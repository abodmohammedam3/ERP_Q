<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory\Item;
use App\Models\Inventory\Type;
use App\Models\Inventory\Unit;

class PurchaseInvoiceDetail extends Model
{
    protected $table = 'purchase_invoice_details';
    protected $primaryKey = 'purchase_invoice_detail_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'purchase_invoice_id',
        'item_id',
        'type_id',
        'unit_id',
        'code',
        'quantity',
        'price',
        'discount',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'price'    => 'decimal:6',
        'discount' => 'decimal:6',
        'total'    => 'decimal:6',
    ];

    // ============================================
    // العلاقات
    // ============================================

    /**
     * رأس الفاتورة
     */
    public function purchaseInvoice()
    {
        return $this->belongsTo(
            PurchaseInvoice::class,
            'purchase_invoice_id',
            'purchase_invoice_id'
        );
    }

    /**
     * الصنف
     */
    public function item()
    {
        return $this->belongsTo(
            Item::class,
            'item_id',
            'itemID'
        );
    }

    /**
     * النوع
     */
    public function type()
    {
        return $this->belongsTo(
            Type::class,
            'type_id',
            'id'
        );
    }

    /**
     * الوحدة
     */
    public function unit()
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id',
            'UnitID'
        );
    }
}