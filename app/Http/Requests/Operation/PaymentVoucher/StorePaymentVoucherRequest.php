<?php

namespace App\Http\Requests\Operation\PaymentVoucher;

use Illuminate\Validation\Validator;

class StorePaymentVoucherRequest extends BasePaymentVoucherRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'voucherNumber' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9]+$/',
                'unique:payment_vouchers,voucherNumber',
            ],
        ]);
    }

    public function messages(): array
    {
        return self::commonMessages();
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            $this->runCommonValidation($v);
        });
    }
}