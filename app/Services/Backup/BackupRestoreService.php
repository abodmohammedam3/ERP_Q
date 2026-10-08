<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BackupRestoreService
{
    public function __construct(
        protected BackupService $backupService,
        protected BackupProcessRunner $runner,
        protected BackupValidator $validator
    ) {}

    /**
     * Restore database from a backup file following BR-A7 strict order.
     */
    public function restore(string $filePath): bool
    {
        $startTime = microtime(true);

        // Step 1: Validate target file & mysql binary BEFORE acquiring lock
        $this->validator->validateBackupFile($filePath);
        $mysqlPath = config('backup.mysql_path');
        $this->validator->validateBinaryPath($mysqlPath, 'mysql');

        // Step 2: ACQUIRE Cache::lock('backup_restore') — TTL configurable, MUST cover full restore
        $lockTimeout = (int) config('backup.lock_timeout', 3600);
        $lock = Cache::lock('backup_restore', $lockTimeout);

        if (!$lock->get()) {
            $this->backupService->log('restore', 'FAILED: Could not acquire lock', filesize($filePath), 0);
            throw new RuntimeException("Concurrent restore in progress or lock could not be acquired.");
        }

        try {
            // Step 3: Create SAFETY BACKUP -> if fails, RELEASE LOCK + ABORT
            $safetyFileName = 'safety_backup_' . date('Y-m-d_His') . '.sql';
            $this->backupService->createBackup($safetyFileName, false);

            // Step 4: Import via mysql.exe --default-character-set=utf8mb4 < file
            $defaultConn = config('database.default');
            $dbConfig = config("database.connections.{$defaultConn}");
            $timeout = (int) config('backup.timeout', 600);

            $this->runner->runRestore($mysqlPath, $filePath, $dbConfig, $timeout);

            // Step 6: Verify post-restore (table count check via information_schema)
            $databaseName = $dbConfig['database'] ?? '';
            $tableCount = $this->getInformationSchemaTableCount($databaseName);

            if ($tableCount <= 0) {
                throw new RuntimeException("Post-restore verification failed: database has 0 tables in information_schema.");
            }

            $duration = round(microtime(true) - $startTime, 2);
            $size = filesize($filePath);

            $this->backupService->log('restore', 'SUCCESS', $size, $duration, [
                'file' => basename($filePath),
                'tables' => $tableCount,
            ]);

            return true;
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $size = file_exists($filePath) ? filesize($filePath) : 0;
            $this->backupService->log('restore', 'FAILED: ' . $e->getMessage(), $size, $duration);
            throw $e;
        } finally {
            // Step 7: Release lock
            $lock->release();
        }
    }

    /**
     * Query table count from information_schema.tables.
     */
    protected function getInformationSchemaTableCount(string $databaseName): int
    {
        $result = DB::select(
            "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = ?",
            [$databaseName]
        );

        return (int) ($result[0]->table_count ?? 0);
    }
}

