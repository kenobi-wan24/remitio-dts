<x-app-layout title="Dashboard">
    <x-page-header :title="$greeting.', '.$firstName"
        subtitle="Here's what's happening in the office today, {{ now()->format('l, F j, Y') }}.">
        <x-slot name="actions">
            <x-button :href="route('documents.create')" icon="plus">Record Document</x-button>
        </x-slot>
    </x-page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active Cases" :value="$stats['active_cases']" icon="briefcase" color="blue" :href="route('cases.index')" />
        <x-stat-card label="Documents In Process" :value="$stats['open_documents']" icon="document-text" color="indigo" :href="route('documents.index')" />
        <x-stat-card label="For Review" :value="$stats['for_review']" icon="eye" color="amber"
            :href="route('documents.index', ['status' => 'for_review'])" />
        <x-stat-card label="Waiting for Me" :value="$stats['with_me']" icon="inbox" color="green"
            :href="route('documents.index', ['holder' => 'me'])" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Workflow v2: what's waiting on me, longest-waiting first --}}
        <x-card title="Waiting for Me" :padding="false" class="lg:col-span-2">
            <x-slot name="actions">
                <a href="{{ route('documents.index', ['holder' => 'me']) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">View all →</a>
            </x-slot>

            @if ($waitingForMe->isEmpty())
                <x-empty-state class="m-5" icon="check-circle" title="All caught up" message="No documents are waiting for you." />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($waitingForMe as $document)
                        <li>
                            <a href="{{ route('documents.show', $document) }}" class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50">
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-semibold text-slate-900">{{ $document->tracking_code }}</span>
                                        <x-status-badge :status="$document->status" />
                                    </p>
                                    <p class="truncate text-sm text-slate-700">{{ $document->title }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $document->client?->display_name }} · {{ $document->status->meaning() }}</p>
                                </div>
                                <span class="shrink-0 text-xs text-slate-400" title="Last moved">{{ $document->updated_at->diffForHumans() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Documents by status (CSS bar chart — no extra library) --}}
        <x-card title="Documents by Status">
            @php
                $barColors = [
                    'blue' => 'bg-blue-500', 'indigo' => 'bg-indigo-500', 'amber' => 'bg-amber-500',
                    'purple' => 'bg-purple-500', 'green' => 'bg-green-500', 'gray' => 'bg-slate-400', 'red' => 'bg-red-500',
                ];
            @endphp
            <ul class="space-y-3">
                @foreach ($statusChart as $row)
                    <li>
                        <a href="{{ route('documents.index', ['status' => $row['status']->value]) }}" class="group block">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600 group-hover:text-slate-900">{{ $row['status']->label() }}</span>
                                <span class="font-semibold text-slate-900">{{ $row['count'] }}</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $barColors[$row['status']->color()] ?? 'bg-slate-400' }}" style="width: {{ $row['percent'] }}%"></div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Recent activity --}}
        <x-card title="Recent Document Activity" :padding="false" class="lg:col-span-2">
            @forelse ($recentMovements as $movement)
                <div class="flex items-start gap-4 border-b border-slate-100 px-5 py-4 last:border-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                        <x-icon :name="$movement->action->icon()" class="h-4 w-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($movement->document && ! $movement->document->trashed())
                                <a href="{{ route('documents.show', $movement->document) }}" class="font-mono text-xs font-semibold text-slate-900 hover:underline">{{ $movement->document->tracking_code }}</a>
                            @else
                                <span class="font-mono text-xs font-semibold text-slate-400">{{ $movement->document?->tracking_code }}</span>
                            @endif
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

        <div class="space-y-6">
            {{-- Workflow v2 (replaces due dates): documents that haven't moved the longest --}}
            <x-card title="In Process Longest" :padding="false">
                @if ($longestInProcess->isEmpty())
                    <p class="px-5 py-6 text-center text-sm text-slate-400">No documents in process.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($longestInProcess as $document)
                            <li>
                                <a href="{{ route('documents.show', $document) }}" class="block px-5 py-3 hover:bg-slate-50">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-xs font-semibold text-slate-900">{{ $document->tracking_code }}</span>
                                        <span class="text-xs text-slate-400">{{ $document->updated_at->diffForHumans(null, true) }}</span>
                                    </div>
                                    <p class="truncate text-sm text-slate-700">{{ $document->title }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $document->status->label() }} · with {{ $document->currentHolder?->name ?? 'nobody' }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Monthly intake --}}
            <x-card title="Documents Received (6 months)">
                <div class="flex h-36 items-end gap-2">
                    @foreach ($monthlyChart as $month)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ $month['full'] }}: {{ $month['count'] }}">
                            <span class="text-xs font-semibold text-slate-700">{{ $month['count'] }}</span>
                            <div class="w-full rounded-t-md {{ $loop->last ? 'bg-amber-500' : 'bg-slate-300' }}" style="height: {{ max($month['percent'], 2) }}%"></div>
                            <span class="text-xs text-slate-500">{{ $month['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
