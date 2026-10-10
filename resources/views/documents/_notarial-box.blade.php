{{--
    Workflow v2: the document's Notarial Register entry (a separate record).
    Can be added from Approved until Released. Expects: $document (with notarialEntry.recorder/notary loaded), $lawyers, $defaultLawyerId, $principalsPrefill
--}}
@php $entry = $document->notarialEntry; @endphp

<x-card title="Notarial Register">
    @if ($entry)
        <p class="text-sm font-semibold text-slate-900">{{ $entry->reference }}</p>
        <dl class="mt-3 space-y-2 text-sm">
            <div><dt class="text-xs uppercase text-slate-500">Notary public</dt><dd class="text-slate-900">{{ $entry->notary?->name ?? '—' }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Date notarized · Type of act</dt>
                <dd class="text-slate-900">{{ $entry->notarized_on?->format('M d, Y') ?? '—' }} · {{ $entry->act_type?->label() ?? '—' }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Principals</dt>
                <dd class="space-y-1">
                    @forelse ($entry->principals ?? [] as $p)
                        <p class="text-slate-900">{{ $p['name'] }}<span class="block text-xs text-slate-500">{{ $p['address'] }}@if ($p['evidence']) · {{ $p['evidence'] }}@endif</span></p>
                    @empty
                        <p class="text-slate-400">—</p>
                    @endforelse
                </dd></div>
            @if (! empty($entry->witnesses))
                <div><dt class="text-xs uppercase text-slate-500">Witnesses</dt>
                    <dd>@foreach ($entry->witnesses as $w)<p class="text-slate-900">{{ $w['name'] }} <span class="text-xs text-slate-500">{{ $w['address'] }}</span></p>@endforeach</dd></div>
            @endif
        </dl>
        <p class="mt-3 text-xs text-slate-500">
            Recorded {{ $entry->created_at->format('M d, Y h:i A') }}
            @if ($entry->recorder) by {{ $entry->recorder->name }} @endif
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-button variant="ghost" size="sm" :href="route('notarial.index', ['series' => $entry->series, 'book' => $entry->book_no, 'notary' => $entry->notary_id])" icon="eye">View in register</x-button>
            @can('update', $entry)
                <x-button variant="ghost" size="sm" :href="route('notarial.edit', $entry)" icon="pencil-square">Correct</x-button>
            @endcan
        </div>
    @elseif ($document->status->allowsNotarialEntry())
        <p class="mb-4 text-xs text-slate-500">If this document needs notarization, record its register entry here. This does not change the document's status.</p>
        <form method="POST" action="{{ route('notarial.store', $document) }}" class="space-y-4">
            @csrf
            @include('notarial._fields', ['prefill' => $principalsPrefill, 'defaultNotaryId' => $defaultLawyerId])
            <div class="flex justify-end">
                <x-button size="sm" icon="plus">Add notarial entry</x-button>
            </div>
        </form>
    @else
        <p class="text-sm text-slate-400">
            @if ($document->status->isFinal())
                Not notarized.
            @else
                A notarial entry can be added once the lawyer approves the document.
            @endif
        </p>
    @endif
</x-card>
