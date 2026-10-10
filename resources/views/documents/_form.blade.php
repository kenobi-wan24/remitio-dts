{{--
    Shared by create & edit.
    Expects: $document, $documentTypes, $clients, $casesByClient, $users, $minPartiesByType, $initialParties
    $isCreate toggles the "holder / remarks / attachments" parts.
--}}
@php
    $isCreate = ! $document->exists;
    $clientId = (string) old('client_id', $document->client_id ?? '');
    $caseId = (string) old('legal_case_id', $document->legal_case_id ?? '');
    $typeId = (string) old('document_type_id', $document->document_type_id ?? '');
    $partyRows = collect(old('parties', $initialParties))
        ->map(fn ($p) => ['client_id' => (string) ($p['client_id'] ?? ''), 'role' => (string) ($p['role'] ?? ''), 'search' => '', 'open' => false])
        ->values();
    $partyItems = collect($clients)->map(fn ($label, $id) => ['value' => (string) $id, 'label' => $label])->values();
@endphp

<div class="space-y-6"
    x-data="{
        clientId: @js($clientId),
        caseId: @js($caseId),
        casesByClient: @js($casesByClient),
        get cases() { return this.casesByClient[this.clientId] ?? []; },
        typeId: @js($typeId),
        minByType: @js($minPartiesByType),
        get minParties() { return Number(this.minByType[this.typeId] ?? 1); },
    }"
    x-on:select-changed="
        if ($event.detail.name === 'client_id') {
            clientId = $event.detail.value;
            if (! cases.some(c => c.id === caseId)) caseId = '';
        }
    ">

    {{-- Phase 8: possible duplicate warning --}}
    @if (session('duplicateDocuments'))
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-5">
            <div class="flex gap-3">
                <x-icon name="exclamation-triangle" class="h-6 w-6 shrink-0 text-amber-600" />
                <div class="flex-1">
                    <h3 class="font-semibold text-amber-900">This document may already be recorded</h3>
                    <p class="mt-1 text-sm text-amber-800">A document with the same title, type and client was received within 30 days of this one.</p>
                    <ul class="mt-3 space-y-2">
                        @foreach (session('duplicateDocuments') as $dup)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-white/70 px-3 py-2 text-sm">
                                <a href="{{ route('documents.show', $dup['id']) }}" target="_blank" class="font-mono font-semibold text-slate-900 underline">{{ $dup['code'] }}</a>
                                <span class="text-slate-700">{{ $dup['title'] }}</span>
                                <span class="text-slate-500">received {{ $dup['received'] }} · {{ $dup['status'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <label class="mt-4 flex items-center gap-2 text-sm font-medium text-amber-900">
                        <input type="checkbox" name="confirm_duplicate" value="1" class="rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                        This is a different document. Record it anyway.
                    </label>
                    <p class="mt-1 text-xs text-amber-700">Note: re-select any files you want to attach — browsers don't keep them after a warning.</p>
                </div>
            </div>
        </div>
    @endif

    <x-card title="Document Information">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-form.input name="title" label="Document Title" :value="$document->title" required maxlength="255"
                    placeholder="e.g. Answer with Counterclaim" />
            </div>

            <x-form.select name="document_type_id" label="Document Type" :options="$documentTypes"
                :selected="$document->document_type_id" placeholder="Select type" required x-model="typeId" />

            <x-form.input name="physical_location" label="Physical Location" :value="$document->physical_location" maxlength="255"
                placeholder="e.g. Cabinet A - Drawer 2" hint="Where the paper copy is kept." />

            <div class="sm:col-span-2">
                <x-form.textarea name="description" label="Description" :value="$document->description" rows="3"
                    placeholder="Short note about the contents, number of pages, etc." />
            </div>
        </div>
        <x-confidential-toggle :checked="$document->is_confidential" what="document" :inherited="(bool) $document->legalCase?->is_confidential" />
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

    {{-- 11C: parties to the document (Agreements and Deeds need at least 2) --}}
    <x-card title="Parties"
        x-data="{
            rows: @js($partyRows),
            items: @js($partyItems),
            addRow() { this.rows.push({ client_id: '', role: '', search: '', open: false }) },
            labelOf(id) { return (this.items.find(i => i.value === id) || {}).label || '' },
            filtered(row) {
                const t = row.search.toLowerCase().trim();
                return t === '' ? this.items.slice(0, 50) : this.items.filter(i => i.label.toLowerCase().includes(t)).slice(0, 50);
            },
        }"
        x-effect="
            if (minParties >= 2) {
                while (rows.length < minParties) addRow();
                if (rows[0] && ! rows[0].client_id && clientId) rows[0].client_id = clientId;
            }
        ">
        <p class="-mt-1 mb-4 text-xs text-slate-500" x-show="minParties >= 2">
            This document type needs at least <strong x-text="minParties"></strong> parties, e.g. <em>First Party / Vendor</em> and <em>Second Party / Vendee</em>. Each party must be a client; add new people under Clients first.
        </p>
        <p class="-mt-1 mb-4 text-xs text-slate-500" x-show="minParties < 2">
            Optional. List the people involved if the document has more than one party.
        </p>

        <div class="space-y-3">
            <template x-for="(row, i) in rows" :key="i">
                <div class="grid gap-2 sm:grid-cols-12 sm:items-start">
                    {{-- client picker (type to search) --}}
                    <div class="relative sm:col-span-7" x-on:click.outside="row.open = false">
                        <input type="hidden" :name="`parties[${i}][client_id]`" :value="row.client_id">
                        <button type="button" x-on:click="row.open = ! row.open"
                            class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm shadow-sm">
                            <span class="truncate" :class="row.client_id ? 'text-slate-900' : 'text-slate-400'"
                                x-text="labelOf(row.client_id) || `Party ${i + 1}: select a client`"></span>
                            <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
                        </button>
                        <div x-show="row.open" x-cloak class="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                            <div class="border-b border-slate-100 p-2">
                                <input type="text" x-model="row.search" placeholder="Type to search..."
                                    class="block w-full rounded-md border-slate-300 py-1.5 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </div>
                            <ul class="max-h-56 overflow-y-auto py-1 text-sm">
                                <template x-for="item in filtered(row)" :key="item.value">
                                    <li><button type="button" x-on:click="row.client_id = item.value; row.open = false; row.search = ''"
                                        class="block w-full truncate px-3 py-2 text-left text-slate-700 hover:bg-slate-50" x-text="item.label"></button></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <input type="text" :name="`parties[${i}][role]`" x-model="row.role" list="party-roles" maxlength="60"
                        placeholder="Role, e.g. First Party / Vendor"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:col-span-4">
                    <button type="button" x-on:click="rows.splice(i, 1)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-red-600 sm:col-span-1" aria-label="Remove party">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>
            </template>
        </div>

        <datalist id="party-roles">
            @foreach (['First Party', 'Second Party', 'Vendor', 'Vendee', 'Lessor', 'Lessee', 'Donor', 'Donee', 'Assignor', 'Assignee', 'Mortgagor', 'Mortgagee', 'Principal', 'Attorney-in-Fact'] as $role)
                <option value="{{ $role }}">
            @endforeach
        </datalist>

        <button type="button" x-on:click="addRow()" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-700 hover:text-slate-900">
            <x-icon name="plus" class="h-4 w-4" /> Add party
        </button>

        @foreach ($errors->getMessages() as $key => $messages)
            @if ($key === 'parties' || str_starts_with($key, 'parties.'))
                @foreach (array_unique($messages) as $message)
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @endforeach
            @endif
        @endforeach
    </x-card>

    <x-card title="Date">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="date_received" type="date" label="Date Received" :value="$document->date_received" required
                max="{{ today()->toDateString() }}" hint="The date the request or document reached the office." />
            @if (config('dts.deadlines'))
                {{-- R4: optional, only when the firm turned deadlines on --}}
                <x-form.input name="deadline" type="date" label="Deadline (optional)" :value="$document->deadline"
                    hint="Only if there is a real deadline (court, client, agency). Used to sort the priority queue." />
            @endif
        </div>
        <p class="mt-3 text-xs text-slate-500">Drafted, approved, signed, notarized and released dates are recorded automatically as the document moves.</p>
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
