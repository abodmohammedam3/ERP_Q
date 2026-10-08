<?php

namespace App\Console\Commands\Backup;

use Illuminate\Console\Command;
use App\Services\Backup\BackupService;

class BackupListCommand extends Command
{
    protected $signature = 'backup:list';
    protected $description = 'List all existing database backups.';

    public function handle(BackupService $backupService): int
    {
        $backups = $backupService->listBackups();

        if (empty($backups)) {
            $this->info('No backup files found.');
            return Command::SUCCESS;
        }

        $headers = ['Filename', 'Size (Bytes)', 'Created At'];
        $rows = array_map(function ($b) {
            return [
                $b['filename'],
                number_format($b['size']),
                $b['date'],
            ];
        }, $backups);

        $this->table($headers, $rows);
        return Command::SUCCESS;
    }
}

