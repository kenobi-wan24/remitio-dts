@props(['href', 'active' => false, 'icon' => null])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
        'bg-white/10 text-white' => $active,
        'text-slate-300 hover:bg-white/5 hover:text-white' => ! $active,
    ]) }}>
    @if ($icon)
        <x-icon :name="$icon" :class="$active ? 'h-5 w-5 shrink-0 text-amber-400' : 'h-5 w-5 shrink-0 text-slate-400 group-hover:text-slate-200'" />
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
