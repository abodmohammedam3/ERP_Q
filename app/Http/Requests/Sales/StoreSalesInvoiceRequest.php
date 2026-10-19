<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * التحقق من بيانات فاتورة البيع (إنشاء/تعديل).
 *
 * 3 طرق دفع: أجل (1) / نقدي (2) / بنك (3)
 */
class StoreSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number'     => ['required', 'string', 'max:50'],
            'invoice_date'       => ['required', 'date'],
            'account_id'         => ['required', 'exists:characcount,accountID'],
            'payment_method'     => ['required', 'integer', 'in:1,2,3'],
            'coin_id'            => ['required', 'exists:coins,coinsID'],
            /* ✅ سعر الصرف خاص بالفاتورة — يجب أن يكون موجبًا (الخدمة تستخدم ?? 1 احتياطًا) */
            'exchange_rate'      => ['nullable', 'numeric', 'gt:0'],
            'payment_account_id' => ['nullable', 'exists:characcount,accountID'],
            'statement'          => ['nullable', 'string'],
            'reference'          => ['nullable', 'string'],

            'details'                => ['required', 'array', 'min:1'],
            'details.*.item_id'      => ['required', 'exists:Items,itemID'],
            'details.*.type_id'      => ['nullable', 'exists:type,id'],
            'details.*.unit_id'      => ['nullable', 'exists:units,UnitID'],
            'details.*.warehouse_id' => ['required', 'exists:stocks,StockID'],
            'details.*.code'         => ['nullable', 'string', 'max:50'],
            'details.*.quantity'     => ['required', 'numeric', 'gt:0'],
            'details.*.price'        => ['required', 'numeric', 'gt:0'],
            'details.*.cost_price'   => ['nullable', 'numeric', 'min:0'],
            'details.*.discount'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_number.required' => 'رقم الفاتورة مطلوب',
            'invoice_date.required'   => 'تاريخ الفاتورة مطلوب',
            'account_id.required'     => 'يجب اختيار العميل',
            'account_id.exists'       => 'العميل المحدد غير موجود',
            'payment_method.required' => 'طريقة الدفع مطلوبة',
            'coin_id.required'        => 'يجب اختيار العملة',
            'coin_id.exists'          => 'العملة المحددة غير موجودة',
            'exchange_rate.numeric'   => 'سعر الصرف يجب أن يكون رقمًا',
            'exchange_rate.gt'        => 'سعر الصرف يجب أن يكون أكبر من صفر',

            'details.required' => 'يجب إضافة صنف واحد على الأقل',
            'details.min'      => 'يجب إضافة صنف واحد على الأقل',

            'details.*.item_id.required'      => 'يجب اختيار الصنف في كل الصفوف',
            'details.*.item_id.exists'        => 'أحد الأصناف المحددة غير موجود',
            'details.*.warehouse_id.required' => 'يجب اختيار المخزن في كل الصفوف',
            'details.*.warehouse_id.exists'   => 'أحد المخازن المحددة غير موجود',
            'details.*.quantity.gt'           => 'الكمية يجب أن تكون أكبر من صفر',
            'details.*.price.gt'              => 'سعر الوحدة يجب أن يكون أكبر من صفر',
        ];
    }

    /**
     * تحققات ما بعد القواعد الأساسية:
     *  - منطق طريقة الدفع مقابل حساب الدفع.
     *  - منطق الخصم لكل صف.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $method    = (int) $this->input('payment_method');
            $accountId = $this->input('payment_account_id');

            // ✅ أجل: لا يحتاج حساب دفع
            if ($method === 1 && !empty($accountId)) {
                $v->errors()->add(
                    'payment_account_id',
                    'طريقة الدفع "أجل" لا تحتاج إلى حساب دفع'
                );
            }

            // ✅ نقدي (2) أو بنك (3): يجب اختيار حساب الدفع
            if (in_array($method, [2, 3], true) && empty($accountId)) {
                $v->errors()->add(
                    'payment_account_id',
                    'يجب اختيار حساب الدفع'
                );
            }

            foreach ((array) $this->input('details', []) as $i => $row) {
                $qty      = (float) ($row['quantity'] ?? 0);
                $price    = (float) ($row['price'] ?? 0);
                $discount = (float) ($row['discount'] ?? 0);

                if ($discount > $qty * $price) {
                    $v->errors()->add(
                        "details.{$i}.discount",
                        'الخصم لا يمكن أن يتجاوز قيمة الصف'
                    );
                }

                if (max(0, $qty * $price - $discount) <= 0) {
                    $v->errors()->add(
                        "details.{$i}.price",
                        'إجمالي الصف يجب أن يكون أكبر من صفر'
                    );
                }
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