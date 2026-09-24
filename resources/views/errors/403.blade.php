<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access denied · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex h-full items-center justify-center px-6 font-sans antialiased">
    <div class="max-w-md text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
            <x-icon name="lock-closed" class="h-7 w-7" />
        </div>
        <p class="mt-4 text-sm font-semibold text-red-600">403 · Access denied</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">You can't open this page</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $exception->getMessage() ?: 'Your account does not have permission to view this page.' }}</p>
        <div class="mt-6">
            <x-button :href="url('/dashboard')" icon="arrow-left">Back to Dashboard</x-button>
        </div>
    </div>
</body>
</html>
