{{-- Shared by create & edit. Expects $case, $clients, $attorneys. --}}
@php
    use App\Enums\CaseStatus;
    use App\Enums\CaseType;

    $currentType = old('case_type', $case->case_type?->value ?? '');
    $currentStatus = old('status', $case->status?->value ?? CaseStatus::Open->value);
    $litigatedTypes = collect(CaseType::cases())->filter->isLitigated()->map->value->values();
@endphp

<div x-data="{
        caseType: @js($currentType),
        status: @js($currentStatus),
        litigated: @js($litigatedTypes),
        get isLitigated() { return this.litigated.includes(this.caseType); },
    }" class="space-y-6">

    <x-card title="Case Information">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-form.input name="title" label="Case Title" :value="$case->title" required maxlength="255"
                    placeholder="e.g. Dela Cruz vs. Mindanao Realty Corp." />
            </div>

            <div class="sm:col-span-2">
                <x-form.searchable-select name="client_id" label="Client" :options="$clients" :selected="$case->client_id"
                    placeholder="Search and select a client" required
                    hint="Client not listed? Add them first under Clients." />
            </div>

            <x-form.select name="case_type" label="Case Type" :options="CaseType::options()" :selected="$case->case_type"
                placeholder="Select type" required x-model="caseType" />

            <x-form.select name="handling_attorney_id" label="Handling Attorney" :options="$attorneys"
                :selected="$case->handling_attorney_id" placeholder="Unassigned" />
        </div>
    </x-card>

    <x-card title="Court / Agency Details" x-show="isLitigated" x-cloak>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="docket_number" label="Docket / Case Number" :value="$case->docket_number" maxlength="50"
                placeholder="e.g. R-DVO-26-01234-CV" hint="Leave blank if not yet filed." />
            <x-form.input name="court_or_venue" label="Court / Venue" :value="$case->court_or_venue" maxlength="255"
                placeholder="e.g. RTC Branch 12, Davao City" />
        </div>
    </x-card>

    <x-card title="Status">
        <div class="grid gap-5 sm:grid-cols-3">
            <x-form.select name="status" label="Status" :options="CaseStatus::options()" :selected="$case->status"
                required x-model="status" />
            <x-form.input name="date_opened" type="date" label="Date Opened" :value="$case->date_opened" required
                max="{{ today()->toDateString() }}" />
            <div x-show="status === 'closed'" x-cloak>
                <x-form.input name="date_closed" type="date" label="Date Closed" :value="$case->date_closed"
                    max="{{ today()->toDateString() }}" hint="Defaults to today if left blank." />
            </div>
        </div>
    </x-card>

    <x-card title="Description">
        <x-form.textarea name="description" :value="$case->description" rows="4"
            placeholder="Summary of the matter, opposing party, key dates, etc." />
    </x-card>
</div>
