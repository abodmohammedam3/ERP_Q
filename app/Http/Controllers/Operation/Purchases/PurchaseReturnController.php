<?php

namespace App\Http\Controllers\Operation\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchases\PurchaseReturn;
use App\Support\Permissions;

class PurchaseReturnController extends Controller
{
    /**
     * عرض شاشة مرتجعات الشراء
     */
    public function index()
    {
        Permissions::authorize(config('permissions.purchase_returns.view'));

        return view('operation.purchases.returns.index');
    }

    /**
     * طباعة مرتجع الشراء
     */
    public function print($id)
    {
        Permissions::authorize(config('permissions.purchase_returns.print'));

        $purchaseReturn = PurchaseReturn::with([
            'details.item',
            'details.type',
            'details.unit',
            'supplierAccount',
            'coin',
            'warehouse',
            'originalInvoice',
        ])->find($id);

        if (!$purchaseReturn) {
            abort(404, 'مرتجع الشراء غير موجود');
        }

        return view('print.purchase-return', compact('purchaseReturn'));
    }
}