<?php

namespace App\Services;

use App\Models\Accounting\PaymentVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentVoucherService
{
    public static function generateNextVoucherNumber(): string
    {
        return self::generateVoucherNumber();
    }

    // ══════════════════════════════════════════════════════════
    //  CREATE
    // ══════════════════════════════════════════════════════════

    public static function create(array $data): PaymentVoucher|string|null
    {
        $lock = Cache::lock('payment_voucher_create', 10);

        if (!$lock->get()) {
            Log::warning('PaymentVoucherService@create: طلب مزدوج');
            return null;
        }

        DB::beginTransaction();

        try {
            if (empty($data['voucherNumber'])) {
                $data['voucherNumber'] = self::generateVoucherNumber();
            }

            if (PaymentVoucher::where('voucherNumber', $data['voucherNumber'])->exists()) {
                Log::warning('PaymentVoucherService@create: رقم مكرر ' . $data['voucherNumber']);
                DB::rollBack();
                return null;
            }

            $data['voucherDate'] = $data['voucherDate'] ?? now()->toDateString();
            $data['localAmount'] = ($data['amount'] ?? 0) * ($data['exchangeRate'] ?? 1);

            // ✅ التحقق من الرصيد
            if ($error = self::validateBalance($data)) {
                Log::warning('PaymentVoucherService@create: ' . $error);
                DB::rollBack();
                return $error;
            }

            $voucher = PaymentVoucher::create($data);

            $entryID = self::createJournalEntry($voucher);

            if ($entryID) {
                $voucher->update(['entryID' => $entryID]);
            }

            DB::commit();

            self::recalculateBalances($voucher);

            Log::info('PaymentVoucherService@create: ' . $voucher->voucherNumber);

            return $voucher;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PaymentVoucherService@create: ' . $e->getMessage());
            return null;
        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    //  UPDATE
    // ══════════════════════════════════════════════════════════

    public static function update(int $id, array $data): PaymentVoucher|string|null
    {
        $lockKey = 'payment_voucher_update_' . $id;
        $lock    = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Log::warning('PaymentVoucherService@update: طلب مزدوج #' . $id);
            return null;
        }

        $voucher = PaymentVoucher::find($id);
        if (!$voucher) {
            $lock->release();
            return null;
        }

        DB::beginTransaction();

        try {
            unset($data['voucherNumber']);

            $data['localAmount'] = ($data['amount'] ?? 0) * ($data['exchangeRate'] ?? 1);

            // ✅ التحقق من الرصيد (مع استثناء السند الحالي)
            if ($error = self::validateBalance($data, $id)) {
                Log::warning('PaymentVoucherService@update: ' . $error);
                DB::rollBack();
                return $error;
            }

            $oldCreditID = (int) $voucher->creditAccountID;
            $oldDebitID  = (int) $voucher->debitAccountID;

            $voucher->update($data);

            if ($voucher->entryID) {
                JournalEntryService::updateEntry($voucher->entryID, [
                    'docType'     => 'سند صرف',
                    'entryDate'   => $voucher->voucherDate,
                    'description' => 'سند صرف رقم ' . $voucher->voucherNumber
                                   . ' - ' . ($voucher->creditAccount->accName ?? ''),
                    'lines'       => self::buildEntryLines($voucher),
                ]);
            } else {
                $entryID = self::createJournalEntry($voucher);
                if ($entryID) {
                    $voucher->update(['entryID' => $entryID]);
                }
            }

            DB::commit();

            AccountBalanceService::recalculateBatch(array_unique([
                $oldCreditID,
                $oldDebitID,
                (int) $voucher->creditAccountID,
                (int) $voucher->debitAccountID,
            ]));

            Log::info('PaymentVoucherService@update: ' . $voucher->voucherNumber);

            return $voucher;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PaymentVoucherService@update: ' . $e->getMessage());
            return null;
        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    //  DELETE
    // ══════════════════════════════════════════════════════════

    public static function delete(int $id): bool
    {
        $lockKey = 'payment_voucher_delete_' . $id;
        $lock    = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Log::warning('PaymentVoucherService@delete: طلب مزدوج #' . $id);
            return false;
        }

        $voucher = PaymentVoucher::find($id);
        if (!$voucher) {
            $lock->release();
            return false;
        }

        DB::beginTransaction();

        try {
            $creditID = (int) $voucher->creditAccountID;
            $debitID  = (int) $voucher->debitAccountID;

            JournalEntryService::deleteByDocNumber('PV-' . $voucher->paymentID);
            $voucher->delete();

            DB::commit();

            AccountBalanceService::recalculateBatch([$creditID, $debitID]);

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PaymentVoucherService@delete: ' . $e->getMessage());
            return false;
        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    //  Balance Validation
    // ══════════════════════════════════════════════════════════

    /**
     * التحقق من رصيد الصندوق/البنك
     */
    private static function validateBalance(array $data, ?int $excludeVoucherID = null): ?string
    {
        $paymentMethod = $data['paymentMethod'] ?? null;

        if (!in_array($paymentMethod, ['cash', 'bank'])) {
            return null;
        }

        $debitAccountID = (int) ($data['debitAccountID'] ?? 0);
        if (!$debitAccountID) return null;

        $amount       = (float) ($data['amount'] ?? 0);
        $exchangeRate = (float) ($data['exchangeRate'] ?? 1);
        $localAmount  = $amount * $exchangeRate;

        $balance = AccountBalanceService::getBalance($debitAccountID);

        // إذا كان تعديلًا، أضف المبلغ القديم للسند الحالي (لأنه سيُعاد خصمه)
        if ($excludeVoucherID) {
            $oldVoucher = PaymentVoucher::find($excludeVoucherID);
            if ($oldVoucher && (int) $oldVoucher->debitAccountID === $debitAccountID) {
                $balance += (float) $oldVoucher->localAmount;
            }
        }

        if ($localAmount > $balance) {
            $account = \App\Models\Accounting\CharAccount::find($debitAccountID);
            $name    = $account->accName ?? 'الحساب';

            return 'رصيد ' . $name . ' غير كافٍ. '
                 . 'الرصيد المتاح: ' . number_format($balance, 2)
                 . ' | المطلوب: ' . number_format($localAmount, 2);
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════
    //  Journal Entry
    // ══════════════════════════════════════════════════════════

    /**
     * بناء أسطر القيد
     *
     * ⚠️ نموذج سند الصرف:
     *   - creditAccountID = المورد
     *   - debitAccountID  = الصندوق/البنك
     *
     * في القيد:
     *   - المورد (ندفع له) → مدين
     *   - الصندوق/البنك    → دائن
     */
    private static function buildEntryLines(PaymentVoucher $voucher): array
    {
        $amount = (float) $voucher->amount;
        $rate   = (float) $voucher->exchangeRate;
        $local  = (float) $voucher->localAmount;

        return [
            // مدين: المورد
            [
                'accountID'   => $voucher->creditAccountID,
                'coinsID'     => $voucher->coinsID,
                'exchangRate' => $rate,
                'debit'       => $amount,
                'credit'      => 0,
                'localDebit'  => $local,
                'localCredit' => 0,
            ],
            // دائن: الصندوق/البنك
            [
                'accountID'   => $voucher->debitAccountID,
                'coinsID'     => $voucher->coinsID,
                'exchangRate' => $rate,
                'debit'       => 0,
                'credit'      => $amount,
                'localDebit'  => 0,
                'localCredit' => $local,
            ],
        ];
    }

    private static function createJournalEntry(PaymentVoucher $voucher): ?int
    {
        $supplier = $voucher->creditAccount;

        $desc = 'سند صرف رقم ' . $voucher->voucherNumber
              . ' - ' . ($supplier->accName ?? '');

        return JournalEntryService::create([
            'docType'     => 'سند صرف',
            'docNumber'   => 'PV-' . $voucher->paymentID,
            'entryDate'   => $voucher->voucherDate,
            'description' => $desc,
            'lines'       => self::buildEntryLines($voucher),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════

    private static function recalculateBalances(PaymentVoucher $voucher): void
    {
        AccountBalanceService::recalculateBatch(array_unique([
            (int) $voucher->creditAccountID,
            (int) $voucher->debitAccountID,
        ]));
    }

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