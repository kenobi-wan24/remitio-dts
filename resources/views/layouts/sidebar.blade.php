<div class="flex h-full flex-col bg-slate-900">
    {{-- Brand --}}
    <a href="{{ route('dashboard') }}" class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500 text-slate-900">
            <x-application-logo class="h-5 w-5" />
        </span>
        <span class="leading-tight">
            <span class="block text-sm font-semibold text-white">Remitio &amp; Remitio</span>
            <span class="block text-xs text-slate-400">Document Tracking System</span>
        </span>
    </a>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        <div class="space-y-1">
            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">Dashboard</x-sidebar-link>
            <x-sidebar-link :href="route('documents.index')" :active="request()->routeIs('documents.*')" icon="document-text">Documents</x-sidebar-link>
            <x-sidebar-link :href="route('cases.index')" :active="request()->routeIs('cases.*')" icon="briefcase">Cases</x-sidebar-link>
            <x-sidebar-link :href="route('clients.index')" :active="request()->routeIs('clients.*')" icon="users">Clients</x-sidebar-link>
        </div>

        <div>
            <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Tools</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('search')" :active="request()->routeIs('search')" icon="magnifying-glass">Search</x-sidebar-link>
                <x-sidebar-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="chart-bar">Reports</x-sidebar-link>
            </div>
        </div>

        @can('admin')
            <div>
                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Administration</p>
                <div class="mt-2 space-y-1">
                    <x-sidebar-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="user-circle">Users</x-sidebar-link>
                    <x-sidebar-link :href="route('admin.document-types.index')" :active="request()->routeIs('admin.document-types.*')" icon="tag">Document Types</x-sidebar-link>
                    <x-sidebar-link :href="route('admin.activity-logs.index')" :active="request()->routeIs('admin.activity-logs.*')" icon="clock">Activity Log</x-sidebar-link>
                </div>
            </div>
        @endcan
    </nav>

    <div class="border-t border-white/10 px-5 py-4 text-xs text-slate-500">
        Davao City · Est. 2008
    </div>
</div>
