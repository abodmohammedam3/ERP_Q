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

    private const LOCK_KEY = 'backup.operation.lock';

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
        $filteredPath     = null;
        $appWasDown       = false;

        try {
            // 1) نسخة أمان إجبارية
            $status->update($this->operationId, 'running', 5, 'جاري إنشاء نسخة أمان...');

            $safetyBackupPath = $this->createSafetyBackup($runner, $detector);

            if (!$safetyBackupPath) {
                throw new \RuntimeException('فشل إنشاء النسخة الآمنة — تم الإلغاء');
            }

            // 2) وضع الصيانة — في الإنتاج فقط
            //    (في local لا نفعّلها حتى لا ينقطع مسار المتابعة operations/* بـ 503)
            if (config('app.env') === 'production') {
                $status->update($this->operationId, 'running', 15, 'جاري تفعيل الصيانة...');

                try {
                    Artisan::call('down', ['--secret' => 'backup-restore-in-progress']);
                    $appWasDown = true;
                } catch (\Throwable $e) {
                    Log::warning('[Backup] Maintenance mode failed', ['error' => $e->getMessage()]);
                }
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

            // تنقية اللقطة من الجداول النظامية المحمية
            // (النسخ الجديدة لا تحتويها أصلاً بسبب --ignore-table في التصدير،
            //  لكن اللقطات القديمة تحتوي DROP TABLE لها ودهست صف العملية → 404)
            $filteredPath = $this->filterDumpFile($this->uploadedFilePath);

            $runner->runRestore($mysqlBinary, $filteredPath, $dbConfig, 1800);

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

        } finally {
            // حذف النسخة المُصفّاة دائماً (الأصلية تُحذف في مسار النجاح/الفشل أعلاه)
            if ($filteredPath && is_string($filteredPath)) {
                @unlink($filteredPath);
            }

            // ✅ يُنفَّذ دائماً — نجاح أو فشل — يحرر القفل
            $this->releaseLock();
        }
    }

    /**
     * يُستدعى تلقائياً عند الفشل النهائي (بعد استنفاد tries).
     */
    public function failed(\Throwable $e): void
    {
        $this->releaseLock();

        Log::error('[Backup] Import failed permanently', [
            'operation_id' => $this->operationId,
            'error'        => $e->getMessage(),
        ]);
    }

    /**
     * تحرير القفل بعد انتهاء العملية.
     */
    private function releaseLock(): void
    {
        try {
            Cache::lock(self::LOCK_KEY)->forceRelease();
        } catch (\Throwable $ignore) {
            // تجاهل — لا نريد أن يفشل الـ Job بسبب تحرير القفل
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

            // ⚠️ يجب أن تكون البادئة safety_backup_ حتى تُنظّفها BackupRetention
            //    (كانت safety_ فتتخطاها قواعد التنظيف وتتراكم على القرص)
            $path = $dir . DIRECTORY_SEPARATOR . 'safety_backup_' . now()->format('Y-m-d_His') . '.sql';

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

        $excluded = $this->excludedTables();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $row) {
                $name = $row->table_name ?? $row->TABLE_NAME ?? null;

                if (!$name || in_array($name, $excluded, true)) {
                    continue;
                }

                DB::table($name)->truncate();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * الجداول النظامية المحمية — المصدر الوحيد config/backup.php
     * (تستخدمه أيضاً: التصدير عبر --ignore-table، و truncateData، و filterDumpFile).
     */
    private function excludedTables(): array
    {
        return array_values(array_filter((array) config('backup.excluded_tables', [])));
    }

    /**
     * تنقية ملف لقطة قديم من الجداول النظامية المحمية.
     *
     * المشكلة التي تحلها: اللقطات المُصدَّرة قبل إضافة --ignore-table تحتوي
     * DROP TABLE / CREATE TABLE / INSERT لجداول مثل backup_operations و users —
     * كان تنفيذها أثناء الاستيراد يمسح صف العملية الجارية (المتصفح يرى 404)
     * ويعيد بناء الجداول من لقطة قديمة رغم قواعد الاستثناء.
     *
     * القراءة سطراً بسطر (fgets) لتجنّب تحميل لقطات ضخمة في ذاكرة PHP.
     * الملف المُصفّى يُكتب في backup_uploads ويُحذف في finally بعد الاستيراد.
     */
    private function filterDumpFile(string $sourcePath): string
    {
        $excluded = $this->excludedTables();

        if (empty($excluded)) {
            return $sourcePath;
        }

        $uploadDir = storage_path('app/private/backup_uploads');

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filteredPath = $uploadDir . DIRECTORY_SEPARATOR
            . 'filtered_' . bin2hex(random_bytes(6)) . '.sql';

        $in = @fopen($sourcePath, 'r');

        if (!$in) {
            throw new \RuntimeException('لا يمكن قراءة ملف الاستيراد: ' . $sourcePath);
        }

        $out = @fopen($filteredPath, 'w');

        if (!$out) {
            fclose($in);
            throw new \RuntimeException('لا يمكن إنشاء الملف المُصفّى: ' . $filteredPath);
        }

        try {
            $skipUntilTerminator = false;

            while (($line = fgets($in)) !== false) {

                if ($skipUntilTerminator) {
                    if (substr(rtrim($line), -1) === ';') {
                        $skipUntilTerminator = false;
                    }
                    continue;
                }

                foreach ($excluded as $table) {

                    // كتلة تخص جدولاً محمياً؟ مثال mysqldump:
                    //   DROP TABLE IF EXISTS `users`;
                    //   CREATE TABLE `users` ( ... multi-line ... );
                    //   INSERT INTO `users` VALUES (...);
                    //   ALTER TABLE `users` ...;
                    //   LOCK TABLES `users` WRITE;
                    if (preg_match(
                        '/^(?:DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO|ALTER TABLE|LOCK TABLES)\s+`'
                        . preg_quote($table, '/')
                        . '`/i',
                        $line
                    )) {
                        $skipUntilTerminator = true;
                        break;
                    }
                }

                if ($skipUntilTerminator) {
                    // جملة أسطرية اكتملت على السطر نفسه (DROP ...; / LOCK ...;)
                    if (substr(rtrim($line), -1) === ';') {
                        $skipUntilTerminator = false;
                    }
                    continue;
                }

                fwrite($out, $line);
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return $filteredPath;
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