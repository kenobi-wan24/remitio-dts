@php
    use App\Http\Controllers\Admin\ActivityLogController;
    use App\Models\ActivityLog;

    // Where a log entry's subject can be opened
    $subjectLink = function ($log) {
        $subject = $log->subject;
        if (! $subject) {
            return null;
        }
        return match (true) {
            $subject instanceof \App\Models\Document => route('documents.show', $subject),
            $subject instanceof \App\Models\LegalCase => route('cases.show', $subject),
            $subject instanceof \App\Models\Client => route('clients.show', $subject),
            $subject instanceof \App\Models\User => route('admin.users.edit', $subject),
            $subject instanceof \App\Models\DocumentType => route('admin.document-types.edit', $subject),
            default => null,
        };
    };

    $formatValue = fn ($value) => match (true) {
        $value === null || $value === '' => '—',
        is_bool($value) => $value ? 'Yes' : 'No',
        default => \Illuminate\Support\Str::limit((string) $value, 60),
    };
@endphp

<x-app-layout title="Activity Log">
    <x-page-header title="Activity Log"
        subtitle="Who did what, and when. Entries are recorded automatically and cannot be edited or deleted." />

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
        <div class="sm:col-span-2">
            <label for="q" class="block text-xs font-medium text-slate-500">Search description</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="e.g. DOC-2026-00012, client name..."
                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        @foreach ([
            ['user', 'User', ['' => 'Everyone'] + $users->all(), $filters['user']],
            ['action', 'Action', ['' => 'All actions'] + collect(ActivityLog::ACTIONS)->map(fn ($a) => $a[0])->all(), $filters['action']],
            ['subject', 'Record type', ['' => 'All records'] + collect(ActivityLogController::SUBJECTS)->map(fn ($s) => $s[0])->all(), $filters['subject']],
        ] as [$field, $label, $options, $current])
            <div>
                <label for="{{ $field }}" class="block text-xs font-medium text-slate-500">{{ $label }}</label>
                <select id="{{ $field }}" name="{{ $field }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($options as $value => $text)
                        <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:col-span-1 lg:grid-cols-1">
            <div>
                <label for="from" class="block text-xs font-medium text-slate-500">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from']?->toDateString() }}"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-slate-500">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to']?->toDateString() }}"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
        </div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6 lg:justify-end">
            @if (request()->hasAny(['q', 'user', 'action', 'subject', 'from', 'to']))
                <x-button variant="ghost" :href="route('admin.activity-logs.index')">Reset</x-button>
            @endif
            <x-button>Apply Filters</x-button>
        </div>
    </form>

    @if ($logs->isEmpty())
        <x-empty-state icon="clock" title="No activity found"
            message="Actions such as creating, editing, moving, and signing in are recorded here automatically. Demo data created by the seeder is not logged." />
    @else
        <x-card :padding="false">
            <ul class="divide-y divide-slate-100">
                @foreach ($logs as $log)
                    @php
                        $link = $subjectLink($log);
                        $props = $log->properties ?? [];
                        $changes = $log->action === 'updated' ? ($props['new'] ?? []) : [];
                        $old = $props['old'] ?? [];
                    @endphp
                    <li class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-start sm:gap-4" x-data="{ open: false }">
                        <div class="w-40 shrink-0 text-xs text-slate-500">
                            <p class="font-medium text-slate-700">{{ $log->created_at->format('M d, Y') }}</p>
                            <p>{{ $log->created_at->format('h:i:s A') }}</p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :color="$log->actionColor()" :label="$log->actionLabel()" />
                                <span class="text-sm font-medium text-slate-900">{{ $log->user?->name ?? 'System' }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-700">
                                @if ($link)
                                    <a href="{{ $link }}" class="hover:underline">{{ $log->description }}</a>
                                @else
                                    {{ $log->description }}
                                @endif
                            </p>

                            @if ($changes)
                                <button type="button" x-on:click="open = ! open" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800">
                                    <x-icon name="chevron-right" class="h-3 w-3 transition" x-bind:class="open && 'rotate-90'" />
                                    <span x-text="open ? 'Hide changes' : 'Show changes'"></span>
                                </button>
                                <div x-show="open" x-cloak class="mt-2 overflow-x-auto rounded-lg border border-slate-200">
                                    <table class="min-w-full text-xs">
                                        <thead class="bg-slate-50 text-left text-slate-500">
                                            <tr><th class="px-3 py-1.5">Field</th><th class="px-3 py-1.5">Before</th><th class="px-3 py-1.5">After</th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($changes as $field => $new)
                                                <tr>
                                                    <td class="px-3 py-1.5 font-mono text-slate-600">{{ $field }}</td>
                                                    <td class="px-3 py-1.5 text-red-700 line-through decoration-red-300">{{ $formatValue($old[$field] ?? null) }}</td>
                                                    <td class="px-3 py-1.5 text-green-700">{{ $formatValue($new) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                        <span class="shrink-0 font-mono text-xs text-slate-400">{{ $log->ip_address }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-app-layout>
