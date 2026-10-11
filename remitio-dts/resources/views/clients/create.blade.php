<x-app-layout title="New Client">
    <x-page-header title="New Client" subtitle="A client code will be assigned automatically when you save."
        :back="route('clients.index')" />

    <form method="POST" action="{{ route('clients.store') }}" class="max-w-4xl">
        @csrf
        @include('clients._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" :href="route('clients.index')">Cancel</x-button>
            <x-button icon="check-circle">Save Client</x-button>
        </div>
    </form>
</x-app-layout>
