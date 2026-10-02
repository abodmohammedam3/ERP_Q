<?php

namespace App\Services;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalEntryService
{
    // =========================================================
    // CREATE
    // =========================================================

    public static function create(array $options): ?int
    {
        $lines = $options['lines'] ?? [];

        if (!self::validateLines($lines)) {
            return null;
        }

        try {
            /*
             * إذا كنا أصلًا داخل Transaction
             * فلا ننشئ Transaction جديدة.
             */
            if (DB::transactionLevel() > 0) {
                return self::createWithinTransaction(
                    $options
                );
            }

            return DB::transaction(
                fn () => self::createWithinTransaction($options)
            );
        } catch (\Throwable $e) {
            Log::error(
                'Journal entry creation failed.',
                [
                    'docType' => $options['docType'] ?? null,
                    'docNumber' => $options['docNumber'] ?? null,
                    'error' => $e->getMessage(),
                ]
            );

            return null;
        }
    }

    private static function createWithinTransaction(
        array $options
    ): int {
        $entryNo = self::generateNextEntryNumber();

        $lines = $options['lines'];

        $totalAmount =
            self::calculateTotalDebit($lines);

        $entry = JournalEntry::create([
            'entryNo' => $entryNo,
            'entryDate' =>
                $options['entryDate'] ?? now()->toDateString(),

            'docType' =>
                $options['docType'] ?? null,

            'docNumber' =>
                $options['docNumber'] ?? null,

            'description2' =>
                $options['description'] ?? null,

            'totalAmount' =>
                $totalAmount,

            'createdAt' =>
                now(),
        ]);

        self::insertLines(
            $entry->entryID,
            $lines
        );

        $affectedAccountIDs =
            self::getAccountIdsFromLines($lines);

        self::scheduleBalanceRecalculation(
            $affectedAccountIDs
        );

        return (int) $entry->entryID;
    }

    // =========================================================
    // UPDATE
    // =========================================================

