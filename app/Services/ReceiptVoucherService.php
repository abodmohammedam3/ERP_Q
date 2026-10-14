<?php

namespace App\Services;

use App\Models\Accounting\Bank;
use App\Models\Accounting\Box;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\ReceiptVoucher;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptVoucherService
{
    private const CREATE_LOCK_TTL = 15;
    private const UPDATE_LOCK_TTL = 15;
    private const DELETE_LOCK_TTL = 15;
    private const POST_LOCK_TTL   = 15;

    private const CACHE_TTL = 3600;

    private const DESCENDANT_MAX_LEVELS = 10;

    // =========================================================
    // NUMBER
    // =========================================================

    public static function generateNextVoucherNumber(): string
    {
        return self::generateVoucherNumber();
    }

    private static function generateVoucherNumber(): string
    {
        $today  = now()->format('Ymd');
        $prefix = 'RC-' . $today . '-';

        $lastVoucherNumber = ReceiptVoucher::query()
            ->where('voucherNumber', 'like', $prefix . '%')
            ->orderByDesc('receiptID')
            ->value('voucherNumber');

        $nextNumber = 1;

        if ($lastVoucherNumber) {
            $suffix = substr($lastVoucherNumber, strlen($prefix));
            if (ctype_digit($suffix)) {
                $nextNumber = ((int) $suffix) + 1;
            }
        }

        do {
            $voucherNumber = $prefix . str_pad(
                (string) $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );

            $exists = ReceiptVoucher::query()
                ->where('voucherNumber', $voucherNumber)
                ->exists();

            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $voucherNumber;
    }

    // =========================================================
    // LIST
    // =========================================================

    public static function getList(string $search = ''): Collection
    {
        $query = ReceiptVoucher::query()
            ->select([
                'receiptID', 'voucherNumber', 'voucherDate',
                'creditAccountID', 'debitAccountID', 'coinsID',
                'entryID', 'amount', 'exchangeRate', 'localAmount',
                'paymentMethod', 'chequeNumber', 'notes',
                'is_posted', 'posted_at', 'posted_by',
                'created_by', 'updated_by',
            ])
            ->with([
                'creditAccount:accountID,accCode,accName',
                'debitAccount:accountID,accCode,accName',
                'currency:coinsID,coinsCode,coinsName',
            ])
            ->orderByDesc('receiptID');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('voucherNumber', 'like', "{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('creditAccount', function ($a) use ($search) {
                      $a->where('accName', 'like', "%{$search}%")
                        ->orWhere('accCode', 'like', "%{$search}%");
                  })
                  ->orWhereHas('debitAccount', function ($a) use ($search) {
                      $a->where('accName', 'like', "%{$search}%")
                        ->orWhere('accCode', 'like', "%{$search}%");
                  });
            });
        }

        return $query->limit(100)->get();
    }

    // =========================================================
    // FIND
    // =========================================================

    public static function findForShow(int $id): ?ReceiptVoucher
    {
        return ReceiptVoucher::query()
            ->select([
                'receiptID', 'voucherNumber', 'voucherDate',
                'creditAccountID', 'debitAccountID', 'coinsID',
                'entryID', 'amount', 'exchangeRate', 'localAmount',
                'paymentMethod', 'chequeNumber', 'notes',
                'is_posted', 'posted_at', 'posted_by',
                'created_by', 'updated_by',
            ])
            ->with([
                'creditAccount:accountID,accCode,accName,nature',
                'debitAccount:accountID,accCode,accName',
                'currency:coinsID,coinsCode,coinsName',
                'entry:entryID,entryNo',
            ])
            ->find($id);
    }

    public static function findForPrint(int $id): ?ReceiptVoucher
    {
        return self::findForShow($id);
    }

    // =========================================================
    // CURRENCIES
    // =========================================================

    public static function getCurrencies(): Collection
    {
        return Cache::remember(
            'receipt_voucher_currencies',
            self::CACHE_TTL,
            fn () => Coin::query()
                ->where('is_active', 1)
                ->orderByDesc('coinsSystem')
                ->orderBy('coinsName')
                ->get([
                    'coinsID', 'coinsName', 'coinsCode',
                    'coinsExchangeRate', 'coinsSystem',
                ])
        );
    }

    public static function getSystemCurrencyCode(): string
    {
        return (string) (
            Cache::remember(
                'receipt_voucher_system_currency_code',
                self::CACHE_TTL,
                fn () => Coin::query()
                    ->where('coinsSystem', 1)
                    ->value('coinsCode')
            ) ?? ''
        );
    }

    // =========================================================
    // CREATE — حفظ + ترحيل تلقائي
    // =========================================================

    public static function create(array $data): ReceiptVoucher
    {
        $lock = Cache::lock('receipt_voucher_create', self::CREATE_LOCK_TTL);

        if (! $lock->get()) {
            throw new \RuntimeException(
                'لا يمكن إنشاء السند حالياً. حاول بعد قليل.'
            );
        }

        try {
            return DB::transaction(function () use ($data) {

                if (empty($data['voucherNumber'])) {
                    $data['voucherNumber'] = self::generateVoucherNumber();
                }

                $exists = ReceiptVoucher::query()
                    ->where('voucherNumber', $data['voucherNumber'])
                    ->exists();

                if ($exists) {
                    throw new \RuntimeException(
                        'رقم السند مستخدم مسبقاً. حدّث الصفحة للحصول على رقم جديد.'
                    );
                }

                $data['voucherDate']  = $data['voucherDate'] ?? now()->toDateString();
                $data['amount']       = (float) ($data['amount'] ?? 0);
                $data['exchangeRate'] = (float) ($data['exchangeRate'] ?? 1);
                $data['localAmount']  = self::calculateLocalAmount(
                    $data['amount'],
                    $data['exchangeRate']
                );

                $userId = auth()->id();
                $data['created_by'] = $userId;
                $data['updated_by'] = $userId;
                $data['is_posted']  = false;
                $data['posted_at']  = null;
                $data['posted_by']  = null;

                $voucher = ReceiptVoucher::create($data);

                $voucher->load('creditAccount:accountID,accName');

                /* ★ الترحيل التلقائي بعد الإنشاء */
                $entryID = self::createJournalEntry($voucher);

                if (! $entryID) {
                    throw new \RuntimeException(
                        'فشل إنشاء القيد المحاسبي للسند. تحقق من صحة البيانات.'
                    );
                }

                $voucher->update([
                    'entryID'   => $entryID,
                    'is_posted' => true,
                    'posted_at' => now(),
                    'posted_by' => $userId,
                ]);

                return $voucher->fresh([
                    'creditAccount:accountID,accCode,accName',
                    'debitAccount:accountID,accCode,accName',
                    'currency:coinsID,coinsCode,coinsName',
                ]);
            });

        } catch (\RuntimeException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Receipt voucher creation failed.', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            throw new \RuntimeException('فشل حفظ السند. يرجى المحاولة مرة أخرى.');

        } finally {
            $lock->release();
        }
    }

    // =========================================================
    // UPDATE — يبقى مسودة (لا يُعيد الترحيل)
    // =========================================================

    public static function update(int $id, array $data): ReceiptVoucher
    {
        if ($id <= 0) {
            throw new \RuntimeException('معرّف السند غير صالح.');
        }

        $lock = Cache::lock('receipt_voucher_update_' . $id, self::UPDATE_LOCK_TTL);

        if (! $lock->get()) {
            throw new \RuntimeException('لا يمكن تعديل السند حالياً.');
        }

        try {
            return DB::transaction(function () use ($id, $data) {

                $voucher = ReceiptVoucher::query()
                    ->where('receiptID', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $voucher) {
                    throw new \RuntimeException('السند غير موجود.');
                }

                if ($voucher->is_posted) {
                    throw new \RuntimeException(
                        'لا يمكن تعديل سند مرحّل. ألغِ الترحيل أولاً.'
                    );
                }

                unset($data['voucherNumber']);

                $data['amount'] = (float) ($data['amount'] ?? $voucher->amount);
                $data['exchangeRate'] = (float) (
                    $data['exchangeRate'] ?? $voucher->exchangeRate ?? 1
                );
                $data['localAmount'] = self::calculateLocalAmount(
                    $data['amount'],
                    $data['exchangeRate']
                );
                $data['updated_by'] = auth()->id();

                $voucher->update($data);

                /* ★ يبقى مسودة — لا نلمس is_posted ولا نُنشئ قيداً */
                return $voucher->fresh([
                    'creditAccount:accountID,accCode,accName',
                    'debitAccount:accountID,accCode,accName',
                    'currency:coinsID,coinsCode,coinsName',
                ]);
            });

        } catch (\RuntimeException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Receipt voucher update failed.', [
                'receiptID' => $id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            throw new \RuntimeException('فشل تعديل السند. يرجى المحاولة مرة أخرى.');

        } finally {
            $lock->release();
        }
    }

    // =========================================================
    // POST — ترحيل يدوي (بعد تعديل سند مسودة)
    // =========================================================

    public static function post(int $id): ReceiptVoucher
    {
        if ($id <= 0) {
            throw new \RuntimeException('معرّف السند غير صالح.');
        }

        $lock = Cache::lock('receipt_voucher_post_' . $id, self::POST_LOCK_TTL);

        if (! $lock->get()) {
            throw new \RuntimeException('لا يمكن ترحيل السند حالياً.');
        }

        try {
            return DB::transaction(function () use ($id) {

                $voucher = ReceiptVoucher::query()
                    ->where('receiptID', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $voucher) {
                    throw new \RuntimeException('السند غير موجود.');
                }

                if ($voucher->is_posted) {
                    throw new \RuntimeException('السند مُرحّل بالفعل.');
                }

                $voucher->load('creditAccount:accountID,accName');

                $entryID = self::createJournalEntry($voucher);

                if (! $entryID) {
                    throw new \RuntimeException(
                        'فشل إنشاء القيد المحاسبي. تحقق من صحة البيانات.'
                    );
                }

                $voucher->update([
                    'entryID'   => $entryID,
                    'is_posted' => true,
                    'posted_at' => now(),
                    'posted_by' => auth()->id(),
                ]);

                return $voucher->fresh([
                    'creditAccount:accountID,accCode,accName',
                    'debitAccount:accountID,accCode,accName',
                    'currency:coinsID,coinsCode,coinsName',
                ]);
            });

        } catch (\RuntimeException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Receipt voucher post failed.', [
                'receiptID' => $id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            throw new \RuntimeException('فشل ترحيل السند. يرجى المحاولة مرة أخرى.');

        } finally {
            $lock->release();
        }
    }

    // =========================================================
    // UNPOST — إلغاء الترحيل (يحذف القيد، يسمح بالتعديل)
    // =========================================================

   public static function unpost(int $id): ReceiptVoucher
{
    if ($id <= 0) {
        throw new \RuntimeException('معرّف السند غير صالح.');
    }

    $lock = Cache::lock('receipt_voucher_unpost_' . $id, self::POST_LOCK_TTL);

    if (! $lock->get()) {
        throw new \RuntimeException('لا يمكن إلغاء الترحيل حالياً.');
    }

    try {
        return DB::transaction(function () use ($id) {

            $voucher = ReceiptVoucher::query()
                ->where('receiptID', $id)
                ->lockForUpdate()
                ->first();

            if (! $voucher) {
                throw new \RuntimeException('السند غير موجود.');
            }

            if (! $voucher->is_posted) {
                throw new \RuntimeException('السند غير مُرحّل.');
            }

            /* 1) احفظ entryID مؤقتاً */
            $entryID = $voucher->entryID;

            /*
             * 2) صفّر entryID + is_posted على السند أولاً.
             *
             * السبب: receipt_vouchers.entryID مرتبط بـ Journal_Entries
             * بـ restrictOnDelete. لذلك لا يمكن حذف القيد بينما السند
             * لا يزال يشير إليه.
             */
            $voucher->update([
                'entryID'    => null,
                'is_posted'  => false,
                'posted_at'  => null,
                'posted_by'  => null,
                'updated_by' => auth()->id(),
            ]);

            /* 3) الآن يمكن حذف القيد بأمان */
            if (! empty($entryID)) {
                $deleted = JournalEntryService::delete((int) $entryID);
            } else {
                $deleted = JournalEntryService::deleteByDocNumber(
                    'RC-' . $voucher->receiptID
                );
            }

            if (! $deleted) {
                throw new \RuntimeException('فشل حذف القيد المحاسبي.');
            }

            return $voucher->fresh([
                'creditAccount:accountID,accCode,accName',
                'debitAccount:accountID,accCode,accName',
                'currency:coinsID,coinsCode,coinsName',
            ]);
        });

    } catch (\RuntimeException $e) {
        throw $e;

    } catch (\Throwable $e) {
        Log::error('Receipt voucher unpost failed.', [
            'receiptID' => $id,
            'error'     => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ]);

        throw new \RuntimeException('فشل إلغاء الترحيل. يرجى المحاولة مرة أخرى.');

    } finally {
        $lock->release();
    }
}
    // =========================================================
    // DELETE — يُسمح للمسودة فقط
    // =========================================================

    public static function delete(int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('معرّف السند غير صالح.');
        }

        $lock = Cache::lock('receipt_voucher_delete_' . $id, self::DELETE_LOCK_TTL);

        if (! $lock->get()) {
            throw new \RuntimeException('لا يمكن حذف السند حالياً.');
        }

        try {
            DB::transaction(function () use ($id) {

                $voucher = ReceiptVoucher::query()
                    ->where('receiptID', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $voucher) {
                    throw new \RuntimeException('السند غير موجود.');
                }

                if ($voucher->is_posted) {
                    throw new \RuntimeException(
                        'لا يمكن حذف سند مرحّل. ألغِ الترحيل أولاً.'
                    );
                }

                /* حذف القيد إن وُجد (احتياط) */
                if (! empty($voucher->entryID)) {
                    $deleted = JournalEntryService::delete((int) $voucher->entryID);
                    if (! $deleted) {
                        throw new \RuntimeException('فشل حذف القيد المحاسبي.');
                    }
                }

                $voucher->delete();
            });

        } catch (\RuntimeException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Receipt voucher deletion failed.', [
                'receiptID' => $id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            throw new \RuntimeException('فشل حذف السند. يرجى المحاولة مرة أخرى.');

        } finally {
            $lock->release();
        }
    }

    // =========================================================
    // PICKER (بدون تغيير — نسخة مختصرة)
    // =========================================================

    public static function getPickerData(
        string $type,
        string $search = '',
        $coinsID = null
    ): array {
        $parentKey = match ($type) {
            'customer' => 'customers',
            'cash'     => 'cash',
            'bank'     => 'banks',
            default    => 'customers',
        };

        $parentId = Cache::remember(
            'receipt_picker_parent_' . $parentKey,
            self::CACHE_TTL,
            fn () => CharAccount::query()
                ->where('system_key', $parentKey)
                ->value('accountID')
        );

        if (! $parentId) {
            return ['error' => true, 'message' => 'لم يتم العثور على الحساب الأب'];
        }

        $ids = self::getDescendantIds((int) $parentId);

        if (empty($ids)) {
            return ['error' => false, 'type' => $type, 'rows' => []];
        }

        $query = CharAccount::query()
            ->whereIn('accountID', $ids)
            ->where('IsActive', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('accCode', 'like', "{$search}%")
                  ->orWhere('accName', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('accCode')->limit(50)
            ->get(['accountID', 'accCode', 'accName', 'nature']);

        if ($accounts->isEmpty()) {
            return ['error' => false, 'type' => $type, 'rows' => []];
        }

        $accountIDs = $accounts->pluck('accountID')->map(fn ($i) => (int) $i)->all();
        $entities   = self::getPickerEntities($type, $accountIDs);
        $balances   = AccountBalanceService::getBalances($accountIDs);

        $rows = [];

        foreach ($accounts as $account) {
            $entity = $entities->get($account->accountID);
            if (! $entity) continue;

            $row = [
                'id'            => $account->accountID,
                'code'          => $account->accCode,
                'name'          => $account->accName,
                'extra'         => '—',
                'balance'       => 0,
                'currency_id'   => null,
                'currency_code' => null,
                'exchange_rate' => 1,
                'nature'        => (int) $account->nature,
            ];

            if ($type === 'customer') {
                $row['name']    = $entity->CustomersName2 ?? $account->accName;
                $row['extra']   = ! empty($entity->CusPhone) ? $entity->CusPhone : '—';
                $row['balance'] = (float) ($balances[$account->accountID] ?? 0);

            } elseif ($type === 'cash') {
                if (! empty($coinsID) && (int) $entity->coinsID !== (int) $coinsID) continue;
                $row['name']          = $entity->boxName ?? $account->accName;
                $row['extra']         = $entity->coin->coinsCode ?? '—';
                $row['currency_id']   = $entity->coinsID;
                $row['currency_code'] = $entity->coin->coinsCode ?? '';
                $row['exchange_rate'] = (float) ($entity->coin->coinsExchangeRate ?? 1);
                $row['balance']       = (float) ($balances[$account->accountID] ?? 0);

            } elseif ($type === 'bank') {
                if (! empty($coinsID) && (int) $entity->coinsID !== (int) $coinsID) continue;
                $row['name']          = $entity->bankName ?? $account->accName;
                $row['extra']         = $entity->coin->coinsCode ?? '—';
                $row['currency_id']   = $entity->coinsID;
                $row['currency_code'] = $entity->coin->coinsCode ?? '';
                $row['exchange_rate'] = (float) ($entity->coin->coinsExchangeRate ?? 1);
                $row['balance']       = (float) ($balances[$account->accountID] ?? 0);
            }

            $rows[] = $row;
        }

        return ['error' => false, 'type' => $type, 'rows' => $rows];
    }

    private static function getPickerEntities(string $type, array $accountIDs)
    {
        if ($type === 'customer') {
            return Customer::query()
                ->whereIn('accountID', $accountIDs)
                ->where('is_active', 1)
                ->get(['accountID', 'CustomersName2', 'CusPhone'])
                ->keyBy('accountID');
        }

        if ($type === 'cash') {
            return Box::query()
                ->whereIn('accountID', $accountIDs)
                ->where('is_active', 1)
                ->with(['coin:coinsID,coinsCode,coinsExchangeRate'])
                ->get(['accountID', 'coinsID', 'boxName'])
                ->keyBy('accountID');
        }

        if ($type === 'bank') {
            return Bank::query()
                ->whereIn('accountID', $accountIDs)
                ->where('is_active', 1)
                ->with(['coin:coinsID,coinsCode,coinsExchangeRate'])
                ->get(['accountID', 'coinsID', 'bankName', 'accountNumber'])
                ->keyBy('accountID');
        }

        return collect();
    }

    private static function getDescendantIds(int $parentId): array
    {
        if ($parentId <= 0) return [];

        return Cache::remember(
            'receipt_picker_descendants_' . $parentId,
            self::CACHE_TTL,
            function () use ($parentId) {
                $ids = [];
                $currentLevel = [$parentId];

                for ($level = 0; $level < self::DESCENDANT_MAX_LEVELS; $level++) {
                    $children = CharAccount::query()
                        ->whereIn('accParent', $currentLevel)
                        ->where('IsActive', 1)
                        ->pluck('accountID')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if (empty($children)) break;

                    $ids = array_merge($ids, $children);
                    $currentLevel = $children;
                }

                return array_values(array_unique($ids));
            }
        );
    }

    // =========================================================
    // JOURNAL
    // =========================================================

    private static function createJournalEntry(ReceiptVoucher $voucher): ?int
    {
        return JournalEntryService::create([
            'docType'     => 'سند قبض',
            'docNumber'   => 'RC-' . $voucher->receiptID,
            'entryDate'   => $voucher->voucherDate,
            'description' => self::buildJournalDescription($voucher),
            'totalAmount' => $voucher->localAmount,
            'lines'       => self::buildEntryLines($voucher),
        ]);
    }

    private static function buildEntryLines(ReceiptVoucher $voucher): array
    {
        return [
            [
                'accountID'    => (int) $voucher->debitAccountID,
                'coinsID'      => (int) $voucher->coinsID,
                'description2' => 'سند قبض رقم ' . $voucher->voucherNumber,
                'exchangRate'  => $voucher->exchangeRate,
                'debit'        => $voucher->amount,
                'credit'       => 0,
                'localDebit'   => $voucher->localAmount,
                'localCredit'  => 0,
            ],
            [
                'accountID'    => (int) $voucher->creditAccountID,
                'coinsID'      => (int) $voucher->coinsID,
                'description2' => 'سند قبض رقم ' . $voucher->voucherNumber,
                'exchangRate'  => $voucher->exchangeRate,
                'debit'        => 0,
                'credit'       => $voucher->amount,
                'localDebit'   => 0,
                'localCredit'  => $voucher->localAmount,
            ],
        ];
    }

    private static function buildJournalDescription(ReceiptVoucher $voucher): string
    {
        return 'سند قبض رقم ' . $voucher->voucherNumber
            . ' - '
            . ($voucher->creditAccount?->accName ?? '');
    }

    // =========================================================
    // AMOUNT
    // =========================================================

    private static function calculateLocalAmount(
        float $amount,
        float $exchangeRate
    ): float {
        return round($amount * $exchangeRate, 2);
    }
}