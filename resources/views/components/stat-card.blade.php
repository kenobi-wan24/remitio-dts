@props(['label', 'value', 'icon', 'color' => 'slate', 'href' => null])

@php
    $colors = [
        'slate' => 'bg-slate-100 text-slate-600',
        'blue' => 'bg-blue-50 text-blue-600',
        'indigo' => 'bg-indigo-50 text-indigo-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'green' => 'bg-green-50 text-green-600',
        'red' => 'bg-red-50 text-red-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition'.($href ? ' hover:border-slate-300 hover:shadow-md' : '')]) }}>
    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg {{ $colors[$color] ?? $colors['slate'] }}">
        <x-icon :name="$icon" class="h-6 w-6" />
    </div>
    <div class="min-w-0">
        <p class="truncate text-sm text-slate-500">{{ $label }}</p>
        <p class="text-2xl font-bold text-slate-900">{{ number_format($value) }}</p>
    </div>
</{{ $tag }}>
