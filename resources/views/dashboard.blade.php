<x-app-layout title="Dashboard">
    <x-page-header :title="$greeting.', '.$firstName"
        subtitle="Here's what's happening in the office today, {{ now()->format('l, F j, Y') }}." />

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active Cases" :value="$stats['active_cases']" icon="briefcase" color="blue" :href="route('cases.index')" />
        <x-stat-card label="Documents In Process" :value="$stats['open_documents']" icon="document-text" color="indigo" :href="route('documents.index')" />
        <x-stat-card label="Overdue Documents" :value="$stats['overdue']" icon="exclamation-triangle" :color="$stats['overdue'] > 0 ? 'red' : 'green'" />
        <x-stat-card label="Documents With Me" :value="$stats['with_me']" icon="inbox" color="amber" />
    </div>

    {{-- Recent movements --}}
    <x-card title="Recent Document Activity" :padding="false" class="mt-6">
        @forelse ($recentMovements as $movement)
            <div class="flex items-start gap-4 border-b border-slate-100 px-5 py-4 last:border-0">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                    <x-icon name="arrows-right-left" class="h-4 w-4" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs font-semibold text-slate-900">{{ $movement->document?->tracking_code }}</span>
                        <x-status-badge :status="$movement->action" />
                        @if ($movement->to_status)
                            <x-icon name="chevron-right" class="h-3 w-3 text-slate-400" />
                            <x-status-badge :status="$movement->to_status" />
                        @endif
                    </div>
                    <p class="mt-1 truncate text-sm text-slate-700">{{ $movement->document?->title }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        by {{ $movement->actor?->name ?? 'Unknown' }}
                        @if ($movement->toUser) · now with <span class="font-medium text-slate-700">{{ $movement->toUser->name }}</span> @endif
                    </p>
                </div>

                <time class="shrink-0 text-xs text-slate-400" datetime="{{ $movement->acted_at->toIso8601String() }}"
                    title="{{ $movement->acted_at->format('M d, Y h:i A') }}">
                    {{ $movement->acted_at->diffForHumans() }}
                </time>
            </div>
        @empty
            <x-empty-state class="m-5" title="No document activity yet" message="Movements will appear here once documents are received and forwarded." />
        @endforelse
    </x-card>
</x-app-layout>
