<?php

namespace App\Services;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalEntryService
{
    /**
     * إنشاء قيد محاسبي
     */
    public static function create(array $options): ?int
    {
        $docType     = $options['docType']     ?? null;
        $docNumber   = $options['docNumber']   ?? null;
        $entryDate   = $options['entryDate']   ?? now();
        $description = $options['description'] ?? '';
        $lines       = $options['lines']       ?? [];

        if (empty($lines)) {
            Log::warning('JournalEntryService: لا توجد أسطر');
            return null;
        }

        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit  += (float) ($line['localDebit']  ?? 0);
            $totalCredit += (float) ($line['localCredit'] ?? 0);
        }

        if ($totalDebit == 0 && $totalCredit == 0) {
            Log::warning('JournalEntryService: لا يوجد مدين ولا دائن');
            return null;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            Log::warning('JournalEntryService: القيد غير متوازن');
            return null;
        }

        $nextEntryNo = (JournalEntry::max('entryNo') ?? 0) + 1;

        DB::beginTransaction();

        try {
            $entry = JournalEntry::create([
                'entryNo'      => $nextEntryNo,
                'entryDate'    => $entryDate,
                'docType'      => $docType,
                'docNumber'    => $docNumber,
                'description2' => $description,
                'totalAmount'  => $totalDebit,
                'createdAt'    => now(),
            ]);

            foreach ($lines as $line) {
                JournalEntryLine::create([
                    'entryID'      => $entry->entryID,
                    'accountID'    => $line['accountID'],
                    'coinsID'      => $line['coinsID']     ?? null,
                    'description2' => $line['description'] ?? '',
                    'exchangRate'  => $line['exchangRate'] ?? 1,
                    'debit'        => $line['debit']        ?? 0,
                    'credit'       => $line['credit']       ?? 0,
                    'localDebit'   => $line['localDebit']   ?? 0,
                    'localCredit'  => $line['localCredit']  ?? 0,
                ]);
            }

            $affectedAccountIds = collect($lines)
                ->pluck('accountID')
                ->filter()
                ->unique()
                ->values()
                ->all();

            DB::commit();

            if (!empty($affectedAccountIds)) {
                AccountBalanceService::recalculateBatch($affectedAccountIds);
            }

            return $entry->entryID;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('JournalEntryService: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * تحديث قيد موجود (يحافظ على entryID و entryNo و docNumber)
     */
    public static function updateEntry(int $entryID, array $options): bool
    {
        $entryDate   = $options['entryDate']   ?? now();
        $description = $options['description'] ?? '';
        $lines       = $options['lines']       ?? [];

        if (empty($lines)) {
            Log::warning('JournalEntryService::updateEntry: لا توجد أسطر');
            return false;
        }

        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit  += (float) ($line['localDebit']  ?? 0);
            $totalCredit += (float) ($line['localCredit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            Log::warning('JournalEntryService::updateEntry: القيد غير متوازن');
            return false;
        }

        // ✅ احفظ الحسابات القديمة قبل الحذف
        $oldAccountIds = JournalEntryLine::where('entryID', $entryID)
            ->pluck('accountID')
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::beginTransaction();

        try {
            $entry = JournalEntry::find($entryID);

            if (!$entry) {
                DB::rollBack();
                Log::warning('JournalEntryService::updateEntry: القيد غير موجود');
                return false;
            }

            // 1) حدّث الرأس (لا نلمس entryNo ولا docNumber ولا docType)
            $entry->update([
                'entryDate'    => $entryDate,
                'description2' => $description,
                'totalAmount'  => $totalDebit,
            ]);

            // 2) احذف السطور القديمة
            JournalEntryLine::where('entryID', $entryID)->delete();

            // 3) أضف السطور الجديدة (بنفس entryID)
            foreach ($lines as $line) {
                JournalEntryLine::create([
                    'entryID'      => $entryID,
                    'accountID'    => $line['accountID'],
                    'coinsID'      => $line['coinsID']     ?? null,
                    'description2' => $line['description'] ?? '',
                    'exchangRate'  => $line['exchangRate'] ?? 1,
                    'debit'        => $line['debit']        ?? 0,
                    'credit'       => $line['credit']       ?? 0,
                    'localDebit'   => $line['localDebit']   ?? 0,
                    'localCredit'  => $line['localCredit']  ?? 0,
                ]);
            }

            // ✅ اجمع الحسابات الجديدة
            $newAccountIds = collect($lines)
                ->pluck('accountID')
                ->filter()
                ->unique()
                ->values()
                ->all();

            DB::commit();

            // ✅ إعادة حساب كل الحسابات المتأثرة (القديمة + الجديدة)
            $affectedAccountIds = array_unique(array_merge($oldAccountIds, $newAccountIds));

            if (!empty($affectedAccountIds)) {
                AccountBalanceService::recalculateBatch($affectedAccountIds);
            }

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('JournalEntryService::updateEntry: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف قيد برقم المستند
     */
    public static function deleteByDocNumber(string $docNumber): bool
    {
        DB::beginTransaction();

        try {
            $entries = JournalEntry::where('docNumber', $docNumber)->get();

            $affectedAccountIds = [];

            foreach ($entries as $entry) {
                $accountIds = JournalEntryLine::where('entryID', $entry->entryID)
                    ->pluck('accountID')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $affectedAccountIds = array_merge($affectedAccountIds, $accountIds);

                JournalEntryLine::where('entryID', $entry->entryID)->delete();
                $entry->delete();
            }

            DB::commit();

            $affectedAccountIds = array_unique($affectedAccountIds);

            if (!empty($affectedAccountIds)) {
                AccountBalanceService::recalculateBatch($affectedAccountIds);
            }

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('JournalEntryService: ' . $e->getMessage());
            return false;
        }
    }
}