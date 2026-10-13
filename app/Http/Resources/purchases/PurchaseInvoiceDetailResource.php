<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'purchase_invoice_detail_id' => $this->purchase_invoice_detail_id,
            'item_id'    => $this->item_id,
            'item_name'  => $this->item->itemName2 ?? '',
            'type_id'    => $this->type_id,
            'type_name'  => $this->type->name ?? '',
            'unit_id'    => $this->unit_id,
            'unit_name'  => $this->unit->UnitName ?? '',
            'code'       => $this->code,
            'quantity'   => (float) $this->quantity,
            'price'      => (float) $this->price,
            'discount'   => (float) $this->discount,
            'total'      => (float) $this->total,
        ];
    }
}