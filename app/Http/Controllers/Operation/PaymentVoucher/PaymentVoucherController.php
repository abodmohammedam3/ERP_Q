<?php

namespace App\Http\Controllers\Operation\PaymentVoucher;

use App\Http\Controllers\Controller;
use App\Services\PaymentVoucherService;
use App\Support\Permissions;

class PaymentVoucherController extends Controller
{
    /**
     * صفحة العرض الرئيسية لسندات الصرف.
     */
    public function index()
    {
        Permissions::authorize(
            config('permissions.payment_vouchers.view')
        );

        return view('operation.accounting.paymentVouchers.index');
    }

    /**
     * صفحة الطباعة لسند واحد.
     *
     * يُمرّر كل البيانات المطلوبة (لا undefined variables).
     * ModelNotFoundException تُعالَج تلقائياً بـ 404 من Exception Handler.
     */
    public function printView(int $id)
    {
        Permissions::authorize(
            config('permissions.payment_vouchers.print')
        );

        $data = PaymentVoucherService::buildPrintData($id);

        return view(
            'operation.accounting.paymentVouchers.print',
            $data
        );
    }
}