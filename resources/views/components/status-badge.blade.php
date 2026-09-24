{{--
    Pass an enum that has label() and color():
        <x-status-badge :status="$document->status" />
    Or set it manually:
        <x-status-badge color="red" label="Overdue" />
--}}
@props(['status' => null, 'color' => null, 'label' => null])

@php
    $color ??= $status?->color() ?? 'gray';
    $label ??= $status?->label() ?? '';

    $styles = [
        'gray' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'green' => 'bg-green-50 text-green-700 ring-green-600/20',
        'purple' => 'bg-purple-50 text-purple-700 ring-purple-600/20',
        'red' => 'bg-red-50 text-red-700 ring-red-600/20',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($styles[$color] ?? $styles['gray'])]) }}>
    {{ $label }}
</span>
