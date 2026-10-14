<?php

namespace App\Services\Backup;

use App\Models\Backup\BackupLog;
use App\Models\Backup\BackupOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BackupStatusService
{
    public function create(string $type, string $format = 'sql'): BackupOperation
    {
        return BackupOperation::create([
            'operation_id' => (string) Str::uuid(),
            'type'         => $type,
            'format'       => $format,
            'status'       => 'pending',
            'progress'     => 0,
            'user_id'      => auth()->id(),
        ]);
    }

    public function update(
        string $operationId,
        string $status,
        int $progress,
        ?string $stage = null,
        ?string $filePath = null,
        ?int $sizeBytes = null,
        ?string $errorMessage = null
    ): void {
        // updateOrCreate: حتى لو حذف الاستيراد الصف (لقطة قديمة كانت تحتوي
        // backup_operations ودهست صف العملية الجارية) تُعاد كتابته وتستمر
        // المتابعة في الواجهة بدل رؤية 404 "العملية غير موجودة".
        $op = BackupOperation::where('operation_id', $operationId)->first();

        $values = [
            'status'   => $status,
            'progress' => max(0, min(100, $progress)),
            'stage'    => $stage,
        ];

        if ($filePath !== null)      $values['file_path'] = $filePath;
        if ($sizeBytes !== null)     $values['size_bytes'] = $sizeBytes;
        if ($errorMessage !== null)  $values['error_message'] = $errorMessage;

        if ($status === 'running' && (!$op || !$op->started_at)) {
            $values['started_at'] = now();
        }

        if (in_array($status, ['done', 'failed'], true)) {
            $values['finished_at'] = now();
        }

        BackupOperation::updateOrCreate(
            ['operation_id' => $operationId],
            $values
        );
    }

    public function find(string $operationId): ?BackupOperation
    {
        return BackupOperation::where('operation_id', $operationId)->first();
    }

    public function logResult(BackupOperation $op): void
    {
        $duration = $op->started_at && $op->finished_at
             ? (int) abs($op->finished_at->diffInSeconds($op->started_at))
             : null;

        BackupLog::create([
            'operation'        => $op->type,
            'format'           => $op->format,
            'status'           => $op->status === 'done' ? 'success' : 'failed',
            'filename'         => $op->file_path ? basename($op->file_path) : null,
            'size_bytes'       => $op->size_bytes,
            'message'          => $op->error_message,
            'user_id'          => $op->user_id,
            'ip_address'       => request()->ip(),
            'user_agent'       => substr((string) request()->userAgent(), 0, 500),
            'duration_seconds' => $duration,
        ]);
    }

    public function cleanup(): int
    {
        return BackupOperation::where('created_at', '<', now()->subDays(7))->delete();
    }

    /**
     * تنظيف العمليات العالقة (pending/running منذ أكثر من X ساعة).
     * - يحوّلها إلى failed مع سبب واضح
     * - يحرر قفل backup.operation.lock من cache_locks (بشرط — انظر أدناه)
     *
     * ملاحظة أمان: تحرير القفل يتم فقط إذا لم يتبقَّ أي عملية شرعية
     * حالية (pending/running حديثة) — لأن القفل عام لجميع العمليات،
     * وحذفه أثناء وجود عملية جديدة سيفتح الباب لعملية متزامنة ثالثة.
     *
     * يُستدعى دورياً من Scheduler (routes/console.php).
     */
    public function reapStuck(int $hoursOld = 24): int
    {
        $threshold = now()->subHours($hoursOld);

        $stuck = BackupOperation::whereIn('status', ['pending', 'running'])
            ->where('created_at', '<', $threshold)
            ->get();

        if ($stuck->isEmpty()) {
            return 0;
        }

        foreach ($stuck as $op) {
            $op->status = 'failed';
            $op->progress = 0;
            $op->stage = "Stuck — auto-cleaned after {$hoursOld}h";
            $op->error_message = "العملية تجاوزت {$hoursOld} ساعة بدون اكتمال — تم تنظيفها تلقائياً";
            $op->finished_at = now();
            $op->save();
        }

        // حرر القفل فقط إذا لم يتبقَّ أي عملية شرعية نشطة الآن
        $stillActive = BackupOperation::whereIn('status', ['pending', 'running'])
            ->where('created_at', '>=', $threshold)
            ->exists();

        if (! $stillActive) {
            try {
                DB::table('cache_locks')
                    ->where('key', 'like', '%backup.operation.lock%')
                    ->delete();
            } catch (\Throwable $e) {
                Log::warning('[Backup] reapStuck: could not release lock — '.$e->getMessage());
            }
        }

        Log::warning('[Backup] Reaped stuck operations', [
            'count'        => $stuck->count(),
            'operation_ids' => $stuck->pluck('operation_id')->all(),
            'lock_released' => ! $stillActive,
        ]);

        return $stuck->count();
    }
}
