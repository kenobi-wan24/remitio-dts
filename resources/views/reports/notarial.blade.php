<x-print-layout title="Notarial Register Listing" subtitle="Series of {{ $series }}" orientation="landscape" :filters="$filters" :csv="true">
    <p class="avoid-break mb-4 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
        Reference listing generated from documents recorded in the system ({{ $documents->count() }} {{ str('entry')->plural($documents->count()) }}).
        It helps prepare the monthly report but does not replace the official Notarial Register book. Dates shown are the dates the documents were received.
    </p>

    @if ($documents->isEmpty())
        <p class="py-10 text-center text-sm text-slate-500">No notarized documents recorded for these filters.</p>
    @else
        <table class="w-full border-collapse text-[11px]">
            <thead>
                <tr class="border-b-2 border-slate-800 text-left uppercase text-slate-600">
                    <th class="py-1.5 pr-2 text-right">Doc. No.</th>
                    <th class="py-1.5 pr-2 text-right">Page No.</th>
                    <th class="py-1.5 pr-2">Book No.</th>
                    <th class="py-1.5 pr-2">Date</th>
                    <th class="py-1.5 pr-2">Instrument</th>
                    <th class="py-1.5 pr-2">Principal / Client</th>
                    <th class="py-1.5">Tracking Code</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documents as $doc)
                    <tr class="border-b border-slate-200 align-top">
                        <td class="py-1.5 pr-2 text-right font-semibold">{{ $doc->notarial_doc_no }}</td>
                        <td class="py-1.5 pr-2 text-right">{{ $doc->notarial_page_no }}</td>
                        <td class="py-1.5 pr-2">{{ $doc->notarial_book_no }}</td>
                        <td class="whitespace-nowrap py-1.5 pr-2">{{ $doc->date_received->format('M d, Y') }}</td>
                        <td class="py-1.5 pr-2">
                            <p class="font-medium">{{ $doc->title }}</p>
                            <p class="text-slate-500">{{ $doc->documentType?->name }}</p>
                        </td>
                        <td class="py-1.5 pr-2">{{ $doc->client?->display_name }}</td>
                        <td class="whitespace-nowrap py-1.5 font-mono">{{ $doc->tracking_code }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-print-layout>
