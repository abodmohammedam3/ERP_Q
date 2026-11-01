<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;

/**
 * سجل تتبع توزيعات المشتريات (Audit Log)
 * -----------------------------------------------------
 * كل فعالية على التوزيع تُسجَّل هنا (إنشاء / تعديل / إصدار / حذف)
 * مع المستخدم والوقت وبيانات إضافية (meta).
 */
class DistributionLog extends Model
{
    protected $table = 'distribution_logs';
    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
    ];

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_ISSUED  = 'issued';
    public const ACTION_DELETED = 'deleted';

    /** التسمية العربية للفعالية */
    public static function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_CREATED => 'إنشاء',
            self::ACTION_UPDATED => 'تعديل',
            self::ACTION_ISSUED  => 'إصدار فاتورة',
            self::ACTION_DELETED => 'حذف',
            default              => $action,
        };
    }

    public function distribution()
    {
        return $this->belongsTo(Distribution::class, 'distribution_id');
    }
}
