<x-app-layout :title="$document->tracking_code">
    <x-page-header :title="$document->title" :back="route('documents.index')">
        <x-slot name="actions">
            <x-button variant="secondary" :href="route('documents.edit', $document)" icon="pencil-square">Edit</x-button>
            @can('delete', $document)
                <x-confirm-delete :action="route('documents.destroy', $document)"
                    title="Delete {{ $document->tracking_code }}?"
                    message="The document and its history will be moved to Deleted records. An admin can restore it later." />
            @endcan
        </x-slot>
    </x-page-header>

    {{-- Tracking strip --}}
    <div class="-mt-2 mb-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
        <div class="flex-1">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Tracking Code</p>
            <p class="font-mono text-2xl font-bold tracking-wider text-slate-900">{{ $document->tracking_code }}</p>
            @if ($document->isConfidential())
                <x-confidential-badge class="mt-1" />
                @if (! $document->is_confidential) <span class="text-xs text-slate-500">(because its case is)</span> @endif
            @endif
        </div>
        <div class="grid flex-[2] grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</p>
                <div class="mt-1"><x-status-badge :status="$document->status" class="text-sm" /></div>
                <p class="mt-1 text-xs text-slate-500">{{ $document->status->meaning() }}</p>
                @if ($document->urgency !== \App\Enums\Urgency::Normal || $document->isOnHold())
                    <div class="mt-1 flex flex-wrap gap-1">
                        @if ($document->urgency !== \App\Enums\Urgency::Normal) <x-status-badge :status="$document->urgency" :label="$document->urgency->label().' urgency'" /> @endif
                        @if ($document->isOnHold()) <x-status-badge color="purple" label="On hold" /> @endif
                    </div>
                @endif
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Currently With</p>
                <p class="mt-1 font-medium text-slate-900">{{ $document->currentHolder?->name ?? ($document->status->isFinal() ? 'Out of office' : '—') }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Location</p>
                @if ($document->isOutOfOffice())
                    <p class="mt-1"><x-status-badge color="amber" label="Out of office" /></p>
                    <p class="mt-1 font-medium text-slate-900">{{ $document->out_destination }}</p>
                @else
                    <p class="mt-1 font-medium text-slate-900">{{ $document->physical_location ?? '—' }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- R2: where the physical copy is while it is outside the office --}}
    @if ($document->isOutOfOffice())
        <div @class([
            '-mt-2 mb-6 flex flex-wrap items-start gap-x-8 gap-y-2 rounded-xl border px-5 py-4 text-sm',
            'border-red-200 bg-red-50 text-red-800' => $document->isOutOverdue(),
            'border-amber-200 bg-amber-50 text-amber-900' => ! $document->isOutOverdue(),
        ])>
            <p class="flex items-center gap-2 font-semibold"><x-icon name="truck" class="h-5 w-5" /> Physical copy out of the office</p>
            <p><span class="text-xs uppercase tracking-wide opacity-70">Brought to</span><br>{{ $document->out_destination }}</p>
            <p><span class="text-xs uppercase tracking-wide opacity-70">Purpose</span><br>{{ $document->out_purpose }}</p>
            <p><span class="text-xs uppercase tracking-wide opacity-70">Carried by</span><br>{{ $document->out_carried_by ?? '—' }}</p>
            <p><span class="text-xs uppercase tracking-wide opacity-70">Out since</span><br>{{ $document->out_since->format('M d, Y h:i A') }} ({{ $document->out_since->diffForHumans() }})</p>
            @if ($document->out_expected_back)
                <p><span class="text-xs uppercase tracking-wide opacity-70">Expected back</span><br>
                    {{ $document->out_expected_back->format('M d, Y') }} @if ($document->isOutOverdue()) <strong>· overdue</strong> @endif</p>
            @endif
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Left: details --}}
        <div class="space-y-6">
            @include('documents._priority')
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
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Date Requested In</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $document->date_received->format('M d, Y') }}</dd>
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

            {{-- 11C: parties to the document --}}
            @if ($document->parties->isNotEmpty())
                <x-card title="Parties ({{ $document->parties->count() }})" :padding="false">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($document->parties as $party)
                            @php $id = $party->validIds->first(); @endphp
                            <li class="px-5 py-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $party->pivot->role ?: 'Party '.($loop->iteration) }}</p>
                                <a href="{{ route('clients.show', $party) }}" class="text-sm font-medium text-slate-900 hover:underline">{{ $party->display_name }}</a>
                                <p class="text-xs text-slate-500">
                                    {{ $id ? $id->id_type.' '.$id->masked : 'No valid ID on file' }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            {{-- Workflow v2: dates filled in automatically from the tracking history --}}
            <x-card title="Key Dates">
                <dl class="space-y-2 text-sm">
                    @foreach ($keyDates as $label => $date)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd @class(['text-right', 'text-slate-900' => $date, 'text-slate-300' => ! $date])>
                                {{ $date?->format('M d, Y h:i A') ?? 'Not yet' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            {{-- Workflow v2: Notarial Register — a separate record linked to this document --}}
            @include('documents._notarial-box')

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
