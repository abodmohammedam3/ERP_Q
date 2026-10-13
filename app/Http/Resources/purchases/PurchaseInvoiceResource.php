<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'header' => [
                'purchase_invoice_id'    => $this->purchase_invoice_id,
                'invoice_number'         => $this->invoice_number,
                'invoice_date'           => optional($this->invoice_date)->format('Y-m-d'),
                'account_id'             => $this->account_id,
                'supplier_name'          => $this->supplierAccount->accName ?? '',
                'payment_account_id'     => $this->payment_account_id,
                'payment_account_name'   => $this->paymentAccount->accName ?? '',
                'coin_id'                => $this->coin_id,
                'coin_name'              => $this->coin->coinsName ?? '',
                'coin_code'              => $this->coin->coinsCode ?? '',
                'warehouse_id'           => $this->warehouse_id,
                'warehouse_name'         => $this->warehouse->StockName ?? '',
                'exchange_rate'          => (float) $this->exchange_rate,
                'payment_method'         => (int) $this->payment_method,
                'items_total'            => (float) $this->items_total,
                'discount_total'         => (float) $this->discount_total,
                'expenses'               => (float) $this->expenses,
                'tax_cost'               => (float) $this->tax_cost,
                'transportation'         => (float) $this->transportation,
                'other_cost'             => (float) $this->other_cost,
                'other_cost_description' => $this->other_cost_description,
                'statement'              => $this->statement,
                'reference'              => $this->reference,
                'total'                  => (float) $this->total_in_invoice_currency,
                'total_in_base'          => (float) ($this->total_in_base_currency ?? 0),
                'is_posted'              => (bool) $this->is_posted,
                'posted_at'              => optional($this->posted_at)->format('Y-m-d H:i'),
                'posted_by'              => $this->posted_by,
                'created_by'             => $this->created_by,
                'updated_by'             => $this->updated_by,
                'entryID'                => $this->entryID,
                'has_returns'            => (bool) $this->returns()->exists(),  // ✅ جديد
            ],
            'details' => PurchaseInvoiceDetailResource::collection(
                $this->whenLoaded('details')
            ),
        ];
    }
}