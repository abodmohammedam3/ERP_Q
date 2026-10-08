<?php

namespace App\Services\Backup;

use InvalidArgumentException;
use RuntimeException;

class BackupValidator
{
    /**
     * Validate a backup file for existence, non-zero size, and SQL content.
     */
    public function validateBackupFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Backup file does not exist: {$filePath}");
        }

        $size = @filesize($filePath);
        if ($size === false || $size <= 0) {
            throw new InvalidArgumentException("Backup file is empty: {$filePath}");
        }

        $content = @file_get_contents($filePath, false, null, 0, 500000);
        if ($content === false || stripos($content, 'CREATE TABLE') === false) {
            throw new InvalidArgumentException("Backup file is invalid: does not contain CREATE TABLE statement.");
        }
    }

    /**
     * Validate binary executable path.
     */
    public function validateBinaryPath(?string $path, string $binaryName): void
    {
        if ($path === null || trim($path) === '') {
            throw new RuntimeException("{$binaryName} binary path is missing or empty in configuration. Check BACKUP_" . strtoupper($binaryName) . "_PATH in .env");
        }

        if (!file_exists($path)) {
            throw new RuntimeException("{$binaryName} binary not found at path: {$path}");
        }

        if (!is_executable($path)) {
            throw new RuntimeException("{$binaryName} binary is not executable at path: {$path}");
        }
    }

    /**
     * Validate backup storage directory existence and write permission.
     */
    public function validateDirectoryWritable(string $directoryPath): void
    {
        if (!file_exists($directoryPath)) {
            if (!@mkdir($directoryPath, 0777, true) && !is_dir($directoryPath)) {
                throw new RuntimeException("Backup directory missing and could not be created: {$directoryPath}");
            }
        }

        if (!is_writable($directoryPath)) {
            throw new RuntimeException("Backup directory is not writable: {$directoryPath}");
        }
    }
}

