<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * طلب إنشاء صنف.
 *
 * ملاحظة (قرار Q2): يُسمح بتكرار أسماء الأصناف —
 * لا قاعدة unique على التطبيق ولا على قاعدة البيانات
 * (يُضاف فهرس بحث فقط على itemName2 في الهجرة 2026_10_12_000001).
 *
 * الشكل المرجعي للاستجابة عند الفشل موحّد مع عقد الواجهة الحالي:
 * { success: false, message: ..., errors: {...} } مع HTTP 422
 */
class ItemStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // شبكة أمان فقط — الصلاحيات المخططة (config/permissions.php) تُفعَّل لاحقاً
        return true;
    }

    public function rules(): array
    {
        return [
            'itemName2' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'itemName2.required' => 'اسم الصنف مطلوب',
            'itemName2.max'      => 'اسم الصنف لا يجوز أن يتجاوز 255 حرفاً',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first('itemName2'),
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}