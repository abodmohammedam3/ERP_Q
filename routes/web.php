<?php
use Illuminate\Support\Facades\Route;

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
