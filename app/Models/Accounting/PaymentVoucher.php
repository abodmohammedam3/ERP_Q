<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class PaymentVoucher extends Model
{
    protected $table      = 'payment_vouchers';
    protected $primaryKey = 'paymentID';

    public $timestamps = false;

    protected $fillable = [
        'voucherNumber',
        'voucherDate',
        'beneficiaryAccountID',
        'paymentAccountID',
        'coinsID',
        'entryID',
        'amount',
        'exchangeRate',
        'localAmount',
        'paymentMethod',
        'notes',
    ];

    protected $casts = [
        'voucherDate'  => 'date',
        'amount'       => 'decimal:2',
        'exchangeRate' => 'decimal:6',
        'localAmount'  => 'decimal:2',
    ];

    public function beneficiaryAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'beneficiaryAccountID',
            'accountID'
        );
    }

    public function paymentAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'paymentAccountID',
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