<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return view('dashboard', [
            'greeting' => $greeting,
            'firstName' => Str::of($user->name)->after('Atty. ')->explode(' ')->first(),

            /*
             * R3 — every number and list below only covers records this user may see.
             * R1 — counting rules (each KPI counts one clearly defined set):
             *   In process      = every document NOT Released/Archived
             *   Completed       = Released + Archived      → In process + Completed = all documents
             *   Awaiting review = status For Review (office-wide)
             *   Pending my action = in process AND currently with the logged-in user
             */
            'stats' => [
                'active_cases' => LegalCase::visibleTo($user)->ongoing()->count(),
                'in_process' => Document::visibleTo($user)->open()->count(),
                'awaiting_review' => Document::visibleTo($user)->where('status', DocumentStatus::ForReview->value)->count(),
                'pending_mine' => Document::visibleTo($user)->open()->heldBy($user)->count(),
            ],

            // R1: "My Action Queue" — grouped by what I have to do
            // R4: …or (default) by priority: Immediate → Due Soon → High → Routine → On Hold
            'queueView' => $queueView = $request->input('queue') === 'action' ? 'action' : 'priority',
            'actionQueue' => $queueView === 'action' ? $this->actionQueue($user) : $this->priorityQueue($user),

            // R2: physical copies outside the office right now (longest out first)
            'outOfOffice' => Document::visibleTo($user)->outOfOffice()
                ->orderBy('out_since')
                ->take(6)
                ->get(['id', 'tracking_code', 'title', 'out_since', 'out_destination', 'out_purpose', 'out_carried_by', 'out_expected_back']),
            'outOfOfficeCount' => Document::visibleTo($user)->outOfOffice()->count(),

            // Workflow v2 (replaces "overdue"): in-process documents that haven't moved the longest
            'longestInProcess' => Document::visibleTo($user)->open()
                ->withLastMovedAt()
                ->with(['client' => fn ($q) => $q->withTrashed(), 'currentHolder:id,name'])
                ->orderBy('last_moved_at')
                ->take(6)
                ->get(),

            'statusChart' => $this->statusChart($user),
            'monthlyChart' => $this->monthlyChart($user),

            'recentMovements' => DocumentMovement::query()
                ->whereIn('document_id', Document::withTrashed()->visibleTo($user)->select('id'))   // R3
                // deleted_at is selected so the view can tell if a document was deleted
                ->with(['document:id,tracking_code,title,deleted_at', 'actor:id,name', 'toUser:id,name'])
                ->latest('acted_at')
                ->latest('id')
                ->take(8)
                ->get(),
        ]);
    }

    /**
     * R1: my in-process documents grouped by the action they need, in workflow order.
     * [ ['status' => DocumentStatus, 'label' => 'Awaiting Review / Approval', 'count' => int, 'documents' => Collection], ... ]
     */
    private function actionQueue($user): array
    {
        $mine = Document::visibleTo($user)->open()
            ->heldBy($user)
            ->withLastMovedAt()
            ->with(['client' => fn ($q) => $q->withTrashed()])
            ->orderBy('last_moved_at')
            ->get()
            ->groupBy(fn (Document $d) => $d->status->value);

        return collect(DocumentStatus::inProcessCases())
            ->filter(fn (DocumentStatus $s) => $mine->has($s->value))
            ->map(fn (DocumentStatus $s) => [
                'status' => $s,
                'label' => $s->pendingAction(),
                'count' => $mine[$s->value]->count(),
                'documents' => $mine[$s->value]->take(4),
            ])
            ->values()
            ->all();
    }

    /**
     * R4: my in-process documents grouped by priority (rules on App\Enums\PriorityGroup).
     * Inside a group: higher urgency → earlier deadline (if on) → waiting longest.
     * Same shape as actionQueue(); each group's 'status' is null and 'group' is the PriorityGroup.
     */
    private function priorityQueue($user): array
    {
        $mine = Document::visibleTo($user)->open()
            ->heldBy($user)
            ->withLastMovedAt()
            ->with(['client' => fn ($q) => $q->withTrashed()])
            ->get()
            ->sortBy(fn (Document $d) => [
                $d->urgency->rank(),
                $d->activeDeadline()?->timestamp ?? PHP_INT_MAX,
                $d->waitingSince()->timestamp,
            ])
            ->groupBy(fn (Document $d) => $d->priorityGroup()->value);

        return collect(\App\Enums\PriorityGroup::cases())
            ->filter(fn ($g) => $mine->has($g->value))
            ->map(fn ($g) => [
                'status' => null,
                'group' => $g,
                'label' => $g->label(),
                'count' => $mine[$g->value]->count(),
                'documents' => $mine[$g->value]->take(4),
            ])
            ->values()
            ->all();
    }

    /**
     * R1: in-process statuses as bars; Released/Archived only as one "Completed" line,
     * so the bars add up to the "Documents In Process" KPI.
     */
    private function statusChart($user): array
    {
        $counts = Document::query()
            ->visibleTo($user)   // R3
            ->toBase()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (DocumentStatus $s) => (int) ($counts[$s->value] ?? 0);
        $inProcess = collect(DocumentStatus::inProcessCases());
        $max = max(1, $inProcess->map($count)->max());

        $completed = [
            'released' => $count(DocumentStatus::Released),
            'archived' => $count(DocumentStatus::Archived),
        ];
        $inProcessTotal = $inProcess->sum($count);

        return [
            'rows' => $inProcess->map(fn (DocumentStatus $s) => [
                'status' => $s,
                'count' => $count($s),
                'percent' => round($count($s) / $max * 100, 1),
            ])->all(),
            'in_process' => $inProcessTotal,
            'released' => $completed['released'],
            'archived' => $completed['archived'],
            'completed' => array_sum($completed),
            'total' => $inProcessTotal + array_sum($completed),
        ];
    }

    /** Documents received per month for the last 6 months (portable — grouped in PHP). */
    private function monthlyChart($user): array
    {
        $start = today()->startOfMonth()->subMonths(5);

        $perMonth = Document::query()
            ->visibleTo($user)
            ->whereDate('date_received', '>=', $start)
            ->pluck('date_received')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));

        $months = collect(range(0, 5))->map(function (int $i) use ($start, $perMonth) {
            $month = $start->copy()->addMonths($i);

            return [
                'label' => $month->format('M'),
                'full' => $month->format('F Y'),
                'count' => (int) ($perMonth[$month->format('Y-m')] ?? 0),
            ];
        });

        $max = max(1, $months->max('count'));

        return $months->map(fn ($m) => [...$m, 'percent' => round($m['count'] / $max * 100, 1)])->all();
    }
}
