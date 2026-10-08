<?php

namespace App\Services\Backup;

class BackupRetention
{
    /**
     * Delete backups beyond the retention limit N.
     *
     * Manages two independent groups:
     *  - Group A: backup_*.sql            (retentionCount)
     *  - Group B: safety_backup_*.sql     (safetyRetentionCount)
     *
     * Returns flat array of deleted file paths.
     */
    public function cleanup(?string $directory = null, ?int $retentionCount = null, ?int $safetyRetentionCount = null): array
    {
        $directory = $directory ?? (string) $this->configValue('backup.path', '');

        // Config is resolved only when the argument is null AND a config
        // repository is actually bound, so this class stays usable in
        // plain-PHPUnit tests without a Laravel container.
        $retentionCount = $retentionCount ?? (int) $this->configValue('backup.retention_count', 7);
        $safetyRetentionCount = $safetyRetentionCount ?? (int) $this->configValue('backup.safety_retention_count', 3);

        if (empty($directory) || !is_dir($directory)) {
            return [];
        }

        // Get all backup .sql files matching backup_*.sql pattern
        // (glob pattern is literal, so backup_*.sql does NOT match safety_backup_*.sql)
        $backupFiles = glob($directory . '/backup_*.sql', GLOB_NOSORT) ?: [];
        $safetyFiles = glob($directory . '/safety_backup_*.sql', GLOB_NOSORT) ?: [];

        // BEHAVIOR CHANGE (intentional): previously, a retention value of 0
        // caused array_slice($files, 0) to DELETE ALL files in the group.
        // As of BR-0A-5, a retention value of 0 means KEEP ALL files of that
        // group (delete nothing). This is a deliberate, documented change.
        $deletedFiles = [];

        if ($retentionCount > 0) {
            $deletedFiles = array_merge(
                $deletedFiles,
                $this->deleteBeyondRetention($backupFiles, $retentionCount)
            );
        }

        if ($safetyRetentionCount > 0) {
            $deletedFiles = array_merge(
                $deletedFiles,
                $this->deleteBeyondRetention($safetyFiles, $safetyRetentionCount)
            );
        }

        return $deletedFiles;
    }

    /**
     * Read a config value only when a config repository is bound,
     * falling back to the default in container-less contexts (plain PHPUnit).
     */
    private function configValue(string $key, mixed $default): mixed
    {
        if (function_exists('app') && app()->bound('config')) {
            return config($key, $default);
        }

        return $default;
    }

    /**
     * Delete files beyond the retention limit, ordered by filemtime (newest kept).
     * Also deletes companion <filename>.sha256 files when present (BR-0A-9).
     *
     * @param  array<int, string>  $files
     * @return array<int, string> Deleted file paths
     */
    private function deleteBeyondRetention(array $files, int $keep): array
    {
        if (count($files) <= $keep) {
            return [];
        }

        // Sort by modification time descending (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        // Files beyond retention limit to delete
        $filesToDelete = array_slice($files, $keep);
        $deletedFiles = [];

        foreach ($filesToDelete as $file) {
            if (@unlink($file)) {
                $deletedFiles[] = $file;

                // Delete companion checksum file if present (BR-0A-9)
                $companion = $file . '.sha256';
                if (file_exists($companion) && @unlink($companion)) {
                    $deletedFiles[] = $companion;
                }
            }
        }

        return $deletedFiles;
    }
}

