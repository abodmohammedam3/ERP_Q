<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentVoucher extends Model
{
    protected $table      = 'payment_vouchers';
    protected $primaryKey = 'paymentID';
    public $timestamps    = false;

    protected $fillable = [
        'voucherNumber', 'voucherDate',
        'beneficiaryAccountID', 'paymentAccountID',
        'coinsID', 'entryID',
        'amount', 'exchangeRate', 'localAmount',
        'paymentMethod', 'notes',
        'created_by', 'updated_by',
        'is_posted', 'posted_at', 'posted_by',
    ];

    protected $casts = [
        'voucherDate'   => 'date',
        'amount'        => 'decimal:2',
        'exchangeRate'  => 'decimal:6',
        'localAmount'   => 'decimal:2',
        'entryID'       => 'integer',
        'paymentMethod' => 'integer',
        'created_by'    => 'integer',
        'updated_by'    => 'integer',
        'is_posted'     => 'boolean',
        'posted_at'     => 'datetime',
        'posted_by'     => 'integer',
    ];

    // ─── الثوابت ──────────────────────────────────────────────────
    const PAYMENT_CASH = 2;
    const PAYMENT_BANK = 3;

    const DOC_TYPE_PAYMENT_CASH = 'payment_voucher_cash';
    const DOC_TYPE_PAYMENT_BANK = 'payment_voucher_bank';

    // ─── العلاقات ─────────────────────────────────────────────────
    public function beneficiaryAccount()
    {
        return $this->belongsTo(CharAccount::class, 'beneficiaryAccountID', 'accountID');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(CharAccount::class, 'paymentAccountID', 'accountID');
    }

    public function currency()
    {
        return $this->belongsTo(Coin::class, 'coinsID', 'coinsID');
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'entryID', 'entryID');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // ─── Accessors ───────────────────────────────────────────────
    public function getJournalDocTypeAttribute(): string
    {
        return match ((int) $this->paymentMethod) {
            self::PAYMENT_CASH => self::DOC_TYPE_PAYMENT_CASH,
            self::PAYMENT_BANK => self::DOC_TYPE_PAYMENT_BANK,
            default            => self::DOC_TYPE_PAYMENT_CASH,
        };
    }

    public function getJournalDocTypeLabelAttribute(): string
    {
        return match ((int) $this->paymentMethod) {
            self::PAYMENT_CASH => 'سند صرف نقدي',
            self::PAYMENT_BANK => 'سند صرف بنكي',
            default            => 'سند صرف',
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::paymentMethodLabels()[(int) $this->paymentMethod] ?? '—';
    }

    public static function paymentMethodLabels(): array
    {
        return [
            self::PAYMENT_CASH => 'نقد',
            self::PAYMENT_BANK => 'تحويل بنكي',
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────
    public function isPosted(): bool
    {
        return (bool) $this->is_posted;
    }

    public function isDraft(): bool
    {
        return ! $this->isPosted();
    }
}