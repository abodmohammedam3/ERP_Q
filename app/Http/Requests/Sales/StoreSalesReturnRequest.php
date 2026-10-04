<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * التحقق من بيانات مرتجع البيع (إنشاء/تعديل).
 *
 * نُقل إليه منطق التحقق الذي كان داخل
 * SalesReturnController::validateReturn() مع الحفاظ
 * على نفس القواعد والرسائل العربية تماماً.
 */
class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_number'             => ['required', 'string', 'max:50'],
            'return_date'               => ['required', 'date'],
            'original_sales_invoice_id' => ['required', 'exists:sales_invoices,sales_invoice_id'],
            'account_id'                => ['required', 'exists:characcount,accountID'],
            'payment_method'            => ['required', 'integer', 'in:1,2,3,4'],
            'coin_id'                   => ['required', 'exists:coins,coinsID'],
            'exchange_rate'             => ['nullable', 'numeric', 'gt:0'],
            'payment_account_id'        => ['nullable', 'exists:characcount,accountID'],
            'statement'                 => ['nullable', 'string'],
            'reference'                 => ['nullable', 'string'],

            'details'                           => ['required', 'array', 'min:1'],
            'details.*.sales_invoice_detail_id' => ['required', 'exists:sales_invoice_details,sales_invoice_detail_id'],
            'details.*.quantity'                => ['required', 'numeric', 'gt:0'],
            'details.*.price'                   => ['required', 'numeric', 'gt:0'],
            'details.*.discount'                => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'return_number.required'             => 'رقم المرتجع مطلوب',
            'return_date.required'               => 'تاريخ المرتجع مطلوب',
            'original_sales_invoice_id.required' => 'يجب اختيار الفاتورة الأصلية',
            'original_sales_invoice_id.exists'   => 'الفاتورة الأصلية غير موجودة',
            'account_id.required'                => 'يجب اختيار العميل',
            'payment_method.required'            => 'طريقة الدفع مطلوبة',
            'coin_id.required'                   => 'يجب اختيار العملة',
            'exchange_rate.gt'                   => 'سعر الصرف يجب أن يكون أكبر من صفر',
            'details.required'                   => 'يجب إضافة صنف واحد على الأقل للمرتجع',
            'details.min'                        => 'يجب إضافة صنف واحد على الأقل للمرتجع',
            'details.*.sales_invoice_detail_id.required' => 'سطر الفاتورة الأصلية مطلوب في كل الصفوف',
            'details.*.quantity.gt'              => 'الكمية المراد إرجاعها يجب أن تكون أكبر من صفر',
        ];
    }

    /**
     * تحققات ما بعد القواعد: منطق طريقة الدفع (نفس منطق الفواتير).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $method    = (int) $this->input('payment_method');
            $accountId = $this->input('payment_account_id');

            if ($method === 1 && !empty($accountId)) {
                $v->errors()->add(
                    'payment_account_id',
                    'طريقة الدفع "أجل" لا تحتاج إلى حساب دفع'
                );
            }

            if (in_array($method, [2, 3, 4], true) && empty($accountId)) {
                $v->errors()->add(
                    'payment_account_id',
                    'يجب اختيار حساب الدفع'
                );
            }
        });
    }

    /**
     * الحفاظ على شكل استجابة الأخطاء الذي تتوقعه الواجهة:
     * { message, errors } مع حالة 422.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
