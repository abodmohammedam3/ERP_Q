<?php

namespace App\Models\Backup;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    protected $table = 'backup_logs';

    protected $fillable = [
        'operation',
        'format',
        'status',
        'filename',
        'size_bytes',
        'message',
        'user_id',
        'ip_address',
        'user_agent',
        'duration_seconds',
    ];

    protected $casts = [
        'size_bytes'       => 'integer',
        'duration_seconds' => 'integer',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
