<x-app-layout title="Edit Document Type">
    <x-page-header title="Edit Document Type" :subtitle="$type->name" :back="route('admin.document-types.index')" />

    <form method="POST" action="{{ route('admin.document-types.update', $type) }}" class="max-w-xl">
        @csrf
        @method('PUT')
        <x-card>
            <div class="space-y-4">
                <x-form.input name="name" label="Name" :value="$type->name" required maxlength="100" />
                <x-form.input name="description" label="Description" :value="$type->description" maxlength="255" />
            </div>
        </x-card>
        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" :href="route('admin.document-types.index')">Cancel</x-button>
            <x-button icon="check-circle">Save Changes</x-button>
        </div>
    </form>
</x-app-layout>
