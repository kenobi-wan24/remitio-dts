{{-- R3: small lock badge for confidential cases/documents. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800']) }}
    title="Confidential — visible only to the people assigned to it">
    <x-icon name="lock-closed" class="h-3 w-3" /> Confidential
</span>
