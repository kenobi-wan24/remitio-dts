<x-app-layout title="New Case">
    <x-page-header title="New Case" subtitle="A case code (RR-YYYY-####) will be assigned automatically."
        :back="url()->previous() !== url()->current() ? url()->previous() : route('cases.index')" />

    <form method="POST" action="{{ route('cases.store') }}" class="max-w-4xl">
        @csrf
        @include('cases._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" :href="route('cases.index')">Cancel</x-button>
            <x-button icon="check-circle">Open Case</x-button>
        </div>
    </form>
</x-app-layout>
