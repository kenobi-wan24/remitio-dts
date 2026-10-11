@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'required' => false, 'hint' => null])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
            {{ $label }} @if ($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->except('id')->merge([
            'class' => 'mt-1 block w-full rounded-lg shadow-sm sm:text-sm '.($hasError
                ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500'),
        ]) }}>{{ old($name, $value) }}</textarea>

    @if ($hint && ! $hasError)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
