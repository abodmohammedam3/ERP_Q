<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\accounting\CharAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;

use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\TypeController;
use App\Http\Controllers\Inventory\UnitController;
use App\Http\Controllers\accounting\OpeningBalanceController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\accounting\CoinController;
use App\Http\Controllers\accounting\BoxController;
use App\Http\Controllers\accounting\BankController;
use App\Http\Controllers\Operation\Purchases\PurchaseInvoiceController;
use App\Http\Controllers\Operation\Movements\InventoryMovementController;
use App\Http\Controllers\Operation\Sales\SalesInvoiceController;
use App\Http\Controllers\accounting\JournalEntryController;
use App\Http\Controllers\Operation\ReceiptVoucher\ReceiptVoucherController;
use App\Http\Controllers\Operation\PaymentVoucher\PaymentVoucherController;




Route::get('/', function () {
    return view('dashboard.index');
});

Route::get('/dashboard', function () {
    return view('dashboard.index');
});

// =====================================================
// دوال دليل الحسابات
// =====================================================

Route::get(
    '/settings/accounting/chartOfAccounts',
    [CharAccountController::class, 'index']
)->name('chartOfAccounts.index');

Route::get(
    '/settings/accounting/chartOfAccounts/tree',
    [CharAccountController::class, 'tree']
)->name('chartOfAccounts.tree');

Route::get(
    '/settings/accounting/chartOfAccounts/{account}/analytical',
    [CharAccountController::class, 'analyticalAccounts']
)->name('chartOfAccounts.analytical');

Route::get(
    '/settings/accounting/chartOfAccounts/next-code/{parentId}',
    [CharAccountController::class, 'nextCode']
)->name('chartOfAccounts.nextCode');


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


Route::delete(
    '/settings/accounting/chartOfAccounts/{account}',
    [CharAccountController::class, 'destroy']
)->name('chartOfAccounts.destroy');

// =====================================================
// العملاء
// =====================================================

Route::get(
    '/setting/customers',
    [CustomerController::class, 'index']
)->name('customers.index');

Route::get(
    '/setting/customers/list',
    [CustomerController::class, 'list']
)->name('customers.list');

Route::get(
    '/setting/customers/search',
    [CustomerController::class, 'search']
)->name('customers.search');

Route::get(
    '/setting/customers/{id}',
    [CustomerController::class, 'show']
)->name('customers.show');

Route::post(
    '/setting/customers',
    [CustomerController::class, 'store']
)->name('customers.store');

Route::put(
    '/setting/customers/{id}',
    [CustomerController::class, 'update']
)->name('customers.update');

Route::delete(
    '/setting/customers/{id}',
    [CustomerController::class, 'destroy']
)->name('customers.destroy');


// =====================================================
// الموردون
// =====================================================

Route::get(
    '/setting/suppliers',
    [SupplierController::class, 'index']
)->name('suppliers.index');

Route::get(
    '/setting/suppliers/list',
    [SupplierController::class, 'list']
)->name('suppliers.list');

Route::get(
    '/setting/suppliers/search',
    [SupplierController::class, 'search']
)->name('suppliers.search');

Route::get(
    '/setting/suppliers/{id}',
    [SupplierController::class, 'show']
)->name('suppliers.show');

Route::post(
    '/setting/suppliers',
    [SupplierController::class, 'store']
)->name('suppliers.store');

Route::put(
    '/setting/suppliers/{id}',
    [SupplierController::class, 'update']
)->name('suppliers.update');

Route::delete(
    '/setting/suppliers/{id}',
    [SupplierController::class, 'destroy']
)->name('suppliers.destroy');


// =====================================================
// الصناديق
// =====================================================
Route::get(
    '/setting/accounting/boxes',
    [BoxController::class, 'index']
)->name('boxes.index');

Route::get(
    '/setting/accounting/boxes/next-code',
    [BoxController::class, 'getNextCode']
)->name('boxes.nextCode');

Route::get(
    '/setting/accounting/boxes/list',
    [BoxController::class, 'list']
)->name('boxes.list');

Route::post(
    '/setting/accounting/boxes',
    [BoxController::class, 'store']
)->name('boxes.store');

Route::put(
    '/setting/accounting/boxes/{box}',
    [BoxController::class, 'update']
)->name('boxes.update');

Route::delete(
    '/setting/accounting/boxes/{box}',
    [BoxController::class, 'destroy']
)->name('boxes.destroy');

Route::patch(
    '/setting/accounting/boxes/{box}/toggle-status',
    [BoxController::class, 'toggleStatus']
)->name('boxes.toggleStatus');



