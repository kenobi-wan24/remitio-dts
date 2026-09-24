@php use App\Services\AttachmentService; @endphp

<x-app-layout :title="$document->tracking_code">
    <x-page-header :title="$document->title" :back="route('documents.index')">
        <x-slot name="actions">
            <x-button variant="secondary" :href="route('documents.edit', $document)" icon="pencil-square">Edit</x-button>
            @can('delete', $document)
                <x-confirm-delete :action="route('documents.destroy', $document)"
                    title="Delete {{ $document->tracking_code }}?"
                    message="The document and its history will be moved to trash. An admin can restore it later." />
            @endcan
        </x-slot>
    </x-page-header>

    {{-- Tracking strip --}}
    <div class="-mt-2 mb-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
        <div class="flex-1">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Tracking Code</p>
            <p class="font-mono text-2xl font-bold tracking-wider text-slate-900">{{ $document->tracking_code }}</p>
        </div>
        <div class="grid flex-[2] grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</p>
                <div class="mt-1"><x-status-badge :status="$document->status" class="text-sm" /></div>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Currently With</p>
                <p class="mt-1 font-medium text-slate-900">{{ $document->currentHolder?->name ?? ($document->status->isFinal() ? 'Out of office' : '—') }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Location</p>
                <p class="mt-1 font-medium text-slate-900">{{ $document->physical_location ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Left: details --}}
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Type</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $document->documentType?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Client</dt>
                        <dd class="mt-0.5">
                            @if ($document->client && ! $document->client->trashed())
                                <a href="{{ route('clients.show', $document->client) }}" class="font-medium text-slate-900 hover:underline">{{ $document->client->display_name }}</a>
                            @else
                                <span class="text-slate-400">{{ $document->client?->display_name ?? '—' }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Case</dt>
                        <dd class="mt-0.5">
                            @if ($document->legalCase && ! $document->legalCase->trashed())
                                <a href="{{ route('cases.show', $document->legalCase) }}" class="font-medium text-slate-900 hover:underline">{{ $document->legalCase->case_code }}</a>
                                <span class="block text-xs text-slate-500">{{ $document->legalCase->title }}</span>
                            @else
                                <span class="text-slate-400">Standalone document</span>
                            @endif
                        </dd>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Received</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $document->date_received->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Due</dt>
                            <dd class="mt-0.5"><x-due-badge :document="$document" /></dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Recorded</dt>
                        <dd class="mt-0.5 text-slate-900">
                            {{ $document->created_at->format('M d, Y h:i A') }}
                            @if ($document->creator) <span class="text-slate-500">by {{ $document->creator->name }}</span> @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Description">
                @if ($document->description)
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $document->description }}</p>
                @else
                    <p class="text-sm text-slate-400">No description.</p>
                @endif
            </x-card>
        </div>

        {{-- Right: attachments + history --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Attachments --}}
            <x-card title="Attachments ({{ $document->attachments->count() }})">
                @if ($document->attachments->isNotEmpty())
                    <ul class="-mx-5 -mt-5 mb-5 divide-y divide-slate-100 border-b border-slate-100">
                        @foreach ($document->attachments as $attachment)
                            @php
                                $ext = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
                                $iconColor = match (true) {
                                    $ext === 'pdf' => 'bg-red-50 text-red-600',
                                    in_array($ext, ['doc', 'docx']) => 'bg-blue-50 text-blue-600',
                                    in_array($ext, ['xls', 'xlsx']) => 'bg-green-50 text-green-600',
                                    default => 'bg-amber-50 text-amber-600',
                                };
                            @endphp
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg {{ $iconColor }}">
                                    <x-icon name="document-text" class="h-4 w-4" />
                                    <span class="text-[9px] font-bold uppercase leading-none">{{ $ext }}</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $attachment->human_size }} · {{ $attachment->created_at->format('M d, Y') }}
                                        @if ($attachment->uploader) · {{ $attachment->uploader->name }} @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    @if (AttachmentService::isPreviewable($attachment->mime_type))
                                        <x-button variant="ghost" size="sm" icon="eye" target="_blank"
                                            :href="route('attachments.download', [$attachment, 'inline' => 1])">View</x-button>
                                    @endif
                                    <x-button variant="ghost" size="sm" :href="route('attachments.download', $attachment)">Download</x-button>
                                    @can('delete', $attachment)
                                        <x-confirm-delete :action="route('attachments.destroy', $attachment)" label=""
                                            title="Delete this file?" message="“{{ $attachment->original_name }}” will be permanently removed." />
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('documents.attachments.store', $document) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <x-file-input :label="$document->attachments->isEmpty() ? 'Upload scanned copies or soft files' : 'Add more files'" />
                    <div class="flex justify-end">
                        <x-button size="sm" icon="plus">Upload</x-button>
                    </div>
                </form>
            </x-card>

            {{-- History (Phase 6 adds the "Move / Update" form and full timeline) --}}
            <x-card title="Tracking History" :padding="false">
                <ol class="divide-y divide-slate-100">
                    @forelse ($document->movements as $movement)
                        <li class="flex gap-4 px-5 py-4">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                <x-icon name="arrows-right-left" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-status-badge :status="$movement->action" />
                                    @if ($movement->to_status)
                                        <x-status-badge :status="$movement->to_status" />
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-slate-700">
                                    by <span class="font-medium">{{ $movement->actor?->name ?? 'Unknown' }}</span>
                                    @if ($movement->toUser) → now with <span class="font-medium">{{ $movement->toUser->name }}</span> @endif
                                </p>
                                @if ($movement->remarks)
                                    <p class="mt-1 text-sm text-slate-500">{{ $movement->remarks }}</p>
                                @endif
                            </div>
                            <time class="shrink-0 text-right text-xs text-slate-400" title="{{ $movement->acted_at->format('M d, Y h:i A') }}">
                                {{ $movement->acted_at->format('M d, Y') }}<br>{{ $movement->acted_at->format('h:i A') }}
                            </time>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-slate-400">No movements recorded.</li>
                    @endforelse
                </ol>
            </x-card>
        </div>
    </div>
</x-app-layout>
