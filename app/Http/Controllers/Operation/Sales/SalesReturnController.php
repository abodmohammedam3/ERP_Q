<?php

namespace App\Http\Controllers\Operation\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesReturn;
use App\Support\Permissions;

class SalesReturnController extends Controller
{
    /**
     * عرض شاشة مرتجعات البيع
     */
    public function index()
    {
        Permissions::authorize(config('permissions.sales_returns.view'));

        return view('operation.sales.returns.index');
    }

    /**
     * طباعة مرتجع البيع
     */
    public function print($id)
    {
        Permissions::authorize(config('permissions.sales_returns.print'));

        $salesReturn = SalesReturn::with([
            'details.item',
            'details.type',
            'details.unit',
            'customerAccount',
            'coin',
            'originalInvoice',
        ])->find($id);

        if (!$salesReturn) {
            abort(404, 'مرتجع البيع غير موجود');
        }

        return view('print.sales-return', compact('salesReturn'));
    }
}