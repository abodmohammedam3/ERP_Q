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
use App\Models\Inventory\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\JournalEntryService;
use App\Services\AccountBalanceService;

class OpeningBalanceController extends Controller
{
    // ══════════════════════════════════════════════════════════
    //  الثوابت
    // ══════════════════════════════════════════════════════════

    private const SYSTEM_KEYS = [
        'CASH'      => ['cash'],
        'BANK'      => ['banks', 'bank'],
        'CUSTOMER'  => ['customers', 'customer'],
        'SUPPLIER'  => ['suppliers', 'supplier'],
        'INVENTORY' => ['inventory', 'stock'],
    ];

    private const TYPE_BY_KEY = [
        'cash'      => 'CASH',
        'banks'     => 'BANK',
        'bank'      => 'BANK',
        'customers' => 'CUSTOMER',
        'customer'  => 'CUSTOMER',
        'suppliers' => 'SUPPLIER',
        'supplier'  => 'SUPPLIER',
        'inventory' => 'INVENTORY',
        'stock'     => 'INVENTORY',
    ];

    private const PICKER_LIMIT          = 100;
    private const CACHE_TTL             = 300;     // 5 دقائق
    private const PARENT_CACHE_TTL      = 86400;   // يوم
    private const DESCENDANTS_CACHE_TTL = 1800;    // 30 دقيقة
    private const VERSION_TTL           = 86400;   // يوم — لمفاتيح version

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

        $type    = $request->input('type', 'CASH');
        $search  = trim($request->input('search', ''));
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', 10)));

        $cacheKey = $this->buildListCacheKey($type, $search, $page, $perPage);

