<?php

namespace App\Services;

use App\Models\Accounting\Bank;
use App\Models\Accounting\Box;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\PaymentVoucher;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentVoucherService
{
    private const LOCK_TTL = 15;
    private const LOCK_WAIT = 10;
    private const CACHE_TTL = 3600;
    private const DESCENDANT_MAX_LEVELS = 10;

    private const PICKER_SYSTEM_KEYS = [
        'supplier' => 'suppliers',
        'customer' => 'customers',
        'cash'     => 'cash',
        'bank'     => 'banks',
        'expense'  => 'expenses',
    ];

    private const ENTITY_LESS_PICKERS = ['expense'];

    // =========================================================
    // EXECUTION WRAPPER
    // =========================================================

    private static function executeWithLockAndTransaction(
        string $lockKey,
        string $operationName,
        callable $callback
    ): mixed {
        $lock = Cache::lock($lockKey, self::LOCK_TTL);

        try {
            $result = $lock->block(self::LOCK_WAIT, function () use ($callback) {
                return DB::transaction($callback);
            });

            if ($result === false) {
                throw new \RuntimeException(
                    'لا يمكن تنفيذ العملية الآن — الخدمة مزدحمة. حاول بعد لحظات.'
                );
            }

            return $result;

        } catch (\RuntimeException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error("Payment voucher {$operationName} failed.", [
                'operation' => $operationName,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'user_id'   => auth()->id(),
            ]);

            throw new \RuntimeException(
                'فشل تنفيذ العملية. يرجى المحاولة مرة أخرى.',
                0,
                $e
            );

        } finally {
            optional($lock)->release();
        }
    }

    // =========================================================
    // NUMBER
    // =========================================================

    public static function generateNextVoucherNumber(): string
    {
        return self::generateVoucherNumber();
    }

    private static function generateVoucherNumber(): string
    {
        $lastVoucherNumber = PaymentVoucher::query()
            ->whereRaw("voucherNumber REGEXP '^[0-9]+$'")
            ->orderByRaw('CAST(voucherNumber AS UNSIGNED) DESC')
            ->value('voucherNumber');

        $nextNumber = 1;

        if ($lastVoucherNumber !== null && ctype_digit((string) $lastVoucherNumber)) {
            $nextNumber = ((int) $lastVoucherNumber) + 1;
        }

        while (
            PaymentVoucher::query()
                ->where('voucherNumber', (string) $nextNumber)
                ->exists()
        ) {
            $nextNumber++;
        }

        return (string) $nextNumber;
    }

    // =========================================================
    // LIST
    // =========================================================

    public static function getList(string $search = ''): Collection
    {
        return self::buildListQuery($search)->limit(100)->get();
    }

    public static function getListPaginated(
        string $search = '',
        int $perPage = 50
    ): CursorPaginator {
        $perPage = min(max($perPage, 10), 100);

        return self::buildListQuery($search)
            ->orderByDesc('paymentID')
            ->cursorPaginate($perPage);
    }

    private static function buildListQuery(string $search)
    {
        $query = PaymentVoucher::query()
            ->select([
                'paymentID', 'voucherNumber', 'voucherDate',
                'beneficiaryAccountID', 'paymentAccountID', 'coinsID',
                'entryID', 'amount', 'exchangeRate', 'localAmount',
                'paymentMethod', 'notes',
                'is_posted', 'posted_at', 'posted_by',
                'created_by', 'updated_by',
            ])
            ->with([
                'beneficiaryAccount:accountID,accCode,accName,nature',
                'paymentAccount:accountID,accCode,accName,nature',
                'currency:coinsID,coinsCode,coinsName',
                'entry:entryID,entryNo',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('voucherNumber', 'like', "{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('beneficiaryAccount', function ($a) use ($search) {
                      $a->where('accName', 'like', "%{$search}%")
                        ->orWhere('accCode', 'like', "%{$search}%");
                  })
                  ->orWhereHas('paymentAccount', function ($a) use ($search) {
                      $a->where('accName', 'like', "%{$search}%")
                        ->orWhere('accCode', 'like', "%{$search}%");
                  });
            });
        }

        return $query;
    }

    // =========================================================
    // FIND
    // =========================================================

    public static function findForShow(int $id): PaymentVoucher
    {
        return PaymentVoucher::query()
            ->select([
                'paymentID', 'voucherNumber', 'voucherDate',
                'beneficiaryAccountID', 'paymentAccountID', 'coinsID',
                'entryID', 'amount', 'exchangeRate', 'localAmount',
                'paymentMethod', 'notes',
                'is_posted', 'posted_at', 'posted_by',
                'created_by', 'updated_by',
            ])
            ->with([
                'beneficiaryAccount:accountID,accCode,accName,nature',
                'paymentAccount:accountID,accCode,accName,nature',
                'currency:coinsID,coinsCode,coinsName',
                'entry:entryID,entryNo',
            ])
            ->findOrFail($id);
    }

    public static function findForPrint(int $id): PaymentVoucher
    {
        return self::findForShow($id);
    }

    // =========================================================
    // CURRENCIES
    // =========================================================

    public static function getCurrencies(): Collection
    {
        return Cache::remember(
            'payment_voucher_currencies',
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
        $code = Cache::remember(
            'payment_voucher_system_currency_code',
            self::CACHE_TTL,
            fn () => Coin::query()
                ->where('coinsSystem', 1)
                ->value('coinsCode')
        );

        if ($code === null) {
            Cache::forget('payment_voucher_system_currency_code');
            return '';
        }

        return (string) $code;
    }

    // =========================================================
    // CREATE
    // =========================================================

    public static function create(array $data): PaymentVoucher
{
    $paymentAccountId = (int) ($data['paymentAccountID'] ?? 0);

    return self::executeWithLockAndTransaction(
        'payment_voucher_create_' . $paymentAccountId,
        'creation',
        function () use ($data) {

            /*
             *  النظام في وضع التهيئة — لا يوجد مستخدمون بعد
             * الأعمدة created_by / updated_by / posted_by كلها NULLABLE
             * عند تفعيل auth middleware → سيُملأ تلقائياً
             */
            $userId = auth()->id();

            if ($error = self::validateBalance($data, null)) {
                throw new \RuntimeException($error);
            }

            /* ★ التحقق من الحسابات التحليلية */
            self::assertPostableAccounts(
                (int) ($data['beneficiaryAccountID'] ?? 0),
                (int) ($data['paymentAccountID'] ?? 0)
            );

            if (empty($data['voucherNumber'])) {
                $data['voucherNumber'] = self::generateVoucherNumber();
            }

            if (PaymentVoucher::query()
                ->where('voucherNumber', $data['voucherNumber'])
                ->exists()
            ) {
                throw new \RuntimeException(
                    'رقم السند مستخدم مسبقاً. حدّث الصفحة للحصول على رقم جديد.'
                );
            }

            $data['voucherDate']   = $data['voucherDate'] ?? now()->toDateString();
            $data['amount']        = (float) ($data['amount'] ?? 0);
            $data['exchangeRate']  = (float) ($data['exchangeRate'] ?? 1);
            $data['localAmount']   = self::calculateLocalAmount(
                $data['amount'],
                $data['exchangeRate']
            );
            $data['paymentMethod'] = self::normalizePaymentMethod(
                $data['paymentMethod'] ?? null
            );

            /* ★ null مسموح */
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
            $data['is_posted']  = false;
            $data['posted_at']  = null;
            $data['posted_by']  = null;

            $voucher = PaymentVoucher::create($data);
            $voucher->load('beneficiaryAccount:accountID,accName');

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
                'posted_by' => $userId,        // ★ null مسموح
            ]);

            return $voucher->fresh([
                'beneficiaryAccount:accountID,accCode,accName,nature',
                'paymentAccount:accountID,accCode,accName,nature',
                'currency:coinsID,coinsCode,coinsName',
                'entry:entryID,entryNo',
            ]);
        }
    );
}

    // =========================================================
    // UPDATE
    // =========================================================

    public static function update(int $id, array $data): PaymentVoucher
    {
        if ($id <= 0) {
            throw new \RuntimeException('معرّف السند غير صالح.');
        }

        return self::executeWithLockAndTransaction(
            'payment_voucher_update_' . $id,
            'update',
            function () use ($id, $data) {

                $voucher = PaymentVoucher::query()
                    ->where('paymentID', $id)
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

                $merged = array_merge($voucher->toArray(), $data);
                $merged = array_filter($merged, fn ($v) => $v !== null);

                $merged['amount'] = (float) ($data['amount'] ?? $voucher->amount);
                $merged['exchangeRate'] = (float) (
                    $data['exchangeRate'] ?? $voucher->exchangeRate ?? 1
                );
                $merged['localAmount'] = self::calculateLocalAmount(
                    $merged['amount'],
                    $merged['exchangeRate']
                );

                if (isset($data['paymentMethod'])) {
                    $merged['paymentMethod'] = self::normalizePaymentMethod(
                        $data['paymentMethod']
                    );
                }

                if ($error = self::validateBalance($merged, null)) {
                    throw new \RuntimeException($error);
                }

                /* ★ التحقق من الحسابات التحليلية */
                self::assertPostableAccounts(
                    (int) ($merged['beneficiaryAccountID'] ?? 0),
                    (int) ($merged['paymentAccountID'] ?? 0)
                );

                $merged['updated_by'] = auth()->id();

                $allowed = [
                    'voucherDate',
                    'beneficiaryAccountID', 'paymentAccountID',
                    'coinsID',
                    'amount', 'exchangeRate', 'localAmount',
                    'paymentMethod', 'notes',
                    'updated_by',
                ];

                $voucher->update(
                    array_intersect_key($merged, array_flip($allowed))
                );

                return $voucher->fresh([
                    'beneficiaryAccount:accountID,accCode,accName,nature',
                    'paymentAccount:accountID,accCode,accName,nature',
                    'currency:coinsID,coinsCode,coinsName',
                    'entry:entryID,entryNo',
                ]);
            }
        );
    }

    // =========================================================
    // POST
    // =========================================================

    public static function post(int $id): PaymentVoucher
    {
        if ($id <= 0) {
            throw new \RuntimeException('معرّف السند غير صالح.');
        }

        return self::executeWithLockAndTransaction(
            'payment_voucher_post_' . $id,
            'post',
            function () use ($id) {

                $voucher = PaymentVoucher::query()
                    ->where('paymentID', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $voucher) {
                    throw new \RuntimeException('السند غير موجود.');
                }

                if ($voucher->is_posted) {
                    throw new \RuntimeException('السند مُرحّل بالفعل.');
                }

                if ($error = self::validateBalance($voucher->toArray(), null)) {
                    throw new \RuntimeException($error);
                }

                /* ★ التحقق من الحسابات التحليلية */
                self::assertPostableAccounts(
                    (int) $voucher->beneficiaryAccountID,
                    (int) $voucher->paymentAccountID
                );

                $voucher->load('beneficiaryAccount:accountID,accName');

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
                    'beneficiaryAccount:accountID,accCode,accName,nature',
                    'paymentAccount:accountID,accCode,accName,nature',
                    'currency:coinsID,coinsCode,coinsName',
                    'entry:entryID,entryNo',
                ]);
            }
        );
    }

    // =========================================================
    // UNPOST
    // =========================================================

    public static function unpost(int $id): PaymentVoucher
    {
        if ($id <= 0) {
            throw new \RuntimeException('معرّف السند غير صالح.');
        }

        return self::executeWithLockAndTransaction(
            'payment_voucher_unpost_' . $id,
            'unpost',
            function () use ($id) {

                $voucher = PaymentVoucher::query()
                    ->where('paymentID', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $voucher) {
                    throw new \RuntimeException('السند غير موجود.');
                }

                if (! $voucher->is_posted) {
                    throw new \RuntimeException('السند غير مُرحّل.');
                }

                $entryID = $voucher->entryID;

                $voucher->update([
                    'entryID'    => null,
                    'is_posted'  => false,
                    'posted_at'  => null,
                    'posted_by'  => null,
                    'updated_by' => auth()->id(),
                ]);

                $deleted = ! empty($entryID)
                    ? JournalEntryService::delete((int) $entryID)
                    : JournalEntryService::deleteByDocNumber('PV-' . $voucher->paymentID);

                if (! $deleted) {
                    throw new \RuntimeException('فشل حذف القيد المحاسبي.');
                }

                return $voucher->fresh([
                    'beneficiaryAccount:accountID,accCode,accName,nature',
                    'paymentAccount:accountID,accCode,accName,nature',
                    'currency:coinsID,coinsCode,coinsName',
                    'entry:entryID,entryNo',
                ]);
            }
        );
    }

    // =========================================================
    // DELETE
    // =========================================================

    public static function delete(int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('معرّف السند غير صالح.');
        }

        self::executeWithLockAndTransaction(
            'payment_voucher_delete_' . $id,
            'deletion',
            function () use ($id) {

                $voucher = PaymentVoucher::query()
                    ->where('paymentID', $id)
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

                if (! empty($voucher->entryID)) {
                    if (! JournalEntryService::delete((int) $voucher->entryID)) {
                        throw new \RuntimeException('فشل حذف القيد المحاسبي.');
                    }
                }

                $voucher->delete();
            }
        );
    }

    // =========================================================
    // PICKER
    // =========================================================

    public static function getPickerData(
        string $type,
        string $search = '',
        $coinsID = null
    ): array {
        $parentKey = self::PICKER_SYSTEM_KEYS[$type] ?? self::PICKER_SYSTEM_KEYS['supplier'];
        $cacheKey  = 'payment_picker_parent_' . $parentKey;

        $parentId = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => CharAccount::query()
                ->where('system_key', $parentKey)
                ->value('accountID')
        );

        if ($parentId === null) {
            Cache::forget($cacheKey);
            return [
                'error'   => true,
                'message' => "لم يتم العثور على الحساب الأب للنوع: {$type} (system_key = {$parentKey})",
            ];
        }

        $ids = self::getDescendantIds((int) $parentId);

        if (empty($ids)) {
            return ['error' => false, 'type' => $type, 'rows' => []];
        }

        /* ★ الحسابات التحليلية فقط */
        $query = CharAccount::query()
            ->whereIn('accountID', $ids)
            ->where('IsActive', 1)
            ->where('isPostable', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('accCode', 'like', "{$search}%")
                  ->orWhere('accName', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('accCode')->limit(50)
            ->get(['accountID', 'accCode', 'accName', 'nature', 'accParent']);

        if ($accounts->isEmpty()) {
            return ['error' => false, 'type' => $type, 'rows' => []];
        }

        /* ★ جلب الآباء دفعة واحدة للمصروفات */
        $parentIds = $accounts->pluck('accParent')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $parentsMap = [];

        if (! empty($parentIds)) {
            $parentsMap = CharAccount::query()
                ->whereIn('accountID', $parentIds)
                ->get(['accountID', 'accCode', 'accName'])
                ->keyBy('accountID');
        }

        $accountIDs = $accounts->pluck('accountID')->map(fn ($i) => (int) $i)->all();
        $entities   = self::getPickerEntities($type, $accountIDs);
        $balances   = AccountBalanceService::getBalances($accountIDs);

        $needsEntity = ! in_array($type, self::ENTITY_LESS_PICKERS, true);
        $rows = [];

        foreach ($accounts as $account) {
            $entity = $entities->get($account->accountID);

            if ($needsEntity && ! $entity) {
                if (in_array($type, ['cash', 'bank'], true)) {
                    continue;
                }
            }

            $row = [
                'id'            => $account->accountID,
                'code'          => $account->accCode,
                'name'          => $account->accName,
                'extra'         => '—',
                'balance'       => (float) ($balances[$account->accountID] ?? 0),
                'currency_id'   => null,
                'currency_code' => null,
                'exchange_rate' => 1,
                'nature'        => (int) $account->nature,
            ];

            if ($type === 'supplier') {
                /* ★ الأعمدة الفعلية: supName, supPhone */
                $row['name']  = $entity->supName ?? $account->accName;
                $row['extra'] = ! empty($entity?->supPhone) ? $entity->supPhone : '—';

            } elseif ($type === 'customer') {
                $row['name']  = $entity->CustomersName2 ?? $account->accName;
                $row['extra'] = ! empty($entity?->CusPhone) ? $entity->CusPhone : '—';

            } elseif ($type === 'cash') {
                if (! empty($coinsID) && (int) $entity->coinsID !== (int) $coinsID) continue;
                $row['name']          = $entity->boxName ?? $account->accName;
                $row['extra']         = $entity->coin->coinsCode ?? '—';
                $row['currency_id']   = $entity->coinsID;
                $row['currency_code'] = $entity->coin->coinsCode ?? '';
                $row['exchange_rate'] = (float) ($entity->coin->coinsExchangeRate ?? 1);

            } elseif ($type === 'bank') {
                if (! empty($coinsID) && (int) $entity->coinsID !== (int) $coinsID) continue;
                $row['name']          = $entity->bankName ?? $account->accName;
                $row['extra']         = $entity->coin->coinsCode ?? '—';
                $row['currency_id']   = $entity->coinsID;
                $row['currency_code'] = $entity->coin->coinsCode ?? '';
                $row['exchange_rate'] = (float) ($entity->coin->coinsExchangeRate ?? 1);

            } elseif ($type === 'expense') {
                $row['name']  = $account->accName;
                $row['extra'] = $account->accCode;

                $parentId   = (int) ($account->accParent ?? 0);
                $parentNode = $parentsMap[$parentId] ?? null;

                if ($parentNode) {
                    $row['parent_name'] = $parentNode->accName;
                    $row['parent_code'] = $parentNode->accCode;
                }
            }

            $rows[] = $row;
        }

        return ['error' => false, 'type' => $type, 'rows' => $rows];
    }

    private static function getPickerEntities(string $type, array $accountIDs)
    {
        if ($type === 'supplier') {
            $model = 'App\\Models\\Supplier';
            if (! class_exists($model)) {
                Log::warning('[PaymentVoucherService] Supplier model not found.');
                return collect();
            }

            /* ★ الأعمدة الفعلية في جدول Suppliers: supName, supPhone */
            return $model::query()
                ->whereIn('accountID', $accountIDs)
                ->get(['accountID', 'supName', 'supPhone'])
                ->keyBy('accountID');
        }

        if ($type === 'customer') {
            $model = 'App\\Models\\Customer';
            if (! class_exists($model)) {
                Log::warning('[PaymentVoucherService] Customer model not found.');
                return collect();
            }
            return $model::query()
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

    // =========================================================
    // DESCENDANTS
    // =========================================================

    private static function getDescendantIds(int $parentId): array
    {
        if ($parentId <= 0) return [];

        return Cache::remember(
            'payment_picker_descendants_' . $parentId,
            self::CACHE_TTL,
            fn () => self::fetchDescendantIds($parentId)
        );
    }

    private static function fetchDescendantIds(int $parentId): array
    {
        try {
            $rows = DB::select("
                WITH RECURSIVE tree AS (
                    SELECT accountID
                      FROM characcount
                     WHERE accParent = ?
                       AND IsActive  = 1

                    UNION ALL

                    SELECT c.accountID
                      FROM characcount c
                      JOIN tree t ON c.accParent = t.accountID
                     WHERE c.IsActive = 1
                )
                SELECT DISTINCT accountID FROM tree
            ", [$parentId]);

            return array_map(fn ($r) => (int) $r->accountID, $rows);

        } catch (\Throwable $e) {
            Log::warning('[PaymentVoucherService] Recursive CTE unavailable, falling back.', [
                'parentId' => $parentId,
                'error'    => $e->getMessage(),
            ]);

            return self::fetchDescendantIdsLegacy($parentId);
        }
    }

    private static function fetchDescendantIdsLegacy(int $parentId): array
    {
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

    // =========================================================
    // BALANCE VALIDATION
    // =========================================================

    public static function validateBalance(
        array $data,
        ?PaymentVoucher $oldVoucher = null
    ): ?string {
        $paymentAccountID = (int) ($data['paymentAccountID'] ?? 0);
        $amount           = (float) ($data['amount'] ?? 0);
        $exchangeRate     = (float) ($data['exchangeRate'] ?? 1);

        if ($paymentAccountID <= 0 || $amount <= 0) {
            return null;
        }

        $localAmount = self::calculateLocalAmount($amount, $exchangeRate);

        $available = AccountBalanceService::getAvailableBalanceForPayment(
            $paymentAccountID,
            $oldVoucher?->localAmount,
            $oldVoucher?->paymentAccountID
        );

        if ($localAmount > $available + 0.01) {
            return sprintf(
                'رصيد حساب الدفع غير كافٍ. المتاح: %s — المطلوب: %s',
                number_format($available, 2),
                number_format($localAmount, 2)
            );
        }

        return null;
    }

    // =========================================================
    // POSTABLE ACCOUNTS — التحقق من الحسابات التحليلية
    // =========================================================

    /**
     * يتحقق من أن الحسابين قابلان للترحيل.
     * استعلام واحد يجلب الحسابين معاً (بلا N+1).
     *
     *  الحماية المزدوجة (Defense in Depth):
     *   - FormRequest يفحصها في مسار HTTP
     *   - هذه الدالة تفحصها في Service (Jobs, Commands, Tests, ...)
     *
     * @throws \RuntimeException إذا كان أي حساب غير موجود أو غير قابل للترحيل
     */
    private static function assertPostableAccounts(
        int $beneficiaryID,
        int $paymentID
    ): void {
        $accounts = DB::table('characcount')
            ->whereIn('accountID', [$beneficiaryID, $paymentID])
            ->get(['accountID', 'isPostable', 'IsActive'])
            ->keyBy('accountID');

        if (! $accounts->has($beneficiaryID)) {
            throw new \RuntimeException('حساب المستفيد غير موجود.');
        }
        if (! $accounts->has($paymentID)) {
            throw new \RuntimeException('حساب الدفع غير موجود.');
        }

        $beneficiary = $accounts[$beneficiaryID];
        $payment     = $accounts[$paymentID];

        if ((int) $beneficiary->isPostable !== 1) {
            throw new \RuntimeException(
                'حساب المستفيد غير قابل للترحيل (حساب تجميعي). اختر حساباً تحليلياً.'
            );
        }
        if ((int) $payment->isPostable !== 1) {
            throw new \RuntimeException(
                'حساب الدفع غير قابل للترحيل (حساب تجميعي). اختر حساباً تحليلياً.'
            );
        }
        if ((int) $beneficiary->IsActive !== 1) {
            throw new \RuntimeException('حساب المستفيد غير مفعّل.');
        }
        if ((int) $payment->IsActive !== 1) {
            throw new \RuntimeException('حساب الدفع غير مفعّل.');
        }
    }

    // =========================================================
    // JOURNAL
    // =========================================================

    private static function createJournalEntry(PaymentVoucher $voucher): ?int
    {
        return JournalEntryService::create([
            'docType'     => 'سند صرف',
            'docNumber'   => 'PV-' . $voucher->paymentID,
            'entryDate'   => $voucher->voucherDate,
            'description' => self::buildJournalDescription($voucher),
            'totalAmount' => $voucher->localAmount,
            'lines'       => self::buildEntryLines($voucher),
        ]);
    }

    private static function buildEntryLines(PaymentVoucher $voucher): array
    {
        $amount       = (float) $voucher->amount;
        $localAmount  = (float) $voucher->localAmount;
        $exchangeRate = (float) $voucher->exchangeRate;
        $coinsID      = (int) $voucher->coinsID;
        $description  = 'سند صرف رقم ' . $voucher->voucherNumber;

        return [
            [
                'accountID'    => (int) $voucher->beneficiaryAccountID,
                'coinsID'      => $coinsID,
                'description2' => $description,
                'exchangRate'  => $exchangeRate,
                'debit'        => $amount,
                'credit'       => 0.0,
                'localDebit'   => $localAmount,
                'localCredit'  => 0.0,
            ],
            [
                'accountID'    => (int) $voucher->paymentAccountID,
                'coinsID'      => $coinsID,
                'description2' => $description,
                'exchangRate'  => $exchangeRate,
                'debit'        => 0.0,
                'credit'       => $amount,
                'localDebit'   => 0.0,
                'localCredit'  => $localAmount,
            ],
        ];
    }

    private static function buildJournalDescription(PaymentVoucher $voucher): string
    {
        return 'سند صرف رقم ' . $voucher->voucherNumber
            . ' - '
            . ($voucher->beneficiaryAccount?->accName ?? '');
    }

    // =========================================================
    // PRINT DATA
    // =========================================================

    public static function buildPrintData(int $id): array
{
    $voucher = self::findForPrint($id);

    $entryNo = $voucher->entry?->entryNo;

    /* رصيد المستفيد فقط — لا نحتاج رصيد حساب الدفع */
    $beneficiaryBalance = $voucher->beneficiaryAccountID
        ? AccountBalanceService::getBalanceAroundEntry(
            (int) $voucher->beneficiaryAccountID,
            $entryNo
        )
        : ['before' => 0.0, 'after' => 0.0, 'nature' => 0];

    return [
        'voucher'            => $voucher,
        'companyName'        => config('app.company_name')
                                ?? config('app.name', 'ERP'),
        'companySubtitle'    => 'نظام إدارة الموارد',
        'formattedDate'      => $voucher->voucherDate
                                ? $voucher->voucherDate->format('Y-m-d')
                                : '—',
        'paymentMethodText'  => $voucher->payment_method_label,
        'systemCurrencyCode' => self::getSystemCurrencyCode(),
        'currencyCode'       => $voucher->currency?->coinsCode ?? '—',
        'currencyName'       => $voucher->currency?->coinsName ?? '—',

        // رصيد المستفيد فقط
        'beneficiaryBalanceBefore' => (float) ($beneficiaryBalance['before'] ?? 0),
        'beneficiaryBalanceAfter'  => (float) ($beneficiaryBalance['after']  ?? 0),

        'amountWords'        => self::tafqeetAmount(
                                    (float) $voucher->amount,
                                    $voucher->currency?->coinsName ?? ''
                                ),
        'printTime'          => now()
                                    ->timezone(config('app.timezone'))
                                    ->format('Y-m-d H:i'),
    ];
}

/**
 * تفقيط المبلغ — يستخدم App\Helpers\Tafqeet::numberToWords
 * يُرجع صيغة احتياطية (رقم + عملة) عند الفشل.
 */
private static function tafqeetAmount(float $amount, string $currencyName): string
{
    if ($amount <= 0) {
        return '—';
    }

    if (class_exists(\App\Helpers\Tafqeet::class)
        && method_exists(\App\Helpers\Tafqeet::class, 'numberToWords')) {
        try {
            return \App\Helpers\Tafqeet::numberToWords($amount, $currencyName);
        } catch (\Throwable $e) {
            Log::warning('[PaymentVoucherService] Tafqeet failed.', [
                'amount' => $amount,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // Fallback
    return number_format($amount, 2) . ' ' . trim($currencyName);
}
    // =========================================================
    // HELPERS
    // =========================================================

    private static function calculateLocalAmount(
        float $amount,
        float $exchangeRate
    ): float {
        return round($amount * $exchangeRate, 2);
    }

    private static function normalizePaymentMethod($value): int
    {
        $method = (int) $value;
        return in_array($method, [PaymentVoucher::PAYMENT_CASH, PaymentVoucher::PAYMENT_BANK], true)
            ? $method
            : PaymentVoucher::PAYMENT_CASH;
    }
}