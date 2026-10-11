{{-- 11C: printed in the layout of the physical notarial register book (one page per Book/Page). --}}
@php use App\Enums\NotarialActType; @endphp

<x-print-layout title="Notarial Register" subtitle="Series of {{ $series }}" orientation="landscape" :filters="$filters" :csv="true">
    <p class="avoid-break mb-4 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
        Generated from the system's Notarial Register ({{ $entries->count() }} {{ str('entry')->plural($entries->count()) }}) to help prepare the monthly report.
        It does not replace the official Notarial Register book, which the notary signs.
    </p>

    @forelse ($pages as $key => $pageEntries)
        @php [$bookNo, $pageNo] = explode('|', $key); @endphp
        <section @class(['avoid-break', 'mt-8' => ! $loop->first]) style="{{ $loop->first ? '' : 'break-before: page;' }}">
            {{-- Book header --}}
            <div class="flex items-start justify-between text-[11px] leading-tight">
                <div>
                    <p class="font-bold uppercase">Notarial Register of {{ $notary?->name }}</p>
                    @if ($notary?->notary_office_address) <p class="uppercase">{{ $notary->notary_office_address }}</p> @endif
                    @if ($notary?->notarial_commission_no)
                        <p class="uppercase">Notarial Commission No. {{ $notary->notarial_commission_no }}
                            @if ($notary->commission_expires_on) expiring {{ $notary->commission_expires_on->format('F j, Y') }} @endif</p>
                    @endif
                </div>
                <div class="text-right uppercase">
                    <p>Page No. <span class="inline-block border-b border-slate-800 text-center font-semibold" style="min-width: 2rem">{{ $pageNo }}</span></p>
                    <p>Book No. <span class="inline-block border-b border-slate-800 text-center font-semibold" style="min-width: 2rem">{{ $bookNo }}</span></p>
                </div>
            </div>

            <table class="mt-2 w-full border-collapse border border-slate-800 text-[10px]">
                <thead>
                    <tr class="text-center align-middle font-semibold">
                        <th class="w-[9%] border border-slate-800 px-1 py-1">Doc. No./Date</th>
                        <th class="w-[14%] border border-slate-800 px-1 py-1">Type of Notarial Act</th>
                        <th class="w-[15%] border border-slate-800 px-1 py-1">Description of the Instrument</th>
                        <th class="w-[22%] border border-slate-800 px-1 py-1">Name &amp; Address of Principal Parties</th>
                        <th class="w-[8%] border border-slate-800 px-1 py-1">Signatures</th>
                        <th class="w-[14%] border border-slate-800 px-1 py-1">Evidence of Identity</th>
                        <th class="w-[18%] border border-slate-800 px-1 py-1">Name &amp; Address of the Witness</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pageEntries as $entry)
                        @php $principals = $entry->principals ?: [['name' => $entry->document?->client?->display_name, 'address' => '', 'evidence' => '']]; @endphp
                        <tr class="align-top">
                            <td class="border border-slate-800 px-1 py-1 text-center">
                                <p class="text-base font-bold">{{ $entry->doc_no }}</p>
                                <p>{{ $entry->notarized_on?->format('F j, Y') }}</p>
                            </td>
                            <td class="border border-slate-800 px-1 py-1 leading-snug">
                                @foreach (NotarialActType::cases() as $type)
                                    <p>{{ $type->label() }} ( {{ $entry->act_type === $type ? 'x' : ' ' }} )</p>
                                @endforeach
                            </td>
                            <td class="border border-slate-800 px-1 py-1 text-center">
                                <p class="font-medium">{{ $entry->document?->title }}</p>
                                <p class="font-mono text-[9px] text-slate-500">{{ $entry->document?->tracking_code }}</p>
                            </td>
                            <td class="border border-slate-800 px-1 py-1">
                                @foreach ($principals as $p)
                                    <p @class(['mt-1 border-t border-slate-300 pt-1' => ! $loop->first])><span class="font-medium">{{ $p['name'] }}</span>
                                        @if (! empty($p['address'])) <span class="block text-[9px]">{{ $p['address'] }}</span> @endif</p>
                                @endforeach
                            </td>
                            <td class="border border-slate-800 px-1 py-1"></td>
                            <td class="border border-slate-800 px-1 py-1">
                                @foreach ($principals as $p)
                                    <p @class(['mt-1 border-t border-slate-300 pt-1' => ! $loop->first])>{{ $p['evidence'] ?: '—' }}</p>
                                @endforeach
                            </td>
                            <td class="border border-slate-800 px-1 py-1">
                                @forelse ($entry->witnesses ?? [] as $w)
                                    <p @class(['mt-1 border-t border-slate-300 pt-1' => ! $loop->first])><span class="font-medium">{{ $w['name'] }}</span>
                                        @if (! empty($w['address'])) <span class="block text-[9px]">{{ $w['address'] }}</span> @endif</p>
                                @empty
                                    &nbsp;
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="py-10 text-center text-sm text-slate-500">No notarial entries recorded for these filters.</p>
    @endforelse

    {{-- The book's weekly certification --}}
    @if ($weekly->isNotEmpty())
        <section class="avoid-break mt-8 text-[11px]">
            <p class="font-semibold uppercase">Weekly certification</p>
            <table class="mt-2 w-full border-collapse text-[11px]">
                <tbody>
                    @foreach ($weekly as $week)
                        <tr class="border-b border-slate-200">
                            <td class="py-1.5 pr-4 align-top whitespace-nowrap">{{ $week['from']->format('M j') }} – {{ $week['to']->format('M j, Y') }}</td>
                            <td class="py-1.5">
                                I HEREBY CERTIFY that during the past week (<strong>{{ $week['acknowledged'] }}</strong>) instruments were acknowledged
                                and (<strong>{{ $week['sworn'] }}</strong>) sworn to before me.
                                @if ($week['other']) <span class="text-slate-500">({{ $week['other'] }} other notarial act/s.)</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-10 w-72 border-t border-slate-800 pt-1 text-center font-bold uppercase">
                {{ $notary?->name }}<br><span class="font-normal">Notary Public</span>
            </div>
        </section>
    @endif
</x-print-layout>
