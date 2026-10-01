<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'physical_location' => ['nullable', 'string', 'max:255'],
            'date_received' => ['required', 'date', 'before_or_equal:today'],
        ];
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
        ];
    }

    public function messages(): array
    {
        return [
            'legal_case_id.exists' => 'The selected case does not belong to this client.',
        ];
    }

    public function documentData(): array
    {
        return $this->safe()->only($this->fields);
    }
}
