<?php

namespace App\Http\Controllers\Operation\ReceiptVoucher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\ReceiptVoucher\StoreReceiptVoucherRequest;
use App\Http\Requests\Operation\ReceiptVoucher\UpdateReceiptVoucherRequest;
use App\Services\AccountBalanceService;
use App\Services\ReceiptVoucherService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReceiptVoucherApiController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // NUMBER
    // ═══════════════════════════════════════════════════════════

    public function nextNumber(): JsonResponse
    {
        Permissions::authorize(
            config('permissions.receipt_vouchers.view')
        );

        try {
            return $this->ok([
                'nextNumber' =>
                    ReceiptVoucherService::generateNextVoucherNumber(),
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // LIST
    // ═══════════════════════════════════════════════════════════

    public function list(Request $request): JsonResponse
    {
        Permissions::authorize(
            config('permissions.receipt_vouchers.view')
        );

        try {
            $search = trim(
                (string) $request->input('search', '')
            );

            $vouchers =
                ReceiptVoucherService::getList($search);

            $rows = $vouchers->map(
                fn ($voucher) => [
                    'id' =>
                        $voucher->receiptID,

                    'voucherNumber' =>
                        $voucher->voucherNumber,

                    'voucherDate' =>
                        $voucher->voucherDate?->format('Y-m-d'),

                    'creditAccountID' =>
                        $voucher->creditAccountID,

                    'creditAccountCode' =>
                        $voucher->creditAccount->accCode ?? '',

                    'creditAccountName' =>
                        $voucher->creditAccount->accName ?? '',

                    'debitAccountID' =>
                        $voucher->debitAccountID,

                    'debitAccountCode' =>
                        $voucher->debitAccount->accCode ?? '',

                    'debitAccountName' =>
                        $voucher->debitAccount->accName ?? '',

                    'entryID' =>
                        $voucher->entryID,

                    'amount' =>
                        (float) $voucher->amount,

                    'currencyID' =>
                        $voucher->coinsID,

                    'currencyCode' =>
                        $voucher->currency->coinsCode ?? '',

                    'localAmount' =>
                        (float) $voucher->localAmount,

                    'paymentMethod' =>
                        (int) $voucher->paymentMethod,

                    'notes' =>
                        $voucher->notes,

                    // ⭐ حالة الترحيل
                    'isPosted' =>
                        (bool) $voucher->is_posted,

                    'postedAt' =>
                        optional($voucher->posted_at)
                            ->format('Y-m-d H:i'),

                    'postedBy' =>
                        $voucher->posted_by,
                ]
            );

            return $this->ok(['rows' => $rows]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // SHOW
    // ═══════════════════════════════════════════════════════════

    public function show(int $id): JsonResponse
    {
        Permissions::authorize(
            config('permissions.receipt_vouchers.view')
        );

        try {
            $voucher =
                ReceiptVoucherService::findForShow($id);

            if (! $voucher) {
                return $this->fail('السند غير موجود.', 404);
            }

            /*
             * رصيد الحساب الدائن قبل وبعد القيد
             */
            $creditBalance =
                AccountBalanceService::getBalanceAroundEntry(
                    (int) $voucher->creditAccountID,
                    $voucher->entry?->entryNo
                );

            /*
             * الرصيد الحالي للحساب المدين (الصندوق/البنك)
             */
            $debitBalance =
                AccountBalanceService::getBalance(
                    (int) $voucher->debitAccountID
                );

            return $this->ok([
                'voucher' => [
                    'id' =>
                        $voucher->receiptID,

                    'voucherNumber' =>
                        $voucher->voucherNumber,

                    'voucherDate' =>
                        $voucher->voucherDate?->format('Y-m-d'),

                    // الحساب الدائن
                    'creditAccountID' =>
                        $voucher->creditAccountID,

                    'creditAccountCode' =>
                        $voucher->creditAccount->accCode ?? '',

                    'creditAccountName' =>
                        $voucher->creditAccount->accName ?? '',

                    'creditNature' =>
                        (int) ($creditBalance['nature'] ?? 0),

                    'creditBalanceBefore' =>
                        $creditBalance['before'] ?? 0,

                    'creditBalanceAfter' =>
                        $creditBalance['after'] ?? 0,

                    // الحساب المدين
                    'debitAccountID' =>
                        $voucher->debitAccountID,

                    'debitAccountCode' =>
                        $voucher->debitAccount->accCode ?? '',

                    'debitAccountName' =>
                        $voucher->debitAccount->accName ?? '',

                    'debitBalance' =>
                        $debitBalance,

                    // القيد
                    'entryID' =>
                        $voucher->entryID,

                    // المبلغ
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

                    // طريقة الدفع
                    'paymentMethod' =>
                        (int) $voucher->paymentMethod,

                    'chequeNumber' =>
                        $voucher->chequeNumber,

                    // نصوص
                    'notes' =>
                        $voucher->notes,

                    // الترحيل
                    'isPosted' =>
                        (bool) $voucher->is_posted,

                    'postedAt' =>
                        optional($voucher->posted_at)
                            ->format('Y-m-d H:i'),

                    'postedBy' =>
                        $voucher->posted_by,

                    // التتبع
                    'createdBy' =>
                        $voucher->created_by,

                    'updatedBy' =>
                        $voucher->updated_by,
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // STORE
    // ═══════════════════════════════════════════════════════════

    public function store(
        StoreReceiptVoucherRequest $request
    ): JsonResponse {
        Permissions::authorize(
            config('permissions.receipt_vouchers.create')
        );

        try {
            $data = $request->validated();

            $data['voucherDate'] =
                $data['voucherDate'] ?? now()->toDateString();

            $voucher =
                ReceiptVoucherService::create($data);

            return $this->ok([
                'message' =>
                    'تم حفظ السند بنجاح',

                'voucherID' =>
                    $voucher->receiptID,

                'voucherNumber' =>
                    $voucher->voucherNumber,

                'entryID' =>
                    $voucher->entryID,

                'isPosted' =>
                    (bool) $voucher->is_posted,
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════

    public function update(
        UpdateReceiptVoucherRequest $request,
        int $id
    ): JsonResponse {
        Permissions::authorize(
            config('permissions.receipt_vouchers.update')
        );

        try {
            $data = $request->validated();

            $data['voucherDate'] =
                $data['voucherDate'] ?? now()->toDateString();

            $voucher =
                ReceiptVoucherService::update($id, $data);

            return $this->ok([
                'message' =>
                    'تم تعديل السند بنجاح',

                'voucherID' =>
                    $voucher->receiptID,

                'voucherNumber' =>
                    $voucher->voucherNumber,

                'entryID' =>
                    $voucher->entryID,

                'isPosted' =>
                    (bool) $voucher->is_posted,
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════

    public function destroy(int $id): JsonResponse
    {
        Permissions::authorize(
            config('permissions.receipt_vouchers.delete')
        );

        try {
            ReceiptVoucherService::delete($id);

            return $this->ok([
                'message' => 'تم حذف السند بنجاح',
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // CURRENCIES
    // ═══════════════════════════════════════════════════════════

    public function currencies(): JsonResponse
    {
        Permissions::authorize(
            config('permissions.receipt_vouchers.view')
        );

        try {
            $currencies =
                ReceiptVoucherService::getCurrencies();

            return $this->ok([
                'rows' => $currencies->map(
                    fn ($currency) => [
                        'id' =>
                            $currency->coinsID,

                        'name' =>
                            $currency->coinsName,

                        'code' =>
                            $currency->coinsCode,

                        'exchangeRate' =>
                            (float) $currency->coinsExchangeRate,
                    ]
                ),
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PICKER
    // ═══════════════════════════════════════════════════════════

    public function picker(Request $request): JsonResponse
    {
        Permissions::authorize(
            config('permissions.receipt_vouchers.view')
        );

        try {
            $type = $request->input('type', 'customer');

            $search = trim(
                (string) $request->input('search', '')
            );

            $coinsID = $request->input('coinsID');

            $result =
                ReceiptVoucherService::getPickerData(
                    $type,
                    $search,
                    $coinsID
                );

            if (isset($result['error']) && $result['error']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ]);
            }

            return response()->json([
                'success' => true,
                'type'    => $result['type'],
                'rows'    => $result['rows'],
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ═══════════════════════════════════════════════════════════
// POST — ترحيل
// ═══════════════════════════════════════════════════════════

public function post(int $id): JsonResponse
{
    Permissions::authorize(
        config('permissions.receipt_vouchers.post')
    );

    try {
        $voucher = ReceiptVoucherService::post($id);

        return $this->ok([
            'message'  => 'تم ترحيل السند بنجاح',
            'entryID'  => $voucher->entryID,
            'isPosted' => (bool) $voucher->is_posted,
        ]);

    } catch (\Throwable $e) {
        return $this->handleException($e);
    }
}

// ═══════════════════════════════════════════════════════════
// UNPOST — إلغاء الترحيل
// ═══════════════════════════════════════════════════════════

public function unpost(int $id): JsonResponse
{
    Permissions::authorize(
        config('permissions.receipt_vouchers.unpost')
    );

    try {
        $voucher = ReceiptVoucherService::unpost($id);

        return $this->ok([
            'message'  => 'تم إلغاء ترحيل السند بنجاح',
            'isPosted' => (bool) $voucher->is_posted,
        ]);

    } catch (\Throwable $e) {
        return $this->handleException($e);
    }
}

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    private function ok(
        array $data = [],
        int $status = 200
    ): JsonResponse {
        return response()->json(
            array_merge(['success' => true], $data),
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

    /**
     * معالج استثناءات موحّد بـ 4 طبقات.
     */
    private function handleException(\Throwable $e): JsonResponse
    {
        // 1) 404
        if ($e instanceof ModelNotFoundException) {
            return $this->fail('السجل غير موجود.', 404);
        }

        // 2) 422 — أخطاء التحقق
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }

        // 3) 422 — أخطاء منطق الأعمال
        if ($e instanceof \RuntimeException) {
            return $this->fail($e->getMessage(), 422);
        }

        // 4) 500 — أخطاء غير متوقعة
        \Log::error(
            'ReceiptVoucherApiController unexpected error.',
            [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]
        );

        return $this->fail(
            'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.',
            500
        );
    }
}