<?php

namespace App\Http\Controllers\Operation\ReceiptVoucher;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ReceiptVoucher;
use App\Services\AccountBalanceService;
use App\Services\ReceiptVoucherService;
use App\Support\Permissions;

class ReceiptVoucherController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // عرض الشاشة الرئيسية
    // ═══════════════════════════════════════════════════════════

    public function index()
    {
        Permissions::authorize(
            config('permissions.receipt_vouchers.view')
        );

        return view(
            'operation.accounting.receiptVouchers.index'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // طباعة السند
    // ═══════════════════════════════════════════════════════════

    public function printView(int $id)
    {
        Permissions::authorize(
            config('permissions.receipt_vouchers.print')
        );

        $voucher = ReceiptVoucherService::findForPrint($id);

        if (! $voucher) {
            abort(404, 'السند غير موجود.');
        }

        $paymentMethodLabels =
            ReceiptVoucher::paymentMethodLabels();

        $paymentMethodText =
            $paymentMethodLabels[(int) $voucher->paymentMethod] ?? '—';

        $formattedDate = $voucher->voucherDate
            ? $voucher->voucherDate->locale('ar')->translatedFormat('d F Y')
            : '—';

        $printTime = now()
            ->locale('ar')
            ->translatedFormat('d/m/Y H:i');

        $companyName = config('app.company_name', 'نظام ERP');

        $currencyName = $voucher->currency->coinsName ?? '';

        $amountWords = \App\Helpers\Tafqeet::numberToWords(
            (float) $voucher->amount,
            $currencyName
        );

        $balanceInfo = AccountBalanceService::getBalanceAroundEntry(
            (int) $voucher->creditAccountID,
            $voucher->entry?->entryNo
        );

        $systemCurrencyCode =
            ReceiptVoucherService::getSystemCurrencyCode();

        return view(
            'operation.accounting.receiptVouchers.print',
            [
                'voucher'            => $voucher,
                'paymentMethodText'  => $paymentMethodText,
                'formattedDate'      => $formattedDate,
                'printTime'          => $printTime,
                'companyName'        => $companyName,
                'amountWords'        => $amountWords,
                'balanceBefore'      => $balanceInfo['before'] ?? 0,
                'balanceAfter'       => $balanceInfo['after'] ?? 0,
                'systemCurrencyCode' => $systemCurrencyCode,
            ]
        );
    }
}