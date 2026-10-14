<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// ============================================================
// مهام الصيانة الدورية لـ ERP_Q
// تُطلق تلقائياً عبر: Task Scheduler → php artisan schedule:run (كل دقيقة)
// ============================================================

// تنظيف النسخ القديمة يومياً 2 صباحاً (حسب policy الاحتفاظ في config/backup.php)
Schedule::command('backup:cleanup')->dailyAt('02:00');

// تنظيف العمليات العالقة كل 6 ساعات — أي pending/running منذ أكثر من 24h
// تُحوَّل إلى failed وتُحرَّر أقفالها (الحماية من "running للأبد")
Schedule::call(function () {
    app(\App\Services\Backup\BackupStatusService::class)->reapStuck(24);
})->everySixHours()->name('backup:reap-stuck');

// تنظيف failed_jobs الأقدم من 7 أيام
Schedule::command('queue:prune-failed --hours=168')->daily();

// تنظيف job_batches المكتملة الأقدم من 48 ساعة
Schedule::command('queue:prune-batches --hours=48')->daily();

// ------------------------------------------------------------
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

