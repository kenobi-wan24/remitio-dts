<x-app-layout title="User Management">
    <x-page-header title="User Management" subtitle="Office accounts. Users are deactivated, never deleted, so their history stays intact.">
        <x-slot name="actions">
            <x-button :href="route('admin.users.create')" icon="plus">New Account</x-button>
        </x-slot>
    </x-page-header>

    <div class="mb-4 flex gap-1 border-b border-slate-200">
        @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Deactivated'] as $value => $label)
            <a href="{{ route('admin.users.index', array_filter(['status' => $value])) }}"
                @class([
                    '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
                    'border-slate-800 text-slate-900' => ($status ?? '') === $value,
                    'border-transparent text-slate-500 hover:text-slate-700' => ($status ?? '') !== $value,
                ])>{{ $label }}</a>
        @endforeach
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-center">Holding</th>
                        <th class="px-5 py-3">Last Sign-in</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr @class(['hover:bg-slate-50', 'opacity-60' => ! $user->is_active])>
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-900">
                                    {{ $user->name }}
                                    @if ($user->is(auth()->user())) <span class="text-xs font-normal text-slate-500">(you)</span> @endif
                                </p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td class="px-5 py-3"><x-status-badge :status="$user->role" /></td>
                            <td class="px-5 py-3">
                                <x-status-badge :color="$user->is_active ? 'green' : 'gray'" :label="$user->is_active ? 'Active' : 'Deactivated'" />
                            </td>
                            <td class="px-5 py-3 text-center text-slate-700">
                                @if ($user->documents_held_count)
                                    <a href="{{ route('documents.index', ['holder' => $user->id]) }}" class="hover:underline">{{ $user->documents_held_count }}</a>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-500">
                                {{ $user->last_login_at ? \Illuminate\Support\Carbon::parse($user->last_login_at)->diffForHumans() : 'Never' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <x-button variant="ghost" size="sm" :href="route('admin.users.edit', $user)" icon="pencil-square">Manage</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
