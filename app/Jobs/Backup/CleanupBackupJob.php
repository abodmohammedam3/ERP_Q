<?php

namespace App\Jobs\Backup;

use App\Services\Backup\BackupRetention;
use App\Services\Backup\BackupStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        BackupRetention $retention,
        BackupStatusService $status,
    ): void {
        try {
            $deleted    = $retention->cleanup(config('backup.path'));
            $opsDeleted = $status->cleanup();

            Log::info('[Backup] Cleanup done', [
                'files'      => count($deleted),
                'operations' => $opsDeleted,
            ]);

        } catch (\Throwable $e) {
            Log::error('[Backup] Cleanup failed', ['error' => $e->getMessage()]);
        }
    }
}
