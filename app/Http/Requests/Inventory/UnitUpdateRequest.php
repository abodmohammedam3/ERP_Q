<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * طلب تحديث وحدة قياس.
 */
class UnitUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // شبكة أمان فقط — الصلاحيات المخططة (config/permissions.php) تُفعَّل لاحقاً
        return true;
    }

    public function rules(): array
    {
        // route('unit') هنا نص خام (لا Implicit Binding لأن الـ Controller يستقبل $id)
        $unitId = (int) $this->route('unit');

        return [
            'UnitName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'UnitName')->ignore($unitId, 'UnitID'),
            ],
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
