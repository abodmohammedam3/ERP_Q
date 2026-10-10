<?php

namespace App\Models\Backup;

use Illuminate\Database\Eloquent\Model;

class BackupOperation extends Model
{
    protected $table = 'backup_operations';

    protected $fillable = [
        'operation_id',
        'type',
        'format',
        'status',
        'progress',
        'stage',
        'file_path',
        'size_bytes',
        'error_message',
        'user_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'progress'    => 'integer',
        'size_bytes'  => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed'], true);
    }
}
