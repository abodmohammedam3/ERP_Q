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

    /* ✅ جديد: أنواع الفرز */
    const TYPE_SORTING_OUT     = 'sorting_out';   // فرز تحويل كيلو (خروج)
    const TYPE_SORTING_IN      = 'sorting_in';    // فرز تحويل حبه (دخول)

    // ============================================
    // ثوابت المصدر
    // ============================================
    const SOURCE_PURCHASE_INVOICE = 'purchase_invoice';
    const SOURCE_SALES_INVOICE    = 'sales_invoice';
    const SOURCE_MANUAL           = null;
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
        self::TYPE_SORTING_IN      => self::DIRECTION_IN,
        self::TYPE_ISSUE           => self::DIRECTION_OUT,
        self::TYPE_SALE            => self::DIRECTION_OUT,
        self::TYPE_PURCHASE_RETURN => self::DIRECTION_OUT,
        self::TYPE_SORTING_OUT     => self::DIRECTION_OUT,
    ];

    // ============================================
    // خريطة: نوع الحركة → التسمية العربية
    // ============================================
    const TYPE_LABELS = [
        self::TYPE_SUPPLY          => 'توريد مخزني',
        self::TYPE_ISSUE           => 'صرف مخزني',
        self::TYPE_PURCHASE        => 'توريد شراء',
        self::TYPE_SALE            => 'صرف بيع',
        self::TYPE_PURCHASE_RETURN => 'مرتجع شراء',
        self::TYPE_SALE_RETURN     => 'مرتجع بيع',
        self::TYPE_SORTING_OUT     => 'فرز تحويل كيلو',
        self::TYPE_SORTING_IN      => 'فرز تحويل حبه',
    ];

    // ============================================
    // العلاقات
    // ============================================

    public function warehouse()
    {
        return $this->belongsTo(
            Stock::class,
            'warehouse_id',
            'StockID'
        );
    }

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

    public static function directionForType(string $type): string
    {
        return self::TYPE_DIRECTION_MAP[$type] ?? self::DIRECTION_IN;
    }

    public static function labelForType(?string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ($type ?? '—');
    }

    public function isFromInvoice(): bool
    {
        return in_array($this->source_type, [
            self::SOURCE_PURCHASE_INVOICE,
            self::SOURCE_SALES_INVOICE,
        ], true) && !empty($this->source_id);
    }

    public function isFromSorting(): bool
    {
        return $this->source_type === self::SOURCE_SORTING;
    }
}