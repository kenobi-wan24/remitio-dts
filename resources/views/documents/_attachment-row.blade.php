{{-- One file version row. Expects: $attachment --}}
@php
    $ext = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
    $iconColor = match (true) {
        $ext === 'pdf' => 'bg-red-50 text-red-600',
        in_array($ext, ['doc', 'docx']) => 'bg-blue-50 text-blue-600',
        in_array($ext, ['xls', 'xlsx']) => 'bg-green-50 text-green-600',
        default => 'bg-amber-50 text-amber-600',
    };
@endphp

<div class="flex items-center gap-3">
    <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg {{ $iconColor }}">
        <x-icon name="document-text" class="h-4 w-4" />
        <span class="text-[9px] font-bold uppercase leading-none">{{ $ext }}</span>
    </span>

    <div class="min-w-0 flex-1">
        <p class="flex flex-wrap items-center gap-1.5">
            <span class="truncate text-sm font-medium text-slate-900" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</span>
            <x-status-badge color="gray" label="v{{ $attachment->version }}" />
            @if ($attachment->is_final)
                <x-status-badge color="green" label="FINAL" />
            @endif
        </p>
        <p class="text-xs text-slate-500">
            {{ $attachment->human_size }} · {{ $attachment->created_at->format('M d, Y h:i A') }}
            @if ($attachment->uploader) · {{ $attachment->uploader->name }} @endif
        </p>
        @if ($attachment->version_notes)
            <p class="mt-0.5 text-xs italic text-slate-500">“{{ $attachment->version_notes }}”</p>
        @endif
    </div>

    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1">
        @if (\App\Services\AttachmentService::isPreviewable($attachment->mime_type))
            <x-button variant="ghost" size="sm" icon="eye" target="_blank"
                :href="route('attachments.download', [$attachment, 'inline' => 1])">View</x-button>
        @endif
        <x-button variant="ghost" size="sm" :href="route('attachments.download', $attachment)">Download</x-button>


        @can('delete', $attachment)
            <x-confirm-delete :action="route('attachments.destroy', $attachment)" label=""
                title="Delete this file version?" message="“{{ $attachment->original_name }}” (v{{ $attachment->version }}) will be permanently removed." />
        @endcan
    </div>
</div>
