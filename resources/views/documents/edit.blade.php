<x-app-layout title="Edit Document">
    <x-page-header title="Edit Document" subtitle="{{ $document->tracking_code }} · {{ $document->title }}"
        :back="route('documents.show', $document)" />

    <form method="POST" action="{{ route('documents.update', $document) }}" class="max-w-4xl">
        @csrf
        @method('PUT')
        @include('documents._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" :href="route('documents.show', $document)">Cancel</x-button>
            <x-button icon="check-circle">Save Changes</x-button>
        </div>
    </form>
</x-app-layout>
