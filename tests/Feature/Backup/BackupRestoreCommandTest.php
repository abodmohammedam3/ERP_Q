<?php

namespace Tests\Feature\Backup;

use Tests\TestCase;
use App\Services\Backup\BackupProcessRunner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

class BackupRestoreCommandTest extends TestCase
{
    protected string $tempDir;
    protected string $mysqlPath;
    protected string $mysqldumpPath;
    protected string $validBackupFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_restore_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        $this->mysqlPath = $this->tempDir . DIRECTORY_SEPARATOR . 'mysql.exe';
        $this->mysqldumpPath = $this->tempDir . DIRECTORY_SEPARATOR . 'mysqldump.exe';

        file_put_contents($this->mysqlPath, 'dummy mysql executable');
        file_put_contents($this->mysqldumpPath, 'dummy mysqldump executable');

        @chmod($this->mysqlPath, 0755);
        @chmod($this->mysqldumpPath, 0755);

        Config::set('backup.path', $this->tempDir);
        Config::set('backup.mysql_path', $this->mysqlPath);
        Config::set('backup.mysqldump_path', $this->mysqldumpPath);

        $this->validBackupFile = $this->tempDir . DIRECTORY_SEPARATOR . 'valid_backup.sql';
        file_put_contents($this->validBackupFile, "-- Backup dump file\nCREATE TABLE `users` (`id` int);\n");
    }

    protected function tearDown(): void
    {
        Cache::lock('backup_restore')->forceRelease();

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

    public function test_restore_rejects_non_existent_file_before_acquiring_lock(): void
    {
        $nonExistent = $this->tempDir . DIRECTORY_SEPARATOR . 'does_not_exist.sql';

        $this->artisan('backup:restore', ['file' => $nonExistent])
            ->assertExitCode(1)
            ->expectsOutputToContain('Restore failed');

        $this->assertTrue(Cache::lock('backup_restore')->get(), 'Lock should not be held prior to fail');
    }

    public function test_restore_rejects_invalid_file_before_acquiring_lock(): void
    {
        $invalidFile = $this->tempDir . DIRECTORY_SEPARATOR . 'invalid.sql';
        file_put_contents($invalidFile, '-- Invalid SQL file without create table');

        $this->artisan('backup:restore', ['file' => $invalidFile])
            ->assertExitCode(1)
            ->expectsOutputToContain('Restore failed');

        $this->assertTrue(Cache::lock('backup_restore')->get(), 'Lock should not be held prior to fail');
    }

    public function test_restore_verifies_lock_acquired_before_safety_backup(): void
    {
        $sequence = [];

        $this->mock(BackupProcessRunner::class, function (MockInterface $mock) use (&$sequence) {
            $mock->shouldReceive('runDump')
                ->once()
                ->andReturnUsing(function ($binaryPath, $outputPath, $dbConfig, $timeout) use (&$sequence) {
                    $sequence[] = 'safety_backup_created';
                    // Verify lock is active (cannot be acquired again) when safety backup runs
                    if (!Cache::lock('backup_restore', 3600)->get()) {
                        $sequence[] = 'lock_is_active';
                    }
                    file_put_contents($outputPath, "-- Safety Dump\nCREATE TABLE `users` (`id` int);\n");
                });

            $mock->shouldReceive('runRestore')
                ->once()
                ->andReturnUsing(function ($binaryPath, $inputPath, $dbConfig, $timeout) use (&$sequence) {
                    $sequence[] = 'restore_executed';
                });
        });

        $this->artisan('backup:restore', ['file' => $this->validBackupFile])
            ->assertExitCode(0)
            ->expectsOutputToContain('Database restored successfully');

        $this->assertContains('safety_backup_created', $sequence);
        $this->assertContains('lock_is_active', $sequence);
        $this->assertContains('restore_executed', $sequence);
    }

    public function test_concurrent_restore_fails_on_lock_before_safety_backup_attempt(): void
    {
        // Pre-acquire lock
        $lock = Cache::lock('backup_restore', 3600);
        $this->assertTrue($lock->get());

        $safetyBackupAttempted = false;

        $this->mock(BackupProcessRunner::class, function (MockInterface $mock) use (&$safetyBackupAttempted) {
            $mock->shouldReceive('runDump')
                ->never()
                ->andReturnUsing(function () use (&$safetyBackupAttempted) {
                    $safetyBackupAttempted = true;
                });
        });

        $this->artisan('backup:restore', ['file' => $this->validBackupFile])
            ->assertExitCode(1)
            ->expectsOutputToContain('Concurrent restore in progress or lock could not be acquired');

        $this->assertFalse($safetyBackupAttempted);
    }

    public function test_restore_fails_fast_when_mysql_path_is_missing(): void
    {
        Config::set('backup.mysql_path', '');

        $this->artisan('backup:restore', ['file' => $this->validBackupFile])
            ->assertExitCode(1)
            ->expectsOutputToContain('mysql binary path is missing or empty');
    }
}

