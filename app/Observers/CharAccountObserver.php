<?php

namespace App\Observers;

use App\Models\Accounting\CharAccount;
use App\Models\Inventory\Stock;
use App\Http\Controllers\accounting\OpeningBalanceController;

class CharAccountObserver
{
    /**
     * عند إنشاء حساب
     */
    public function created(CharAccount $account)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();
    }

    /**
     * عند تعديل الحساب
     */
    public function updated(CharAccount $account)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();

        $stock = Stock::where('accountID', $account->accountID)->first();

        if (!$stock) {
            return;
        }

        $changed = false;

        // مزامنة اسم الحساب
        if (
            $account->isDirty('accName') &&
            $stock->StockName !== $account->accName
        ) {
            $stock->StockName = $account->accName;
            $changed = true;
        }

        // مزامنة حالة الحساب
        if (
            $account->isDirty('IsActive') &&
            (int) $stock->is_active !== (int) $account->IsActive
        ) {
            $stock->is_active = $account->IsActive;
            $changed = true;
        }

        if ($changed) {
            $stock->save();
        }
    }

    /**
     * عند حذف الحساب
     */
    public function deleting(CharAccount $account)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();

        $stock = Stock::where('accountID', $account->accountID)->first();

        if ($stock) {
            $stock->delete();
        }
    }
}