<?php

namespace App\Observers;

use App\Models\Supplier;
use App\Models\Accounting\CharAccount;
use App\Http\Controllers\accounting\OpeningBalanceController;

class SupplierObserver
{
    // =====================================================
    // عند إنشاء المورد
    // =====================================================

    public function created(Supplier $supplier)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();

        $account = CharAccount::find($supplier->accountID);

        if (!$account) {
            return;
        }

        $changed = false;

        // مزامنة اسم المورد مع اسم الحساب
        if ($account->accName !== $supplier->supName) {
            $account->accName = $supplier->supName;
            $changed = true;
        }

        // مزامنة حالة المورد مع الحساب
        $newIsActive = (int) $supplier->is_active === 1 ? 1 : 0;

        if ((int) $account->IsActive !== $newIsActive) {
            $account->IsActive = $newIsActive;
            $changed = true;
        }

        if ($changed) {
            $account->save();
        }
    }

    // =====================================================
    // عند تعديل المورد
    // =====================================================

    public function updated(Supplier $supplier)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();

        $account = CharAccount::find($supplier->accountID);

        if (!$account) {
            return;
        }

        $changed = false;

        // مزامنة اسم المورد
        if (
            $supplier->wasChanged('supName') &&
            $account->accName !== $supplier->supName
        ) {
            $account->accName = $supplier->supName;
            $changed = true;
        }

        // مزامنة حالة المورد
        if ($supplier->wasChanged('is_active')) {
            $newIsActive = (int) $supplier->is_active === 1 ? 1 : 0;

            if ((int) $account->IsActive !== $newIsActive) {
                $account->IsActive = $newIsActive;
                $changed = true;
            }
        }

        if ($changed) {
            $account->save();
        }
    }

    // =====================================================
    // عند حذف المورد
    // =====================================================

    public function deleted(Supplier $supplier)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();

        // تتم معالجة الحساب المرتبط في SupplierController
    }
}