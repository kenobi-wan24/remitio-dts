{{--
    Files grouped by version (v1, v2, v3...). One version can be FINAL (set when the lawyer approves).
    Workflow v2: drafts and corrected versions are uploaded ONLY through Submit / Resubmit for review
    in Update Tracking. This card's upload adds OTHER files (scans, supporting papers).
    Expects: $document (with attachments.uploader loaded)
--}}
@php
    $groups = $document->attachments
        ->groupBy(fn ($a) => $a->version_group_id ?? $a->id)
        ->map(fn ($files) => $files->sortByDesc('version')->values())
        ->sortByDesc(fn ($files) => $files->first()->created_at);

    $draftingNow = in_array($document->status, [
        \App\Enums\DocumentStatus::ForDrafting, \App\Enums\DocumentStatus::RevisionRequired,
    ], true);
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

    {{-- Upload: other files only --}}
    <form method="POST" action="{{ route('documents.attachments.store', $document) }}" enctype="multipart/form-data" class="space-y-3">
        @csrf

        @if ($draftingNow)
            <div class="flex items-start gap-2 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
                <x-icon name="information-circle" class="h-4 w-4 shrink-0" />
                <p>Uploading the draft or a corrected version? Use <strong>{{ $document->status === \App\Enums\DocumentStatus::RevisionRequired ? 'Resubmit for review' : 'Submit for review' }}</strong> in Update Tracking so it becomes the next version and goes to the lawyer.</p>
            </div>
        @endif

        <x-file-input label="Add other files (scans, supporting papers)"
            hint="Not for drafts — draft versions are added through Update Tracking · PDF, Word, Excel, JPG or PNG · up to 10 MB each" />
        <div class="flex justify-end">
            <x-button size="sm" icon="plus">Upload</x-button>
        </div>
    </form>
</x-card>
