{{--
    Delete button + confirmation modal in one:
        <x-confirm-delete :action="route('clients.destroy', $client)"
            title="Delete this client?" message="Their record will be moved to trash." />
--}}
@props([
    'action',
    'title' => 'Delete this record?',
    'message' => 'This action cannot be undone.',
    'label' => 'Delete',
    'confirmLabel' => 'Yes, delete',
])

<div x-data="{ open: false }" class="inline-block">
    <x-button type="button" variant="danger" size="sm" icon="trash" x-on:click="open = true">{{ $label }}</x-button>

    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity x-on:keydown.escape.window="open = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div x-on:click.outside="open = false" x-show="open" x-transition
                class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <x-icon name="exclamation-triangle" class="h-5 w-5 text-red-600" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $message }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ $action }}" class="mt-6 flex justify-end gap-2">
                    @csrf
                    @method('DELETE')
                    <x-button type="button" variant="secondary" x-on:click="open = false">Cancel</x-button>
                    <x-button variant="danger">{{ $confirmLabel }}</x-button>
                </form>
            </div>
        </div>
    </template>
</div>
