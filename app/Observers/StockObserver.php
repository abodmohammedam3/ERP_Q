<?php

namespace App\Observers;

use App\Models\Inventory\Stock;
use App\Models\Accounting\CharAccount;

class StockObserver
{
    public function created(Stock $stock)
    {
        $account = CharAccount::find($stock->accountID);
        if ($account && $account->accName !== $stock->StockName) {
            $account->accName = $stock->StockName;
            $account->save();
        }
    }

    public function updated(Stock $stock)
    {
        $account = CharAccount::find($stock->accountID);
        if (!$account) return;

        $changed = false;

        if ($stock->isDirty('StockName') && $account->accName !== $stock->StockName) {
            $account->accName = $stock->StockName;
            $changed = true;
        }

        if ($stock->isDirty('is_active') && $account->IsActive != $stock->is_active) {
            $account->IsActive = $stock->is_active;
            $changed = true;
        }

        if ($changed) {
            $account->save();
        }
    }

    public function deleted(Stock $stock)
    {
        // التعامل مع الحذف في Controller
    }
}