<x-app-layout title="My Profile">
    <x-page-header title="My Profile" subtitle="Update your name, email address, and password." />

    <div class="max-w-3xl space-y-6">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>
    </div>
</x-app-layout>
