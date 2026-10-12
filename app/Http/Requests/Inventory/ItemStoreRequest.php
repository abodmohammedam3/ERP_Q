<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * طلب إنشاء صنف.
 *
 * قرار المستخدم: الإبقاء على قاعدة unique على itemName2
 * (نقض قرار Q2 السابق «التكرار مسموح»).
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
            'itemName2' => ['required', 'string', 'max:255', 'unique:Items,itemName2'],
        ];
    }

    public function messages(): array
    {
        return [
            'itemName2.required' => 'اسم الصنف مطلوب',
            'itemName2.unique'   => 'هذا الصنف موجود بالفعل',
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