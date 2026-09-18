<?php

namespace App\Http\Controllers\accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Bank;
use App\Models\Accounting\Box;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\OpeningBalance;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\JournalEntryService;

class OpeningBalanceController extends Controller
{
    // ══════════════════════════════════════════════════════════
    //  الثوابت
    // ══════════════════════════════════════════════════════════

    private const SYSTEM_KEYS = [
        'CASH'     => ['cash'],
        'BANK'     => ['banks', 'bank'],
        'CUSTOMER' => ['customers', 'customer'],
        'SUPPLIER' => ['suppliers', 'supplier'],
    ];

    private const TYPE_BY_KEY = [
        'cash'      => 'CASH',
        'banks'     => 'BANK',
        'bank'      => 'BANK',
        'customers' => 'CUSTOMER',
        'customer'  => 'CUSTOMER',
        'suppliers' => 'SUPPLIER',
        'supplier'  => 'SUPPLIER',
    ];

    private const PICKER_LIMIT = 100;
    private const CACHE_TTL    = 60;

    // ══════════════════════════════════════════════════════════
    //  Cache داخل الطلب
    // ══════════════════════════════════════════════════════════

    private array $parentCache      = [];
    private array $descendantsCache = [];

    // ══════════════════════════════════════════════════════════
    //  Endpoints
    // ══════════════════════════════════════════════════════════

    public function index()
    {
        session()->save();

        $systemCurrency = Coin::where('coinsSystem', 1)
            ->first(['coinsID', 'coinsCode']);

        return view('setting.accounting.openingBalances.index', [
            'currencies'         => $this->getActiveCurrencies(),
            'systemCurrencyId'   => $systemCurrency->coinsID ?? null,
            'systemCurrencyCode' => $systemCurrency->coinsCode ?? '',
        ]);
    }

    public function picker(Request $request): JsonResponse
    {
        session()->save();

        $type   = $request->input('type', 'CASH');
        $search = trim($request->input('search', ''));

        return $this->ok(['data' => $this->fetchEntities($type, $search)]);
    }

    public function list(Request $request): JsonResponse
    {
        session()->save();

        $type   = $request->input('type', 'CASH');
        $search = trim($request->input('search', ''));

        $cacheKey = "ob_list_{$type}_" . md5($search);

        return $this->ok(
            cache()->remember($cacheKey, self::CACHE_TTL, fn() => $this->buildList($type, $search))
        );
    }

    public function edit(int $id): JsonResponse
    {
        session()->save();

        return $this->ok($this->buildEditPayload($id));
    }

    public function store(Request $request): JsonResponse
    {
        session()->save();
        return $this->persist($request);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        session()->save();
        return $this->persist($request, $id);
    }

    public function destroy(int $id): JsonResponse
    {
        session()->save();

        // 1) احذف القيد المرتبط (قبل حذف الرصيد)
        JournalEntryService::deleteByDocNumber('OB-' . $id);

        // 2) احذف الرصيد
        if (!OpeningBalance::where('openingBalancesID', $id)->delete()) {
            return $this->fail('الرصيد غير موجود.', 404);
        }

        $this->clearListCache();

        return $this->ok(['message' => 'تم حذف الرصيد بنجاح']);
    }

    // ══════════════════════════════════════════════════════════
    //  Response Helpers
    // ══════════════════════════════════════════════════════════

