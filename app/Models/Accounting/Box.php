<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    protected $table = 'boxes';
    protected $primaryKey = 'boxID';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'coinsID',
        'accountID',
        'boxName',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ============================================
    // العلاقات
    // ============================================

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coinsID', 'coinsID');
    }

    public function account()
    {
        return $this->belongsTo(CharAccount::class, 'accountID', 'accountID');
    }
}