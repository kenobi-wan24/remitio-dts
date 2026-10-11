<?php

namespace App\Http\Requests;

use App\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Editing a document's details. Status, holder and location change only through
 * tracking steps; notarial details live in the Notarial Register (Workflow v2).
 */
class UpdateDocumentRequest extends FormRequest
{
    /** Columns this form is allowed to write. */
    protected array $fields = [
        'title', 'document_type_id', 'client_id', 'legal_case_id',
        'description', 'physical_location', 'date_received',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'document_type_id' => ['required', Rule::exists('document_types', 'id')],
            'client_id' => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'legal_case_id' => [
                'nullable',
                Rule::exists('legal_cases', 'id')
                    ->where('client_id', $this->input('client_id'))
                    ->whereNull('deleted_at'),
                // R3: you can only file a document under a case you may see
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && ! \App\Models\LegalCase::query()->whereKey($value)->visibleTo($this->user())->exists()) {
                        $fail('The selected case is confidential or not assigned to you.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_confidential' => ['nullable', 'boolean'], // R3: applied only for lawyers (see documentData)
            'deadline' => ['nullable', 'date'],            // R4: used only when DTS_DEADLINES=true
            'physical_location' => ['nullable', 'string', 'max:255'],
            'date_received' => ['required', 'date', 'before_or_equal:today'],

            // 11C: parties to the document (each a client) — Agreements and Deeds need at least 2
            'parties' => ['nullable', 'array', 'max:10'],
            'parties.*.client_id' => ['required', 'distinct', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'parties.*.role' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** 11C: enforce the document type's minimum number of parties. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $type = DocumentType::find($this->input('document_type_id'));
            $min = $type?->min_parties ?? 1;
            $count = collect($this->input('parties', []))->pluck('client_id')->filter()->unique()->count();

            if ($min >= 2 && $count < $min) {
                $validator->errors()->add('parties', "{$type->name} documents need at least {$min} parties. Add each party below (e.g. First Party and Second Party).");
            }
        }];
    }

    /** 11C: [client_id => ['role' => …, 'position' => …]] for $document->parties()->sync() */
    public function partiesData(): array
    {
        return collect($this->validated('parties') ?? [])
            ->values()
            ->mapWithKeys(fn ($party, $i) => [(int) $party['client_id'] => [
                'role' => filled($party['role'] ?? null) ? trim($party['role']) : null,
                'position' => $i,
            ]])
            ->all();
    }

    public function attributes(): array
    {
        return [
            'document_type_id' => 'document type',
            'client_id' => 'client',
            'legal_case_id' => 'case',
            'physical_location' => 'physical location',
            'date_received' => 'date received',
            'current_holder_id' => 'person holding the document',
            'parties.*.client_id' => 'party',
            'parties.*.role' => 'party role',
        ];
    }

    public function messages(): array
    {
        return [
            'legal_case_id.exists' => 'The selected case does not belong to this client.',
            'parties.*.client_id.distinct' => 'The same client is listed twice as a party.',
            'parties.*.client_id.required' => 'Choose a client for each party row, or remove the empty row.',
        ];
    }

    public function documentData(): array
    {
        $data = $this->safe()->only($this->fields);

        // R4: optional deadline, only when the firm turned deadlines on
        if (config('dts.deadlines')) {
            $data['deadline'] = $this->safe()->deadline ?: null;
        }

        // R3: only lawyers decide confidentiality; for staff the current value is left untouched
        if ($this->user()->isLawyer()) {
            $data['is_confidential'] = $this->boolean('is_confidential');
        }

        return $data;
    }
}