// =====================================================
// الأرصدة الافتتاحية
// =====================================================

Route::get('/setting/accounting/openingBalances',
    [OpeningBalanceController::class, 'index']
)->name('openingBalances.index');

Route::get('/setting/accounting/openingBalances/list',
    [OpeningBalanceController::class, 'list']
)->name('openingBalances.list');

Route::get('/setting/accounting/openingBalances/picker',
    [OpeningBalanceController::class, 'picker']
)->name('openingBalances.picker');

Route::get('/setting/accounting/openingBalances/{id}/edit',
    [OpeningBalanceController::class, 'edit']
)->name('openingBalances.edit');

Route::post('/setting/accounting/openingBalances',
    [OpeningBalanceController::class, 'store']
)->name('openingBalances.store');

Route::put('/setting/accounting/openingBalances/{id}',
    [OpeningBalanceController::class, 'update']
)->name('openingBalances.update');

Route::delete('/setting/accounting/openingBalances/{id}',
    [OpeningBalanceController::class, 'destroy']
)->name('openingBalances.destroy');

// =====================================================
// قيود اليومية
// =====================================================

Route::get('/operation/accounting/journalEntries',
    [JournalEntryController::class, 'index']
)->name('journalEntries.index');

Route::get('/operation/accounting/journalEntries/list',
    [JournalEntryController::class, 'list']
)->name('journalEntries.list');

Route::get('/operation/accounting/journalEntries/{id}/show',
    [JournalEntryController::class, 'show']
)->name('journalEntries.show');

// =====================================================
// البنوك
// =====================================================
Route::get('/setting/accounting/banks',
    [BankController::class, 'index']
)->name('banks.index');

Route::get('/setting/accounting/banks/list',
    [BankController::class, 'list']
)->name('banks.list');

Route::get('/setting/accounting/banks/next-code',
    [BankController::class, 'getNextCode']
)->name('banks.nextCode');

Route::post('/setting/accounting/banks',
    [BankController::class, 'store']
)->name('banks.store');

Route::put('/setting/accounting/banks/{bank}',
    [BankController::class, 'update']
)->name('banks.update');

Route::delete('/setting/accounting/banks/{bank}',
    [BankController::class, 'destroy']
)->name('banks.destroy');

Route::patch('/setting/accounting/banks/{bank}/toggle-status',
    [BankController::class, 'toggleStatus']
)->name('banks.toggleStatus');


// =====================================================
// Routes الخاصة بالأصناف
// =====================================================

Route::get(
    '/setting/inventory/items',
    [ItemController::class, 'index']
)->name('items.index');

Route::get(
    '/setting/inventory/items/list',
    [ItemController::class, 'list']
)->name('items.list');

Route::post(
    '/setting/inventory/items',
    [ItemController::class, 'store']
)->name('items.store');

Route::put(
    '/setting/inventory/items/{item}',
    [ItemController::class, 'update']
)->name('items.update');

Route::delete(
    '/setting/inventory/items/{item}',
    [ItemController::class, 'destroy']
)->name('items.destroy');

Route::patch(
    '/setting/inventory/items/{item}/toggle-status',
    [ItemController::class, 'toggleStatus']
)->name('items.toggleStatus');

Route::get(
    '/setting/inventory/items/search',
    [ItemController::class, 'search']
)->name('items.search');



// =====================================================
// Routes الخاصة بالأنواع
// =====================================================

Route::get(
    '/setting/inventory/types',
    [TypeController::class, 'index']
)->name('types.index');

Route::get(
    '/setting/inventory/types/list',
    [TypeController::class, 'list']
)->name('types.list');

Route::post(
    '/setting/inventory/types',
    [TypeController::class, 'store']
)->name('types.store');

Route::put(
    '/setting/inventory/types/{type}',
    [TypeController::class, 'update']
)->name('types.update');

Route::delete(
    '/setting/inventory/types/{type}',
    [TypeController::class, 'destroy']
)->name('types.destroy');

Route::patch(
    '/setting/inventory/types/{type}/toggle-status',
    [TypeController::class, 'toggleStatus']
)->name('types.toggleStatus');


// =====================================================
// Routes الخاصة بالوحدات
// =====================================================

Route::get(
    '/setting/inventory/units',
    [UnitController::class, 'index']
)->name('units.index');

Route::get(
    '/setting/inventory/units/list',
    [UnitController::class, 'list']
)->name('units.list');

Route::post(
    '/setting/inventory/units',
    [UnitController::class, 'store']
)->name('units.store');