    private function ok(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['success' => true], $data), $status);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    // ══════════════════════════════════════════════════════════
    //  Account Hierarchy
    // ══════════════════════════════════════════════════════════

    private function parentIdByType(string $type): ?int
    {
        if (array_key_exists($type, $this->parentCache)) {
            return $this->parentCache[$type];
        }

        return $this->parentCache[$type] = cache()->rememberForever(
            "ob_parent_{$type}",
            fn() => $this->resolveParentId($type)
        );
    }

    private function resolveParentId(string $type): ?int
    {
        $patterns = self::SYSTEM_KEYS[$type] ?? [];

        foreach ($patterns as $pattern) {
            $id = CharAccount::whereRaw('LOWER(system_key) = ?', [strtolower($pattern)])
                ->value('accountID');

            if ($id) return $id;
        }

        foreach ($patterns as $pattern) {
            $id = CharAccount::whereRaw('LOWER(system_key) LIKE ?', ['%' . strtolower($pattern) . '%'])
                ->value('accountID');

            if ($id) return $id;
        }

        return null;
    }

    private function typeByParentId(?int $parentId): ?string
    {
        if (!$parentId) return null;

        $key = CharAccount::where('accountID', $parentId)->value('system_key');

        return $key ? (self::TYPE_BY_KEY[strtolower($key)] ?? null) : null;
    }

    private function typeBySystemKey(?string $key): ?string
    {
        return $key ? (self::TYPE_BY_KEY[strtolower($key)] ?? null) : null;
    }

    private function getAllDescendantAccountIds(int $parentId): array
    {
        if (isset($this->descendantsCache[$parentId])) {
            return $this->descendantsCache[$parentId];
        }

        return $this->descendantsCache[$parentId] = cache()->rememberForever(
            "ob_desc_{$parentId}",
            fn() => $this->buildDescendantIds($parentId)
        );
    }

    private function buildDescendantIds(int $parentId): array
    {
        $byParent = CharAccount::where('IsActive', 1)
            ->get(['accountID', 'accParent'])
            ->groupBy('accParent');

        $ids          = [$parentId];
        $currentLevel = [$parentId];

        for ($i = 0; $i < 10; $i++) {
            $children = [];

            foreach ($currentLevel as $pid) {
                foreach ($byParent[$pid] ?? [] as $child) {
                    $children[] = $child->accountID;
                }
            }

            if (empty($children)) break;

            $ids          = array_merge($ids, $children);
            $currentLevel = $children;
        }

        return $ids;
    }

    // ══════════════════════════════════════════════════════════
    //  Build List
    // ══════════════════════════════════════════════════════════

    private function buildList(string $type, string $search): array
    {
        $parentId = $this->parentIdByType($type);

        if (!$parentId) {
            return $this->emptyList();
        }

        $balances = $this->fetchBalances($parentId, $search);

        if ($balances->isEmpty()) {
            return $this->emptyList();
        }

        $accountIds = $balances->pluck('accountID')->unique()->all();
        $entities   = $this->bulkFetchEntities($accountIds);

        $result = $this->mapBalancesToRows($balances, $entities);

        return [
            'rows'   => $result['items'],
            'totals' => $result['totals'],
        ];
    }

    private function emptyList(): array
    {
        return [
            'rows'   => [],
            'totals' => $this->emptyTotals(),
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'debit'        => 0,
            'credit'       => 0,
            'net'          => 0,
            'local_debit'  => 0,
            'local_credit' => 0,
            'local_net'    => 0,
        ];
    }

    private function fetchBalances(int $parentId, string $search)
    {
        $accountIds = $this->getAllDescendantAccountIds($parentId);

        $query = OpeningBalance::query()
            ->with(['account', 'currency'])
            ->whereIn('accountID', $accountIds);

        if ($search !== '') {
            $query->whereHas('account', fn($q) => $q
                ->where('accCode', 'like', "%{$search}%")
                ->orWhere('accName', 'like', "%{$search}%"));
        }

        return $query->orderBy('openingBalancesID')->get();
    }

    private function mapBalancesToRows($balances, array $entities): array
    {
        $totals = $this->emptyTotals();
        $items  = [];

        foreach ($balances as $row) {
            $debit  = (float) $row->opeDebit;
            $credit = (float) $row->opeCredit;
            $rate   = (float) ($row->opeExchangeRate ?? 1);

            $localDebit  = $debit  * $rate;
            $localCredit = $credit * $rate;

            $totals['debit']        += $debit;
            $totals['credit']       += $credit;
            $totals['local_debit']  += $localDebit;
            $totals['local_credit'] += $localCredit;

            $entity = $entities[$row->accountID] ?? null;

            $items[] = [
                'id'             => $row->openingBalancesID,
                'entity_id'      => $entity['id']   ?? null,
                'entity_code'    => $entity['code'] ?? '',
                'entity_name'    => $entity['name'] ?? '',
                'account_id'     => $row->accountID,
                'account_code'   => $row->account->accCode ?? '',
                'account_name'   => $row->account->accName ?? '',
                'currency_id'    => $row->coinsID,
                'currency_code'  => $row->currency->coinsCode ?? '',
                'exchange_rate'  => $rate,
                'debit'          => $debit,
                'credit'         => $credit,
                'net'            => $debit - $credit,
                'local_debit'    => $localDebit,
                'local_credit'   => $localCredit,
                'local_net'      => $localDebit - $localCredit,
            ];
        }

        $totals['net']       = $totals['debit'] - $totals['credit'];
        $totals['local_net'] = $totals['local_debit'] - $totals['local_credit'];

        return ['items' => $items, 'totals' => $totals];
    }

    // ══════════════════════════════════════════════════════════
    //  Entity Fetching
    // ══════════════════════════════════════════════════════════

    private function bulkFetchEntities(array $accountIds): array
    {
        if (empty($accountIds)) return [];

        $map = [];

        $this->mergeInto($map, Box::with('account')->whereIn('accountID', $accountIds)->get(), fn($r) => [
            'id'   => $r->boxID,
            'code' => $r->account->accCode ?? '',
            'name' => $r->boxName,
        ]);

        $this->mergeInto($map, Bank::with('account')->whereIn('accountID', $accountIds)->get(), fn($r) => [
            'id'   => $r->bankID,
            'code' => $r->account->accCode ?? '',
            'name' => $r->bankName,
        ]);

        $this->mergeInto($map, Customer::with('account')->whereIn('accountID', $accountIds)->get(), fn($r) => [
            'id'   => $r->CustomersID,
            'code' => $r->account->accCode ?? '',
            'name' => $r->CustomersName2,
        ]);

        $this->mergeInto($map, Supplier::with('account')->whereIn('accountID', $accountIds)->get(), fn($r) => [
            'id'   => $r->suplierID,
            'code' => $r->account->accCode ?? '',
            'name' => $r->supName,
        ]);

        return $map;
    }

    private function mergeInto(array &$map, $rows, callable $transform): void
    {
        foreach ($rows as $row) {
            $map[$row->accountID] ??= $transform($row);
        }
    }

    // ══════════════════════════════════════════════════════════
    //  Picker Data
    // ══════════════════════════════════════════════════════════

    private function fetchEntities(string $type, string $search): array
    {
        return match ($type) {
            'CASH'     => $this->fetchBoxes($search),
            'BANK'     => $this->fetchBanks($search),
            'CUSTOMER' => $this->fetchCustomers($search),
            'SUPPLIER' => $this->fetchSuppliers($search),
            default    => [],
        };
    }

    private function fetchBoxes(string $search): array
    {
        $q = Box::with(['account', 'coin'])
            ->where('is_active', 1)
            ->whereHas('account', fn($a) => $a->where('IsActive', 1));

        $this->applySearch($q, $search, 'boxName');

        return $q->limit(self::PICKER_LIMIT)->get()
            ->map(fn($r) => [
                'id'            => $r->boxID,
                'code'          => $r->account->accCode ?? '',
                'name'          => $r->boxName ?? '',
                'account_id'    => $r->accountID,
                'account_code'  => $r->account->accCode ?? '',
                'account_name'  => $r->account->accName ?? '',
                'currency_id'   => $r->coinsID,
                'currency_code' => $r->coin->coinsCode ?? '—',
                'exchange_rate' => $r->coin->coinsExchangeRate ?? 1,
            ])->all();
    }

    private function fetchBanks(string $search): array
    {
        $q = Bank::with(['account', 'coin'])
            ->where('is_active', 1)
            ->whereHas('account', fn($a) => $a->where('IsActive', 1));

        $this->applySearch($q, $search, 'bankName');

        return $q->limit(self::PICKER_LIMIT)->get()
            ->map(fn($r) => [
                'id'            => $r->bankID,
                'code'          => $r->account->accCode ?? '',
                'name'          => $r->bankName ?? '',
                'account_id'    => $r->accountID,
                'account_code'  => $r->account->accCode ?? '',
                'account_name'  => $r->account->accName ?? '',
                'currency_id'   => $r->coinsID,
                'currency_code' => $r->coin->coinsCode ?? '—',
                'exchange_rate' => $r->coin->coinsExchangeRate ?? 1,
            ])->all();
    }

    private function fetchCustomers(string $search): array
    {
        $q = Customer::with('account')
            ->where('CusIsStopeed', 0)
            ->whereHas('account', fn($a) => $a->where('IsActive', 1));

        $this->applySearch($q, $search, 'CustomersName2');

        return $q->limit(self::PICKER_LIMIT)->get()
            ->map(fn($r) => [
                'id'            => $r->CustomersID,
                'code'          => $r->account->accCode ?? '',
                'name'          => $r->CustomersName2 ?? '',
                'account_id'    => $r->accountID,
                'account_code'  => $r->account->accCode ?? '',
                'account_name'  => $r->account->accName ?? '',
                'currency_id'   => null,
                'currency_code' => null,
                'exchange_rate' => 1,
            ])->all();
    }

    private function fetchSuppliers(string $search): array
    {
        $q = Supplier::with('account')
            ->where('supStoped', 0)
            ->whereHas('account', fn($a) => $a->where('IsActive', 1));

        $this->applySearch($q, $search, 'supName');

        return $q->limit(self::PICKER_LIMIT)->get()
            ->map(fn($r) => [
                'id'            => $r->suplierID,
                'code'          => $r->account->accCode ?? '',
                'name'          => $r->supName ?? '',
                'account_id'    => $r->accountID,
                'account_code'  => $r->account->accCode ?? '',
                'account_name'  => $r->account->accName ?? '',
                'currency_id'   => null,
                'currency_code' => null,
                'exchange_rate' => 1,
            ])->all();
    }

    private function applySearch($query, string $search, string $nameColumn): void
    {
        if ($search === '') return;

        $query->where(function ($q) use ($search, $nameColumn) {
            $q->where($nameColumn, 'like', "%{$search}%")
              ->orWhereHas('account', fn($a) => $a
                  ->where('accCode', 'like', "%{$search}%")
                  ->orWhere('accName', 'like', "%{$search}%"));
        });
    }

    // ══════════════════════════════════════════════════════════
    //  Edit Payload
    // ══════════════════════════════════════════════════════════

    private function buildEditPayload(int $id): array
    {
        $row = OpeningBalance::with(['account', 'currency'])->findOrFail($id);

        $type = $this->typeByParentId($row->account->accParent ?? null)
             ?? $this->typeBySystemKey($row->account->system_key ?? null);

        return [
            'id'    => $row->openingBalancesID,
            'lines' => [[
                'type'          => $type,
                'account_id'    => $row->accountID,
                'account_code'  => $row->account->accCode ?? '',
                'account_name'  => $row->account->accName ?? '',
                'entity_id'     => null,
                'entity_code'   => '',
                'entity_name'   => '',
                'currency_id'   => $row->coinsID,
                'exchange_rate' => (float) $row->opeExchangeRate,
                'debit'         => (float) $row->opeDebit,
                'credit'        => (float) $row->opeCredit,
                'notes'         => '',
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════
    //  Persist
    // ══════════════════════════════════════════════════════════

    private function persist(Request $request, ?int $id = null): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lines'                 => 'required|array|min:1|max:100',
            'lines.*.type'          => 'required|in:CASH,BANK,CUSTOMER,SUPPLIER',
            'lines.*.account_id'    => 'required|exists:characcount,accountID',
            'lines.*.currency_id'   => 'nullable|exists:coins,coinsID',
            'lines.*.exchange_rate' => 'nullable|numeric|gt:0',
            'lines.*.debit'         => 'nullable|numeric|min:0',
            'lines.*.credit'        => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first());
        }

        if ($error = $this->validateLines($request->lines, $id)) {
            return $this->fail($error);
        }

        DB::beginTransaction();

        try {
            // عند التعديل: احذف الأرصدة القديمة والقيود المرتبطة
            if ($id) {
                JournalEntryService::deleteByDocNumber('OB-' . $id);
                OpeningBalance::where('openingBalancesID', $id)->delete();
            }

            // أضف الأرصدة الجديدة
            $insertedIDs = [];

            foreach ($request->lines as $line) {
                $row = OpeningBalance::create([
                    'accountID'       => $line['account_id'],
                    'coinsID'         => $line['currency_id'] ?? null,
                    'opeExchangeRate' => (float) ($line['exchange_rate'] ?? 1),
                    'opeDebit'        => (float) ($line['debit']  ?? 0),
                    'opeCredit'       => (float) ($line['credit'] ?? 0),
                    'opeFiscalYear'   => now()->startOfYear(),
                    'opeData'         => now(),
                ]);

                $insertedIDs[] = $row->openingBalancesID;
            }

            // ⭐ إنشاء قيد لكل رصيد مضاف
            foreach ($insertedIDs as $balanceID) {
                $this->createEntryForBalance($balanceID);
            }

            DB::commit();

            $this->clearListCache();

            return $this->ok([
                'message' => $id ? 'تم تعديل الرصيد بنجاح' : 'تم إضافة الرصيد بنجاح',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail('فشل الحفظ: ' . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════
    //  Journal Entry Integration
    // ══════════════════════════════════════════════════════════

    /**
     * إنشاء قيد محاسبي لرصيد افتتاحي واحد
     */
    private function createEntryForBalance(int $balanceID): void
    {
        $balance = OpeningBalance::with(['account', 'currency'])->find($balanceID);
        if (!$balance) return;

        $offsetAccount = CharAccount::where('system_key', 'openingBalance')->first();
        if (!$offsetAccount) {
            Log::warning('حساب الأرصدة الافتتاحية غير موجود (system_key = openingBalance)');
            return;
        }

        $rate   = (float) ($balance->opeExchangeRate ?? 1);
        $debit  = (float) $balance->opeDebit;
        $credit = (float) $balance->opeCredit;

        $localDebit  = $debit  * $rate;
        $localCredit = $credit * $rate;
        $netAmount   = $localDebit - $localCredit;

        if ($netAmount == 0) return;

        // ⭐ استخدم Service المشترك
        JournalEntryService::create([
            'docType'     => 'قيد افتتاحي',
            'docNumber'   => 'OB-' . $balanceID,
            'entryDate'   => $balance->opeData ?? now(),
            'description' => 'الأرصدة الافتتاحية - ' . ($balance->account->accName ?? ''),
            'lines'       => [
                // سطر الرصيد
                [
                    'accountID'   => $balance->accountID,
                    'coinsID'     => $balance->coinsID,
                    'exchangRate' => $rate,
                    'debit'       => $debit,
                    'credit'      => $credit,
                    'localDebit'  => $localDebit,
                    'localCredit' => $localCredit,
                ],
                // سطر الطرف المقابل
                [
                    'accountID'   => $offsetAccount->accountID,
                    'coinsID'     => null,
                    'exchangRate' => 1,
                    'debit'       => $netAmount < 0 ? abs($netAmount) : 0,
                    'credit'      => $netAmount > 0 ? $netAmount : 0,
                    'localDebit'  => $netAmount < 0 ? abs($netAmount) : 0,
                    'localCredit' => $netAmount > 0 ? $netAmount : 0,
                ],
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  Validation
    // ══════════════════════════════════════════════════════════

    private function validateLines(array $lines, ?int $excludeId = null): ?string
    {
        $accountIds = collect($lines)->pluck('account_id')->unique()->all();

        $ids = collect($lines)->pluck('account_id');

        if ($ids->count() !== $ids->unique()->count()) {
            return 'لا يمكن إدخال نفس الحساب أكثر من مرة في نفس العملية.';
        }

        $query = OpeningBalance::whereIn('accountID', $accountIds);

        if ($excludeId) {
            $query->where('openingBalancesID', '!=', $excludeId);
        }

        $existingIds = $query->pluck('accountID')->all();

        if (!empty($existingIds)) {
            $duplicates = CharAccount::whereIn('accountID', $existingIds)
                ->get(['accountID', 'accCode', 'accName'])
                ->map(fn($a) => "{$a->accCode} - {$a->accName}")
                ->implode('، ');

            return "الحسابات التالية لها رصيد افتتاحي مسجل مسبقًا: {$duplicates}";
        }

        $activeIds = CharAccount::whereIn('accountID', $accountIds)
            ->where('IsActive', 1)
            ->pluck('accountID')
            ->flip();

        foreach ($lines as $i => $line) {
            if ($error = $this->validateLine($line, $i, $activeIds)) {
                return $error;
            }
        }

        return null;
    }

    private function validateLine(array $line, int $index, $activeIds): ?string
    {
        $row = $index + 1;

        $parentId = $this->parentIdByType($line['type']);
        if (!$parentId) {
            return "السطر {$row}: لم يتم العثور على الحساب الأب للنوع المحدد.";
        }

        if (!in_array($line['account_id'], $this->getAllDescendantAccountIds($parentId))) {
            return "السطر {$row}: الحساب لا ينتمي للنوع المحدد أو غير نشط.";
        }

        if (!isset($activeIds[$line['account_id']])) {
            return "السطر {$row}: الحساب غير نشط.";
        }

        if ((float) ($line['exchange_rate'] ?? 0) <= 0) {
            return "السطر {$row}: سعر الصرف يجب أن يكون أكبر من صفر.";
        }

        $debit  = (float) ($line['debit']  ?? 0);
        $credit = (float) ($line['credit'] ?? 0);

        if ($debit > 0 && $credit > 0) {
            return "السطر {$row}: لا يمكن إدخال مدين ودائن في نفس السطر.";
        }

        if ($debit <= 0 && $credit <= 0) {
            return "السطر {$row}: يجب أن يكون المبلغ أكبر من صفر.";
        }

        return null;
    }

    private function buildInsertData(array $lines): array
    {
        $now  = now();
        $year = $now->copy()->startOfYear();

        return array_map(fn($line) => [
            'accountID'       => $line['account_id'],
            'coinsID'         => $line['currency_id'] ?? null,
            'opeExchangeRate' => (float) ($line['exchange_rate'] ?? 1),
            'opeDebit'        => (float) ($line['debit']  ?? 0),
            'opeCredit'       => (float) ($line['credit'] ?? 0),
            'opeFiscalYear'   => $year,
            'opeData'         => $now,
        ], $lines);
    }

    // ══════════════════════════════════════════════════════════
    //  Cache Helpers
    // ══════════════════════════════════════════════════════════

    public function clearCache(): void
    {
        foreach (array_keys(self::SYSTEM_KEYS) as $type) {
            cache()->forget("ob_parent_{$type}");
        }

        $this->clearListCache();

        $this->parentCache      = [];
        $this->descendantsCache = [];
    }

    private function clearListCache(): void
    {
        foreach (array_keys(self::SYSTEM_KEYS) as $type) {
            cache()->forget("ob_list_{$type}_" . md5(''));
        }
    }

    // ══════════════════════════════════════════════════════════
    //  Currency Helpers
    // ══════════════════════════════════════════════════════════

    private function getActiveCurrencies(): array
    {
        return Coin::where('is_active', 1)
            ->orderBy('coinsName')
            ->get(['coinsID', 'coinsName', 'coinsCode', 'coinsExchangeRate'])
            ->all();
    }
}