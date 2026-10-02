<?php

namespace App\Http\Requests\Operation\PaymentVoucher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePaymentVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiaryAccountID' => [
                'required',
                'exists:characcount,accountID',
            ],

            'paymentAccountID' => [
                'required',
                'exists:characcount,accountID',
            ],

            'coinsID' => [
                'required',
                'exists:coins,coinsID',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'exchangeRate' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'paymentMethod' => [
                'nullable',
                'in:cash,bank',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'voucherDate' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                $this->filled('beneficiaryAccountID') &&
                $this->filled('paymentAccountID') &&
                (int) $this->input('beneficiaryAccountID')
                    === (int) $this->input('paymentAccountID')
            ) {
                $validator->errors()->add(
                    'paymentAccountID',
                    'لا يمكن أن يكون الحساب المستفيد هو نفس حساب الدفع.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'beneficiaryAccountID.required' =>
                'الحساب المستفيد مطلوب.',

            'beneficiaryAccountID.exists' =>
                'الحساب المستفيد غير موجود.',

            'paymentAccountID.required' =>
                'حساب الدفع مطلوب.',

            'paymentAccountID.exists' =>
                'حساب الدفع غير موجود.',

            'coinsID.required' =>
                'العملة مطلوبة.',

            'coinsID.exists' =>
                'العملة غير موجودة.',

            'amount.required' =>
                'المبلغ مطلوب.',

            'amount.numeric' =>
                'المبلغ يجب أن يكون رقمًا.',

            'amount.min' =>
                'المبلغ يجب أن يكون أكبر من صفر.',

            'exchangeRate.required' =>
                'سعر الصرف مطلوب.',

            'exchangeRate.numeric' =>
                'سعر الصرف يجب أن يكون رقمًا.',

            'exchangeRate.gt' =>
                'سعر الصرف يجب أن يكون أكبر من صفر.',

            'paymentMethod.in' =>
                'طريقة الدفع غير صحيحة.',

            'notes.string' =>
                'الملاحظات يجب أن تكون نصًا.',

            'voucherDate.date' =>
                'تاريخ السند غير صحيح.',
        ];
    }

    protected function failedValidation(
        Validator $validator
    ): void {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }
}