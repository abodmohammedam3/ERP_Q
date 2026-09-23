<?php

namespace App\Http\Controllers\Operation\PaymentVoucher;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PaymentVoucher;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Box;
use App\Models\Accounting\Bank;
use App\Models\Supplier;
use App\Services\PaymentVoucherService;
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
            ->orderBy('coinsName')
            ->get(['coinsID', 'coinsName', 'coinsCode', 'coinsExchangeRate']);

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
    //  اختيار الحسابات (Picker) - النشطة فقط
    //  ⭐ الأنواع: supplier | cash | bank
    // ══════════════════════════════════════════════════════════

    public function picker(Request $request): JsonResponse
    {
        session()->save();

        $type   = $request->input('type', 'supplier');
        $search = trim($request->input('search', ''));

        $parentKey = match ($type) {
            'supplier' => 'suppliers',
            'cash'     => 'cash',
            'bank'     => 'banks',
            default    => 'suppliers',
        };

        $parentId = CharAccount::whereRaw('LOWER(system_key) = ?', [strtolower($parentKey)])->value('accountID');
        if (!$parentId) {
            $parentId = CharAccount::whereRaw('LOWER(system_key) LIKE ?', ['%' . strtolower($parentKey) . '%'])->value('accountID');
        }

        if (!$parentId) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على الحساب الأب']);
        }

        $ids = $this->getDescendantIds($parentId);
        if (!empty($ids)) {
            $ids = CharAccount::whereIn('accountID', $ids)->whereNotNull('accParent')->pluck('accountID')->all();
        }

        $query = CharAccount::whereIn('accountID', $ids)->where('IsActive', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('accCode', 'like', "%{$search}%")->orWhere('accName', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('accCode')->limit(50)->get(['accountID', 'accCode', 'accName']);

        $rows = [];
        foreach ($accounts as $acc) {
            $row = ['id' => $acc->accountID, 'code' => $acc->accCode, 'name' => $acc->accName, 'extra' => '—'];

            // ⭐ المورد (نشط فقط)
            if ($type === 'supplier') {
                $supplier = Supplier::where('accountID', $acc->accountID)
                    ->where('supStoped', 0)
                    ->first();

                if (!$supplier) continue;

                $row['name']  = $supplier->supName ?? $acc->accName;
                $row['extra'] = !empty($supplier->supPhone) ? $supplier->supPhone : '—';
            }
            // ⭐ الصندوق (نشط فقط)
            elseif ($type === 'cash') {
                $box = Box::where('accountID', $acc->accountID)
                    ->where('is_active', 1)
                    ->with('coin')
                    ->first();

                if (!$box) continue;

                $row['name']  = $box->boxName ?? $acc->accName;
                $row['extra'] = $box->coin->coinsCode ?? '—';
            }
            // ⭐ البنك (نشط فقط)
            elseif ($type === 'bank') {
                $bank = Bank::where('accountID', $acc->accountID)
                    ->where('is_active', 1)
                    ->with('coin')
                    ->first();

                if (!$bank) continue;

                $row['name']  = $bank->bankName ?? $acc->accName;
                $row['extra'] = $bank->coin->coinsCode ?? '—';
            }

            $rows[] = $row;
        }

        return response()->json(['success' => true, 'type' => $type, 'rows' => $rows]);
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
     * ⭐ عرض سند الصرف للطباعة
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

        $amountWords = null;

        return view('operation.accounting.paymentVouchers.print', [
            'voucher'           => $voucher,
            'paymentMethodText' => $paymentMethodText,
            'formattedDate'     => $formattedDate,
            'printTime'         => $printTime,
            'companyName'       => $companyName,
            'amountWords'       => $amountWords,
        ]);
    }
}