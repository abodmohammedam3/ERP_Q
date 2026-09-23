<?php

namespace App\Services;

use App\Models\Accounting\ReceiptVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ReceiptVoucherService
{
    /**
     * ⭐ توليد رقم السند التالي (public - يُستدعى من Controller)
     */
    public static function generateNextVoucherNumber(): string
    {
        return self::generateVoucherNumber();
    }

    /**
     * إنشاء سند قبض + قيد محاسبي (مع حماية كاملة)
     */
    public static function create(array $data): ?ReceiptVoucher
    {
        // ⭐ حماية 1: Cache Lock
        $lock = Cache::lock('receipt_voucher_create', 10);

        if (!$lock->get()) {
            Log::warning('ReceiptVoucherService@create: طلب مزدوج (Cache Lock)');
            return null;
        }

        DB::beginTransaction();

        try {
            // ⭐ حماية 2: التحقق من عدم تكرار رقم السند
            if (empty($data['voucherNumber'])) {
                $data['voucherNumber'] = self::generateVoucherNumber();
            }

            // ⭐ تحقق: هل الرقم موجود مسبقًا؟
            $exists = ReceiptVoucher::where('voucherNumber', $data['voucherNumber'])->exists();
            if ($exists) {
                Log::warning('ReceiptVoucherService@create: رقم السند مكرر ' . $data['voucherNumber']);
                DB::rollBack();
                return null;
            }

            $data['voucherDate'] = $data['voucherDate'] ?? now()->toDateString();
            $data['localAmount'] = ($data['amount'] ?? 0) * ($data['exchangeRate'] ?? 1);

            $voucher = ReceiptVoucher::create($data);

            self::createJournalEntry($voucher);

            DB::commit();

            Log::info('ReceiptVoucherService@create: تم إنشاء السند ' . $voucher->voucherNumber);

            return $voucher;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ReceiptVoucherService@create: ' . $e->getMessage());
            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * تعديل سند قبض (مع حماية كاملة)
     */
    public static function update(int $id, array $data): ?ReceiptVoucher
    {
        // ⭐ حماية 1: Cache Lock
        $lockKey = 'receipt_voucher_update_' . $id;
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Log::warning('ReceiptVoucherService@update: طلب مزدوج - السند #' . $id);
            return null;
        }

        $voucher = ReceiptVoucher::find($id);
        if (!$voucher) {
            $lock->release();
            return null;
        }

        // ⭐ حماية 2: التحقق من وجود تغييرات فعلية
        $hasChanges = false;
        foreach ($data as $key => $value) {
            if (isset($voucher->$key) && $voucher->$key != $value) {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            Log::info('ReceiptVoucherService@update: لا توجد تغييرات - تجاهل');
            $lock->release();
            return $voucher;
        }

        DB::beginTransaction();

        try {
            unset($data['voucherNumber']);

            JournalEntryService::deleteByDocNumber('RC-' . $voucher->receiptID);

            $data['localAmount'] = ($data['amount'] ?? 0) * ($data['exchangeRate'] ?? 1);
            $voucher->update($data);

            self::createJournalEntry($voucher);

            DB::commit();

            Log::info('ReceiptVoucherService@update: تم تعديل السند ' . $voucher->voucherNumber);

            return $voucher;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ReceiptVoucherService@update: ' . $e->getMessage());
            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * حذف سند قبض + قيده
     */
    public static function delete(int $id): bool
    {
        $lockKey = 'receipt_voucher_delete_' . $id;
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Log::warning('ReceiptVoucherService@delete: طلب مزدوج - السند #' . $id);
            return false;
        }

        $voucher = ReceiptVoucher::find($id);
        if (!$voucher) {
            $lock->release();
            return false;
        }

        DB::beginTransaction();

        try {
            JournalEntryService::deleteByDocNumber('RC-' . $voucher->receiptID);
            $voucher->delete();

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ReceiptVoucherService@delete: ' . $e->getMessage());
            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * إنشاء القيد المحاسبي
     */
    private static function createJournalEntry(ReceiptVoucher $voucher): void
    {
        $amount = (float) $voucher->amount;
        $rate   = (float) $voucher->exchangeRate;
        $local  = (float) $voucher->localAmount;

        $creditAcc = $voucher->creditAccount;
        $desc = 'سند قبض رقم ' . $voucher->voucherNumber . ' - ' . ($creditAcc->accName ?? '');

        JournalEntryService::create([
            'docType'     => 'سند قبض',
            'docNumber'   => 'RC-' . $voucher->receiptID,
            'entryDate'   => $voucher->voucherDate,
            'description' => $desc,
            'lines'       => [
                [
                    'accountID'   => $voucher->debitAccountID,
                    'coinsID'     => $voucher->coinsID,
                    'exchangRate' => $rate,
                    'debit'       => $amount,
                    'credit'      => 0,
                    'localDebit'  => $local,
                    'localCredit' => 0,
                ],
                [
                    'accountID'   => $voucher->creditAccountID,
                    'coinsID'     => $voucher->coinsID,
                    'exchangRate' => $rate,
                    'debit'       => 0,
                    'credit'      => $amount,
                    'localDebit'  => 0,
                    'localCredit' => $local,
                ],
            ],
        ]);
    }

    /**
     * توليد رقم سند جديد
     */
    private static function generateVoucherNumber(): string
    {
        $today = now()->format('Ymd');

        $lastVoucher = ReceiptVoucher::where('voucherNumber', 'LIKE', "RC-{$today}-%")
            ->orderByDesc('receiptID')
            ->first();

        if ($lastVoucher) {
            $lastNumber = (int) substr($lastVoucher->voucherNumber, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        do {
            $voucherNumber = 'RC-' . $today . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $exists = ReceiptVoucher::where('voucherNumber', $voucherNumber)->exists();
            if ($exists) $nextNumber++;
        } while ($exists);

        return $voucherNumber;
    }
}