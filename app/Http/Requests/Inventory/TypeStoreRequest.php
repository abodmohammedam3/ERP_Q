<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * طلب إنشاء نوع صنف.
 *
 * الشكل المرجعي للاستجابة عند الفشل موحّد مع عقد الواجهة الحالي:
 * { success: false, message: ..., errors: {...} } مع HTTP 422
 */
class TypeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // شبكة أمان فقط — الصلاحيات المخططة (config/permissions.php) تُفعَّل لاحقاً
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:type,name'],
            'code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم النوع مطلوب',
            'name.unique'   => 'هذا النوع موجود بالفعل',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first('name'),
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