Route::put(
    '/setting/inventory/units/{unit}',
    [UnitController::class, 'update']
)->name('units.update');

Route::delete(
    '/setting/inventory/units/{unit}',
    [UnitController::class, 'destroy']
)->name('units.destroy');

Route::patch(
    '/setting/inventory/units/{unit}/toggle-status',
    [UnitController::class, 'toggleStatus']
)->name('units.toggleStatus');

// =====================================================
// Routes الخاصة بالمخازن
// =====================================================

Route::get(
    '/setting/inventory/warehouses',
    [StockController::class, 'index']
)->name('warehouses.index');

Route::get(
    '/setting/inventory/warehouses/list',
    [StockController::class, 'list']
)->name('warehouses.list');

Route::get(
    '/setting/inventory/warehouses/next-code',
    [StockController::class, 'getNextCode']
)->name('warehouses.nextCode');

Route::post(
    '/setting/inventory/warehouses',
    [StockController::class, 'store']
)->name('warehouses.store');

Route::put(
    '/setting/inventory/warehouses/{stock}',
    [StockController::class, 'update']
)->name('warehouses.update');

Route::delete(
    '/setting/inventory/warehouses/{stock}',
    [StockController::class, 'destroy']
)->name('warehouses.destroy');

Route::patch(
    '/setting/inventory/warehouses/{stock}/toggle-status',
    [StockController::class, 'toggleStatus']
)->name('warehouses.toggleStatus');

// =====================================================
// الصفحات التشغيلية
// =====================================================

// =====================================================
// سندات الصرف
// =====================================================

Route::get(
    '/operation/accounting/paymentVouchers',
    [PaymentVoucherController::class, 'index']
)->name('paymentVouchers.index');

Route::get(
    '/operation/accounting/paymentVouchers/list',
    [PaymentVoucherController::class, 'list']
)->name('paymentVouchers.list');

Route::get(
    '/operation/accounting/paymentVouchers/picker',
    [PaymentVoucherController::class, 'picker']
)->name('paymentVouchers.picker');

Route::get(
    '/operation/accounting/paymentVouchers/currencies',
    [PaymentVoucherController::class, 'currencies']
)->name('paymentVouchers.currencies');

Route::get(
    '/operation/accounting/paymentVouchers/next-number',
    [PaymentVoucherController::class, 'nextNumber']
)->name('paymentVouchers.nextNumber');

Route::get(
    '/operation/accounting/paymentVouchers/{id}/print',
    [PaymentVoucherController::class, 'printView']
)->name('paymentVouchers.print');

Route::get(
    '/operation/accounting/paymentVouchers/{id}',
    [PaymentVoucherController::class, 'show']
)->name('paymentVouchers.show');

Route::post(
    '/operation/accounting/paymentVouchers',
    [PaymentVoucherController::class, 'store']
)->name('paymentVouchers.store');

Route::put(
    '/operation/accounting/paymentVouchers/{id}',
    [PaymentVoucherController::class, 'update']
)->name('paymentVouchers.update');

Route::delete(
    '/operation/accounting/paymentVouchers/{id}',
    [PaymentVoucherController::class, 'destroy']
)->name('paymentVouchers.destroy');

// =====================================================
// سندات القبض
// =====================================================

Route::get(
    '/operation/accounting/receiptVouchers',
    [ReceiptVoucherController::class, 'index']
)->name('receiptVouchers.index');

Route::get(
    '/operation/accounting/receiptVouchers/list',
    [ReceiptVoucherController::class, 'list']
)->name('receiptVouchers.list');

Route::get(
    '/operation/accounting/receiptVouchers/picker',
    [ReceiptVoucherController::class, 'picker']
)->name('receiptVouchers.picker');

Route::get(
    '/operation/accounting/receiptVouchers/currencies',
    [ReceiptVoucherController::class, 'currencies']
)->name('receiptVouchers.currencies');

Route::get(
    '/operation/accounting/receiptVouchers/next-number',
    [ReceiptVoucherController::class, 'nextNumber']
)->name('receiptVouchers.nextNumber');

Route::get(
    '/operation/accounting/receiptVouchers/{id}/print',
    [ReceiptVoucherController::class, 'printView']
)->name('receiptVouchers.print');

Route::get(
    '/operation/accounting/receiptVouchers/{id}',
    [ReceiptVoucherController::class, 'show']
)->name('receiptVouchers.show');

Route::post(
    '/operation/accounting/receiptVouchers',
    [ReceiptVoucherController::class, 'store']
)->name('receiptVouchers.store');

