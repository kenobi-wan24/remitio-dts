<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Admin-only (route group uses role:admin).
 * Users are never deleted — they are deactivated, so their history stays intact.
 * Safety rules: you can't deactivate or demote yourself, and the last active
 * administrator can't be deactivated or demoted (no lock-out).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->input('status'), ['active', 'inactive'], true) ? $request->input('status') : null;

        $users = User::query()
            ->when($status, fn ($q) => $q->where('is_active', $status === 'active'))
            ->withMax(['activityLogs as last_login_at' => fn ($q) => $q->where('action', 'login')], 'created_at')
            ->withCount(['heldDocuments as documents_held_count' => fn ($q) => $q->open()])
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return view('admin.users.index', compact('users', 'status'));
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(['role' => UserRole::Staff, 'is_active' => true]),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'role']),
            'password' => $request->validated('password'), // hashed by the model cast
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Account created for {$user->name}. Give them their password privately; they can change it under My Profile.");
    }

    public function edit(User $user): View
    {
        $user->loadCount(['heldDocuments as documents_held_count' => fn ($q) => $q->open()]);

        $recentActivity = $user->activityLogs()->latest()->take(10)->get();

        return view('admin.users.edit', compact('user', 'recentActivity'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $newRole = UserRole::from($request->validated('role'));

        if ($user->isAdmin() && $newRole !== UserRole::Admin) {
            if ($user->is($request->user())) {
                return back()->with('error', 'You cannot remove your own administrator role.');
            }
            if ($this->isLastActiveAdmin($user)) {
                return back()->with('error', 'This is the last active administrator. Make someone else an administrator first.');
            }
        }

        $user->update($request->validated());

        return redirect()->route('admin.users.edit', $user)->with('success', 'Account details updated.');
    }

    /** Activate / deactivate. */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is_active) {
            if ($user->is($request->user())) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }
            if ($this->isLastActiveAdmin($user)) {
                return back()->with('error', 'This is the last active administrator and cannot be deactivated.');
            }
        }

        $user->update(['is_active' => ! $user->is_active]);

        $action = $user->is_active ? 'activated' : 'deactivated';
        ActivityLogger::log($action, $user, ucfirst($action)." {$user->activityLabel()}");

        $message = $user->is_active
            ? "{$user->name} can sign in again."
            : "{$user->name} has been deactivated and can no longer sign in."
                .($user->heldDocuments()->open()->exists() ? ' They still hold documents; forward those to someone else.' : '');

        return back()->with($user->is_active ? 'success' : 'warning', $message);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update(['password' => $request->input('password')]);

        ActivityLogger::log('password_reset', $user, "Reset the password of {$user->activityLabel()}");

        return back()->with('success', "Password reset for {$user->name}. Give them the new password privately.");
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->isAdmin()
            && User::attorneys()->active()->whereKeyNot($user->id)->doesntExist();
    }
}
