<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Remitio DTS') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚖️</text></svg>">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
    <div class="flex min-h-full">
        {{-- Left brand panel (desktop only) --}}
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-slate-900 p-12 text-white lg:flex">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-amber-500/10"></div>
            <div class="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-white/5"></div>

            <div class="relative flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-amber-500 text-slate-900">
                    <x-application-logo class="h-6 w-6" />
                </span>
                <span class="leading-tight">
                    <span class="block font-semibold">Remitio &amp; Remitio Law Offices</span>
                    <span class="block text-sm text-slate-400">Davao City · Est. 2008</span>
                </span>
            </div>

            <div class="relative max-w-md">
                <h1 class="text-4xl font-bold leading-tight">Every document.<br>Every hand-off.<br><span class="text-amber-400">Accounted for.</span></h1>
                <p class="mt-4 text-slate-400">Record clients, cases, and documents in one place, and always know where a document is and who has it.</p>
            </div>

            <p class="relative text-xs text-slate-500">Document Tracking System · For authorized office personnel only</p>
        </div>

        {{-- Right: form --}}
        <div class="flex flex-1 flex-col justify-center bg-slate-50 px-6 py-12 sm:px-12">
            <div class="mx-auto w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-900 text-amber-400">
                        <x-application-logo class="h-6 w-6" />
                    </span>
                    <span class="font-semibold leading-tight text-slate-900">Remitio &amp; Remitio<br><span class="text-sm font-normal text-slate-500">Document Tracking System</span></span>
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