Route::put(
    '/operation/accounting/receiptVouchers/{id}',
    [ReceiptVoucherController::class, 'update']
)->name('receiptVouchers.update');

Route::delete(
    '/operation/accounting/receiptVouchers/{id}',
    [ReceiptVoucherController::class, 'destroy']
)->name('receiptVouchers.destroy');



// =====================================================
// العملات
// =====================================================
Route::get(
    '/setting/accounting/coins',
    [CoinController::class, 'index']
)->name('coins.index');

Route::get(
    '/setting/accounting/coins/list',
    [CoinController::class, 'list']
)->name('coins.list');

Route::post(
    '/setting/accounting/coins',
    [CoinController::class, 'store']
)->name('coins.store');

Route::put(
    '/setting/accounting/coins/{coin}',
    [CoinController::class, 'update']
)->name('coins.update');

Route::delete(
    '/setting/accounting/coins/{coin}',
    [CoinController::class, 'destroy']
)->name('coins.destroy');

Route::patch(
    '/setting/accounting/coins/{coin}/toggle-status',
    [CoinController::class, 'toggleStatus']
)->name('coins.toggleStatus');

// =====================================================
// فواتير الشراء
// =====================================================

// -----------------------------------------------------
// عرض الشاشة الرئيسية
// -----------------------------------------------------

Route::get(
    '/operation/purchases/invoicesPurch',
    [PurchaseInvoiceController::class, 'index']
)->name('invoicesPurch.index');


// -----------------------------------------------------
// رقم الفاتورة التالي
// -----------------------------------------------------

Route::get(
    '/operation/purchases/invoicesPurch/next-number',
    [PurchaseInvoiceController::class, 'nextNumber']
)->name('invoicesPurch.nextNumber');


// -----------------------------------------------------
// قائمة الفواتير (JSON) — لنافذة البحث
// -----------------------------------------------------

Route::get(
    '/operation/purchases/invoicesPurch/list',
    [PurchaseInvoiceController::class, 'list']
)->name('invoicesPurch.list');


// -----------------------------------------------------
// طباعة فاتورة الشراء
// -----------------------------------------------------

Route::get(
    '/operation/purchases/invoicesPurch/{id}/print',
    [PurchaseInvoiceController::class, 'print']
)->name('invoicesPurch.print');


// -----------------------------------------------------
// عرض فاتورة واحدة (JSON)
// -----------------------------------------------------

Route::get(
    '/operation/purchases/invoicesPurch/{id}',
    [PurchaseInvoiceController::class, 'show']
)->name('invoicesPurch.show');


// -----------------------------------------------------
// حفظ فاتورة جديدة
// -----------------------------------------------------

Route::post(
    '/operation/purchases/invoicesPurch',
    [PurchaseInvoiceController::class, 'store']
)->name('invoicesPurch.store');


// -----------------------------------------------------
// تحديث فاتورة
// -----------------------------------------------------

Route::put(
    '/operation/purchases/invoicesPurch/{id}',
    [PurchaseInvoiceController::class, 'update']
)->name('invoicesPurch.update');


// -----------------------------------------------------
// حذف فاتورة
// -----------------------------------------------------

Route::delete(
    '/operation/purchases/invoicesPurch/{id}',
    [PurchaseInvoiceController::class, 'destroy']
)->name('invoicesPurch.destroy');


// =====================================================
// حركات المخزون
// =====================================================

// -----------------------------------------------------
// عرض الشاشة الرئيسية
// -----------------------------------------------------

Route::get(
    '/operation/movements',
    [InventoryMovementController::class, 'index']
)->name('movements.index');


// -----------------------------------------------------
// رقم الحركة التالي
// -----------------------------------------------------

Route::get(
    '/operation/movements/next-number',
    [InventoryMovementController::class, 'nextNumber']
)->name('movements.nextNumber');


// -----------------------------------------------------
// قائمة الحركات (JSON) — للبحث
// -----------------------------------------------------

Route::get(
    '/operation/movements/list',
    [InventoryMovementController::class, 'list']
)->name('movements.list');


// -----------------------------------------------------
// ✅ Helpers — جلب الرصيد المتاح (Unit-aware)
// ⚠️ يجب أن يوضع قبل /{id}
// -----------------------------------------------------

Route::get(
    '/operation/movements/helpers/available',
    [InventoryMovementController::class, 'available']
)->name('movements.helpers.available');


// -----------------------------------------------------
// ✅ Helpers — جلب التسعير الحالي
// ⚠️ يجب أن يوضع قبل /{id}
// -----------------------------------------------------

