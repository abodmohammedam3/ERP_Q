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
    // ✅ ثوابت المصدر (جديد)
    // ============================================
    const SOURCE_PURCHASE_INVOICE = 'purchase_invoice';
    const SOURCE_SALES_INVOICE    = 'sales_invoice';
    const SOURCE_MANUAL           = null;

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
        return !empty($this->source_type) && !empty($this->source_id);
    }
}