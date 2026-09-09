<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    protected $table = 'type';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];
}