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

        //  حساب المدين والدائن (وليس الصافي)
        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit  += (float) ($line['localDebit']  ?? 0);
            $totalCredit += (float) ($line['localCredit'] ?? 0);
        }

        // لا يوجد مدين ولا دائن
        if ($totalDebit == 0 && $totalCredit == 0) {
            Log::warning('JournalEntryService: لا يوجد مدين ولا دائن');
            return null;
        }

        // التحقق من التوازن
        if (abs($totalDebit - $totalCredit) > 0.01) {
            Log::warning('JournalEntryService: القيد غير متوازن');
            return null;
        }

        // توليد رقم القيد
        $nextEntryNo = (JournalEntry::max('entryNo') ?? 0) + 1;

        DB::beginTransaction();

        try {
            // 1) رأس القيد
            $entry = JournalEntry::create([
                'entryNo'     => $nextEntryNo,
                'entryDate'   => $entryDate,
                'docType'     => $docType,
                'docNumber'   => $docNumber,
                'description2'=> $description,
                'totalAmount' => $totalDebit,   // ⭐ القيمة الصحيحة
                'createdAt'   => now(),
            ]);

            // 2) الأسطر
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

            DB::commit();

            return $entry->entryID;

        } catch (\Throwable $e) {
            DB::rollBack();

            //  مؤقت: اطبع الخطأ الحقيقي
            echo "❌ ERROR: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . " (Line " . $e->getLine() . ")\n";

            Log::error('JournalEntryService: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * حذف قيد برقم المستند
     */
    public static function deleteByDocNumber(string $docNumber): bool
    {
        try {
            $entries = JournalEntry::where('docNumber', $docNumber)->get();

            foreach ($entries as $entry) {
                JournalEntryLine::where('entryID', $entry->entryID)->delete();
                $entry->delete();
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('JournalEntryService: ' . $e->getMessage());
            return false;
        }
    }
}