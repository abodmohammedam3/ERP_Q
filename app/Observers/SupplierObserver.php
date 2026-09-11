<?php

namespace App\Observers;

use App\Models\Supplier;
use App\Models\Accounting\CharAccount;

class SupplierObserver
{
    // =====================================================
    // عند إنشاء المورد
    // =====================================================

    public function created(Supplier $supplier)
    {
        $account = CharAccount::find(
            $supplier->accountID
        );

        if (!$account) {
            return;
        }

        if (
            $account->accName !==
            $supplier->supName
        ) {
            $account->accName =
                $supplier->supName;

            $account->save();
        }
    }

    // =====================================================
    // عند تعديل المورد
    // =====================================================

    public function updated(Supplier $supplier)
    {
        $account = CharAccount::find(
            $supplier->accountID
        );

        if (!$account) {
            return;
        }

        $changed = false;

        // =================================================
        // مزامنة اسم المورد مع اسم الحساب
        // =================================================

        if (
            $supplier->wasChanged('supName') &&
            $account->accName !==
                $supplier->supName
        ) {
            $account->accName =
                $supplier->supName;

            $changed = true;
        }

        // =================================================
        // مزامنة حالة المورد مع الحساب
        //
        // supStoped:
        // 0 = نشط
        // 1 = غير نشط
        //
        // IsActive:
        // 1 = نشط
        // 0 = غير نشط
        // =================================================

        if (
            $supplier->wasChanged('supStoped')
        ) {

            $newIsActive =
                (int) $supplier->supStoped === 1
                    ? 0
                    : 1;

            if (
                (int) $account->IsActive !==
                $newIsActive
            ) {
                $account->IsActive =
                    $newIsActive;

                $changed = true;
            }
        }

        // =================================================
        // حفظ الحساب إذا حدث تغيير
        // =================================================

        if ($changed) {
            $account->save();
        }
    }

    // =====================================================
    // عند حذف المورد
    // =====================================================

    public function deleted(Supplier $supplier)
    {
        // تتم معالجة الحساب المرتبط في SupplierController
    }
}