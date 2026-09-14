<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Inventory\Stock;

class PurchaseInvoice extends Model
{
    protected $table = 'purchase_invoices';
    protected $primaryKey = 'purchase_invoice_id';
    public $incrementing = true;
    protected $keyType = 'int';

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
    ];

    protected $casts = [
        'invoice_date'    => 'date',
        'exchange_rate'   => 'decimal:6',
        'items_total'     => 'decimal:6',
        'discount_total'  => 'decimal:6',
        'expenses'        => 'decimal:6',
        'tax_cost'        => 'decimal:6',
        'transportation'  => 'decimal:6',
        'other_cost'      => 'decimal:6',
        'payment_method'  => 'integer',
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
     * حساب المورد في دليل الحسابات
     */
    public function supplierAccount()
    {
        return $this->belongsTo(
            CharAccount::class,
            'account_id',
            'accountID'
        );
    }

    /**
     * حساب الدفع الفوري (صندوق/بنك/محفظة)
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
        return $this->belongsTo(
            Coin::class,
            'coin_id',
            'coinsID'
        );
    }

    /**
     * المخزن
     */
    public function warehouse()
    {
        return $this->belongsTo(
            Stock::class,
            'warehouse_id',
            'StockID'
        );
    }

    /**
     * تفاصيل الأصناف
     */
    public function details()
    {
        return $this->hasMany(
            PurchaseInvoiceDetail::class,
            'purchase_invoice_id',
            'purchase_invoice_id'
        );
    }

    // ============================================
    // Accessors (محسوبة ديناميكيًا)
    // ============================================

    /**
     * إجمالي الفاتورة بعملة الفاتورة
     * = (items_total − discount_total)
     *   + expenses + tax_cost + transportation + other_cost
     */
    public function getTotalInInvoiceCurrencyAttribute(): float
    {
        $net = (float) $this->items_total - (float) $this->discount_total;

        return $net
            + (float) $this->expenses
            + (float) $this->tax_cost
            + (float) $this->transportation
            + (float) $this->other_cost;
    }

    /**
     * الإجمالي بالعملة المحلية
     * = الإجمالي بعملة الفاتورة × سعر الصرف
     */
    public function getTotalInLocalCurrencyAttribute(): float
    {
        return $this->total_in_invoice_currency
            * (float) $this->exchange_rate;
    }
}