{{--
    "Update Tracking" form on the document page.
    Expects: $document, $availableActions (array<MovementAction>), $users, $returnTo
--}}
@php
    use App\Enums\DocumentStatus;
    use App\Enums\MovementAction;

    $default = old('action', $availableActions[0]->value);
    $statusOptions = collect(DocumentStatus::cases())
        ->reject(fn ($s) => $s->isFinal())
        ->mapWithKeys(fn ($s) => [$s->value => $s->label()]);
    $isOut = $document->status->isFinal();
@endphp

<form method="POST" action="{{ route('documents.movements.store', $document) }}"
    x-data="{
        action: @js($default),
        toUser: @js((string) old('to_user_id', '')),
        returnTo: @js($returnTo ? (string) $returnTo : ''),
        get needsUser() { return ['forwarded', 'returned', 'received', 'filed_in_court'].includes(this.action) },
        get userRequired() { return ['forwarded', 'returned', 'received'].includes(this.action) },
        get needsStatus() { return ['forwarded', 'status_changed'].includes(this.action) },
        get needsLocation() { return this.action !== 'released_to_client' },
        get remarksRequired() { return ['returned', 'released_to_client'].includes(this.action) },
    }"
    x-init="$watch('action', value => { if (value === 'returned' && ! toUser) toUser = returnTo })"
    class="space-y-5">
    @csrf

    {{-- 1. Choose action --}}
    <fieldset>
        <legend class="text-sm font-medium text-slate-700">What happened to the document?</legend>
        <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($availableActions as $option)
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                    :class="action === '{{ $option->value }}' ? 'border-slate-800 bg-slate-50 ring-1 ring-slate-800' : 'border-slate-200 hover:border-slate-300'">
                    <input type="radio" name="action" value="{{ $option->value }}" x-model="action" class="sr-only">
                    <x-icon :name="$option->icon()" class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" />
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">
                            {{ $option === MovementAction::Received ? 'Receive Back' : $option->label() }}
                        </span>
                        <span class="block text-xs text-slate-500">{{ $option->description() }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('action') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </fieldset>

    {{-- 2. Details (fields appear depending on the action) --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div x-show="needsUser">
            <x-form.select name="to_user_id" label="Now With" :options="$users" placeholder="Select person"
                x-model="toUser" x-bind:required="userRequired" />
            <p x-show="action === 'filed_in_court'" class="mt-1 text-xs text-slate-500">Optional — who keeps the received copy. Leave blank to keep with the current holder.</p>
        </div>

        <div x-show="needsStatus">
            <label for="to_status" class="block text-sm font-medium text-slate-700">New Status</label>
            <select id="to_status" name="to_status" x-bind:required="action === 'status_changed'"
                @class([
                    'mt-1 block w-full rounded-lg shadow-sm sm:text-sm',
                    'border-red-400 focus:border-red-500 focus:ring-red-500' => $errors->has('to_status'),
                    'border-slate-300 focus:border-slate-500 focus:ring-slate-500' => ! $errors->has('to_status'),
                ])>
                <option value="" x-text="action === 'forwarded' ? 'Keep as {{ $document->status->label() }}' : 'Select new status'"></option>
                @foreach ($statusOptions as $value => $label)
                    @continue(! $isOut && $value === $document->status->value)
                    <option value="{{ $value }}" @selected(old('to_status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('to_status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div x-show="needsLocation">
            <x-form.input name="location" label="Location" maxlength="255"
                :placeholder="$document->physical_location ?: 'e.g. Cabinet B - Drawer 1'"
                x-bind:required="action === 'archived'" />
            <p class="mt-1 text-xs text-slate-500"
                x-text="action === 'archived' ? 'Required — e.g. Archive Room - Box 4' : 'Leave blank if it stays where it is.'"></p>
        </div>

        <div>
            <x-form.input name="acted_at" type="datetime-local" label="Date & Time"
                max="{{ now()->format('Y-m-d\TH:i') }}" />
            <p class="mt-1 text-xs text-slate-500">Leave blank to use the current time.</p>
        </div>

        <div class="sm:col-span-2">
            <label for="remarks" class="block text-sm font-medium text-slate-700">
                Remarks <span x-show="remarksRequired" class="text-red-500">*</span>
            </label>
            <textarea id="remarks" name="remarks" rows="2" maxlength="1000" x-bind:required="remarksRequired"
                :placeholder="{
                    forwarded: 'e.g. For review and signature',
                    returned: 'e.g. Returned for corrections on page 2',
                    status_changed: 'e.g. Reviewed, ready for signing',
                    filed_in_court: 'e.g. Filed at RTC Br. 12, received-stamped copy kept',
                    released_to_client: 'Required — e.g. Original received by the client personally',
                    archived: 'e.g. Case closed, filed with complete records',
                    received: 'e.g. Client returned the signed original',
                }[action] ?? ''"
                @class([
                    'mt-1 block w-full rounded-lg shadow-sm sm:text-sm',
                    'border-red-400 focus:border-red-500 focus:ring-red-500' => $errors->has('remarks'),
                    'border-slate-300 focus:border-slate-500 focus:ring-slate-500' => ! $errors->has('remarks'),
                ])>{{ old('remarks') }}</textarea>
            @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- 3. What will happen --}}
    <div class="flex items-start gap-2 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-600">
        <x-icon name="information-circle" class="h-4 w-4 shrink-0 text-slate-400" />
        <p x-text="{
            forwarded: 'The document will be handed to the selected person. Status stays the same unless you pick a new one.',
            returned: 'The document goes back to the selected person (usually whoever handed it over).',
            status_changed: 'Only the status changes. The document stays with ' + @js($document->currentHolder?->name ?? 'the current holder') + '.',
            filed_in_court: 'Status becomes Filed.',
            released_to_client: 'Status becomes Released. The document leaves the office and will have no holder.',
            archived: 'Status becomes Archived. The document leaves active work.',
            received: 'Status goes back to Received and the document is with the selected person again.',
        }[action]"></p>
    </div>

    <div class="flex justify-end">
        <x-button icon="check-circle">Record Movement</x-button>
    </div>
</form>
