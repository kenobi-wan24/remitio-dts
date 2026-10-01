<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Document;
use App\Services\AttachmentService;
use App\Services\DocumentTracker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Workflow v2: rules depend on the chosen step.
 *   submit for review   → lawyer; file required if the document has no file yet
 *   resubmit            → lawyer + corrected file (required)
 *   approve             → which file version is approved (lawyer only — enforced by the allowed actions)
 *   revision required   → comment required (lawyer only)
 *   send for signature  → lawyer
 *   mark as signed      → who signed
 *   release to client   → received by (name) required
 *   archive             → location required
 *   receive back        → remarks required
 *   hand over           → person (not the current holder)
 */
class StoreDocumentMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Document $document */
        $document = $this->route('document');
        $action = $this->input('action');

        $allowed = array_map(
            fn ($a) => $a->value,
            app(DocumentTracker::class)->availableActions($document, $this->user()),
        );

        $hasFiles = $document->attachments()->exists();
        $lastMovementAt = $document->movements()->value('acted_at');
        $activeLawyer = Rule::exists('users', 'id')->where('role', UserRole::Admin->value)->where('is_active', true);

        return [
            'action' => ['required', Rule::in($allowed)],

            'lawyer_id' => [
                Rule::requiredIf(in_array($action, ['submitted_for_review', 'resubmitted', 'sent_for_signature'], true)),
                'nullable', $activeLawyer,
            ],

            'to_user_id' => [
                Rule::requiredIf($action === 'forwarded'),
                'nullable',
                Rule::exists('users', 'id')->where('is_active', true),
                Rule::when($action === 'forwarded' && $document->current_holder_id, [Rule::notIn([$document->current_holder_id])]),
            ],

            'file' => [
                Rule::requiredIf($action === 'resubmitted' || ($action === 'submitted_for_review' && ! $hasFiles)),
                'nullable',
                ...AttachmentService::singleFileRules(),
            ],
            'version_notes' => ['nullable', 'string', 'max:255'],

            'attachment_id' => [
                Rule::requiredIf($action === 'approved'),
                'nullable',
                Rule::exists('document_attachments', 'id')->where('document_id', $document->id),
            ],

            'signed_by' => [Rule::requiredIf($action === 'signed'), 'nullable', Rule::exists('users', 'id')->where('role', UserRole::Admin->value)],

            'received_by' => [Rule::requiredIf($action === 'released_to_client'), 'nullable', 'string', 'max:255'],

            'location' => [Rule::requiredIf($action === 'archived'), 'nullable', 'string', 'max:255'],

            'remarks' => [
                Rule::requiredIf(in_array($action, ['revision_required', 'received'], true)),
                'nullable', 'string', 'max:1000',
            ],

            'acted_at' => array_values(array_filter([
                'nullable', 'date', 'before_or_equal:now',
                $lastMovementAt ? 'after_or_equal:'.$lastMovementAt : null,
            ])),
        ];
    }

    public function attributes(): array
    {
        return [
            'lawyer_id' => 'lawyer',
            'to_user_id' => 'person',
            'attachment_id' => 'approved version',
            'signed_by' => 'signed by',
            'received_by' => 'received by',
            'acted_at' => 'date & time',
        ];
    }

    public function messages(): array
    {
        return [
            'action.in' => 'That step is not available for this document right now.',
            'lawyer_id.required' => 'Choose the lawyer.',
            'to_user_id.required' => 'Choose who has the document now.',
            'to_user_id.not_in' => 'The document is already with this person.',
            'file.required' => 'Upload the draft or corrected file. Each version is kept in the document\'s file history.',
            'attachment_id.required' => 'Choose which file version you are approving.',
            'signed_by.required' => 'Choose who signed the document.',
            'received_by.required' => 'Enter the name of the person who received the document.',
            'location.required' => 'Enter where the document is archived (e.g. Archive Room - Box 4).',
            'remarks.required' => 'Please add remarks for this step.',
            'acted_at.before_or_equal' => 'The date & time cannot be in the future.',
            'acted_at.after_or_equal' => 'The date & time cannot be earlier than the last recorded step.',
            ...AttachmentService::messages(),
            'file.extensions' => 'Allowed file types: PDF, Word, Excel, JPG, PNG.',
            'file.mimetypes' => 'This file does not look like a real PDF, Word, Excel, JPG or PNG file.',
            'file.max' => 'The file must be 10 MB or smaller.',
        ];
    }
}
