<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory\Item;
use App\Models\Inventory\Type;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Stock;

/**
 * تفاصيل مرتجع الشراء
 * -----------------------------------------------------
 * كل سطر مرتبط إلزاميًا بسطر فاتورة الشراء الأصلية
 * (purchase_invoice_detail_id) لحساب الكمية المتبقية القابلة للإرجاع.
 *
 * unit_cost = التكلفة الفعلية (Landed Cost) من حركة الشراء الأصلية.
 */
class PurchaseReturnDetail extends Model
{
    protected $table = 'purchase_return_details';
    protected $primaryKey = 'purchase_return_detail_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'purchase_return_id',
        'purchase_invoice_detail_id',
        'item_id',
        'type_id',
        'unit_id',
        'warehouse_id',
        'code',
        'quantity',
        'price',
        'unit_cost',
        'discount',
        'total',
    ];

    protected $casts = [
        'quantity'  => 'decimal:6',
        'price'     => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'discount'  => 'decimal:6',
        'total'     => 'decimal:6',
    ];

    // ============================================
    // العلاقات
    // ============================================

    public function purchaseReturn()
    {
        return $this->belongsTo(
            PurchaseReturn::class,
            'purchase_return_id',
            'purchase_return_id'
        );
    }

    /**
     * سطر فاتورة الشراء الأصلية
     */
    public function originalDetail()
    {
        return $this->belongsTo(
            PurchaseInvoiceDetail::class,
            'purchase_invoice_detail_id',
            'purchase_invoice_detail_id'
        );
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'itemID');
    }

    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id', 'id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'UnitID');
    }

    public function warehouse()
    {
        return $this->belongsTo(Stock::class, 'warehouse_id', 'StockID');
    }
}