<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only audit trail. There is no way to edit or delete entries from the app.
 */
class ActivityLogController extends Controller
{
    public const SUBJECTS = [
        'document' => ['Documents', Document::class],
        'case' => ['Cases', LegalCase::class],
        'client' => ['Clients', Client::class],
        'user' => ['User accounts', User::class],
        'document_type' => ['Document types', DocumentType::class],
    ];

    public function __invoke(Request $request): View
    {
        $filters = [
            'user' => is_numeric($request->input('user')) ? (int) $request->input('user') : null,
            'action' => array_key_exists((string) $request->input('action'), ActivityLog::ACTIONS) ? $request->input('action') : null,
            'subject' => array_key_exists((string) $request->input('subject'), self::SUBJECTS) ? $request->input('subject') : null,
            'from' => $request->date('from'),
            'to' => $request->date('to'),
            'q' => trim((string) $request->input('q')),
        ];

        $logs = ActivityLog::query()
            ->with(['user:id,name', 'subject'])
            ->when($filters['user'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action))
            ->when($filters['subject'], fn ($q, $s) => $q->where('subject_type', (new (self::SUBJECTS[$s][1]))->getMorphClass()))
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['q'] !== '', fn ($q) => $q->where('description', 'like', '%'.$filters['q'].'%'))
            ->latest()
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
