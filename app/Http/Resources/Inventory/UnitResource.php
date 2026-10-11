<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تنسيق JSON لوحدة القياس.
 * أسماء الحقول تُبقى كما هي (UnitID/UnitName/is_active) حفاظاً على عقد الواجهة.
 */
class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'UnitID'     => $this->UnitID,
            'UnitName'   => $this->UnitName,
            'is_active'  => (int) $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
