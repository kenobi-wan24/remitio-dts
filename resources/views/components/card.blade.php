{{--
    <x-card title="Case Details"> ... </x-card>
    <x-card :padding="false"> <table>...</table> </x-card>
--}}
@props(['title' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>
            @isset($actions) <div class="flex items-center gap-2">{{ $actions }}</div> @endisset
        </div>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</div>
