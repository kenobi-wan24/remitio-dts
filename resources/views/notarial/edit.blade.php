<x-app-layout title="Correct Notarial Entry">
    <x-page-header title="Correct Notarial Entry"
        subtitle="{{ $entry->document?->tracking_code }} · {{ $entry->document?->title }}"
        :back="route('documents.show', $entry->document_id)" />

    <div class="max-w-xl space-y-6">
        <form method="POST" action="{{ route('notarial.update', $entry) }}">
            @csrf
            @method('PUT')
            <x-card title="Register Details">
                <div class="grid grid-cols-2 gap-4">
                    <x-form.input name="doc_no" type="number" label="Doc. No." :value="$entry->doc_no" min="1" required />
                    <x-form.input name="page_no" type="number" label="Page No." :value="$entry->page_no" min="1" required />
                    <x-form.input name="book_no" label="Book No." :value="$entry->book_no" maxlength="10" required />
                    <x-form.input name="series" type="number" label="Series (year)" :value="$entry->series" min="1990" max="{{ now()->year + 1 }}" required />
                </div>
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
