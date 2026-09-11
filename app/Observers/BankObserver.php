<?php

namespace App\Observers;

use App\Models\Accounting\Bank;
use App\Models\Accounting\CharAccount;

class BankObserver
{
    public function created(Bank $bank)
    {
        $account = CharAccount::find($bank->accountID);
        if ($account && $account->accName !== $bank->bankName) {
            $account->accName = $bank->bankName;
            $account->save();
        }
    }

    public function updated(Bank $bank)
    {
        $account = CharAccount::find($bank->accountID);
        if (!$account) return;

        $changed = false;

        if ($bank->isDirty('bankName') && $account->accName !== $bank->bankName) {
            $account->accName = $bank->bankName;
            $changed = true;
        }

        if ($bank->isDirty('is_active') && $account->IsActive != $bank->is_active) {
            $account->IsActive = $bank->is_active ? 1 : 0;
            $changed = true;
        }

        if ($changed) {
            $account->save();
        }
    }

    public function deleted(Bank $bank)
    {
        // التعامل في Controller
    }
}