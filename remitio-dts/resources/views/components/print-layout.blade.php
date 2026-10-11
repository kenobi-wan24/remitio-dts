{{--
    Standalone printable page with the firm's letterhead.
    <x-print-layout title="Documents Report" orientation="landscape" :filters="$filters" :csv="true"> ... </x-print-layout>
    In the browser: Print → "Save as PDF" to get a PDF.
--}}
@props(['title', 'subtitle' => null, 'orientation' => 'portrait', 'filters' => [], 'csv' => false])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Remitio & Remitio Law Offices</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚖️</text></svg>">
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 {{ $orientation }}; margin: 12mm; }
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            thead { display: table-header-group; }
            tr, .avoid-break { break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased print:bg-white">
    {{-- Toolbar (hidden when printing) --}}
    <div class="no-print sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-6 py-3">
        <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900">
            <x-icon name="arrow-left" class="h-4 w-4" /> Back to Reports
        </a>
        <div class="flex items-center gap-2">
            @if ($csv)
                <x-button variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])" icon="arrow-down-tray">Download CSV</x-button>
            @endif
            <x-button type="button" onclick="window.print()" icon="document-text">Print / Save as PDF</x-button>
        </div>
    </div>

    <main class="mx-auto my-6 max-w-[1120px] bg-white p-8 shadow print:m-0 print:max-w-none print:p-0 print:shadow-none">
        {{-- Letterhead --}}
        <header class="flex items-start justify-between gap-6 border-b-2 border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-900 text-amber-400">
                    <x-application-logo class="h-6 w-6" />
                </span>
                <div>
                    <p class="text-lg font-bold leading-tight text-slate-900">Remitio &amp; Remitio Law Offices</p>
                    <p class="text-xs text-slate-500">Davao City · Document Tracking System</p>
                </div>
            </div>
            <div class="text-right text-xs text-slate-500">
                <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
                <p>by {{ auth()->user()->name }}</p>
            </div>
        </header>

        <div class="mt-5">
            <h1 class="text-xl font-bold text-slate-900">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
            @if ($filters)
                <p class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-600">
                    @foreach ($filters as $label => $value)
                        <span><span class="font-semibold">{{ $label }}:</span> {{ $value }}</span>
                    @endforeach
                </p>
            @endif
        </div>

        <div class="mt-5">
            {{ $slot }}
        </div>

        <footer class="mt-8 border-t border-slate-200 pt-3 text-center text-[10px] text-slate-400">
            Confidential — for the internal use of Remitio &amp; Remitio Law Offices only.
        </footer>
    </main>
</body>
</html>
