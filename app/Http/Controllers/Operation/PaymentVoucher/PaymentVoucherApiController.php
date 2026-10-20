<?php

namespace App\Http\Controllers\Operation\PaymentVoucher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\PaymentVoucher\StorePaymentVoucherRequest;
use App\Http\Requests\Operation\PaymentVoucher\UpdatePaymentVoucherRequest;
use App\Http\Resources\Accounting\PaymentVoucherResource;
use App\Services\AccountBalanceService;
use App\Services\PaymentVoucherService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentVoucherApiController extends Controller
{
    // =========================================================
    // HELPERS
    // =========================================================

    private function ok(array $data = [], ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => [],
        ]);
    }

    private function fail(
        string $message,
        int $code = 422,
        array $errors = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $code);
    }

    private function handleException(\Throwable $e): JsonResponse
    {
        if ($e instanceof ModelNotFoundException) {
            return $this->fail('السند غير موجود.', 404);
        }

        if ($e instanceof ValidationException) {
            return $this->fail($e->getMessage(), 422, $e->errors());
        }

        if ($e instanceof \RuntimeException) {
            return $this->fail($e->getMessage(), 422);
        }

        Log::error('[PaymentVoucherApi] Unhandled exception', [
            'error'   => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'user_id' => auth()->id(),
            'url'     => request()->fullUrl(),
        ]);

        return $this->fail('حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.', 500);
    }

    // =========================================================
    // NEXT NUMBER
    // =========================================================

    public function nextNumber(): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.create'));

        try {
            $number = PaymentVoucherService::generateNextVoucherNumber();

            return $this->ok([
                'nextNumber'    => $number,
                'voucherNumber' => $number,
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // =========================================================
    // LIST
    // =========================================================

    public function list(Request $request): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.view'));

        try {
            $search  = trim((string) $request->query('search', ''));
            $perPage = min(max((int) $request->query('per_page', 50), 10), 100);

            $paginator = PaymentVoucherService::getListPaginated($search, $perPage);

            return $this->ok([
                'rows' => PaymentVoucherResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'per_page'    => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more'    => $paginator->hasMorePages(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // =========================================================
    // SHOW
    // =========================================================

    public function show(int $id): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.view'));

        try {
            $voucher = PaymentVoucherService::findForShow($id);

            $entryNo = $voucher->entry?->entryNo;

            $paymentBalance = $voucher->paymentAccountID
                ? AccountBalanceService::getBalanceAroundEntry(
                    (int) $voucher->paymentAccountID,
                    $entryNo
                )
                : null;

            $beneficiaryBalance = $voucher->beneficiaryAccountID
                ? AccountBalanceService::getBalanceAroundEntry(
                    (int) $voucher->beneficiaryAccountID,
                    $entryNo
                )
                : null;

            return $this->ok([
                'voucher' => (new PaymentVoucherResource($voucher))->resolve(),
                'balance' => [
                    'payment'     => $paymentBalance,
                    'beneficiary' => $beneficiaryBalance,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // =========================================================
    // STORE
    // =========================================================

    public function store(StorePaymentVoucherRequest $request): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.create'));

        try {
            $voucher = PaymentVoucherService::create($request->validated());

            return $this->ok([
                'voucher'       => (new PaymentVoucherResource($voucher))->resolve(),
                'voucherID'     => (int) $voucher->paymentID,
                'voucherNumber' => (string) $voucher->voucherNumber,
                'isPosted'      => $voucher->isPosted(),
                'entryID'       => $voucher->entryID,
            ], 'تم حفظ وترحيل السند بنجاح.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // =========================================================
    // UPDATE
    // =========================================================

    public function update(
        UpdatePaymentVoucherRequest $request,
        int $id
    ): JsonResponse {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.edit'));

        try {
            $voucher = PaymentVoucherService::update($id, $request->validated());

            return $this->ok([
                'voucher'       => (new PaymentVoucherResource($voucher))->resolve(),
                'voucherID'     => (int) $voucher->paymentID,
                'voucherNumber' => (string) $voucher->voucherNumber,
                'isPosted'      => $voucher->isPosted(),
            ], 'تم تعديل السند. يبقى مسودة حتى الترحيل.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // =========================================================
    // DESTROY
    // =========================================================

    public function destroy(int $id): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.delete'));

        try {
            PaymentVoucherService::delete($id);

            return $this->ok([], 'تم حذف السند بنجاح.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // =========================================================
    // POST / UNPOST
    // =========================================================

    public function post(int $id): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.post'));

        try {
            $voucher = PaymentVoucherService::post($id);

            return $this->ok([
                'entryID'  => $voucher->entryID,
                'isPosted' => $voucher->isPosted(),
            ], 'تم ترحيل السند بنجاح.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function unpost(int $id): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.post'));

        try {
            $voucher = PaymentVoucherService::unpost($id);

            return $this->ok([
                'isPosted' => $voucher->isPosted(),
            ], 'تم إلغاء ترحيل السند.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // =========================================================
    // CURRENCIES
    // =========================================================

    public function currencies(): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.view'));

        try {
            $currencies = PaymentVoucherService::getCurrencies()
                ->map(fn ($c) => [
                    'id'           => (int) $c->coinsID,
                    'code'         => (string) $c->coinsCode,
                    'name'         => (string) $c->coinsName,
                    'exchangeRate' => (float) ($c->coinsExchangeRate ?? 1),
                    'isSystem'     => (int) ($c->coinsSystem ?? 0),
                ])
                ->values()
                ->all();

            return $this->ok([
                'currencies'     => $currencies,
                'rows'           => $currencies,
                'systemCurrency' => PaymentVoucherService::getSystemCurrencyCode(),
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // =========================================================
    // PICKER
    // =========================================================

    public function picker(Request $request): JsonResponse
    {
        // TODO: Re-enable Permissions::authorize(config('permissions.payment_vouchers.view'));

        try {
            $type = (string) $request->query('type', 'supplier');

            $valid = ['supplier', 'customer', 'cash', 'bank', 'expense'];
            if (! in_array($type, $valid, true)) {
                throw new \RuntimeException("نوع picker غير معروف: {$type}");
            }

            $search  = (string) $request->query('search', '');
            $coinsID = $request->query('coinsID');

            $result = PaymentVoucherService::getPickerData(
                $type,
                $search,
                $coinsID ? (int) $coinsID : null
            );

            return response()->json([
                'success' => true,
                'error'   => $result['error'] ?? false,
                'type'    => $type,
                'rows'    => $result['rows'] ?? [],
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}