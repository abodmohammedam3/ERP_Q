<?php

return [
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),
    'mysql_path' => env('BACKUP_MYSQL_PATH'),
    'path' => storage_path('app/private/backups'),
    'retention_count' => (int) env('BACKUP_RETENTION_COUNT', 7),
    'safety_retention_count' => (int) env('BACKUP_SAFETY_RETENTION_COUNT', 3),
    'lock_timeout' => (int) env('BACKUP_LOCK_TIMEOUT', 3600),
    'timeout' => (int) env('BACKUP_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------
    | الجداول النظامية المحمية
    |--------------------------------------------------------------
    | 1) يستخدمها mysqldump عبر --ignore-table عند التصدير
    |    (حتى لا تدخل الجداول في ملف النسخة أصلاً).
    | 2) يستخدمها ImportBackupJob عند حذف البيانات قبل الاستيراد.
    | 3) يستخدمها ImportBackupJob لتنقية الملفات القديمة التي ما زالت
    |    تحتوي هذه الجداول (نسخ مُصدَّرة قبل هذا الإصلاح).
    |
    | ⚠️ لا تستخدم wildcards — الاسم الدقيق لكل جدول.
    */
    'excluded_tables' => [
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
    ],
];

