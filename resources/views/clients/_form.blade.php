{{-- Shared by create & edit. Expects $client. --}}
@php
    use App\Enums\CivilStatus;
    use App\Enums\ClientType;
    use App\Enums\Gender;
    use App\Enums\NameSuffix;
    use App\Models\ClientId;

    $currentType = old('client_type', $client->client_type?->value ?? ClientType::Individual->value);
    $currentCategory = old('category', $client->notarization_only ? 'walk_in' : ($client->is_retainer ? 'retainer' : 'regular'));
    $currentCivil = old('civil_status', $client->civil_status?->value ?? '');
    $isNew = ! $client->exists;
@endphp

<div x-data="{
        type: @js($currentType),
        category: @js($currentCategory),
        civil: @js($currentCivil),
        get isWalkIn() { return this.category === 'walk_in'; },
        get isCompany() { return this.type === 'company' && ! this.isWalkIn; },
        get isMarried() { return ! this.isCompany && this.civil === 'married'; },
    }"
    x-init="$watch('category', value => { if (value === 'walk_in') type = 'individual'; })"
    class="space-y-6">

    {{-- Possible duplicate warning --}}
    @if (session('duplicates'))
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-5">
            <div class="flex gap-3">
                <x-icon name="exclamation-triangle" class="h-6 w-6 shrink-0 text-amber-600" />
                <div class="flex-1">
                    <h3 class="font-semibold text-amber-900">Possible duplicate client</h3>
                    <p class="mt-1 text-sm text-amber-800">A client with similar details is already on record. Please check before saving to avoid duplicated records.</p>

                    <ul class="mt-3 space-y-2">
                        @foreach (session('duplicates') as $dup)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-white/70 px-3 py-2 text-sm">
                                <a href="{{ route('clients.show', $dup['id']) }}" target="_blank" class="font-medium text-slate-900 underline">{{ $dup['name'] }}</a>
                                <span class="font-mono text-xs text-slate-500">{{ $dup['code'] }}</span>
                                <span class="text-slate-500">{{ $dup['contact'] }}</span>
                                @foreach ($dup['reasons'] as $reason)
                                    <x-status-badge color="amber" :label="$reason" />
                                @endforeach
                            </li>
                        @endforeach
                    </ul>

                    <label class="mt-4 flex items-center gap-2 text-sm font-medium text-amber-900">
                        <input type="checkbox" name="confirm_duplicate" value="1" class="rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                        This is a different client. Save anyway.
                    </label>
                </div>
            </div>
        </div>
    @endif

    {{-- Category + type --}}
    <x-card title="Client Type">
        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Category</p>
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                'regular' => ['Regular client', 'Individual or company with cases and documents.'],
                'retainer' => ['Retainer client', 'Corporate / cooperative account with a continuing engagement.'],
                'walk_in' => ['Walk-in', 'Recorded for notarization only. No cases.'],
            ] as $value => [$label, $hint])
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                    :class="category === '{{ $value }}' ? 'border-slate-800 bg-slate-50 ring-1 ring-slate-800' : 'border-slate-200 hover:border-slate-300'">
                    <input type="radio" name="category" value="{{ $value }}" x-model="category" class="mt-0.5 text-slate-800 focus:ring-slate-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-800">{{ $label }}</span>
                        <span class="block text-xs text-slate-500">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('category') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

        <div x-show="! isWalkIn" class="mt-5">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Client is a</p>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (ClientType::cases() as $option)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition"
                        :class="type === '{{ $option->value }}' ? 'border-slate-800 bg-slate-50 ring-1 ring-slate-800' : 'border-slate-200 hover:border-slate-300'">
                        <input type="radio" name="client_type" value="{{ $option->value }}" x-model="type" class="text-slate-800 focus:ring-slate-500">
                        <x-icon :name="$option === ClientType::Company ? 'briefcase' : 'user'" class="h-5 w-5 text-slate-500" />
                        <span class="text-sm font-medium text-slate-800">{{ $option->label() }}</span>
                    </label>
                @endforeach
            </div>
            @error('client_type') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <p x-show="isWalkIn" x-cloak class="mt-4 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800">
            Walk-in clients are individuals recorded only so the firm can notarize their document. A valid ID is required, and cases cannot be opened for them.
        </p>
    </x-card>

    {{-- Individual --}}
    <x-card title="Personal Information" x-show="! isCompany">
        <div class="grid gap-5 sm:grid-cols-6">
            <div class="sm:col-span-2">
                <x-form.input name="last_name" label="Last Name" :value="$client->last_name" required
                    x-bind:required="! isCompany" maxlength="100" />
            </div>
            <div class="sm:col-span-2">
                <x-form.input name="first_name" label="First Name" :value="$client->first_name" required
                    x-bind:required="! isCompany" maxlength="100" />
            </div>
            <div class="sm:col-span-1">
                <x-form.input name="middle_name" label="Middle Name" :value="$client->middle_name" maxlength="100" />
            </div>
            <div class="sm:col-span-1">
                <x-form.select name="suffix" label="Suffix" :options="NameSuffix::options()" :selected="$client->suffix" placeholder="None" />
            </div>

            <div class="sm:col-span-3">
                <x-form.select name="civil_status" label="Civil Status" :options="CivilStatus::options()" :selected="$client->civil_status"
                    placeholder="Select" required x-model="civil" x-bind:required="! isCompany && ! isWalkIn" />
            </div>
            <div class="sm:col-span-3">
                <x-form.select name="gender" label="Gender" :options="Gender::options()" :selected="$client->gender"
                    placeholder="Select" required x-bind:required="! isCompany && ! isWalkIn" />
            </div>
        </div>

        {{-- Spouse details (married only) --}}
        <div x-show="isMarried" x-cloak class="mt-6 rounded-lg border border-slate-200 bg-slate-50/60 p-4">
            <h3 class="text-sm font-semibold text-slate-800">Spouse Details</h3>
            <div class="mt-3 grid gap-5 sm:grid-cols-3">
                <x-form.input name="spouse_last_name" label="Last Name" :value="$client->spouse_last_name" required
                    x-bind:required="isMarried" maxlength="100" />
                <x-form.input name="spouse_first_name" label="First Name" :value="$client->spouse_first_name" required
                    x-bind:required="isMarried" maxlength="100" />
                <x-form.input name="spouse_middle_name" label="Middle Name" :value="$client->spouse_middle_name" maxlength="100" />
                <div class="sm:col-span-3 sm:max-w-xs">
                    <x-form.input name="spouse_contact_number" label="Contact Number" :value="$client->spouse_contact_number"
                        placeholder="Optional" maxlength="30" />
                </div>
            </div>
        </div>
    </x-card>

    {{-- Company --}}
    <x-card title="Company Information" x-show="isCompany" x-cloak>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-form.input name="company_name" label="Business Name" :value="$client->company_name" required
                    x-bind:required="isCompany" maxlength="150" />
            </div>
            <div class="sm:col-span-2">
                <x-form.input name="representative" label="Representative" :value="$client->representative" required
                    x-bind:required="isCompany" maxlength="150"
                    hint="The person who acts for the company (name and position, e.g. Ana Reyes, General Manager)." />
            </div>
            <x-form.input name="business_permit_number" label="Business Permit No." :value="$client->business_permit_number"
                placeholder="Optional" maxlength="100" />
            <x-form.input name="tin" label="TIN" :value="$client->tin" placeholder="000-000-000-000" maxlength="30" />
        </div>
    </x-card>

    {{-- Contact --}}
    <x-card title="Contact Details">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="contact_number" label="Contact Number" :value="$client->contact_number" required
                x-bind:required="! isWalkIn" placeholder="09XX XXX XXXX" maxlength="30" />
            <x-form.input name="email" type="email" label="Email" :value="$client->email" placeholder="Optional" />

            <div class="sm:col-span-2">
                <x-form.input name="address" label="Address" :value="$client->address" placeholder="Street, Barangay, City" maxlength="255" />
            </div>
        </div>
    </x-card>

    {{-- Valid ID (new clients; more IDs can be added later on the client's profile) --}}
    @if ($isNew)
        <x-card title="Valid ID" x-show="! isCompany">
            <p class="-mt-1 mb-4 text-xs text-slate-500">
                <span x-show="isWalkIn" x-cloak class="font-semibold text-slate-700">Required for walk-in clients.</span>
                <span x-show="! isWalkIn">Optional. You can also add IDs later on the client's profile.</span>
                ID numbers are personal information (Data Privacy Act) and are masked elsewhere in the system.
            </p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-form.input name="id_type" label="ID Type" list="client-id-types" maxlength="60" required
                        x-bind:required="isWalkIn && ! isCompany" placeholder="e.g. Driver's License" />
                    <datalist id="client-id-types">
                        @foreach (ClientId::COMMON_TYPES as $idType) <option value="{{ $idType }}"> @endforeach
                    </datalist>
                </div>
                <x-form.input name="id_number" label="ID Number" maxlength="60" required
                    x-bind:required="isWalkIn && ! isCompany" placeholder="e.g. D12-88-013540" />
                <div class="sm:col-span-2">
                    <label for="scan" class="block text-sm font-medium text-slate-700">Scanned copy (optional)</label>
                    <input type="file" id="scan" name="scan" accept=".pdf,.jpg,.jpeg,.png"
                        class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                    @error('scan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-card>
    @endif

    <x-card title="Notes">
        <x-form.textarea name="notes" :value="$client->notes" rows="4"
            placeholder="Referral source, preferred contact time, etc." />
    </x-card>
</div>
