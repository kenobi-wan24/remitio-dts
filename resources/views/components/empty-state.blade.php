{{--
    <x-empty-state title="No clients yet" message="Add your first client to get started.">
        <x-button :href="route('clients.create')" icon="plus">New Client</x-button>
    </x-empty-state>
--}}
@props(['title' => 'Nothing here yet', 'message' => null, 'icon' => 'folder-open'])

<div {{ $attributes->merge(['class' => 'rounded-xl border-2 border-dashed border-slate-200 bg-white px-6 py-12 text-center']) }}>
    <x-icon :name="$icon" class="mx-auto h-10 w-10 text-slate-400" />
    <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @if ($message)
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">{{ $message }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
