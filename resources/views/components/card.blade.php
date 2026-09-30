{{--
    <x-card title="Case Details"> ... </x-card>
    <x-card :padding="false"> <table>...</table> </x-card>

    Only cards WITHOUT padding (full-width tables/lists) clip their content, so tables keep
    rounded corners. Padded cards (forms) don't clip, so dropdowns can open over what's below.
--}}
@props(['title' => null, 'padding' => true])

<div {{ $attributes->class([
    'rounded-xl border border-slate-200 bg-white shadow-sm',
    'overflow-hidden' => ! $padding,
]) }}>
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