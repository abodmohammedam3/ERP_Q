<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\accounting\CharAccountController;


Route::get('/', function () {
    return view('dashboard.index');
});

Route::get('/dashboard', function () {
    return view('dashboard.index');
});
// دوال دليل الحسابات

Route::get(
    '/settings/accounting/chartOfAccounts',
    [CharAccountController::class, 'index']
)->name('chartOfAccounts.index');

Route::get(
    '/settings/accounting/chartOfAccounts/list',
    [CharAccountController::class, 'list']
)->name('chartOfAccounts.list');

Route::post(
    '/settings/accounting/chartOfAccounts',
    [CharAccountController::class, 'store']
)->name('chartOfAccounts.store');

Route::get(
    '/settings/accounting/chartOfAccounts/{account}',
    [CharAccountController::class, 'edit']
)->name('chartOfAccounts.edit');

Route::put(
    '/settings/accounting/chartOfAccounts/{account}',
    [CharAccountController::class, 'update']
)->name('chartOfAccounts.update');
/////////////////////////////////////////////////////////////

Route::get('/setting/accounting/boxes', function () {
    return view('setting.accounting.boxes.index');
})->name('boxes.index');

Route::get('/setting/accounting/banks', function () {
    return view('setting.accounting.banks.index');
})->name('banks.index');

Route::get('/setting/accounting/currenc', function () {
    return view('setting.accounting.currenc.index');
})->name('currenc.index');

Route::get('/setting/accounting/openingBalances', function () {
    return view('setting.accounting.openingBalances.index');
})->name('openingBalances.index');

Route::get('/setting/suppliers', function () {
    return view('setting.suppliers.index');
})->name('suppliers.index');

Route::get('/setting/customers', function () {
    return view('setting.customers.index');
})->name('customers.index');

Route::get('/setting/inventory/warehouses', function () {
    return view('setting.inventory.warehouses.index');
})->name('warehouses.index');

Route::get('/setting/inventory/units', function () {
    return view('setting.inventory.units.index');
})->name('unites.index');

Route::get('/setting/inventory/types', function () {
    return view('setting.inventory.types.index');
})->name('types.index');

Route::get('/setting/inventory/items', function () {
    return view('setting.inventory.items.index');
})->name('items.index');

Route::get('/operation/sales/invoices', function () {
    return view('operation.sales.invoices.index');
})->name('sales.index');

Route::get('/operation/purchases/invoicesPurch', function () {
    return view('operation.purchases.invoicesPurch.index');
})->name('invoicesPurch.index');

Route::get('/operation/accounting/paymentVouchers', function () {
    return view('operation.accounting.paymentVouchers.index');
})->name('paymentVouchers.index');

Route::get('/operation/accounting/receiptVouchers', function () {
    return view('operation.accounting.receiptVouchers.index');
})->name('receiptVouchers.index');

Route::get('/operation/movements', function () {
    return view('operation.movements.index');
})->name('movements.index');

