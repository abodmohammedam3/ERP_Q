<?php

return [
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),
    'mysql_path' => env('BACKUP_MYSQL_PATH'),
    'path' => storage_path('app/private/backups'),
    'retention_count' => (int) env('BACKUP_RETENTION_COUNT', 7),
    'safety_retention_count' => (int) env('BACKUP_SAFETY_RETENTION_COUNT', 3),
    'lock_timeout' => (int) env('BACKUP_LOCK_TIMEOUT', 3600),
    'timeout' => (int) env('BACKUP_TIMEOUT', 600),
];

