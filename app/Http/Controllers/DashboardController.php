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

            'stats' => [
                'active_cases' => LegalCase::ongoing()->count(),
                'open_documents' => Document::open()->count(),
                'for_review' => Document::where('status', DocumentStatus::ForReview->value)->count(),
                'with_me' => Document::open()->heldBy($user)->count(),
            ],

            // Workflow v2: documents waiting for ME, longest-waiting first
            'waitingForMe' => Document::open()
                ->heldBy($user)
                ->with(['client' => fn ($q) => $q->withTrashed()])
                ->orderBy('updated_at')
                ->take(8)
                ->get(),

            // Workflow v2 (replaces "overdue"): in-process documents that haven't moved the longest
            'longestInProcess' => Document::open()
                ->with(['client' => fn ($q) => $q->withTrashed(), 'currentHolder:id,name'])
                ->orderBy('updated_at')
                ->take(6)
                ->get(),

            'statusChart' => $this->statusChart(),
            'monthlyChart' => $this->monthlyChart(),

            'recentMovements' => DocumentMovement::query()
                // deleted_at is selected so the view can tell if a document is in trash
                ->with(['document:id,tracking_code,title,deleted_at', 'actor:id,name', 'toUser:id,name'])
                ->latest('acted_at')
                ->latest('id')
                ->take(8)
                ->get(),
        ]);
    }

    /** [ ['status' => DocumentStatus, 'count' => int, 'percent' => float], ... ] */
    private function statusChart(): array
    {
        $counts = Document::query()
            ->toBase()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $max = max(1, (int) $counts->max());

        return collect(DocumentStatus::cases())
            ->map(fn (DocumentStatus $status) => [
                'status' => $status,
                'count' => (int) ($counts[$status->value] ?? 0),
                'percent' => round(((int) ($counts[$status->value] ?? 0)) / $max * 100, 1),
            ])
            ->all();
    }

    /** Documents received per month for the last 6 months (portable — grouped in PHP). */
    private function monthlyChart(): array
    {
        $start = today()->startOfMonth()->subMonths(5);

        $perMonth = Document::query()
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
