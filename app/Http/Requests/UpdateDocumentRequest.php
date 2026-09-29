<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a document's details. Status, holder and location changes are NOT
 * edited here — they go through movements (Phase 6) so history stays complete.
 */
class UpdateDocumentRequest extends FormRequest
{
    /** Columns this form is allowed to write. */
    protected array $fields = [
        'title', 'document_type_id', 'client_id', 'legal_case_id',
        'description', 'physical_location', 'date_received', 'due_date',
        'notarial_doc_no', 'notarial_page_no', 'notarial_book_no', 'notarial_series',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $notarial = 'notarial_doc_no,notarial_page_no,notarial_book_no,notarial_series';

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
            'due_date' => ['nullable', 'date', 'after_or_equal:date_received'],

            // Phase 8: notarial register reference — all four or none
            'notarial_doc_no' => [
                'nullable', 'integer', 'min:1', 'max:999999', "required_with:{$notarial}",
                Rule::unique('documents', 'notarial_doc_no')
                    ->where('notarial_series', $this->input('notarial_series'))
                    ->where('notarial_book_no', $this->input('notarial_book_no'))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('document')?->id),
            ],
            'notarial_page_no' => ['nullable', 'integer', 'min:1', 'max:99999', "required_with:{$notarial}"],
            'notarial_book_no' => ['nullable', 'string', 'max:10', "required_with:{$notarial}"],
            'notarial_series' => ['nullable', 'integer', 'min:1990', 'max:'.(now()->year + 1), "required_with:{$notarial}"],
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
            'due_date' => 'due date',
            'current_holder_id' => 'person holding the document',
            'notarial_doc_no' => 'Doc. No.',
            'notarial_page_no' => 'Page No.',
            'notarial_book_no' => 'Book No.',
            'notarial_series' => 'Series (year)',
        ];
    }

    public function messages(): array
    {
        return [
            'legal_case_id.exists' => 'The selected case does not belong to this client.',
            'notarial_doc_no.unique' => 'This Doc. No. is already used in the same Book and Series.',
            'required_with' => 'Fill in all four notarial fields (Doc., Page, Book, Series) or leave them all blank.',
        ];
    }

    public function documentData(): array
    {
        $data = $this->safe()->only($this->fields);

        if (isset($data['notarial_book_no'])) {
            $data['notarial_book_no'] = strtoupper(trim($data['notarial_book_no']));
        }

        return $data;
    }
}
