<?php

namespace Tests\Unit\Backup;

use PHPUnit\Framework\TestCase;
use App\Services\Backup\BackupRetention;

class BackupRetentionTest extends TestCase
{
    protected BackupRetention $retention;
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->retention = new BackupRetention();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_retention_' . uniqid();
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

    public function test_cleanup_deletes_oldest_files_beyond_retention_limit(): void
    {
        $createdFiles = [];
        $baseTime = time() - 3600;

        // Create 10 files with staggered mtimes
        for ($i = 0; $i < 10; $i++) {
            $fileName = sprintf('backup_2026-10-07_%02d0000.sql', $i);
            $filePath = $this->tempDir . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($filePath, "-- Dump file {$i}\nCREATE TABLE `t{$i}` (`id` int);\n");
            
            // Set modification time: $i = 0 is oldest, $i = 9 is newest
            touch($filePath, $baseTime + ($i * 60));
            $createdFiles[] = $filePath;
        }

        $this->assertCount(10, glob($this->tempDir . '/backup_*.sql'));

        // Run cleanup with retention limit = 7
        $deleted = $this->retention->cleanup($this->tempDir, 7);

        $remainingFiles = glob($this->tempDir . '/backup_*.sql');

        $this->assertCount(3, $deleted);
        $this->assertCount(7, $remainingFiles);

        // Verify the 3 oldest files ($i = 0, 1, 2) were deleted
        $this->assertFileDoesNotExist($createdFiles[0]);
        $this->assertFileDoesNotExist($createdFiles[1]);
        $this->assertFileDoesNotExist($createdFiles[2]);

        // Verify the 7 newest files ($i = 3..9) remain
        for ($i = 3; $i < 10; $i++) {
            $this->assertFileExists($createdFiles[$i]);
        }
    }
}

