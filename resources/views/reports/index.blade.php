@php use App\Enums\DocumentStatus; use App\Enums\MovementAction; @endphp

<x-app-layout title="Reports">
    <x-page-header title="Reports"
        subtitle="Reports open in a new tab. Use Print → Save as PDF for a PDF copy, or Download CSV to open in Excel." />

    @php
        $input = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500';
        $label = 'block text-xs font-medium text-slate-500';
        $monthStart = today()->startOfMonth()->toDateString();
        $today = today()->toDateString();
    @endphp

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- 1. Documents --}}
        <x-card title="Documents Report">
            <p class="-mt-1 mb-4 text-sm text-slate-500">Documents received in a period, with status, holder, location and due date.</p>
            <form method="GET" action="{{ route('reports.documents') }}" target="_blank" class="grid gap-3 sm:grid-cols-2">
                <div><label class="{{ $label }}">Received from</label><input type="date" name="from" value="{{ $monthStart }}" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">To</label><input type="date" name="to" value="{{ $today }}" class="{{ $input }}"></div>
                <div>
                    <label class="{{ $label }}">Status</label>
                    <select name="status" class="{{ $input }}">
                        <option value="">All statuses</option>
                        @foreach (DocumentStatus::options() as $value => $text) <option value="{{ $value }}">{{ $text }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Type</label>
                    <select name="type" class="{{ $input }}">
                        <option value="">All types</option>
                        @foreach ($documentTypes as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Currently with</label>
                    <select name="holder" class="{{ $input }}">
                        <option value="">Anyone</option>
                        @foreach ($users as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Show</label>
                    <select name="due" class="{{ $input }}">
                        <option value="">All documents</option>
                        <option value="open">In process only</option>
                        <option value="overdue">Overdue only</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <x-button variant="secondary" name="format" value="csv" icon="arrow-down-tray">CSV</x-button>
                    <x-button icon="document-text">View / Print</x-button>
                </div>
            </form>
        </x-card>

        {{-- 2. Movements --}}
        <x-card title="Document Movements Report">
            <p class="-mt-1 mb-4 text-sm text-slate-500">Every hand-off, filing, release and archive in a period, with who logged it.</p>
            <form method="GET" action="{{ route('reports.movements') }}" target="_blank" class="grid gap-3 sm:grid-cols-2">
                <div><label class="{{ $label }}">From</label><input type="date" name="from" value="{{ $monthStart }}" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">To</label><input type="date" name="to" value="{{ $today }}" class="{{ $input }}"></div>
                <div>
                    <label class="{{ $label }}">Action</label>
                    <select name="action" class="{{ $input }}">
                        <option value="">All actions</option>
                        @foreach (MovementAction::options() as $value => $text) <option value="{{ $value }}">{{ $text }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Logged by</label>
                    <select name="actor" class="{{ $input }}">
                        <option value="">Anyone</option>
                        @foreach ($users as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <x-button variant="secondary" name="format" value="csv" icon="arrow-down-tray">CSV</x-button>
                    <x-button icon="document-text">View / Print</x-button>
                </div>
            </form>
        </x-card>

        {{-- 3. Notarial --}}
        <x-card title="Notarial Register Listing">
            <p class="-mt-1 mb-4 text-sm text-slate-500">Notarized documents in register order (Book → Page → Doc. No.). A reference list to help prepare the monthly report; it does not replace the official Notarial Register.</p>
            <form method="GET" action="{{ route('reports.notarial') }}" target="_blank" class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="{{ $label }}">Series (year)</label>
                    <select name="series" class="{{ $input }}">
                        @foreach ($seriesOptions as $year) <option value="{{ $year }}">{{ $year }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Month</label>
                    <select name="month" class="{{ $input }}">
                        <option value="">All months</option>
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}" @selected($m === now()->month)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="{{ $label }}">Book No.</label><input type="text" name="book" maxlength="10" placeholder="All" class="{{ $input }}"></div>
                <div class="flex justify-end gap-2 sm:col-span-3">
                    <x-button variant="secondary" name="format" value="csv" icon="arrow-down-tray">CSV</x-button>
                    <x-button icon="document-text">View / Print</x-button>
                </div>
            </form>
        </x-card>

        {{-- 4. Client summary --}}
        <x-card title="Client Case Summary">
            <p class="-mt-1 mb-4 text-sm text-slate-500">One client's cases and documents with their current status. Useful when a client asks for an update.</p>
            <form method="GET" action="{{ route('reports.client-summary') }}" target="_blank" class="space-y-3">
                <x-form.searchable-select name="client_id" label="Client" :options="$clients" placeholder="Search and select a client" required />
                <div class="flex justify-end">
                    <x-button icon="document-text">View / Print</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
