{{-- <x-due-badge :document="$document" /> → red if overdue, amber if due within 3 days --}}
@props(['document'])

@if (! $document->due_date)
    <span class="text-slate-400">—</span>
@elseif ($document->is_overdue)
    <x-status-badge color="red" label="Overdue · {{ $document->due_date->format('M d') }}" />
@elseif ($document->is_due_soon)
    <x-status-badge color="amber" label="Due {{ $document->due_date->format('M d') }}" />
@else
    <span class="whitespace-nowrap text-slate-600">{{ $document->due_date->format('M d, Y') }}</span>
@endif
