<?php

namespace App\Http\Controllers\Operation\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Bank;
use App\Models\Accounting\Coin;
use App\Models\Purchases\PurchaseInvoice;
use App\Support\Permissions;

class PurchaseInvoiceController extends Controller
{
    public function index()
    {
        Permissions::authorize(config('permissions.purchase_invoices.view'));

        session()->save();

        $systemCurrency = Coin::where('coinsSystem', 1)
            ->first(['coinsID', 'coinsCode']);

        return view('operation.purchases.invoicesPurch.index', [
            'systemCurrencyId'   => $systemCurrency->coinsID ?? null,
            'systemCurrencyCode' => $systemCurrency->coinsCode ?? '',
        ]);
    }

    public function print($id)
    {
        Permissions::authorize(config('permissions.purchase_invoices.print'));

        $invoice = PurchaseInvoice::with([
            'details.item',
            'details.type',
            'details.unit',
            'supplierAccount',
            'warehouse',
            'coin',
            'paymentAccount',
        ])->find($id);

        if (!$invoice) {
            abort(404, 'الفاتورة غير موجودة');
        }

        $bank = null;
        if ((int) $invoice->payment_method === PurchaseInvoice::PAYMENT_BANK) {
            $bank = Bank::where('accountID', $invoice->payment_account_id)
                ->with('coin')
                ->first();
        }

        return view('print.purchase-invoice', compact('invoice', 'bank'));
    }
}