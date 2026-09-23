<?php

namespace App\Services;

use App\Models\Accounting\PaymentVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentVoucherService
{
    /**
     * ⭐ توليد رقم السند التالي (public - يُستدعى من Controller)
     */
    public static function generateNextVoucherNumber(): string
    {
        return self::generateVoucherNumber();
    }

    /**
     * إنشاء سند صرف + قيد محاسبي (مع حماية كاملة)
     */
    public static function create(array $data): ?PaymentVoucher
    {
        // ⭐ حماية 1: Cache Lock
        $lock = Cache::lock('payment_voucher_create', 10);

        if (!$lock->get()) {
            Log::warning('PaymentVoucherService@create: طلب مزدوج (Cache Lock)');
            return null;
        }

        DB::beginTransaction();

        try {
            // ⭐ حماية 2: التحقق من عدم تكرار رقم السند
            if (empty($data['voucherNumber'])) {
                $data['voucherNumber'] = self::generateVoucherNumber();
            }

            // ⭐ تحقق: هل الرقم موجود مسبقًا؟
            $exists = PaymentVoucher::where('voucherNumber', $data['voucherNumber'])->exists();
            if ($exists) {
                Log::warning('PaymentVoucherService@create: رقم السند مكرر ' . $data['voucherNumber']);
                DB::rollBack();
                return null;
            }

            $data['voucherDate'] = $data['voucherDate'] ?? now()->toDateString();
            $data['localAmount'] = ($data['amount'] ?? 0) * ($data['exchangeRate'] ?? 1);

            $voucher = PaymentVoucher::create($data);

            self::createJournalEntry($voucher);

            DB::commit();

            Log::info('PaymentVoucherService@create: تم إنشاء السند ' . $voucher->voucherNumber);

            return $voucher;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PaymentVoucherService@create: ' . $e->getMessage());
            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * تعديل سند صرف (مع حماية كاملة)
     */
    public static function update(int $id, array $data): ?PaymentVoucher
    {
        // ⭐ حماية 1: Cache Lock
        $lockKey = 'payment_voucher_update_' . $id;
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Log::warning('PaymentVoucherService@update: طلب مزدوج - السند #' . $id);
            return null;
        }

        $voucher = PaymentVoucher::find($id);
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
            Log::info('PaymentVoucherService@update: لا توجد تغييرات - تجاهل');
            $lock->release();
            return $voucher;
        }

        DB::beginTransaction();

        try {
            unset($data['voucherNumber']);

            // ⭐ حذف القيد القديم (PV-{id})
            JournalEntryService::deleteByDocNumber('PV-' . $voucher->paymentID);

            $data['localAmount'] = ($data['amount'] ?? 0) * ($data['exchangeRate'] ?? 1);
            $voucher->update($data);

            self::createJournalEntry($voucher);

            DB::commit();

            Log::info('PaymentVoucherService@update: تم تعديل السند ' . $voucher->voucherNumber);

            return $voucher;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PaymentVoucherService@update: ' . $e->getMessage());
            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * حذف سند صرف + قيده
     */
    public static function delete(int $id): bool
    {
        $lockKey = 'payment_voucher_delete_' . $id;
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Log::warning('PaymentVoucherService@delete: طلب مزدوج - السند #' . $id);
            return false;
        }

        $voucher = PaymentVoucher::find($id);
        if (!$voucher) {
            $lock->release();
            return false;
        }

        DB::beginTransaction();

        try {
            // ⭐ حذف القيد المرتبط
            JournalEntryService::deleteByDocNumber('PV-' . $voucher->paymentID);
            $voucher->delete();

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PaymentVoucherService@delete: ' . $e->getMessage());
            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * ⭐⭐ إنشاء القيد المحاسبي لسند الصرف
     *
     * سند الصرف:
     * - مدين: المورد (debitAccountID) ← النقدية تدخل إليه
     * - دائن: الصندوق/البنك (creditAccountID) ← النقدية تخرج منه
     */
    private static function createJournalEntry(PaymentVoucher $voucher): void
    {
        $amount = (float) $voucher->amount;
        $rate   = (float) $voucher->exchangeRate;
        $local  = (float) $voucher->localAmount;

        // ⭐ الوصف: يعتمد على المورد (الحساب المدين)
        $debitAcc = $voucher->debitAccount;
        $desc = 'سند صرف رقم ' . $voucher->voucherNumber . ' - ' . ($debitAcc->accName ?? '');

        JournalEntryService::create([
            'docType'     => 'سند صرف',
            'docNumber'   => 'PV-' . $voucher->paymentID,
            'entryDate'   => $voucher->voucherDate,
            'description' => $desc,
            'lines'       => [
                // ⭐ سطر المدين: المورد (يستلم النقدية)
                [
                    'accountID'   => $voucher->debitAccountID,
                    'coinsID'     => $voucher->coinsID,
                    'exchangRate' => $rate,
                    'debit'       => $amount,
                    'credit'      => 0,
                    'localDebit'  => $local,
                    'localCredit' => 0,
                ],
                // ⭐ سطر الدائن: الصندوق/البنك (يدفع النقدية)
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
     * الصيغة: PV-YYYYMMDD-XXXX
     */
    private static function generateVoucherNumber(): string
    {
        $today = now()->format('Ymd');

        $lastVoucher = PaymentVoucher::where('voucherNumber', 'LIKE', "PV-{$today}-%")
            ->orderByDesc('paymentID')
            ->first();

        if ($lastVoucher) {
            $lastNumber = (int) substr($lastVoucher->voucherNumber, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        do {
            $voucherNumber = 'PV-' . $today . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $exists = PaymentVoucher::where('voucherNumber', $voucherNumber)->exists();
            if ($exists) $nextNumber++;
        } while ($exists);

        return $voucherNumber;
    }
}