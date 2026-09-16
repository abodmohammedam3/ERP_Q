<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory\Item;
use App\Models\Inventory\Type;
use App\Models\Inventory\Unit;
use App\Models\Inventory\Stock;

class SalesInvoiceDetail extends Model
{
    protected $table = 'sales_invoice_details';
    protected $primaryKey = 'sales_invoice_detail_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'sales_invoice_id',
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

    public function salesInvoice()
    {
        return $this->belongsTo(
            SalesInvoice::class,
            'sales_invoice_id',
            'sales_invoice_id'
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