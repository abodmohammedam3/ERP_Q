<?php

namespace App\Services\Backup;

use Symfony\Component\Process\Process;
use RuntimeException;
use InvalidArgumentException;

class BackupProcessRunner
{
    /**
     * Run mysqldump command to produce a .sql dump file.
     */
    public function runDump(string $binaryPath, string $outputPath, array $dbConfig, int $timeout = 600): void
    {
        if (empty($binaryPath) || !file_exists($binaryPath)) {
            throw new RuntimeException("mysqldump binary path is invalid or missing: {$binaryPath}");
        }

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? 3306);
        $user = $dbConfig['username'] ?? 'root';
        $pass = $dbConfig['password'] ?? '';
        $database = $dbConfig['database'] ?? '';

        if (empty($database)) {
            throw new InvalidArgumentException("Database name is not configured.");
        }

        $command = [
            $binaryPath,
            "--host={$host}",
            "--port={$port}",
            "--user={$user}",
        ];

        if ($pass !== null && $pass !== '') {
            $command[] = "--password={$pass}";
        }

        $command[] = '--single-transaction';
        $command[] = '--routines';
        $command[] = '--triggers';

        // استبعاد الجداول النظامية من النسخة (لا wildcards — خيار لكل جدول)
        // حتى لا يحذف الاستيراد القديم هذه الجداول عبر DROP TABLE في اللقطة
        foreach ((array) config('backup.excluded_tables', []) as $protectedTable) {
            if (is_string($protectedTable) && $protectedTable !== '') {
                $command[] = "--ignore-table={$database}.{$protectedTable}";
            }
        }

        $command[] = $database;

        $handle = @fopen($outputPath, 'w');
        if (!$handle) {
            throw new RuntimeException("Unable to open output file for writing: {$outputPath}");
        }

        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            
            $process->run(function ($type, $buffer) use ($handle) {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });

            if (is_resource($handle)) {
                fclose($handle);
            }

            if (!$process->isSuccessful()) {
                if (file_exists($outputPath)) {
                    @unlink($outputPath);
                }
                throw new RuntimeException("mysqldump process failed: " . trim($process->getErrorOutput()));
            }
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
            throw $e;
        }
    }

    /**
     * Run mysql import command from a .sql dump file.
     */
    public function runRestore(string $binaryPath, string $inputPath, array $dbConfig, int $timeout = 600): void
    {
        if (empty($binaryPath) || !file_exists($binaryPath)) {
            throw new RuntimeException("mysql binary path is invalid or missing: {$binaryPath}");
        }

        if (!file_exists($inputPath)) {
            throw new InvalidArgumentException("Backup import file does not exist: {$inputPath}");
        }

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? 3306);
        $user = $dbConfig['username'] ?? 'root';
        $pass = $dbConfig['password'] ?? '';
        $database = $dbConfig['database'] ?? '';

        if (empty($database)) {
            throw new InvalidArgumentException("Database name is not configured.");
        }

        $command = [
            $binaryPath,
            "--host={$host}",
            "--port={$port}",
            "--user={$user}",
        ];

        if ($pass !== null && $pass !== '') {
            $command[] = "--password={$pass}";
        }

        $command[] = '--default-character-set=utf8mb4';
        $command[] = $database;

        $handle = @fopen($inputPath, 'r');
        if (!$handle) {
            throw new RuntimeException("Unable to open input file for reading: {$inputPath}");
        }

        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->setInput($handle);
            $process->run();

            if (is_resource($handle)) {
                fclose($handle);
            }

            if (!$process->isSuccessful()) {
                throw new RuntimeException("mysql restore process failed: " . trim($process->getErrorOutput()));
            }
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw $e;
        }
    }
}

