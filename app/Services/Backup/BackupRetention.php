<?php

namespace App\Services\Backup;

class BackupRetention
{
    /**
     * Delete backups beyond the retention limit N.
     * Returns array of deleted file paths.
     */
    public function cleanup(?string $directory = null, ?int $retentionCount = null): array
    {
        $directory = $directory ?? config('backup.path');
        $retentionCount = $retentionCount ?? (int) config('backup.retention_count', 7);

        if (empty($directory) || !is_dir($directory)) {
            return [];
        }

        // Get all backup .sql files matching backup_*.sql pattern
        $files = glob($directory . '/backup_*.sql') ?: [];

        // Sort files by modification time descending (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        if (count($files) <= $retentionCount) {
            return [];
        }

        // Files beyond retention limit to delete
        $filesToDelete = array_slice($files, $retentionCount);
        $deletedFiles = [];

        foreach ($filesToDelete as $file) {
            if (@unlink($file)) {
                $deletedFiles[] = $file;
            }
        }

        return $deletedFiles;
    }
}

