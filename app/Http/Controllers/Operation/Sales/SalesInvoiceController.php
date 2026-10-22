<?php

namespace App\Http\Controllers\Operation\Sales;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Coin;
use App\Models\Sales\SalesInvoice;
use App\Support\Permissions;
use Illuminate\Support\Facades\Cache;

class SalesInvoiceController extends Controller
{
    public function index()
    {
        Permissions::authorize(config('permissions.sales_invoices.view'));

        session()->save();

        // ✅ عملة النظام (مخزّنة — ثابتة عملياً)
        $systemCurrency = Cache::rememberForever('system_coin', fn () => Coin::where('coinsSystem', 1)
            ->first(['coinsID', 'coinsCode']));

        return view('operation.sales.invoices.index', [
            'systemCurrencyId'   => $systemCurrency->coinsID ?? null,
            'systemCurrencyCode' => $systemCurrency->coinsCode ?? '',
        ]);
    }

    public function print($id)
    {
        Permissions::authorize(config('permissions.sales_invoices.print'));

        $invoice = SalesInvoice::with([
            'details.item',
            'details.type',
            'details.unit',
            'customerAccount',
            'coin',
        ])->find($id);

        if (!$invoice) {
            abort(404, 'الفاتورة غير موجودة');
        }

        return view('print.sales-invoice', compact('invoice'));
    }
}