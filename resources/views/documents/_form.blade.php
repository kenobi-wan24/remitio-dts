{{--
    Shared by create & edit.
    Expects: $document, $documentTypes, $clients, $casesByClient, $users
    $isCreate toggles the "holder / remarks / attachments" parts.
--}}
@php
    $isCreate = ! $document->exists;
    $clientId = (string) old('client_id', $document->client_id ?? '');
    $caseId = (string) old('legal_case_id', $document->legal_case_id ?? '');
@endphp

<div class="space-y-6"
    x-data="{
        clientId: @js($clientId),
        caseId: @js($caseId),
        casesByClient: @js($casesByClient),
        get cases() { return this.casesByClient[this.clientId] ?? []; },
    }"
    x-on:select-changed="
        if ($event.detail.name === 'client_id') {
            clientId = $event.detail.value;
            if (! cases.some(c => c.id === caseId)) caseId = '';
        }
    ">

    <x-card title="Document Information">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-form.input name="title" label="Document Title" :value="$document->title" required maxlength="255"
                    placeholder="e.g. Answer with Counterclaim" />
            </div>

            <x-form.select name="document_type_id" label="Document Type" :options="$documentTypes"
                :selected="$document->document_type_id" placeholder="Select type" required />

            <x-form.input name="physical_location" label="Physical Location" :value="$document->physical_location" maxlength="255"
                placeholder="e.g. Cabinet A - Drawer 2" hint="Where the paper copy is kept." />

            <div class="sm:col-span-2">
                <x-form.textarea name="description" label="Description" :value="$document->description" rows="3"
                    placeholder="Short note about the contents, number of pages, etc." />
            </div>
        </div>
    </x-card>

    <x-card title="Client & Case">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.searchable-select name="client_id" label="Client" :options="$clients" :selected="$document->client_id"
                placeholder="Search and select a client" required />

            <div>
                <label for="legal_case_id" class="block text-sm font-medium text-slate-700">Case</label>
                <select id="legal_case_id" name="legal_case_id" x-model="caseId" :disabled="! clientId"
                    @class([
                        'mt-1 block w-full rounded-lg shadow-sm disabled:cursor-not-allowed disabled:bg-slate-100 sm:text-sm',
                        'border-red-400 focus:border-red-500 focus:ring-red-500' => $errors->has('legal_case_id'),
                        'border-slate-300 focus:border-slate-500 focus:ring-slate-500' => ! $errors->has('legal_case_id'),
                    ])>
                    <option value="" x-text="clientId ? '— No case (standalone / walk-in) —' : 'Select a client first'"></option>
                    <template x-for="c in cases" :key="c.id">
                        <option :value="c.id" x-text="c.label" :selected="c.id === caseId"></option>
                    </template>
                </select>
                <p x-show="clientId && cases.length === 0" x-cloak class="mt-1 text-xs text-slate-500">This client has no cases yet.</p>
                @error('legal_case_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-card>

    <x-card title="Dates">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="date_received" type="date" label="Date Received" :value="$document->date_received" required
                max="{{ today()->toDateString() }}" />
            <x-form.input name="due_date" type="date" label="Due Date" :value="$document->due_date"
                hint="Optional — filing deadline, hearing date, or release date." />
        </div>
    </x-card>

    @if ($isCreate)
        <x-card title="Receiving">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="current_holder_id" label="Currently With" :options="$users"
                    :selected="$document->current_holder_id" required hint="The person physically holding the document now." />
                <x-form.input name="received_remarks" label="Remarks" maxlength="1000"
                    placeholder="e.g. Received from client, 3 pages, original copy" />
            </div>
        </x-card>

        <x-card title="Attachments (optional)">
            <x-file-input label="Scanned copies / soft files" />
        </x-card>
    @else
        <div class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            <x-icon name="information-circle" class="h-5 w-5 shrink-0" />
            <p>Status, current holder, and location changes are logged as <strong>movements</strong> from the document page, so the tracking history stays complete. Attachments are also managed there.</p>
        </div>
    @endif
</div>
