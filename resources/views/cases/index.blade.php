@php use App\Enums\CaseStatus; use App\Enums\CaseType; @endphp

<x-app-layout :title="$showTrashed ? 'Deleted Cases' : 'Cases'">
    <x-page-header :title="$showTrashed ? 'Deleted Cases' : 'Cases'"
        subtitle="{{ number_format($cases->total()) }} {{ str('case')->plural($cases->total()) }} {{ $showTrashed ? 'in trash' : 'found' }}">
        <x-slot name="actions">
            @can('admin')
                @if ($showTrashed)
                    <x-button variant="secondary" :href="route('cases.index')" icon="arrow-left">Back to Cases</x-button>
                @else
                    <x-button variant="ghost" :href="route('cases.index', ['view' => 'trash'])" icon="trash">Trash</x-button>
                @endif
            @endcan
            @unless ($showTrashed)
                <x-button :href="route('cases.create')" icon="plus">New Case</x-button>
            @endunless
        </x-slot>
    </x-page-header>

    {{-- Status tabs --}}
    @unless ($showTrashed)
        <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
            @php $tabs = ['' => 'All'] + CaseStatus::options(); @endphp
            @foreach ($tabs as $value => $label)
                @php
                    $active = ($filters['status'] ?? '') === $value;
                    $count = $value === '' ? $statusCounts->sum() : ($statusCounts[$value] ?? 0);
                @endphp
                <a href="{{ request()->fullUrlWithQuery(['status' => $value ?: null, 'page' => null]) }}"
                    @class([
                        '-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium transition',
                        'border-slate-800 text-slate-900' => $active,
                        'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' => ! $active,
                    ])>
                    {{ $label }}
                    <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-slate-800 text-white' => $active, 'bg-slate-100 text-slate-600' => ! $active])>{{ $count }}</span>
                </a>
            @endforeach
        </div>
    @endunless

    {{-- Filters --}}
    <form method="GET" action="{{ route('cases.index') }}" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
        @if ($showTrashed) <input type="hidden" name="view" value="trash"> @endif
        @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif

        <div class="sm:col-span-2 lg:col-span-4">
            <label for="q" class="block text-xs font-medium text-slate-500">Search</label>
            <div class="relative mt-1">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Case code, docket no., title, client..."
                    class="block w-full rounded-lg border-slate-300 pl-9 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
        </div>

        <div class="lg:col-span-2">
            <label for="type" class="block text-xs font-medium text-slate-500">Type</label>
            <select id="type" name="type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">All types</option>
                @foreach (CaseType::options() as $value => $label)
                    <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="lg:col-span-2">
            <label for="attorney" class="block text-xs font-medium text-slate-500">Attorney</label>
            <select id="attorney" name="attorney" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">All attorneys</option>
                @if (auth()->user()->isAdmin())
                    <option value="me" @selected($filters['attorney'] === 'me')>My cases</option>
                @endif
                @foreach ($attorneys as $id => $name)
                    <option value="{{ $id }}" @selected((string) $filters['attorney'] === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="lg:col-span-2">
            <label for="sort" class="block text-xs font-medium text-slate-500">Sort by</label>
            <select id="sort" name="sort" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="newest" @selected($filters['sort'] === 'newest')>Newest opened</option>
                <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest opened</option>
                <option value="title" @selected($filters['sort'] === 'title')>Title (A–Z)</option>
            </select>
        </div>

        <div class="flex gap-2 lg:col-span-2">
            <x-button class="flex-1">Filter</x-button>
            @if (request()->hasAny(['q', 'type', 'attorney', 'sort', 'status']))
                <x-button variant="ghost" :href="route('cases.index', $showTrashed ? ['view' => 'trash'] : [])">Reset</x-button>
            @endif
        </div>
    </form>

    @if ($cases->isEmpty())
        @if (request()->hasAny(['q', 'type', 'attorney', 'status']))
            <x-empty-state icon="magnifying-glass" title="No matching cases" message="Try another search term or clear the filters." />
        @elseif ($showTrashed)
            <x-empty-state icon="trash" title="Trash is empty" message="Deleted cases will appear here and can be restored." />
        @else
            <x-empty-state icon="briefcase" title="No cases yet" message="Open the first case for a client.">
                <x-button :href="route('cases.create')" icon="plus">New Case</x-button>
            </x-empty-state>
        @endif
    @else
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Case</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Attorney</th>
                            <th class="px-5 py-3">Opened</th>
                            <th class="px-5 py-3 text-center">Docs</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($cases as $case)
                            <tr class="hover:bg-slate-50">
                                <td class="max-w-xs px-5 py-3">
                                    @if ($showTrashed)
                                        <p class="truncate font-medium text-slate-900">{{ $case->title }}</p>
                                    @else
                                        <a href="{{ route('cases.show', $case) }}" class="block truncate font-medium text-slate-900 hover:underline" title="{{ $case->title }}">{{ $case->title }}</a>
                                    @endif
                                    <p class="text-xs text-slate-500">
                                        <span class="font-mono">{{ $case->case_code }}</span>
                                        @if ($case->docket_number) · {{ $case->docket_number }} @endif
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    @if ($case->client && ! $case->client->trashed())
                                        <a href="{{ route('clients.show', $case->client) }}" class="text-slate-700 hover:underline">{{ $case->client->display_name }}</a>
                                    @else
                                        <span class="text-slate-400">{{ $case->client?->display_name ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $case->case_type->label() }}</td>
                                <td class="px-5 py-3"><x-status-badge :status="$case->status" /></td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $case->attorney?->name ?? 'Unassigned' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $case->date_opened->format('M d, Y') }}</td>
                                <td class="px-5 py-3 text-center text-slate-700">{{ $case->documents_count }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    @if ($showTrashed)
                                        @can('restore', $case)
                                            <form method="POST" action="{{ route('cases.restore', $case) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-button variant="secondary" size="sm">Restore</x-button>
                                            </form>
                                        @endcan
                                    @else
                                        <x-button variant="ghost" size="sm" :href="route('cases.show', $case)" icon="eye">View</x-button>
                                        <x-button variant="ghost" size="sm" :href="route('cases.edit', $case)" icon="pencil-square">Edit</x-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-4">{{ $cases->links() }}</div>
    @endif
</x-app-layout>
