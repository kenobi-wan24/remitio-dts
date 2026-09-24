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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $showTrashed = $request->input('view') === 'trash' && $request->user()->isAdmin();

        $filters = [
            'status' => in_array($request->input('status'), DocumentStatus::values(), true) ? $request->input('status') : null,
            'type' => is_numeric($request->input('type')) ? (int) $request->input('type') : null,
            'holder' => $request->input('holder'),
            'due' => in_array($request->input('due'), ['overdue', 'soon', 'week'], true) ? $request->input('due') : null,
            'sort' => in_array($request->input('sort'), ['newest', 'oldest', 'due'], true) ? $request->input('sort') : 'newest',
        ];

        $holderId = match (true) {
            $filters['holder'] === 'me' => $request->user()->id,
            is_numeric($filters['holder']) => (int) $filters['holder'],
            default => null,
        };

        // Base filters (everything except status)
        $base = Document::query()
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->search($request->input('q'))
            ->when($filters['type'], fn ($q, $type) => $q->where('document_type_id', $type))
            ->when($holderId, fn ($q, $id) => $q->heldBy($id))
            ->when($filters['due'], fn ($q, $due) => match ($due) {
                'overdue' => $q->overdue(),
                'soon' => $q->dueWithin(3),
                'week' => $q->dueWithin(7),
            });

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
            ])
            ->withCount('attachments')
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status));

        match ($filters['sort']) {
            'oldest' => $query->orderBy('date_received')->orderBy('id'),
            'due' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id'),
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

        $document = DB::transaction(function () use ($request, $user) {
            $document = Document::create([
                ...$request->documentData(),
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
        ]);

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document): View
    {
        return view('documents.edit', ['document' => $document, ...$this->formOptions($document)]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $document->update($request->documentData());

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
            ->with('success', "Document {$document->tracking_code} was moved to trash.");
    }

    public function restore(Document $document): RedirectResponse
    {
        Gate::authorize('restore', $document);

        $document->restore();

        return redirect()
            ->route('documents.show', $document)
            ->with('success', "Document {$document->tracking_code} has been restored.");
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
            ->orderByDesc('date_opened')
            ->get(['id', 'client_id', 'case_code', 'title', 'status'])
            ->groupBy('client_id')
            ->map(fn ($cases) => $cases->map(fn (LegalCase $case) => [
                'id' => (string) $case->id,
                'label' => $case->case_code.' · '.Str::limit($case->title, 60)
                    .($case->status === CaseStatus::Closed ? ' (closed)' : ''),
            ])->values());

        $users = User::active()->orderBy('name')->pluck('name', 'id');

        return compact('documentTypes', 'clients', 'casesByClient', 'users');
    }
}
