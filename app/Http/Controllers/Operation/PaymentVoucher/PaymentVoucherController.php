<?php

namespace App\Http\Controllers\Operation\PaymentVoucher;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Bank;
use App\Models\Accounting\Box;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\PaymentVoucher;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\AccountBalanceService;
use App\Services\PaymentVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class PaymentVoucherController extends Controller
{
    public function index()
    {
        session()->save();

        return view(
            'operation.accounting.paymentVouchers.index'
        );
    }

    // ══════════════════════════════════════════════════════════
    // NEXT NUMBER
    // ══════════════════════════════════════════════════════════

    public function nextNumber(): JsonResponse
    {
        session()->save();

        return $this->ok([
            'nextNumber' =>
                PaymentVoucherService::generateNextVoucherNumber(),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // LIST
    // ══════════════════════════════════════════════════════════

    public function list(Request $request): JsonResponse
    {
        session()->save();

        $search =
            trim($request->input('search', ''));

        $query = PaymentVoucher::with([
            'beneficiaryAccount',
            'paymentAccount',
            'currency',
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
                    fn ($c) => $c
                        ->where(
                            'accName',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'accCode',
                            'like',
                            "%{$search}%"
                        )
                )
                ->orWhereHas(
                    'paymentAccount',
                    fn ($c) => $c
                        ->where(
                            'accName',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'accCode',
                            'like',
                            "%{$search}%"
                        )
                );
            });
        }

        $vouchers =
            $query->limit(50)->get();

        $rows = $vouchers->map(
            fn ($v) => [
                'id' =>
                    $v->paymentID,

                'voucherNumber' =>
                    $v->voucherNumber,

                'voucherDate' =>
                    $v->voucherDate?->format('Y-m-d'),

                'beneficiaryAccountID' =>
                    $v->beneficiaryAccountID,

                'beneficiaryAccountCode' =>
                    $v->beneficiaryAccount->accCode ?? '',

                'beneficiaryAccountName' =>
                    $v->beneficiaryAccount->accName ?? '',

                'paymentAccountID' =>
                    $v->paymentAccountID,

                'paymentAccountCode' =>
                    $v->paymentAccount->accCode ?? '',

                'paymentAccountName' =>
                    $v->paymentAccount->accName ?? '',

                'amount' =>
                    (float) $v->amount,

                'currencyID' =>
                    $v->coinsID,

                'currencyCode' =>
                    $v->currency->coinsCode ?? '',

                'localAmount' =>
                    (float) $v->localAmount,

                'paymentMethod' =>
                    $v->paymentMethod,

                'notes' =>
                    $v->notes,
            ]
        );

        return $this->ok([
            'rows' => $rows,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════

    public function show(int $id): JsonResponse
    {
        session()->save();

        $voucher = PaymentVoucher::with([
            'beneficiaryAccount',
            'paymentAccount',
            'currency',
            'entry',
        ])->findOrFail($id);

        $beneficiaryBalance =
            $this->getBalanceAroundEntry(
                (int) $voucher->beneficiaryAccountID,
                $voucher->entry?->entryNo
            );

        $paymentBalance =
            AccountBalanceService::getBalance(
                (int) $voucher->paymentAccountID
            );

        return $this->ok([
            'voucher' => [
                'id' =>
                    $voucher->paymentID,

                'voucherNumber' =>
                    $voucher->voucherNumber,

                'voucherDate' =>
                    $voucher->voucherDate?->format('Y-m-d'),

                'beneficiaryAccountID' =>
                    $voucher->beneficiaryAccountID,

                'beneficiaryAccountCode' =>
                    $voucher->beneficiaryAccount->accCode ?? '',

                'beneficiaryAccountName' =>
                    $voucher->beneficiaryAccount->accName ?? '',

                'beneficiaryNature' =>
                    (int) (
                        $voucher
                            ->beneficiaryAccount
                            ->nature ?? 0
                    ),

                'beneficiaryBalanceBefore' =>
                    $beneficiaryBalance['before'],

                'beneficiaryBalanceAfter' =>
                    $beneficiaryBalance['after'],

                'paymentAccountID' =>
                    $voucher->paymentAccountID,

                'paymentAccountCode' =>
                    $voucher->paymentAccount->accCode ?? '',

                'paymentAccountName' =>
                    $voucher->paymentAccount->accName ?? '',

                'paymentBalance' =>
                    $paymentBalance,

                'amount' =>
                    (float) $voucher->amount,

                'currencyID' =>
                    $voucher->coinsID,

                'currencyCode' =>
                    $voucher->currency->coinsCode ?? '',

                'currencyName' =>
                    $voucher->currency->coinsName ?? '',

                'exchangeRate' =>
                    (float) $voucher->exchangeRate,

                'localAmount' =>
                    (float) $voucher->localAmount,

                'paymentMethod' =>
                    $voucher->paymentMethod,

                'notes' =>
                    $voucher->notes,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // STORE
    // ══════════════════════════════════════════════════════════

    public function store(Request $request): JsonResponse
    {
        session()->save();

        $lock =
            Cache::lock(
                'payment_store_lock',
                10
            );

        if (!$lock->get()) {
            return $this->fail(
                'يتم حفظ السند، يرجى الانتظار...'
            );
        }

        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'beneficiaryAccountID' =>
                        'required|exists:characcount,accountID',

                    'paymentAccountID' =>
                        'required|exists:characcount,accountID',

                    'coinsID' =>
                        'required|exists:coins,coinsID',

                    'amount' =>
                        'required|numeric|min:0.01',

                    'exchangeRate' =>
                        'required|numeric|gt:0',

                    'paymentMethod' =>
                        'nullable|in:cash,bank',

                    'notes' =>
                        'nullable|string',

                    'voucherDate' =>
                        'nullable|date',

                    'voucherNumber' =>
                        'nullable|string|max:50',
                ]
            );

            if ($validator->fails()) {
                return $this->fail(
                    $validator->errors()->first()
                );
            }

            if (
                $request->beneficiaryAccountID
                == $request->paymentAccountID
            ) {
                return $this->fail(
                    'لا يمكن أن يكون الحساب المستفيد هو نفس حساب الدفع.'
                );
            }

            $voucherNumber =
                $request->input('voucherNumber');

            if (!empty($voucherNumber)) {
                $exists =
                    PaymentVoucher::where(
                        'voucherNumber',
                        $voucherNumber
                    )->exists();

                if ($exists) {
                    return $this->fail(
                        'رقم السند مستخدم بالفعل. يرجى تحديث الصفحة للحصول على رقم جديد.'
                    );
                }
            }

            $data = $request->only([
                'beneficiaryAccountID',
                'paymentAccountID',
                'coinsID',
                'amount',
                'exchangeRate',
                'paymentMethod',
                'notes',
            ]);

            $data['voucherNumber'] =
                $voucherNumber ?: null;

            $data['voucherDate'] =
                $request->input(
                    'voucherDate',
                    now()->toDateString()
                );

            $voucher =
                PaymentVoucherService::create($data);

            if (is_string($voucher)) {
                return $this->fail($voucher);
            }

            if (!$voucher) {
                return $this->fail(
                    'فشل حفظ السند. يرجى المحاولة مرة أخرى.'
                );
            }

            return $this->ok([
                'message' =>
                    'تم حفظ السند بنجاح',

                'voucherID' =>
                    $voucher->paymentID,

                'voucherNumber' =>
                    $voucher->voucherNumber,
            ]);

        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        session()->save();

        $lock =
            Cache::lock(
                'payment_update_lock_' . $id,
                10
            );

        if (!$lock->get()) {
            return $this->fail(
                'يتم تعديل السند، يرجى الانتظار...'
            );
        }

        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'beneficiaryAccountID' =>
                        'required|exists:characcount,accountID',

                    'paymentAccountID' =>
                        'required|exists:characcount,accountID',

                    'coinsID' =>
                        'required|exists:coins,coinsID',

                    'amount' =>
                        'required|numeric|min:0.01',

                    'exchangeRate' =>
                        'required|numeric|gt:0',

                    'paymentMethod' =>
                        'nullable|in:cash,bank',

                    'notes' =>
                        'nullable|string',

                    'voucherDate' =>
                        'nullable|date',
                ]
            );

            if ($validator->fails()) {
                return $this->fail(
                    $validator->errors()->first()
                );
            }

            if (
                $request->beneficiaryAccountID
                == $request->paymentAccountID
            ) {
                return $this->fail(
                    'لا يمكن أن يكون الحساب المستفيد هو نفس حساب الدفع.'
                );
            }

            $data = $request->only([
                'beneficiaryAccountID',
                'paymentAccountID',
                'coinsID',
                'amount',
                'exchangeRate',
                'paymentMethod',
                'notes',
            ]);

            $data['voucherDate'] =
                $request->input(
                    'voucherDate',
                    now()->toDateString()
                );

            $voucher =
                PaymentVoucherService::update(
                    $id,
                    $data
                );

            if (is_string($voucher)) {
                return $this->fail($voucher);
            }

            if (!$voucher) {
                return $this->fail(
                    'فشل تعديل السند.'
                );
            }

            return $this->ok([
                'message' =>
                    'تم تعديل السند بنجاح',

                'voucherNumber' =>
                    $voucher->voucherNumber,
            ]);

        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════════

    public function destroy(int $id): JsonResponse
    {
        session()->save();

        $deleted =
            PaymentVoucherService::delete($id);

        if (!$deleted) {
            return $this->fail(
                'فشل حذف السند.',
                404
            );
        }

        return $this->ok([
            'message' =>
                'تم حذف السند بنجاح',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // CURRENCIES
    // ══════════════════════════════════════════════════════════

    public function currencies(): JsonResponse
    {
        session()->save();

        $currencies =
            Coin::where('is_active', 1)
                ->orderByDesc('coinsSystem')
                ->orderBy('coinsName')
                ->get([
                    'coinsID',
                    'coinsName',
                    'coinsCode',
                    'coinsExchangeRate',
                    'coinsSystem',
                ]);

        return $this->ok([
            'rows' =>
                $currencies->map(
                    fn ($c) => [
                        'id' =>
                            $c->coinsID,

                        'name' =>
                            $c->coinsName,

                        'code' =>
                            $c->coinsCode,

                        'exchangeRate' =>
                            (float) $c->coinsExchangeRate,
                    ]
                ),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // ACCOUNT PICKER
    // ══════════════════════════════════════════════════════════

    public function picker(
        Request $request
    ): JsonResponse {
        session()->save();

        $type =
            $request->input(
                'type',
                'supplier'
            );

        $search =
            trim(
                $request->input(
                    'search',
                    ''
                )
            );

        $coinsID =
            $request->input('coinsID');

        /*
         * دعم مؤقت لأي نسخة قديمة من JavaScript
         * كانت تستخدم other.
         */
        if ($type === 'other') {
            $type = 'expense';
        }

        if ($type === 'expense') {
            return $this->pickerExpenses($search);
        }

        $parentKey = match ($type) {
            'supplier' => 'suppliers',
            'customer' => 'customers',
            'cash'     => 'cash',
            'bank'     => 'banks',

            default    => 'suppliers',
        };

        $parentId =
            CharAccount::whereRaw(
                'LOWER(system_key) = ?',
                [strtolower($parentKey)]
            )->value('accountID');

        if (!$parentId) {
            return response()->json([
                'success' => false,
                'message' =>
                    'لم يتم العثور على الحساب الأب',
            ]);
        }

        $ids =
            $this->getDescendantIds(
                $parentId
            );

        if (!empty($ids)) {
            $ids =
                CharAccount::whereIn(
                    'accountID',
                    $ids
                )
                ->whereNotNull('accParent')
                ->pluck('accountID')
                ->all();
        }

        $query =
            CharAccount::whereIn(
                'accountID',
                $ids
            )
            ->where('IsActive', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
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
            });
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

        $accountIDs =
            $accounts
                ->pluck('accountID')
                ->map(fn ($id) => (int) $id)
                ->all();

        $entities = collect();

        if ($type === 'supplier') {
            $entities =
                Supplier::whereIn(
                    'accountID',
                    $accountIDs
                )
                ->where('is_active', 1)
                ->get()
                ->keyBy('accountID');
        }

        if ($type === 'customer') {
            $entities =
                Customer::whereIn(
                    'accountID',
                    $accountIDs
                )
                ->where('is_active', 1)
                ->get()
                ->keyBy('accountID');
        }

        if ($type === 'cash') {
            $entities =
                Box::whereIn(
                    'accountID',
                    $accountIDs
                )
                ->where('is_active', 1)
                ->with('coin')
                ->get()
                ->keyBy('accountID');
        }

        if ($type === 'bank') {
            $entities =
                Bank::whereIn(
                    'accountID',
                    $accountIDs
                )
                ->where('is_active', 1)
                ->with('coin')
                ->get()
                ->keyBy('accountID');
        }

        $balances =
            AccountBalanceService::getBalances(
                $accountIDs
            );

        $rows = [];

        foreach ($accounts as $acc) {
            $entity =
                $entities->get(
                    $acc->accountID
                );

            if (!$entity) {
                continue;
            }

            $row = [
                'id' =>
                    $acc->accountID,

                'code' =>
                    $acc->accCode,

                'name' =>
                    $acc->accName,

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
                    (int) $acc->nature,
            ];

            if ($type === 'supplier') {
                $row['name'] =
                    $entity->supName
                    ?? $acc->accName;

                $row['extra'] =
                    !empty($entity->supPhone)
                        ? $entity->supPhone
                        : '—';

                $row['balance'] =
                    (float) (
                        $balances[$acc->accountID]
                        ?? 0
                    );
            }

            elseif ($type === 'customer') {
                $row['name'] =
                    $entity->CustomersName2
                    ?? $acc->accName;

                $row['extra'] =
                    !empty($entity->CusPhone)
                        ? $entity->CusPhone
                        : '—';

                $row['balance'] =
                    (float) (
                        $balances[$acc->accountID]
                        ?? 0
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
                    ?? $acc->accName;

                $row['extra'] =
                    $entity->coin->coinsCode
                    ?? '—';

                $row['balance'] =
                    (float) (
                        $balances[$acc->accountID]
                        ?? 0
                    );

                $row['currency_id'] =
                    $entity->coinsID;

                $row['currency_code'] =
                    $entity->coin->coinsCode
                    ?? '';

                $row['exchange_rate'] =
                    (float) (
                        $entity
                            ->coin
                            ->coinsExchangeRate
                        ?? 1
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
                    ?? $acc->accName;

                $row['extra'] =
                    $entity->coin->coinsCode
                    ?? '—';

                $row['balance'] =
                    (float) (
                        $balances[$acc->accountID]
                        ?? 0
                    );

                $row['currency_id'] =
                    $entity->coinsID;

                $row['currency_code'] =
                    $entity->coin->coinsCode
                    ?? '';

                $row['exchange_rate'] =
                    (float) (
                        $entity
                            ->coin
                            ->coinsExchangeRate
                        ?? 1
                    );
            }

            $rows[] = $row;
        }

        return response()->json([
            'success' => true,
            'type'    => $type,
            'rows'    => $rows,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // EXPENSE PICKER
    // ══════════════════════════════════════════════════════════

    private function pickerExpenses(
        string $search
    ): JsonResponse {
        $parentId =
            CharAccount::whereRaw(
                'LOWER(system_key) = ?',
                ['expenses']
            )->value('accountID');

        if (!$parentId) {
            return response()->json([
                'success' => false,
                'message' =>
                    'لم يتم العثور على حساب المصروفات',
            ]);
        }

        $ids =
            $this->getDescendantIds(
                (int) $parentId
            );

        if (empty($ids)) {
            return response()->json([
                'success' => true,
                'type'    => 'expense',
                'rows'    => [],
            ]);
        }

        $query =
            CharAccount::whereIn(
                'accountID',
                $ids
            )
            ->where('IsActive', 1)
            ->where('isPostable', 1);

        /*
         * نحتاج الحساب الأب المباشر للمصروف
         * حتى يظهر اسم الأب في القائمة.
         */
        $candidateAccounts =
            (clone $query)->get([
                'accountID',
                'accParent',
            ]);

        $parentIDs =
            $candidateAccounts
                ->pluck('accParent')
                ->filter()
                ->unique()
                ->values()
                ->all();

        $parents =
            CharAccount::whereIn(
                'accountID',
                $parentIDs
            )
            ->get([
                'accountID',
                'accCode',
                'accName',
            ])
            ->keyBy('accountID');

        if ($search !== '') {
            $parentSearchIDs =
                CharAccount::whereIn(
                    'accountID',
                    $parentIDs
                )
                ->where(function ($q) use ($search) {
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
                })
                ->pluck('accountID');

            $query->where(function ($q) use (
                $search,
                $parentSearchIDs
            ) {
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

                if ($parentSearchIDs->isNotEmpty()) {
                    $q->orWhereIn(
                        'accParent',
                        $parentSearchIDs
                    );
                }
            });
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

        $accountIDs =
            $accounts
                ->pluck('accountID')
                ->map(fn ($id) => (int) $id)
                ->all();

        $balances =
            AccountBalanceService::getBalances(
                $accountIDs
            );

        $rows =
            $accounts->map(
                function ($acc) use (
                    $parents,
                    $balances
                ) {
                    $parent =
                        $parents->get(
                            $acc->accParent
                        );

                    return [
                        'id' =>
                            $acc->accountID,

                        'code' =>
                            $acc->accCode,

                        'name' =>
                            $acc->accName,

                        'parent_name' =>
                            $parent->accName ?? '—',

                        'parent_code' =>
                            $parent->accCode ?? '—',

                        'extra' =>
                            '—',

                        'balance' =>
                            (float) (
                                $balances[
                                    $acc->accountID
                                ] ?? 0
                            ),

                        'nature' =>
                            (int) $acc->nature,

                        'currency_id' =>
                            null,

                        'currency_code' =>
                            null,

                        'exchange_rate' =>
                            1,
                    ];
                }
            )->values();

        return response()->json([
            'success' => true,
            'type'    => 'expense',
            'rows'    => $rows,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // DESCENDANTS
    // ══════════════════════════════════════════════════════════

    private function getDescendantIds(
        int $parentId
    ): array {
        $byParent =
            CharAccount::where('IsActive', 1)
                ->get([
                    'accountID',
                    'accParent',
                ])
                ->groupBy('accParent');

        $ids = [];

        $currentLevel = [
            $parentId,
        ];

        for ($i = 0; $i < 10; $i++) {
            $children = [];

            foreach ($currentLevel as $pid) {
                foreach (
                    $byParent[$pid] ?? []
                    as $child
                ) {
                    $children[] =
                        $child->accountID;
                }
            }

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

        return $ids;
    }

    // ══════════════════════════════════════════════════════════
    // HISTORICAL BALANCE
    // ══════════════════════════════════════════════════════════

    private function getBalanceAroundEntry(
        int $accountID,
        ?int $entryNo
    ): array {
        $account =
            CharAccount::find($accountID);

        $nature =
            (int) ($account->nature ?? 0);

        /*
         * إذا لم يوجد رقم قيد:
         * نستخدم الرصيد الحالي كحل احتياطي.
         */
        if (!$entryNo) {
            $balance =
                AccountBalanceService::getBalance(
                    $accountID
                );

            return [
                'before' => $balance,
                'after'  => $balance,
                'nature' => $nature,
            ];
        }

        $lineTable =
            (new JournalEntryLine)->getTable();

        $entryTable =
            (new JournalEntry)->getTable();

        $totalsBefore =
            DB::table(
                $lineTable . ' as l'
            )
            ->join(
                $entryTable . ' as e',
                'e.entryID',
                '=',
                'l.entryID'
            )
            ->where(
                'l.accountID',
                $accountID
            )
            ->where(
                'e.entryNo',
                '<',
                $entryNo
            )
            ->selectRaw(
                'COALESCE(SUM(l.localDebit), 0) AS debit,
                 COALESCE(SUM(l.localCredit), 0) AS credit'
            )
            ->first();

        $totalsAfter =
            DB::table(
                $lineTable . ' as l'
            )
            ->join(
                $entryTable . ' as e',
                'e.entryID',
                '=',
                'l.entryID'
            )
            ->where(
                'l.accountID',
                $accountID
            )
            ->where(
                'e.entryNo',
                '<=',
                $entryNo
            )
            ->selectRaw(
                'COALESCE(SUM(l.localDebit), 0) AS debit,
                 COALESCE(SUM(l.localCredit), 0) AS credit'
            )
            ->first();

        $before =
            $this->calculateNatureBalance(
                (float) ($totalsBefore->debit ?? 0),
                (float) ($totalsBefore->credit ?? 0),
                $nature
            );

        $after =
            $this->calculateNatureBalance(
                (float) ($totalsAfter->debit ?? 0),
                (float) ($totalsAfter->credit ?? 0),
                $nature
            );

        return [
            'before' => $before,
            'after'  => $after,
            'nature' => $nature,
        ];
    }

    private function calculateNatureBalance(
        float $debit,
        float $credit,
        int $nature
    ): float {
        if ($nature === 1) {
            return $credit - $debit;
        }

        return $debit - $credit;
    }

    // ══════════════════════════════════════════════════════════
    // RESPONSE HELPERS
    // ══════════════════════════════════════════════════════════

    private function ok(
        array $data = [],
        int $status = 200
    ): JsonResponse {
        return response()->json(
            array_merge(
                ['success' => true],
                $data
            ),
            $status
        );
    }

    private function fail(
        string $message,
        int $status = 422
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    // ══════════════════════════════════════════════════════════
    // PRINT
    // ══════════════════════════════════════════════════════════

    public function printView(int $id)
    {
        session()->save();

        $voucher =
            PaymentVoucher::with([
                'beneficiaryAccount',
                'paymentAccount',
                'currency',
                'entry',
            ])->findOrFail($id);

        $paymentMethodText = [
            'cash' => 'نقد',
            'bank' => 'تحويل بنكي',
        ][$voucher->paymentMethod] ?? '—';

        $formattedDate =
            $voucher->voucherDate
                ? $voucher
                    ->voucherDate
                    ->locale('ar')
                    ->translatedFormat('d F Y')
                : '—';

        $printTime =
            now()
                ->locale('ar')
                ->translatedFormat(
                    'd/m/Y H:i'
                );

        $companyName =
            config(
                'app.company_name',
                'نظام ERP'
            );

        $currencyName =
            $voucher
                ->currency
                ->coinsName ?? '';

        $amountWords =
            \App\Helpers\Tafqeet::numberToWords(
                (float) $voucher->amount,
                $currencyName
            );

        $balanceInfo =
            $this->getBalanceAroundEntry(
                (int) $voucher->beneficiaryAccountID,
                $voucher->entry?->entryNo
            );

        $balanceBefore =
            $balanceInfo['before'];

        $balanceAfter =
            $balanceInfo['after'];

        $systemCurrency =
            Coin::where(
                'coinsSystem',
                1
            )->first([
                'coinsCode',
            ]);

        return view(
            'operation.accounting.paymentVouchers.print',
            [
                'voucher' =>
                    $voucher,

                'paymentMethodText' =>
                    $paymentMethodText,

                'formattedDate' =>
                    $formattedDate,

                'printTime' =>
                    $printTime,

                'companyName' =>
                    $companyName,

                'amountWords' =>
                    $amountWords,

                'balanceBefore' =>
                    $balanceBefore,

                'balanceAfter' =>
                    $balanceAfter,

                'systemCurrencyCode' =>
                    $systemCurrency->coinsCode
                    ?? '',
            ]
        );
    }
}