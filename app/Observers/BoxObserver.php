<?php

namespace App\Observers;

use App\Models\Accounting\Box;
use App\Models\Accounting\CharAccount;

class BoxObserver
{
    public function created(Box $box)
    {
        $account = CharAccount::find($box->accountID);
        if ($account && $account->accName !== $box->boxName) {
            $account->accName = $box->boxName;
            $account->save();
        }
    }

    public function updated(Box $box)
    {
        $account = CharAccount::find($box->accountID);
        if (!$account) return;

        $changed = false;

        if ($box->isDirty('boxName') && $account->accName !== $box->boxName) {
            $account->accName = $box->boxName;
            $changed = true;
        }

        if ($box->isDirty('is_active') && $account->IsActive != $box->is_active) {
            $account->IsActive = $box->is_active ? 1 : 0;
            $changed = true;
        }

        if ($changed) {
            $account->save();
        }
    }

    public function deleted(Box $box)
    {
        // التعامل مع الحذف في Controller
    }
}