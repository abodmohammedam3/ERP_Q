<?php

namespace Tests\Feature\Backup;

use Tests\TestCase;
use App\Services\Backup\BackupProcessRunner;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

class BackupRunCommandTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_run_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        Config::set('backup.path', $this->tempDir);
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

    public function test_backup_run_creates_backup_file_with_mocked_process_runner(): void
    {
        $mysqldumpPath = $this->tempDir . DIRECTORY_SEPARATOR . 'mysqldump.exe';
        file_put_contents($mysqldumpPath, 'dummy executable');
        @chmod($mysqldumpPath, 0755);

        Config::set('backup.mysqldump_path', $mysqldumpPath);

        $this->mock(BackupProcessRunner::class, function (MockInterface $mock) {
            $mock->shouldReceive('runDump')
                ->once()
                ->andReturnUsing(function ($binaryPath, $outputPath, $dbConfig, $timeout) {
                    file_put_contents($outputPath, "-- Dummy Dump\nCREATE TABLE `users` (`id` int);\n");
                });
        });

        $this->artisan('backup:run')
            ->assertExitCode(0)
            ->expectsOutputToContain('Database backup created successfully');

        $files = glob($this->tempDir . '/backup_*.sql');
        $this->assertCount(1, $files);
        $this->assertFileExists($files[0]);
    }

    public function test_backup_run_fails_fast_when_mysqldump_path_is_missing(): void
    {
        Config::set('backup.mysqldump_path', '');

        $this->artisan('backup:run')
            ->assertExitCode(1)
            ->expectsOutputToContain('Backup failed');
    }
}

