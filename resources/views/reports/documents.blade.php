<x-print-layout title="Documents Report" orientation="landscape" :filters="$filters" :csv="true">
    <div class="avoid-break mb-4 grid grid-cols-4 gap-2 text-center sm:grid-cols-6">
        <div class="rounded-lg border border-slate-200 p-2">
            <p class="text-lg font-bold text-slate-900">{{ $total }}</p><p class="text-[10px] uppercase text-slate-500">Total</p>
        </div>
        @foreach ($summary as $label => $count)
            <div class="rounded-lg border border-slate-200 p-2">
                <p class="text-lg font-bold text-slate-900">{{ $count }}</p><p class="text-[10px] uppercase text-slate-500">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    @if ($truncated)
        <p class="mb-3 rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">Showing the first {{ $documents->count() }} of {{ $total }} documents. Narrow the filters or use the CSV export.</p>
    @endif

    @if ($documents->isEmpty())
        <p class="py-10 text-center text-sm text-slate-500">No documents match these filters.</p>
    @else
        <table class="w-full border-collapse text-[11px]">
            <thead>
                <tr class="border-b-2 border-slate-800 text-left uppercase text-slate-600">
                    <th class="py-1.5 pr-2">#</th>
                    <th class="py-1.5 pr-2">Tracking Code</th>
                    <th class="py-1.5 pr-2">Received</th>
                    <th class="py-1.5 pr-2">Document</th>
                    <th class="py-1.5 pr-2">Client / Case</th>
                    <th class="py-1.5 pr-2">Status</th>
                    <th class="py-1.5 pr-2">With / Location</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documents as $doc)
                    <tr class="border-b border-slate-200 align-top">
                        <td class="py-1.5 pr-2 text-slate-400">{{ $loop->iteration }}</td>
                        <td class="whitespace-nowrap py-1.5 pr-2 font-mono font-semibold">{{ $doc->tracking_code }}</td>
                        <td class="whitespace-nowrap py-1.5 pr-2">{{ $doc->date_received->format('M d, Y') }}</td>
                        <td class="py-1.5 pr-2">
                            <p class="font-medium">{{ $doc->title }}</p>
                            <p class="text-slate-500">{{ $doc->documentType?->name }}@if ($doc->notarialEntry) · Doc {{ $doc->notarialEntry->doc_no }}/Pg {{ $doc->notarialEntry->page_no }}/Bk {{ $doc->notarialEntry->book_no }}/{{ $doc->notarialEntry->series }}@endif</p>
                        </td>
                        <td class="py-1.5 pr-2">
                            <p>{{ $doc->client?->display_name }}</p>
                            <p class="font-mono text-slate-500">{{ $doc->legalCase?->case_code ?? '—' }}</p>
                        </td>
                        <td class="whitespace-nowrap py-1.5 pr-2">{{ $doc->status->label() }}</td>
                        <td class="py-1.5 pr-2">
                            <p>{{ $doc->currentHolder?->name ?? '—' }}</p>
                            <p class="text-slate-500">{{ $doc->physical_location }}</p>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-print-layout>
