<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Http\Requests\StoreLegalCaseRequest;
use App\Http\Requests\UpdateLegalCaseRequest;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Routes: /cases  (route names cases.*, route parameter {legal_case})
 */
class LegalCaseController extends Controller
{
    public function index(Request $request): View
    {
        $showTrashed = $request->input('view') === 'trash' && $request->user()->isAdmin();

        $filters = [
            'status' => in_array($request->input('status'), CaseStatus::values(), true) ? $request->input('status') : null,
            'type' => in_array($request->input('type'), CaseType::values(), true) ? $request->input('type') : null,
            'attorney' => $request->input('attorney'),
            'sort' => in_array($request->input('sort'), ['newest', 'oldest', 'title'], true) ? $request->input('sort') : 'newest',
        ];

        $attorneyId = match (true) {
            $filters['attorney'] === 'me' => $request->user()->id,
            is_numeric($filters['attorney']) => (int) $filters['attorney'],
            default => null,
        };

        // Base filters (everything except status)
        $base = LegalCase::query()
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->search($request->input('q'))
            ->when($filters['type'], fn ($q, $type) => $q->where('case_type', $type))
            ->when($attorneyId, fn ($q, $id) => $q->where('handling_attorney_id', $id));

        // Counts per status for the tabs (respecting the other filters)
        $statusCounts = (clone $base)
            ->toBase()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $query = $base
            ->with(['client' => fn ($q) => $q->withTrashed(), 'attorney:id,name'])
            ->withCount('documents')
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status));

        match ($filters['sort']) {
            'oldest' => $query->orderBy('date_opened')->orderBy('id'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('date_opened')->orderByDesc('id'),
        };

        $cases = $query->paginate(15)->withQueryString();
        $attorneys = User::attorneys()->orderBy('name')->pluck('name', 'id');

        return view('cases.index', compact('cases', 'showTrashed', 'filters', 'statusCounts', 'attorneys'));
    }

    public function create(Request $request): View
    {
        $case = new LegalCase([
            'client_id' => $request->integer('client_id') ?: null,
            'status' => CaseStatus::Open,
            'date_opened' => today(),
            'handling_attorney_id' => $request->user()->isAdmin() ? $request->user()->id : null,
        ]);

        return view('cases.create', ['case' => $case, ...$this->formOptions()]);
    }

    public function store(StoreLegalCaseRequest $request): RedirectResponse
    {
        $case = LegalCase::create([...$request->caseData(), 'created_by' => $request->user()->id]);

        return redirect()
            ->route('cases.show', $case)
            ->with('success', "Case {$case->case_code} has been opened.");
    }

    public function show(LegalCase $legalCase): View
    {
        $legalCase->load([
            'client' => fn ($q) => $q->withTrashed(),
            'attorney:id,name',
            'creator:id,name',
        ]);

        $documents = $legalCase->documents()
            ->with(['documentType:id,name', 'currentHolder:id,name'])
            ->latest('date_received')
            ->latest('id')
            ->get();

        $stats = [
            'total' => $documents->count(),
            'open' => $documents->reject(fn ($doc) => $doc->status->isFinal())->count(),
            'overdue' => $documents->filter(fn ($doc) => $doc->is_overdue)->count(),
        ];

        return view('cases.show', ['case' => $legalCase, 'documents' => $documents, 'stats' => $stats]);
    }

    public function edit(LegalCase $legalCase): View
    {
        return view('cases.edit', ['case' => $legalCase, ...$this->formOptions($legalCase)]);
    }

    public function update(UpdateLegalCaseRequest $request, LegalCase $legalCase): RedirectResponse
    {
        $legalCase->update($request->caseData());

        return redirect()
            ->route('cases.show', $legalCase)
            ->with('success', 'Case details updated.');
    }

    public function destroy(LegalCase $legalCase): RedirectResponse
    {
        Gate::authorize('delete', $legalCase);

        if ($legalCase->documents()->exists()) {
            return back()->with('error', 'This case still has documents on record, so it cannot be deleted. Close the case instead.');
        }

        $legalCase->delete();

        return redirect()
            ->route('cases.index')
            ->with('success', "Case {$legalCase->case_code} was moved to trash.");
    }

    public function restore(LegalCase $legalCase): RedirectResponse
    {
        Gate::authorize('restore', $legalCase);

        $legalCase->restore();

        return redirect()
            ->route('cases.show', $legalCase)
            ->with('success', "Case {$legalCase->case_code} has been restored.");
    }

    /** Dropdown data shared by create & edit. */
    private function formOptions(?LegalCase $case = null): array
    {
        $clients = Client::query()
            ->orderByRaw('COALESCE(company_name, last_name)')
            ->orderBy('first_name')
            ->get(['id', 'client_code', 'client_type', 'first_name', 'last_name', 'company_name'])
            ->mapWithKeys(fn (Client $client) => [$client->id => "{$client->display_name} · {$client->client_code}"]);

        // Active attorneys, plus the current one even if since deactivated
        $attorneys = User::query()
            ->where(fn ($q) => $q->attorneys()->active())
            ->when($case?->handling_attorney_id, fn ($q, $id) => $q->orWhere('id', $id))
            ->orderBy('name')
            ->pluck('name', 'id');

        return compact('clients', 'attorneys');
    }
}
