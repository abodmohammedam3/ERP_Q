<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Stock;

/**
 * رأس مرتجع البيع
 * -----------------------------------------------------
 * مستند مستقل مرتبط بالفاتورة الأصلية عبر:
 *   original_sales_invoice_id
 *
 * لا يُعدَّل أي شيء على الفاتورة الأصلية.
 */
class SalesReturn extends Model
{
    protected $table = 'sales_returns';
    protected $primaryKey = 'sales_return_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'return_number',
        'return_date',
        'original_sales_invoice_id',
        'account_id',
        'payment_account_id',
        'coin_id',
        'warehouse_id',
        'exchange_rate',
        'payment_method',
        'items_total',
        'discount_total',
        'statement',
        'reference',
        'entryID',
        'created_by',
        'updated_by',
        'is_posted',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'return_date'     => 'date',
        'exchange_rate'   => 'decimal:6',
        'items_total'     => 'decimal:6',
        'discount_total'  => 'decimal:6',
        'payment_method'  => 'integer',
        'entryID'         => 'integer',
        'created_by'      => 'integer',
        'updated_by'      => 'integer',
        'is_posted'       => 'boolean',
        'posted_at'       => 'datetime',
        'posted_by'       => 'integer',
    ];

    // ============================================
    // ثوابت طريقة الدفع
    // ============================================
    const PAYMENT_CREDIT = 1;  // أجل
    const PAYMENT_CASH   = 2;  // نقدي (صندوق)
    const PAYMENT_BANK   = 3;  // بنك

    // ============================================
    // ثوابت نوع المستند للقيود المحاسبية
    // ============================================
    const DOC_TYPE_SALES_RETURN_CREDIT = 'sales_return_credit';
    const DOC_TYPE_SALES_RETURN_CASH   = 'sales_return_cash';
    const DOC_TYPE_SALES_RETURN_BANK   = 'sales_return_bank';

    // ============================================
    // العلاقات
    // ============================================

    public function originalInvoice()
    {
        return $this->belongsTo(
            SalesInvoice::class,
            'original_sales_invoice_id',
            'sales_invoice_id'
        );
    }

    public function customerAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'account_id',
            'accountID'
        );
    }

    public function paymentAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'payment_account_id',
            'accountID'
        );
    }

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coin_id', 'coinsID');
    }

    public function warehouse()
    {
        return $this->belongsTo(Stock::class, 'warehouse_id', 'StockID');
    }

    public function details()
    {
        return $this->hasMany(
            SalesReturnDetail::class,
            'sales_return_id',
            'sales_return_id'
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

    // ============================================
    // Accessors
    // ============================================

    public function getTotalInReturnCurrencyAttribute(): float
    {
        return (float) $this->items_total - (float) $this->discount_total;
    }

    public function getTotalInLocalCurrencyAttribute(): float
    {
        return $this->total_in_return_currency
            * (float) ($this->exchange_rate ?: 1);
    }

    public function getJournalDocTypeAttribute(): string
    {
        return match ((int) $this->payment_method) {
            self::PAYMENT_CREDIT => self::DOC_TYPE_SALES_RETURN_CREDIT,
            self::PAYMENT_CASH   => self::DOC_TYPE_SALES_RETURN_CASH,
            self::PAYMENT_BANK   => self::DOC_TYPE_SALES_RETURN_BANK,
            default              => 'sales_return',
        };
    }

    public function getJournalDocTypeLabelAttribute(): string
    {
        return match ((int) $this->payment_method) {
            self::PAYMENT_CREDIT => 'مرتجع بيع أجل',
            self::PAYMENT_CASH   => 'مرتجع بيع نقدي',
            self::PAYMENT_BANK   => 'مرتجع بيع بنك',
            default              => 'مرتجع بيع',
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