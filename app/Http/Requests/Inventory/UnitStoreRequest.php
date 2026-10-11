<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * طلب إنشاء وحدة قياس.
 *
 * الشكل المرجعي للاستجابة عند الفشل موحّد مع عقد الواجهة الحالي:
 * { success: false, message: ..., errors: {...} } مع HTTP 422
 */
class UnitStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // شبكة أمان فقط — الصلاحيات المخططة (config/permissions.php) تُفعَّل لاحقاً
        return true;
    }

    public function rules(): array
    {
        return [
            'UnitName' => ['required', 'string', 'max:255', 'unique:units,UnitName'],
        ];
    }

    public function messages(): array
    {
        return [
            'UnitName.required' => 'اسم الوحدة مطلوب',
            'UnitName.unique'   => 'هذه الوحدة موجودة بالفعل',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first('UnitName'),
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
