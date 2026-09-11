<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Accounting\CharAccount;

class CustomerObserver
{
    /**
     * بعد إنشاء العميل:
     *
     * الحساب التحليلي يكون قد أُنشئ مسبقاً
     * من CustomerController.
     *
     * هنا نضمن تطابق اسم الحساب مع اسم العميل.
     */
    public function created(Customer $customer)
    {
        $account = CharAccount::find(
            $customer->accountID
        );

        if (!$account) {
            return;
        }

        if (
            $account->accName !==
            $customer->CustomersName2
        ) {
            $account->accName =
                $customer->CustomersName2;

            $account->save();
        }
    }

    /**
     * عند تعديل العميل:
     *
     * مزامنة اسم وحالة الحساب التحليلي
     * المرتبط بالعميل.
     */
    public function updated(Customer $customer)
    {
        $account = CharAccount::find(
            $customer->accountID
        );

        if (!$account) {
            return;
        }

        $changed = false;

        /*
         * مزامنة اسم العميل
         */
        if (
            $customer->wasChanged('CustomersName2') &&
            $account->accName !==
                $customer->CustomersName2
        ) {
            $account->accName =
                $customer->CustomersName2;

            $changed = true;
        }

        /*
         * مزامنة حالة العميل
         */
       if ($customer->wasChanged('CusIsStopeed')) {

    $newIsActive =
        (int) $customer->CusIsStopeed === 1
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

        if ($changed) {
            $account->save();
        }
    }

    /**
     * الحذف يتم التحكم به من CustomerController
     * لأن الحساب المرتبط يجب التعامل معه حسب
     * وجود أبناء له.
     */
    public function deleted(Customer $customer)
    {
        // يتم التعامل مع حذف الحساب في Controller
    }
}