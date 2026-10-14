<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'header' => [
                // المعرّفات
                'receipt_id'      => $this->receiptID,
                'voucher_number'  => $this->voucherNumber,
                'voucher_date'    => optional($this->voucherDate)->format('Y-m-d'),

                // الأطراف
                'debit_account_id'    => $this->debitAccountID,
                'debit_account_name'  => $this->debitAccount->accName ?? '',
                'debit_account_code'  => $this->debitAccount->accCode ?? '',
                'credit_account_id'   => $this->creditAccountID,
                'credit_account_name' => $this->creditAccount->accName ?? '',
                'credit_account_code' => $this->creditAccount->accCode ?? '',

                // العملة
                'coin_id'       => $this->coinsID,
                'coin_name'     => $this->currency->coinsName ?? '',
                'coin_code'     => $this->currency->coinsCode ?? '',
                'exchange_rate' => (float) $this->exchangeRate,

                // المبالغ
                'amount'       => (float) $this->amount,
                'local_amount' => (float) $this->localAmount,

                // طريقة الدفع
                'payment_method'       => (int) $this->paymentMethod,
                'payment_method_label' => $this->payment_method_label,
                'cheque_number'        => $this->chequeNumber,

                // نصوص
                'notes' => $this->notes,

                // الترحيل
                'entryID'   => $this->entryID,
                'is_posted' => (bool) $this->is_posted,
                'posted_at' => optional($this->posted_at)->format('Y-m-d H:i'),
                'posted_by' => $this->posted_by,

                // التتبع
                'created_by' => $this->created_by,
                'updated_by' => $this->updated_by,
            ],
        ];
    }
}