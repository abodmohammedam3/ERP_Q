<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Bank;
use App\Models\Accounting\Box;
use App\Models\Inventory\Stock;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Purchases\PurchaseInvoice;

use App\Observers\CharAccountObserver;
use App\Observers\BankObserver;
use App\Observers\BoxObserver;
use App\Observers\StockObserver;
use App\Observers\CustomerObserver;
use App\Observers\SupplierObserver;
use App\Observers\PurchaseInvoiceObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        CharAccount::observe(CharAccountObserver::class);
        Box::observe(BoxObserver::class);
        Bank::observe(BankObserver::class);
        Stock::observe(StockObserver::class);
        Customer::observe(CustomerObserver::class);
        Supplier::observe(SupplierObserver::class);

        // ✅ جديد: مراقب فاتورة الشراء
        PurchaseInvoice::observe(PurchaseInvoiceObserver::class);
    }
}