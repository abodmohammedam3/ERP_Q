<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $table = 'inventory_movements';
    protected $primaryKey = 'movement_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'display_id',
        'movement_type',
        'direction',
        'movement_date',
        'document_number',
        'warehouse_id',
        'statement',
        'source_type',
        'source_id',
        'total',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'total'         => 'decimal:6',
    ];

    // ============================================
    // ثوابت أنواع الحركة
    // ============================================
    const TYPE_SUPPLY          = 'supply';
    const TYPE_ISSUE           = 'issue';
    const TYPE_PURCHASE        = 'purchase';
    const TYPE_SALE            = 'sale';
    const TYPE_PURCHASE_RETURN = 'purchase_return';
    const TYPE_SALE_RETURN     = 'sale_return';

    // ============================================
    // ثوابت المصدر
    // ============================================
    const SOURCE_PURCHASE_INVOICE = 'purchase_invoice';
    const SOURCE_SALES_INVOICE    = 'sales_invoice';
    const SOURCE_MANUAL           = null;

    /**
     * ✅ مصدر "فرز / تجهيز المخزون"
     *
     * حركات الفرز تُسجَّل كحركتين:
     *   - OUT: movement_type = 'issue' + source_type = 'sorting'
     *   - IN:  movement_type = 'supply' + source_type = 'sorting'
     *
     * ويتم ربطهما عبر:
     *   - document_number = 'SORT-XXXXXX'
     *   - source_id = movement_id الأصلي (اختياري)
     */
    const SOURCE_SORTING          = 'sorting';

    // ============================================
    // ثوابت الاتجاه
    // ============================================
    const DIRECTION_IN  = 'in';
    const DIRECTION_OUT = 'out';

    // ============================================
    // خريطة: نوع الحركة → الاتجاه
    // ============================================
    const TYPE_DIRECTION_MAP = [
        self::TYPE_SUPPLY          => self::DIRECTION_IN,
        self::TYPE_PURCHASE        => self::DIRECTION_IN,
        self::TYPE_SALE_RETURN     => self::DIRECTION_IN,
        self::TYPE_ISSUE           => self::DIRECTION_OUT,
        self::TYPE_SALE            => self::DIRECTION_OUT,
        self::TYPE_PURCHASE_RETURN => self::DIRECTION_OUT,
    ];

    // ============================================
    // العلاقات
    // ============================================

    /**
     * المخزن الرئيسي/الافتراضي
     */
    public function warehouse()
    {
        return $this->belongsTo(
            Stock::class,
            'warehouse_id',
            'StockID'
        );
    }

    /**
     * تفاصيل الحركة
     */
    public function details()
    {
        return $this->hasMany(
            InventoryMovementDetail::class,
            'movement_id',
            'movement_id'
        );
    }

    // ============================================
    // Helper Methods
    // ============================================

    /**
     * اتجاه الحركة من النوع
     */
    public static function directionForType(string $type): string
    {
        return self::TYPE_DIRECTION_MAP[$type] ?? self::DIRECTION_IN;
    }

    /**
     * هل الحركة ناتجة عن فاتورة (غير قابلة للتعديل)؟
     */
    public function isFromInvoice(): bool
    {
        return in_array($this->source_type, [
            self::SOURCE_PURCHASE_INVOICE,
            self::SOURCE_SALES_INVOICE,
        ], true) && !empty($this->source_id);
    }

    /**
     * ✅ هل الحركة ناتجة عن عملية فرز/تجهيز؟
     */
    public function isFromSorting(): bool
    {
        return $this->source_type === self::SOURCE_SORTING;
    }
}