Route::get(
    '/operation/movements/helpers/pricing',
    [InventoryMovementController::class, 'pricing']
)->name('movements.helpers.pricing');


// -----------------------------------------------------
// ✅ Helpers — جلب الرصيد + التكلفة + التسعير (مدمج)
// ⚠️ يجب أن يوضع قبل /{id}
// -----------------------------------------------------

Route::get(
    '/operation/movements/helpers/stock-pricing',
    [InventoryMovementController::class, 'stockPricing']
)->name('movements.helpers.stockPricing');


// -----------------------------------------------------
// ✅ تحديث التسعير لآخر حركة "in"
// -----------------------------------------------------

Route::put(
    '/operation/movements/helpers/pricing',
    [InventoryMovementController::class, 'updatePricing']
)->name('movements.helpers.updatePricing');


// -----------------------------------------------------
// ✅ Helpers — جلب جميع الأرصدة مع التسعير
// ⚠️ يجب أن يوضع قبل /{id}
// -----------------------------------------------------

Route::get(
    '/operation/movements/helpers/stock-balances',
    [InventoryMovementController::class, 'allStockBalances']
)->name('movements.helpers.stockBalances');


// -----------------------------------------------------
// ✅ فرز / تجهيز المخزون
// -----------------------------------------------------

Route::post(
    '/operation/movements/sort',
    [InventoryMovementController::class, 'sort']
)->name('movements.sort');


// -----------------------------------------------------
// ✅ عكس عملية فرز
// -----------------------------------------------------

Route::post(
    '/operation/movements/sort/{documentNumber}/reverse',
    [InventoryMovementController::class, 'reverseSort']
)->name('movements.sort.reverse');


// -----------------------------------------------------
// عرض حركة واحدة (JSON)
// ⚠️ يجب أن يكون بعد helpers
// -----------------------------------------------------

Route::get(
    '/operation/movements/{id}',
    [InventoryMovementController::class, 'show']
)->name('movements.show');


// -----------------------------------------------------
// حفظ حركة جديدة
// -----------------------------------------------------

Route::post(
    '/operation/movements',
    [InventoryMovementController::class, 'store']
)->name('movements.store');


// =====================================================
// فواتير البيع
// =====================================================

// -----------------------------------------------------
// عرض الشاشة الرئيسية
// -----------------------------------------------------

Route::get(
    '/operation/sales/invoices',
    [SalesInvoiceController::class, 'index']
)->name('sales.invoices.index');


// -----------------------------------------------------
// رقم الفاتورة التالي
// -----------------------------------------------------

Route::get(
    '/operation/sales/invoices/next-number',
    [SalesInvoiceController::class, 'nextNumber']
)->name('sales.invoices.nextNumber');


// -----------------------------------------------------
// قائمة الفواتير (JSON)
// -----------------------------------------------------

Route::get(
    '/operation/sales/invoices/list',
    [SalesInvoiceController::class, 'list']
)->name('sales.invoices.list');

// -----------------------------------------------------
// آخر تكلفة لصنف في مخزن (مساعد)
// -----------------------------------------------------

Route::get(
    '/operation/sales/invoices/helpers/last-cost',
    [SalesInvoiceController::class, 'lastCost']
)->name('sales.invoices.lastCost');

// -----------------------------------------------------
// طباعة فاتورة البيع
// -----------------------------------------------------

Route::get(
    '/operation/sales/invoices/{id}/print',
    [SalesInvoiceController::class, 'print']
)->name('sales.invoices.print');


// -----------------------------------------------------
// عرض فاتورة واحدة (JSON)
// -----------------------------------------------------

Route::get(
    '/operation/sales/invoices/{id}',
    [SalesInvoiceController::class, 'show']
)->name('sales.invoices.show');


// -----------------------------------------------------
// حفظ فاتورة جديدة
// -----------------------------------------------------

Route::post(
    '/operation/sales/invoices',
    [SalesInvoiceController::class, 'store']
)->name('sales.invoices.store');


// -----------------------------------------------------
// تحديث فاتورة
// -----------------------------------------------------

Route::put(
    '/operation/sales/invoices/{id}',
    [SalesInvoiceController::class, 'update']
)->name('sales.invoices.update');


// -----------------------------------------------------
// حذف فاتورة
// -----------------------------------------------------

Route::delete(
    '/operation/sales/invoices/{id}',
    [SalesInvoiceController::class, 'destroy']
)->name('sales.invoices.destroy');