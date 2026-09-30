<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Inventory\Stock;

/**
 * رأس مرتجع الشراء
 * -----------------------------------------------------
 * مستند مستقل مرتبط بالفاتورة الأصلية عبر:
 *   original_purchase_invoice_id
 *
 * ولا يُعدَّل أي شيء على الفاتورة الأصلية.
 */
class PurchaseReturn extends Model
{
    protected $table = 'purchase_returns';
    protected $primaryKey = 'purchase_return_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'return_number',
        'return_date',
        'original_purchase_invoice_id',
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
            PurchaseInvoice::class,
            'original_purchase_invoice_id',
            'purchase_invoice_id'
        );
    }

    /**
     * حساب المورد
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
     * المستودع
     */
    public function warehouse()
    {
        return $this->belongsTo(Stock::class, 'warehouse_id', 'StockID');
    }

    /**
     * تفاصيل المرتجع
     */
    public function details()
    {
        return $this->hasMany(
            PurchaseReturnDetail::class,
            'purchase_return_id',
            'purchase_return_id'
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