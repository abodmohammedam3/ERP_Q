<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class OpeningBalance extends Model
{
    protected $table      = 'openingBalances';
    protected $primaryKey = 'openingBalancesID';
    public    $incrementing = true;
    protected $keyType    = 'int';
    public    $timestamps = false;

    protected $fillable = [
        'accountID',
        'coinsID',
        'opeExchangeRate',
        'opeDebit',
        'opeCredit',
        'opeFiscalYear',
        'opeData',
    ];

    protected $casts = [
        'opeExchangeRate' => 'decimal:6',
        'opeDebit'        => 'decimal:2',
        'opeCredit'       => 'decimal:2',
        'opeFiscalYear'   => 'date',
        'opeData'         => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(CharAccount::class, 'accountID', 'accountID');
    }

    public function currency()
    {
        return $this->belongsTo(Coin::class, 'coinsID', 'coinsID');
    }
}