<x-app-layout title="Dashboard">
    <x-page-header :title="$greeting.', '.$firstName"
        subtitle="Here's what's happening in the office today, {{ now()->format('l, F j, Y') }}.">
        <x-slot name="actions">
            <x-button :href="route('documents.create')" icon="plus">Record Document</x-button>
        </x-slot>
    </x-page-header>

    {{-- KPIs — each one states its counting rule (R1) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active Cases" :value="$stats['active_cases']" icon="briefcase" color="blue" :href="route('cases.index')"
            caption="Cases not yet closed" />
        <x-stat-card label="Documents In Process" :value="$stats['in_process']" icon="document-text" color="indigo"
            :href="route('documents.index', ['show' => 'in_process'])" caption="Not yet released or archived" />
        <x-stat-card label="Awaiting Review" :value="$stats['awaiting_review']" icon="eye" color="amber"
            :href="route('documents.index', ['status' => 'for_review'])" caption="Drafts waiting for a lawyer's check" />
        <x-stat-card label="Pending My Action" :value="$stats['pending_mine']" icon="inbox" color="green"
            :href="route('documents.index', ['holder' => 'me', 'show' => 'in_process'])" caption="The next step is yours" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- R1/R4: my documents, by priority (default) or by the action they need --}}
        <x-card title="My Action Queue" :padding="false" class="lg:col-span-2">
            <x-slot name="actions">
                <div class="flex rounded-lg bg-slate-100 p-0.5 text-xs font-medium">
                    @foreach (['priority' => 'By priority', 'action' => 'By action'] as $view => $text)
                        <a href="{{ route('dashboard', $view === 'priority' ? [] : ['queue' => $view]) }}"
                            @class(['rounded-md px-2.5 py-1', 'bg-white text-slate-900 shadow-sm' => $queueView === $view, 'text-slate-500 hover:text-slate-800' => $queueView !== $view])>{{ $text }}</a>
                    @endforeach
                </div>
                <a href="{{ route('documents.index', ['holder' => 'me', 'show' => 'in_process', 'sort' => 'priority']) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">View all →</a>
            </x-slot>

            @if (empty($actionQueue))
                <x-empty-state class="m-5" icon="check-circle" title="All caught up" message="No documents need your action right now." />
            @else
                @foreach ($actionQueue as $group)
                    @php
                        $groupLink = $group['status']
                            ? route('documents.index', ['holder' => 'me', 'status' => $group['status']->value])
                            : route('documents.index', ['holder' => 'me', 'sort' => 'priority',
                                'show' => $group['group'] === \App\Enums\PriorityGroup::OnHold ? 'on_hold' : 'in_process']);
                    @endphp
                    <div class="border-b border-slate-100 last:border-0">
                        <a href="{{ $groupLink }}"
                            class="flex items-center justify-between gap-2 bg-slate-50 px-5 py-2 hover:bg-slate-100">
                            <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                @if ($group['status'])
                                    <x-status-badge :status="$group['status']" />
                                    {{ $group['label'] }}
                                @else
                                    <x-status-badge :color="$group['group']->color()" :label="$group['label']" />
                                    <span class="font-normal normal-case tracking-normal text-slate-400">{{ $group['group']->hint() }}</span>
                                @endif
                            </span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">{{ $group['count'] }}</span>
                        </a>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($group['documents'] as $document)
                                <li>
                                    <a href="{{ route('documents.show', $document) }}" class="flex items-center gap-4 px-5 py-2.5 hover:bg-slate-50">
                                        <span class="w-28 shrink-0 font-mono text-xs font-semibold text-slate-900">{{ $document->tracking_code }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm text-slate-700">{{ $document->title }}</span>
                                            <span class="block truncate text-xs text-slate-500">
                                                @unless ($group['status']) <span class="font-medium text-slate-600">{{ $document->status->pendingAction() }}</span> · @endunless
                                                {{ $document->client?->display_name }}
                                                @if ($document->isOnHold()) · <span class="text-purple-700">{{ $document->on_hold_reason }}</span> @endif
                                            </span>
                                        </span>
                                        @if ($document->urgency !== \App\Enums\Urgency::Normal)
                                            <x-status-badge :status="$document->urgency" class="shrink-0" />
                                        @endif
                                        @if ($document->activeDeadline())
                                            <span @class(['shrink-0 text-xs', 'font-semibold text-red-600' => $document->isOverdue(), 'text-slate-500' => ! $document->isOverdue()])>due {{ $document->deadline->format('M d') }}</span>
                                        @endif
                                        <span class="shrink-0 text-xs text-slate-400" title="Last step: {{ $document->waitingSince()->format('M d, Y h:i A') }}">
                                            waiting {{ $document->waitingSince()->diffForHumans(null, true) }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                            @if ($group['count'] > $group['documents']->count())
                                <li class="px-5 py-2 text-xs">
                                    <a href="{{ $groupLink }}" class="text-slate-500 hover:text-slate-900">
                                        + {{ $group['count'] - $group['documents']->count() }} more
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            @endif
        </x-card>

        {{-- R1: in-process statuses only; the bars add up to the "Documents In Process" KPI --}}
        <x-card title="Documents In Process">
            @php
                $barColors = [
                    'blue' => 'bg-blue-500', 'indigo' => 'bg-indigo-500', 'amber' => 'bg-amber-500',
                    'purple' => 'bg-purple-500', 'green' => 'bg-green-500', 'gray' => 'bg-slate-400', 'red' => 'bg-red-500',
                ];
            @endphp
            <ul class="space-y-3">
                @foreach ($statusChart['rows'] as $row)
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

            {{-- How the numbers reconcile --}}
            <dl class="mt-5 space-y-1 border-t border-slate-200 pt-3 text-sm">
                <div class="flex justify-between font-semibold text-slate-900">
                    <dt>In process</dt><dd>{{ number_format($statusChart['in_process']) }}</dd>
                </div>
                <div class="flex justify-between text-slate-500">
                    <dt>
                        <a href="{{ route('documents.index', ['show' => 'completed']) }}" class="hover:text-slate-900 hover:underline">Completed</a>
                        <span class="text-xs">(released {{ $statusChart['released'] }} · archived {{ $statusChart['archived'] }})</span>
                    </dt>
                    <dd>{{ number_format($statusChart['completed']) }}</dd>
                </div>
                <div class="flex justify-between border-t border-dashed border-slate-200 pt-1 text-slate-700">
                    <dt>All documents</dt><dd>{{ number_format($statusChart['total']) }}</dd>
                </div>
            </dl>
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
                            @if ($movement->action->isOutOfOfficeStep())
                                @if ($movement->location)
                                    <span class="text-xs text-slate-500">{{ $movement->location }}</span>
                                @endif
                            @elseif ($movement->to_status)
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
                <x-empty-state class="m-5" title="No document activity yet" message="Movements will appear here once documents are requested in and forwarded." />
            @endforelse
        </x-card>

        <div class="space-y-6">
            {{-- R2 / Problem 3: physical copies outside the office --}}
            <x-card title="Out of Office ({{ $outOfOfficeCount }})" :padding="false">
                @if ($outOfOfficeCount > $outOfOffice->count())
                    <x-slot name="actions">
                        <a href="{{ route('documents.index', ['show' => 'out']) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">View all →</a>
                    </x-slot>
                @endif
                @if ($outOfOffice->isEmpty())
                    <p class="px-5 py-6 text-center text-sm text-slate-400">All physical copies are in the office.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($outOfOffice as $document)
                            <li>
                                <a href="{{ route('documents.show', $document) }}" class="block px-5 py-3 hover:bg-slate-50">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-xs font-semibold text-slate-900">{{ $document->tracking_code }}</span>
                                        <span @class(['text-xs', 'font-semibold text-red-600' => $document->isOutOverdue(), 'text-slate-400' => ! $document->isOutOverdue()])>
                                            {{ $document->isOutOverdue() ? 'past expected return' : 'out '.$document->out_since->diffForHumans(null, true) }}
                                        </span>
                                    </div>
                                    <p class="truncate text-sm text-slate-700">{{ $document->out_destination }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $document->out_purpose }} · {{ $document->out_carried_by }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

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
                                        <span class="text-xs text-slate-400" title="Since the last step">{{ $document->waitingSince()->diffForHumans(null, true) }}</span>
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
            <x-card title="Documents Requested In (6 months)">
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
