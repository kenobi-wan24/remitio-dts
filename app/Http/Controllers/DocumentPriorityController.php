<?php

namespace App\Http\Controllers;

use App\Enums\MovementAction;
use App\Enums\Urgency;
use App\Models\Document;
use App\Services\DocumentTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * R4 — urgency (lawyers only, reason required) and On Hold (anyone handling the document).
 * Access to the document itself is already checked by the record.access middleware.
 */
class DocumentPriorityController extends Controller
{
    public function urgency(Request $request, Document $document, DocumentTracker $tracker): RedirectResponse
    {
        abort_unless($request->user()->isLawyer(), 403, 'Only a lawyer can change the urgency.');

        $data = $request->validateWithBag('priority', [
            'urgency' => ['required', Rule::enum(Urgency::class), Rule::notIn([$document->urgency->value])],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'urgency.not_in' => 'The document already has this urgency.',
            'reason.required' => 'Give the reason for the change (e.g. hearing moved to tomorrow).',
        ]);

        $tracker->changePriority($document, MovementAction::UrgencyChanged, $data, $request->user());

        return back()->with('success', 'Urgency set to '.Urgency::from($data['urgency'])->label().'.');
    }

    public function hold(Request $request, Document $document, DocumentTracker $tracker): RedirectResponse
    {
        if ($document->isOnHold()) {
            $data = $request->validateWithBag('priority', ['reason' => ['nullable', 'string', 'max:500']]);
            $tracker->changePriority($document, MovementAction::Resumed, $data, $request->user());

            return back()->with('success', 'No longer on hold.');
        }

        abort_if($document->status->isFinal(), 422, 'Released or archived documents cannot be put on hold.');

        $data = $request->validateWithBag('priority', ['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Say what it is waiting for (e.g. waiting for the client\'s TCT copy).',
        ]);
        $tracker->changePriority($document, MovementAction::PutOnHold, $data, $request->user());

        return back()->with('success', 'Put on hold.');
    }
}
