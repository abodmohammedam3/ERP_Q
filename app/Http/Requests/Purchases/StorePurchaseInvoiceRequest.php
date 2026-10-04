<?php

namespace App\Http\Requests\Purchases;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * التحقق من بيانات فاتورة الشراء (إنشاء/تعديل).
 *
 * نُقل إليه منطق التحقق الذي كان داخل
 * PurchaseInvoiceController::validateInvoice() (~95 سطرًا)،
 * مع الحفاظ على نفس القواعد والرسائل العربية تمامًا.
 */
class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number'         => ['required', 'string', 'max:50'],
            'invoice_date'           => ['required', 'date'],
            'account_id'             => ['required', 'exists:characcount,accountID'],
            'payment_method'         => ['required', 'integer', 'in:1,2,3,4'],
            'coin_id'                => ['required', 'exists:coins,coinsID'],
            'warehouse_id'           => ['required', 'exists:stocks,StockID'],
            'exchange_rate'          => ['required', 'numeric', 'gt:0'],
            'payment_account_id'     => ['nullable', 'exists:characcount,accountID'],
            'expenses'               => ['nullable', 'numeric', 'min:0'],
            'tax_cost'               => ['nullable', 'numeric', 'min:0'],
            'transportation'         => ['nullable', 'numeric', 'min:0'],
            'other_cost'             => ['nullable', 'numeric', 'min:0'],
            'other_cost_description' => ['nullable', 'string'],
            'statement'              => ['nullable', 'string'],
            'reference'              => ['nullable', 'string'],

            'details'            => ['required', 'array', 'min:1'],
            'details.*.item_id'  => ['required', 'exists:Items,itemID'],
            'details.*.type_id'  => ['nullable', 'exists:type,id'],
            'details.*.unit_id'  => ['nullable', 'exists:units,UnitID'],
            'details.*.code'     => ['nullable', 'string', 'max:50'],
            'details.*.quantity' => ['required', 'numeric', 'gt:0'],
            'details.*.price'    => ['required', 'numeric', 'gt:0'],
            'details.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_number.required' => 'رقم الفاتورة مطلوب',
            'invoice_date.required'   => 'تاريخ الفاتورة مطلوب',
            'account_id.required'     => 'يجب اختيار المورد',
            'account_id.exists'       => 'المورد المحدد غير موجود',
            'payment_method.required' => 'طريقة الدفع مطلوبة',
            'coin_id.required'        => 'يجب اختيار العملة',
            'coin_id.exists'          => 'العملة المحددة غير موجودة',
            'warehouse_id.required'   => 'يجب اختيار المخزن',
            'warehouse_id.exists'     => 'المخزن المحدد غير موجود',

            'details.required' => 'يجب إضافة صنف واحد على الأقل',
            'details.min'      => 'يجب إضافة صنف واحد على الأقل',

            'details.*.item_id.required' => 'يجب اختيار الصنف في كل الصفوف',
            'details.*.item_id.exists'   => 'أحد الأصناف المحددة غير موجود',
            'details.*.quantity.gt'      => 'الكمية يجب أن تكون أكبر من صفر',
            'details.*.price.gt'         => 'سعر الوحدة يجب أن يكون أكبر من صفر',
        ];
    }

    /**
     * تحققات ما بعد القواعد:
     *  - منطق طريقة الدفع مقابل حساب الدفع.
     *  - منطق الخصم لكل صف.
     *  - إلزام وصف التكلفة الأخرى عند وجود قيمة.
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

            $otherCost = (float) $this->input('other_cost', 0);
            $otherDesc = trim((string) $this->input('other_cost_description', ''));

            if ($otherCost > 0 && $otherDesc === '') {
                $v->errors()->add(
                    'other_cost_description',
                    'يجب إدخال وصف التكلفة الأخرى'
                );
            }
        });
    }

    /**
     * الحفاظ على شكل استجابة الأخطاء الذي تتوقعه الواجهة.
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