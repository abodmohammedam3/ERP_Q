<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;

/**
 * رأس مرتجع البيع
 * -----------------------------------------------------
 * مستند مستقل مرتبط بالفاتورة الأصلية عبر:
 *   original_sales_invoice_id
 *
 * ولا يُعدَّل أي شيء على الفاتورة الأصلية.
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
        'exchange_rate',
        'payment_method',
        'items_total',
        'discount_total',
        'statement',
        'reference',
    ];

    protected $casts = [
        'return_date'     => 'date',
        'exchange_rate'   => 'decimal:6',
        'items_total'    => 'decimal:6',
        'discount_total' => 'decimal:6',
        'payment_method' => 'integer',
    ];

    // ============================================
    // ثوابت طريقة الدفع (مطابقة للفواتير)
    // ============================================
    const PAYMENT_CREDIT  = 1;
    const PAYMENT_CASH    = 2;
    const PAYMENT_BANK    = 3;
    const PAYMENT_NETWORK = 4;

    // ============================================
    // العلاقات
    // ============================================

    /**
     * الفاتورة الأصلية
     */
    public function originalInvoice()
    {
        return $this->belongsTo(
            SalesInvoice::class,
            'original_sales_invoice_id',
            'sales_invoice_id'
        );
    }

    /**
     * حساب العميل
     */
    public function customerAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'account_id',
            'accountID'
        );
    }

    /**
     * حساب الدفع الفوري
     */
    public function paymentAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'payment_account_id',
            'accountID'
        );
    }

    /**
     * العملة
     */
    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coin_id', 'coinsID');
    }

    /**
     * تفاصيل المرتجع
     */
    public function details()
    {
        return $this->hasMany(
            SalesReturnDetail::class,
            'sales_return_id',
            'sales_return_id'
        );
    }

    // ============================================
    // Accessors (مطابقة لنمط الفواتير)
    // ============================================

    /**
     * الإجمالي بعملة المرتجع = items_total − discount_total
     */
    public function getTotalInReturnCurrencyAttribute(): float
    {
        return (float) $this->items_total - (float) $this->discount_total;
    }

    /**
     * الإجمالي بالعملة المحلية
     */
    public function getTotalInLocalCurrencyAttribute(): float
    {
        return $this->total_in_return_currency
            * (float) ($this->exchange_rate ?: 1);
    }
}