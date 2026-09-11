<?php

namespace App\Observers;

use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Bank;
use App\Models\Accounting\Box;
use App\Models\Inventory\Stock;

class CharAccountObserver
{
    // =====================================================
    // حسابات المخازن
    // =====================================================
    private function getStockParent()
    {
        return CharAccount::where('accName', 'المخازن')->first()
            ?? CharAccount::where('accName', 'LIKE', '%مخازن%')->first()
            ?? CharAccount::where('accCode', 113)->first();
    }

    // =====================================================
    // حسابات الصناديق
    // =====================================================
    private function getBoxParent()
    {
        return CharAccount::where('accName', 'الصناديق')->first()
            ?? CharAccount::where('accName', 'LIKE', '%صناديق%')->first();
    }

    // =====================================================
    // حسابات البنوك
    // =====================================================
    private function getBankParent()
    {
        return CharAccount::where('accName', 'البنوك')->first()
            ?? CharAccount::where('accName', 'LIKE', '%بنوك%')->first();
    }

    private function isDescendant($account, $parent)
    {
        if (!$parent) return false;

        $current = $account;
        while ($current) {
            if ($current->accParent == $parent->accountID) return true;
            if (!$current->accParent) break;
            $current = CharAccount::find($current->accParent);
            if (!$current) break;
        }
        return false;
    }

    public function created(CharAccount $account)
    {
        // ============================================
        // 1) المخزون
        // ============================================
        $stockParent = $this->getStockParent();
        if ($stockParent && $account->accountID != $stockParent->accountID) {
            if ($this->isDescendant($account, $stockParent)) {
                if (!Stock::where('accountID', $account->accountID)->first()) {
                    Stock::create([
                        'StockName' => $account->accName,
                        'accountID' => $account->accountID,
                        'is_active' => $account->IsActive,
                    ]);
                }
            }
        }

        // ============================================
        // 2) الصناديق
        // ============================================
        $boxParent = $this->getBoxParent();
        if ($boxParent && $account->accountID != $boxParent->accountID) {
            if ($this->isDescendant($account, $boxParent)) {
                if (!Box::where('accountID', $account->accountID)->first()) {
                    Box::create([
                        'boxName'   => $account->accName,
                        'accountID' => $account->accountID,
                        'coinsID'   => null,
                        'is_active' => $account->IsActive,
                    ]);
                }
            }
        }

        // ============================================
        // 3) البنوك
        // ============================================
        $bankParent = $this->getBankParent();
        if ($bankParent && $account->accountID != $bankParent->accountID) {
            if ($this->isDescendant($account, $bankParent)) {
                if (!Bank::where('accountID', $account->accountID)->first()) {
                    Bank::create([
                        'bankName'      => $account->accName,
                        'accountID'     => $account->accountID,
                        'coinsID'       => null,
                        'accountNumber' => null,
                        'is_active'     => $account->IsActive,
                    ]);
                }
            }
        }
    }

    public function updated(CharAccount $account)
    {
        // مزامنة المخزون
        $stock = Stock::where('accountID', $account->accountID)->first();
        if ($stock) {
            $changed = false;
            if ($account->isDirty('accName') && $stock->StockName !== $account->accName) {
                $stock->StockName = $account->accName;
                $changed = true;
            }
            if ($account->isDirty('IsActive') && $stock->is_active != $account->IsActive) {
                $stock->is_active = $account->IsActive;
                $changed = true;
            }
            if ($changed) $stock->save();
        }

        // مزامنة الصناديق
        $box = Box::where('accountID', $account->accountID)->first();
        if ($box) {
            $changed = false;
            if ($account->isDirty('accName') && $box->boxName !== $account->accName) {
                $box->boxName = $account->accName;
                $changed = true;
            }
            if ($account->isDirty('IsActive') && $box->is_active != $account->IsActive) {
                $box->is_active = $account->IsActive;
                $changed = true;
            }
            if ($changed) $box->save();
        }

        // مزامنة البنوك
        $bank = Bank::where('accountID', $account->accountID)->first();
        if ($bank) {
            $changed = false;
            if ($account->isDirty('accName') && $bank->bankName !== $account->accName) {
                $bank->bankName = $account->accName;
                $changed = true;
            }
            if ($account->isDirty('IsActive') && $bank->is_active != $account->IsActive) {
                $bank->is_active = $account->IsActive;
                $changed = true;
            }
            if ($changed) $bank->save();
        }
    }

    public function deleting(CharAccount $account)
    {
        if ($s = Stock::where('accountID', $account->accountID)->first()) $s->delete();
        if ($b = Box::where('accountID', $account->accountID)->first()) $b->delete();
        if ($k = Bank::where('accountID', $account->accountID)->first()) $k->delete();
    }
}