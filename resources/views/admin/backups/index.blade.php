<x-app-layout title="Backup & Restore">
    <x-page-header title="Backup & Restore"
        subtitle="A backup is one .zip file with all records and uploaded files. Keep copies outside this computer." />

    @unless ($zipAvailable)
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-800">
            <x-icon name="x-circle" class="h-5 w-5 shrink-0" />
            <div>
                <p class="font-semibold">Backups are unavailable: the PHP "zip" extension is turned off.</p>
                <p class="mt-1">Open <code class="rounded bg-white px-1">C:\xampp\php\php.ini</code>, find <code class="rounded bg-white px-1">;extension=zip</code>, remove the <code>;</code>, save, and restart <code class="rounded bg-white px-1">php artisan serve</code>.</p>
            </div>
        </div>
    @endunless

    @if ($zipAvailable && ($daysSinceLast === null || $daysSinceLast >= 7))
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900">
            <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-600" />
            <p>{{ $daysSinceLast === null ? 'No backup has been made yet.' : "The last backup is {$daysSinceLast} days old." }} Create one now and copy it to a USB drive or cloud storage.</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Create --}}
            <x-card title="Create a Backup">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-600">Saves every client, case, document, movement, file, user account and activity log entry into one dated file.</p>
                    <form method="POST" action="{{ route('admin.backups.store') }}">
                        @csrf
                        <x-button icon="archive-box" :disabled="! $zipAvailable">Back Up Now</x-button>
                    </form>
                </div>
            </x-card>

            {{-- List --}}
            <x-card title="Backups on this Computer ({{ count($backups) }})" :padding="false">
                @if (empty($backups))
                    <x-empty-state class="m-5" icon="archive-box" title="No backups yet" message="Create the first backup above." />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Created</th>
                                    <th class="px-5 py-3">Type</th>
                                    <th class="px-5 py-3">Size</th>
                                    <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($backups as $backup)
                                    @php
                                        $mb = $backup['size'] / 1048576;
                                        $labels = ['manual' => ['Manual', 'blue'], 'auto' => ['Automatic', 'green'], 'before-restore' => ['Safety copy (before restore)', 'amber']];
                                    @endphp
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-slate-900">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['time'])->timezone(config('app.timezone'))->format('M d, Y h:i A') }}</p>
                                            <p class="font-mono text-xs text-slate-500">{{ $backup['name'] }}</p>
                                        </td>
                                        <td class="px-5 py-3"><x-status-badge :color="$labels[$backup['label']][1]" :label="$labels[$backup['label']][0]" /></td>
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $mb >= 1 ? number_format($mb, 1).' MB' : number_format($backup['size'] / 1024, 1).' KB' }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <x-button variant="ghost" size="sm" icon="arrow-down-tray" :href="route('admin.backups.download', $backup['name'])">Download</x-button>
                                                <x-confirm-delete :action="route('admin.backups.destroy', $backup['name'])" label=""
                                                    title="Delete this backup?" message="{{ $backup['name'] }} will be removed from this computer. Copies you downloaded are not affected." />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            {{-- Automatic backups --}}
            <x-card title="Automatic Daily Backups (optional)">
                <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                    <li>Open <strong>Task Scheduler</strong> on this computer → <em>Create Basic Task</em> → name it "Remitio DTS Backup".</li>
                    <li>Trigger: <strong>Daily</strong>, at a time the computer is on (e.g. 5:00 PM).</li>
                    <li>Action: <em>Start a program</em> → choose <code class="rounded bg-slate-100 px-1">backup-dts.bat</code> in the system folder.</li>
                </ol>
                <p class="mt-3 text-xs text-slate-500">Keeps the 10 most recent automatic backups. Manual backups are never removed automatically.</p>
            </x-card>
        </div>

        {{-- Restore --}}
        <x-card title="Restore a Backup" class="self-start border-red-200">
            <div class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-800">
                <x-icon name="exclamation-triangle" class="h-4 w-4 shrink-0" />
                <p>Restoring <strong>replaces all current data and files</strong> with the backup's contents. A safety copy of the current data is made first, and everyone is signed out.</p>
            </div>

            <form method="POST" action="{{ route('admin.backups.restore') }}" enctype="multipart/form-data" class="space-y-4"
                x-data="{ source: 'list' }">
                @csrf
                <div class="flex gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="radio" value="list" x-model="source" class="text-slate-800 focus:ring-slate-500"> From the list</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" value="upload" x-model="source" class="text-slate-800 focus:ring-slate-500"> Upload a file</label>
                </div>

                <div x-show="source === 'list'">
                    <x-form.select name="backup" label="Backup" placeholder="Select a backup"
                        :options="collect($backups)->mapWithKeys(fn ($b) => [$b['name'] => \Illuminate\Support\Carbon::createFromTimestamp($b['time'])->timezone(config('app.timezone'))->format('M d, Y h:i A').' · '.$b['label']])"
                        x-bind:disabled="source !== 'list'" />
                </div>

                <div x-show="source === 'upload'" x-cloak>
                    <label class="block text-sm font-medium text-slate-700">Backup file (.zip)</label>
                    <input type="file" name="upload" accept=".zip" x-bind:disabled="source !== 'upload'"
                        class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                    @error('upload') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500">Large backups may exceed PHP's post_max_size (64M).</p>
                </div>

                <x-form.input name="confirm" label="Type RESTORE to confirm" autocomplete="off" required />
                <x-form.input name="password" type="password" label="Your password" autocomplete="current-password" required />

                <x-button variant="danger" class="w-full" icon="arrow-path" :disabled="! $zipAvailable">Restore Backup</x-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
