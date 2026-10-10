{{--
    Workflow v2 — "next step" form on the document page.
    Expects: $document, $nextSteps, $availableActions (array<MovementAction>), $users, $lawyers,
             $defaultLawyerId, $approvable ([attachment_id => "name (vN)"])
--}}
@php
    use App\Enums\MovementAction;

    $first = $nextSteps[0] ?? ($availableActions[0] ?? null);
    $default = old('action', $first?->value);
    $hasFiles = $document->attachments->isNotEmpty();
    $finalFiles = $document->attachments->where('is_final', true);
    $waitingOnLawyer = $document->status->isWithLawyer() && ! auth()->user()->isLawyer();
@endphp

@if (empty($availableActions))
    <p class="text-sm text-slate-500">This document is archived. No further steps.</p>
@else
<form method="POST" action="{{ route('documents.movements.store', $document) }}" enctype="multipart/form-data"
    x-data="{
        action: @js($default),
        is(...names) { return names.includes(this.action) },
    }"
    class="space-y-5">
    @csrf

    @if ($waitingOnLawyer)
        <div class="flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-icon name="clock" class="h-5 w-5 shrink-0" />
            <p>Waiting for the lawyer. {{ $document->status === \App\Enums\DocumentStatus::ForReview ? 'Only the lawyer can approve it or ask for revision.' : 'The lawyer (or you, once it is signed) marks it as signed.' }}</p>
        </div>
    @endif

    {{-- R2: physical copy is outside the office --}}
    @if ($document->isOutOfOffice())
        <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-icon name="truck" class="h-5 w-5 shrink-0" />
            <p>The physical copy is <strong>out of the office</strong> ({{ $document->out_destination }}). Log its return before the next workflow step.</p>
        </div>
    @endif

    {{-- 1. Next step --}}
    <fieldset>
        <legend class="text-sm font-medium text-slate-700">{{ count($nextSteps) ? 'Next step' : 'Available' }}</legend>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach ($availableActions as $option)
                @php $secondary = in_array($option, [MovementAction::Forwarded, MovementAction::SentOut], true); @endphp
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                    :class="action === '{{ $option->value }}' ? 'border-slate-800 bg-slate-50 ring-1 ring-slate-800' : 'border-slate-200 hover:border-slate-300'">
                    <input type="radio" name="action" value="{{ $option->value }}" x-model="action" class="sr-only">
                    <x-icon :name="$option->icon()" :class="$secondary ? 'mt-0.5 h-5 w-5 shrink-0 text-slate-400' : 'mt-0.5 h-5 w-5 shrink-0 text-slate-600'" />
                    <span>
                        <span @class(['block text-sm', 'font-semibold text-slate-900' => ! $secondary, 'font-medium text-slate-600' => $secondary])>
                            {{ $option->buttonLabel() }}
                            @if ($option->isLawyerOnly()) <span class="ml-1 text-xs font-normal text-slate-400">(lawyer)</span> @endif
                        </span>
                        <span class="block text-xs text-slate-500">{{ $option->description() }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('action') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </fieldset>

    {{-- 2. What this step needs --}}
    <div class="grid gap-4 sm:grid-cols-2">
        {{-- Send to lawyer --}}
        <div x-show="is('submitted_for_review', 'resubmitted', 'sent_for_signature')">
            <x-form.select name="lawyer_id" label="Send to (lawyer)" :options="$lawyers" :selected="$defaultLawyerId"
                x-bind:disabled="! is('submitted_for_review', 'resubmitted', 'sent_for_signature')" />
        </div>

        {{-- Draft / corrected file --}}
        <div x-show="is('submitted_for_review', 'resubmitted')">
            <label for="file" class="block text-sm font-medium text-slate-700">
                <span x-text="is('resubmitted') ? 'Corrected file' : 'Draft file'"></span>
                @unless ($hasFiles) <span class="text-red-500">*</span> @else <span x-show="is('resubmitted')" class="text-red-500">*</span> @endunless
            </label>
            <input type="file" id="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                x-bind:disabled="! is('submitted_for_review', 'resubmitted')"
                class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
            <p class="mt-1 text-xs text-slate-500" x-text="is('resubmitted') ? 'Saved as the next version (v2, v3, …).' : '{{ $hasFiles ? 'Optional — a file is already attached.' : 'Saved as version 1.' }}'"></p>
            @error('file') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div x-show="is('resubmitted')" class="sm:col-span-2">
            <x-form.input name="version_notes" label="What changed?" maxlength="255" placeholder="e.g. Corrected the client's middle name in paragraph 3"
                x-bind:disabled="! is('resubmitted')" />
        </div>

        {{-- Approve: which version --}}
        <div x-show="is('approved')" class="sm:col-span-2">
            @if ($approvable->isNotEmpty())
                <x-form.select name="attachment_id" label="Approved version" :options="$approvable" :selected="$approvable->keys()->first()"
                    x-bind:disabled="! is('approved')" hint="This version is marked FINAL — it is the one that gets printed and released." />
            @else
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">No file has been uploaded for this document. Ask the secretary to upload the draft first.</p>
            @endif
        </div>

        {{-- Signed by --}}
        <div x-show="is('signed')">
            <x-form.select name="signed_by" label="Signed by" :options="$lawyers"
                :selected="$document->currentHolder?->isLawyer() ? $document->current_holder_id : $defaultLawyerId"
                x-bind:disabled="! is('signed')" />
        </div>

        {{-- Release: received by --}}
        <div x-show="is('released_to_client')">
            <x-form.input name="received_by" label="Received by" maxlength="255" placeholder="e.g. Juan Dela Cruz (client)"
                :value="$document->client?->display_name" x-bind:disabled="! is('released_to_client')" x-bind:required="is('released_to_client')" />
        </div>

        {{-- Hand over --}}
        <div x-show="is('forwarded')">
            <x-form.select name="to_user_id" label="Hand over to" :options="$users->except($document->current_holder_id)" placeholder="Select person"
                x-bind:disabled="! is('forwarded')" />
        </div>

        {{-- R2: send out of office --}}
        <div x-show="is('sent_out')">
            <x-form.input name="destination" label="Brought to" maxlength="255" placeholder="e.g. RTC Branch 12, Davao City"
                x-bind:disabled="! is('sent_out')" x-bind:required="is('sent_out')" />
        </div>
        <div x-show="is('sent_out')">
            <x-form.select name="purpose" label="Purpose" placeholder="Select purpose"
                :options="collect(MovementAction::OUT_PURPOSES)->mapWithKeys(fn ($p) => [$p => $p])"
                x-bind:disabled="! is('sent_out')" x-bind:required="is('sent_out')" />
        </div>
        <div x-show="is('sent_out', 'returned_to_office')">
            <label for="carried_by" class="block text-sm font-medium text-slate-700">
                <span x-text="is('sent_out') ? 'Carried by' : 'Brought back by'"></span>
                <span x-show="is('sent_out')" class="text-red-500">*</span>
            </label>
            <input type="text" id="carried_by" name="carried_by" maxlength="255" value="{{ old('carried_by') }}"
                placeholder="e.g. Process server / messenger name"
                x-bind:disabled="! is('sent_out', 'returned_to_office')" x-bind:required="is('sent_out')"
                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
            @error('carried_by') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div x-show="is('sent_out')">
            <x-form.input name="expected_back" type="date" label="Expected back (optional)" min="{{ today()->toDateString() }}"
                x-bind:disabled="! is('sent_out')" />
        </div>

        {{-- 11C: correct a wrong step (lawyer only) --}}
        <div x-show="is('status_corrected')" class="sm:col-span-2">
            <div class="mb-3 flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800">
                <x-icon name="exclamation-triangle" class="h-4 w-4 shrink-0" />
                <p>Use this only to undo a wrong step. The correction and your reason are shown in the history next to the original step.</p>
            </div>
        </div>
        <div x-show="is('status_corrected')">
            <x-form.select name="corrected_status" label="Correct status" placeholder="Select the right status"
                :options="collect(\App\Enums\DocumentStatus::options())->except($document->status->value)"
                x-bind:disabled="! is('status_corrected')" x-bind:required="is('status_corrected')" />
        </div>
        <div x-show="is('status_corrected')">
            <x-form.select name="to_user_id" id="correct_to_user_id" label="Now with (optional)" :options="$users"
                placeholder="Keep the current holder" x-bind:disabled="! is('status_corrected')" />
        </div>

        {{-- Location --}}
        <div x-show="is('archived', 'forwarded', 'returned_to_office')">
            <label for="location" class="block text-sm font-medium text-slate-700">
                <span x-text="is('returned_to_office') ? 'Kept at (in the office)' : 'Location'"></span>
                <span x-show="is('archived')" class="text-red-500">*</span>
            </label>
            <input type="text" id="location" name="location" maxlength="255" value="{{ old('location') }}"
                placeholder="{{ $document->physical_location ?: 'e.g. Archive Room - Box 4' }}"
                x-bind:disabled="! is('archived', 'forwarded', 'returned_to_office')" x-bind:required="is('archived')"
                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
            <p x-show="is('returned_to_office')" class="mt-1 text-xs text-slate-500">Leave blank if it goes back to {{ $document->physical_location ?: 'its usual place' }}.</p>
            @error('location') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-form.input name="acted_at" type="datetime-local" label="Date & Time" max="{{ now()->format('Y-m-d\TH:i') }}" />
            <p class="mt-1 text-xs text-slate-500">Leave blank to use the current time.</p>
        </div>

        {{-- Remarks --}}
        <div class="sm:col-span-2">
            <label for="remarks" class="block text-sm font-medium text-slate-700">
                <span x-text="is('status_corrected') ? 'Reason for the correction' : 'Remarks'"></span>
                <span x-show="is('revision_required', 'received', 'status_corrected')" class="text-red-500">*</span>
                <span x-show="is('sent_out')" class="text-xs font-normal text-slate-400">(required if purpose is Other)</span>
            </label>
            <textarea id="remarks" name="remarks" rows="2" maxlength="1000" x-bind:required="is('revision_required', 'received', 'status_corrected')"
                :placeholder="{
                    drafting_started: 'The lawyer\'s instructions, e.g. Prepare an SPA authorizing Juan Dela Cruz to process the property documents',
                    submitted_for_review: 'e.g. Draft ready for your review',
                    approved: 'Optional',
                    revision_required: 'Required — what to fix, e.g. Correct the client\'s middle name and revise paragraph 3',
                    resubmitted: 'e.g. Revised per your comments',
                    sent_for_signature: 'e.g. Printed, 2 copies',
                    signed: 'Optional',
                    finalized: 'e.g. Sealed; client copy prepared',
                    released_to_client: 'e.g. Original and 1 copy released',
                    archived: 'e.g. Matter completed',
                    received: 'Required — why it came back',
                    forwarded: 'e.g. Passed to the clerk for photocopying',
                    status_corrected: 'Required — e.g. Marked as signed by mistake; the lawyer has not signed it yet',
                    sent_out: 'e.g. Original and 2 copies for filing; bring back the receiving copy',
                    returned_to_office: 'e.g. Returned with the court\'s received-stamped copy',
                }[action] ?? ''"
                @class([
                    'mt-1 block w-full rounded-lg shadow-sm sm:text-sm',
                    'border-red-400 focus:border-red-500 focus:ring-red-500' => $errors->has('remarks'),
                    'border-slate-300 focus:border-slate-500 focus:ring-slate-500' => ! $errors->has('remarks'),
                ])>{{ old('remarks') }}</textarea>
            @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Before release: which version was approved --}}
    <div x-show="is('released_to_client')" x-cloak>
        @if ($finalFiles->isNotEmpty())
            <div class="flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <x-icon name="check-circle" class="h-5 w-5 shrink-0" />
                <p>Approved (FINAL) version:
                    @foreach ($finalFiles as $file)
                        <strong>{{ $file->original_name }} (v{{ $file->version }})</strong>@if (! $loop->last), @endif
                    @endforeach
                    — make sure this is the copy being released.</p>
            </div>
        @else
            <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0" />
                <p>No file is marked <strong>FINAL</strong> for this document. Double-check that the approved version is the one being released.</p>
            </div>
        @endif
    </div>

    <div class="flex justify-end">
        <x-button icon="check-circle"><span x-text="@js(collect($availableActions)->mapWithKeys(fn ($a) => [$a->value => $a->buttonLabel()]))[action] ?? 'Save'"></span></x-button>
    </div>
</form>
@endif
