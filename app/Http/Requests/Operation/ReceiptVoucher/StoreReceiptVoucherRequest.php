<?php

namespace App\Http\Requests\Operation\ReceiptVoucher;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'creditAccountID' => [
                'required',
                'integer',
                'exists:characcount,accountID',
            ],

            'debitAccountID' => [
                'required',
                'integer',
                'exists:characcount,accountID',
            ],

            'coinsID' => [
                'required',
                'integer',
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

            'voucherNumber' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            if (
                $this->filled('creditAccountID')
                && $this->filled('debitAccountID')
                && (int) $this->creditAccountID
                    === (int) $this->debitAccountID
            ) {
                $validator->errors()->add(
                    'debitAccountID',
                    'لا يمكن أن يكون الحساب المدين هو نفس الحساب الدائن.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'creditAccountID.required' =>
                'الحساب الدائن مطلوب.',

            'creditAccountID.integer' =>
                'الحساب الدائن غير صحيح.',

            'creditAccountID.exists' =>
                'الحساب الدائن غير موجود.',

            'debitAccountID.required' =>
                'الحساب المدين مطلوب.',

            'debitAccountID.integer' =>
                'الحساب المدين غير صحيح.',

            'debitAccountID.exists' =>
                'الحساب المدين غير موجود.',

            'coinsID.required' =>
                'العملة مطلوبة.',

            'coinsID.integer' =>
                'العملة غير صحيحة.',

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

            'voucherDate.date' =>
                'تاريخ السند غير صحيح.',

            'voucherNumber.string' =>
                'رقم السند غير صحيح.',

            'voucherNumber.max' =>
                'رقم السند طويل جدًا.',

            'notes.string' =>
                'الملاحظات يجب أن تكون نصًا.',
        ];
    }
}