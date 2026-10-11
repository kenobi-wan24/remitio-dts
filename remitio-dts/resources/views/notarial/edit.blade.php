<x-app-layout title="Correct Notarial Entry">
    <x-page-header title="Correct Notarial Entry"
        subtitle="{{ $entry->document?->tracking_code }} · {{ $entry->document?->title }}"
        :back="route('documents.show', $entry->document_id)" />

    <div class="max-w-2xl space-y-6">
        <form method="POST" action="{{ route('notarial.update', $entry) }}">
            @csrf
            @method('PUT')
            <x-card title="Register Details">
                @include('notarial._fields', ['entry' => $entry])
                <p class="mt-4 text-xs text-slate-500">Corrections are recorded in the Activity Log with the old and new values.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('documents.show', $entry->document_id)">Cancel</x-button>
                    <x-button icon="check-circle">Save Correction</x-button>
                </div>
            </x-card>
        </form>

        @can('delete', $entry)
            <x-card title="Remove Entry">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm text-slate-600">Only if the entry was recorded on the wrong document.</p>
                    <x-confirm-delete :action="route('notarial.destroy', $entry)" label="Remove"
                        title="Remove this notarial entry?" message="{{ $entry->reference }} will be removed from the system's register. This is recorded in the Activity Log." />
                </div>
            </x-card>
        @endcan
    </div>
</x-app-layout>
