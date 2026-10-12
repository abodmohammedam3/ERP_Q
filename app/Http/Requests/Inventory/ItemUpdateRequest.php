<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Validation\Rule;

/**
 * طلب تعديل صنف — نفس قواعد الإنشاء مع تجاهل الصنف الحالي في قاعدة unique.
 *
 * قرار المستخدم: الإبقاء على unique على itemName2.
 */
class ItemUpdateRequest extends ItemStoreRequest
{
    public function rules(): array
    {
        // route('item') هنا نص خام (لا Implicit Binding لأن الـ Controller يستقبل $id)
        $itemId = (int) $this->route('item');

        return [
            'itemName2' => [
                'required',
                'string',
                'max:255',
                Rule::unique('Items', 'itemName2')->ignore($itemId, 'itemID'),
            ],
        ];
    }
}