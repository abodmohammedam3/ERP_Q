<?php

namespace App\Http\Controllers\Operation\PaymentVoucher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\PaymentVoucher\StorePaymentVoucherRequest;
use App\Http\Requests\Operation\PaymentVoucher\UpdatePaymentVoucherRequest;
use App\Models\Accounting\PaymentVoucher;
use App\Services\AccountBalanceService;
use App\Services\PaymentVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentVoucherController extends Controller
{
    public function index()
    {
        return view(
            'operation.accounting.paymentVouchers.index'
        );
    }

    public function nextNumber(): JsonResponse
    {
        return $this->ok([
            'nextNumber' =>
                PaymentVoucherService::generateNextVoucherNumber(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $search = trim(
            (string) $request->input('search', '')
        );

        $vouchers =
            PaymentVoucherService::getList(
                $search
            );

        $rows = $vouchers->map(
            fn ($voucher) => [
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

                'paymentAccountID' =>
                    $voucher->paymentAccountID,

                'paymentAccountCode' =>
                    $voucher->paymentAccount->accCode ?? '',

                'paymentAccountName' =>
                    $voucher->paymentAccount->accName ?? '',

                'amount' =>
                    (float) $voucher->amount,

                'currencyID' =>
                    $voucher->coinsID,

                'currencyCode' =>
                    $voucher->currency->coinsCode ?? '',

                'localAmount' =>
                    (float) $voucher->localAmount,

                'paymentMethod' =>
                    $voucher->paymentMethod,

                'notes' =>
                    $voucher->notes,
            ]
        );

        return $this->ok([
            'rows' => $rows,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $voucher =
            PaymentVoucherService::findForShow(
                $id
            );

        if (!$voucher) {
            return $this->fail(
                'السند غير موجود.',
                404
            );
        }

        $beneficiaryBalance =
            AccountBalanceService::getBalanceAroundEntry(
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
                        $beneficiaryBalance['nature'] ?? 0
                    ),

                'beneficiaryBalanceBefore' =>
                    $beneficiaryBalance['before'] ?? 0,

                'beneficiaryBalanceAfter' =>
                    $beneficiaryBalance['after'] ?? 0,

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

    public function store(
        StorePaymentVoucherRequest $request
    ): JsonResponse {

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
            $request->input('voucherNumber') ?: null;

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
    }

    public function update(
        UpdatePaymentVoucherRequest $request,
        int $id
    ): JsonResponse {

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
    }

    public function destroy(
        int $id
    ): JsonResponse {

        $deleted =
            PaymentVoucherService::delete(
                $id
            );

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

    public function currencies(): JsonResponse
    {
        $currencies =
            PaymentVoucherService::getCurrencies();

        return $this->ok([
            'rows' =>
                $currencies->map(
                    fn ($currency) => [
                        'id' =>
                            $currency->coinsID,

                        'name' =>
                            $currency->coinsName,

                        'code' =>
                            $currency->coinsCode,

                        'exchangeRate' =>
                            (float)
                            $currency->coinsExchangeRate,
                    ]
                ),
        ]);
    }

    public function picker(
        Request $request
    ): JsonResponse {

        $type =
            $request->input(
                'type',
                'supplier'
            );

        $search =
            trim(
                (string) $request->input(
                    'search',
                    ''
                )
            );

        $coinsID =
            $request->input('coinsID');

        if ($type === 'other') {
            $type = 'expense';
        }

        $result =
            PaymentVoucherService::getPickerData(
                $type,
                $search,
                $coinsID
            );

        if (
            isset($result['error'])
            && $result['error']
        ) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'success' => true,
            'type' =>
                $result['type'],
            'rows' =>
                $result['rows'],
        ]);
    }

    public function printView(int $id)
    {
        $voucher =
            PaymentVoucherService::findForPrint(
                $id
            );

        if (!$voucher) {
            abort(404);
        }

        $paymentMethodText = [
            'cash' => 'نقد',
            'bank' => 'تحويل بنكي',
        ][$voucher->paymentMethod] ?? '—';

        $formattedDate =
            $voucher->voucherDate
                ? $voucher->voucherDate
                    ->locale('ar')
                    ->translatedFormat('d F Y')
                : '—';

        $printTime =
            now()
                ->locale('ar')
                ->translatedFormat('d/m/Y H:i');

        $companyName =
            config(
                'app.company_name',
                'نظام ERP'
            );

        $currencyName =
            $voucher->currency->coinsName ?? '';

        $amountWords =
            \App\Helpers\Tafqeet::numberToWords(
                (float) $voucher->amount,
                $currencyName
            );

        $balanceInfo =
            AccountBalanceService::getBalanceAroundEntry(
                (int) $voucher->beneficiaryAccountID,
                $voucher->entry?->entryNo
            );

        $systemCurrencyCode =
            PaymentVoucherService::getSystemCurrencyCode();

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
                    $balanceInfo['before'] ?? 0,

                'balanceAfter' =>
                    $balanceInfo['after'] ?? 0,

                'systemCurrencyCode' =>
                    $systemCurrencyCode,
            ]
        );
    }

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
}