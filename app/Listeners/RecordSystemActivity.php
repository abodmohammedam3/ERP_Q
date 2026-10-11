<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;

/**
 * يسجّل نشاط المستخدمين منذ آخر نسخة احتياطية.
 *
 * - يزيد system.activity.count مع كل عملية (إنشاء/تعديل/حذف)
 * - يحدّث system.activity.last_at بآخر وقت نشاط
 * - عند بلوغ العتبة (100) يضع علامة system.activity.backup_needed
 *
 * يقرأها BackupController::index() لعرض مؤشر النشاط في شاشة الإعدادات.
 */
class RecordSystemActivity
{
    /** مهلة الاحتفاظ بالعدّاد (24 ساعة) */
    private const TTL = 86400;

    /** العتبة المطلوبة للإشارة إلى حاجة نسخة احتياطية */
    public const THRESHOLD = 100;

    public function handle(object $event): void
    {
        $count = (int) Cache::get('system.activity.count', 0) + 1;

        Cache::put('system.activity.count', $count, self::TTL);
        Cache::put('system.activity.last_at', now()->toIso8601String(), self::TTL);

        if ($count >= self::THRESHOLD) {
            Cache::put('system.activity.backup_needed', true, self::TTL);
        }
    }
}
