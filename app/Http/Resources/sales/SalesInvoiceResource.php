<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'header' => [
                'sales_invoice_id'     => $this->sales_invoice_id,
                'invoice_number'       => $this->invoice_number,
                'invoice_date'         => optional($this->invoice_date)->format('Y-m-d'),
                'account_id'           => $this->account_id,
                'customer_name'        => $this->customerAccount->accName ?? '',
                'payment_account_id'   => $this->payment_account_id,
                'payment_account_name' => $this->paymentAccount->accName ?? '',
                'coin_id'              => $this->coin_id,
                'coin_name'            => $this->coin->coinsName ?? '',
                'coin_code'            => $this->coin->coinsCode ?? '',
                'exchange_rate'        => (float) $this->exchange_rate,
                'payment_method'       => (int) $this->payment_method,
                'items_total'          => (float) $this->items_total,
                'discount_total'       => (float) $this->discount_total,
                'statement'            => $this->statement,
                'reference'            => $this->reference,
                'total'                => (float) $this->total_in_invoice_currency,
                'total_in_base'        => (float) $this->total_in_local_currency,

                // ✅ حقول الترحيل والتتبع
                'is_posted'            => (bool) $this->is_posted,
                'posted_at'            => optional($this->posted_at)->format('Y-m-d H:i'),
                'posted_by'            => $this->posted_by,
                'created_by'           => $this->created_by,
                'updated_by'           => $this->updated_by,
                'entryID'              => $this->entryID,

                // ✅ هل لها مرتجعات؟
                'has_returns'          => $this->returns()->exists(),
            ],
            'details' => SalesInvoiceDetailResource::collection(
                $this->whenLoaded('details')
            ),
        ];
    }
}