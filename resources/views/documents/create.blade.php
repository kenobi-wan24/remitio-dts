<x-app-layout title="Record Document">
    <x-page-header title="Record Document" subtitle="A tracking code (DOC-YYYY-#####) is assigned automatically and the document starts as Received."
        :back="url()->previous() !== url()->current() ? url()->previous() : route('documents.index')" />

    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="max-w-4xl">
        @csrf
        @include('documents._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" :href="route('documents.index')">Cancel</x-button>
            <x-button icon="check-circle">Record Document</x-button>
        </div>
    </form>
</x-app-layout>
