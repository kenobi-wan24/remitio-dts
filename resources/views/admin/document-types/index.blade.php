<x-app-layout title="Document Types">
    <x-page-header title="Document Types" subtitle="Categories used when recording documents. Types in use can be hidden but not deleted." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Add Document Type" class="self-start">
            <form method="POST" action="{{ route('admin.document-types.store') }}" class="space-y-4">
                @csrf
                <x-form.input name="name" label="Name" required maxlength="100" placeholder="e.g. Compromise Agreement" />
                <x-form.input name="description" label="Description" maxlength="255" placeholder="Optional" />
                <div class="flex justify-end">
                    <x-button icon="plus">Add Type</x-button>
                </div>
            </form>
        </x-card>

        <x-card :padding="false" class="lg:col-span-2">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3 text-center">Documents</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($types as $type)
                            <tr @class(['hover:bg-slate-50', 'opacity-60' => ! $type->is_active])>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-900">{{ $type->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $type->description ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($type->documents_count)
                                        <a href="{{ route('documents.index', ['type' => $type->id]) }}" class="text-slate-700 hover:underline">{{ $type->documents_count }}</a>
                                    @else
                                        <span class="text-slate-400">0</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <x-status-badge :color="$type->is_active ? 'green' : 'gray'" :label="$type->is_active ? 'In use' : 'Hidden'" />
                                </td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button variant="ghost" size="sm" :href="route('admin.document-types.edit', $type)" icon="pencil-square">Edit</x-button>
                                        <form method="POST" action="{{ route('admin.document-types.toggle', $type) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button variant="ghost" size="sm">{{ $type->is_active ? 'Hide' : 'Show' }}</x-button>
                                        </form>
                                        @if ($type->documents_count === 0)
                                            <x-confirm-delete :action="route('admin.document-types.destroy', $type)" label=""
                                                title="Delete “{{ $type->name }}”?" message="This type is not used by any document." />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-app-layout>
