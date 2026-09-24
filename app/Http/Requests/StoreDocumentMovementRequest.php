<?php

namespace App\Http\Requests;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentTracker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules change depending on the chosen action:
 *   forwarded / returned / received → "Now With" required
 *   status_changed                  → new status required (must differ)
 *   archived                        → location required (e.g. Archive Room - Box 4)
 *   returned / released_to_client   → remarks required
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

        $allowedActions = array_map(
            fn ($a) => $a->value,
            app(DocumentTracker::class)->availableActions($document),
        );

        $workflowStatuses = collect(DocumentStatus::cases())
            ->reject(fn (DocumentStatus $s) => $s->isFinal())
            ->map(fn (DocumentStatus $s) => $s->value)
            ->all();

        // Latest movement time — a new movement can't be dated before it
        $lastMovementAt = $document->movements()->value('acted_at');

        return [
            'action' => ['required', Rule::in($allowedActions)],

            'to_user_id' => [
                Rule::requiredIf(in_array($action, ['forwarded', 'returned', 'received'], true)),
                'nullable',
                Rule::exists('users', 'id')->where('is_active', true),
                Rule::when(
                    $action === 'forwarded' && $document->current_holder_id,
                    [Rule::notIn([$document->current_holder_id])],
                ),
            ],

            'to_status' => [
                Rule::requiredIf($action === 'status_changed'),
                'nullable',
                Rule::in($workflowStatuses),
                Rule::when($action === 'status_changed', [Rule::notIn([$document->status->value])]),
            ],

            'location' => [Rule::requiredIf($action === 'archived'), 'nullable', 'string', 'max:255'],

            'remarks' => [
                Rule::requiredIf(in_array($action, ['returned', 'released_to_client'], true)),
                'nullable',
                'string',
                'max:1000',
            ],

            'acted_at' => array_values(array_filter([
                'nullable',
                'date',
                'before_or_equal:now',
                $lastMovementAt ? 'after_or_equal:'.$lastMovementAt : null,
            ])),
        ];
    }

    public function attributes(): array
    {
        return [
            'to_user_id' => 'person',
            'to_status' => 'new status',
            'acted_at' => 'date & time',
        ];
    }

    public function messages(): array
    {
        return [
            'action.in' => 'That action is not allowed for a document with this status.',
            'to_user_id.required' => 'Choose who has the document now.',
            'to_user_id.not_in' => 'The document is already with this person.',
            'to_status.required' => 'Choose the new status.',
            'to_status.not_in' => 'The document already has this status.',
            'location.required' => 'Enter where the document is archived (e.g. Archive Room - Box 4).',
            'remarks.required' => 'Please add remarks for this action (e.g. who received it or why it was returned).',
            'acted_at.before_or_equal' => 'The date & time cannot be in the future.',
            'acted_at.after_or_equal' => 'The date & time cannot be earlier than the last recorded movement.',
        ];
    }
}
