<x-app-layout :title="$case->case_code">
    <x-page-header :title="$case->title" :back="route('cases.index')">
        <x-slot name="actions">
            <x-button variant="secondary" :href="route('cases.edit', $case)" icon="pencil-square">Edit</x-button>
            @can('delete', $case)
                <x-confirm-delete :action="route('cases.destroy', $case)"
                    title="Delete case {{ $case->case_code }}?"
                    message="The case will be moved to trash. This is only allowed if it has no documents. To end a matter, set its status to Closed instead." />
            @endcan
        </x-slot>
    </x-page-header>

    <div class="-mt-3 mb-6 flex flex-wrap items-center gap-2 text-sm">
        <span class="font-mono text-slate-600">{{ $case->case_code }}</span>
        <x-status-badge :status="$case->status" />
        <x-status-badge color="gray" :label="$case->case_type->label()" />
        @if ($case->docket_number)
            <span class="text-slate-500">· {{ $case->docket_number }}</span>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Left: details --}}
        <div class="space-y-6">
            <x-card title="Case Details">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Client</dt>
                        <dd class="mt-0.5">
                            @if ($case->client && ! $case->client->trashed())
                                <a href="{{ route('clients.show', $case->client) }}" class="font-medium text-slate-900 hover:underline">{{ $case->client->display_name }}</a>
                                <span class="block text-xs text-slate-500">{{ $case->client->client_code }} · {{ $case->client->contact_number }}</span>
                            @else
                                <span class="text-slate-400">{{ $case->client?->display_name ?? '—' }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Handling Attorney</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $case->attorney?->name ?? 'Unassigned' }}</dd>
                    </div>
                    @if ($case->case_type->isLitigated())
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Docket / Case No.</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $case->docket_number ?? 'Not yet filed' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Court / Venue</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $case->court_or_venue ?? '—' }}</dd>
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Opened</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $case->date_opened->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Closed</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $case->date_closed?->format('M d, Y') ?? '—' }}</dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Recorded</dt>
                        <dd class="mt-0.5 text-slate-900">
                            {{ $case->created_at->format('M d, Y') }}
                            @if ($case->creator) <span class="text-slate-500">by {{ $case->creator->name }}</span> @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Description">
                @if ($case->description)
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $case->description }}</p>
                @else
                    <p class="text-sm text-slate-400">No description.</p>
                @endif
            </x-card>
        </div>

        {{-- Right: documents --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="grid grid-cols-3 gap-4">
                <x-stat-card label="Documents" :value="$stats['total']" icon="document-text" color="indigo" />
                <x-stat-card label="In Process" :value="$stats['open']" icon="arrows-right-left" color="blue" />
                <x-stat-card label="Overdue" :value="$stats['overdue']" icon="exclamation-triangle" :color="$stats['overdue'] ? 'red' : 'green'" />
            </div>

            <x-card title="Case Documents" :padding="false">
                @if (Route::has('documents.create'))
                    <x-slot name="actions">
                        <x-button size="sm" variant="secondary" icon="plus"
                            :href="route('documents.create', ['client_id' => $case->client_id, 'legal_case_id' => $case->id])">Add Document</x-button>
                    </x-slot>
                @endif

                @if ($documents->isEmpty())
                    <x-empty-state class="m-5" icon="document-text" title="No documents yet" message="Documents filed under this case will appear here." />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-2.5">Tracking Code</th>
                                    <th class="px-5 py-2.5">Document</th>
                                    <th class="px-5 py-2.5">Status</th>
                                    <th class="px-5 py-2.5">With</th>
                                    <th class="px-5 py-2.5">Due</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($documents as $document)
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-5 py-3 font-mono text-xs">
                                            @if (Route::has('documents.show'))
                                                <a href="{{ route('documents.show', $document) }}" class="font-semibold text-slate-900 hover:underline">{{ $document->tracking_code }}</a>
                                            @else
                                                <span class="font-semibold text-slate-900">{{ $document->tracking_code }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <p class="text-slate-900">{{ $document->title }}</p>
                                            <p class="text-xs text-slate-500">{{ $document->documentType?->name }} · received {{ $document->date_received->format('M d, Y') }}</p>
                                        </td>
                                        <td class="px-5 py-3"><x-status-badge :status="$document->status" /></td>
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $document->currentHolder?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-5 py-3">
                                            @if ($document->is_overdue)
                                                <x-status-badge color="red" label="Overdue · {{ $document->due_date->format('M d') }}" />
                                            @elseif ($document->is_due_soon)
                                                <x-status-badge color="amber" label="{{ $document->due_date->format('M d') }}" />
                                            @else
                                                <span class="text-slate-500">{{ $document->due_date?->format('M d, Y') ?? '—' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
