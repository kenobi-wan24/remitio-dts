{{--
    Phase 8: files grouped by version.
    Each group = one file and all its versions (v1, v2, v3...). One version can be FINAL.
    Expects: $document (with attachments.uploader loaded)
--}}
@php
    $groups = $document->attachments
        ->groupBy(fn ($a) => $a->version_group_id ?? $a->id)
        ->map(fn ($files) => $files->sortByDesc('version')->values())
        ->sortByDesc(fn ($files) => $files->first()->created_at);

    $versionOptions = $groups->mapWithKeys(fn ($files) => [
        $files->first()->id => $files->first()->original_name.' (currently v'.$files->first()->version.')',
    ]);
@endphp

<x-card title="Files & Versions ({{ $groups->count() }})">
    @if ($groups->isNotEmpty())
        <ul class="-mx-5 -mt-5 mb-5 divide-y divide-slate-100 border-b border-slate-100">
            @foreach ($groups as $files)
                @php
                    $latest = $files->first();
                    $final = $files->firstWhere('is_final', true);
                    $older = $files->slice(1);
                @endphp
                <li class="px-5 py-3" x-data="{ showOld: false }">
                    @include('documents._attachment-row', ['attachment' => $latest])

                    @if ($final && $final->id !== $latest->id)
                        <p class="mt-2 flex items-center gap-1.5 pl-[3.25rem] text-xs text-amber-700">
                            <x-icon name="exclamation-triangle" class="h-4 w-4" />
                            The newest upload is not the final version. Final is v{{ $final->version }}.
                        </p>
                    @endif

                    @if ($older->isNotEmpty())
                        <button type="button" x-on:click="showOld = ! showOld"
                            class="mt-2 inline-flex items-center gap-1 pl-[3.25rem] text-xs font-medium text-slate-500 hover:text-slate-800">
                            <x-icon name="chevron-right" class="h-3 w-3 transition" x-bind:class="showOld && 'rotate-90'" />
                            <span x-text="showOld ? 'Hide' : 'Show'"></span>
                            {{ $older->count() }} previous {{ str('version')->plural($older->count()) }}
                        </button>
                        <div x-show="showOld" x-cloak class="ml-[3.25rem] mt-2 space-y-3 border-l-2 border-slate-200 pl-4">
                            @foreach ($older as $attachment)
                                @include('documents._attachment-row', ['attachment' => $attachment])
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Upload --}}
    <form method="POST" action="{{ route('documents.attachments.store', $document) }}" enctype="multipart/form-data"
        class="space-y-3" x-data="{ mode: @js(old('version_of') ? 'version' : 'new') }">
        @csrf

        @if ($groups->isNotEmpty())
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" value="new" x-model="mode" class="text-slate-800 focus:ring-slate-500"> New file(s)
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" value="version" x-model="mode" class="text-slate-800 focus:ring-slate-500"> New version of an existing file
                </label>
            </div>

            <div x-show="mode === 'version'" x-cloak class="grid gap-3 sm:grid-cols-2">
                <x-form.select name="version_of" label="Which file?" :options="$versionOptions" placeholder="Select file"
                    x-bind:disabled="mode !== 'version'" x-bind:required="mode === 'version'" />
                <x-form.input name="version_notes" label="What changed?" maxlength="255"
                    placeholder="e.g. Corrected client name on page 2" x-bind:disabled="mode !== 'version'" />
            </div>
        @endif

        <x-file-input :label="$groups->isEmpty() ? 'Upload scanned copies or soft files' : null" />
        <div class="flex justify-end">
            <x-button size="sm" icon="plus">Upload</x-button>
        </div>
    </form>
</x-card>
