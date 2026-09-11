<?php

namespace App\Observers;

use App\Models\Inventory\Stock;
use App\Models\Accounting\CharAccount;

class StockObserver
{
    /**
     * بعد إنشاء المخزن:
     * الحساب التحليلي يكون قد أُنشئ مسبقاً من Controller
     * لذلك لا ننشئ حساباً جديداً هنا.
     */
    public function created(Stock $stock)
    {
        $account = CharAccount::find($stock->accountID);

        if (!$account) {
            return;
        }

        // ضمان تطابق اسم الحساب مع اسم المخزن
        if ($account->accName !== $stock->StockName) {
            $account->accName = $stock->StockName;
            $account->save();
        }
    }

    /**
     * عند تعديل المخزن:
     * مزامنة اسم وحالة الحساب التحليلي المرتبط به.
     */
    public function updated(Stock $stock)
    {
        $account = CharAccount::find($stock->accountID);

        if (!$account) {
            return;
        }

        $changed = false;

        if (
            $stock->wasChanged('StockName') &&
            $account->accName !== $stock->StockName
        ) {
            $account->accName = $stock->StockName;
            $changed = true;
        }

        if (
            $stock->wasChanged('is_active') &&
            (int) $account->IsActive !== (int) $stock->is_active
        ) {
            $account->IsActive = $stock->is_active;
            $changed = true;
        }

        if ($changed) {
            $account->save();
        }
    }

    /**
     * الحذف يتم التحكم به من StockController
     * لأن الحساب المرتبط يجب التعامل معه حسب وجود أبناء له.
     */
    public function deleted(Stock $stock)
    {
        // يتم التعامل مع حذف الحساب في Controller
    }
}