<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * كاش لوحة المعلومات — إبطال بالإصدار (version++) بلا Cache::tags.
 *
 * لماذا:
 *  - CACHE_STORE=file في .env ⇒ الوسم (tags) غير مدعوم إطلاقًا،
 *    فمسح مفاتيح "الداش" بالاسم مستحيل. البديل الموجود في النظام نفسه:
 *    إصدار يُزاد في المفتاح (OpeningBalanceController::buildListCacheKey).
 *  - أي عملية كتابة (إنشاء/تعديل/حذف) تزيد الإصدار ⇒ كل المفاتيح القديمة
 *    تُصبح غير قابلة للوصول منطقيًا، والطلب التالي يبنيها من جديد.
 *  - TTL قصير (30 ثانية) يخدم فقط فتحات متتالية سريعة لنفس الشاشة،
 *    وهو لا يُستخدم أبدًا كبديل عن الإبطال.
 *
 * ملاحظة: ImportBackupJob.php:117 يستدعي Cache::flush() بعد الاستيراد
 * ⇒ مفاتيح هذا الكلاس تُمسح تلقائيًا بعد أي استيراد نسخة احتياطية.
 */
class DashboardCache
{
    /** مفتاح الإصدار — كاش دائم يُزاد فقط */
    public const VERSION_KEY = 'dashboard.version';

    /** مدة صلاحية القراءة (ثوانٍ) — ليست آلية تحديث، فقط اختصار */
    public const TTL = 30;

    /**
     * الإصدار الحالي (يبدأ من 1).
     */
    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * إبطال منطقي: زيادة الإصدار.
     *
     * ملاحظة: زيادة الإصدار ليست ذرّية (get ثم put). لو تضاربت عمليتان
     * في نفس اللحظة فقد تُعيد كلتاهما نفس القيمة. الأثر الوحيد هو بقاء
     * البيانات القديمة حتى انتهاء TTL (30 ثانية) — مقبول، ولا يفسد البيانات.
     */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    /**
     * قراءة/بناء جزء من بيانات اللوحة داخل الكاش المرتبط بالإصدار.
     *
     * @param  string       $part  اسم الجزء (indicators / summary / supply ...)
     * @param  Closure       $callback
     * @param  int|null      $ttl
     * @return mixed
     */
    public static function remember(string $part, Closure $callback, ?int $ttl = null): mixed
    {
        $key = self::key($part);

        return Cache::remember($key, $ttl ?? self::TTL, $callback);
    }

    /**
     * مفتاح الجزء بإصداره الحالي.
     */
    public static function key(string $part): string
    {
        return 'dashboard.v' . self::version() . '.' . $part;
    }
}
