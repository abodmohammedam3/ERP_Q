<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تنسيق JSON للصنف.
 * أسماء الحقول تُبقى كما هي (itemID/itemName2/is_active) حفاظاً على عقد الواجهة.
 */
class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'itemID'     => $this->itemID,
            'itemName2'  => $this->itemName2,
            'is_active'  => (int) $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}