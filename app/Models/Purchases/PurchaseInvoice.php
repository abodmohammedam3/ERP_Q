<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Stock;

class PurchaseInvoice extends Model
{
    protected $table      = 'purchase_invoices';
    protected $primaryKey = 'purchase_invoice_id';

    public $incrementing = true;
    protected $keyType   = 'int';

    public $timestamps = false;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'account_id',
        'payment_account_id',
        'coin_id',
        'warehouse_id',
        'exchange_rate',
        'payment_method',
        'items_total',
        'discount_total',
        'expenses',
        'tax_cost',
        'transportation',
        'other_cost',
        'other_cost_description',
        'statement',
        'reference',
        'total_in_base_currency',
        'entryID',   // ✅ FK للقيد
    ];

    protected $casts = [
        'invoice_date'            => 'date',
        'exchange_rate'           => 'decimal:6',
        'items_total'             => 'decimal:6',
        'discount_total'          => 'decimal:6',
        'expenses'                => 'decimal:6',
        'tax_cost'                => 'decimal:6',
        'transportation'          => 'decimal:6',
        'other_cost'              => 'decimal:6',
        'total_in_base_currency'  => 'decimal:6',
        'payment_method'          => 'integer',
        'entryID'                 => 'integer',
    ];

    // ============================================
    // ثوابت طريقة الدفع
    // ============================================
    const PAYMENT_CREDIT = 1;  // أجل
    const PAYMENT_CASH   = 2;  // نقدي
    const PAYMENT_BANK   = 3;  // بنك

    // ============================================
    // ثوابت نوع المستند للقيود المحاسبية
    // ============================================
    const DOC_TYPE_PURCHASE_CREDIT = 'purchase_invoice_credit';
    const DOC_TYPE_PURCHASE_CASH   = 'purchase_invoice_cash';
    const DOC_TYPE_PURCHASE_BANK   = 'purchase_invoice_bank';

    // ============================================
    // العلاقات
    // ============================================

    public function supplierAccount()
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

    public function warehouse()
    {
        return $this->belongsTo(Stock::class, 'warehouse_id', 'StockID');
    }

    public function details()
    {
        return $this->hasMany(
            PurchaseInvoiceDetail::class,
            'purchase_invoice_id',
            'purchase_invoice_id'
        );
    }

    /**
     * ✅ مرتجعات هذه الفاتورة
     */
    public function returns()
    {
        return $this->hasMany(
            \App\Models\Purchases\PurchaseReturn::class,
            'original_purchase_invoice_id',
            'purchase_invoice_id'
        );
    }

    /**
     * ✅ القيد المحاسبي المرتبط بالفاتورة
     */
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

    /**
     * ✅ الإجمالي بعملة الفاتورة
     */
    public function getTotalInInvoiceCurrencyAttribute(): float
    {
        return (float) $this->items_total - (float) $this->discount_total;
    }

    /**
     * ✅ مجموع التكاليف الإضافية
     */
    public function getExtraCostsTotalAttribute(): float
    {
        return (float) $this->expenses
            + (float) $this->tax_cost
            + (float) $this->transportation
            + (float) $this->other_cost;
    }

    /**
     * الإجمالي بالعملة المحلية
     */
    public function getTotalInLocalCurrencyAttribute(): float
    {
        if (!empty($this->attributes['total_in_base_currency'])) {
            return (float) $this->attributes['total_in_base_currency'];
        }

        return $this->total_in_invoice_currency
            * (float) ($this->exchange_rate ?: 1);
    }

    /**
     * ✅ docType المناسب للقيد حسب طريقة الدفع
     */
    public function getJournalDocTypeAttribute(): string
    {
        return match ((int) $this->payment_method) {
            self::PAYMENT_CREDIT => self::DOC_TYPE_PURCHASE_CREDIT,
            self::PAYMENT_CASH   => self::DOC_TYPE_PURCHASE_CASH,
            self::PAYMENT_BANK   => self::DOC_TYPE_PURCHASE_BANK,
            default              => 'purchase_invoice',
        };
    }

    /**
     * ✅ اسم عربي مقروء لنوع الفاتورة
     */
    public function getJournalDocTypeLabelAttribute(): string
    {
        return match ((int) $this->payment_method) {
            self::PAYMENT_CREDIT => 'فاتورة شراء أجل',
            self::PAYMENT_CASH   => 'فاتورة شراء نقدي',
            self::PAYMENT_BANK   => 'فاتورة شراء بنك',
            default              => 'فاتورة شراء',
        };
    }

    /**
     * ✅ خريطة: القيمة → الاسم العربي
     */
    public static function paymentMethodLabels(): array
    {
        return [
            self::PAYMENT_CREDIT => 'أجل',
            self::PAYMENT_CASH   => 'نقدي',
            self::PAYMENT_BANK   => 'بنك',
        ];
    }
}