<?php

namespace App\Http\Requests\Operation\ReceiptVoucher;

use App\Models\Accounting\CharAccount;
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
                'max:999999999999.99',
            ],

            'exchangeRate' => [
                'required',
                'numeric',
                'gt:0',
            ],

            /* 2 = نقدي، 3 = بنك */
            'paymentMethod' => [
                'required',
                'integer',
                'in:2,3',
            ],

            'chequeNumber' => [
                'nullable',
                'string',
                'max:50',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
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

            /* 1) المدين ≠ الدائن */
            if (
                $this->filled('creditAccountID')
                && $this->filled('debitAccountID')
                && (int) $this->creditAccountID === (int) $this->debitAccountID
            ) {
                $validator->errors()->add(
                    'debitAccountID',
                    'لا يمكن أن يكون الحساب المدين هو نفس الحساب الدائن.'
                );
            }

            /* 2) الحساب الدائن يجب أن يقبل الترحيل (isPostable=1) */
            if ($this->filled('creditAccountID')) {
                $isPostable = CharAccount::query()
                    ->where('accountID', $this->creditAccountID)
                    ->where('IsActive', 1)
                    ->where('isPostable', 1)
                    ->exists();

                if (! $isPostable) {
                    $validator->errors()->add(
                        'creditAccountID',
                        'الحساب الدائن يجب أن يكون حساباً نشطاً يقبل الترحيل.'
                    );
                }
            }

            /* 3) التاريخ ليس في المستقبل */
            if (
                $this->filled('voucherDate')
                && $this->voucherDate > now()->toDateString()
            ) {
                $validator->errors()->add(
                    'voucherDate',
                    'تاريخ السند لا يمكن أن يكون في المستقبل.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'creditAccountID.required' => 'الحساب الدائن مطلوب.',
            'creditAccountID.integer'  => 'الحساب الدائن غير صحيح.',
            'creditAccountID.exists'   => 'الحساب الدائن غير موجود.',

            'debitAccountID.required' => 'الحساب المدين مطلوب.',
            'debitAccountID.integer'  => 'الحساب المدين غير صحيح.',
            'debitAccountID.exists'   => 'الحساب المدين غير موجود.',

            'coinsID.required' => 'العملة مطلوبة.',
            'coinsID.integer'  => 'العملة غير صحيحة.',
            'coinsID.exists'   => 'العملة غير موجودة.',

            'amount.required' => 'المبلغ مطلوب.',
            'amount.numeric'  => 'المبلغ يجب أن يكون رقمًا.',
            'amount.min'      => 'المبلغ يجب أن يكون أكبر من صفر.',
            'amount.max'      => 'المبلغ كبير جدًا.',

            'exchangeRate.required' => 'سعر الصرف مطلوب.',
            'exchangeRate.numeric'  => 'سعر الصرف يجب أن يكون رقمًا.',
            'exchangeRate.gt'       => 'سعر الصرف يجب أن يكون أكبر من صفر.',

            'paymentMethod.required' => 'طريقة القبض مطلوبة.',
            'paymentMethod.integer'  => 'طريقة القبض غير صحيحة.',
            'paymentMethod.in'       => 'طريقة القبض غير صحيحة.',

            'chequeNumber.string' => 'رقم الشيك غير صحيح.',
            'chequeNumber.max'    => 'رقم الشيك طويل جدًا.',

            'notes.string' => 'الملاحظات يجب أن تكون نصًا.',
            'notes.max'    => 'الملاحظات طويلة جدًا.',

            'voucherDate.date' => 'تاريخ السند غير صحيح.',

            'voucherNumber.string' => 'رقم السند غير صحيح.',
            'voucherNumber.max'    => 'رقم السند طويل جدًا.',
        ];
    }
}