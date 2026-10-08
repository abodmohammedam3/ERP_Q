<?php

namespace Tests\Unit\Backup;

use PHPUnit\Framework\TestCase;
use App\Services\Backup\BackupValidator;
use InvalidArgumentException;
use RuntimeException;

class BackupValidatorTest extends TestCase
{
    protected BackupValidator $validator;
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BackupValidator();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_rejects_non_existent_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $this->validator->validateBackupFile($this->tempDir . '/non_existent.sql');
    }

    public function test_rejects_empty_file(): void
    {
        $filePath = $this->tempDir . '/empty.sql';
        file_put_contents($filePath, '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');

        $this->validator->validateBackupFile($filePath);
    }

    public function test_rejects_file_without_create_table(): void
    {
        $filePath = $this->tempDir . '/no_create.sql';
        file_put_contents($filePath, '-- Just a comment without structure');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not contain CREATE TABLE');

        $this->validator->validateBackupFile($filePath);
    }

    public function test_accepts_valid_file_with_create_table(): void
    {
        $filePath = $this->tempDir . '/valid.sql';
        file_put_contents($filePath, "-- Dump file\nCREATE TABLE `users` (`id` int);\n");

        $this->expectNotToPerformAssertions();
        $this->validator->validateBackupFile($filePath);
    }

    public function test_rejects_missing_binary_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or empty');

        $this->validator->validateBinaryPath('', 'mysqldump');
    }

    public function test_rejects_non_existent_binary_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');

        $this->validator->validateBinaryPath('C:/non_existent_path/mysqldump.exe', 'mysqldump');
    }
}

