<?php

namespace App\Services;

use App\Models\Accounting\AccountBalance;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\JournalEntryLine;

class AccountBalanceService
{
    /**
     * Cache محلي للعملة النظامية (لكل طلب)
     */
    private static ?int $systemCurrencyIdCache = null;

    /**
     * Cache لطبائع الحسابات (لكل طلب)
     */
    private static array $natureCache = [];

    // ══════════════════════════════════════════════════════════
    //  الحساب الأساسي
    // ══════════════════════════════════════════════════════════

    /**
     * إعادة حساب رصيد حساب واحد.
     */
    public static function recalculate(int $accountID): void
    {
        $account = CharAccount::find($accountID);

        if (!$account) {
            return;
        }

        $totals = JournalEntryLine::where('accountID', $accountID)
            ->selectRaw(
                'COALESCE(SUM(localDebit), 0)  AS total_debit,
                 COALESCE(SUM(localCredit), 0) AS total_credit'
            )
            ->first();

        $totalDebit  = (float) ($totals->total_debit  ?? 0);
        $totalCredit = (float) ($totals->total_credit ?? 0);

        $balance = self::calculateBalance(
            $totalDebit,
            $totalCredit,
            (int) $account->nature
        );

        self::saveBalance($accountID, $totalDebit, $totalCredit, $balance);
    }

    /**
     * إعادة حساب مجموعة حسابات.
     */
    public static function recalculateBatch(array $accountIDs): void
    {
        $accountIDs = array_unique(array_filter($accountIDs));

        if (empty($accountIDs)) {
            return;
        }

        // جلب المجاميع لكل حساب دفعة واحدة
        $totalsByAccount = JournalEntryLine::whereIn('accountID', $accountIDs)
            ->selectRaw(
                'accountID,
                 COALESCE(SUM(localDebit), 0)  AS total_debit,
                 COALESCE(SUM(localCredit), 0) AS total_credit'
            )
            ->groupBy('accountID')
            ->get()
            ->keyBy('accountID');

        // ✅ جلب طبائع كل الحسابات دفعة واحدة
        $natures = CharAccount::whereIn('accountID', $accountIDs)
            ->pluck('nature', 'accountID')
            ->all();

        $fiscalYear       = (int) now()->year;
        $systemCurrencyId = self::getSystemCurrencyId();

        foreach ($accountIDs as $accountID) {
            $row = $totalsByAccount->get($accountID);

            $totalDebit  = (float) ($row->total_debit  ?? 0);
            $totalCredit = (float) ($row->total_credit ?? 0);

            $nature = (int) ($natures[$accountID] ?? 0);

            $balance = self::calculateBalance($totalDebit, $totalCredit, $nature);

            self::upsert(
                (int) $accountID,
                $fiscalYear,
                $systemCurrencyId,
                $totalDebit,
                $totalCredit,
                $balance
            );
        }
    }

    /**
     * إعادة حساب كل الحسابات.
     */
    public static function recalculateAll(): void
    {
        $totals = JournalEntryLine::selectRaw(
                'accountID,
                 COALESCE(SUM(localDebit), 0)  AS total_debit,
                 COALESCE(SUM(localCredit), 0) AS total_credit'
            )
            ->groupBy('accountID')
            ->get();

        // جلب طبائع كل الحسابات
        $natures = CharAccount::pluck('nature', 'accountID')->all();

        $fiscalYear       = (int) now()->year;
        $systemCurrencyId = self::getSystemCurrencyId();

        foreach ($totals as $row) {
            $totalDebit  = (float) $row->total_debit;
            $totalCredit = (float) $row->total_credit;

            $nature = (int) ($natures[$row->accountID] ?? 0);

            $balance = self::calculateBalance($totalDebit, $totalCredit, $nature);

            self::upsert(
                (int) $row->accountID,
                $fiscalYear,
                $systemCurrencyId,
                $totalDebit,
                $totalCredit,
                $balance
            );
        }
    }

    // ══════════════════════════════════════════════════════════
    //  الحساب حسب الطبيعة
    // ══════════════════════════════════════════════════════════

    /**
     * ✅ حساب الرصيد حسب طبيعة الحساب.
     *
     * nature = 0 (مدين):  balance = debit - credit
     * nature = 1 (دائن):  balance = credit - debit
     */
    private static function calculateBalance(
        float $totalDebit,
        float $totalCredit,
        int $nature
    ): float {
        // nature = 1 (دائن) → الرصيد = credit - debit
        if ($nature === 1) {
            return $totalCredit - $totalDebit;
        }

        // nature = 0 (مدين) → الرصيد = debit - credit
        return $totalDebit - $totalCredit;
    }

    // ══════════════════════════════════════════════════════════
    //  القراءة
    // ══════════════════════════════════════════════════════════

    public static function get(int $accountID): ?AccountBalance
    {
        return AccountBalance::where('accountID', $accountID)
            ->where('fiscalYear', (int) now()->year)
            ->where('coinsID', self::getSystemCurrencyId())
            ->first();
    }

    public static function getBalance(int $accountID): float
    {
        return (float) (self::get($accountID)?->balance ?? 0);
    }

    public static function getBalances(array $accountIDs): array
    {
        if (empty($accountIDs)) {
            return [];
        }

        return AccountBalance::whereIn('accountID', $accountIDs)
            ->where('fiscalYear', (int) now()->year)
            ->where('coinsID', self::getSystemCurrencyId())
            ->pluck('balance', 'accountID')
            ->map(fn($v) => (float) $v)
            ->all();
    }

    // ══════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════

    private static function saveBalance(
        int $accountID,
        float $totalDebit,
        float $totalCredit,
        float $balance
    ): void {
        self::upsert(
            $accountID,
            (int) now()->year,
            self::getSystemCurrencyId(),
            $totalDebit,
            $totalCredit,
            $balance
        );
    }

    private static function upsert(
        int $accountID,
        int $fiscalYear,
        int $coinsID,
        float $totalDebit,
        float $totalCredit,
        float $balance
    ): void {
        AccountBalance::updateOrCreate(
            [
                'accountID'  => $accountID,
                'fiscalYear' => $fiscalYear,
                'coinsID'    => $coinsID,
            ],
            [
                'debitTotal'    => $totalDebit,
                'creditTotal'   => $totalCredit,
                'balance'       => $balance,
                'lastUpdatedAt' => now(),
            ]
        );
    }

    private static function getSystemCurrencyId(): int
    {
        if (self::$systemCurrencyIdCache !== null) {
            return self::$systemCurrencyIdCache;
        }

        self::$systemCurrencyIdCache = (int) (
            Coin::where('coinsSystem', 1)->value('coinsID') ?? 1
        );

        return self::$systemCurrencyIdCache;
    }
}