@php use App\Enums\ClientType; @endphp

<x-app-layout :title="$client->display_name">
    <x-page-header :title="$client->display_name" :back="route('clients.index')">
        <x-slot name="actions">
            <x-button variant="secondary" :href="route('clients.edit', $client)" icon="pencil-square">Edit</x-button>
            @can('delete', $client)
                <x-confirm-delete :action="route('clients.destroy', $client)"
                    title="Delete {{ $client->display_name }}?"
                    message="The client will be moved to trash. This is only allowed if they have no cases or documents." />
            @endcan
        </x-slot>
    </x-page-header>

    <div class="-mt-3 mb-6 flex flex-wrap items-center gap-2 text-sm">
        <span class="font-mono text-slate-600">{{ $client->client_code }}</span>
        <x-status-badge :color="$client->client_type === ClientType::Company ? 'indigo' : 'gray'" :label="$client->client_type->label()" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Left: details --}}
        <div class="space-y-6">
            <x-card title="Client Details">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contact Number</dt>
                        <dd class="mt-0.5"><a href="tel:{{ preg_replace('/\s+/', '', $client->contact_number) }}" class="text-slate-900 hover:underline">{{ $client->contact_number }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-0.5">
                            @if ($client->email)
                                <a href="mailto:{{ $client->email }}" class="text-slate-900 hover:underline">{{ $client->email }}</a>
                            @else
                                <span class="text-slate-400">Not provided</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $client->address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Added</dt>
                        <dd class="mt-0.5 text-slate-900">
                            {{ $client->created_at->format('M d, Y') }}
                            @if ($client->creator) <span class="text-slate-500">by {{ $client->creator->name }}</span> @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Notes">
                @if ($client->notes)
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $client->notes }}</p>
                @else
                    <p class="text-sm text-slate-400">No notes.</p>
                @endif
            </x-card>
        </div>

        {{-- Right: cases & documents --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="grid grid-cols-2 gap-4">
                <x-stat-card label="Cases" :value="$cases->count()" icon="briefcase" color="blue" />
                <x-stat-card label="Documents" :value="$documentsCount" icon="document-text" color="indigo" />
            </div>

            {{-- Cases --}}
            <x-card title="Cases" :padding="false">
                @if (Route::has('cases.create'))
                    <x-slot name="actions">
                        <x-button size="sm" variant="secondary" :href="route('cases.create', ['client_id' => $client->id])" icon="plus">New Case</x-button>
                    </x-slot>
                @endif

                @forelse ($cases as $case)
                    <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-3 last:border-0 sm:flex-row sm:items-center sm:gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs text-slate-500">{{ $case->case_code }}</span>
                                <x-status-badge :status="$case->status" />
                                <span class="text-xs text-slate-500">{{ $case->case_type->label() }}</span>
                            </div>
                            @if (Route::has('cases.show'))
                                <a href="{{ route('cases.show', $case) }}" class="mt-0.5 block truncate text-sm font-medium text-slate-900 hover:underline">{{ $case->title }}</a>
                            @else
                                <p class="mt-0.5 truncate text-sm font-medium text-slate-900">{{ $case->title }}</p>
                            @endif
                            <p class="text-xs text-slate-500">
                                {{ $case->docket_number ?? 'No docket no.' }}
                                @if ($case->attorney) · {{ $case->attorney->name }} @endif
                            </p>
                        </div>
                        <div class="text-xs text-slate-500 sm:text-right">
                            <p>Opened {{ $case->date_opened->format('M d, Y') }}</p>
                            <p>{{ $case->documents_count }} {{ str('document')->plural($case->documents_count) }}</p>
                        </div>
                    </div>
                @empty
                    <x-empty-state class="m-5" icon="briefcase" title="No cases yet" message="Cases for this client will appear here." />
                @endforelse
            </x-card>

            {{-- Documents --}}
            <x-card title="Recent Documents" :padding="false">
                @if ($documentsCount > $documents->count())
                    <x-slot name="actions">
                        <span class="text-xs text-slate-500">Showing latest {{ $documents->count() }} of {{ $documentsCount }}</span>
                    </x-slot>
                @endif

                @if ($documents->isEmpty())
                    <x-empty-state class="m-5" icon="document-text" title="No documents yet" message="Documents received for this client will appear here." />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-2.5">Tracking Code</th>
                                    <th class="px-5 py-2.5">Document</th>
                                    <th class="px-5 py-2.5">Status</th>
                                    <th class="px-5 py-2.5">With</th>
                                    <th class="px-5 py-2.5">Received</th>
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
                                            <p class="text-xs text-slate-500">
                                                {{ $document->documentType?->name }}
                                                @if ($document->legalCase) · {{ $document->legalCase->case_code }} @endif
                                            </p>
                                        </td>
                                        <td class="px-5 py-3"><x-status-badge :status="$document->status" /></td>
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $document->currentHolder?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $document->date_received->format('M d, Y') }}</td>
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
