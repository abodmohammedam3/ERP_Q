<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class Coin extends Model
{
    protected $table = 'coins';
    protected $primaryKey = 'coinsID';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'coinsName',
        'coinsCode',
        'coinsExchangeRate',
        'coinsSystem',
        'is_active',
    ];

    protected $casts = [
        'coinsSystem'       => 'boolean',
        'is_active'         => 'boolean',
        'coinsExchangeRate' => 'decimal:6',
    ];

    // ============================================
    // العلاقات
    // ============================================

    public function banks()
    {
        return $this->hasMany(\App\Models\Accounting\Bank::class, 'coinsID', 'coinsID');
    }

    public function boxes()
    {
        return $this->hasMany(\App\Models\Accounting\Box::class, 'coinsID', 'coinsID');
    }
}