{{--
    Multi-file picker that lists the chosen files.
    <x-file-input name="attachments[]" label="Attachments" />
--}}
@props(['name' => 'attachments[]', 'label' => null, 'hint' => 'PDF, Word, Excel, JPG or PNG · up to 10 MB each · max 10 files'])

@php $errorKeys = ['attachments', 'attachments.*']; @endphp

<div x-data="{ files: [] }">
    @if ($label)
        <label class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    @endif

    <label class="mt-1 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition hover:border-slate-400 hover:bg-slate-100">
        <x-icon name="folder-open" class="h-8 w-8 text-slate-400" />
        <span class="text-sm text-slate-600"><span class="font-semibold text-slate-800">Click to choose files</span></span>
        <span class="text-xs text-slate-500">{{ $hint }}</span>
        <input type="file" name="{{ $name }}" multiple class="sr-only"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
            x-on:change="files = Array.from($event.target.files).map(f => ({ name: f.name, size: (f.size / 1024 / 1024).toFixed(2) + ' MB' }))">
    </label>

    <ul x-show="files.length" x-cloak class="mt-2 space-y-1 text-sm">
        <template x-for="file in files" :key="file.name">
            <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-1.5">
                <span class="truncate text-slate-700" x-text="file.name"></span>
                <span class="ml-3 shrink-0 text-xs text-slate-500" x-text="file.size"></span>
            </li>
        </template>
    </ul>

    @foreach ($errors->getMessages() as $key => $messages)
        @if (str_starts_with($key, 'attachments'))
            @foreach (array_unique($messages) as $message)
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @endforeach
        @endif
    @endforeach
</div>
