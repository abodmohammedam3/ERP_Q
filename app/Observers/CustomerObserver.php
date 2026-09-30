<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Accounting\CharAccount;
use App\Http\Controllers\accounting\OpeningBalanceController;

class CustomerObserver
{
    /**
     * بعد إنشاء العميل
     */
    public function created(Customer $customer)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();

        $account = CharAccount::find($customer->accountID);

        if (!$account) {
            return;
        }

        $changed = false;

        // مزامنة اسم العميل
        if ($account->accName !== $customer->CustomersName2) {
            $account->accName = $customer->CustomersName2;
            $changed = true;
        }

        // مزامنة حالة العميل
        $newIsActive = (int) $customer->is_active === 1 ? 1 : 0;

        if ((int) $account->IsActive !== $newIsActive) {
            $account->IsActive = $newIsActive;
            $changed = true;
        }

        if ($changed) {
            $account->save();
        }
    }

    /**
     * عند تعديل العميل
     */
    public function updated(Customer $customer)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();

        $account = CharAccount::find($customer->accountID);

        if (!$account) {
            return;
        }

        $changed = false;

        // مزامنة اسم العميل
        if (
            $customer->wasChanged('CustomersName2') &&
            $account->accName !== $customer->CustomersName2
        ) {
            $account->accName = $customer->CustomersName2;
            $changed = true;
        }

        // مزامنة حالة العميل
        if ($customer->wasChanged('is_active')) {
            $newIsActive = (int) $customer->is_active === 1 ? 1 : 0;

            if ((int) $account->IsActive !== $newIsActive) {
                $account->IsActive = $newIsActive;
                $changed = true;
            }
        }

        if ($changed) {
            $account->save();
        }
    }

    /**
     * الحذف يتم التحكم به من CustomerController
     */
    public function deleted(Customer $customer)
    {
        // ✅ امسح كاش الأرصدة الافتتاحية
        OpeningBalanceController::forgetAllCache();
    }
}