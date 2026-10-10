<?php

namespace App\Services\Backup;

use App\Models\Backup\BackupLog;
use App\Models\Backup\BackupOperation;
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
        $op = BackupOperation::where('operation_id', $operationId)->first();

        if (!$op) {
            return;
        }

        $op->status   = $status;
        $op->progress = max(0, min(100, $progress));
        $op->stage    = $stage;

        if ($filePath !== null)     $op->file_path = $filePath;
        if ($sizeBytes !== null)    $op->size_bytes = $sizeBytes;
        if ($errorMessage !== null) $op->error_message = $errorMessage;

        if ($status === 'running' && !$op->started_at) {
            $op->started_at = now();
        }

        if (in_array($status, ['done', 'failed'], true)) {
            $op->finished_at = now();
        }

        $op->save();
    }

    public function find(string $operationId): ?BackupOperation
    {
        return BackupOperation::where('operation_id', $operationId)->first();
    }

    public function logResult(BackupOperation $op): void
    {
        $duration = $op->started_at && $op->finished_at
            ? $op->finished_at->diffInSeconds($op->started_at)
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
}
