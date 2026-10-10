<?php

namespace App\Http\Requests;

use App\Enums\MovementAction;
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
 *   send out of office  → destination, purpose, carried by (R2); remarks if purpose is "Other"
 *   returned to office  → optional: where it is kept now, brought back by
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
        $activeLawyer = Rule::exists('users', 'id')->whereIn('role', UserRole::lawyerValues())->where('is_active', true);

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

            'signed_by' => [Rule::requiredIf($action === 'signed'), 'nullable', Rule::exists('users', 'id')->whereIn('role', UserRole::lawyerValues())],

            'received_by' => [Rule::requiredIf($action === 'released_to_client'), 'nullable', 'string', 'max:255'],

            'location' => [Rule::requiredIf($action === 'archived'), 'nullable', 'string', 'max:255'],

            // 11C: correcting a wrong step — the right status, different from the current one
            'corrected_status' => [
                Rule::requiredIf($action === 'status_corrected'),
                'nullable',
                Rule::in(\App\Enums\DocumentStatus::values()),
                Rule::when($action === 'status_corrected', [Rule::notIn([$document->status->value])]),
            ],

            // R2: physical copy leaving the office
            'destination' => [Rule::requiredIf($action === 'sent_out'), 'nullable', 'string', 'max:255'],
            'purpose' => [Rule::requiredIf($action === 'sent_out'), 'nullable', Rule::in(MovementAction::OUT_PURPOSES)],
            'carried_by' => [Rule::requiredIf($action === 'sent_out'), 'nullable', 'string', 'max:255'],
            'expected_back' => ['nullable', 'date', 'after_or_equal:today'],

            'remarks' => [
                Rule::requiredIf(in_array($action, ['revision_required', 'received', 'status_corrected'], true)
                    || ($action === 'sent_out' && $this->input('purpose') === 'Other')),
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
            'carried_by' => 'carried by',
            'expected_back' => 'expected return date',
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
            'corrected_status.required' => 'Choose the correct status.',
            'destination.required' => 'Enter where the document is being brought (e.g. RTC Branch 12, Davao City).',
            'purpose.required' => 'Choose why the document is leaving the office.',
            'carried_by.required' => 'Enter who is carrying the document.',
            'expected_back.after_or_equal' => 'The expected return date cannot be in the past.',
            'corrected_status.not_in' => 'The document already has this status.',
            ...AttachmentService::messages(),
            'file.extensions' => 'Allowed file types: PDF, Word, Excel, JPG, PNG.',
            'file.mimetypes' => 'This file does not look like a real PDF, Word, Excel, JPG or PNG file.',
            'file.max' => 'The file must be 10 MB or smaller.',
        ];
    }
}
