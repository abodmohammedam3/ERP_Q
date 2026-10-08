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

    /**
     * Create a file with a specific mtime, helper shared by new tests.
     */
    private function createFile(string $fileName, int $mtime): string
    {
        $filePath = $this->tempDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($filePath, "-- Dump: {$fileName}\nCREATE TABLE `t` (`id` int);\n");
        touch($filePath, $mtime);

        return $filePath;
    }

    /**
     * T-0A-1: 8 backup + 5 safety, keep=7/3 → 7+3 remain, 1+2 deleted.
     */
    public function test_cleanup_handles_safety_backups_with_separate_retention(): void
    {
        $baseTime = time() - 7200;
        $backupFiles = [];
        $safetyFiles = [];

        for ($i = 0; $i < 8; $i++) {
            $backupFiles[] = $this->createFile(sprintf('backup_2026-10-07_%02d0000.sql', $i), $baseTime + ($i * 60));
        }
        for ($i = 0; $i < 5; $i++) {
            $safetyFiles[] = $this->createFile(sprintf('safety_backup_2026-10-07_%02d0000.sql', $i), $baseTime + ($i * 60));
        }

        $deleted = $this->retention->cleanup($this->tempDir, 7, 3);

        $remainingBackup = glob($this->tempDir . '/backup_*.sql');
        $remainingSafety = glob($this->tempDir . '/safety_backup_*.sql');

        $this->assertCount(3, $deleted);
        $this->assertCount(7, $remainingBackup);
        $this->assertCount(3, $remainingSafety);

        // Oldest backup ($i=0) and two oldest safety ($i=0,1) deleted
        $this->assertFileDoesNotExist($backupFiles[0]);
        $this->assertFileDoesNotExist($safetyFiles[0]);
        $this->assertFileDoesNotExist($safetyFiles[1]);
    }

    /**
     * T-0A-2: safety_retention_count=0 → keep ALL safety, backup cleaned normally.
     */
    public function test_cleanup_keeps_all_safety_when_retention_is_zero(): void
    {
        $baseTime = time() - 7200;

        for ($i = 0; $i < 8; $i++) {
            $this->createFile(sprintf('backup_2026-10-07_%02d0000.sql', $i), $baseTime + ($i * 60));
        }
        for ($i = 0; $i < 5; $i++) {
            $this->createFile(sprintf('safety_backup_2026-10-07_%02d0000.sql', $i), $baseTime + ($i * 60));
        }

        $deleted = $this->retention->cleanup($this->tempDir, 7, 0);

        $this->assertCount(1, $deleted);
        $this->assertCount(7, glob($this->tempDir . '/backup_*.sql'));
        $this->assertCount(5, glob($this->tempDir . '/safety_backup_*.sql'));
    }

    /**
     * T-0A-3: retention_count=0 → keep ALL backups, safety cleaned normally.
     */
    public function test_cleanup_keeps_all_backups_when_retention_is_zero(): void
    {
        $baseTime = time() - 7200;

        for ($i = 0; $i < 8; $i++) {
            $this->createFile(sprintf('backup_2026-10-07_%02d0000.sql', $i), $baseTime + ($i * 60));
        }
        for ($i = 0; $i < 5; $i++) {
            $this->createFile(sprintf('safety_backup_2026-10-07_%02d0000.sql', $i), $baseTime + ($i * 60));
        }

        $deleted = $this->retention->cleanup($this->tempDir, 0, 3);

        $this->assertCount(2, $deleted);
        $this->assertCount(8, glob($this->tempDir . '/backup_*.sql'));
        $this->assertCount(3, glob($this->tempDir . '/safety_backup_*.sql'));
    }

    /**
     * T-0A-4: ordering by filemtime, NOT filename.
     * backup_2026-10-09 has OLD mtime, backup_2026-10-01 has NEW mtime.
     * keep=1 → backup_2026-10-09 (old mtime) is deleted despite newer name.
     */
    public function test_cleanup_orders_by_filemtime_not_filename(): void
    {
        $oldFile = $this->createFile('backup_2026-10-09_000000.sql', time() - 7200);
        $newFile = $this->createFile('backup_2026-10-01_000000.sql', time());

        $deleted = $this->retention->cleanup($this->tempDir, 1);

        $this->assertCount(1, $deleted);
        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($newFile);
    }

    /**
     * T-0A-5: unrelated files (notes.txt, backup.sql.bak) are untouched.
     */
    public function test_cleanup_ignores_unrelated_files(): void
    {
        $baseTime = time() - 7200;

        for ($i = 0; $i < 10; $i++) {
            $this->createFile(sprintf('backup_2026-10-07_%02d0000.sql', $i), $baseTime + ($i * 60));
        }

        $notes = $this->tempDir . DIRECTORY_SEPARATOR . 'notes.txt';
        file_put_contents($notes, 'unrelated');
        touch($notes, $baseTime);

        $bak = $this->tempDir . DIRECTORY_SEPARATOR . 'backup.sql.bak';
        file_put_contents($bak, 'unrelated');
        touch($bak, $baseTime);

        $deleted = $this->retention->cleanup($this->tempDir, 7);

        $this->assertCount(3, $deleted);
        $this->assertFileExists($notes);
        $this->assertFileExists($bak);
    }

    /**
     * T-0A-6: companion .sha256 files are deleted with their .sql file (BR-0A-9).
     * keep=0 → keep all (BR-0A-5), so use keep=1 with 2 files.
     */
    public function test_cleanup_deletes_companion_sha256_when_present(): void
    {
        $baseTime = time() - 7200;

        $older = $this->createFile('backup_2026-01-01_000000.sql', $baseTime);
        $this->createFile('backup_2026-01-02_000000.sql', $baseTime + 60);

        $shaOlder = $older . '.sha256';
        file_put_contents($shaOlder, 'abc123');
        touch($shaOlder, $baseTime);

        $deleted = $this->retention->cleanup($this->tempDir, 1);

        // glob() returns paths with forward slashes; compare by basename to
        // avoid DIRECTORY_SEPARATOR mismatches on Windows.
        $deletedBasenames = array_map('basename', $deleted);

        $this->assertCount(2, $deleted);
        $this->assertContains(basename($older), $deletedBasenames);
        $this->assertContains(basename($shaOlder), $deletedBasenames);
        $this->assertFileDoesNotExist($older);
        $this->assertFileDoesNotExist($shaOlder);
        $this->assertFileExists($this->tempDir . DIRECTORY_SEPARATOR . 'backup_2026-01-02_000000.sql');
    }
}

