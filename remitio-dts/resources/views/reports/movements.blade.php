<x-print-layout title="Document Movements Report" orientation="landscape" :filters="$filters" :csv="true">
    <div class="avoid-break mb-4 flex flex-wrap gap-2 text-xs">
        <span class="rounded-full border border-slate-300 px-3 py-1 font-semibold">{{ $total }} movements</span>
        @foreach ($summary as $label => $count)
            <span class="rounded-full border border-slate-200 px-3 py-1">{{ $label }}: <strong>{{ $count }}</strong></span>
        @endforeach
    </div>

    @if ($truncated)
        <p class="mb-3 rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">Showing the first {{ $movements->count() }} of {{ $total }} movements. Narrow the period or use the CSV export.</p>
    @endif

    @if ($movements->isEmpty())
        <p class="py-10 text-center text-sm text-slate-500">No movements in this period.</p>
    @else
        <table class="w-full border-collapse text-[11px]">
            <thead>
                <tr class="border-b-2 border-slate-800 text-left uppercase text-slate-600">
                    <th class="py-1.5 pr-2">Date &amp; Time</th>
                    <th class="py-1.5 pr-2">Document</th>
                    <th class="py-1.5 pr-2">Action</th>
                    <th class="py-1.5 pr-2">Status</th>
                    <th class="py-1.5 pr-2">From → To</th>
                    <th class="py-1.5 pr-2">Remarks</th>
                    <th class="py-1.5">Logged By</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $m)
                    <tr class="border-b border-slate-200 align-top">
                        <td class="whitespace-nowrap py-1.5 pr-2">{{ $m->acted_at->format('M d, Y') }}<br><span class="text-slate-500">{{ $m->acted_at->format('h:i A') }}</span></td>
                        <td class="py-1.5 pr-2">
                            <p class="font-mono font-semibold">{{ $m->document?->tracking_code }}</p>
                            <p class="text-slate-500">{{ $m->document?->title }}</p>
                        </td>
                        <td class="whitespace-nowrap py-1.5 pr-2 font-medium">{{ $m->action->label() }}</td>
                        <td class="whitespace-nowrap py-1.5 pr-2">
                            @if ($m->from_status && $m->from_status !== $m->to_status)
                                {{ $m->from_status->label() }} → {{ $m->to_status?->label() }}
                            @else
                                {{ $m->to_status?->label() ?? '—' }}
                            @endif
                        </td>
                        <td class="py-1.5 pr-2">
                            {{ $m->fromUser?->name ?? '—' }} → {{ $m->toUser?->name ?? ($m->to_status?->isFinal() ? 'Out of office' : '—') }}
                            @if ($m->location) <p class="text-slate-500">{{ $m->location }}</p> @endif
                        </td>
                        <td class="py-1.5 pr-2 text-slate-600">{{ $m->remarks }}</td>
                        <td class="whitespace-nowrap py-1.5">{{ $m->actor?->name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-print-layout>
