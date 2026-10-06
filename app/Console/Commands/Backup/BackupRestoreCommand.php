<?php

namespace App\Console\Commands\Backup;

use Illuminate\Console\Command;
use App\Services\Backup\BackupRestoreService;
use Throwable;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore {file : The backup filename or absolute path}';
    protected $description = 'Restore database from a backup file.';

    public function handle(BackupRestoreService $restoreService): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $backupDir = config('backup.path');
            $fullPath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . $file;
            if (file_exists($fullPath)) {
                $file = $fullPath;
            }
        }

        $this->info("Starting database restore from: {$file}...");

        try {
            $restoreService->restore($file);
            $this->info("Database restored successfully from " . basename($file));
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Restore failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

