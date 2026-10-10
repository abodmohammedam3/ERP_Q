<?php

namespace App\Jobs\Backup;

use App\Services\AccountBalanceService;
use App\Services\Backup\BackupBinaryDetector;
use App\Services\Backup\BackupProcessRunner;
use App\Services\Backup\BackupStatusService;
use App\Services\ChartAccountScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries   = 1;

    /**
     * جداول نظامية لا تُلمس إطلاقاً.
     */
    private const EXCLUDED_TABLES = [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
        'backup_logs',
        'backup_operations',
    ];

    public function __construct(
        public string $operationId,
        public string $uploadedFilePath,
    ) {}

    public function handle(
        BackupStatusService $status,
        BackupProcessRunner $runner,
        BackupBinaryDetector $detector,
    ): void {

        $safetyBackupPath = null;
        $appWasDown = false;

        try {
            // 1) نسخة أمان إجبارية
            $status->update($this->operationId, 'running', 5, 'جاري إنشاء نسخة أمان...');

            $safetyBackupPath = $this->createSafetyBackup($runner, $detector);

            if (!$safetyBackupPath) {
                throw new \RuntimeException('فشل إنشاء النسخة الآمنة — تم الإلغاء');
            }

            // 2) وضع الصيانة
            $status->update($this->operationId, 'running', 15, 'جاري تفعيل الصيانة...');

            try {
                Artisan::call('down', ['--secret' => 'backup-restore-in-progress']);
                $appWasDown = true;
            } catch (\Throwable $e) {
                Log::warning('[Backup] Maintenance mode failed', ['error' => $e->getMessage()]);
            }

            // 3) حذف البيانات
            $status->update($this->operationId, 'running', 25, 'جاري حذف البيانات الحالية...');

            $this->truncateData();

            // 4) الاستيراد
            $status->update($this->operationId, 'running', 40, 'جاري الاستيراد...');

            $mysqlBinary = $detector->detectMysql();

            if (!$mysqlBinary) {
                throw new \RuntimeException('لم يتم العثور على mysql.exe');
            }

            $dbConfig = config('database.connections.' . config('database.default'));

            $runner->runRestore($mysqlBinary, $this->uploadedFilePath, $dbConfig, 1800);

            // 5) التحقق
            $status->update($this->operationId, 'running', 75, 'جاري التحقق...');

            $tableCount = $this->verifyImport();

            if ($tableCount <= 0) {
                throw new \RuntimeException('فشل التحقق — لا توجد جداول');
            }

            // 6) رفع الصيانة
            if ($appWasDown) {
                try { Artisan::call('up'); $appWasDown = false; } catch (\Throwable $e) {}
            }

            // 7) إعادة حساب الأرصدة
            $status->update($this->operationId, 'running', 90, 'جاري إعادة حساب الأرصدة...');

            try {
                AccountBalanceService::recalculateAll();
            } catch (\Throwable $e) {
                Log::warning('[Backup] Recalculate failed', ['error' => $e->getMessage()]);
            }

            // 8) تنظيف الكاش
            $status->update($this->operationId, 'running', 95, 'جاري تنظيف الكاش...');

            Cache::flush();

            try { ChartAccountScope::flush(); } catch (\Throwable $e) {}

            // ✅ الاكتمال
            $status->update(
                $this->operationId,
                'done',
                100,
                'اكتمل الاستيراد بنجاح'
            );

            $op = $status->find($this->operationId);
            if ($op) {
                $status->logResult($op);
            }

            @unlink($this->uploadedFilePath);

            Log::info('[Backup] Import success', [
                'operation_id' => $this->operationId,
                'tables'       => $tableCount,
            ]);

        } catch (\Throwable $e) {

            Log::error('[Backup] Import failed', [
                'operation_id' => $this->operationId,
                'error'        => $e->getMessage(),
            ]);

            // 🔴 محاولة استعادة تلقائية
            if ($safetyBackupPath && file_exists($safetyBackupPath)) {

                $status->update($this->operationId, 'running', 60, 'فشل — جاري الاستعادة...');

                try {
                    $mysqlBinary = $detector->detectMysql();
                    $dbConfig    = config('database.connections.' . config('database.default'));

                    $this->truncateData();

                    if ($mysqlBinary) {
                        $runner->runRestore($mysqlBinary, $safetyBackupPath, $dbConfig, 900);
                    }

                    if ($appWasDown) { try { Artisan::call('up'); } catch (\Throwable $ee) {} }

                    Cache::flush();

                    $status->update(
                        $this->operationId,
                        'failed',
                        100,
                        'فشل الاستيراد — تم استعادة بياناتك السابقة',
                        null,
                        null,
                        $e->getMessage()
                    );

                } catch (\Throwable $restoreErr) {

                    Log::emergency('[Backup] SAFETY RESTORE FAILED', [
                        'original' => $e->getMessage(),
                        'restore'  => $restoreErr->getMessage(),
                    ]);

                    if ($appWasDown) { try { Artisan::call('up'); } catch (\Throwable $ee) {} }

                    $status->update(
                        $this->operationId,
                        'failed',
                        100,
                        'فشل الاستيراد وفشلت الاستعادة — راجع السجل',
                        null,
                        null,
                        "خطأ الاستيراد: {$e->getMessage()} | خطأ الاستعادة: {$restoreErr->getMessage()}"
                    );
                }

            } else {

                if ($appWasDown) { try { Artisan::call('up'); } catch (\Throwable $ee) {} }

                $status->update(
                    $this->operationId,
                    'failed',
                    0,
                    'فشل الاستيراد',
                    null,
                    null,
                    $e->getMessage()
                );
            }

            $op = $status->find($this->operationId);
            if ($op) {
                $status->logResult($op);
            }

            @unlink($this->uploadedFilePath);
        }
    }

    private function createSafetyBackup(
        BackupProcessRunner $runner,
        BackupBinaryDetector $detector
    ): ?string {
        try {
            $binary = $detector->detectMysqldump();
            if (!$binary) return null;

            $dir = config('backup.path');
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            $path = $dir . DIRECTORY_SEPARATOR . 'safety_' . now()->format('Y-m-d_His') . '.sql';

            $dbConfig = config('database.connections.' . config('database.default'));

            $runner->runDump($binary, $path, $dbConfig, 600);

            return file_exists($path) ? $path : null;

        } catch (\Throwable $e) {
            Log::error('[Backup] Safety failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function truncateData(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database');

        $tables = DB::select(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = ?',
            [$database]
        );

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $row) {
                $name = $row->table_name ?? $row->TABLE_NAME ?? null;

                if (!$name || in_array($name, self::EXCLUDED_TABLES, true)) {
                    continue;
                }

                DB::table($name)->truncate();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function verifyImport(): int
    {
        $database = config('database.connections.' . config('database.default') . '.database');

        $result = DB::select(
            'SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?',
            [$database]
        );

        return (int) ($result[0]->count ?? 0);
    }
}
