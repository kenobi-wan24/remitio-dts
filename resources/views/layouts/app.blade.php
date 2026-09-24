<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Remitio DTS') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚖️</text></svg>">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <style>[x-cloak] { display: none !important; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
    @php
        $user = auth()->user();
        $initials = \Illuminate\Support\Str::of($user->name)->after('Atty. ')->explode(' ')
            ->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    @endphp

    <div x-data="{ sidebarOpen: false }" class="min-h-full">

        {{-- ── Mobile sidebar (off-canvas) ── --}}
        <div x-show="sidebarOpen" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
            <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60" x-on:click="sidebarOpen = false"></div>
            <div x-show="sidebarOpen"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                class="fixed inset-y-0 left-0 flex w-72 flex-col">
                @include('layouts.sidebar')
            </div>
        </div>

        {{-- ── Desktop sidebar ── --}}
        <div class="hidden lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:w-64 lg:flex-col">
            @include('layouts.sidebar')
        </div>

        <div class="lg:pl-64">
            {{-- ── Top bar ── --}}
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                <button type="button" class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" x-on:click="sidebarOpen = true" aria-label="Open menu">
                    <x-icon name="bars-3" class="h-6 w-6" />
                </button>

                <form action="{{ route('search') }}" method="GET" class="relative w-full max-w-md">
                    <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search tracking code, client, case..."
                        class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:ring-slate-400">
                </form>

                <div class="ml-auto">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button type="button" class="flex items-center gap-2 rounded-lg p-1.5 text-left hover:bg-slate-100">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-800 text-xs font-semibold text-white">{{ $initials }}</span>
                                <span class="hidden sm:block">
                                    <span class="block text-sm font-medium leading-tight text-slate-800">{{ $user->name }}</span>
                                    <span class="block text-xs leading-tight text-slate-500">{{ $user->role->label() }}</span>
                                </span>
                                <x-icon name="chevron-down" class="hidden h-4 w-4 text-slate-400 sm:block" />
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">My Profile</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                    Log Out
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </header>

            {{-- ── Page content ── --}}
            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <x-flash-message />

                    {{-- Breeze pages (e.g. profile partials) may still pass a header slot --}}
                    @isset($header)
                        <div class="mb-6">{{ $header }}</div>
                    @endisset

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>
