<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global search (top bar). Typing an exact code jumps straight to the record:
 *   DOC-2026-00012 → document · RR-2026-0005 → case · CL-2026-0003 → client
 */
class SearchController extends Controller
{
    private const PER_SECTION = 8;

    public function __invoke(Request $request): View|RedirectResponse
    {
        $q = trim((string) $request->input('q'));

        if ($q === '') {
            return view('search', ['q' => '']);
        }

        if ($redirect = $this->exactCodeMatch($q)) {
            return $redirect;
        }

        $documentQuery = Document::query()->search($q);
        $caseQuery = LegalCase::query()->search($q);
        $clientQuery = Client::query()->search($q);

        return view('search', [
            'q' => $q,

            'documentsTotal' => (clone $documentQuery)->count(),
            'documents' => $documentQuery
                ->with(['documentType:id,name', 'client' => fn ($x) => $x->withTrashed(), 'currentHolder:id,name'])
                ->latest('date_received')
                ->take(self::PER_SECTION)
                ->get(),

            'casesTotal' => (clone $caseQuery)->count(),
            'cases' => $caseQuery
                ->with(['client' => fn ($x) => $x->withTrashed(), 'attorney:id,name'])
                ->latest('date_opened')
                ->take(self::PER_SECTION)
                ->get(),

            'clientsTotal' => (clone $clientQuery)->count(),
            'clients' => $clientQuery
                ->withCount(['cases', 'documents'])
                ->orderByRaw('COALESCE(company_name, last_name)')
                ->take(self::PER_SECTION)
                ->get(),
        ]);
    }

    private function exactCodeMatch(string $q): ?RedirectResponse
    {
        $code = strtoupper($q);

        return match (true) {
            str_starts_with($code, 'DOC-') && ($doc = Document::where('tracking_code', $code)->first()) !== null
                => redirect()->route('documents.show', $doc),
            str_starts_with($code, 'RR-') && ($case = LegalCase::where('case_code', $code)->first()) !== null
                => redirect()->route('cases.show', $case),
            str_starts_with($code, 'CL-') && ($client = Client::where('client_code', $code)->first()) !== null
                => redirect()->route('clients.show', $client),
            default => null,
        };
    }
}
