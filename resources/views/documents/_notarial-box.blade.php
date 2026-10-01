{{--
    Workflow v2: the document's Notarial Register entry (a separate record).
    Can be added from Approved until Released. Expects: $document (with notarialEntry.recorder loaded)
--}}
@php $entry = $document->notarialEntry; @endphp

<x-card title="Notarial Register">
    @if ($entry)
        <p class="text-sm font-semibold text-slate-900">{{ $entry->reference }}</p>
        <p class="mt-1 text-xs text-slate-500">
            Recorded {{ $entry->created_at->format('M d, Y h:i A') }}
            @if ($entry->recorder) by {{ $entry->recorder->name }} @endif
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-button variant="ghost" size="sm" :href="route('notarial.index', ['series' => $entry->series, 'book' => $entry->book_no])" icon="eye">View in register</x-button>
            @can('update', $entry)
                <x-button variant="ghost" size="sm" :href="route('notarial.edit', $entry)" icon="pencil-square">Correct</x-button>
            @endcan
        </div>
    @elseif ($document->status->allowsNotarialEntry())
        <p class="mb-4 text-xs text-slate-500">If this document needs notarization, record its entry here. This does not change the document's status.</p>
        <form method="POST" action="{{ route('notarial.store', $document) }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <x-form.input name="doc_no" type="number" label="Doc. No." min="1" required />
                <x-form.input name="page_no" type="number" label="Page No." min="1" required />
                <x-form.input name="book_no" label="Book No." maxlength="10" placeholder="e.g. III" required />
                <x-form.input name="series" type="number" label="Series (year)" :value="now()->year" min="1990" max="{{ now()->year + 1 }}" required />
            </div>
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
