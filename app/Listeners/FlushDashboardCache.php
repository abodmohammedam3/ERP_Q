<?php

namespace App\Listeners;

use App\Support\DashboardCache;
use Illuminate\Support\Facades\Log;

/**
 * إبطال كاش لوحة المعلومات عند أي عملية كتابة.
 *
 * يُسجَّل مركزيًا في AppServiceProvider::boot() على أحداث Eloquent
 * (created / updated / deleted) — نفس أسلوب RecordSystemActivity.
 *
 * try/catch إجباري:
 *  - هذا الـ listener يُنفَّذ داخل دورة Eloquent أثناء حفظ الفاتورة.
 *  - أي استثناء هنا يُرجع للمستخدم ويفشل الحفظ نفسه (كسر نظام).
 *  - نفس الحماية موجودة في ImportBackupJob.php:102 و :119.
 */
class FlushDashboardCache
{
    public function handle(object $event): void
    {
        try {
            DashboardCache::bump();
        } catch (\Throwable $e) {
            Log::warning('[Dashboard] cache bump failed', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
