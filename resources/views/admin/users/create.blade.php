<x-app-layout title="New Account">
    <x-page-header title="New Account" subtitle="Create a sign-in for an office member." :back="route('admin.users.index')" />

    <form method="POST" action="{{ route('admin.users.store') }}" class="max-w-3xl space-y-6">
        @csrf

        <x-card title="Account Details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Full Name" required maxlength="255" placeholder="e.g. Atty. Juan Dela Cruz" />
                <x-form.input name="email" type="email" label="Email (used to sign in)" required />
                <div class="sm:col-span-2">
                    <x-form.select name="role" label="Role" :options="\App\Enums\UserRole::options()" :selected="$user->role" required
                        hint="Administrator = Lawyer/Owner (and Law Associates handling cases). Staff = Executive Assistant, Secretary, Staff, Process Server." />
                </div>
            </div>
        </x-card>

        <x-card title="Initial Password">
            @include('admin.users._password-fields')
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="route('admin.users.index')">Cancel</x-button>
            <x-button icon="check-circle">Create Account</x-button>
        </div>
    </form>
</x-app-layout>
