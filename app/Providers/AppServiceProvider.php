<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Accounting\CharAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Bank;
use App\Models\Accounting\Box;
use App\Models\Inventory\Stock;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Sales\SalesInvoice;
use App\Models\Accounting\Coin;


use App\Observers\CharAccountObserver;
use App\Observers\BankObserver;
use App\Observers\BoxObserver;
use App\Observers\StockObserver;
use App\Observers\CustomerObserver;
use App\Observers\SupplierObserver;
use App\Observers\PurchaseInvoiceObserver;
use App\Observers\SalesInvoiceObserver;
use App\Observers\CoinObserver;
use App\Listeners\RecordSystemActivity;
use Illuminate\Support\Facades\Event;

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
        SalesInvoice::observe(SalesInvoiceObserver::class);
        Coin::observe(CoinObserver::class);

        // مؤشر النشاط منذ آخر نسخة احتياطية (شاشة الإعدادات → النسخ الاحتياطي)
        // يقرأها BackupController::index() ويعرضها بطاقة "مؤشر النشاط"
        foreach ([
            'eloquent.created: '.SalesInvoice::class,
            'eloquent.updated: '.SalesInvoice::class,
            'eloquent.deleted: '.SalesInvoice::class,
            'eloquent.created: '.PurchaseInvoice::class,
            'eloquent.updated: '.PurchaseInvoice::class,
            'eloquent.deleted: '.PurchaseInvoice::class,
            'eloquent.created: '.JournalEntry::class,
            'eloquent.updated: '.JournalEntry::class,
            'eloquent.deleted: '.JournalEntry::class,
        ] as $activityEvent) {
            Event::listen($activityEvent, RecordSystemActivity::class);
        }
         if ($this->app->environment('testing')) {
        $bacPath = database_path('bac');
        if (is_dir($bacPath)) {
            $this->loadMigrationsFrom($bacPath);
        }
    }

        
    }
}