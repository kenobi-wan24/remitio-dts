<x-app-layout :title="$showTrashed ? 'Deleted Clients' : 'Clients'">
    <x-page-header :title="$showTrashed ? 'Deleted Clients' : 'Clients'"
        subtitle="{{ number_format($clients->total()) }} {{ str('client')->plural($clients->total()) }} {{ $showTrashed ? 'in trash' : 'on record' }}">
        <x-slot name="actions">
            @can('admin')
                @if ($showTrashed)
                    <x-button variant="secondary" :href="route('clients.index')" icon="arrow-left">Back to Clients</x-button>
                @else
                    <x-button variant="ghost" :href="route('clients.index', ['view' => 'trash'])" icon="trash">Trash</x-button>
                @endif
            @endcan
            @unless ($showTrashed)
                <x-button :href="route('clients.create')" icon="plus">New Client</x-button>
            @endunless
        </x-slot>
    </x-page-header>

    {{-- Filters --}}
    <form method="GET" action="{{ route('clients.index') }}" class="mb-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        @if ($showTrashed) <input type="hidden" name="view" value="trash"> @endif

        <div class="flex-1">
            <label for="q" class="block text-xs font-medium text-slate-500">Search</label>
            <div class="relative mt-1">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Name, client code, contact no., email..."
                    class="block w-full rounded-lg border-slate-300 pl-9 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
        </div>

        <div class="sm:w-48">
            <label for="type" class="block text-xs font-medium text-slate-500">Type</label>
            <select id="type" name="type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">All types</option>
                @foreach (\App\Enums\ClientType::options() as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="sm:w-40">
            <label for="sort" class="block text-xs font-medium text-slate-500">Sort by</label>
            <select id="sort" name="sort" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="name" @selected($sort === 'name')>Name (A–Z)</option>
                <option value="newest" @selected($sort === 'newest')>Newest first</option>
            </select>
        </div>

        <div class="flex gap-2">
            <x-button>Filter</x-button>
            @if (request()->hasAny(['q', 'type', 'sort']))
                <x-button variant="ghost" :href="route('clients.index', $showTrashed ? ['view' => 'trash'] : [])">Reset</x-button>
            @endif
        </div>
    </form>

    @if ($clients->isEmpty())
        @if (request()->hasAny(['q', 'type']))
            <x-empty-state icon="magnifying-glass" title="No matching clients" message="Try a different name, code, or contact number." />
        @elseif ($showTrashed)
            <x-empty-state icon="trash" title="Trash is empty" message="Deleted clients will appear here and can be restored." />
        @else
            <x-empty-state icon="users" title="No clients yet" message="Add the firm's first client to get started.">
                <x-button :href="route('clients.create')" icon="plus">New Client</x-button>
            </x-empty-state>
        @endif
    @else
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Code</th>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3 text-center">Cases</th>
                            <th class="px-5 py-3 text-center">Documents</th>
                            <th class="px-5 py-3">{{ $showTrashed ? 'Deleted' : 'Added' }}</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($clients as $client)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $client->client_type === \App\Enums\ClientType::Company ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-500' }}">
                                            <x-icon :name="$client->client_type === \App\Enums\ClientType::Company ? 'briefcase' : 'user'" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0">
                                            @if ($showTrashed)
                                                <p class="font-medium text-slate-900">{{ $client->display_name }}</p>
                                            @else
                                                <a href="{{ route('clients.show', $client) }}" class="font-medium text-slate-900 hover:underline">{{ $client->display_name }}</a>
                                            @endif
                                            <p class="truncate text-xs text-slate-500">{{ $client->address ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">{{ $client->client_code }}</td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    <p class="text-slate-700">{{ $client->contact_number }}</p>
                                    <p class="text-xs text-slate-500">{{ $client->email ?? '' }}</p>
                                </td>
                                <td class="px-5 py-3 text-center text-slate-700">{{ $client->cases_count }}</td>
                                <td class="px-5 py-3 text-center text-slate-700">{{ $client->documents_count }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-500">
                                    {{ ($showTrashed ? $client->deleted_at : $client->created_at)->format('M d, Y') }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    @if ($showTrashed)
                                        @can('restore', $client)
                                            <form method="POST" action="{{ route('clients.restore', $client) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-button variant="secondary" size="sm">Restore</x-button>
                                            </form>
                                        @endcan
                                    @else
                                        <x-button variant="ghost" size="sm" :href="route('clients.show', $client)" icon="eye">View</x-button>
                                        <x-button variant="ghost" size="sm" :href="route('clients.edit', $client)" icon="pencil-square">Edit</x-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-4">{{ $clients->links() }}</div>
    @endif
</x-app-layout>
