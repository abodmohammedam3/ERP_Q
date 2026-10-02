<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class ReceiptVoucher extends Model
{
    protected $table      = 'receipt_vouchers';
    protected $primaryKey = 'receiptID';

    public $timestamps = false;

    protected $fillable = [
        'voucherNumber',
        'voucherDate',
        'creditAccountID',
        'debitAccountID',
        'coinsID',
        'entryID',
        'amount',
        'exchangeRate',
        'localAmount',
        'paymentMethod',
        'chequeNumber',
        'notes',
    ];

    protected $casts = [
        'voucherDate'  => 'date',
        'amount'       => 'decimal:2',
        'exchangeRate' => 'decimal:6',
        'localAmount'  => 'decimal:2',
        'entryID'      => 'integer',
    ];

    public function creditAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'creditAccountID',
            'accountID'
        );
    }

    public function debitAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'debitAccountID',
            'accountID'
        );
    }

    public function currency()
    {
        return $this->belongsTo(
            Coin::class,
            'coinsID',
            'coinsID'
        );
    }

    public function entry()
    {
        return $this->belongsTo(
            JournalEntry::class,
            'entryID',
            'entryID'
        );
    }
}