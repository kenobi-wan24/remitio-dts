<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $showTrashed = $request->input('view') === 'trash' && $request->user()->isAdmin();
        $type = in_array($request->input('type'), ClientType::values(), true) ? $request->input('type') : null;
        $sort = $request->input('sort', 'name');

        $clients = Client::query()
            ->when($showTrashed, fn ($query) => $query->onlyTrashed())
            ->search($request->input('q'))
            ->when($type, fn ($query) => $query->where('client_type', $type))
            ->withCount(['cases', 'documents'])
            ->when(
                $sort === 'newest',
                fn ($query) => $query->latest()->latest('id'),
                fn ($query) => $query->orderByRaw('COALESCE(company_name, last_name)')->orderBy('first_name'),
            )
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients', 'showTrashed', 'type', 'sort'));
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

        $client = Client::create([...$data, 'created_by' => $request->user()->id]);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', "Client {$client->client_code} ({$client->display_name}) has been added.");
    }

    public function show(Client $client): View
    {
        $client->load('creator:id,name');

        $cases = $client->cases()
            ->with('attorney:id,name')
            ->withCount('documents')
            ->latest('date_opened')
            ->get();

        $documents = $client->documents()
            ->with(['documentType:id,name', 'currentHolder:id,name', 'legalCase:id,case_code'])
            ->latest('date_received')
            ->latest('id')
            ->take(10)
            ->get();

        $documentsCount = $client->documents()->count();

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

        $client->update($data);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client details updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);

        if ($client->cases()->exists() || $client->documents()->exists()) {
            return back()->with('error', 'This client still has cases or documents on record, so it cannot be deleted.');
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->client_code} was moved to trash.");
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

        return $duplicates->map(fn (Client $client) => [
            'id' => $client->id,
            'code' => $client->client_code,
            'name' => $client->display_name,
            'contact' => $client->contact_number,
            'reasons' => array_values(array_filter([
                mb_strtolower((string) $client->display_name) === $newName ? 'same name' : null,
                filled($data['contact_number'] ?? null) && $client->contact_number === $data['contact_number'] ? 'same contact number' : null,
                filled($data['email'] ?? null) && strcasecmp((string) $client->email, $data['email']) === 0 ? 'same email' : null,
            ])),
        ])->all();
    }
}
