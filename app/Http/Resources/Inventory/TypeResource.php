<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تنسيق JSON لنوع الصنف.
 * أسماء الحقول تُبقى كما هي (id/name/code/is_active) حفاظاً على عقد الواجهة.
 */
class TypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'code'       => $this->code,
            'is_active'  => (int) $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
