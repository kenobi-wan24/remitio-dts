{{--
    R4 — Priority card on the document page.
    Urgency (lawyers change it, with a reason) · optional deadline · On Hold (anyone handling it).
    Every change is written to the tracking history.
--}}
@php
    use App\Enums\Urgency;
    $priorityErrors = $errors->getBag('priority');
    $canChangeUrgency = auth()->user()->isLawyer() && ! $document->status->isFinal();
@endphp

<x-card title="Priority">
    <div class="space-y-4 text-sm">
        {{-- Urgency --}}
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Urgency</p>
            <div class="mt-1 flex items-center gap-2">
                <x-status-badge :status="$document->urgency" class="text-sm" />
                <span class="text-xs text-slate-500">{{ $document->priorityGroup()->label() }}</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ $document->urgency->criteria() }}</p>
        </div>

        @if (config('dts.deadlines'))
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Deadline</p>
                <p @class(['mt-0.5', 'font-semibold text-red-600' => $document->isOverdue(), 'text-slate-900' => ! $document->isOverdue()])>
                    {{ $document->deadline?->format('M d, Y') ?? 'None' }}
                    @if ($document->isOverdue()) · overdue @elseif ($document->deadline) <span class="text-xs text-slate-500">({{ $document->deadline->diffForHumans() }})</span> @endif
                </p>
            </div>
        @endif

        {{-- Change urgency (lawyers) --}}
        @if ($canChangeUrgency)
            <div x-data="{ open: @js($priorityErrors->hasAny(['urgency', 'reason']) && old('urgency')), u: @js(old('urgency', $document->urgency->value)),
                c: @js(collect(Urgency::cases())->mapWithKeys(fn ($u) => [$u->value => $u->criteria()])) }">
                <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900">
                    <x-icon name="pencil-square" class="h-3.5 w-3.5" /> Change urgency
                </button>
                <form x-show="open" x-cloak method="POST" action="{{ route('documents.urgency', $document) }}" class="mt-2 space-y-2 rounded-lg border border-slate-200 p-3">
                    @csrf
                    <select name="urgency" x-model="u" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach (Urgency::cases() as $level)
                            <option value="{{ $level->value }}">{{ $level->label() }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500" x-text="c[u]"></p>
                    <input type="text" name="reason" maxlength="500" required placeholder="Reason — e.g. hearing moved to tomorrow" value="{{ old('urgency') ? old('reason') : '' }}"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach (['urgency', 'reason'] as $field)
                        @if (old('urgency') && $priorityErrors->has($field)) <p class="text-xs text-red-600">{{ $priorityErrors->first($field) }}</p> @endif
                    @endforeach
                    <x-button size="sm" class="w-full justify-center">Save urgency</x-button>
                </form>
            </div>
        @endif

        {{-- On Hold --}}
        @if ($document->isOnHold())
            <div class="rounded-lg border border-purple-200 bg-purple-50 p-3 text-purple-900">
                <p class="flex items-center gap-1.5 font-semibold"><x-icon name="clock" class="h-4 w-4" /> On hold</p>
                <p class="mt-1 text-xs">{{ $document->on_hold_reason }}</p>
                <p class="mt-1 text-xs opacity-75">Since {{ $document->on_hold_since->format('M d, Y') }} ({{ $document->on_hold_since->diffForHumans() }})</p>
                <form method="POST" action="{{ route('documents.hold', $document) }}" class="mt-2">
                    @csrf
                    <x-button size="sm" variant="secondary" icon="arrow-path">Resume</x-button>
                </form>
            </div>
        @elseif (! $document->status->isFinal())
            <div x-data="{ open: @js($priorityErrors->has('reason') && ! old('urgency')) }">
                <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900">
                    <x-icon name="clock" class="h-3.5 w-3.5" /> Put on hold
                </button>
                <form x-show="open" x-cloak method="POST" action="{{ route('documents.hold', $document) }}" class="mt-2 space-y-2 rounded-lg border border-slate-200 p-3">
                    @csrf
                    <input type="text" name="reason" maxlength="500" required placeholder="Waiting for… e.g. the client's TCT copy"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @if (! old('urgency') && $priorityErrors->has('reason')) <p class="text-xs text-red-600">{{ $priorityErrors->first('reason') }}</p> @endif
                    <p class="text-xs text-slate-500">For documents waiting on the client or another office. It does not lower the urgency.</p>
                    <x-button size="sm" class="w-full justify-center">Put on hold</x-button>
                </form>
            </div>
        @endif
    </div>
</x-card>
