<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sales_invoice_detail_id' => $this->sales_invoice_detail_id,
            'item_id'                 => $this->item_id,
            'item_name'               => $this->item->itemName2 ?? '',
            'type_id'                 => $this->type_id,
            'type_name'               => $this->type->name ?? '',
            'unit_id'                 => $this->unit_id,
            'unit_name'               => $this->unit->UnitName ?? '',
            'warehouse_id'            => $this->warehouse_id,
            'warehouse_name'          => $this->warehouse->StockName ?? '',
            'code'                    => $this->code,
            'quantity'                => (float) $this->quantity,
            'price'                   => (float) $this->price,
            'cost_price'              => (float) $this->cost_price,
            'discount'                => (float) $this->discount,
            'total'                   => (float) $this->total,
        ];
    }
}