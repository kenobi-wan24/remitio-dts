{{-- Temporary page for modules not built yet. Deleted once all phases are done. --}}
<x-app-layout :title="$title">
    <x-page-header :title="$title" />

    <x-empty-state icon="wrench" title="{{ $title }} is under construction"
        message="This module is scheduled for Phase {{ $phase }} of development." />
</x-app-layout>
