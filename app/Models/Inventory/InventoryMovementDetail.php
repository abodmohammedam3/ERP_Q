<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounting\CharAccount;

class InventoryMovementDetail extends Model
{
    protected $table = 'inventory_movement_details';
    protected $primaryKey = 'movement_detail_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'movement_id',
        'item_id',
        'type_id',
        'unit_id',
        'code',
        'warehouse_id',
        'supplier_account_id',
        'lot_source_detail_id',
        'quantity',
        'unit_cost',
        'min_price',
        'max_price',
        'sale_price',
        'total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:6',
        'unit_cost'  => 'decimal:6',
        'min_price'  => 'decimal:6',
        'max_price'  => 'decimal:6',
        'sale_price' => 'decimal:6',
        'total'      => 'decimal:6',
    ];

    // ============================================
    // العلاقات
    // ============================================

    /**
     * المورد الفعلي للدفعة (NULL = توريد عام)
     */
    public function supplierAccount()
    {
        return $this->belongsTo(CharAccount::class, 'supplier_account_id', 'accountID');
    }

    /**
     * دفعة الأصل (مرتجع بيع / فرز يرث دفعة أصلها)
     */
    public function lotSource()
    {
        return $this->belongsTo(
            InventoryMovementDetail::class,
            'lot_source_detail_id',
            'movement_detail_id'
        );
    }

    /**
     * رأس الحركة
     */
    public function movement()
    {
        return $this->belongsTo(
            InventoryMovement::class,
            'movement_id',
            'movement_id'
        );
    }

    /**
     * الصنف
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'itemID');
    }

    /**
     * النوع
     */
    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id', 'id');
    }

    /**
     * الوحدة
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'UnitID');
    }

    /**
     * مخزن الصف
     */
    public function warehouse()
    {
        return $this->belongsTo(Stock::class, 'warehouse_id', 'StockID');
    }
}