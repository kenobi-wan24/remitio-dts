<x-guest-layout>
    <h2 class="text-2xl font-bold tracking-tight text-slate-900">Sign in</h2>
    <p class="mt-1 text-sm text-slate-500">Use the account given to you by the office administrator.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <x-form.input name="email" type="email" label="Email" required autofocus autocomplete="username" />
        <x-form.input name="password" type="password" label="Password" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-slate-800 shadow-sm focus:ring-slate-500">
                <span class="text-sm text-slate-600">Remember me</span>
            </label>
        </div>

        <x-button class="w-full py-2.5">Sign in</x-button>

        <p class="text-center text-xs text-slate-500">Forgot your password? Ask the administrator to reset it.</p>
    </form>
</x-guest-layout>
