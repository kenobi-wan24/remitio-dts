<x-app-layout title="Notarial Register">
    <x-page-header title="Notarial Register"
        subtitle="Entries recorded for notarized documents. Each entry is linked to its document. Add entries from the document's page once it is approved." />

    {{-- Filters --}}
    <form method="GET" action="{{ route('notarial.index') }}" class="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4 lg:grid-cols-7 lg:items-end">
        @php $input = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500'; @endphp
        {{-- R2: search by name --}}
        <div class="col-span-2 sm:col-span-4 lg:col-span-3">
            <label for="name" class="block text-xs font-medium text-slate-500">Name</label>
            <div class="relative mt-1">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" id="name" name="name" value="{{ $filters['name'] }}" placeholder="Principal, witness or client — e.g. Dela Cruz"
                    class="block w-full rounded-lg border-slate-300 pl-9 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
        </div>
        <div class="col-span-2 lg:col-span-2">
            <label for="type" class="block text-xs font-medium text-slate-500">Instrument type</label>
            <select id="type" name="type" class="{{ $input }}">
                <option value="">All types</option>
                @foreach ($documentTypes as $id => $name)
                    <option value="{{ $id }}" @selected((string) $filters['type'] === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="block text-xs font-medium text-slate-500">Notarized from</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="{{ $input }}">
        </div>
        <div>
            <label for="to" class="block text-xs font-medium text-slate-500">to</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] }}" class="{{ $input }}">
        </div>
        <div>
            <label for="notary" class="block text-xs font-medium text-slate-500">Notary</label>
            <select id="notary" name="notary" class="{{ $input }}">
                <option value="">All notaries</option>
                @foreach ($lawyers as $id => $name)
                    <option value="{{ $id }}" @selected((string) $filters['notary'] === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="series" class="block text-xs font-medium text-slate-500">Series</label>
            <select id="series" name="series" class="{{ $input }}">
                <option value="">All years</option>
                @foreach ($seriesOptions as $year)
                    <option value="{{ $year }}" @selected((string) $filters['series'] === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="book" class="block text-xs font-medium text-slate-500">Book No.</label>
            <input id="book" name="book" value="{{ $filters['book'] }}" maxlength="10" placeholder="e.g. III" class="{{ $input }}">
        </div>
        <div>
            <label for="page_no" class="block text-xs font-medium text-slate-500">Page No.</label>
            <input id="page_no" name="page_no" type="number" min="1" value="{{ $filters['page'] }}" class="{{ $input }}">
        </div>
        <div>
            <label for="doc_no" class="block text-xs font-medium text-slate-500">Doc. No.</label>
            <input id="doc_no" name="doc_no" type="number" min="1" value="{{ $filters['doc'] }}" class="{{ $input }}">
        </div>
        <div>
            <label for="month" class="block text-xs font-medium text-slate-500">Month recorded</label>
            <select id="month" name="month" class="{{ $input }}">
                <option value="">All months</option>
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}" @selected($filters['month'] === $m)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <x-button class="flex-1">Find</x-button>
            @if (request()->hasAny(['name', 'type', 'from', 'to', 'notary', 'series', 'book', 'page_no', 'doc_no', 'month']))
                <x-button variant="ghost" :href="route('notarial.index')">Reset</x-button>
            @endif
        </div>
    </form>

    @if ($entries->isEmpty())
        <x-empty-state icon="building-library" title="No notarial entries found"
            :message="request()->hasAny(['name', 'type', 'from', 'to', 'notary', 'series', 'book', 'page_no', 'doc_no', 'month'])
                ? 'Try another name or number, or clear the filters.'
                : 'Entries appear here after they are added from a document\'s page (once the lawyer approves it).'" />
    @else
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-right">Doc. No.</th>
                            <th class="px-5 py-3 text-right">Page</th>
                            <th class="px-5 py-3">Book</th>
                            <th class="px-5 py-3">Series</th>
                            <th class="px-5 py-3">Notarized</th>
                            <th class="px-5 py-3">Instrument</th>
                            <th class="px-5 py-3">Principals</th>
                            <th class="px-5 py-3">Notary</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($entries as $entry)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ $entry->doc_no }}</td>
                                <td class="px-5 py-3 text-right text-slate-700">{{ $entry->page_no }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $entry->book_no }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $entry->series }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-700">
                                    {{ $entry->notarized_on?->format('M d, Y') ?? '—' }}
                                    <span class="block text-xs text-slate-500">{{ $entry->act_type?->label() }}</span>
                                </td>
                                <td class="max-w-xs px-5 py-3">
                                    @if ($entry->document && ! $entry->document->trashed() && $visibleDocumentIds->contains($entry->document_id))
                                        <a href="{{ route('documents.show', $entry->document) }}" class="block truncate font-medium text-slate-900 hover:underline">{{ $entry->document->title }}</a>
                                    @else
                                        <span class="flex items-center gap-1 truncate text-slate-500" @if ($entry->document && ! $visibleDocumentIds->contains($entry->document_id)) title="Confidential document — not assigned to you" @endif>
                                            @if ($entry->document && ! $visibleDocumentIds->contains($entry->document_id)) <x-icon name="lock-closed" class="h-3.5 w-3.5 shrink-0" /> @endif
                                            {{ $entry->document?->title ?? '—' }}
                                        </span>
                                    @endif
                                    <p class="font-mono text-xs text-slate-500">{{ $entry->document?->tracking_code }} · {{ $entry->document?->documentType?->name }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-700">
                                    @forelse ($entry->principals ?? [] as $p)
                                        <span class="block whitespace-nowrap"><x-highlight :text="$p['name']" :term="$filters['name']" /></span>
                                    @empty
                                        {{ $entry->document?->client?->display_name }}
                                    @endforelse
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $entry->notary?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    @can('update', $entry)
                                        <x-button variant="ghost" size="sm" :href="route('notarial.edit', $entry)" icon="pencil-square">Correct</x-button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
</x-app-layout>
