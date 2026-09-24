{{--
    <x-form.select name="status" label="Status" :options="\App\Enums\CaseStatus::options()" :selected="$case->status" required />
    <x-form.select name="client_id" label="Client" :options="$clients->pluck('display_name', 'id')" placeholder="Select a client" />
    :selected accepts a plain value or an enum.
--}}
@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => null, 'required' => false, 'hint' => null])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $selected = old($name, $selected instanceof \BackedEnum ? $selected->value : $selected);
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
            {{ $label }} @if ($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <select name="{{ $name }}" id="{{ $id }}" @required($required)
        {{ $attributes->except('id')->merge([
            'class' => 'mt-1 block w-full rounded-lg shadow-sm sm:text-sm '.($hasError
                ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500'),
        ]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $value === (string) $selected)>{{ $text }}</option>
        @endforeach
    </select>

    @if ($hint && ! $hasError)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
