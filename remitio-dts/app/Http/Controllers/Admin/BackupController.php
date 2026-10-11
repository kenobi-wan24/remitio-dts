<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin-only. Create / download / delete / restore backups.
 */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups)
    {
    }

    public function index(): View
    {
        $list = BackupService::zipAvailable() ? $this->backups->list() : [];
        $latest = collect($list)->reject(fn ($b) => $b['label'] === 'before-restore')->first();

        return view('admin.backups.index', [
            'backups' => $list,
            'zipAvailable' => BackupService::zipAvailable(),
            'daysSinceLast' => $latest ? (int) floor((time() - $latest['time']) / 86400) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $name = $this->backups->create($request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLogger::log('backup_created', null, "Created backup {$name}");

        return back()->with('success', "Backup created: {$name}. Download it and keep a copy outside this computer (USB drive or cloud).");
    }

    public function download(string $file): BinaryFileResponse
    {
        abort_unless($this->backups->exists($file), 404);

        return response()->download($this->backups->path($file));
    }

    public function destroy(string $file): RedirectResponse
    {
        abort_unless($this->backups->exists($file), 404);

        $this->backups->delete($file);
        ActivityLogger::log('backup_deleted', null, "Deleted backup {$file}");

        return back()->with('success', "Backup {$file} deleted.");
    }

    /**
     * Restore from a backup on the server OR an uploaded .zip.
     * Requires typing RESTORE and the admin's own password. Signs everyone out afterwards.
     */
    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'backup' => ['nullable', 'required_without:upload', 'string'],
            'upload' => ['nullable', 'required_without:backup', 'file', 'extensions:zip'],
            'confirm' => ['required', 'in:RESTORE'],
            'password' => ['required', 'current_password'],
        ], [
            'backup.required_without' => 'Choose a backup from the list or upload a backup file.',
            'upload.required_without' => 'Choose a backup from the list or upload a backup file.',
            'confirm.in' => 'Type RESTORE in capital letters to confirm.',
            'password.current_password' => 'Your password is incorrect.',
        ]);

        if ($request->filled('backup') && ! $this->backups->exists($request->input('backup'))) {
            return back()->with('error', 'That backup no longer exists.');
        }

        $zipPath = $request->hasFile('upload')
            ? $request->file('upload')->getRealPath()
            : $this->backups->path($request->input('backup'));

        $admin = $request->user();

        try {
            $result = $this->backups->restore($zipPath, $admin);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }

        // The users table was replaced — log as the same person if they exist in the restored data.
        if ($restoredAdmin = User::where('email', $admin->email)->first()) {
            ActivityLogger::log('backup_restored', null,
                'Restored backup from '.($result['manifest']['created_at'] ?? 'unknown date')." (safety copy: {$result['safety']})",
                [], $restoredAdmin);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status',
            'The backup was restored. Please sign in again. A safety copy of the previous data was saved as '.$result['safety'].'.');
    }
}