    public static function updateEntry(
        int $entryID,
        array $options
    ): bool {
        if ($entryID <= 0) {
            return false;
        }

        $lines = $options['lines'] ?? [];

        if (!self::validateLines($lines)) {
            return false;
        }

        try {
            if (DB::transactionLevel() > 0) {
                return self::updateWithinTransaction(
                    $entryID,
                    $options
                );
            }

            return DB::transaction(
                fn () =>
                    self::updateWithinTransaction(
                        $entryID,
                        $options
                    )
            );
        } catch (\Throwable $e) {
            Log::error(
                'Journal entry update failed.',
                [
                    'entryID' => $entryID,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    private static function updateWithinTransaction(
        int $entryID,
        array $options
    ): bool {
        /*
         * قفل رأس القيد يمنع تعديل نفس القيد
         * من عملية أخرى في نفس الوقت.
         */
        $entry = JournalEntry::where(
            'entryID',
            $entryID
        )
            ->lockForUpdate()
            ->first();

        if (!$entry) {
            return false;
        }

        /*
         * نحتاج الحسابات القديمة حتى نعيد
         * حساب أرصدتها بعد حذف التفاصيل القديمة.
         */
        $oldAccountIDs = JournalEntryLine::where(
            'entryID',
            $entryID
        )
            ->pluck('accountID')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $lines = $options['lines'];

        $totalAmount =
            self::calculateTotalDebit($lines);

        $entry->update([
            'entryDate' =>
                $options['entryDate'] ??
                $entry->entryDate,

            'description2' =>
                $options['description'] ??
                $entry->description2,

            'totalAmount' =>
                $totalAmount,
        ]);

        /*
         * حذف التفاصيل القديمة.
         */
        JournalEntryLine::where(
            'entryID',
            $entryID
        )->delete();

        /*
         * إدخال التفاصيل الجديدة دفعة واحدة.
         */
        self::insertLines(
            $entryID,
            $lines
        );

        $newAccountIDs =
            self::getAccountIdsFromLines($lines);

        /*
         * الحسابات المتأثرة =
         * القديمة + الجديدة.
         */
        $affectedAccountIDs = array_values(
            array_unique(
                array_merge(
                    $oldAccountIDs,
                    $newAccountIDs
                )
            )
        );

        self::scheduleBalanceRecalculation(
            $affectedAccountIDs
        );

        return true;
    }

    // =========================================================
    // DELETE BY ENTRY ID
    // =========================================================

    /**
     * حذف قيد محدد بواسطة entryID.
     *
     * هذه الطريقة هي الأفضل عندما نملك entryID
     * مباشرة، مثل سند الصرف.
     */
    public static function delete(
        int $entryID
    ): bool {
        if ($entryID <= 0) {
            return true;
        }

        try {
            if (DB::transactionLevel() > 0) {
                return self::deleteWithinTransaction(
                    $entryID
                );
            }

            return DB::transaction(
                fn () =>
                    self::deleteWithinTransaction(
                        $entryID
                    )
            );
        } catch (\Throwable $e) {
            Log::error(
                'Journal entry deletion failed.',
                [
                    'entryID' => $entryID,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    private static function deleteWithinTransaction(
        int $entryID
    ): bool {
        /*
         * قفل القيد قبل الحصول على تفاصيله.
         */
        $entry = JournalEntry::where(
            'entryID',
            $entryID
        )
            ->lockForUpdate()
            ->first();

        /*
         * إذا لم يعد القيد موجودًا،
         * فلا يوجد شيء نحتاج لحذفه.
         */
        if (!$entry) {
            return true;
        }

        /*
         * الحسابات التي ستتأثر بالحذف.
         */
        $affectedAccountIDs =
            JournalEntryLine::where(
                'entryID',
                $entryID
            )
                ->pluck('accountID')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

        /*
         * التفاصيل أولًا.
         */
        JournalEntryLine::where(
            'entryID',
            $entryID
        )->delete();

        /*
         * ثم رأس القيد.
         */
        $entry->delete();

        self::scheduleBalanceRecalculation(
            $affectedAccountIDs
        );

        return true;
    }

    // =========================================================
    // DELETE BY DOCUMENT NUMBER
    // =========================================================

    /**
     * تستخدم مع المستندات القديمة أو المستندات
     * التي لا نملك فيها entryID مباشرة.
     */
    public static function deleteByDocNumber(
        string $docNumber
    ): bool {
        if ($docNumber === '') {
            return true;
        }

        try {
            if (DB::transactionLevel() > 0) {
                return self::deleteByDocNumberWithinTransaction(
                    $docNumber
                );
            }

            return DB::transaction(
                fn () =>
                    self::deleteByDocNumberWithinTransaction(
                        $docNumber
                    )
            );
        } catch (\Throwable $e) {
            Log::error(
                'Journal entries deletion by document number failed.',
                [
                    'docNumber' => $docNumber,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    private static function deleteByDocNumberWithinTransaction(
        string $docNumber
    ): bool {
        /*
         * قفل القيود المطابقة قبل التعامل معها.
         */
        $entryIDs = JournalEntry::where(
            'docNumber',
            $docNumber
        )
            ->lockForUpdate()
            ->pluck('entryID')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($entryIDs)) {
            return true;
        }

        /*
         * الحسابات المتأثرة.
         */
        $affectedAccountIDs =
            JournalEntryLine::whereIn(
                'entryID',
                $entryIDs
            )
                ->pluck('accountID')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

        /*
         * حذف التفاصيل.
         */
        JournalEntryLine::whereIn(
            'entryID',
            $entryIDs
        )->delete();

        /*
         * حذف رؤوس القيود.
         */
        JournalEntry::whereIn(
            'entryID',
            $entryIDs
        )->delete();

        self::scheduleBalanceRecalculation(
            $affectedAccountIDs
        );

        return true;
    }

    // =========================================================
    // VALIDATION
    // =========================================================

    private static function validateLines(
        array $lines
    ): bool {
        if (empty($lines)) {
            return false;
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $accountID =
                (int) ($line['accountID'] ?? 0);

            if ($accountID <= 0) {
                return false;
            }

            $debit =
                (float) ($line['debit'] ?? 0);

            $credit =
                (float) ($line['credit'] ?? 0);

            $localDebit =
                (float) ($line['localDebit'] ?? 0);

            $localCredit =
                (float) ($line['localCredit'] ?? 0);

            if (
                $debit < 0 ||
                $credit < 0 ||
                $localDebit < 0 ||
                $localCredit < 0
            ) {
                return false;
            }

            /*
             * لا يسمح للسطر أن يكون مدينًا ودائنًا
             * في نفس الوقت بالقيمة المحلية.
             */
            if (
                $localDebit > 0 &&
                $localCredit > 0
            ) {
                return false;
            }

            $totalDebit += $localDebit;
            $totalCredit += $localCredit;
        }

        /*
         * يجب أن يحتوي القيد على حركة فعلية.
         */
        if (
            $totalDebit == 0 &&
            $totalCredit == 0
        ) {
            return false;
        }

        /*
         * القيد يجب أن يكون متوازنًا.
         */
        return abs(
            $totalDebit - $totalCredit
        ) <= 0.01;
    }

    // =========================================================
    // INSERT LINES
    // =========================================================

    private static function insertLines(
        int $entryID,
        array $lines
    ): void {
        $rows = [];

        foreach ($lines as $line) {
            $rows[] = [
                'entryID' =>
                    $entryID,

                'accountID' =>
                    (int) $line['accountID'],

                'coinsID' =>
                    $line['coinsID'] ?? null,

                'description2' =>
                    $line['description2'] ?? null,

                'exchangRate' =>
                    $line['exchangRate'] ?? 1,

                'debit' =>
                    $line['debit'] ?? 0,

                'credit' =>
                    $line['credit'] ?? 0,

                'localDebit' =>
                    $line['localDebit'] ?? 0,

                'localCredit' =>
                    $line['localCredit'] ?? 0,
            ];
        }

        if (empty($rows)) {
            return;
        }

        JournalEntryLine::insert($rows);
    }

    // =========================================================
    // ENTRY NUMBER
    // =========================================================

    private static function generateNextEntryNumber(): int
    {
        $lastEntryNo = JournalEntry::query()
            ->orderByDesc('entryNo')
            ->lockForUpdate()
            ->value('entryNo');

        return ((int) ($lastEntryNo ?? 0)) + 1;
    }

    // =========================================================
    // TOTAL
    // =========================================================

    private static function calculateTotalDebit(
        array $lines
    ): float {
        $total = 0.0;

        foreach ($lines as $line) {
            $total += (float) (
                $line['localDebit'] ?? 0
            );
        }

        return round($total, 2);
    }

    // =========================================================
    // ACCOUNT IDS
    // =========================================================

    private static function getAccountIdsFromLines(
        array $lines
    ): array {
        return collect($lines)
            ->pluck('accountID')
            ->filter(
                fn ($id) =>
                    is_numeric($id)
                    && (int) $id > 0
            )
            ->map(
                fn ($id) => (int) $id
            )
            ->unique()
            ->values()
            ->all();
    }

    // =========================================================
    // BALANCE RECALCULATION
    // =========================================================

    private static function scheduleBalanceRecalculation(
        array $accountIDs
    ): void {
        $accountIDs = collect($accountIDs)
            ->filter(
                fn ($id) =>
                    is_numeric($id)
                    && (int) $id > 0
            )
            ->map(
                fn ($id) => (int) $id
            )
            ->unique()
            ->values()
            ->all();

        if (empty($accountIDs)) {
            return;
        }

        /*
         * ننتظر نجاح الـ Transaction بالكامل.
         *
         * بذلك لا يتم تحديث account_balances
         * قبل التأكد من نجاح القيد.
         */
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(
                function () use ($accountIDs) {
                    AccountBalanceService::recalculateBatch(
                        $accountIDs
                    );
                }
            );

            return;
        }

        /*
         * احتياطًا إذا تم استدعاؤها خارج Transaction.
         */
        AccountBalanceService::recalculateBatch(
            $accountIDs
        );
    }
}