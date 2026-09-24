{{--
    <x-button>Save</x-button>                                  → submit button
    <x-button variant="secondary" :href="route('clients.index')">Cancel</x-button>  → link
    <x-button type="button" variant="danger" size="sm" icon="trash">Delete</x-button>
    Variants: primary · secondary · danger · accent · ghost      Sizes: sm · md
--}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit', 'icon' => null])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
    ];

    $variants = [
        'primary' => 'bg-slate-800 text-white shadow-sm hover:bg-slate-700 focus:ring-slate-500',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus:ring-slate-400',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus:ring-red-500',
        'accent' => 'bg-amber-500 text-slate-900 shadow-sm hover:bg-amber-400 focus:ring-amber-400',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-300',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
    $iconClass = $size === 'sm' ? 'h-4 w-4' : 'h-4 w-4 -ml-0.5';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" :class="$iconClass" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" :class="$iconClass" /> @endif
        {{ $slot }}
    </button>
@endif
