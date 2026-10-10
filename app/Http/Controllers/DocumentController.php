<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\DocumentTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private AttachmentService $attachments)
    {
    }

    public function index(Request $request): View
    {
        $showTrashed = in_array($request->input('view'), ['deleted', 'trash'], true) && $request->user()->isAdmin();

        $filters = [
            'status' => in_array($request->input('status'), DocumentStatus::values(), true) ? $request->input('status') : null,
            'type' => is_numeric($request->input('type')) ? (int) $request->input('type') : null,
            'holder' => $request->input('holder'),
            // R1/R2: in process vs completed (matches the dashboard), or out of the office right now
            'show' => in_array($request->input('show'), ['in_process', 'completed', 'out', 'on_hold'], true) ? $request->input('show') : null,
            // R4: "priority" sort; it is the default when looking at your own queue
            'sort' => in_array($request->input('sort'), ['priority', 'newest', 'oldest'], true)
                ? $request->input('sort')
                : ($request->input('holder') === 'me' ? 'priority' : 'newest'),
            'urgency' => in_array($request->input('urgency'), \App\Enums\Urgency::values(), true) ? $request->input('urgency') : null,
            // Notarial register lookup (Workflow v2: through the linked register entry)
            'notarial' => array_filter($request->only(['n_series', 'n_book', 'n_page', 'n_doc'])),
        ];

        $holderId = match (true) {
            $filters['holder'] === 'me' => $request->user()->id,
            is_numeric($filters['holder']) => (int) $filters['holder'],
            default => null,
        };

        // Base filters (everything except status)
        $base = Document::query()
            ->visibleTo($request->user())   // R3
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->search($request->input('q'))
            ->when($filters['type'], fn ($q, $type) => $q->where('document_type_id', $type))
            ->when($holderId, fn ($q, $id) => $q->heldBy($id))
            ->when($filters['show'] === 'in_process', fn ($q) => $q->open())
            ->when($filters['show'] === 'completed', fn ($q) => $q->whereIn('status', DocumentStatus::finalValues()))
            ->when($filters['show'] === 'out', fn ($q) => $q->outOfOffice())
            ->when($filters['show'] === 'on_hold', fn ($q) => $q->whereNotNull('on_hold_since'))
            ->when($filters['urgency'], fn ($q, $u) => $q->where('urgency', $u))
            ->notarialLookup([
                'series' => $filters['notarial']['n_series'] ?? null,
                'book' => isset($filters['notarial']['n_book']) ? strtoupper(trim($filters['notarial']['n_book'])) : null,
                'page' => $filters['notarial']['n_page'] ?? null,
                'doc' => $filters['notarial']['n_doc'] ?? null,
            ]);

        $statusCounts = (clone $base)
            ->toBase()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $query = $base
            ->with([
                'documentType:id,name',
                'client' => fn ($q) => $q->withTrashed(),
                'legalCase' => fn ($q) => $q->withTrashed()->select('id', 'case_code'),
                'currentHolder:id,name',
                'notarialEntry',
            ])
            ->withCount('attachments')
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status));

        match ($filters['sort']) {
            'priority' => $query->byPriority(),
            'oldest' => $query->orderBy('date_received')->orderBy('id'),
            default => $query->orderByDesc('date_received')->orderByDesc('id'),
        };

        return view('documents.index', [
            'documents' => $query->paginate(20)->withQueryString(),
            'showTrashed' => $showTrashed,
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'documentTypes' => DocumentType::orderBy('name')->pluck('name', 'id'),
            'users' => User::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $document = new Document([
            'client_id' => $request->integer('client_id') ?: null,
            'legal_case_id' => $request->integer('legal_case_id') ?: null,
            'date_received' => today(),
            'current_holder_id' => $request->user()->id,
        ]);

        return view('documents.create', ['document' => $document, ...$this->formOptions()]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Phase 8: warn about a likely duplicate before recording
        if (! $request->boolean('confirm_duplicate') && ($duplicates = $this->possibleDuplicates($request->documentData()))) {
            return back()->withInput()->with('duplicateDocuments', $duplicates);
        }

        $document = DB::transaction(function () use ($request, $user) {
            $document = Document::create([
                ...$request->documentData(),
                // R4: a new document starts with its case's urgency (lawyers can change it afterwards)
                'urgency' => LegalCase::find($request->documentData()['legal_case_id'] ?? null)?->urgency ?? \App\Enums\Urgency::Normal,
                'status' => DocumentStatus::Received,
                'created_by' => $user->id,
            ]);

            // Every document starts its history with a "received" movement
            $document->movements()->create([
                'action' => MovementAction::Received,
                'to_user_id' => $document->current_holder_id,
                'to_status' => DocumentStatus::Received,
                'location' => $document->physical_location,
                'remarks' => $request->input('received_remarks') ?: 'Document received and recorded.',
                'acted_by' => $user->id,
                'acted_at' => now(),
            ]);

            $this->attachments->storeFiles($document, $request->file('attachments', []), $user);

            // 11C: parties to the document (Agreements and Deeds need 2+)
            $document->parties()->sync($request->partiesData());

            return $document;
        });

        return redirect()
            ->route('documents.show', $document)
            ->with('success', "Document recorded. Tracking code: {$document->tracking_code}");
    }

    public function show(Document $document): View
    {
        $document->load([
            'documentType',
            'client' => fn ($q) => $q->withTrashed(),
            'legalCase' => fn ($q) => $q->withTrashed(),
            'currentHolder:id,name',
            'creator:id,name',
            'attachments.uploader:id,name',
            'movements.actor:id,name',
            'movements.fromUser:id,name',
            'movements.toUser:id,name',
            'notarialEntry.recorder:id,name',
            'notarialEntry.notary:id,name',
            'parties.validIds',
            'client.validIds',
        ]);

        $tracker = app(DocumentTracker::class);
        $user = request()->user();

        // Latest version of each file — the choices when the lawyer approves
        $approvable = $document->attachments
            ->groupBy(fn ($a) => $a->version_group_id ?? $a->id)
            ->map(fn ($files) => $files->sortByDesc('version')->first())
            ->sortByDesc('id')
            ->mapWithKeys(fn ($a) => [$a->id => "{$a->original_name} (v{$a->version})"]);

        return view('documents.show', [
            'document' => $document,
            // Workflow v2: next-step buttons and their form data
            'nextSteps' => $tracker->nextSteps($document, $user),
            'availableActions' => $tracker->availableActions($document, $user),
            'users' => User::active()->orderBy('name')->pluck('name', 'id'),
            'lawyers' => User::attorneys()->active()->orderBy('name')->pluck('name', 'id'),
            'defaultLawyerId' => $tracker->defaultLawyerId($document),
            'approvable' => $approvable,
            'keyDates' => $document->keyDates(),
            // 11C: notarial entry pre-fill — the principals are the parties (or the client), with their latest valid ID
            'principalsPrefill' => ($document->parties->isNotEmpty() ? $document->parties : collect([$document->client])->filter())
                ->map(fn (Client $c) => [
                    'name' => $c->display_name,
                    'address' => (string) $c->address,
                    'evidence' => (string) $c->validIds->first()?->label,
                ])->values()->all(),
        ]);
    }

    public function edit(Document $document): View
    {
        return view('documents.edit', ['document' => $document, ...$this->formOptions($document)]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        DB::transaction(function () use ($request, $document) {
            $document->update($request->documentData());
            $document->parties()->sync($request->partiesData()); // 11C
        });

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Document details updated.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        return redirect()
            ->route('documents.index')
            ->with('success', "Document {$document->tracking_code} was moved to Removed entries.");
    }

    public function restore(Document $document): RedirectResponse
    {
        Gate::authorize('restore', $document);

        $document->restore();

        return redirect()
            ->route('documents.show', $document)
            ->with('success', "Document {$document->tracking_code} has been restored.");
    }

    /**
     * Same client + same document type + same title, received within 30 days
     * of each other → probably the same document recorded twice.
     */
    private function possibleDuplicates(array $data): array
    {
        $received = Carbon::parse($data['date_received']);

        return Document::query()
            ->visibleTo(auth()->user())   // R3
            ->where('client_id', $data['client_id'])
            ->where('document_type_id', $data['document_type_id'])
            ->where('title', $data['title'])
            ->whereBetween('date_received', [$received->copy()->subDays(30)->toDateString(), $received->copy()->addDays(30)->toDateString()])
            ->take(5)
            ->get()
            ->map(fn (Document $doc) => [
                'id' => $doc->id,
                'code' => $doc->tracking_code,
                'title' => $doc->title,
                'status' => $doc->status->label(),
                'received' => $doc->date_received->format('M d, Y'),
            ])
            ->all();
    }

    /** Dropdown data shared by create & edit. */
    private function formOptions(?Document $document = null): array
    {
        $documentTypes = DocumentType::query()
            ->where(fn ($q) => $q->active())
            ->when($document?->document_type_id, fn ($q, $id) => $q->orWhere('id', $id))
            ->orderBy('name')
            ->pluck('name', 'id');

        $clients = Client::query()
            ->orderByRaw('COALESCE(company_name, last_name)')
            ->orderBy('first_name')
            ->get(['id', 'client_code', 'client_type', 'first_name', 'last_name', 'company_name'])
            ->mapWithKeys(fn (Client $client) => [$client->id => "{$client->display_name} · {$client->client_code}"]);

        // { client_id: [ {id, label}, ... ] } — powers the dependent "Case" dropdown
        $casesByClient = LegalCase::query()
            ->visibleTo(auth()->user())   // R3: only cases you may see
            ->orderByDesc('date_opened')
            ->get(['id', 'client_id', 'case_code', 'title', 'status'])
            ->groupBy('client_id')
            ->map(fn ($cases) => $cases->map(fn (LegalCase $case) => [
                'id' => (string) $case->id,
                'label' => $case->case_code.' · '.Str::limit($case->title, 60)
                    .($case->status === CaseStatus::Closed ? ' (closed)' : ''),
            ])->values());

        $users = User::active()->orderBy('name')->pluck('name', 'id');

        // 11C: how many parties each document type needs, and the parties already on this document
        $minPartiesByType = DocumentType::query()->pluck('min_parties', 'id');
        $initialParties = $document
            ? $document->parties->map(fn (Client $p) => ['client_id' => (string) $p->id, 'role' => (string) $p->pivot->role])->values()->all()
            : [];

        return compact('documentTypes', 'clients', 'casesByClient', 'users', 'minPartiesByType', 'initialParties');
    }
}
