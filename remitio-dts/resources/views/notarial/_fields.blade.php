{{--
    11C: Notarial Register entry fields — the columns of the register book.
    Expects: $lawyers; optional: $entry (editing), $prefill (principals pre-filled from parties), $defaultNotaryId
--}}
@php
    use App\Enums\NotarialActType;

    $entry ??= null;
    $principals = old('principals', $entry?->principals ?? ($prefill ?? []));
    if (empty($principals)) {
        $principals = [['name' => '', 'address' => '', 'evidence' => '']];
    }
    $witnesses = old('witnesses', $entry?->witnesses ?? []);
    $actType = old('act_type', $entry?->act_type?->value);
@endphp

<div class="space-y-4"
    x-data="{
        principals: @js(array_values($principals)),
        witnesses: @js(array_values($witnesses)),
    }">
    <div class="grid gap-3 sm:grid-cols-2">
        <x-form.select name="notary_id" label="Notary public" :options="$lawyers"
            :selected="$entry?->notary_id ?? ($defaultNotaryId ?? null)" required />
        <x-form.input name="notarized_on" type="date" label="Date notarized" required
            :value="$entry?->notarized_on ?? today()" max="{{ today()->toDateString() }}" />
    </div>

    <fieldset>
        <legend class="text-sm font-medium text-slate-700">Type of notarial act <span class="text-red-500">*</span></legend>
        <div class="mt-1 grid grid-cols-2 gap-2">
            @foreach (NotarialActType::cases() as $type)
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input type="radio" name="act_type" value="{{ $type->value }}" @checked($actType === $type->value)
                        class="text-slate-800 focus:ring-slate-500" required>
                    {{ $type->label() }}
                </label>
            @endforeach
        </div>
        @error('act_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </fieldset>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-form.input name="doc_no" type="number" label="Doc. No." :value="$entry?->doc_no" min="1" required />
        <x-form.input name="page_no" type="number" label="Page No." :value="$entry?->page_no" min="1" required />
        <x-form.input name="book_no" label="Book No." :value="$entry?->book_no" maxlength="10" placeholder="e.g. III" required />
        <x-form.input name="series" type="number" label="Series (year)" :value="$entry?->series ?? now()->year" min="1990" max="{{ now()->year + 1 }}" required />
    </div>

    {{-- Principals --}}
    <div>
        <p class="text-sm font-medium text-slate-700">Principal parties <span class="text-red-500">*</span></p>
        <p class="text-xs text-slate-500">Pre-filled from the document's parties and their valid IDs. The register keeps this copy even if a client's details change later.</p>
        <div class="mt-2 space-y-3">
            <template x-for="(p, i) in principals" :key="i">
                <div class="space-y-2 rounded-lg border border-slate-200 p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase text-slate-500" x-text="`Principal ${i + 1}`"></span>
                        <button type="button" x-show="principals.length > 1" x-on:click="principals.splice(i, 1)" class="text-xs text-slate-400 hover:text-red-600">Remove</button>
                    </div>
                    <input type="text" :name="`principals[${i}][name]`" x-model="p.name" placeholder="Full name" required maxlength="255"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    <input type="text" :name="`principals[${i}][address]`" x-model="p.address" placeholder="Address" maxlength="255"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    <input type="text" :name="`principals[${i}][evidence]`" x-model="p.evidence" placeholder="Evidence of identity, e.g. Driver's License No. D12-88-013540" maxlength="255"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            </template>
        </div>
        <button type="button" x-on:click="principals.push({ name: '', address: '', evidence: '' })"
            class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-slate-700 hover:text-slate-900">
            <x-icon name="plus" class="h-4 w-4" /> Add principal
        </button>
    </div>

    {{-- Witnesses --}}
    <div>
        <p class="text-sm font-medium text-slate-700">Witnesses (optional)</p>
        <div class="mt-2 space-y-2">
            <template x-for="(w, i) in witnesses" :key="i">
                <div class="grid gap-2 sm:grid-cols-12">
                    <input type="text" :name="`witnesses[${i}][name]`" x-model="w.name" placeholder="Witness name" maxlength="255"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:col-span-5">
                    <input type="text" :name="`witnesses[${i}][address]`" x-model="w.address" placeholder="Address" maxlength="255"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:col-span-6">
                    <button type="button" x-on:click="witnesses.splice(i, 1)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-red-600 sm:col-span-1" aria-label="Remove witness">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>
            </template>
        </div>
        <button type="button" x-show="witnesses.length < 4" x-on:click="witnesses.push({ name: '', address: '' })"
            class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-slate-700 hover:text-slate-900">
            <x-icon name="plus" class="h-4 w-4" /> Add witness
        </button>
    </div>

    @foreach ($errors->getMessages() as $key => $messages)
        @if (str_starts_with($key, 'principals') || str_starts_with($key, 'witnesses'))
            @foreach (array_unique($messages) as $message)
                <p class="text-xs text-red-600">{{ $message }}</p>
            @endforeach
        @endif
    @endforeach
</div>
