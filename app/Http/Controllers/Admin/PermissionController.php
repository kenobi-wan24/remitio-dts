<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\View\View;

/**
 * R3 — read-only "who can do what" page. The rules themselves live in the policies,
 * the EnsureRecordAccess middleware and Document/LegalCase::visibleTo(); this page documents them.
 */
class PermissionController extends Controller
{
    /** [area => [[action, admin, lawyer, staff], ...]]  (true = yes, false = no, string = condition) */
    public const MATRIX = [
        'Records' => [
            ['View and search clients', true, true, true],
            ['View cases & documents that are not confidential', true, 'open mode: yes · assigned mode: only assigned', 'open mode: yes · assigned mode: only assigned'],
            ['View confidential cases & documents', true, 'Only if assigned', 'Only if assigned'],
            ['Record clients, cases and documents', true, true, true],
            ['Edit case / document details', true, 'Records they may see', 'Records they may see'],
            ['Mark a case or document confidential', true, true, false],
            ['Delete and restore records (Deleted records)', true, false, false],
        ],
        'Document workflow' => [
            ['Start drafting, submit, resubmit, finalize, mark signed & sealed, release, archive', true, true, true],
            ['Hand over, send out of office, log return', true, true, true],
            ['Approve / ask for revision (marks the FINAL version)', true, true, false],
            ['Correct a wrong status (reason required)', true, true, false],
        ],
        'Priority' => [
            ['Change a document\'s urgency (reason required, kept in its history)', true, true, false],
            ['Set a case\'s urgency (new documents in it start with that urgency)', true, true, false],
            ['Put on hold / resume (waiting on the client or another office)', true, true, true],
            ['Enter a deadline (only when deadlines are turned on)', true, true, true],
        ],
        'Files' => [
            ['Upload and download files', 'All documents', 'Documents they may see', 'Documents they may see'],
            ['Delete a file', 'Any file', 'Own uploads, never a FINAL version', 'Own uploads, never a FINAL version'],
        ],
        'Notarial Register' => [
            ['View the register book (all entries, like the physical book)', true, true, true],
            ['Open the linked document from an entry', true, 'Documents they may see', 'Documents they may see'],
            ['Add an entry', true, true, true],
            ['Correct an entry', true, true, false],
            ['Delete an entry', true, false, false],
        ],
        'Reports & search' => [
            ['Search, print and export (CSV) reports', 'All records', 'Only records they may see', 'Only records they may see'],
        ],
        'Administration' => [
            ['Users, document types, backup & restore, activity log', true, false, false],
        ],
    ];

    public function __invoke(): View
    {
        return view('admin.permissions', [
            'matrix' => self::MATRIX,
            'roles' => UserRole::cases(),
            'roleCounts' => User::active()->get()->countBy(fn (User $u) => $u->role->value),
            'accessMode' => config('dts.access_mode') === 'assigned' ? 'assigned' : 'open',
            'recentDenied' => ActivityLog::query()
                ->whereIn('action', ['access_denied', 'confidential_viewed'])
                ->with('user:id,name')
                ->latest()
                ->take(10)
                ->get(),
        ]);
    }
}
