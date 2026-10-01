<x-app-layout :title="$q ? 'Search: '.$q : 'Search'">
    <x-page-header title="Search" subtitle="Find documents, cases, and clients in one place." />

    <form method="GET" action="{{ route('search') }}" class="mb-6">
        <div class="relative max-w-2xl">
            <x-icon name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $q }}" autofocus
                placeholder="Tracking code, case code, docket no., client name, document title..."
                class="block w-full rounded-xl border-slate-300 py-3 pl-12 pr-28 text-base shadow-sm focus:border-slate-500 focus:ring-slate-500">
            <x-button class="absolute right-2 top-1/2 -translate-y-1/2">Search</x-button>
        </div>
    </form>

    @if ($q === '')
        <x-card title="Search tips" class="max-w-2xl">
            <ul class="space-y-2 text-sm text-slate-600">
                <li class="flex gap-2"><x-icon name="chevron-right" class="mt-0.5 h-4 w-4 text-slate-400" /> Type a full code like <code class="rounded bg-slate-100 px-1 font-mono">DOC-2026-00012</code>, <code class="rounded bg-slate-100 px-1 font-mono">RR-2026-0005</code> or <code class="rounded bg-slate-100 px-1 font-mono">CL-2026-0003</code> to jump straight to it.</li>
                <li class="flex gap-2"><x-icon name="chevron-right" class="mt-0.5 h-4 w-4 text-slate-400" /> Search a client's name to see their cases and documents together.</li>
                <li class="flex gap-2"><x-icon name="chevron-right" class="mt-0.5 h-4 w-4 text-slate-400" /> Docket numbers and physical locations (e.g. "Cabinet A") are searchable too.</li>
                <li class="flex gap-2"><x-icon name="chevron-right" class="mt-0.5 h-4 w-4 text-slate-400" /> Press <kbd class="rounded border border-slate-300 bg-white px-1.5 font-mono text-xs">/</kbd> on any page to jump to the search bar.</li>
            </ul>
        </x-card>
    @elseif ($documentsTotal + $casesTotal + $clientsTotal === 0)
        <x-empty-state icon="magnifying-glass" title="No results for “{{ $q }}”"
            message="Check the spelling, try fewer words, or search by code or contact number." />
    @else
        <p class="mb-4 text-sm text-slate-500">
            Found <strong class="text-slate-800">{{ $documentsTotal }}</strong> {{ str('document')->plural($documentsTotal) }},
            <strong class="text-slate-800">{{ $casesTotal }}</strong> {{ str('case')->plural($casesTotal) }} and
            <strong class="text-slate-800">{{ $clientsTotal }}</strong> {{ str('client')->plural($clientsTotal) }}
            for “{{ $q }}”.
        </p>

        <div class="space-y-6">
            {{-- Documents --}}
            @if ($documentsTotal)
                <x-card title="Documents ({{ $documentsTotal }})" :padding="false">
                    @if ($documentsTotal > $documents->count())
                        <x-slot name="actions">
                            <a href="{{ route('documents.index', ['q' => $q]) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">View all {{ $documentsTotal }} →</a>
                        </x-slot>
                    @endif
                    <ul class="divide-y divide-slate-100">
                        @foreach ($documents as $document)
                            <li>
                                <a href="{{ route('documents.show', $document) }}" class="flex flex-col gap-1 px-5 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4">
                                    <span class="w-36 shrink-0 font-mono text-xs font-semibold text-slate-900"><x-highlight :text="$document->tracking_code" :term="$q" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-slate-900"><x-highlight :text="$document->title" :term="$q" /></span>
                                        <span class="block truncate text-xs text-slate-500">
                                            {{ $document->documentType?->name }} · <x-highlight :text="$document->client?->display_name" :term="$q" />
                                        </span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-2">
                                        <x-status-badge :status="$document->status" />
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            {{-- Cases --}}
            @if ($casesTotal)
                <x-card title="Cases ({{ $casesTotal }})" :padding="false">
                    @if ($casesTotal > $cases->count())
                        <x-slot name="actions">
                            <a href="{{ route('cases.index', ['q' => $q]) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">View all {{ $casesTotal }} →</a>
                        </x-slot>
                    @endif
                    <ul class="divide-y divide-slate-100">
                        @foreach ($cases as $case)
                            <li>
                                <a href="{{ route('cases.show', $case) }}" class="flex flex-col gap-1 px-5 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4">
                                    <span class="w-36 shrink-0 font-mono text-xs font-semibold text-slate-900"><x-highlight :text="$case->case_code" :term="$q" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-slate-900"><x-highlight :text="$case->title" :term="$q" /></span>
                                        <span class="block truncate text-xs text-slate-500">
                                            {{ $case->case_type->label() }}
                                            @if ($case->docket_number) · <x-highlight :text="$case->docket_number" :term="$q" /> @endif
                                            · {{ $case->attorney?->name ?? 'Unassigned' }}
                                        </span>
                                    </span>
                                    <x-status-badge :status="$case->status" class="shrink-0" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            {{-- Clients --}}
            @if ($clientsTotal)
                <x-card title="Clients ({{ $clientsTotal }})" :padding="false">
                    @if ($clientsTotal > $clients->count())
                        <x-slot name="actions">
                            <a href="{{ route('clients.index', ['q' => $q]) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">View all {{ $clientsTotal }} →</a>
                        </x-slot>
                    @endif
                    <ul class="divide-y divide-slate-100">
                        @foreach ($clients as $client)
                            <li>
                                <a href="{{ route('clients.show', $client) }}" class="flex flex-col gap-1 px-5 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4">
                                    <span class="w-36 shrink-0 font-mono text-xs font-semibold text-slate-900"><x-highlight :text="$client->client_code" :term="$q" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-slate-900"><x-highlight :text="$client->display_name" :term="$q" /></span>
                                        <span class="block truncate text-xs text-slate-500">
                                            <x-highlight :text="$client->contact_number" :term="$q" />
                                            @if ($client->email) · <x-highlight :text="$client->email" :term="$q" /> @endif
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-xs text-slate-500">{{ $client->cases_count }} {{ str('case')->plural($client->cases_count) }} · {{ $client->documents_count }} {{ str('doc')->plural($client->documents_count) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    @endif
</x-app-layout>
