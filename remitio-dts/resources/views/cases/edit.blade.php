<x-app-layout title="Edit Case">
    <x-page-header title="Edit Case" subtitle="{{ $case->case_code }} · {{ $case->title }}"
        :back="route('cases.show', $case)" />

    <form method="POST" action="{{ route('cases.update', $case) }}" class="max-w-4xl">
        @csrf
        @method('PUT')
        @include('cases._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" :href="route('cases.show', $case)">Cancel</x-button>
            <x-button icon="check-circle">Save Changes</x-button>
        </div>
    </form>
</x-app-layout>
