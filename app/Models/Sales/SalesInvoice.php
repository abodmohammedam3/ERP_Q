<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;

class SalesInvoice extends Model
{
    protected $table = 'sales_invoices';
    protected $primaryKey = 'sales_invoice_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'account_id',
        'payment_account_id',
        'coin_id',
        'exchange_rate',
        'payment_method',
        'items_total',
        'discount_total',
        'statement',
        'reference',
        'total_in_base_currency',
        'entryID',
        'created_by',
        'updated_by',
        'is_posted',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'invoice_date'           => 'date',
        'exchange_rate'          => 'decimal:6',
        'items_total'            => 'decimal:6',
        'discount_total'         => 'decimal:6',
        'total_in_base_currency' => 'decimal:6',
        'payment_method'         => 'integer',
        'entryID'                => 'integer',
        'created_by'             => 'integer',
        'updated_by'             => 'integer',
        'is_posted'              => 'boolean',
        'posted_at'              => 'datetime',
        'posted_by'              => 'integer',
    ];

    // ============================================
    // ثوابت طريقة الدفع
    // ============================================
    const PAYMENT_CREDIT = 1;  // أجل
    const PAYMENT_CASH   = 2;  // نقدي (صندوق)
    const PAYMENT_BANK   = 3;  // بنك

    // ============================================
    // ثوابت نوع المستند للقيود
    // ============================================
    const DOC_TYPE_SALES_CREDIT = 'sales_invoice_credit';
    const DOC_TYPE_SALES_CASH   = 'sales_invoice_cash';
    const DOC_TYPE_SALES_BANK   = 'sales_invoice_bank';

    // ============================================
    // العلاقات
    // ============================================

    public function customerAccount()
    {
        return $this->belongsTo(CharAccount::class, 'account_id', 'accountID');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(CharAccount::class, 'payment_account_id', 'accountID');
    }

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coin_id', 'coinsID');
    }

    public function details()
    {
        return $this->hasMany(
            SalesInvoiceDetail::class,
            'sales_invoice_id',
            'sales_invoice_id'
        );
    }

    public function returns()
    {
        return $this->hasMany(
            \App\Models\Sales\SalesReturn::class,
            'original_sales_invoice_id',
            'sales_invoice_id'
        );
    }

    // ============================================
    // Accessors
    // ============================================

    public function getTotalInInvoiceCurrencyAttribute(): float
    {
        return (float) $this->items_total - (float) $this->discount_total;
    }

    public function getTotalInLocalCurrencyAttribute(): float
    {
        if (!empty($this->attributes['total_in_base_currency'])) {
            return (float) $this->attributes['total_in_base_currency'];
        }

        return $this->total_in_invoice_currency
            * (float) ($this->exchange_rate ?: 1);
    }

    public function getJournalDocTypeAttribute(): string
    {
        return match ((int) $this->payment_method) {
            self::PAYMENT_CREDIT => self::DOC_TYPE_SALES_CREDIT,
            self::PAYMENT_CASH   => self::DOC_TYPE_SALES_CASH,
            self::PAYMENT_BANK   => self::DOC_TYPE_SALES_BANK,
            default              => 'sales_invoice',
        };
    }

    public function getJournalDocTypeLabelAttribute(): string
    {
        return match ((int) $this->payment_method) {
            self::PAYMENT_CREDIT => 'فاتورة بيع أجل',
            self::PAYMENT_CASH   => 'فاتورة بيع نقدي',
            self::PAYMENT_BANK   => 'فاتورة بيع بنك',
            default              => 'فاتورة بيع',
        };
    }

    public static function paymentMethodLabels(): array
    {
        return [
            self::PAYMENT_CREDIT => 'أجل',
            self::PAYMENT_CASH   => 'نقدي',
            self::PAYMENT_BANK   => 'بنك',
        ];
    }

    public function isPosted(): bool
    {
        return (bool) $this->is_posted;
    }

    public function isDraft(): bool
    {
        return ! $this->isPosted();
    }
}