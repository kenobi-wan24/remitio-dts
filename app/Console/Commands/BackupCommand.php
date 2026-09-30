<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * php artisan dts:backup --keep=10
 * Run by Windows Task Scheduler (backup-dts.bat) for automatic backups.
 */
class BackupCommand extends Command
{
    protected $signature = 'dts:backup {--keep=10 : How many automatic backups to keep}';

    protected $description = 'Create an automatic backup of the database and uploaded files';

    public function handle(BackupService $backups): int
    {
        try {
            $name = $backups->create(null, 'auto');
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $removed = $backups->prune(max(1, (int) $this->option('keep')));

        $this->info("Backup created: {$name}".($removed ? " ({$removed} old automatic backup(s) removed)" : ''));

        return self::SUCCESS;
    }
}
