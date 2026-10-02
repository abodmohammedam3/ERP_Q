<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalance extends Model
{
    protected $table = 'account_balances';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'accountID',
        'coinsID',
        'debitTotal',
        'creditTotal',
        'balance',
        'lastUpdatedAt',
    ];

    protected $casts = [
        'accountID'     => 'integer',
        'coinsID'       => 'integer',
        'debitTotal'    => 'decimal:2',
        'creditTotal'   => 'decimal:2',
        'balance'       => 'decimal:2',
        'lastUpdatedAt' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            CharAccount::class,
            'accountID',
            'accountID'
        );
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(
            Coin::class,
            'coinsID',
            'coinsID'
        );
    }
}