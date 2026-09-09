<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Accounting\CharAccount;
use App\Models\Inventory\Stock;
use App\Observers\CharAccountObserver;
use App\Observers\StockObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
       CharAccount::observe(CharAccountObserver::class);
        Stock::observe(StockObserver::class);
    }
}
