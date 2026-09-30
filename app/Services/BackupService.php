<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Backup = ONE .zip file containing:
 *   manifest.json            what's inside, who made it, which migrations it needs
 *   database/<table>.jsonl   every row of every system table (one JSON object per line)
 *   files/documents/...      every uploaded file
 *
 * Pure PHP (no mysqldump path to configure), so it works the same on every PC.
 * Backups are stored privately in storage/app/private/backups.
 */
class BackupService
{
    /** In foreign-key order: parents first. Restore deletes in reverse, inserts in this order. */
    public const TABLES = [
        'users', 'document_types', 'clients', 'legal_cases',
        'documents', 'document_movements', 'document_attachments', 'activity_logs',
    ];

    public const DISK = 'local';

    public const DIR = 'backups';

    public const FILES_DIR = 'documents';

    private const APP = 'Remitio DTS';

    public function ensureZipAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'The PHP "zip" extension is turned off. Open C:\\xampp\\php\\php.ini, remove the ";" before '
                .'"extension=zip", save, then restart "php artisan serve".'
            );
        }
    }

    public static function zipAvailable(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * @param  string  $label  manual | auto | before-restore
     * @return string file name of the new backup
     */
    public function create(?User $user = null, string $label = 'manual'): string
    {
        $this->ensureZipAvailable();

        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(self::DIR);

        $name = 'remitio-dts-backup-'.now()->format('Y-m-d_His').'-'.$label.'.zip';
        $zip = new ZipArchive;

        if ($zip->open($disk->path(self::DIR.'/'.$name), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup file.');
        }

        $temp = [];
        $counts = [];

        foreach (self::TABLES as $table) {
            $path = tempnam(sys_get_temp_dir(), 'dts');
            $handle = fopen($path, 'w');
            $count = 0;

            foreach (DB::table($table)->orderBy('id')->lazy(500) as $row) {
                fwrite($handle, json_encode((array) $row, JSON_UNESCAPED_UNICODE)."\n");
                $count++;
            }

            fclose($handle);
            $zip->addFile($path, "database/{$table}.jsonl");
            $temp[] = $path;
            $counts[$table] = $count;
        }

        $files = $disk->allFiles(self::FILES_DIR);
        foreach ($files as $file) {
            $zip->addFile($disk->path($file), 'files/'.$file);
        }

        $zip->addFromString('manifest.json', json_encode([
            'app' => self::APP,
            'format' => 1,
            'label' => $label,
            'created_at' => now()->toIso8601String(),
            'created_by' => $user?->email,
            'tables' => $counts,
            'files' => count($files),
            'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
        ], JSON_PRETTY_PRINT));

        $zip->close(); // files are read here, so temp files are deleted only afterwards

        foreach ($temp as $path) {
            @unlink($path);
        }

        return $name;
    }

    /** Backups on the server, newest first. */
    public function list(): array
    {
        $disk = Storage::disk(self::DISK);

        return collect($disk->files(self::DIR))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => $disk->size($f),
                'time' => $disk->lastModified($f),
                'label' => $this->labelFromName(basename($f)),
            ])
            ->sortByDesc('time')
            ->values()
            ->all();
    }

    public function path(string $name): string
    {
        $this->assertSafeName($name);

        return Storage::disk(self::DISK)->path(self::DIR.'/'.$name);
    }

    public function exists(string $name): bool
    {
        return $this->isSafeName($name) && Storage::disk(self::DISK)->exists(self::DIR.'/'.$name);
    }

    public function delete(string $name): void
    {
        $this->assertSafeName($name);
        Storage::disk(self::DISK)->delete(self::DIR.'/'.$name);
    }

    /** Keep only the newest $keep backups of a label (used by the scheduled task). */
    public function prune(int $keep, string $label = 'auto'): int
    {
        $old = collect($this->list())->where('label', $label)->slice($keep);
        $old->each(fn ($b) => $this->delete($b['name']));

        return $old->count();
    }

    /**
     * Replaces ALL current data and uploaded files with the backup's contents.
     * A safety backup of the current state is made first.
     *
     * @return array{manifest: array, safety: string}
     */
    public function restore(string $zipPath, ?User $user = null): array
    {
        $this->ensureZipAvailable();

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The file could not be opened as a backup.');
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ($manifest['app'] ?? null) !== self::APP) {
            $zip->close();
            throw new RuntimeException('This file is not a Remitio DTS backup.');
        }

        foreach (self::TABLES as $table) {
            if ($zip->locateName("database/{$table}.jsonl") === false) {
                $zip->close();
                throw new RuntimeException("The backup is incomplete (missing {$table}).");
            }
        }

        $current = DB::table('migrations')->pluck('migration')->all();
        if (array_diff($manifest['migrations'] ?? [], $current)) {
            $zip->close();
            throw new RuntimeException('This backup was made by a newer version of the system. Update the system (git pull + php artisan migrate) first.');
        }

        $safety = $this->create($user, 'before-restore');

        $work = storage_path('app/restore-'.Str::random(8));
        $zip->extractTo($work);
        $zip->close();

        try {
            Schema::disableForeignKeyConstraints();

            DB::transaction(function () use ($work) {
                foreach (array_reverse(self::TABLES) as $table) {
                    DB::table($table)->delete();
                }

                foreach (self::TABLES as $table) {
                    $handle = fopen("{$work}/database/{$table}.jsonl", 'r');
                    $batch = [];

                    while (($line = fgets($handle)) !== false) {
                        if (trim($line) === '') {
                            continue;
                        }
                        $batch[] = json_decode($line, true);
                        if (count($batch) === 200) {
                            DB::table($table)->insert($batch);
                            $batch = [];
                        }
                    }

                    if ($batch) {
                        DB::table($table)->insert($batch);
                    }
                    fclose($handle);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Replace uploaded files
        $disk = Storage::disk(self::DISK);
        $disk->deleteDirectory(self::FILES_DIR);
        if (is_dir("{$work}/files/".self::FILES_DIR)) {
            File::copyDirectory("{$work}/files/".self::FILES_DIR, $disk->path(self::FILES_DIR));
        }

        File::deleteDirectory($work);

        return ['manifest' => $manifest, 'safety' => $safety];
    }

    private function labelFromName(string $name): string
    {
        return match (true) {
            str_contains($name, 'before-restore') => 'before-restore',
            str_contains($name, '-auto') => 'auto',
            default => 'manual',
        };
    }

    private function isSafeName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name) && ! str_contains($name, '..');
    }

    private function assertSafeName(string $name): void
    {
        if (! $this->isSafeName($name)) {
            throw new RuntimeException('Invalid backup file name.');
        }
    }
}
