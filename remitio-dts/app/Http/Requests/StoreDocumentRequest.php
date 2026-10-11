<?php

namespace App\Http\Requests;

use App\Services\AttachmentService;
use Illuminate\Validation\Rule;

/**
 * Logging a new request/document = the editable details + who holds it + optional files.
 */
class StoreDocumentRequest extends UpdateDocumentRequest
{
    protected array $fields = [
        'title', 'document_type_id', 'client_id', 'legal_case_id',
        'description', 'physical_location', 'date_received',
        'current_holder_id',
    ];

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'current_holder_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'received_remarks' => ['nullable', 'string', 'max:1000'],
            ...AttachmentService::rules(),
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), ...AttachmentService::messages()];
    }
}
