{{--
    Shows session flashes. In controllers:
        return redirect()->route(...)->with('success', 'Client saved.');
        ->with('error', '...')   ->with('warning', '...')
--}}
@php
    $styles = [
        'success' => ['border-green-200 bg-green-50 text-green-800', 'check-circle'],
        'error' => ['border-red-200 bg-red-50 text-red-800', 'x-circle'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-800', 'exclamation-triangle'],
    ];
@endphp

@foreach ($styles as $type => [$classes, $icon])
    @if (session($type))
        <div x-data="{ show: true }" x-show="show" x-transition.opacity.duration.300ms
            @if ($type === 'success') x-init="setTimeout(() => show = false, 5000)" @endif
            role="alert"
            class="mb-5 flex items-start gap-3 rounded-lg border p-4 {{ $classes }}">
            <x-icon :name="$icon" class="h-5 w-5 shrink-0" />
            <p class="flex-1 text-sm font-medium">{{ session($type) }}</p>
            <button type="button" x-on:click="show = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss">
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>
    @endif
@endforeach
