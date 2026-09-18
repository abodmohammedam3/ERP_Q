<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $table      = 'JournalEntrryLine';
    protected $primaryKey = 'entryLineID';
    public    $timestamps = false;

    protected $fillable = [
        'entryID',
        'accountID',
        'coinsID',
        'description2',
        'exchangRate',
        'debit',
        'credit',
        'localDebit',
        'localCredit',
    ];

    protected $casts = [
        'exchangRate' => 'decimal:6',
        'debit'       => 'decimal:2',
        'credit'      => 'decimal:2',
        'localDebit'  => 'decimal:2',
        'localCredit' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CharAccount::class, 'accountID', 'accountID');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Coin::class, 'coinsID', 'coinsID');
    }
}