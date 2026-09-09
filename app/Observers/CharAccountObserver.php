<?php

namespace App\Observers;

use App\Models\Accounting\CharAccount;
use App\Models\Inventory\Stock;

class CharAccountObserver
{
    private function getStockParent()
    {
        $parent = CharAccount::where('accName', 'المخازن')->first();
        if ($parent) return $parent;

        $parent = CharAccount::where('accName', 'LIKE', '%مخازن%')->first();
        if ($parent) return $parent;

        $parent = CharAccount::where('accCode', 113)->first();
        if ($parent) return $parent;

        return null;
    }

    private function isDescendant($account, $parent)
    {
        if (!$parent) return false;

        $current = $account;
        while ($current) {
            if ($current->accParent == $parent->accountID) {
                return true;
            }
            if (!$current->accParent) break;
            $current = CharAccount::find($current->accParent);
            if (!$current) break;
        }
        return false;
    }

    public function created(CharAccount $account)
    {
        $parent = $this->getStockParent();
        if (!$parent) return;

        if ($account->accountID == $parent->accountID) return;

        if (!$this->isDescendant($account, $parent)) return;

        // منع التكرار
        $existing = Stock::where('accountID', $account->accountID)->first();
        if ($existing) return;

        // إنشاء المخزن باسم الحساب
        Stock::create([
            'StockName' => $account->accName,
            'accountID' => $account->accountID,
            'is_active' => $account->IsActive,
        ]);
    }

    public function updated(CharAccount $account)
    {
        $stock = Stock::where('accountID', $account->accountID)->first();
        if (!$stock) return;

        $changed = false;

        if ($account->isDirty('accName') && $stock->StockName !== $account->accName) {
            $stock->StockName = $account->accName;
            $changed = true;
        }

        if ($account->isDirty('IsActive') && $stock->is_active != $account->IsActive) {
            $stock->is_active = $account->IsActive;
            $changed = true;
        }

        if ($changed) {
            $stock->save();
        }
    }

    public function deleting(CharAccount $account)
    {
        $stock = Stock::where('accountID', $account->accountID)->first();
        if ($stock) {
            $stock->delete();
        }
    }
}