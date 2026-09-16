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
    ];

    protected $casts = [
        'invoice_date'   => 'date',
        'exchange_rate'  => 'decimal:6',
        'items_total'    => 'decimal:6',
        'discount_total' => 'decimal:6',
        'payment_method' => 'integer',
    ];

    // ============================================
    // ثوابت طريقة الدفع
    // ============================================
    const PAYMENT_CREDIT  = 1;
    const PAYMENT_CASH    = 2;
    const PAYMENT_BANK    = 3;
    const PAYMENT_NETWORK = 4;

    // ============================================
    // العلاقات
    // ============================================

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
     * تفاصيل الأصناف
     */
    public function details()
    {
        return $this->hasMany(
            SalesInvoiceDetail::class,
            'sales_invoice_id',
            'sales_invoice_id'
        );
    }

    // ============================================
    // Accessors
    // ============================================

    /**
     * الإجمالي النهائي بعملة الفاتورة
     * = items_total − discount_total
     */
    public function getTotalInInvoiceCurrencyAttribute(): float
    {
        return (float) $this->items_total - (float) $this->discount_total;
    }

    /**
     * الإجمالي بالعملة المحلية
     */
    public function getTotalInLocalCurrencyAttribute(): float
    {
        return $this->total_in_invoice_currency
            * (float) $this->exchange_rate;
    }
}