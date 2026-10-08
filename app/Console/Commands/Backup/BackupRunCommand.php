<?php

namespace App\Console\Commands\Backup;

use Illuminate\Console\Command;
use App\Services\Backup\BackupService;
use Throwable;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run';
    protected $description = 'Run manual database backup.';

    public function handle(BackupService $backupService): int
    {
        $this->info('Starting database backup...');

        try {
            $filePath = $backupService->createBackup();
            $filename = basename($filePath);
            $size = filesize($filePath);

            $this->info("Database backup created successfully: {$filename} ({$size} bytes)");
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Backup failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

