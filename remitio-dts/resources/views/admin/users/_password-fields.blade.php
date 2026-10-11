{{--
    Password + confirmation with a "Generate" button.
    The generated password is shown once so the admin can give it to the user.
--}}
<div x-data="{
        show: false,
        password: '',
        generate() {
            const letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
            const digits = '23456789';
            const all = letters + digits;
            const pick = s => s[crypto.getRandomValues(new Uint32Array(1))[0] % s.length];
            let p = pick(letters) + pick(digits);
            for (let i = 0; i < 8; i++) p += pick(all);
            this.password = p.split('').sort(() => 0.5 - Math.random()).join('');
            this.show = true;
        },
    }" class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="password" class="block text-sm font-medium text-slate-700">{{ $label ?? 'Password' }} <span class="text-red-500">*</span></label>
        <div class="relative mt-1">
            <input id="password" name="password" x-model="password" :type="show ? 'text' : 'password'" required autocomplete="new-password"
                @class([
                    'block w-full rounded-lg pr-16 shadow-sm sm:text-sm',
                    'border-red-400 focus:border-red-500 focus:ring-red-500' => $errors->has('password'),
                    'border-slate-300 focus:border-slate-500 focus:ring-slate-500' => ! $errors->has('password'),
                ])>
            <button type="button" x-on:click="show = ! show" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-slate-500 hover:text-slate-800"
                x-text="show ? 'Hide' : 'Show'"></button>
        </div>
        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        <p class="mt-1 text-xs text-slate-500">At least 8 characters, with letters and numbers.</p>
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm Password <span class="text-red-500">*</span></label>
        <input id="password_confirmation" name="password_confirmation" x-model="password" :type="show ? 'text' : 'password'" required autocomplete="new-password"
            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm">
        <button type="button" x-on:click="generate()" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-slate-700 hover:text-slate-900">
            <x-icon name="arrow-path" class="h-3.5 w-3.5" /> Generate a password
        </button>
    </div>

    <p x-show="show && password" x-cloak class="sm:col-span-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Copy this password now and give it to the user privately: <span class="font-mono font-semibold" x-text="password"></span>
    </p>
</div>
