<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\DocumentType;
use App\Models\NotarialEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Printable reports (open in a new tab → Print / Save as PDF) + CSV export for Excel.
 * Add ?format=csv to any list report to download it instead.
 */
class ReportController extends Controller
{
    private const MAX_ROWS = 2000;

    public function index(): View
    {
        // Explicit select + groupBy (distinct() + pluck() failed on some setups)
        $series = NotarialEntry::query()
            ->select('series')
            ->groupBy('series')
            ->orderByDesc('series')
            ->pluck('series');

        return view('reports.index', [
            'documentTypes' => DocumentType::orderBy('name')->pluck('name', 'id'),
            'users' => User::orderBy('name')->pluck('name', 'id'),
            'clients' => Client::query()
                ->orderByRaw('COALESCE(company_name, last_name)')
                ->orderBy('first_name')
                ->get(['id', 'client_code', 'client_type', 'first_name', 'last_name', 'company_name'])
                ->mapWithKeys(fn (Client $c) => [$c->id => "{$c->display_name} · {$c->client_code}"]),
            'seriesOptions' => $series->isEmpty() ? collect([now()->year]) : $series,
        ]);
    }

    // ── 1. Documents report ─────────────────────────────────

    public function documents(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $status = in_array($request->input('status'), DocumentStatus::values(), true) ? $request->input('status') : null;
        $type = is_numeric($request->input('type')) ? (int) $request->input('type') : null;
        $holder = is_numeric($request->input('holder')) ? (int) $request->input('holder') : null;
        $due = $request->input('due') === 'open' ? 'open' : null; // "In process only"

        $query = Document::query()
            ->with([
                'documentType:id,name',
                'client' => fn ($q) => $q->withTrashed(),
                'legalCase' => fn ($q) => $q->withTrashed()->select('id', 'case_code'),
                'currentHolder:id,name',
                'notarialEntry',
            ])
            ->when($from, fn ($q) => $q->whereDate('date_received', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date_received', '<=', $to))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($type, fn ($q) => $q->where('document_type_id', $type))
            ->when($holder, fn ($q) => $q->heldBy($holder))
            ->when($due === 'open', fn ($q) => $q->open())
            ->orderBy('date_received')
            ->orderBy('id');

        $total = (clone $query)->count();
        $documents = $query->limit(self::MAX_ROWS)->get();

        if ($request->input('format') === 'csv') {
            return $this->csv('documents-report', [
                'Tracking Code', 'Date Received', 'Title', 'Type', 'Client', 'Case', 'Status', 'With', 'Location',
                'Notarial Reference',
            ], $documents->map(fn (Document $d) => [
                $d->tracking_code, $d->date_received->toDateString(), $d->title, $d->documentType?->name,
                $d->client?->display_name, $d->legalCase?->case_code, $d->status->label(), $d->currentHolder?->name,
                $d->physical_location, $d->notarial_reference,
            ]));
        }

        return view('reports.documents', [
            'documents' => $documents,
            'total' => $total,
            'truncated' => $total > self::MAX_ROWS,
            'summary' => collect(DocumentStatus::cases())->mapWithKeys(fn ($s) => [$s->label() => $documents->where('status', $s)->count()]),
            'filters' => array_filter([
                'Received' => $this->rangeLabel($from, $to),
                'Status' => $status ? DocumentStatus::from($status)->label() : null,
                'Type' => $type ? DocumentType::find($type)?->name : null,
                'With' => $holder ? User::find($holder)?->name : null,
                'Showing' => $due ? 'In-process documents only' : null,
            ]),
        ]);
    }

    // ── 2. Document movements report ────────────────────────

    public function movements(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $action = in_array($request->input('action'), MovementAction::values(), true) ? $request->input('action') : null;
        $actor = is_numeric($request->input('actor')) ? (int) $request->input('actor') : null;

        $query = DocumentMovement::query()
            ->with([
                'document' => fn ($q) => $q->withTrashed()->select('id', 'tracking_code', 'title', 'deleted_at'),
                'actor:id,name', 'fromUser:id,name', 'toUser:id,name',
            ])
            ->when($from, fn ($q) => $q->whereDate('acted_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('acted_at', '<=', $to))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($actor, fn ($q) => $q->where('acted_by', $actor))
            ->orderBy('acted_at')
            ->orderBy('id');

        $total = (clone $query)->count();
        $movements = $query->limit(self::MAX_ROWS)->get();

        if ($request->input('format') === 'csv') {
            return $this->csv('document-movements', [
                'Date & Time', 'Tracking Code', 'Document', 'Action', 'From Status', 'To Status', 'From', 'To', 'Location', 'Remarks', 'Logged By',
            ], $movements->map(fn (DocumentMovement $m) => [
                $m->acted_at->format('Y-m-d H:i'), $m->document?->tracking_code, $m->document?->title, $m->action->label(),
                $m->from_status?->label(), $m->to_status?->label(), $m->fromUser?->name, $m->toUser?->name,
                $m->location, $m->remarks, $m->actor?->name,
            ]));
        }

        return view('reports.movements', [
            'movements' => $movements,
            'total' => $total,
            'truncated' => $total > self::MAX_ROWS,
            'summary' => collect(MovementAction::cases())
                ->mapWithKeys(fn ($a) => [$a->label() => $movements->where('action', $a)->count()])
                ->filter(),
            'filters' => array_filter([
                'Period' => $this->rangeLabel($from, $to),
                'Action' => $action ? MovementAction::from($action)->label() : null,
                'Logged by' => $actor ? User::find($actor)?->name : null,
            ]),
        ]);
    }

    // ── 3. Notarial register listing ────────────────────────

    public function notarial(Request $request): View|StreamedResponse
    {
        $series = (int) ($request->input('series') ?: now()->year);
        $month = in_array((int) $request->input('month'), range(1, 12), true) ? (int) $request->input('month') : null;
        $book = trim((string) $request->input('book')) ?: null;

        // Workflow v2: read from the Notarial Register; month = month the entry was recorded
        $entries = NotarialEntry::query()
            ->with(['document' => fn ($q) => $q->with(['documentType:id,name', 'client' => fn ($c) => $c->withTrashed()])])
            ->where('series', $series)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->when($book, fn ($q) => $q->where('book_no', strtoupper($book)))
            ->orderBy('book_no')
            ->orderBy('page_no')
            ->orderBy('doc_no')
            ->limit(self::MAX_ROWS)
            ->get();

        if ($request->input('format') === 'csv') {
            return $this->csv("notarial-register-{$series}", [
                'Doc. No.', 'Page No.', 'Book No.', 'Series', 'Date Recorded', 'Instrument', 'Type', 'Principal / Client', 'Tracking Code',
            ], $entries->map(fn (NotarialEntry $e) => [
                $e->doc_no, $e->page_no, $e->book_no, $e->series, $e->created_at->toDateString(),
                $e->document?->title, $e->document?->documentType?->name, $e->document?->client?->display_name, $e->document?->tracking_code,
            ]));
        }

        return view('reports.notarial', [
            'entries' => $entries,
            'series' => $series,
            'filters' => array_filter([
                'Series' => (string) $series,
                'Month' => $month ? Carbon::create($series, $month, 1)->format('F') : 'All months',
                'Book No.' => $book ? strtoupper($book) : 'All books',
            ]),
        ]);
    }

    // ── 4. Client case summary ──────────────────────────────

    public function clientSummary(Request $request): View
    {
        $request->validate(['client_id' => ['required', 'exists:clients,id']], ['client_id.required' => 'Choose a client.']);

        $client = Client::withTrashed()->findOrFail($request->input('client_id'));

        $cases = $client->cases()
            ->with(['attorney:id,name', 'documents' => fn ($q) => $q
                ->with(['documentType:id,name', 'currentHolder:id,name'])
                ->orderBy('date_received')])
            ->orderBy('date_opened')
            ->get();

        $standalone = $client->documents()
            ->whereNull('legal_case_id')
            ->with(['documentType:id,name', 'currentHolder:id,name'])
            ->orderBy('date_received')
            ->get();

        $allDocuments = $cases->flatMap->documents->merge($standalone);

        return view('reports.client-summary', [
            'client' => $client,
            'cases' => $cases,
            'standalone' => $standalone,
            'stats' => [
                'cases' => $cases->count(),
                'active_cases' => $cases->reject(fn ($c) => $c->status === \App\Enums\CaseStatus::Closed)->count(),
                'documents' => $allDocuments->count(),
                'in_process' => $allDocuments->reject(fn ($d) => $d->status->isFinal())->count(),
            ],
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────

    /** Defaults to the current month when no dates are given. */
    private function dateRange(Request $request): array
    {
        if (! $request->hasAny(['from', 'to'])) {
            return [today()->startOfMonth(), today()];
        }

        return [$request->date('from'), $request->date('to')];
    }

    private function rangeLabel(?Carbon $from, ?Carbon $to): string
    {
        return match (true) {
            $from && $to => $from->format('M d, Y').' – '.$to->format('M d, Y'),
            (bool) $from => 'From '.$from->format('M d, Y'),
            (bool) $to => 'Up to '.$to->format('M d, Y'),
            default => 'All dates',
        };
    }

    /**
     * CSV download that opens correctly in Excel (UTF-8 BOM).
     * Cells starting with = + - @ are prefixed with ' to block CSV/formula injection.
     */
    private function csv(string $name, array $headings, iterable $rows): StreamedResponse
    {
        $safe = fn ($value) => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($headings, $rows, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, array_map($safe, $row));
            }
            fclose($out);
        }, $name.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
