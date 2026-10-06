<?php

namespace App\Console\Commands\Backup;

use Illuminate\Console\Command;
use App\Services\Backup\BackupRetention;
use Throwable;

class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup {--keep= : Number of backups to retain}';
    protected $description = 'Cleanup old database backups beyond retention limit.';

    public function handle(BackupRetention $retention): int
    {
        $keepOption = $this->option('keep');
        $keep = $keepOption !== null ? (int) $keepOption : (int) config('backup.retention_count', 7);

        $this->info("Cleaning up old backups (retention limit: {$keep})...");

        try {
            $deleted = $retention->cleanup(config('backup.path'), $keep);

            if (empty($deleted)) {
                $this->info('No old backups to clean up.');
            } else {
                $this->info(sprintf('Successfully deleted %d old backup file(s):', count($deleted)));
                foreach ($deleted as $file) {
                    $this->line('- ' . basename($file));
                }
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Cleanup failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

