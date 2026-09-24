<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\LegalCase;
use Illuminate\Http\Request;
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
                'overdue' => Document::overdue()->count(),
                'with_me' => Document::open()->heldBy($user)->count(),
            ],
            'recentMovements' => DocumentMovement::query()
                ->with(['document:id,tracking_code,title', 'actor:id,name', 'toUser:id,name'])
                ->latest('acted_at')
                ->latest('id')
                ->take(8)
                ->get(),
        ]);
    }
}
