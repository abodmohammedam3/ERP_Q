<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class PaymentVoucher extends Model
{
    protected $table      = 'payment_vouchers';
    protected $primaryKey = 'paymentID';

    // ⭐ لا يوجد timestamps (نفس نمط سندات القبض)
    public $timestamps = false;

    protected $fillable = [
        'voucherNumber',
        'voucherDate',
        'creditAccountID',
        'debitAccountID',
        'coinsID',
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
     * الحساب الدائن (الصندوق/البنك) — النقدية تخرج منه
     */
    public function creditAccount()
    {
        return $this->belongsTo(CharAccount::class, 'creditAccountID', 'accountID');
    }

    /**
     * الحساب المدين (المورد) — النقدية تدخل إليه
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
}