@php use App\Enums\DocumentStatus; @endphp

<x-app-layout :title="$showTrashed ? 'Deleted Documents' : 'Documents'">
    <x-page-header :title="$showTrashed ? 'Deleted Documents' : 'Documents'"
        subtitle="{{ number_format($documents->total()) }} {{ str('document')->plural($documents->total()) }} {{ $showTrashed ? 'in trash' : 'found' }}">
        <x-slot name="actions">
            @can('admin')
                @if ($showTrashed)
                    <x-button variant="secondary" :href="route('documents.index')" icon="arrow-left">Back to Documents</x-button>
                @else
                    <x-button variant="ghost" :href="route('documents.index', ['view' => 'trash'])" icon="trash">Trash</x-button>
                @endif
            @endcan
            @unless ($showTrashed)
                <x-button variant="secondary" :href="route('documents.index', ['holder' => 'me'])" icon="inbox">With Me</x-button>
                <x-button :href="route('documents.create')" icon="plus">Record Document</x-button>
            @endunless
        </x-slot>
    </x-page-header>

    {{-- Status tabs --}}
    @unless ($showTrashed)
        <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
            @php $tabs = ['' => 'All'] + DocumentStatus::options(); @endphp
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
    <form method="GET" action="{{ route('documents.index') }}" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
        @if ($showTrashed) <input type="hidden" name="view" value="trash"> @endif
        @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif

        <div class="sm:col-span-2 lg:col-span-6">
            <label for="q" class="block text-xs font-medium text-slate-500">Search</label>
            <div class="relative mt-1">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Tracking code, title, client, case..."
                    class="block w-full rounded-lg border-slate-300 pl-9 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
        </div>

        @foreach ([
            ['type', 'Type', ['' => 'All types'] + $documentTypes->all(), $filters['type']],
            ['holder', 'With', ['' => 'Anyone', 'me' => 'Me'] + $users->all(), $filters['holder']],
            ['sort', 'Sort by', ['newest' => 'Newest received', 'oldest' => 'Oldest received'], $filters['sort']],
        ] as [$field, $label, $options, $current])
            <div class="lg:col-span-2">
                <label for="{{ $field }}" class="block text-xs font-medium text-slate-500">{{ $label }}</label>
                <select id="{{ $field }}" name="{{ $field }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($options as $value => $text)
                        <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach

        {{-- Phase 8: notarial register lookup --}}
        <div class="sm:col-span-2 lg:col-span-12" x-data="{ open: @js(! empty($filters['notarial'])) }">
            <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800">
                <x-icon name="chevron-right" class="h-3 w-3 transition" x-bind:class="open && 'rotate-90'" />
                Notarial register lookup (Doc. / Page / Book / Series)
            </button>
            <div x-show="open" x-cloak class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['n_doc' => 'Doc. No.', 'n_page' => 'Page No.', 'n_book' => 'Book No.', 'n_series' => 'Series'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="block text-xs font-medium text-slate-500">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ request($field) }}"
                            @if ($field !== 'n_book') type="number" min="1" @else type="text" @endif
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex gap-2 sm:col-span-2 lg:col-span-12 lg:justify-end">
            @if (request()->hasAny(['q', 'type', 'holder', 'sort', 'status', 'n_doc', 'n_page', 'n_book', 'n_series']))
                <x-button variant="ghost" :href="route('documents.index', $showTrashed ? ['view' => 'trash'] : [])">Reset</x-button>
            @endif
            <x-button>Apply Filters</x-button>
        </div>
    </form>

    @if ($documents->isEmpty())
        @if (request()->hasAny(['q', 'type', 'holder', 'status', 'n_doc', 'n_page', 'n_book', 'n_series']))
            <x-empty-state icon="magnifying-glass" title="No matching documents" message="Try another search term or clear the filters." />
        @elseif ($showTrashed)
            <x-empty-state icon="trash" title="Trash is empty" message="Deleted documents will appear here and can be restored." />
        @else
            <x-empty-state icon="document-text" title="No documents yet" message="Record the first document received by the office.">
                <x-button :href="route('documents.create')" icon="plus">Record Document</x-button>
            </x-empty-state>
        @endif
    @else
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Tracking Code</th>
                            <th class="px-5 py-3">Document</th>
                            <th class="px-5 py-3">Client / Case</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">With</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($documents as $document)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs">
                                    @if ($showTrashed)
                                        <span class="font-semibold text-slate-900">{{ $document->tracking_code }}</span>
                                    @else
                                        <a href="{{ route('documents.show', $document) }}" class="font-semibold text-slate-900 hover:underline">{{ $document->tracking_code }}</a>
                                    @endif
                                </td>
                                <td class="max-w-xs px-5 py-3">
                                    <p class="truncate font-medium text-slate-900" title="{{ $document->title }}">{{ $document->title }}</p>
                                    <p class="flex items-center gap-1 text-xs text-slate-500">
                                        {{ $document->documentType?->name }}
                                        @if ($document->attachments_count)
                                            · <x-icon name="folder-open" class="h-3 w-3" /> {{ $document->attachments_count }}
                                        @endif
                                    </p>
                                    @if ($document->notarialEntry)
                                        @php $n = $document->notarialEntry; @endphp
                                        <p class="text-xs text-slate-400">Doc {{ $n->doc_no }} · Pg {{ $n->page_no }} · Bk {{ $n->book_no }} · {{ $n->series }}</p>
                                    @endif
                                </td>
                                <td class="max-w-[14rem] px-5 py-3">
                                    <p class="truncate text-slate-700">{{ $document->client?->display_name ?? '—' }}</p>
                                    <p class="font-mono text-xs text-slate-500">{{ $document->legalCase?->case_code ?? 'No case' }}</p>
                                </td>
                                <td class="px-5 py-3"><x-status-badge :status="$document->status" /></td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $document->currentHolder?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    @if ($showTrashed)
                                        @can('restore', $document)
                                            <form method="POST" action="{{ route('documents.restore', $document) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-button variant="secondary" size="sm">Restore</x-button>
                                            </form>
                                        @endcan
                                    @else
                                        <x-button variant="ghost" size="sm" :href="route('documents.show', $document)" icon="eye">View</x-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-4">{{ $documents->links() }}</div>
    @endif
</x-app-layout>
