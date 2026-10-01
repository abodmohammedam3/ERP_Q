<?php

namespace App\Services;

use App\Models\Accounting\CharAccount;
use App\Models\Accounting\PaymentVoucher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentVoucherService
{
    public static function generateNextVoucherNumber(): string
    {
        return self::generateVoucherNumber();
    }

    // ══════════════════════════════════════════════════════════
    // CREATE
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

            if (
                PaymentVoucher::where(
                    'voucherNumber',
                    $data['voucherNumber']
                )->exists()
            ) {
                Log::warning(
                    'PaymentVoucherService@create: رقم مكرر '
                    . $data['voucherNumber']
                );

                DB::rollBack();

                return null;
            }

            $data['voucherDate'] = $data['voucherDate']
                ?? now()->toDateString();

            $data['localAmount'] =
                ($data['amount'] ?? 0)
                * ($data['exchangeRate'] ?? 1);

            // التحقق من رصيد حساب الدفع
            if ($error = self::validateBalance($data)) {
                Log::warning(
                    'PaymentVoucherService@create: ' . $error
                );

                DB::rollBack();

                return $error;
            }

            $voucher = PaymentVoucher::create($data);

            $entryID = self::createJournalEntry($voucher);

            if ($entryID) {
                $voucher->update([
                    'entryID' => $entryID,
                ]);
            }

            DB::commit();

            self::recalculateBalances($voucher);

            Log::info(
                'PaymentVoucherService@create: '
                . $voucher->voucherNumber
            );

            return $voucher;

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error(
                'PaymentVoucherService@create: '
                . $e->getMessage()
            );

            return null;

        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════

    public static function update(
        int $id,
        array $data
    ): PaymentVoucher|string|null {
        $lockKey = 'payment_voucher_update_' . $id;

        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Log::warning(
                'PaymentVoucherService@update: طلب مزدوج #' . $id
            );

            return null;
        }

        $voucher = PaymentVoucher::find($id);

        if (!$voucher) {
            $lock->release();
            return null;
        }

        DB::beginTransaction();

        try {
            // رقم السند لا يتغير أثناء التعديل
            unset($data['voucherNumber']);

            $data['localAmount'] =
                ($data['amount'] ?? 0)
                * ($data['exchangeRate'] ?? 1);

            // التحقق من الرصيد مع استثناء السند الحالي
            if ($error = self::validateBalance($data, $id)) {
                Log::warning(
                    'PaymentVoucherService@update: ' . $error
                );

                DB::rollBack();

                return $error;
            }

            $oldBeneficiaryID =
                (int) $voucher->beneficiaryAccountID;

            $oldPaymentID =
                (int) $voucher->paymentAccountID;

            $voucher->update($data);

            if ($voucher->entryID) {
                JournalEntryService::updateEntry(
                    $voucher->entryID,
                    [
                        'docType'     => 'سند صرف',
                        'entryDate'   => $voucher->voucherDate,
                        'description' =>
                            'سند صرف رقم '
                            . $voucher->voucherNumber
                            . ' - '
                            . (
                                $voucher
                                    ->beneficiaryAccount
                                    ->accName
                                ?? ''
                            ),
                        'lines' => self::buildEntryLines($voucher),
                    ]
                );
            } else {
                $entryID = self::createJournalEntry($voucher);

                if ($entryID) {
                    $voucher->update([
                        'entryID' => $entryID,
                    ]);
                }
            }

            DB::commit();

            AccountBalanceService::recalculateBatch(
                array_unique([
                    $oldBeneficiaryID,
                    $oldPaymentID,
                    (int) $voucher->beneficiaryAccountID,
                    (int) $voucher->paymentAccountID,
                ])
            );

            Log::info(
                'PaymentVoucherService@update: '
                . $voucher->voucherNumber
            );

            return $voucher;

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error(
                'PaymentVoucherService@update: '
                . $e->getMessage()
            );

            return null;

        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════════

    public static function delete(int $id): bool
    {
        $lockKey = 'payment_voucher_delete_' . $id;

        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Log::warning(
                'PaymentVoucherService@delete: طلب مزدوج #' . $id
            );

            return false;
        }

        $voucher = PaymentVoucher::find($id);

        if (!$voucher) {
            $lock->release();
            return false;
        }

        DB::beginTransaction();

        try {
            $beneficiaryID =
                (int) $voucher->beneficiaryAccountID;

            $paymentID =
                (int) $voucher->paymentAccountID;

            JournalEntryService::deleteByDocNumber(
                'PV-' . $voucher->paymentID
            );

            $voucher->delete();

            DB::commit();

            AccountBalanceService::recalculateBatch([
                $beneficiaryID,
                $paymentID,
            ]);

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error(
                'PaymentVoucherService@delete: '
                . $e->getMessage()
            );

            return false;

        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    // BALANCE VALIDATION
    // ══════════════════════════════════════════════════════════

    private static function validateBalance(
        array $data,
        ?int $excludeVoucherID = null
    ): ?string {
        $paymentMethod = $data['paymentMethod'] ?? null;

        if (!in_array($paymentMethod, ['cash', 'bank'])) {
            return null;
        }

        $paymentAccountID =
            (int) ($data['paymentAccountID'] ?? 0);

        if (!$paymentAccountID) {
            return null;
        }

        $amount =
            (float) ($data['amount'] ?? 0);

        $exchangeRate =
            (float) ($data['exchangeRate'] ?? 1);

        $localAmount =
            $amount * $exchangeRate;

        $balance =
            AccountBalanceService::getBalance(
                $paymentAccountID
            );

        /*
         * عند تعديل سند:
         * نعيد مبلغ السند القديم إلى الرصيد
         * قبل مقارنة المبلغ الجديد.
         */
        if ($excludeVoucherID) {
            $oldVoucher =
                PaymentVoucher::find($excludeVoucherID);

            if (
                $oldVoucher
                && (int) $oldVoucher->paymentAccountID
                    === $paymentAccountID
            ) {
                $balance +=
                    (float) $oldVoucher->localAmount;
            }
        }

        if ($localAmount > $balance) {
            $account =
                CharAccount::find($paymentAccountID);

            $name =
                $account->accName ?? 'الحساب';

            return
                'رصيد ' . $name . ' غير كافٍ. '
                . 'الرصيد المتاح: '
                . number_format($balance, 2)
                . ' | المطلوب: '
                . number_format($localAmount, 2);
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════
    // JOURNAL ENTRY LINES
    // ══════════════════════════════════════════════════════════

    private static function buildEntryLines(
        PaymentVoucher $voucher
    ): array {
        $amount =
            (float) $voucher->amount;

        $rate =
            (float) $voucher->exchangeRate;

        $local =
            (float) $voucher->localAmount;

        return [
            // مدين: الحساب المستفيد
            [
                'accountID'   => $voucher->beneficiaryAccountID,
                'coinsID'     => $voucher->coinsID,
                'exchangRate' => $rate,
                'debit'       => $amount,
                'credit'      => 0,
                'localDebit'  => $local,
                'localCredit' => 0,
            ],

            // دائن: حساب الدفع
            [
                'accountID'   => $voucher->paymentAccountID,
                'coinsID'     => $voucher->coinsID,
                'exchangRate' => $rate,
                'debit'       => 0,
                'credit'      => $amount,
                'localDebit'  => 0,
                'localCredit' => $local,
            ],
        ];
    }

    private static function createJournalEntry(
        PaymentVoucher $voucher
    ): ?int {
        $beneficiary =
            $voucher->beneficiaryAccount;

        $desc =
            'سند صرف رقم '
            . $voucher->voucherNumber
            . ' - '
            . ($beneficiary->accName ?? '');

        return JournalEntryService::create([
            'docType'     => 'سند صرف',
            'docNumber'   => 'PV-' . $voucher->paymentID,
            'entryDate'   => $voucher->voucherDate,
            'description' => $desc,
            'lines'       => self::buildEntryLines($voucher),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // RECALCULATE BALANCES
    // ══════════════════════════════════════════════════════════

    private static function recalculateBalances(
        PaymentVoucher $voucher
    ): void {
        AccountBalanceService::recalculateBatch(
            array_unique([
                (int) $voucher->beneficiaryAccountID,
                (int) $voucher->paymentAccountID,
            ])
        );
    }

    // ══════════════════════════════════════════════════════════
    // GENERATE NUMBER
    // ══════════════════════════════════════════════════════════

    private static function generateVoucherNumber(): string
    {
        $today = now()->format('Ymd');

        $lastVoucher =
            PaymentVoucher::where(
                'voucherNumber',
                'LIKE',
                "PV-{$today}-%"
            )
            ->orderByDesc('paymentID')
            ->first();

        if ($lastVoucher) {
            $lastNumber =
                (int) substr(
                    $lastVoucher->voucherNumber,
                    -4
                );

            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        do {
            $voucherNumber =
                'PV-'
                . $today
                . '-'
                . str_pad(
                    $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            $exists =
                PaymentVoucher::where(
                    'voucherNumber',
                    $voucherNumber
                )->exists();

            if ($exists) {
                $nextNumber++;
            }

        } while ($exists);

        return $voucherNumber;
    }
}