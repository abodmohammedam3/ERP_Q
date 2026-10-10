<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Backup\ExportBackupRequest;
use App\Http\Requests\Settings\Backup\ImportBackupRequest;
use App\Jobs\Backup\CleanupBackupJob;
use App\Jobs\Backup\ExportBackupJob;
use App\Jobs\Backup\ImportBackupJob;
use App\Services\Backup\BackupRetention;
use App\Services\Backup\BackupService;
use App\Services\Backup\BackupStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class BackupController extends Controller
{
    private const LOCK_KEY = 'backup.operation.lock';
    private const LOCK_TTL = 3600;

    public function __construct(
        private BackupStatusService $status,
        private BackupService $backupService,
        private BackupRetention $retention,
    ) {}

    // ═══════════════════════════════════════════════════════
    //  Response Helpers (لا توجد في Base Controller)
    // ═══════════════════════════════════════════════════════

    private function ok(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(
            array_merge(['success' => true], $data),
            $status
        );
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    // ═══════════════════════════════════════════════════════
    //  GET  /settings/system/backup
    // ═══════════════════════════════════════════════════════

    public function index(): JsonResponse
    {
        $backups    = $this->backupService->listBackups();
        $totalSize  = array_sum(array_column($backups, 'size'));
        $lastBackup = $backups[0] ?? null;

        return $this->ok([
            'backups'     => $backups,
            'total_size'  => $totalSize,
            'total_count' => count($backups),
            'last_backup' => $lastBackup ? [
                'filename' => $lastBackup['filename'],
                'date'     => $lastBackup['date'],
                'size'     => $lastBackup['size'],
            ] : null,
            'activity' => [
                'since_last_backup' => (int) Cache::get('system.activity.count', 0),
                'threshold'         => 100,
                'last_at'           => Cache::get('system.activity.last_at'),
            ],
            'retention' => [
                'manual' => (int) config('backup.retention_count', 7),
                'safety' => (int) config('backup.safety_retention_count', 3),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  POST  /settings/system/backup/export
    // ═══════════════════════════════════════════════════════

    public function export(ExportBackupRequest $request): JsonResponse
    {
        if (!$this->acquireLock()) {
            return $this->fail('عملية أخرى قيد التنفيذ — حاول بعد قليل.', 423);
        }

        try {
            $format = $request->input('format', 'sql');
            $op     = $this->status->create('export', $format);

            ExportBackupJob::dispatch($op->operation_id)->onQueue('backups');

            return $this->ok([
                'operation_id' => $op->operation_id,
                'message'      => 'بدأ التصدير في الخلفية',
            ], 202);

        } catch (\Throwable $e) {
            $this->releaseLock();
            return $this->fail('فشل بدء التصدير: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════
    //  POST  /settings/system/backup/import
    // ═══════════════════════════════════════════════════════

    public function import(ImportBackupRequest $request): JsonResponse
    {
        if (!$this->acquireLock()) {
            return $this->fail('عملية أخرى قيد التنفيذ — حاول بعد قليل.', 423);
        }

        try {
            $file = $request->file('backup_file');

            $content = file_get_contents($file->getRealPath(), false, null, 0, 100_000);

            if (stripos($content, 'CREATE TABLE') === false) {
                $this->releaseLock();
                return $this->fail('الملف ليس نسخة SQL صالحة');
            }

            $uploadDir = storage_path('app/private/backup_uploads');

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = 'upload_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
            $file->move($uploadDir, $filename);

            $path = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            $op = $this->status->create('import', 'sql');

            ImportBackupJob::dispatch($op->operation_id, $path)->onQueue('backups');

            return $this->ok([
                'operation_id' => $op->operation_id,
                'message'      => 'بدأ الاستيراد — سيتم استبدال كل البيانات الحالية',
            ], 202);

        } catch (\Throwable $e) {
            $this->releaseLock();
            return $this->fail('فشل بدء الاستيراد: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════
    //  GET  /settings/system/backup/operations/{id}
    // ═══════════════════════════════════════════════════════

    public function operation(string $operationId): JsonResponse
    {
        $op = $this->status->find($operationId);

        if (!$op) {
            return $this->fail('العملية غير موجودة', 404);
        }

        if ($op->isFinished()) {
            $this->releaseLock();
        }

        return $this->ok([
            'operation' => [
                'id'       => $op->operation_id,
                'type'     => $op->type,
                'status'   => $op->status,
                'progress' => $op->progress,
                'stage'    => $op->stage,
                'size'     => $op->size_bytes,
                'error'    => $op->error_message,
                'finished' => $op->isFinished(),
                'download' => $op->type === 'export' && $op->status === 'done'
                    ? route('settings.system.backup.download', ['operationId' => $op->operation_id])
                    : null,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  GET  /settings/system/backup/download/{operationId}
    // ═══════════════════════════════════════════════════════

    public function download(string $operationId)
    {
        $op = $this->status->find($operationId);

        if (!$op || $op->type !== 'export' || $op->status !== 'done' || !$op->file_path) {
            abort(404, 'الملف غير متاح');
        }

        if (!file_exists($op->file_path)) {
            abort(404, 'الملف غير موجود');
        }

        return response()->download(
            $op->file_path,
            basename($op->file_path),
            ['Content-Type' => 'application/sql']
        );
    }

    // ═══════════════════════════════════════════════════════
    //  GET  /settings/system/backup/files/{filename}
    // ═══════════════════════════════════════════════════════

    public function downloadExisting(string $filename)
    {
        $filename = basename($filename);

        $path = rtrim(config('backup.path'), '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($path)) {
            abort(404, 'الملف غير موجود');
        }

        return response()->download($path, $filename);
    }

    // ═══════════════════════════════════════════════════════
    //  DELETE  /settings/system/backup/files/{filename}
    // ═══════════════════════════════════════════════════════

    public function destroy(string $filename): JsonResponse
    {
        $filename = basename($filename);

        $path = rtrim(config('backup.path'), '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($path)) {
            return $this->fail('الملف غير موجود', 404);
        }

        if (!@unlink($path)) {
            return $this->fail('فشل حذف الملف');
        }

        @unlink($path . '.sha256');

        return $this->ok(['message' => 'تم الحذف بنجاح']);
    }

    // ═══════════════════════════════════════════════════════
    //  POST  /settings/system/backup/cleanup
    // ═══════════════════════════════════════════════════════

    public function cleanup(): JsonResponse
    {
        try {
            $deleted = $this->retention->cleanup(config('backup.path'));

            CleanupBackupJob::dispatch()->onQueue('backups');

            return $this->ok([
                'deleted_count' => count($deleted),
                'message'       => sprintf('تم حذف %d ملف', count($deleted)),
            ]);

        } catch (\Throwable $e) {
            return $this->fail('فشل التنظيف: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════
    //  Lock Helpers
    // ═══════════════════════════════════════════════════════

    private function acquireLock(): bool
    {
        return Cache::lock(self::LOCK_KEY, self::LOCK_TTL)->get();
    }

    private function releaseLock(): void
    {
        try {
            Cache::lock(self::LOCK_KEY)->release();
        } catch (\Throwable $e) {
            // تجاهل
        }
    }
}
