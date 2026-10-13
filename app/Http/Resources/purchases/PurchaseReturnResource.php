<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'header' => [
                'purchase_return_id'           => $this->purchase_return_id,
                'return_number'                => $this->return_number,
                'return_date'                  => optional($this->return_date)->format('Y-m-d'),
                'original_purchase_invoice_id' => $this->original_purchase_invoice_id,
                'original_invoice_number'      => $this->originalInvoice->invoice_number ?? '',
                'account_id'                   => $this->account_id,
                'supplier_name'                => $this->supplierAccount->accName ?? '',
                'payment_account_id'           => $this->payment_account_id,
                'payment_account_name'         => $this->paymentAccount->accName ?? '',
                'coin_id'                      => $this->coin_id,
                'coin_name'                    => $this->coin->coinsName ?? '',
                'coin_code'                    => $this->coin->coinsCode ?? '',
                'warehouse_id'                 => $this->warehouse_id,
                'warehouse_name'               => $this->warehouse->StockName ?? '',
                'exchange_rate'                => (float) $this->exchange_rate,
                'payment_method'               => (int) $this->payment_method,
                'items_total'                  => (float) $this->items_total,
                'discount_total'               => (float) $this->discount_total,
                'statement'                    => $this->statement,
                'reference'                    => $this->reference,
                'total'                        => (float) $this->total_in_return_currency,
                'total_in_base'                => (float) $this->total_in_local_currency,

                // حقول الترحيل والتتبع
                'is_posted'                    => (bool) $this->is_posted,
                'posted_at'                    => optional($this->posted_at)->format('Y-m-d H:i'),
                'posted_by'                    => $this->posted_by,
                'created_by'                   => $this->created_by,
                'updated_by'                   => $this->updated_by,
                'entryID'                      => $this->entryID,
            ],
            'details' => PurchaseReturnDetailResource::collection(
                $this->whenLoaded('details')
            ),
        ];
    }
}