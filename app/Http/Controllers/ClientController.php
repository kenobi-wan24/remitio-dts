<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\ClientId;
use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $showTrashed = in_array($request->input('view'), ['deleted', 'trash'], true) && $request->user()->isAdmin();
        $type = in_array($request->input('type'), ClientType::values(), true) ? $request->input('type') : null;
        $sort = $request->input('sort', 'name');
        $engagement = in_array($request->input('engagement'), ['retainer', 'regular', 'walk_in'], true) ? $request->input('engagement') : null;

        $clients = Client::query()
            ->when($showTrashed, fn ($query) => $query->onlyTrashed())
            ->search($request->input('q'))
            ->when($type, fn ($query) => $query->where('client_type', $type))
            ->when($engagement === 'retainer', fn ($query) => $query->retainer())
            ->when($engagement === 'regular', fn ($query) => $query->retainer(false)->walkIn(false))
            ->when($engagement === 'walk_in', fn ($query) => $query->walkIn())
            ->withCount(['cases', 'documents'])
            ->when(
                $sort === 'newest',
                fn ($query) => $query->latest()->latest('id'),
                fn ($query) => $query->orderByRaw('COALESCE(company_name, last_name)')->orderBy('first_name'),
            )
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients', 'showTrashed', 'type', 'sort', 'engagement'));
    }

    public function create(): View
    {
        return view('clients.create', [
            'client' => new Client(['client_type' => ClientType::Individual]),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $data = $request->clientData();

        if ($redirect = $this->stopIfPossibleDuplicate($request, $data)) {
            return $redirect;
        }

        $client = DB::transaction(function () use ($request, $data) {
            $client = Client::create([...$data, 'created_by' => $request->user()->id]);

            // ID entered on the new-client form (required for walk-ins)
            if ($id = $request->idData()) {
                ClientId::recordFor($client, $id['id_type'], $id['id_number'], $id['scan'], $request->user()->id);
            }

            return $client;
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('success', "Client {$client->client_code} ({$client->display_name}) has been added.");
    }

    public function show(Client $client): View
    {
        $client->load(['creator:id,name', 'validIds.creator:id,name']);

        $user = auth()->user();

        $cases = $client->cases()
            ->visibleTo($user)   // R3
            ->with('attorney:id,name')
            ->withCount(['documents' => fn ($q) => $q->visibleTo($user)])
            ->latest('date_opened')
            ->get();

        // 11C: documents requested by this client OR where they are one of the parties
        $documentsQuery = Document::query()
            ->visibleTo($user)   // R3
            ->where(fn ($q) => $q->where('client_id', $client->id)
                ->orWhereHas('parties', fn ($p) => $p->whereKey($client->id)));

        $documents = (clone $documentsQuery)
            ->with(['documentType:id,name', 'currentHolder:id,name', 'legalCase:id,case_code,title'])
            ->latest('date_received')
            ->latest('id')
            ->take(10)
            ->get();

        $documentsCount = $documentsQuery->count();

        return view('clients.show', compact('client', 'cases', 'documents', 'documentsCount'));
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', compact('client'));
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $data = $request->clientData();

        if ($redirect = $this->stopIfPossibleDuplicate($request, $data, $client->id)) {
            return $redirect;
        }

        // A client with cases can't become a walk-in (notarization only) client
        if ($data['notarization_only'] && $client->cases()->exists()) {
            return back()->withInput()->withErrors([
                'category' => 'This client already has cases, so they cannot be changed to a walk-in (notarization only) client.',
            ]);
        }

        $client->update($data);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client details updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);

        if ($client->cases()->exists() || $client->documents()->exists() || $client->partyDocuments()->exists()) {
            return back()->with('error', 'This client still has cases or documents on record, so it cannot be deleted.');
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->client_code} was moved to Removed entries.");
    }

    public function restore(Client $client): RedirectResponse
    {
        Gate::authorize('restore', $client);

        $client->restore();

        return redirect()
            ->route('clients.show', $client)
            ->with('success', "Client {$client->client_code} has been restored.");
    }

    /**
     * If similar clients exist and the user hasn't ticked "save anyway",
     * send them back to the form with a list of the possible duplicates.
     */
    private function stopIfPossibleDuplicate(Request $request, array $data, ?int $ignoreId = null): ?RedirectResponse
    {
        if ($request->boolean('confirm_duplicate')) {
            return null;
        }

        $duplicates = Client::possibleDuplicatesOf($data, $ignoreId)->limit(5)->get();

        if ($duplicates->isEmpty()) {
            return null;
        }

        return back()
            ->withInput()
            ->with('duplicates', $this->summarizeDuplicates($duplicates, $data));
    }

    private function summarizeDuplicates(Collection $duplicates, array $data): array
    {
        $newName = mb_strtolower(trim($data['company_name'] ?? "{$data['first_name']} {$data['last_name']}"));
        $newTin = $data['tin'] ?? null;

        return $duplicates->map(fn (Client $client) => [
            'id' => $client->id,
            'code' => $client->client_code,
            'name' => $client->display_name,
            'contact' => $client->contact_number,
            'reasons' => array_values(array_filter([
                mb_strtolower(trim((string) ($client->company_name ?? "{$client->first_name} {$client->last_name}"))) === $newName ? 'same name' : null,
                filled($newTin) && $client->tin === $newTin ? 'same TIN' : null,
                filled($data['contact_number'] ?? null) && $client->contact_number === $data['contact_number'] ? 'same contact number' : null,
                filled($data['email'] ?? null) && strcasecmp((string) $client->email, $data['email']) === 0 ? 'same email' : null,
            ])),
        ])->all();
    }
}
