<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class ReceiptVoucher extends Model
{
    protected $table      = 'receipt_vouchers';
    protected $primaryKey = 'receiptID';

    // ⭐ إلغاء timestamps لأن الجدول لا يحتوي عليها
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
        'chequeNumber',
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
     * الحساب الدائن (العميل)
     */
    public function creditAccount()
    {
        return $this->belongsTo(CharAccount::class, 'creditAccountID', 'accountID');
    }

    /**
     * الحساب المدين (الصندوق/البنك)
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