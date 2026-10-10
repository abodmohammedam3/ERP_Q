<?php

namespace App\Jobs\Backup;

use App\Services\Backup\BackupBinaryDetector;
use App\Services\Backup\BackupProcessRunner;
use App\Services\Backup\BackupStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExportBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 1;

    public function __construct(
        public string $operationId,
    ) {}

    public function handle(
        BackupStatusService $status,
        BackupProcessRunner $runner,
        BackupBinaryDetector $detector,
    ): void {

        $startedAt = microtime(true);

        try {
            $status->update($this->operationId, 'running', 10, 'جاري التحضير...');

            $binary = $detector->detectMysqldump();

            if (!$binary) {
                throw new \RuntimeException(
                    'لم يتم العثور على mysqldump.exe — اضبط BACKUP_MYSQLDUMP_PATH في .env'
                );
            }

            $status->update($this->operationId, 'running', 20, 'جاري التصدير...');

            $filename  = 'backup_' . now()->format('Y-m-d_His') . '.sql';
            $backupDir = config('backup.path');

            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0777, true);
            }

            $filePath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

            $dbConfig = config('database.connections.' . config('database.default'));

            $runner->runDump($binary, $filePath, $dbConfig, $this->timeout - 60);

            $status->update($this->operationId, 'running', 85, 'جاري التحقق...');

            if (!file_exists($filePath) || filesize($filePath) === 0) {
                throw new \RuntimeException('الملف الناتج فارغ');
            }

            $size = filesize($filePath);

            $status->update(
                $this->operationId,
                'done',
                100,
                'اكتمل التصدير',
                $filePath,
                $size
            );

            $op = $status->find($this->operationId);
            if ($op) {
                $status->logResult($op);
            }

            Log::info('[Backup] Export success', [
                'operation_id' => $this->operationId,
                'size'         => $size,
                'duration'     => round(microtime(true) - $startedAt, 2),
            ]);

        } catch (\Throwable $e) {

            Log::error('[Backup] Export failed', [
                'operation_id' => $this->operationId,
                'error'        => $e->getMessage(),
            ]);

            $status->update(
                $this->operationId,
                'failed',
                0,
                'فشل التصدير',
                null,
                null,
                $e->getMessage()
            );

            $op = $status->find($this->operationId);
            if ($op) {
                $status->logResult($op);
            }

            throw $e;
        }
    }
}