        return $this->ok(
            cache()->remember(
                $cacheKey,
                self::CACHE_TTL,
                fn() => $this->buildPaginatedList($type, $search, $page, $perPage)
            )
        );
    }

    public function print(Request $request)
    {
        session()->save();

        $type   = $request->input('type', 'CASH');
        $search = trim($request->input('search', ''));

        $data = $this->buildFullList($type, $search);

        $systemCurrency = Coin::where('coinsSystem', 1)->first(['coinsCode']);

        $typeLabels = [
            'CASH'      => 'الصناديق',
            'BANK'      => 'البنوك',
            'CUSTOMER'  => 'العملاء',
            'SUPPLIER'  => 'الموردين',
            'INVENTORY' => 'المخازن',
        ];

        return view('setting.accounting.openingBalances.print', [
            'rows'               => $data['rows']   ?? [],
            'totals'             => $data['totals'] ?? [],
            'typeLabel'          => $typeLabels[$type] ?? 'الأرصدة',
            'typeCode'           => $type,
            'systemCurrencyCode' => $systemCurrency->coinsCode ?? '',
            'companyName'        => config('app.name', 'نظام ERP'),
            'userName'           => auth()->user()->name ?? '—',
            'printDate'          => now(),
        ]);
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

        JournalEntryService::deleteByDocNumber('OB-' . $id);

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
    //  Empty Responses
    // ══════════════════════════════════════════════════════════

    /**
     * ✅ استجابة فارغة مع ترقيم
     */
    private function emptyPaginatedResponse(int $page, int $perPage): array
    {
        return [
            'rows'       => [],
            'totals'     => $this->emptyTotals(),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => 0,
                'last_page'    => 1,
            ],
        ];
    }

    /**
     * ✅ استجابة فارغة بدون ترقيم (للطباعة)
     */
    private function emptyList(): array
    {
        return [
            'rows'   => [],
            'totals' => $this->emptyTotals(),
        ];
    }

    /**
     * ✅ إجماليات صفرية
     */
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

    // ══════════════════════════════════════════════════════════
    //  Account Hierarchy
    // ══════════════════════════════════════════════════════════

    private function parentIdByType(string $type): ?int
    {
        if (array_key_exists($type, $this->parentCache)) {
            return $this->parentCache[$type];
        }

        return $this->parentCache[$type] = cache()->remember(
            "ob_parent_{$type}",
            self::PARENT_CACHE_TTL,
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

        return $this->descendantsCache[$parentId] = cache()->remember(
            "ob_desc_{$parentId}",
            self::DESCENDANTS_CACHE_TTL,
            fn() => $this->buildDescendantIds($parentId)
        );
    }

    /**
     * ✅ بناء أبناء الحساب مستوى بمستوى
     */
    private function buildDescendantIds(int $parentId): array
    {
        $ids          = [$parentId];
        $currentLevel = [$parentId];

        for ($i = 0; $i < 10; $i++) {
            $children = CharAccount::whereIn('accParent', $currentLevel)
                ->where('IsActive', 1)
                ->pluck('accountID')
                ->all();

            if (empty($children)) break;

            $ids          = array_merge($ids, $children);
            $currentLevel = $children;
        }

        return $ids;
    }

    // ══════════════════════════════════════════════════════════
    //  buildPaginatedList
    // ══════════════════════════════════════════════════════════

    private function buildPaginatedList(string $type, string $search, int $page, int $perPage): array
    {
        $parentId = $this->parentIdByType($type);

        if (!$parentId) {
            return $this->emptyPaginatedResponse($page, $perPage);
        }

        $accountIds = $this->getAllDescendantAccountIds($parentId);

        if (empty($accountIds)) {
            return $this->emptyPaginatedResponse($page, $perPage);
        }

        // ─── الاستعلام الأساسي ───
        $baseQuery = OpeningBalance::query()
            ->whereIn('accountID', $accountIds);

        if ($search !== '') {
            $baseQuery->whereHas('account', fn($q) => $q
                ->where('accCode', 'like', "%{$search}%")
                ->orWhere('accName', 'like', "%{$search}%"));
        }

        // ─── 1) استعلام واحد: العدد + الإجماليات ───
        $agg = (clone $baseQuery)->selectRaw('
            COUNT(*)                                         AS total_count,
            COALESCE(SUM(opeDebit), 0)                       AS total_debit,
            COALESCE(SUM(opeCredit), 0)                      AS total_credit,
            COALESCE(SUM(opeDebit  * opeExchangeRate), 0)    AS total_local_debit,
            COALESCE(SUM(opeCredit * opeExchangeRate), 0)    AS total_local_credit
        ')->first();

        $total = (int) $agg->total_count;

        // ─── 2) الصفحة الحالية ───
        $balances = (clone $baseQuery)
            ->orderBy('openingBalancesID')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        if ($balances->isEmpty()) {
            return [
                'rows'       => [],
                'totals'     => $this->formatTotals($agg),
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => max(1, (int) ceil($total / $perPage)),
                ],
            ];
        }

        // ─── 3) جلب الحسابات والعملات دفعة واحدة ───
        $pageAccountIds  = $balances->pluck('accountID')->unique()->all();
        $pageCurrencyIds = $balances->pluck('coinsID')->filter()->unique()->all();

        $accounts = CharAccount::whereIn('accountID', $pageAccountIds)
            ->get(['accountID', 'accCode', 'accName', 'accParent', 'nature', 'system_key'])
            ->keyBy('accountID');

        $currencies = empty($pageCurrencyIds)
            ? collect()
            : Coin::whereIn('coinsID', $pageCurrencyIds)
                ->pluck('coinsCode', 'coinsID');

        // ─── 4) جلب الكيانات حسب النوع الموجود فقط ───
        $entities = $this->bulkFetchEntitiesFiltered($accounts);

        // ─── 5) بناء الصفوف ───
        $rows = $this->mapRowsWithAccounts($balances, $accounts, $currencies, $entities);

        return [
            'rows'       => $rows,
            'totals'     => $this->formatTotals($agg),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * ✅ تحويل نتيجة SUM إلى مصفوفة إجماليات
     */
    private function formatTotals($agg): array
    {
        $debit       = (float) $agg->total_debit;
        $credit      = (float) $agg->total_credit;
        $localDebit  = (float) $agg->total_local_debit;
        $localCredit = (float) $agg->total_local_credit;

        return [
            'debit'        => $debit,
            'credit'       => $credit,
            'net'          => $debit - $credit,
            'local_debit'  => $localDebit,
            'local_credit' => $localCredit,
            'local_net'    => $localDebit - $localCredit,
        ];
    }

    /**
     * ✅ جلب الكيانات حسب الأنواع الموجودة فقط
     */
    private function bulkFetchEntitiesFiltered($accounts): array
    {
        $byType = [];

        foreach ($accounts as $acc) {
            $type = $this->typeByParentId($acc->accParent)
                 ?? $this->typeBySystemKey($acc->system_key);

            if (!$type) continue;

            $byType[$type][] = $acc->accountID;
        }

        $map = [];

        if (!empty($byType['CASH'])) {
            $this->mergeInto(
                $map,
                Box::whereIn('accountID', $byType['CASH'])
                    ->get(['boxID', 'accountID', 'boxName']),
                fn($r) => ['id' => $r->boxID, 'name' => $r->boxName]
            );
        }

        if (!empty($byType['BANK'])) {
            $this->mergeInto(
                $map,
                Bank::whereIn('accountID', $byType['BANK'])
                    ->get(['bankID', 'accountID', 'bankName']),
                fn($r) => ['id' => $r->bankID, 'name' => $r->bankName]
            );
        }

        if (!empty($byType['CUSTOMER'])) {
            $this->mergeInto(
                $map,
                Customer::whereIn('accountID', $byType['CUSTOMER'])
                    ->get(['CustomersID', 'accountID', 'CustomersName2']),
                fn($r) => ['id' => $r->CustomersID, 'name' => $r->CustomersName2]
            );
        }

        if (!empty($byType['SUPPLIER'])) {
            $this->mergeInto(
                $map,
                Supplier::whereIn('accountID', $byType['SUPPLIER'])
                    ->get(['suplierID', 'accountID', 'supName']),
                fn($r) => ['id' => $r->suplierID, 'name' => $r->supName]
            );
        }

        if (!empty($byType['INVENTORY'])) {
            $this->mergeInto(
                $map,
                Stock::whereIn('accountID', $byType['INVENTORY'])
                    ->get(['StockID', 'accountID', 'StockName']),
                fn($r) => ['id' => $r->StockID, 'name' => $r->StockName]
            );
        }

        return $map;
    }

    /**
     * ✅ بناء الصفوف
     */
    private function mapRowsWithAccounts($balances, $accounts, $currencies, $entities): array
    {
        $items = [];

        foreach ($balances as $row) {
            $debit  = (float) $row->opeDebit;
            $credit = (float) $row->opeCredit;
            $rate   = (float) ($row->opeExchangeRate ?? 1);

            $localDebit  = $debit  * $rate;
            $localCredit = $credit * $rate;

            $account  = $accounts[$row->accountID] ?? null;
            $entity   = $entities[$row->accountID] ?? null;
            $currency = $row->coinsID ? ($currencies[$row->coinsID] ?? '') : '';

            $accountNature = (int) ($account->nature ?? 0);

            $netDiff      = $debit - $credit;
            $localNetDiff = $localDebit - $localCredit;

            $netByNature      = $accountNature === 1 ? -$netDiff      : $netDiff;
            $localNetByNature = $accountNature === 1 ? -$localNetDiff : $localNetDiff;

            $balanceLabel = $netDiff >= 0 ? 'لنا' : 'علينا';

            $items[] = [
                'id'             => $row->openingBalancesID,
                'entity_id'      => $entity['id']   ?? null,
                'entity_code'    => $account->accCode ?? '',
                'entity_name'    => $entity['name'] ?? ($account->accName ?? ''),
                'account_id'     => $row->accountID,
                'account_code'   => $account->accCode ?? '',
                'account_name'   => $account->accName ?? '',
                'currency_id'    => $row->coinsID,
                'currency_code'  => $currency,
                'exchange_rate'  => $rate,
                'debit'          => $debit,
                'credit'         => $credit,
                'net'            => $netByNature,
                'local_debit'    => $localDebit,
                'local_credit'   => $localCredit,
                'local_net'      => $localNetByNature,
                'balance_label'  => $balanceLabel,
            ];
        }

        return $items;
    }

    // ══════════════════════════════════════════════════════════
    //  buildFullList (للطباعة)
    // ══════════════════════════════════════════════════════════

    private function buildFullList(string $type, string $search): array
    {
        $parentId = $this->parentIdByType($type);

        if (!$parentId) return $this->emptyList();

        $accountIds = $this->getAllDescendantAccountIds($parentId);

        if (empty($accountIds)) return $this->emptyList();

        $baseQuery = OpeningBalance::query()->whereIn('accountID', $accountIds);

        if ($search !== '') {
            $baseQuery->whereHas('account', fn($q) => $q
                ->where('accCode', 'like', "%{$search}%")
                ->orWhere('accName', 'like', "%{$search}%"));
        }

        $agg = (clone $baseQuery)->selectRaw('
            COALESCE(SUM(opeDebit), 0)                    AS total_debit,
            COALESCE(SUM(opeCredit), 0)                   AS total_credit,
            COALESCE(SUM(opeDebit  * opeExchangeRate), 0) AS total_local_debit,
            COALESCE(SUM(opeCredit * opeExchangeRate), 0) AS total_local_credit
        ')->first();

        $balances = $baseQuery->orderBy('openingBalancesID')->get();

        $accountIds  = $balances->pluck('accountID')->unique()->all();
        $currencyIds = $balances->pluck('coinsID')->filter()->unique()->all();

        $accounts = CharAccount::whereIn('accountID', $accountIds)
            ->get(['accountID', 'accCode', 'accName', 'accParent', 'nature', 'system_key'])
            ->keyBy('accountID');

        $currencies = empty($currencyIds)
            ? collect()
            : Coin::whereIn('coinsID', $currencyIds)->pluck('coinsCode', 'coinsID');

        $entities = $this->bulkFetchEntitiesFiltered($accounts);

        $rows = $this->mapRowsWithAccounts($balances, $accounts, $currencies, $entities);

        return [
            'rows'   => $rows,
            'totals' => $this->formatTotals($agg),
        ];
    }

    // ══════════════════════════════════════════════════════════
    //  Entity Fetching (لـ Picker)
    // ══════════════════════════════════════════════════════════

    private function mergeInto(array &$map, $rows, callable $transform): void
    {
        foreach ($rows as $row) {
            $map[$row->accountID] ??= $transform($row);
        }
    }

    private function fetchEntities(string $type, string $search): array
    {
        return match ($type) {
            'CASH'      => $this->fetchBoxes($search),
            'BANK'      => $this->fetchBanks($search),
            'CUSTOMER'  => $this->fetchCustomers($search),
            'SUPPLIER'  => $this->fetchSuppliers($search),
            'INVENTORY' => $this->fetchStocks($search),
            default     => [],
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
                'phone'         => '',
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
                'phone'         => '',
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
            ->where('is_active', 1)
            ->whereHas('account', fn($a) => $a->where('IsActive', 1));

        $this->applySearch($q, $search, 'CustomersName2');

        return $q->limit(self::PICKER_LIMIT)->get()
            ->map(fn($r) => [
                'id'            => $r->CustomersID,
                'code'          => $r->account->accCode ?? '',
                'name'          => $r->CustomersName2 ?? '',
                'phone'         => $r->CusPhone ?? '',
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
            ->where('is_active', 1)
            ->whereHas('account', fn($a) => $a->where('IsActive', 1));

        $this->applySearch($q, $search, 'supName');

        return $q->limit(self::PICKER_LIMIT)->get()
            ->map(fn($r) => [
                'id'            => $r->suplierID,
                'code'          => $r->account->accCode ?? '',
                'name'          => $r->supName ?? '',
                'phone'         => $r->supPhone ?? '',
                'account_id'    => $r->accountID,
                'account_code'  => $r->account->accCode ?? '',
                'account_name'  => $r->account->accName ?? '',
                'currency_id'   => null,
                'currency_code' => null,
                'exchange_rate' => 1,
            ])->all();
    }

    private function fetchStocks(string $search): array
    {
        $q = Stock::with('account')
            ->where('is_active', 1)
            ->whereHas('account', fn($a) => $a->where('IsActive', 1));

        $this->applySearch($q, $search, 'StockName');

        return $q->limit(self::PICKER_LIMIT)->get()
            ->map(fn($r) => [
                'id'            => $r->StockID,
                'code'          => $r->account->accCode ?? '',
                'name'          => $r->StockName ?? '',
                'phone'         => '',
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
            ]],
        ];
    }

    // ══════════════════════════════════════════════════════════
    //  Persist
    // ══════════════════════════════════════════════════════════

    private function persist(Request $request, ?int $id = null): JsonResponse
    {
        $request->merge([
            'lines' => array_values($request->input('lines', [])),
        ]);

        $validator = Validator::make($request->all(), [
            'lines'                 => 'required|array|min:1|max:100',
            'lines.*.type'          => 'required|in:CASH,BANK,CUSTOMER,SUPPLIER,INVENTORY',
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
            $affectedAccountIds = [];

            if ($id) {
                $balance = OpeningBalance::find($id);

                if (!$balance) {
                    DB::rollBack();
                    return $this->fail('الرصيد غير موجود.', 404);
                }

                $oldAccountID = (int) $balance->accountID;

                $line = $request->lines[0] ?? null;

                if (!$line) {
                    DB::rollBack();
                    return $this->fail('لا يوجد سطر للتعديل.');
                }

                $balance->update([
                    'accountID'       => $line['account_id'],
                    'coinsID'         => $line['currency_id'] ?? null,
                    'opeExchangeRate' => (float) ($line['exchange_rate'] ?? 1),
                    'opeDebit'        => (float) ($line['debit']  ?? 0),
                    'opeCredit'       => (float) ($line['credit'] ?? 0),
                ]);

                if ($balance->entryID) {
                    JournalEntryService::updateEntry($balance->entryID, [
                        'docType'     => 'قيد افتتاحي',
                        'entryDate'   => $balance->opeData ?? now(),
                        'description' => 'الأرصدة الافتتاحية - ' . ($balance->account->accName ?? ''),
                        'lines'       => $this->buildEntryLines($balance),
                    ]);
                } else {
                    $entryID = $this->createEntryForBalance($balance);
                    if ($entryID) {
                        $balance->update(['entryID' => $entryID]);
                    }
                }

                $affectedAccountIds[] = $oldAccountID;
                $affectedAccountIds[] = (int) $balance->accountID;

            } else {
                foreach ($request->lines as $line) {
                    $balance = OpeningBalance::create([
                        'accountID'       => $line['account_id'],
                        'coinsID'         => $line['currency_id'] ?? null,
                        'entryID'         => null,
                        'opeExchangeRate' => (float) ($line['exchange_rate'] ?? 1),
                        'opeDebit'        => (float) ($line['debit']  ?? 0),
                        'opeCredit'       => (float) ($line['credit'] ?? 0),
                        'opeFiscalYear'   => now()->startOfYear(),
                        'opeData'         => now(),
                    ]);

                    $entryID = $this->createEntryForBalance($balance);

                    if ($entryID) {
                        $balance->update(['entryID' => $entryID]);
                    }

                    $affectedAccountIds[] = (int) $balance->accountID;
                }
            }

            DB::commit();

            if (!empty($affectedAccountIds)) {
                AccountBalanceService::recalculateBatch(
                    array_unique($affectedAccountIds)
                );
            }

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

    private function buildEntryLines(OpeningBalance $balance): array
    {
        $offsetAccount = CharAccount::where('system_key', 'ownerCapital')->first();

        if (!$offsetAccount) {
            throw new \RuntimeException(
                'حساب رأس مال المالك غير موجود (system_key = ownerCapital)'
            );
        }

        $rate   = (float) ($balance->opeExchangeRate ?? 1);
        $debit  = (float) $balance->opeDebit;
        $credit = (float) $balance->opeCredit;

        $localDebit  = $debit  * $rate;
        $localCredit = $credit * $rate;
        $netAmount   = $localDebit - $localCredit;

        return [
            [
                'accountID'   => $balance->accountID,
                'coinsID'     => $balance->coinsID,
                'exchangRate' => $rate,
                'debit'       => $debit,
                'credit'      => $credit,
                'localDebit'  => $localDebit,
                'localCredit' => $localCredit,
            ],
            [
                'accountID'   => $offsetAccount->accountID,
                'coinsID'     => null,
                'exchangRate' => 1,
                'debit'       => $netAmount < 0 ? abs($netAmount) : 0,
                'credit'      => $netAmount > 0 ? $netAmount : 0,
                'localDebit'  => $netAmount < 0 ? abs($netAmount) : 0,
                'localCredit' => $netAmount > 0 ? $netAmount : 0,
            ],
        ];
    }

    private function createEntryForBalance(OpeningBalance $balance): ?int
    {
        try {
            $lines = $this->buildEntryLines($balance);
        } catch (\RuntimeException $e) {
            Log::warning($e->getMessage());
            return null;
        }

        $rate   = (float) ($balance->opeExchangeRate ?? 1);
        $debit  = (float) $balance->opeDebit;
        $credit = (float) $balance->opeCredit;

        $localDebit  = $debit  * $rate;
        $localCredit = $credit * $rate;
        $netAmount   = $localDebit - $localCredit;

        if ($netAmount == 0) {
            return null;
        }

        return JournalEntryService::create([
            'docType'     => 'قيد افتتاحي',
            'docNumber'   => 'OB-' . $balance->openingBalancesID,
            'entryDate'   => $balance->opeData ?? now(),
            'description' => 'الأرصدة الافتتاحية - ' . ($balance->account->accName ?? ''),
            'lines'       => $lines,
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

    // ══════════════════════════════════════════════════════════
    //  Cache Helpers
    // ══════════════════════════════════════════════════════════

    public function clearCache(): void
    {
        self::forgetAllCache();

        $this->parentCache      = [];
        $this->descendantsCache = [];
    }

    /**
     * ✅ بناء مفتاح كاش يعتمد على version
     */
    private function buildListCacheKey(string $type, string $search, int $page, int $perPage): string
    {
        $version = (int) cache()->get("ob_list_version_{$type}", 1);

        return "ob_list_{$type}_v{$version}_" . md5("{$search}_{$page}_{$perPage}");
    }

    /**
     * ✅ إبطال كاش القوائم بزيادة version لكل نوع
     */
    private function clearListCache(): void
    {
        foreach (array_keys(self::SYSTEM_KEYS) as $type) {
            $current = (int) cache()->get("ob_list_version_{$type}", 1);

            cache()->put("ob_list_version_{$type}", $current + 1, self::VERSION_TTL);
        }
    }

    /**
     * ✅ مسح كل الكاش المرتبط بالأرصدة الافتتاحية
     */
    public static function forgetAllCache(): void
    {
        foreach (array_keys(self::SYSTEM_KEYS) as $type) {
            // إبطال كل نسخ القوائم
            $current = (int) cache()->get("ob_list_version_{$type}", 1);
            cache()->put("ob_list_version_{$type}", $current + 1, self::VERSION_TTL);

            // مسح كاش الأب
            cache()->forget("ob_parent_{$type}");
        }

        // مسح كاش الأبناء
        foreach (['cash', 'banks', 'bank', 'customers', 'customer',
                  'suppliers', 'supplier', 'inventory', 'stock'] as $key) {

            $parent = CharAccount::whereRaw(
                'LOWER(system_key) = ?', [strtolower($key)]
            )->value('accountID');

            if ($parent) {
                cache()->forget("ob_desc_{$parent}");
            }
        }
    }

    // ══════════════════════════════════════════════════════════
    //  Currency Helpers
    // ══════════════════════════════════════════════════════════

    /**
     * ✅ العملة النظامية أولاً
     */
    private function getActiveCurrencies(): array
    {
        return Coin::where('is_active', 1)
            ->orderByDesc('coinsSystem')    // العملة النظامية أولاً
            ->orderBy('coinsName')
            ->get(['coinsID', 'coinsName', 'coinsCode', 'coinsExchangeRate', 'coinsSystem'])
            ->all();
    }
}