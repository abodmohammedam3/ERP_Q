<?php

namespace App\Observers;

use App\Models\Accounting\CharAccount;
use App\Models\Inventory\Stock;

class CharAccountObserver
{
    /**
     * الحصول على الحساب الأب للمخازن.
     *
     * الحساب النظامي:
     * system_key = inventory
     */
    private function getStockParent()
    {
        return CharAccount::where('system_key', 'inventory')
            ->where('isPostable', 0)
            ->first();
    }

    /**
     * التحقق هل الحساب يقع تحت حساب المخازن.
     */
    private function isDescendant($account, $parent)
    {
        if (!$parent) {
            return false;
        }

        $current = $account;

        while ($current) {

            if (
                (int) $current->accParent ===
                (int) $parent->accountID
            ) {
                return true;
            }

            if (!$current->accParent) {
                break;
            }

            $current =
                CharAccount::find(
                    $current->accParent
                );

            if (!$current) {
                break;
            }
        }

        return false;
    }

    /**
     * عند إنشاء حساب.
     *
     * لا ننشئ Stock هنا.
     *
     * إنشاء المخزن يتم من StockController
     * لأن stocks.accountID لا يقبل NULL.
     */
    public function created(CharAccount $account)
    {
        return;
    }

    /**
     * عند تعديل الحساب.
     *
     * مزامنة اسم وحالة المخزن المرتبط.
     */
    public function updated(CharAccount $account)
    {
        $stock = Stock::where(
            'accountID',
            $account->accountID
        )->first();

        if (!$stock) {
            return;
        }

        $changed = false;

        /*
         * مزامنة اسم الحساب
         */
        if (
            $account->isDirty('accName') &&
            $stock->StockName !== $account->accName
        ) {

            $stock->StockName =
                $account->accName;

            $changed = true;
        }

        /*
         * مزامنة حالة الحساب
         */
        if (
            $account->isDirty('IsActive') &&
            (int) $stock->is_active !==
            (int) $account->IsActive
        ) {

            $stock->is_active =
                $account->IsActive;

            $changed = true;
        }

        if ($changed) {
            $stock->save();
        }
    }

    /**
     * عند حذف الحساب.
     *
     * حذف المخزن المرتبط به.
     */
    public function deleting(CharAccount $account)
    {
        $stock = Stock::where(
            'accountID',
            $account->accountID
        )->first();

        if ($stock) {
            $stock->delete();
        }
    }
}