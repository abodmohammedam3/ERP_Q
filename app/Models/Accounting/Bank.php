<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $table = 'banks';
    protected $primaryKey = 'bankID';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'bankName',
        'accountID',
        'coinsID',
        'accountNumber',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(CharAccount::class, 'accountID', 'accountID');
    }

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coinsID', 'coinsID');
    }
}