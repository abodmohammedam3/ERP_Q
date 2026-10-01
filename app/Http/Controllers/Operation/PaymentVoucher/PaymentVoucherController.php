<?php

namespace App\Http\Controllers\Operation\PaymentVoucher;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PaymentVoucher;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Box;
use App\Models\Accounting\Bank;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\PaymentVoucherService;
use App\Services\AccountBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentVoucherController extends Controller
{
    // ══════════════════════════════════════════════════════════
    //  عرض الشاشة
    // ══════════════════════════════════════════════════════════

    public function index()
    {
        session()->save();
        return view('operation.accounting.paymentVouchers.index');
    }

    // ══════════════════════════════════════════════════════════
    //  جلب رقم السند التالي
    // ══════════════════════════════════════════════════════════

    public function nextNumber(): JsonResponse
    {
        session()->save();

        $nextNumber = PaymentVoucherService::generateNextVoucherNumber();

        return $this->ok([
            'nextNumber' => $nextNumber,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  قائمة السندات (JSON)
    // ══════════════════════════════════════════════════════════

    public function list(Request $request): JsonResponse
    {
        session()->save();

        $search = trim($request->input('search', ''));

        $query = PaymentVoucher::with(['creditAccount', 'debitAccount', 'currency'])
            ->orderByDesc('paymentID');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('voucherNumber', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('creditAccount', fn($c) => $c
                      ->where('accName', 'like', "%{$search}%")
                      ->orWhere('accCode', 'like', "%{$search}%"))
                  ->orWhereHas('debitAccount', fn($c) => $c
                      ->where('accName', 'like', "%{$search}%")
                      ->orWhere('accCode', 'like', "%{$search}%"));
            });
        }

        $vouchers = $query->limit(50)->get();

        $rows = $vouchers->map(fn($v) => [
            'id'                => $v->paymentID,
            'voucherNumber'     => $v->voucherNumber,
            'voucherDate'       => $v->voucherDate?->format('Y-m-d'),
            'creditAccountID'   => $v->creditAccountID,
            'creditAccountCode' => $v->creditAccount->accCode ?? '',
            'creditAccountName' => $v->creditAccount->accName ?? '',
            'debitAccountID'    => $v->debitAccountID,
            'debitAccountCode'  => $v->debitAccount->accCode ?? '',
            'debitAccountName'  => $v->debitAccount->accName ?? '',
            'amount'            => (float) $v->amount,
            'currencyID'        => $v->coinsID,
            'currencyCode'      => $v->currency->coinsCode ?? '',
            'exchangeRate'      => (float) $v->exchangeRate,
            'localAmount'       => (float) $v->localAmount,
            'paymentMethod'     => $v->paymentMethod,
            'notes'             => $v->notes,
        ]);

        return $this->ok(['rows' => $rows]);
    }

    // ══════════════════════════════════════════════════════════
    //  عرض سند واحد
    // ══════════════════════════════════════════════════════════

    public function show(int $id): JsonResponse
    {
        session()->save();

        $voucher = PaymentVoucher::with(['creditAccount', 'debitAccount', 'currency'])
            ->findOrFail($id);

        return $this->ok([
            'voucher' => [
                'id'                => $voucher->paymentID,
                'voucherNumber'     => $voucher->voucherNumber,
                'voucherDate'       => $voucher->voucherDate?->format('Y-m-d'),
                'creditAccountID'   => $voucher->creditAccountID,
                'creditAccountCode' => $voucher->creditAccount->accCode ?? '',
                'creditAccountName' => $voucher->creditAccount->accName ?? '',
                'debitAccountID'    => $voucher->debitAccountID,
                'debitAccountCode'  => $voucher->debitAccount->accCode ?? '',
                'debitAccountName'  => $voucher->debitAccount->accName ?? '',
                'amount'            => (float) $voucher->amount,
                'currencyID'        => $voucher->coinsID,
                'currencyCode'      => $voucher->currency->coinsCode ?? '',
                'currencyName'      => $voucher->currency->coinsName ?? '',
                'exchangeRate'      => (float) $voucher->exchangeRate,
                'localAmount'       => (float) $voucher->localAmount,
                'paymentMethod'     => $voucher->paymentMethod,
                'notes'             => $voucher->notes,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  حفظ سند جديد
    // ══════════════════════════════════════════════════════════

    public function store(Request $request): JsonResponse
    {
        session()->save();

        $lock = Cache::lock('payment_store_lock', 10);
        if (!$lock->get()) {
            return $this->fail('يتم حفظ السند، يرجى الانتظار...');
        }

        try {
            $validator = Validator::make($request->all(), [
                'creditAccountID' => 'required|exists:characcount,accountID',
                'debitAccountID'  => 'required|exists:characcount,accountID',
                'coinsID'         => 'required|exists:coins,coinsID',
                'amount'          => 'required|numeric|min:0.01',
                'exchangeRate'    => 'required|numeric|gt:0',
                'paymentMethod'   => 'nullable|in:cash,bank',
                'notes'           => 'nullable|string',
                'voucherDate'     => 'nullable|date',
                'voucherNumber'   => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first());
            }

            if ($request->creditAccountID == $request->debitAccountID) {
                return $this->fail('لا يمكن أن يكون الحساب المدين هو نفس الحساب الدائن.');
            }

            $voucherNumber = $request->input('voucherNumber');

            if (!empty($voucherNumber)) {
                $exists = PaymentVoucher::where('voucherNumber', $voucherNumber)->exists();
                if ($exists) {
                    return $this->fail('رقم السند مستخدم بالفعل. يرجى تحديث الصفحة للحصول على رقم جديد.');
                }
            }

            $data = $request->only([
                'creditAccountID', 'debitAccountID', 'coinsID', 'amount',
                'exchangeRate', 'paymentMethod', 'notes',
            ]);

            $data['voucherNumber'] = $voucherNumber ?: null;
            $data['voucherDate']   = $request->input('voucherDate', now()->toDateString());

            $voucher = PaymentVoucherService::create($data);

            if (is_string($voucher)) {
                return $this->fail($voucher);
            }

            if (!$voucher) {
                return $this->fail('فشل حفظ السند. يرجى المحاولة مرة أخرى.');
            }

            return $this->ok([
                'message'       => 'تم حفظ السند بنجاح',
                'voucherID'     => $voucher->paymentID,
                'voucherNumber' => $voucher->voucherNumber,
            ]);

        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    //  تعديل سند
    // ══════════════════════════════════════════════════════════

    public function update(Request $request, int $id): JsonResponse
    {
        session()->save();

        $lock = Cache::lock('payment_update_lock_' . $id, 10);
        if (!$lock->get()) {
            return $this->fail('يتم تعديل السند، يرجى الانتظار...');
        }

        try {
            $validator = Validator::make($request->all(), [
                'creditAccountID' => 'required|exists:characcount,accountID',
                'debitAccountID'  => 'required|exists:characcount,accountID',
                'coinsID'         => 'required|exists:coins,coinsID',
                'amount'          => 'required|numeric|min:0.01',
                'exchangeRate'    => 'required|numeric|gt:0',
                'paymentMethod'   => 'nullable|in:cash,bank',
                'notes'           => 'nullable|string',
                'voucherDate'     => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first());
            }

            if ($request->creditAccountID == $request->debitAccountID) {
                return $this->fail('لا يمكن أن يكون الحساب المدين هو نفس الحساب الدائن.');
            }

            $data = $request->only([
                'creditAccountID', 'debitAccountID', 'coinsID', 'amount',
                'exchangeRate', 'paymentMethod', 'notes',
            ]);

            $data['voucherDate'] = $request->input('voucherDate', now()->toDateString());

            $voucher = PaymentVoucherService::update($id, $data);

            if (is_string($voucher)) {
                return $this->fail($voucher);
            }

            if (!$voucher) {
                return $this->fail('فشل تعديل السند.');
            }

            return $this->ok([
                'message'       => 'تم تعديل السند بنجاح',
                'voucherNumber' => $voucher->voucherNumber,
            ]);

        } finally {
            $lock->release();
        }
    }

    // ══════════════════════════════════════════════════════════
    //  حذف سند
    // ══════════════════════════════════════════════════════════

    public function destroy(int $id): JsonResponse
    {
        session()->save();

        $deleted = PaymentVoucherService::delete($id);

        if (!$deleted) {
            return $this->fail('فشل حذف السند.', 404);
        }

        return $this->ok(['message' => 'تم حذف السند بنجاح']);
    }

    // ══════════════════════════════════════════════════════════
    //  جلب قائمة العملات (النشطة فقط)
    // ══════════════════════════════════════════════════════════

    public function currencies(): JsonResponse
    {
        session()->save();

        $currencies = \App\Models\Accounting\Coin::where('is_active', 1)
            ->orderByDesc('coinsSystem')
            ->orderBy('coinsName')
            ->get(['coinsID', 'coinsName', 'coinsCode', 'coinsExchangeRate', 'coinsSystem']);

        return $this->ok([
            'rows' => $currencies->map(fn($c) => [
                'id'           => $c->coinsID,
                'name'         => $c->coinsName,
                'code'         => $c->coinsCode,
                'exchangeRate' => (float) $c->coinsExchangeRate,
            ]),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  اختيار الحسابات (Picker) — 5 أنواع
    // ══════════════════════════════════════════════════════════

    public function picker(Request $request): JsonResponse
    {
        session()->save();

        $type    = $request->input('type', 'supplier');
        $search  = trim($request->input('search', ''));
        $coinsID = $request->input('coinsID');

        // ─── الحسابات الأخرى (ليست تحت: صناديق/بنوك/عملاء/موردين/مخازن) ───
        if ($type === 'other') {
            return $this->pickerOtherAccounts($search);
        }

        $parentKey = match ($type) {
            'supplier' => 'suppliers',
            'customer' => 'customers',
            'cash'     => 'cash',
            'bank'     => 'banks',
            default    => 'suppliers',
        };

        $parentId = CharAccount::whereRaw('LOWER(system_key) = ?', [strtolower($parentKey)])
            ->value('accountID');

        if (!$parentId) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على الحساب الأب']);
        }

        $ids = $this->getDescendantIds($parentId);

        if (!empty($ids)) {
            $ids = CharAccount::whereIn('accountID', $ids)
                ->whereNotNull('accParent')
                ->pluck('accountID')
                ->all();
        }

        $query = CharAccount::whereIn('accountID', $ids)->where('IsActive', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('accCode', 'like', "%{$search}%")
                  ->orWhere('accName', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('accCode')->limit(50)->get(['accountID', 'accCode', 'accName']);

        $rows = [];

        foreach ($accounts as $acc) {

            $row = [
                'id'            => $acc->accountID,
                'code'          => $acc->accCode,
                'name'          => $acc->accName,
                'extra'         => '—',
                'balance'       => null,
                'currency_id'   => null,
                'currency_code' => null,
                'exchange_rate' => 1,
            ];

            // ─── مورد ───
            if ($type === 'supplier') {
                $entity = Supplier::where('accountID', $acc->accountID)
                    ->where('is_active', 1)
                    ->first();

                if (!$entity) continue;

                $row['name']    = $entity->supName ?? $acc->accName;
                $row['extra']   = !empty($entity->supPhone) ? $entity->supPhone : '—';
                $row['balance'] = AccountBalanceService::getBalance($acc->accountID);
            }

            // ─── عميل ───
            elseif ($type === 'customer') {
                $entity = Customer::where('accountID', $acc->accountID)
                    ->where('is_active', 1)
                    ->first();

                if (!$entity) continue;

                $row['name']    = $entity->CustomersName2 ?? $acc->accName;
                $row['extra']   = !empty($entity->CusPhone) ? $entity->CusPhone : '—';
                $row['balance'] = AccountBalanceService::getBalance($acc->accountID);
            }

            // ─── صندوق ───
            elseif ($type === 'cash') {
                $entity = Box::where('accountID', $acc->accountID)
                    ->where('is_active', 1)
                    ->with('coin')
                    ->first();

                if (!$entity) continue;

                if (!empty($coinsID) && (int) $entity->coinsID !== (int) $coinsID) {
                    continue;
                }

                $row['name']          = $entity->boxName ?? $acc->accName;
                $row['extra']         = $entity->coin->coinsCode ?? '—';
                $row['balance']       = AccountBalanceService::getBalance($acc->accountID);
                $row['currency_id']   = $entity->coinsID;
                $row['currency_code'] = $entity->coin->coinsCode ?? '';
                $row['exchange_rate'] = (float) ($entity->coin->coinsExchangeRate ?? 1);
            }

            // ─── بنك ───
            elseif ($type === 'bank') {
                $entity = Bank::where('accountID', $acc->accountID)
                    ->where('is_active', 1)
                    ->with('coin')
                    ->first();

                if (!$entity) continue;

                if (!empty($coinsID) && (int) $entity->coinsID !== (int) $coinsID) {
                    continue;
                }

                $row['name']          = $entity->bankName ?? $acc->accName;
                $row['extra']         = $entity->coin->coinsCode ?? '—';
                $row['balance']       = AccountBalanceService::getBalance($acc->accountID);
                $row['currency_id']   = $entity->coinsID;
                $row['currency_code'] = $entity->coin->coinsCode ?? '';
                $row['exchange_rate'] = (float) ($entity->coin->coinsExchangeRate ?? 1);
            }

            $rows[] = $row;
        }

        return response()->json(['success' => true, 'type' => $type, 'rows' => $rows]);
    }

    /**
     * الحسابات التحليلية الأخرى
     * ✅ لم نعد نستبعد is_system = 1
     */
    private function pickerOtherAccounts(string $search): JsonResponse
    {
      $systemKeys = [
            'cash',
            'banks', 'bank',
            'customers', 'customer',
            'suppliers', 'supplier',
            'inventory', 'stock',
            'ownerCapital',          // ⭐ رأس مال المالك
            'capital',               // احتياطي
            'openingBalance',        // احتياطي
      ];

        $excludeIds = [];

        foreach ($systemKeys as $key) {
            $parentId = CharAccount::whereRaw('LOWER(system_key) = ?', [strtolower($key)])
                ->value('accountID');

            if ($parentId) {
                $excludeIds = array_merge($excludeIds, $this->getDescendantIds($parentId));
            }
        }

        // ✅ استبعد فقط ما هو تحت الأنواع النظامية
        $query = CharAccount::where('isPostable', 1)
            ->where('IsActive', 1);
        // ⛔ حذف ->where('is_system', 0)

        if (!empty($excludeIds)) {
            $query->whereNotIn('accountID', $excludeIds);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('accCode', 'like', "%{$search}%")
                  ->orWhere('accName', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('accCode')->limit(50)->get(['accountID', 'accCode', 'accName']);

        $rows = $accounts->map(fn($acc) => [
            'id'      => $acc->accountID,
            'code'    => $acc->accCode,
            'name'    => $acc->accName,
            'extra'   => '—',
            'balance' => AccountBalanceService::getBalance($acc->accountID),
        ])->all();

        return response()->json(['success' => true, 'type' => 'other', 'rows' => $rows]);
    }

    private function getDescendantIds(int $parentId): array
    {
        $byParent = CharAccount::where('IsActive', 1)
            ->get(['accountID', 'accParent'])
            ->groupBy('accParent');

        $ids          = [];
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

    private function ok(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['success' => true], $data), $status);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /**
 * عرض سند الصرف للطباعة
 */
public function printView(int $id)
{
    session()->save();

    $voucher = PaymentVoucher::with([
        'creditAccount',
        'debitAccount',
        'currency',
    ])->findOrFail($id);

    $paymentMethodText = [
        'cash' => 'نقد',
        'bank' => 'تحويل بنكي',
    ][$voucher->paymentMethod] ?? '—';

    $formattedDate = $voucher->voucherDate
        ? $voucher->voucherDate->locale('ar')->translatedFormat('d F Y')
        : '—';

    $printTime = now()->locale('ar')->translatedFormat('d/m/Y H:i');

    $companyName = config('app.company_name', 'نظام ERP');

    // ⭐ تحويل المبلغ إلى كلمات — Tafqeet
    $currencyName = $voucher->currency->coinsName ?? '';
    $amountWords  = \App\Helpers\Tafqeet::numberToWords(
        (float) $voucher->amount,
        $currencyName
    );

    // ⭐ حساب الرصيد قبل / بعد العملية للمورد
    $creditAccountID = (int) $voucher->creditAccountID;
    $localAmount     = (float) $voucher->localAmount;

    $balanceAfter  = AccountBalanceService::getBalance($creditAccountID);
    $balanceBefore = $balanceAfter + $localAmount;

    $systemCurrency = \App\Models\Accounting\Coin::where('coinsSystem', 1)
        ->first(['coinsCode']);

    return view('operation.accounting.paymentVouchers.print', [
        'voucher'            => $voucher,
        'paymentMethodText'  => $paymentMethodText,
        'formattedDate'      => $formattedDate,
        'printTime'          => $printTime,
        'companyName'        => $companyName,
        'amountWords'        => $amountWords,
        'balanceBefore'      => $balanceBefore,
        'balanceAfter'       => $balanceAfter,
        'systemCurrencyCode' => $systemCurrency->coinsCode ?? '',
    ]);
}
}