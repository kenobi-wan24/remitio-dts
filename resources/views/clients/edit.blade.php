<x-app-layout title="Edit Client">
    <x-page-header title="Edit Client" subtitle="{{ $client->client_code }} · {{ $client->display_name }}"
        :back="route('clients.show', $client)" />

    <form method="POST" action="{{ route('clients.update', $client) }}" class="max-w-4xl">
        @csrf
        @method('PUT')
        @include('clients._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" :href="route('clients.show', $client)">Cancel</x-button>
            <x-button icon="check-circle">Save Changes</x-button>
        </div>
    </form>
</x-app-layout>
