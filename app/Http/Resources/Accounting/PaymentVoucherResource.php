<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource موحّد لسندات الصرف.
 *
 * الاستخدام:
 *   - single:  new PaymentVoucherResource($voucher)
 *   - multiple: PaymentVoucherResource::collection($rows)
 *
 * ملاحظات:
 *   - يعتمد على eager loading — استخدم ->with() في Service.
 *   - الحقول snake_case في DB تُحوَّل إلى camelCase في JSON.
 *   - يُرجع right shape لـ JS (بدون wrapper إضافي).
 */
class PaymentVoucherResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ═══════════════════════════════════════════════════
            // المعرّفات — aliases للتوافق
            // ═══════════════════════════════════════════════════
            'id'                     => (int) $this->paymentID,
            'paymentID'              => (int) $this->paymentID,

            // ═══════════════════════════════════════════════════
            // الأساسيات
            // ═══════════════════════════════════════════════════
            'voucherNumber'          => (string) $this->voucherNumber,
            'voucherDate'            => $this->voucherDate
                ? $this->voucherDate->format('Y-m-d')
                : null,

            // ═══════════════════════════════════════════════════
            // المستفيد (مدين)
            // ═══════════════════════════════════════════════════
            'beneficiaryAccountID'   => (int) $this->beneficiaryAccountID,
            'beneficiaryAccountCode' => $this->beneficiaryAccount?->accCode,
            'beneficiaryAccountName' => $this->beneficiaryAccount?->accName,
            'beneficiaryNature'      => (int) ($this->beneficiaryAccount?->nature ?? 0),

            // alias legacy للتوافق
            'beneficiaryName'        => $this->beneficiaryAccount?->accName,

            // ═══════════════════════════════════════════════════
            // حساب الدفع (دائن)
            // ═══════════════════════════════════════════════════
            'paymentAccountID'       => (int) $this->paymentAccountID,
            'paymentAccountCode'     => $this->paymentAccount?->accCode,
            'paymentAccountName'     => $this->paymentAccount?->accName,
            'paymentAccountNature'   => (int) ($this->paymentAccount?->nature ?? 0),

            // ═══════════════════════════════════════════════════
            // العملة
            // ═══════════════════════════════════════════════════
            'coinsID'                => (int) $this->coinsID,
            'currencyID'             => (int) $this->coinsID,   // alias
            'currencyCode'           => $this->currency?->coinsCode,
            'currencyName'           => $this->currency?->coinsName,

            // ═══════════════════════════════════════════════════
            // المبالغ
            // ═══════════════════════════════════════════════════
            'amount'                 => (float) $this->amount,
            'exchangeRate'           => (float) $this->exchangeRate,
            'localAmount'            => (float) $this->localAmount,

            // ═══════════════════════════════════════════════════
            // طريقة الدفع
            // ═══════════════════════════════════════════════════
            'paymentMethod'          => (int) $this->paymentMethod,
            'paymentMethodText'      => $this->payment_method_label,

            // ═══════════════════════════════════════════════════
            // القيد والملاحظات
            // ═══════════════════════════════════════════════════
            'notes'                  => $this->notes,
            'entryID'                => $this->entryID ? (int) $this->entryID : null,
            'entryNo'                => $this->entry?->entryNo,

            // ═══════════════════════════════════════════════════
            // الحالة
            // ═══════════════════════════════════════════════════
            'isPosted'               => (bool) $this->is_posted,
            'postedAt'               => $this->posted_at
                ? $this->posted_at->format('Y-m-d H:i')
                : null,
            'createdBy'              => $this->created_by,
            'updatedBy'              => $this->updated_by,
        ];
    }

    /**
     * بيانات إضافية تُضاف خارج "data".
     * (اختياري — للـ meta)
     */
    public function with(Request $request): array
    {
        return [];
    }
}