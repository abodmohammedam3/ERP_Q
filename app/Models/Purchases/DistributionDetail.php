<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;

class DistributionDetail extends Model
{
    protected $table = 'distribution_details';
    protected $guarded = ['id'];

    protected $casts = [
        'quantity'    => 'decimal:6',
        'unit_cost'   => 'decimal:6',
        'sale_price'  => 'decimal:6',
        'invoiced_at' => 'datetime',
    ];

    public function distribution()
    {
        return $this->belongsTo(Distribution::class, 'distribution_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'CustomersID');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id', 'purchase_invoice_id');
    }

    public function movement()
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id', 'movement_id');
    }

    public function movementDetail()
    {
        return $this->belongsTo(InventoryMovementDetail::class, 'movement_detail_id', 'movement_detail_id');
    }
}