<?php

namespace App\Http\Controllers\Operation\ReceiptVoucher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\ReceiptVoucher\StoreReceiptVoucherRequest;
use App\Http\Requests\Operation\ReceiptVoucher\UpdateReceiptVoucherRequest;
use App\Models\Accounting\ReceiptVoucher;
use App\Services\AccountBalanceService;
use App\Services\ReceiptVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptVoucherController extends Controller
{
    public function index()
    {
        return view(
            'operation.accounting.receiptVouchers.index'
        );
    }

    public function nextNumber(): JsonResponse
    {
        return $this->ok([
            'nextNumber' =>
                ReceiptVoucherService::generateNextVoucherNumber(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $search = trim(
            (string) $request->input('search', '')
        );

        $vouchers =
            ReceiptVoucherService::getList(
                $search
            );

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
            ReceiptVoucherService::findForShow(
                $id
            );

        if (!$voucher) {
            return $this->fail(
                'السند غير موجود.',
                404
            );
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
         * الرصيد الحالي للحساب المدين
         * وهو الصندوق أو البنك
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

                /*
                 * الحساب الدائن
                 */
                'creditAccountID' =>
                    $voucher->creditAccountID,

                'creditAccountCode' =>
                    $voucher->creditAccount->accCode ?? '',

                'creditAccountName' =>
                    $voucher->creditAccount->accName ?? '',

                'creditNature' =>
                    (int) (
                        $creditBalance['nature'] ?? 0
                    ),

                'creditBalanceBefore' =>
                    $creditBalance['before'] ?? 0,

                'creditBalanceAfter' =>
                    $creditBalance['after'] ?? 0,

                /*
                 * الحساب المدين
                 */
                'debitAccountID' =>
                    $voucher->debitAccountID,

                'debitAccountCode' =>
                    $voucher->debitAccount->accCode ?? '',

                'debitAccountName' =>
                    $voucher->debitAccount->accName ?? '',

                'debitBalance' =>
                    $debitBalance,

                /*
                 * القيد
                 */
                'entryID' =>
                    $voucher->entryID,

                /*
                 * المبلغ
                 */
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
        StoreReceiptVoucherRequest $request
    ): JsonResponse {

        $data = $request->only([
            'creditAccountID',
            'debitAccountID',
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
            ReceiptVoucherService::create(
                $data
            );

        if (!$voucher) {
            return $this->fail(
                'فشل حفظ السند. يرجى المحاولة مرة أخرى.'
            );
        }

        return $this->ok([
            'message' =>
                'تم حفظ السند بنجاح',

            'voucherID' =>
                $voucher->receiptID,

            'voucherNumber' =>
                $voucher->voucherNumber,

            'entryID' =>
                $voucher->entryID,
        ]);
    }

    public function update(
        UpdateReceiptVoucherRequest $request,
        int $id
    ): JsonResponse {

        $data = $request->only([
            'creditAccountID',
            'debitAccountID',
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
            ReceiptVoucherService::update(
                $id,
                $data
            );

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

            'entryID' =>
                $voucher->entryID,
        ]);
    }

    public function destroy(
        int $id
    ): JsonResponse {

        $deleted =
            ReceiptVoucherService::delete(
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
            ReceiptVoucherService::getCurrencies();

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
                'customer'
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

        $result =
            ReceiptVoucherService::getPickerData(
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
                'message' =>
                    $result['message'],
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
            ReceiptVoucherService::findForPrint(
                $id
            );

        if (!$voucher) {
            abort(404);
        }

        $paymentMethodText = [
            'cash' =>
                'نقد',

            'bank' =>
                'تحويل بنكي',

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
                (int) $voucher->creditAccountID,
                $voucher->entry?->entryNo
            );

        $systemCurrencyCode =
            ReceiptVoucherService::getSystemCurrencyCode();

        return view(
            'operation.accounting.receiptVouchers.print',
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