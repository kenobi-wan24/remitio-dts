@if ($documents->isEmpty())
    <p class="mt-2 text-xs text-slate-400">No documents.</p>
@else
    <table class="mt-2 w-full border-collapse text-[11px]">
        <thead>
            <tr class="border-b border-slate-400 text-left uppercase text-slate-500">
                <th class="py-1 pr-2">Tracking Code</th>
                <th class="py-1 pr-2">Document</th>
                <th class="py-1 pr-2">Received</th>
                <th class="py-1 pr-2">Status</th>
                <th class="py-1 pr-2">With / Location</th>
                <th class="py-1">Due</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($documents as $doc)
                <tr class="border-b border-slate-100 align-top">
                    <td class="whitespace-nowrap py-1 pr-2 font-mono">{{ $doc->tracking_code }}</td>
                    <td class="py-1 pr-2">{{ $doc->title }} <span class="text-slate-500">· {{ $doc->documentType?->name }}</span></td>
                    <td class="whitespace-nowrap py-1 pr-2">{{ $doc->date_received->format('M d, Y') }}</td>
                    <td class="whitespace-nowrap py-1 pr-2">{{ $doc->status->label() }}</td>
                    <td class="py-1 pr-2">{{ $doc->currentHolder?->name ?? ($doc->status->isFinal() ? 'Out of office' : '—') }}{{ $doc->physical_location ? ' · '.$doc->physical_location : '' }}</td>
                    <td class="whitespace-nowrap py-1 {{ $doc->is_overdue ? 'font-semibold text-red-700' : '' }}">{{ $doc->due_date?->format('M d, Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
