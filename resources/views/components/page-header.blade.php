{{--
    <x-page-header title="Clients" subtitle="All clients of the firm">
        <x-slot name="actions"> <x-button ...>New Client</x-button> </x-slot>
    </x-page-header>
--}}
@props(['title', 'subtitle' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
                <x-icon name="arrow-left" class="h-4 w-4" /> Back
            </a>
        @endif
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
