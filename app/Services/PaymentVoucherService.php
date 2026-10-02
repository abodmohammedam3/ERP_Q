<?php

namespace App\Services;

use App\Models\Accounting\Bank;
use App\Models\Accounting\Box;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\PaymentVoucher;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentVoucherService
{
    private const CREATE_LOCK_TTL = 10;
    private const UPDATE_LOCK_TTL = 10;
    private const DELETE_LOCK_TTL = 10;

    private const CACHE_TTL = 60;

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
        $today =
            now()->format('Ymd');

        $prefix =
            'PV-' . $today . '-';

        $lastVoucherNumber =
            PaymentVoucher::query()
                ->where(
                    'voucherNumber',
                    'like',
                    $prefix . '%'
                )
                ->orderByDesc('paymentID')
                ->value('voucherNumber');

        $nextNumber = 1;

        if ($lastVoucherNumber) {

            $suffix =
                substr(
                    $lastVoucherNumber,
                    strlen($prefix)
                );

            if (ctype_digit($suffix)) {
                $nextNumber =
                    ((int) $suffix) + 1;
            }
        }

        do {

            $voucherNumber =
                $prefix .
                str_pad(
                    (string) $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            $exists =
                PaymentVoucher::query()
                    ->where(
                        'voucherNumber',
                        $voucherNumber
                    )
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

    public static function getList(
        string $search = ''
    ): Collection {

        $query =
            PaymentVoucher::query()
                ->select([
                    'paymentID',
                    'voucherNumber',
                    'voucherDate',
                    'beneficiaryAccountID',
                    'paymentAccountID',
                    'coinsID',
                    'amount',
                    'localAmount',
                    'paymentMethod',
                    'notes',
                ])
                ->with([
                    'beneficiaryAccount:accountID,accCode,accName',
                    'paymentAccount:accountID,accCode,accName',
                    'currency:coinsID,coinsCode,coinsName',
                ])
                ->orderByDesc('paymentID');

        if ($search !== '') {

            $query->where(function ($q) use ($search) {

                $q->where(
                    'voucherNumber',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'notes',
                    'like',
                    "%{$search}%"
                )

                ->orWhereHas(
                    'beneficiaryAccount',
                    function ($account) use ($search) {

                        $account
                            ->where(
                                'accName',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'accCode',
                                'like',
                                "%{$search}%"
                            );
                    }
                )

                ->orWhereHas(
                    'paymentAccount',
                    function ($account) use ($search) {

                        $account
                            ->where(
                                'accName',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'accCode',
                                'like',
                                "%{$search}%"
                            );
                    }
                );
            });
        }

        return $query
            ->limit(50)
            ->get();
    }

    // =========================================================
    // FIND
    // =========================================================

    public static function findForShow(
        int $id
    ): ?PaymentVoucher {

        return PaymentVoucher::query()
            ->select([
                'paymentID',
                'voucherNumber',
                'voucherDate',
                'beneficiaryAccountID',
                'paymentAccountID',
                'coinsID',
                'entryID',
                'amount',
                'exchangeRate',
                'localAmount',
                'paymentMethod',
                'notes',
            ])
            ->with([
                'beneficiaryAccount:accountID,accCode,accName,nature',
                'paymentAccount:accountID,accCode,accName',
                'currency:coinsID,coinsCode,coinsName',
                'entry:entryID,entryNo',
            ])
            ->find($id);
    }

    public static function findForPrint(
        int $id
    ): ?PaymentVoucher {

        return PaymentVoucher::query()
            ->select([
                'paymentID',
                'voucherNumber',
                'voucherDate',
                'beneficiaryAccountID',
                'paymentAccountID',
                'coinsID',
                'entryID',
                'amount',
                'exchangeRate',
                'localAmount',
                'paymentMethod',
                'notes',
            ])
            ->with([
                'beneficiaryAccount:accountID,accCode,accName,nature',
                'paymentAccount:accountID,accCode,accName',
                'currency:coinsID,coinsCode,coinsName',
                'entry:entryID,entryNo',
            ])
            ->find($id);
    }

    // =========================================================
    // CURRENCIES
    // =========================================================

    public static function getCurrencies(): Collection
    {
        return Cache::remember(
            'payment_voucher_currencies',
            self::CACHE_TTL,
            function () {

                return Coin::query()
                    ->where(
                        'is_active',
                        1
                    )
                    ->orderByDesc(
                        'coinsSystem'
                    )
                    ->orderBy(
                        'coinsName'
                    )
                    ->get([
                        'coinsID',
                        'coinsName',
                        'coinsCode',
                        'coinsExchangeRate',
                        'coinsSystem',
                    ]);
            }
        );
    }

    public static function getSystemCurrencyCode(): string
    {
        return (string) (
            Cache::remember(
                'payment_voucher_system_currency_code',
                self::CACHE_TTL,
                fn () =>
                    Coin::query()
                        ->where(
                            'coinsSystem',
                            1
                        )
                        ->value('coinsCode')
            ) ?? ''
        );
    }

    // =========================================================
    // CREATE
    // =========================================================

    public static function create(
        array $data
    ): PaymentVoucher|string|null {

        $lock =
            Cache::lock(
                'payment_voucher_create',
                self::CREATE_LOCK_TTL
            );

        if (!$lock->get()) {
            return null;
        }

        try {

            return DB::transaction(
                function () use ($data) {

                    if (
                        empty(
                            $data['voucherNumber']
                        )
                    ) {
                        $data['voucherNumber'] =
                            self::generateVoucherNumber();
                    }

                    $exists =
                        PaymentVoucher::query()
                            ->where(
                                'voucherNumber',
                                $data['voucherNumber']
                            )
                            ->exists();

                    if ($exists) {
                        return null;
                    }

                    $data['voucherDate'] =
                        $data['voucherDate']
                        ?? now()->toDateString();

                    $data['amount'] =
                        (float) (
                            $data['amount'] ?? 0
                        );

                    $data['exchangeRate'] =
                        (float) (
                            $data['exchangeRate']
                            ?? 1
                        );

                    $data['localAmount'] =
                        self::calculateLocalAmount(
                            $data['amount'],
                            $data['exchangeRate']
                        );

                    $balanceError =
                        self::validateBalance(
                            $data
                        );

                    if ($balanceError !== null) {
                        return $balanceError;
                    }

                    $voucher =
                        PaymentVoucher::create(
                            $data
                        );

                    $voucher->load(
                        'beneficiaryAccount:accountID,accName'
                    );

                    $entryID =
                        self::createJournalEntry(
                            $voucher
                        );

                    if (!$entryID) {
                        throw new \RuntimeException(
                            'Failed to create payment voucher journal entry.'
                        );
                    }

                    $voucher->update([
                        'entryID' =>
                            $entryID,
                    ]);

                    $voucher->entryID =
                        $entryID;

                    return $voucher;
                }
            );

        } catch (\Throwable $e) {

            Log::error(
                'Payment voucher creation failed.',
                [
                    'error' =>
                        $e->getMessage(),
                ]
            );

            return null;

        } finally {

            $lock->release();
        }
    }

    // =========================================================
    // UPDATE
    // =========================================================

    public static function update(
        int $id,
        array $data
    ): PaymentVoucher|string|null {

        if ($id <= 0) {
            return null;
        }

        $lock =
            Cache::lock(
                'payment_voucher_update_' . $id,
                self::UPDATE_LOCK_TTL
            );

        if (!$lock->get()) {
            return null;
        }

        try {

            return DB::transaction(
                function () use ($id, $data) {

                    $voucher =
                        PaymentVoucher::query()
                            ->where(
                                'paymentID',
                                $id
                            )
                            ->lockForUpdate()
                            ->first();

                    if (!$voucher) {
                        return null;
                    }

                    unset(
                        $data['voucherNumber']
                    );

                    $data['amount'] =
                        (float) (
                            $data['amount']
                            ?? $voucher->amount
                        );

                    $data['exchangeRate'] =
                        (float) (
                            $data['exchangeRate']
                            ?? $voucher->exchangeRate
                            ?? 1
                        );

                    $data['localAmount'] =
                        self::calculateLocalAmount(
                            $data['amount'],
                            $data['exchangeRate']
                        );

                    $balanceError =
                        self::validateBalance(
                            $data,
                            $voucher
                        );

                    if ($balanceError !== null) {
                        return $balanceError;
                    }

                    $voucher->update(
                        $data
                    );

                    if (
                        array_key_exists(
                            'beneficiaryAccountID',
                            $data
                        )
                    ) {
                        $voucher->unsetRelation(
                            'beneficiaryAccount'
                        );
                    }

                    $voucher->load(
                        'beneficiaryAccount:accountID,accName'
                    );

                    if (
                        !empty(
                            $voucher->entryID
                        )
                    ) {

                        $success =
                            JournalEntryService::updateEntry(
                                (int) $voucher->entryID,
                                [
                                    'entryDate' =>
                                        $voucher->voucherDate,

                                    'description' =>
                                        self::buildJournalDescription(
                                            $voucher
                                        ),

                                    'lines' =>
                                        self::buildEntryLines(
                                            $voucher
                                        ),
                                ]
                            );

                        if (!$success) {
                            throw new \RuntimeException(
                                'Failed to update payment voucher journal entry.'
                            );
                        }

                    } else {

                        $entryID =
                            self::createJournalEntry(
                                $voucher
                            );

                        if (!$entryID) {
                            throw new \RuntimeException(
                                'Failed to create payment voucher journal entry.'
                            );
                        }

                        $voucher->update([
                            'entryID' =>
                                $entryID,
                        ]);

                        $voucher->entryID =
                            $entryID;
                    }

                    return $voucher;
                }
            );

        } catch (\Throwable $e) {

            Log::error(
                'Payment voucher update failed.',
                [
                    'paymentID' =>
                        $id,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return null;

        } finally {

            $lock->release();
        }
    }

    // =========================================================
    // DELETE
    // =========================================================

    public static function delete(
        int $id
    ): bool {

        if ($id <= 0) {
            return false;
        }

        $lock =
            Cache::lock(
                'payment_voucher_delete_' . $id,
                self::DELETE_LOCK_TTL
            );

        if (!$lock->get()) {
            return false;
        }

        try {

            return DB::transaction(
                function () use ($id) {

                    $voucher =
                        PaymentVoucher::query()
                            ->where(
                                'paymentID',
                                $id
                            )
                            ->lockForUpdate()
                            ->first();

                    if (!$voucher) {
                        return false;
                    }

                    if (
                        !empty(
                            $voucher->entryID
                        )
                    ) {

                        $deleted =
                            JournalEntryService::delete(
                                (int) $voucher->entryID
                            );

                    } else {

                        $deleted =
                            JournalEntryService::deleteByDocNumber(
                                'PV-' .
                                $voucher->paymentID
                            );
                    }

                    if (!$deleted) {
                        throw new \RuntimeException(
                            'Failed to delete payment voucher journal entry.'
                        );
                    }

                    $voucher->delete();

                    return true;
                }
            );

        } catch (\Throwable $e) {

            Log::error(
                'Payment voucher deletion failed.',
                [
                    'paymentID' =>
                        $id,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return false;

        } finally {

            $lock->release();
        }
    }

    // =========================================================
    // BALANCE VALIDATION
    // =========================================================

    private static function validateBalance(
        array $data,
        ?PaymentVoucher $oldVoucher = null
    ): ?string {

        $paymentMethod =
            $data['paymentMethod'] ?? null;

        if (
            !in_array(
                $paymentMethod,
                ['cash', 'bank'],
                true
            )
        ) {
            return null;
        }

        $paymentAccountID =
            (int) (
                $data['paymentAccountID']
                ?? 0
            );

        if ($paymentAccountID <= 0) {
            return 'حساب الدفع غير صحيح.';
        }

        $amount =
            (float) (
                $data['amount'] ?? 0
            );

        $exchangeRate =
            (float) (
                $data['exchangeRate'] ?? 1
            );

        $localAmount =
            self::calculateLocalAmount(
                $amount,
                $exchangeRate
            );

        $oldLocalAmount = null;
        $oldPaymentAccountID = null;

        if ($oldVoucher) {

            $oldLocalAmount =
                (float) (
                    $oldVoucher->localAmount
                    ?? 0
                );

            $oldPaymentAccountID =
                (int) (
                    $oldVoucher->paymentAccountID
                    ?? 0
                );
        }

        $availableBalance =
            AccountBalanceService::getAvailableBalanceForPayment(
                $paymentAccountID,
                $oldLocalAmount,
                $oldPaymentAccountID
            );

        if (
            $localAmount <=
            $availableBalance
        ) {
            return null;
        }

        $accountName =
            CharAccount::query()
                ->where(
                    'accountID',
                    $paymentAccountID
                )
                ->value('accName');

        return sprintf(
            'الرصيد غير كافٍ في حساب "%s". الرصيد المتاح: %s، والمبلغ المطلوب: %s.',
            $accountName ?? 'غير معروف',
            number_format(
                $availableBalance,
                2
            ),
            number_format(
                $localAmount,
                2
            )
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

        if ($type === 'other') {
            $type = 'expense';
        }

        if ($type === 'expense') {
            return self::getExpensePickerData(
                $search
            );
        }

        $parentKey = match ($type) {
            'supplier' => 'suppliers',
            'customer' => 'customers',
            'cash'     => 'cash',
            'bank'     => 'banks',
            default    => 'suppliers',
        };

        $parentId =
            Cache::remember(
                'payment_picker_parent_' . $parentKey,
                self::CACHE_TTL,
                fn () =>
                    CharAccount::query()
                        ->where(
                            'system_key',
                            $parentKey
                        )
                        ->value('accountID')
            );

        if (!$parentId) {
            return [
                'error' => true,
                'message' =>
                    'لم يتم العثور على الحساب الأب',
            ];
        }

        $ids =
            self::getDescendantIds(
                (int) $parentId
            );

        if (empty($ids)) {
            return [
                'error' => false,
                'type'  => $type,
                'rows'  => [],
            ];
        }

        $query =
            CharAccount::query()
                ->whereIn(
                    'accountID',
                    $ids
                )
                ->where(
                    'IsActive',
                    1
                );

        if ($search !== '') {

            $query->where(
                function ($q) use ($search) {

                    $q->where(
                        'accCode',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'accName',
                        'like',
                        "%{$search}%"
                    );
                }
            );
        }

        $accounts =
            $query
                ->orderBy('accCode')
                ->limit(50)
                ->get([
                    'accountID',
                    'accCode',
                    'accName',
                    'nature',
                ]);

        if ($accounts->isEmpty()) {
            return [
                'error' => false,
                'type'  => $type,
                'rows'  => [],
            ];
        }

        $accountIDs =
            $accounts
                ->pluck('accountID')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();

        $entities =
            self::getPickerEntities(
                $type,
                $accountIDs
            );

        // =====================================================
        // GET BALANCES
        // المورد + العميل + الصندوق + البنك
        // =====================================================

        $balances = [];

        if (
            in_array(
                $type,
                ['supplier', 'customer', 'cash', 'bank'],
                true
            )
        ) {
            $balances =
                AccountBalanceService::getBalances(
                    $accountIDs
                );
        }

        $rows = [];

        foreach ($accounts as $account) {

            $entity =
                $entities->get(
                    $account->accountID
                );

            if (!$entity) {
                continue;
            }

            $row = [
                'id' =>
                    $account->accountID,

                'code' =>
                    $account->accCode,

                'name' =>
                    $account->accName,

                'extra' =>
                    '—',

                'balance' =>
                    null,

                'currency_id' =>
                    null,

                'currency_code' =>
                    null,

                'exchange_rate' =>
                    1,

                'nature' =>
                    (int) $account->nature,
            ];

            if ($type === 'supplier') {

                $row['name'] =
                    $entity->supName
                    ?? $account->accName;

                $row['extra'] =
                    !empty($entity->supPhone)
                        ? $entity->supPhone
                        : '—';

                $row['balance'] =
                    (float) (
                        $balances[
                            $account->accountID
                        ] ?? 0
                    );
            }

            elseif ($type === 'customer') {

                $row['name'] =
                    $entity->CustomersName2
                    ?? $account->accName;

                $row['extra'] =
                    !empty($entity->CusPhone)
                        ? $entity->CusPhone
                        : '—';

                $row['balance'] =
                    (float) (
                        $balances[
                            $account->accountID
                        ] ?? 0
                    );
            }

            elseif ($type === 'cash') {

                if (
                    !empty($coinsID)
                    && (int) $entity->coinsID
                        !== (int) $coinsID
                ) {
                    continue;
                }

                $row['name'] =
                    $entity->boxName
                    ?? $account->accName;

                $row['extra'] =
                    $entity->coin->coinsCode
                    ?? '—';

                $row['currency_id'] =
                    $entity->coinsID;

                $row['currency_code'] =
                    $entity->coin->coinsCode
                    ?? '';

                $row['exchange_rate'] =
                    (float) (
                        $entity->coin->coinsExchangeRate
                        ?? 1
                    );

                // رصيد الصندوق
                $row['balance'] =
                    (float) (
                        $balances[
                            $account->accountID
                        ] ?? 0
                    );
            }

            elseif ($type === 'bank') {

                if (
                    !empty($coinsID)
                    && (int) $entity->coinsID
                        !== (int) $coinsID
                ) {
                    continue;
                }

                $row['name'] =
                    $entity->bankName
                    ?? $account->accName;

                $row['extra'] =
                    $entity->coin->coinsCode
                    ?? '—';

                $row['currency_id'] =
                    $entity->coinsID;

                $row['currency_code'] =
                    $entity->coin->coinsCode
                    ?? '';

                $row['exchange_rate'] =
                    (float) (
                        $entity->coin->coinsExchangeRate
                        ?? 1
                    );

                // رصيد البنك
                $row['balance'] =
                    (float) (
                        $balances[
                            $account->accountID
                        ] ?? 0
                    );
            }

            $rows[] = $row;
        }

        return [
            'error' => false,
            'type'  => $type,
            'rows'  => $rows,
        ];
    }

    private static function getExpensePickerData(
        string $search
    ): array {

        $parentId =
            Cache::remember(
                'payment_picker_parent_expenses',
                self::CACHE_TTL,
                fn () =>
                    CharAccount::query()
                        ->where(
                            'system_key',
                            'expenses'
                        )
                        ->value('accountID')
            );

        if (!$parentId) {
            return [
                'error' => true,
                'message' =>
                    'لم يتم العثور على حساب المصروفات',
            ];
        }

        $ids =
            self::getDescendantIds(
                (int) $parentId
            );

        if (empty($ids)) {
            return [
                'error' => false,
                'type'  => 'expense',
                'rows'  => [],
            ];
        }

        $query =
            CharAccount::query()
                ->whereIn(
                    'accountID',
                    $ids
                )
                ->where(
                    'IsActive',
                    1
                )
                ->where(
                    'isPostable',
                    1
                );

        if ($search !== '') {

            $query->where(
                function ($q) use ($search) {

                    $q->where(
                        'accCode',
                        'like',
                        "%{$search}%"
                    )

                    ->orWhere(
                        'accName',
                        'like',
                        "%{$search}%"
                    )

                    ->orWhereHas(
                        'parent',
                        function ($parentQuery) use ($search) {

                            $parentQuery
                                ->where(
                                    'accCode',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'accName',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            );
        }

        $accounts =
            $query
                ->orderBy('accCode')
                ->limit(50)
                ->get([
                    'accountID',
                    'accParent',
                    'accCode',
                    'accName',
                    'nature',
                ]);

        if ($accounts->isEmpty()) {
            return [
                'error' => false,
                'type'  => 'expense',
                'rows'  => [],
            ];
        }

        $parentIDs =
            $accounts
                ->pluck('accParent')
                ->filter()
                ->unique()
                ->values()
                ->all();

        $parents =
            CharAccount::query()
                ->whereIn(
                    'accountID',
                    $parentIDs
                )
                ->get([
                    'accountID',
                    'accCode',
                    'accName',
                ])
                ->keyBy('accountID');

        $accountIDs =
            $accounts
                ->pluck('accountID')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();

        $balances =
            AccountBalanceService::getBalances(
                $accountIDs
            );

        $rows =
            $accounts
                ->map(
                    function ($account) use (
                        $parents,
                        $balances
                    ) {

                        $parent =
                            $parents->get(
                                $account->accParent
                            );

                        return [
                            'id' =>
                                $account->accountID,

                            'code' =>
                                $account->accCode,

                            'name' =>
                                $account->accName,

                            'parent_name' =>
                                $parent->accName ?? '—',

                            'parent_code' =>
                                $parent->accCode ?? '—',

                            'extra' =>
                                '—',

                            'balance' =>
                                (float) (
                                    $balances[
                                        $account->accountID
                                    ] ?? 0
                                ),

                            'nature' =>
                                (int) $account->nature,

                            'currency_id' =>
                                null,

                            'currency_code' =>
                                null,

                            'exchange_rate' =>
                                1,
                        ];
                    }
                )
                ->values()
                ->all();

        return [
            'error' => false,
            'type'  => 'expense',
            'rows'  => $rows,
        ];
    }

    private static function getPickerEntities(
        string $type,
        array $accountIDs
    ) {

        if ($type === 'supplier') {

            return Supplier::query()
                ->whereIn(
                    'accountID',
                    $accountIDs
                )
                ->where(
                    'is_active',
                    1
                )
                ->get([
                    'accountID',
                    'supName',
                    'supPhone',
                ])
                ->keyBy('accountID');
        }

        if ($type === 'customer') {

            return Customer::query()
                ->whereIn(
                    'accountID',
                    $accountIDs
                )
                ->where(
                    'is_active',
                    1
                )
                ->get([
                    'accountID',
                    'CustomersName2',
                    'CusPhone',
                ])
                ->keyBy('accountID');
        }

        if ($type === 'cash') {

            return Box::query()
                ->whereIn(
                    'accountID',
                    $accountIDs
                )
                ->where(
                    'is_active',
                    1
                )
                ->with([
                    'coin:coinsID,coinsCode,coinsExchangeRate',
                ])
                ->get([
                    'accountID',
                    'coinsID',
                    'boxName',
                ])
                ->keyBy('accountID');
        }

        if ($type === 'bank') {

            return Bank::query()
                ->whereIn(
                    'accountID',
                    $accountIDs
                )
                ->where(
                    'is_active',
                    1
                )
                ->with([
                    'coin:coinsID,coinsCode,coinsExchangeRate',
                ])
                ->get([
                    'accountID',
                    'coinsID',
                    'bankName',
                    'accountNumber',
                ])
                ->keyBy('accountID');
        }

        return collect();
    }

    private static function getDescendantIds(
        int $parentId
    ): array {

        if ($parentId <= 0) {
            return [];
        }

        return Cache::remember(
            'payment_picker_descendants_' . $parentId,
            self::CACHE_TTL,
            function () use ($parentId) {

                $ids = [];

                $currentLevel = [
                    $parentId,
                ];

                for (
                    $level = 0;
                    $level < self::DESCENDANT_MAX_LEVELS;
                    $level++
                ) {

                    $children =
                        CharAccount::query()
                            ->whereIn(
                                'accParent',
                                $currentLevel
                            )
                            ->where(
                                'IsActive',
                                1
                            )
                            ->pluck('accountID')
                            ->map(
                                fn ($id) =>
                                    (int) $id
                            )
                            ->all();

                    if (empty($children)) {
                        break;
                    }

                    $ids =
                        array_merge(
                            $ids,
                            $children
                        );

                    $currentLevel =
                        $children;
                }

                return array_values(
                    array_unique($ids)
                );
            }
        );
    }

    // =========================================================
    // JOURNAL
    // =========================================================

    private static function createJournalEntry(
        PaymentVoucher $voucher
    ): ?int {

        return JournalEntryService::create([
            'docType' =>
                'سند صرف',

            'docNumber' =>
                'PV-' .
                $voucher->paymentID,

            'entryDate' =>
                $voucher->voucherDate,

            'description' =>
                self::buildJournalDescription(
                    $voucher
                ),

            'lines' =>
                self::buildEntryLines(
                    $voucher
                ),
        ]);
    }

    private static function buildEntryLines(
        PaymentVoucher $voucher
    ): array {

        return [

            [
                'accountID' =>
                    (int) $voucher->beneficiaryAccountID,

                'coinsID' =>
                    (int) $voucher->coinsID,

                'description2' =>
                    'سند صرف رقم ' .
                    $voucher->voucherNumber,

                'exchangRate' =>
                    $voucher->exchangeRate,

                'debit' =>
                    $voucher->amount,

                'credit' =>
                    0,

                'localDebit' =>
                    $voucher->localAmount,

                'localCredit' =>
                    0,
            ],

            [
                'accountID' =>
                    (int) $voucher->paymentAccountID,

                'coinsID' =>
                    (int) $voucher->coinsID,

                'description2' =>
                    'سند صرف رقم ' .
                    $voucher->voucherNumber,

                'exchangRate' =>
                    $voucher->exchangeRate,

                'debit' =>
                    0,

                'credit' =>
                    $voucher->amount,

                'localDebit' =>
                    0,

                'localCredit' =>
                    $voucher->localAmount,
            ],
        ];
    }

    private static function buildJournalDescription(
        PaymentVoucher $voucher
    ): string {

        return
            'سند صرف رقم ' .
            $voucher->voucherNumber .
            ' - ' .
            (
                $voucher
                    ->beneficiaryAccount
                    ?->accName
                ?? ''
            );
    }

    private static function calculateLocalAmount(
        float $amount,
        float $exchangeRate
    ): float {

        return round(
            $amount * $exchangeRate,
            2
        );
    }
}

