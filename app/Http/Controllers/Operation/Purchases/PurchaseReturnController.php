<?php

namespace App\Http\Controllers\Operation\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Coin;
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

        /* ✅ يحتاجها purchase_return.js لتحديد عملة النظام (سعر صرف مقروء = 1) */
        $systemCurrency = Coin::where('coinsSystem', 1)->first();

        return view('operation.purchases.returns.index', [
            'systemCurrencyId'   => $systemCurrency->coinsID ?? null,
            'systemCurrencyCode' => $systemCurrency->coinsCode ?? '',
        ]);
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