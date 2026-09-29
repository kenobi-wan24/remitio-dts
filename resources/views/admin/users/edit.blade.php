<x-app-layout :title="'Manage '.$user->name">
    <x-page-header :title="$user->name" subtitle="{{ $user->email }}" :back="route('admin.users.index')">
        <x-slot name="actions">
            <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                @csrf
                @method('PATCH')
                @if ($user->is_active)
                    <x-button variant="secondary" icon="lock-closed" :disabled="$user->is(auth()->user())">Deactivate</x-button>
                @else
                    <x-button variant="accent" icon="check-circle">Reactivate</x-button>
                @endif
            </form>
        </x-slot>
    </x-page-header>

    <div class="-mt-3 mb-6 flex flex-wrap items-center gap-2">
        <x-status-badge :status="$user->role" />
        <x-status-badge :color="$user->is_active ? 'green' : 'gray'" :label="$user->is_active ? 'Active' : 'Deactivated'" />
        @if ($user->documents_held_count)
            <a href="{{ route('documents.index', ['holder' => $user->id]) }}" class="text-sm text-amber-700 hover:underline">
                Holding {{ $user->documents_held_count }} {{ str('document')->plural($user->documents_held_count) }} →
            </a>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                <x-card title="Account Details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="name" label="Full Name" :value="$user->name" required maxlength="255" />
                        <x-form.input name="email" type="email" label="Email (used to sign in)" :value="$user->email" required />
                        <div class="sm:col-span-2">
                            <x-form.select name="role" label="Role" :options="\App\Enums\UserRole::options()" :selected="$user->role" required />
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end">
                        <x-button icon="check-circle">Save Details</x-button>
                    </div>
                </x-card>
            </form>

            <form method="POST" action="{{ route('admin.users.password', $user) }}">
                @csrf
                @method('PUT')
                <x-card title="Reset Password">
                    <p class="-mt-1 mb-4 text-sm text-slate-500">Use this when {{ $user->name }} forgets their password.</p>
                    @include('admin.users._password-fields', ['label' => 'New Password'])
                    <div class="mt-5 flex justify-end">
                        <x-button variant="secondary" icon="lock-closed">Reset Password</x-button>
                    </div>
                </x-card>
            </form>
        </div>

        <x-card title="Recent Activity" :padding="false">
            <x-slot name="actions">
                <a href="{{ route('admin.activity-logs.index', ['user' => $user->id]) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">View all →</a>
            </x-slot>
            <ul class="divide-y divide-slate-100">
                @forelse ($recentActivity as $log)
                    <li class="px-5 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <x-status-badge :color="$log->actionColor()" :label="$log->actionLabel()" />
                            <time class="text-xs text-slate-400" title="{{ $log->created_at->format('M d, Y h:i A') }}">{{ $log->created_at->diffForHumans() }}</time>
                        </div>
                        <p class="mt-1 text-sm text-slate-700">{{ $log->description }}</p>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-400">No activity yet.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</x-app-layout>
