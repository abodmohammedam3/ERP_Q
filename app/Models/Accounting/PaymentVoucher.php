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
        'creditAccountID',
        'debitAccountID',
        'coinsID',
        'entryID',          // ✅ جديد
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

    // ══════════════════════════════════════════════════════════
    //  العلاقات
    // ══════════════════════════════════════════════════════════

    /**
     * الحساب الدائن (الصندوق/البنك)
     */
    public function creditAccount()
    {
        return $this->belongsTo(CharAccount::class, 'creditAccountID', 'accountID');
    }

    /**
     * الحساب المدين (المورد)
     */
    public function debitAccount()
    {
        return $this->belongsTo(CharAccount::class, 'debitAccountID', 'accountID');
    }

    /**
     * العملة
     */
    public function currency()
    {
        return $this->belongsTo(Coin::class, 'coinsID', 'coinsID');
    }

    /**
     * ✅ القيد المحاسبي المرتبط
     */
    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'entryID', 'entryID');
    }
}