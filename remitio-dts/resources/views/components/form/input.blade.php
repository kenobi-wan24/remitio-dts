{{--
    <x-form.input name="first_name" label="First Name" :value="$client->first_name" required />
    <x-form.input name="date_received" type="date" label="Date Received" :value="$document->date_received" />
    Old input and validation errors are handled automatically.
--}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);

    if ($value instanceof \DateTimeInterface) {
        $value = $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d');
    }
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
            {{ $label }} @if ($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @required($required)
        {{ $attributes->except('id')->merge([
            'class' => 'mt-1 block w-full rounded-lg shadow-sm sm:text-sm '.($hasError
                ? 'border-red-400 text-red-900 focus:border-red-500 focus:ring-red-500'
                : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500'),
        ]) }}>

    @if ($hint && ! $hasError)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
