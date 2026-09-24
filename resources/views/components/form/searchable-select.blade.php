{{--
    A dropdown you can type into — for long lists like clients.
        <x-form.searchable-select name="client_id" label="Client" :options="$clients" :selected="$case->client_id" required />
    :options is [value => label]. Fires a `select-changed` browser event with {name, value}
    so other fields can react (used by the Documents form in Phase 5).
--}}
@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => 'Select...', 'required' => false, 'hint' => null])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $selected = old($name, $selected instanceof \BackedEnum ? $selected->value : $selected);
    $items = collect($options)->map(fn ($text, $value) => ['value' => (string) $value, 'label' => (string) $text])->values();
@endphp

<div {{ $attributes->except('id')->merge(['class' => 'relative']) }}
    x-data="{
        open: false,
        search: '',
        items: @js($items),
        value: @js($selected === null ? '' : (string) $selected),
        get selectedLabel() {
            const item = this.items.find(i => i.value === this.value);
            return item ? item.label : '';
        },
        get filtered() {
            const term = this.search.toLowerCase().trim();
            return term === '' ? this.items : this.items.filter(i => i.label.toLowerCase().includes(term));
        },
        toggle() {
            this.open = ! this.open;
            if (this.open) this.$nextTick(() => this.$refs.search.focus());
        },
        choose(item) {
            this.value = item.value;
            this.open = false;
            this.search = '';
            this.$dispatch('select-changed', { name: @js($name), value: item.value });
        },
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.prevent.stop="open = false">

    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
            {{ $label }} @if ($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <input type="hidden" name="{{ $name }}" :value="value">

    <button type="button" id="{{ $id }}" x-on:click="toggle()"
        @class([
            'mt-1 flex w-full items-center justify-between gap-2 rounded-lg border bg-white px-3 py-2 text-left shadow-sm sm:text-sm focus:outline-none focus:ring-1',
            'border-red-400 focus:border-red-500 focus:ring-red-500' => $hasError,
            'border-slate-300 focus:border-slate-500 focus:ring-slate-500' => ! $hasError,
        ])>
        <span x-text="selectedLabel || @js($placeholder)" :class="selectedLabel ? 'text-slate-900' : 'text-slate-400'" class="truncate"></span>
        <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
        class="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
        <div class="border-b border-slate-100 p-2">
            <input type="text" x-ref="search" x-model="search" placeholder="Type to search..."
                x-on:keydown.enter.prevent="filtered.length && choose(filtered[0])"
                class="block w-full rounded-md border-slate-300 py-1.5 text-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <ul class="max-h-60 overflow-y-auto py-1 text-sm">
            <template x-for="item in filtered" :key="item.value">
                <li>
                    <button type="button" x-on:click="choose(item)"
                        class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-slate-50"
                        :class="item.value === value ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-700'">
                        <span x-text="item.label" class="truncate"></span>
                        <x-icon name="check-circle" class="h-4 w-4 shrink-0 text-slate-700" x-show="item.value === value" />
                    </button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-slate-400">No matches found.</li>
        </ul>
    </div>

    @if ($hint && ! $hasError)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
