{{--
    Vertical timeline of a document's movements (newest first).
    <x-movement-timeline :movements="$document->movements" />
--}}
@props(['movements'])

@php
    $circle = [
        'gray' => 'bg-slate-100 text-slate-600',
        'blue' => 'bg-blue-100 text-blue-600',
        'indigo' => 'bg-indigo-100 text-indigo-600',
        'amber' => 'bg-amber-100 text-amber-700',
        'green' => 'bg-green-100 text-green-600',
        'purple' => 'bg-purple-100 text-purple-600',
        'red' => 'bg-red-100 text-red-600',
    ];
@endphp

@if ($movements->isEmpty())
    <p class="py-6 text-center text-sm text-slate-400">No movements recorded yet.</p>
@else
    <ol {{ $attributes->merge(['class' => 'relative']) }}>
        @foreach ($movements as $movement)
            @php
                $statusChanged = $movement->to_status && $movement->from_status !== $movement->to_status;
                $holderChanged = $movement->from_user_id !== $movement->to_user_id;
            @endphp

            <li class="relative flex gap-4 pb-7 last:pb-0">
                {{-- connector line --}}
                @unless ($loop->last)
                    <span class="absolute bottom-0 left-4 top-9 -ml-px w-0.5 bg-slate-200" aria-hidden="true"></span>
                @endunless

                {{-- icon --}}
                <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white {{ $circle[$movement->action->color()] ?? $circle['gray'] }}">
                    <x-icon :name="$movement->action->icon()" class="h-4 w-4" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            {{ $movement->action->label() }}
                            @if ($loop->first)
                                <x-status-badge color="green" label="Latest" />
                            @endif
                        </p>
                        <time class="text-xs text-slate-500" datetime="{{ $movement->acted_at->toIso8601String() }}">
                            {{ $movement->acted_at->format('M d, Y · h:i A') }}
                            <span class="text-slate-400">({{ $movement->acted_at->diffForHumans() }})</span>
                        </time>
                    </div>

                    {{-- status change --}}
                    @if ($statusChanged)
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            @if ($movement->from_status)
                                <x-status-badge :status="$movement->from_status" />
                                <x-icon name="arrow-right" class="h-3 w-3 text-slate-400" />
                            @endif
                            <x-status-badge :status="$movement->to_status" />
                        </div>
                    @endif

                    {{-- holder change --}}
                    @if ($holderChanged)
                        <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-sm text-slate-700">
                            <x-icon name="user" class="h-4 w-4 text-slate-400" />
                            @if ($movement->from_user_id)
                                <span>{{ $movement->fromUser?->name ?? 'Former user' }}</span>
                                <x-icon name="arrow-right" class="h-3 w-3 text-slate-400" />
                            @endif
                            <span class="font-medium">{{ $movement->toUser?->name ?? 'Out of office' }}</span>
                        </p>
                    @elseif ($movement->toUser)
                        <p class="mt-1.5 flex items-center gap-1.5 text-sm text-slate-700">
                            <x-icon name="user" class="h-4 w-4 text-slate-400" />
                            With <span class="font-medium">{{ $movement->toUser->name }}</span>
                        </p>
                    @endif

                    {{-- location --}}
                    @if ($movement->location)
                        <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-600">
                            <x-icon name="map-pin" class="h-4 w-4 text-slate-400" />
                            {{ $movement->location }}
                        </p>
                    @endif

                    {{-- remarks --}}
                    @if ($movement->remarks)
                        <p class="mt-2 rounded-lg border-l-2 border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $movement->remarks }}</p>
                    @endif

                    <p class="mt-1.5 text-xs text-slate-400">Logged by {{ $movement->actor?->name ?? 'Unknown' }}</p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
