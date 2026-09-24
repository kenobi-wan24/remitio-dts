{{-- Shared by create & edit. Expects $client. --}}
@php
    use App\Enums\ClientType;
    $currentType = old('client_type', $client->client_type?->value ?? ClientType::Individual->value);
@endphp

<div x-data="{ type: '{{ $currentType }}' }" class="space-y-6">

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

    {{-- Client type --}}
    <x-card title="Client Type">
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
    </x-card>

    {{-- Details --}}
    <x-card title="Client Information">
        <div class="grid gap-5 sm:grid-cols-2">
            <div x-show="type === 'individual'">
                <x-form.input name="first_name" label="First Name" :value="$client->first_name" required
                    x-bind:required="type === 'individual'" maxlength="100" />
            </div>
            <div x-show="type === 'individual'">
                <x-form.input name="last_name" label="Last Name" :value="$client->last_name" required
                    x-bind:required="type === 'individual'" maxlength="100" />
            </div>
            <div x-show="type === 'company'" x-cloak class="sm:col-span-2">
                <x-form.input name="company_name" label="Company / Organization Name" :value="$client->company_name" required
                    x-bind:required="type === 'company'" maxlength="150" />
            </div>

            <x-form.input name="contact_number" label="Contact Number" :value="$client->contact_number" required
                placeholder="09XX XXX XXXX" maxlength="30" />
            <x-form.input name="email" type="email" label="Email" :value="$client->email" placeholder="Optional" />

            <div class="sm:col-span-2">
                <x-form.input name="address" label="Address" :value="$client->address" placeholder="Street, Barangay, City" maxlength="255" />
            </div>
        </div>
    </x-card>

    <x-card title="Notes">
        <x-form.textarea name="notes" :value="$client->notes" rows="4"
            placeholder="Referral source, preferred contact time, contact person for companies, etc." />
    </x-card>
</div>
