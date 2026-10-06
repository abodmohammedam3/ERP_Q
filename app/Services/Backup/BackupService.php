<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;
use Throwable;

class BackupService
{
    public function __construct(
        protected BackupProcessRunner $runner,
        protected BackupValidator $validator,
        protected BackupRetention $retention
    ) {}

    /**
     * Perform a manual database backup.
     */
    public function createBackup(?string $customFilename = null, bool $runRetention = true): string
    {
        $backupDir = config('backup.path');
        $this->validator->validateDirectoryWritable($backupDir);

        $mysqldumpPath = config('backup.mysqldump_path');
        $this->validator->validateBinaryPath($mysqldumpPath, 'mysqldump');

        $filename = $customFilename ?? ('backup_' . date('Y-m-d_His') . '.sql');
        $outputPath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        $defaultConn = config('database.default');
        $dbConfig = config("database.connections.{$defaultConn}");

        $startTime = microtime(true);

        try {
            $timeout = (int) config('backup.timeout', 600);
            $this->runner->runDump($mysqldumpPath, $outputPath, $dbConfig, $timeout);

            $duration = round(microtime(true) - $startTime, 2);
            $size = filesize($outputPath);

            $this->validator->validateBackupFile($outputPath);

            if ($runRetention) {
                $this->retention->cleanup($backupDir);
            }

            $this->log('backup', 'SUCCESS', $size, $duration, ['file' => $filename]);

            return $outputPath;
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
            $this->log('backup', 'FAILED: ' . $e->getMessage(), 0, $duration);
            throw $e;
        }
    }

    /**
     * List all database backup files.
     */
    public function listBackups(): array
    {
        $backupDir = config('backup.path');
        if (empty($backupDir) || !is_dir($backupDir)) {
            return [];
        }

        $files = glob(rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . '*.sql') ?: [];

        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $list = [];
        foreach ($files as $file) {
            $list[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        return $list;
    }

    /**
     * Log backup operations to storage/logs/backup.log using daily channel.
     */
    public function log(string $operation, string $result, int $size, float $duration, array $extra = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "Operation: %s | Result: %s | Size: %d bytes | Duration: %.2fs | Timestamp: %s",
            strtoupper($operation),
            $result,
            $size,
            $duration,
            $timestamp
        );

        if (!empty($extra)) {
            $message .= ' | Context: ' . json_encode($extra);
        }

        Log::build([
            'driver' => 'daily',
            'path' => storage_path('logs/backup.log'),
        ])->info($message);
    }
}

