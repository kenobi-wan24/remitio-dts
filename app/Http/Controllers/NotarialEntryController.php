<?php

namespace App\Http\Controllers;

use App\Enums\NotarialActType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\NotarialEntry;
use App\Models\User;
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
        // R2: name search + instrument type + notarized date range
        $type = is_numeric($request->input('type')) ? (int) $request->input('type') : null;
        $from = $request->date('from');
        $to = $request->date('to');

        $entries = NotarialEntry::query()
            ->lookup($filters)
            ->searchName($request->input('name'))
            ->when($type, fn ($q) => $q->whereHas('document', fn ($d) => $d->where('document_type_id', $type)))
            ->when($from, fn ($q) => $q->whereDate('notarized_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('notarized_on', '<=', $to))
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->when($request->input('notary'), fn ($q, $id) => $q->where('notary_id', (int) $id))
            ->with(['document' => fn ($q) => $q->with(['client' => fn ($c) => $c->withTrashed(), 'documentType:id,name']), 'recorder:id,name', 'notary:id,name'])
            ->orderByDesc('series')
            ->orderBy('book_no')
            ->orderByDesc('page_no')
            ->orderByDesc('doc_no')
            ->paginate(25)
            ->withQueryString();

        // R3: the register book lists every entry (like the physical book, which staff keep),
        // but a confidential document is only linked for people who may open it.
        $visibleDocumentIds = Document::withTrashed()
            ->visibleTo($request->user())
            ->whereIn('id', $entries->pluck('document_id'))
            ->pluck('id');

        return view('notarial.index', [
            'entries' => $entries,
            'visibleDocumentIds' => $visibleDocumentIds,
            'filters' => [...$filters, 'month' => $month, 'notary' => $request->input('notary'),
                'name' => $request->input('name'), 'type' => $type, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'documentTypes' => \App\Models\DocumentType::orderBy('name')->pluck('name', 'id'),
            'lawyers' => User::attorneys()->orderBy('name')->pluck('name', 'id'),
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

        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        $entry = $document->notarialEntry()->create([...$this->clean($data), 'recorded_by' => $request->user()->id]);

        return back()->with('success', "Recorded in the Notarial Register: {$entry->reference}.");
    }

    public function edit(NotarialEntry $notarialEntry): View
    {
        Gate::authorize('update', $notarialEntry);

        return view('notarial.edit', [
            'entry' => $notarialEntry->load('document'),
            'lawyers' => User::attorneys()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, NotarialEntry $notarialEntry): RedirectResponse
    {
        Gate::authorize('update', $notarialEntry);

        $notarialEntry->update($this->clean($request->validate($this->rules($notarialEntry), $this->messages(), $this->attributes())));

        return redirect()->route('documents.show', $notarialEntry->document_id)->with('success', 'Notarial entry updated.');
    }

    public function destroy(NotarialEntry $notarialEntry): RedirectResponse
    {
        Gate::authorize('delete', $notarialEntry);

        $documentId = $notarialEntry->document_id;
        $notarialEntry->delete();

        return redirect()->route('documents.show', $documentId)->with('success', 'Notarial entry removed.');
    }

    /**
     * The register book's columns (Rules on Notarial Practice). The same Doc. No. can't repeat
     * within one notary's Book and Series.
     */
    private function rules(?NotarialEntry $entry = null): array
    {
        return [
            'notary_id' => ['required', Rule::exists('users', 'id')->whereIn('role', UserRole::lawyerValues())],
            'notarized_on' => ['required', 'date', 'before_or_equal:today'],
            'act_type' => ['required', Rule::enum(NotarialActType::class)],
            'doc_no' => [
                'required', 'integer', 'min:1', 'max:999999',
                Rule::unique('notarial_entries', 'doc_no')
                    ->where('notary_id', request()->input('notary_id'))
                    ->where('series', request()->input('series'))
                    ->where('book_no', strtoupper(trim((string) request()->input('book_no'))))
                    ->ignore($entry),
            ],
            'page_no' => ['required', 'integer', 'min:1', 'max:99999'],
            'book_no' => ['required', 'string', 'max:10'],
            'series' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'principals' => ['required', 'array', 'min:1', 'max:10'],
            'principals.*.name' => ['required', 'string', 'max:255'],
            'principals.*.address' => ['nullable', 'string', 'max:255'],
            'principals.*.evidence' => ['nullable', 'string', 'max:255'],
            'witnesses' => ['nullable', 'array', 'max:4'],
            'witnesses.*.name' => ['nullable', 'string', 'max:255'],
            'witnesses.*.address' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'doc_no.unique' => 'This Doc. No. is already used in this notary\'s Book and Series.',
            'principals.required' => 'Add at least one principal (the person who signed before the notary).',
        ];
    }

    private function attributes(): array
    {
        return [
            'notary_id' => 'notary public',
            'notarized_on' => 'date notarized',
            'act_type' => 'type of notarial act',
            'principals.*.name' => 'principal\'s name',
            'principals.*.address' => 'principal\'s address',
            'principals.*.evidence' => 'evidence of identity',
            'witnesses.*.name' => 'witness name',
        ];
    }

    /** Capital book numbers; principals and witnesses copied as plain text (empty witness rows dropped). */
    private function clean(array $data): array
    {
        $data['book_no'] = strtoupper(trim($data['book_no']));
        $data['principals'] = collect($data['principals'])->map(fn ($p) => [
            'name' => trim($p['name']),
            'address' => trim((string) ($p['address'] ?? '')),
            'evidence' => trim((string) ($p['evidence'] ?? '')),
        ])->values()->all();
        $data['witnesses'] = collect($data['witnesses'] ?? [])
            ->filter(fn ($w) => filled($w['name'] ?? null))
            ->map(fn ($w) => ['name' => trim($w['name']), 'address' => trim((string) ($w['address'] ?? ''))])
            ->values()->all();

        return $data;
    }
}
