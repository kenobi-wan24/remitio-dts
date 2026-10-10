{{--
    R3: "Confidential" checkbox — shown only to lawyers (staff never see or change it).
    <x-confidential-toggle :checked="$case->is_confidential" what="case" />
--}}
@props(['checked' => false, 'what' => 'record', 'inherited' => false])

@if (auth()->user()?->isLawyer())
    <label {{ $attributes->merge(['class' => 'mt-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/60 p-4']) }}>
        <input type="hidden" name="is_confidential" value="0">
        <input type="checkbox" name="is_confidential" value="1" @checked(old('is_confidential', $checked))
            class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
        <span>
            <span class="flex items-center gap-1.5 text-sm font-medium text-slate-800">
                <x-icon name="lock-closed" class="h-4 w-4 text-amber-600" /> Confidential {{ $what }}
            </span>
            <span class="block text-xs text-slate-500">
                Only the Administrator, the handling lawyer, and the people assigned to this {{ $what }}
                (whoever recorded or handled it) can find or open it. Every opening is logged.
            </span>
            @if ($inherited)
                <span class="mt-1 block text-xs font-medium text-amber-800">Its case is confidential, so this {{ $what }} is already restricted.</span>
            @endif
        </span>
    </label>
@endif
