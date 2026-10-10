<x-app-layout title="Roles & Permissions">
    <x-page-header title="Roles & Permissions"
        subtitle="Who can see and do what. These rules are enforced by the system itself — hiding a menu is never the only protection." />

    {{-- Roles --}}
    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($roles as $role)
            <x-card>
                <div class="flex items-center justify-between">
                    <x-status-badge :status="$role" />
                    <span class="text-xs text-slate-500">{{ $roleCounts[$role->value] ?? 0 }} active</span>
                </div>
                <p class="mt-3 text-sm text-slate-600">{{ $role->description() }}</p>
            </x-card>
        @endforeach
    </div>

    {{-- Access mode --}}
    <x-card class="mt-6" title="Access mode">
        <div class="flex flex-wrap items-start gap-4 text-sm">
            <x-status-badge :color="$accessMode === 'open' ? 'green' : 'amber'" :label="$accessMode === 'open' ? 'Open office' : 'Assigned only'" class="text-sm" />
            <p class="min-w-0 flex-1 text-slate-600">
                @if ($accessMode === 'open')
                    Everyone with an account sees records that are <strong>not</strong> confidential. Records marked
                    <strong>Confidential</strong> are visible only to the Administrator and the people assigned to them.
                @else
                    Lawyers and staff see <strong>only</strong> the cases and documents assigned to them. The Administrator sees everything.
                @endif
                <span class="mt-1 block text-xs text-slate-500">
                    "Assigned" = handling lawyer of the case, current holder, whoever recorded it, or anyone who handled it in the tracking history.
                    Set with <code class="rounded bg-slate-100 px-1">DTS_ACCESS_MODE=open</code> or <code class="rounded bg-slate-100 px-1">assigned</code> in the .env file.
                </span>
            </p>
        </div>
    </x-card>

    {{-- Matrix --}}
    <x-card class="mt-6" title="Permission matrix" :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Action</th>
                        @foreach ($roles as $role)
                            <th class="px-5 py-3">{{ $role->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                @foreach ($matrix as $area => $rows)
                    <tbody class="divide-y divide-slate-100 border-t border-slate-200">
                        <tr><th colspan="4" class="bg-slate-50/60 px-5 py-2 text-left text-xs font-semibold text-slate-700">{{ $area }}</th></tr>
                        @foreach ($rows as [$action, $admin, $lawyer, $staff])
                            <tr>
                                <td class="px-5 py-2.5 text-slate-700">{{ $action }}</td>
                                @foreach ([$admin, $lawyer, $staff] as $cell)
                                    <td class="px-5 py-2.5">
                                        @if ($cell === true)
                                            <x-icon name="check-circle" class="h-5 w-5 text-green-600" />
                                        @elseif ($cell === false)
                                            <x-icon name="x-circle" class="h-5 w-5 text-slate-300" />
                                        @else
                                            <span class="text-xs text-slate-600">{{ $cell }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
    </x-card>

    {{-- Audit --}}
    <x-card class="mt-6" title="Recent confidential access & refused attempts" :padding="false">
        <x-slot name="actions">
            <a href="{{ route('admin.activity-logs.index', ['action' => 'access_denied']) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">Activity Log →</a>
        </x-slot>
        @forelse ($recentDenied as $log)
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                <x-status-badge :color="$log->actionColor()" :label="$log->actionLabel()" />
                <span class="min-w-0 flex-1 truncate text-slate-700">{{ $log->user?->name }} — {{ $log->description }}</span>
                <time class="shrink-0 text-xs text-slate-400">{{ $log->created_at->format('M d, Y h:i A') }}</time>
            </div>
        @empty
            <p class="px-5 py-6 text-center text-sm text-slate-400">Nothing recorded yet.</p>
        @endforelse
    </x-card>
</x-app-layout>
