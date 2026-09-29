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
                    @if ($document->notarial_reference)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notarial Register</dt>
                            <dd class="mt-0.5 font-medium text-slate-900">{{ $document->notarial_reference }}</dd>
                        </div>
                    @endif
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

        {{-- Right: tracking + attachments --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Phase 6: Update Tracking --}}
            <x-card title="Update Tracking" id="update-tracking">
                @include('documents._movement-form')
            </x-card>

            {{-- Phase 6: Timeline --}}
            <x-card title="Tracking History ({{ $document->movements->count() }})">
                <x-movement-timeline :movements="$document->movements" />
            </x-card>

            {{-- Phase 8: files with version control --}}
            @include('documents._attachments')

        </div>
    </div>
</x-app-layout>
