<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory\Item;
use App\Models\Inventory\Type;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Stock;

/**
 * تفاصيل مرتجع البيع
 * -----------------------------------------------------
 * كل سطر مرتبط إلزاميًا بسطر الفاتورة الأصلية
 * (sales_invoice_detail_id) لحساب الكمية المتبقية القابلة للإرجاع.
 */
class SalesReturnDetail extends Model
{
    protected $table = 'sales_return_details';
    protected $primaryKey = 'sales_return_detail_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'sales_return_id',
        'sales_invoice_detail_id',
        'item_id',
        'type_id',
        'unit_id',
        'warehouse_id',
        'code',
        'quantity',
        'price',
        'cost_price',
        'discount',
        'total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:6',
        'price'      => 'decimal:6',
        'cost_price' => 'decimal:6',
        'discount'   => 'decimal:6',
        'total'      => 'decimal:6',
    ];

    // ============================================
    // العلاقات
    // ============================================

    public function salesReturn()
    {
        return $this->belongsTo(
            SalesReturn::class,
            'sales_return_id',
            'sales_return_id'
        );
    }

    /**
     * سطر الفاتورة الأصلية
     */
    public function originalDetail()
    {
        return $this->belongsTo(
            SalesInvoiceDetail::class,
            'sales_invoice_detail_id',
            'sales_invoice_detail_id'
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