<x-print-layout title="Client Case Summary" :subtitle="$client->display_name.' · '.$client->client_code">
    {{-- Client block --}}
    <div class="avoid-break grid grid-cols-2 gap-4 rounded-lg border border-slate-200 p-4 text-sm sm:grid-cols-4">
        <div><p class="text-[10px] uppercase text-slate-500">Client</p><p class="font-semibold">{{ $client->display_name }}</p><p class="text-xs text-slate-500">{{ $client->client_type->label() }}{{ $client->is_retainer ? ' · Retainer' : '' }}</p></div>
        <div><p class="text-[10px] uppercase text-slate-500">Contact</p><p>{{ $client->contact_number }}</p><p class="text-xs text-slate-500">{{ $client->email }}</p></div>
        <div class="col-span-2"><p class="text-[10px] uppercase text-slate-500">Address</p><p>{{ $client->address ?? '—' }}</p></div>
    </div>

    <div class="avoid-break mt-3 grid grid-cols-4 gap-2 text-center">
        @foreach (['Cases' => $stats['cases'], 'Active cases' => $stats['active_cases'], 'Documents' => $stats['documents'], 'In process' => $stats['in_process']] as $label => $value)
            <div class="rounded-lg border border-slate-200 p-2"><p class="text-lg font-bold">{{ $value }}</p><p class="text-[10px] uppercase text-slate-500">{{ $label }}</p></div>
        @endforeach
    </div>

    {{-- Cases --}}
    @forelse ($cases as $case)
        <section class="mt-6">
            <div class="avoid-break border-l-4 border-slate-800 pl-3">
                <p class="text-xs text-slate-500"><span class="font-mono">{{ $case->case_code }}</span> · {{ $case->case_type->label() }} · <strong>{{ $case->status->label() }}</strong></p>
                <h2 class="text-base font-bold">{{ $case->title }}</h2>
                <p class="text-xs text-slate-600">
                    {{ $case->docket_number ? 'Docket '.$case->docket_number.' · ' : '' }}{{ $case->court_or_venue ? $case->court_or_venue.' · ' : '' }}
                    Attorney: {{ $case->attorney?->name ?? 'Unassigned' }} ·
                    Opened {{ $case->date_opened->format('M d, Y') }}{{ $case->date_closed ? ' · Closed '.$case->date_closed->format('M d, Y') : '' }}
                </p>
            </div>
            @include('reports._summary-documents', ['documents' => $case->documents])
        </section>
    @empty
        <p class="mt-6 text-sm text-slate-500">No cases on record.</p>
    @endforelse

    @if ($standalone->isNotEmpty())
        <section class="mt-6">
            <div class="avoid-break border-l-4 border-slate-400 pl-3">
                <h2 class="text-base font-bold">Other documents (not under a case)</h2>
            </div>
            @include('reports._summary-documents', ['documents' => $standalone])
        </section>
    @endif
</x-print-layout>
