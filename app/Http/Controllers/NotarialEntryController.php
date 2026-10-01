<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\NotarialEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Workflow v2 — the Notarial Register: a separate record linked to the notarized document.
 * Fields are the four the system already had; the physical register book is the reference
 * for anything added later. Anyone can add an entry; only administrators edit or delete.
 */
class NotarialEntryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'series' => $request->input('series'),
            'book' => $request->input('book'),
            'page' => $request->input('page_no'),
            'doc' => $request->input('doc_no'),
        ];
        $month = in_array((int) $request->input('month'), range(1, 12), true) ? (int) $request->input('month') : null;

        $entries = NotarialEntry::query()
            ->lookup($filters)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->with(['document' => fn ($q) => $q->with(['client' => fn ($c) => $c->withTrashed(), 'documentType:id,name']), 'recorder:id,name'])
            ->orderByDesc('series')
            ->orderBy('book_no')
            ->orderByDesc('page_no')
            ->orderByDesc('doc_no')
            ->paginate(25)
            ->withQueryString();

        return view('notarial.index', [
            'entries' => $entries,
            'filters' => [...$filters, 'month' => $month],
            'seriesOptions' => NotarialEntry::query()->select('series')->groupBy('series')->orderByDesc('series')->pluck('series'),
        ]);
    }

    public function store(Request $request, Document $document): RedirectResponse
    {
        if ($document->notarialEntry()->exists()) {
            return back()->with('error', 'This document already has a Notarial Register entry.');
        }

        if (! $document->status->allowsNotarialEntry()) {
            return back()->with('error', 'A notarial entry can be added once the document is Approved, and before it is Released.');
        }

        $data = $request->validate($this->rules());

        $entry = $document->notarialEntry()->create([...$this->clean($data), 'recorded_by' => $request->user()->id]);

        return back()->with('success', "Recorded in the Notarial Register: {$entry->reference}.");
    }

    public function edit(NotarialEntry $notarialEntry): View
    {
        Gate::authorize('update', $notarialEntry);

        return view('notarial.edit', ['entry' => $notarialEntry->load('document')]);
    }

    public function update(Request $request, NotarialEntry $notarialEntry): RedirectResponse
    {
        Gate::authorize('update', $notarialEntry);

        $notarialEntry->update($this->clean($request->validate($this->rules($notarialEntry))));

        return redirect()->route('documents.show', $notarialEntry->document_id)->with('success', 'Notarial entry updated.');
    }

    public function destroy(NotarialEntry $notarialEntry): RedirectResponse
    {
        Gate::authorize('delete', $notarialEntry);

        $documentId = $notarialEntry->document_id;
        $notarialEntry->delete();

        return redirect()->route('documents.show', $documentId)->with('success', 'Notarial entry removed.');
    }

    /** Same rules as before: all four fields; the same Doc. No. can't repeat within a Book and Series. */
    private function rules(?NotarialEntry $entry = null): array
    {
        return [
            'doc_no' => [
                'required', 'integer', 'min:1', 'max:999999',
                Rule::unique('notarial_entries', 'doc_no')
                    ->where('series', request()->input('series'))
                    ->where('book_no', strtoupper(trim((string) request()->input('book_no'))))
                    ->ignore($entry),
            ],
            'page_no' => ['required', 'integer', 'min:1', 'max:99999'],
            'book_no' => ['required', 'string', 'max:10'],
            'series' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
        ];
    }

    private function clean(array $data): array
    {
        $data['book_no'] = strtoupper(trim($data['book_no']));

        return $data;
    }
}
