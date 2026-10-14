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
        // التتبع
        'created_by',
        'updated_by',
        // الترحيل
        'is_posted',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'voucherDate'   => 'date',
        'amount'        => 'decimal:2',
        'exchangeRate'  => 'decimal:6',
        'localAmount'   => 'decimal:2',
        'entryID'       => 'integer',
        'paymentMethod' => 'integer',
        // التتبع
        'created_by'    => 'integer',
        'updated_by'    => 'integer',
        // الترحيل
        'is_posted'     => 'boolean',
        'posted_at'     => 'datetime',
        'posted_by'     => 'integer',
    ];

    // =========================================================
    // ثوابت طريقة الدفع
    // متوافقة مع فواتير البيع (2=cash, 3=bank)
    // =========================================================
    const PAYMENT_CASH = 2;
    const PAYMENT_BANK = 3;

    // =========================================================
    // ثوابت نوع المستند للقيود
    // =========================================================
    const DOC_TYPE_RECEIPT_CASH = 'receipt_voucher_cash';
    const DOC_TYPE_RECEIPT_BANK = 'receipt_voucher_bank';

    // =========================================================
    // العلاقات
    // =========================================================

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

    // علاقات التتبع والترحيل
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function poster()
    {
        return $this->belongsTo(\App\Models\User::class, 'posted_by');
    }

    // =========================================================
    // Accessors
    // =========================================================

    public function getJournalDocTypeAttribute(): string
    {
        return match ((int) $this->paymentMethod) {
            self::PAYMENT_CASH => self::DOC_TYPE_RECEIPT_CASH,
            self::PAYMENT_BANK => self::DOC_TYPE_RECEIPT_BANK,
            default            => 'receipt_voucher',
        };
    }

    public function getJournalDocTypeLabelAttribute(): string
    {
        return match ((int) $this->paymentMethod) {
            self::PAYMENT_CASH => 'سند قبض نقدي',
            self::PAYMENT_BANK => 'سند قبض بنك',
            default            => 'سند قبض',
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::paymentMethodLabels()[(int) $this->paymentMethod] ?? '—';
    }

    public static function paymentMethodLabels(): array
    {
        return [
            self::PAYMENT_CASH => 'نقدي',
            self::PAYMENT_BANK => 'بنك',
        ];
    }

    // =========================================================
    // Methods مساعدة
    // =========================================================

    public function isPosted(): bool
    {
        return (bool) $this->is_posted;
    }

    public function isDraft(): bool
    {
        return ! $this->isPosted();
    }
}