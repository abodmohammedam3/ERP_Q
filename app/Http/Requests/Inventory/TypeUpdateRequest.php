<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * طلب تحديث نوع صنف.
 */
class TypeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // شبكة أمان فقط — الصلاحيات المخططة (config/permissions.php) تُفعَّل لاحقاً
        return true;
    }

    public function rules(): array
    {
        // route('type') هنا نص خام (لا Implicit Binding لأن الـ Controller يستقبل $id)
        $typeId = (int) $this->route('type');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('type', 'name')->ignore($typeId, 'id'),
            ],
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
