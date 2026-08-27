<?php
use Illuminate\Support\Facades\Route;
Route::get('/', function () {
    return view('dashboard.index');
});

Route::get('/dashboard', function () {
    return view('dashboard.index');
});
Route::get('/chartOfAccounts', function () {
    return view('accounting.chartOfAccounts.index');
})->name('chartOfAccounts.index');

Route::get('/receiptVouchers', function () {
    return view('accounting.receiptVouchers.index');
})->name('receiptVouchers.index');

Route::get('/invoices', function () {
    return view('sales.invoices.index');
})->name('invoices.index');

Route::get('/invoicesPurch', function () {
    return view('purchases.invoicesPurch.index');
})->name('invoicesPurch.index');

Route::get('/warehouses', function () {
    return view('inventory.warehouses.index');
})->name('warehouses.index');

Route::get('/movements', function () {
    return view('inventory.movements.index');
})->name('movements.index');

Route::get('/customers', function () {
    return view('sales.customers.index');
})->name('customers.index');

Route::get('/suppliers', function () {
    return view('purchases.suppliers.index');
})->name('suppliers.index');

Route::get('/items', function () {
    return view('items.index');
})->name('items.index');

Route::get('/types', function () {
    return view('items.types.index');
})->name('types.index');

Route::get('/banks', function () {
    return view('banks.index');
})->name('banks.index');

Route::get('/units', function () {
    return view('units.index');
})->name('units.index');

Route::get('/boxes', function () {
    return view('boxes.index');
})->name('boxes.index');
