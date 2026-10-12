<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'Items';
    protected $primaryKey = 'itemID';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'itemName2',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // إذا أردت إضافة سكوب للأصناف النشطة
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}