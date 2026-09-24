<?php

namespace App\Http\Controllers;

use App\Enums\MovementAction;
use App\Http\Requests\StoreDocumentMovementRequest;
use App\Models\Document;
use App\Services\DocumentTracker;
use Illuminate\Http\RedirectResponse;

/**
 * Movements are append-only: there is deliberately no edit/update/delete here.
 */
class DocumentMovementController extends Controller
{
    public function store(StoreDocumentMovementRequest $request, Document $document, DocumentTracker $tracker): RedirectResponse
    {
        $movement = $tracker->record(
            $document,
            MovementAction::from($request->validated('action')),
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', $tracker->summary($movement));
    }
}
