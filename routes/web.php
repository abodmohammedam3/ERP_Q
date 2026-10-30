<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\accounting\CharAccountController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Reports\ReportCenterController;
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
use App\Http\Controllers\Operation\Sales\SalesReturnController;
use App\Http\Controllers\Operation\Purchases\PurchaseReturnController;
use App\Http\Controllers\Operation\Sales\SalesInvoiceApiController;
use App\Http\Controllers\Operation\Purchases\PurchaseReturnApiController;
use App\Http\Controllers\Operation\Purchases\PurchaseInvoiceApiController;
use App\Http\Controllers\Operation\Sales\SalesReturnApiController;
use App\Http\Controllers\Operation\ReceiptVoucher\ReceiptVoucherApiController;
use App\Http\Controllers\Operation\PaymentVoucher\PaymentVoucherApiController;
use App\Http\Controllers\Operation\PurchaseDistribution\PurchaseDistributionController;


// =====================================================
// لوحة المعلومات (Dashboard)
// -----------------------------------------------------
Route::get('/', [DashboardController::class, 'index'])->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

// =====================================================
// مركز التقارير
// =====================================================
Route::get('/reports', [ReportCenterController::class, 'index'])->name('reports.index');
Route::get('/reports/definitions', [ReportCenterController::class, 'definitions'])->name('reports.definitions');
Route::get('/reports/sources', [ReportCenterController::class, 'sources'])->name('reports.sources');
Route::get('/reports/data/{key}', [ReportCenterController::class, 'data'])->name('reports.data');
Route::get('/reports/print/{key}', [ReportCenterController::class, 'print'])->name('reports.print');
Route::get('/reports/export/{key}', [ReportCenterController::class, 'export'])->name('reports.export');
Route::get('/reports/accounts', [ReportCenterController::class, 'accounts'])->name('reports.accounts');
Route::get('/lookup/{type}', [ReportCenterController::class, 'lookup'])->name('reports.lookup');

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
    '/setting/customers/print',
    [CustomerController::class, 'print']
)->name('customers.print');

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
    '/setting/suppliers/print',
    [SupplierController::class, 'print']
)->name('suppliers.print');

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

Route::get('/setting/accounting/openingBalances/print', 
    [OpeningBalanceController::class, 'print']
)->name('openingBalances.print');

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
// ═══════════════════════════════════════════════════════════
// Payment Vouchers (سندات الصرف)
// ═══════════════════════════════════════════════════════════

Route::middleware(['web'])
    ->prefix('operation/accounting/paymentVouchers')
    ->name('paymentVouchers.')
    ->group(function () {

        // -----------------------------------------------------
        // Web (Views)
        // -----------------------------------------------------

        Route::get('/', [PaymentVoucherController::class, 'index'])
            ->name('index');

        Route::get('/{id}/print', [PaymentVoucherController::class, 'printView'])
            ->whereNumber('id')
            ->name('print');

        // -----------------------------------------------------
        // API (JSON)
        // ⚠️ المسارات الثابتة قبل /{id}
        // -----------------------------------------------------

        Route::get('/next-number', [PaymentVoucherApiController::class, 'nextNumber'])
            ->name('nextNumber');

        Route::get('/currencies', [PaymentVoucherApiController::class, 'currencies'])
            ->name('currencies');

        Route::get('/picker', [PaymentVoucherApiController::class, 'picker'])
            ->name('picker');

        Route::get('/list', [PaymentVoucherApiController::class, 'list'])
            ->name('list');

        // ─────────────────────────────────────────────────────
        // الترحيل / إلغاء الترحيل
        // ─────────────────────────────────────────────────────

        Route::post('/{id}/post', [PaymentVoucherApiController::class, 'post'])
            ->whereNumber('id')
            ->name('post');

        Route::post('/{id}/unpost', [PaymentVoucherApiController::class, 'unpost'])
            ->whereNumber('id')
            ->name('unpost');

        // ─────────────────────────────────────────────────────
        // CRUD
        // ─────────────────────────────────────────────────────

        Route::get('/{id}', [PaymentVoucherApiController::class, 'show'])
            ->whereNumber('id')
            ->name('show');

        Route::post('/', [PaymentVoucherApiController::class, 'store'])
            ->name('store');

        Route::put('/{id}', [PaymentVoucherApiController::class, 'update'])
            ->whereNumber('id')
            ->name('update');

        Route::delete('/{id}', [PaymentVoucherApiController::class, 'destroy'])
            ->whereNumber('id')
            ->name('destroy');
    });

// =====================================================
// سندات القبض
// =====================================================

// -----------------------------------------------------
// Web (Views)
// -----------------------------------------------------

Route::get(
    '/operation/accounting/receiptVouchers',
    [ReceiptVoucherController::class, 'index']
)->name('receiptVouchers.index');

Route::get(
    '/operation/accounting/receiptVouchers/{id}/print',
    [ReceiptVoucherController::class, 'printView']
)->name('receiptVouchers.print');

// -----------------------------------------------------
// API (JSON)
// ⚠️ المسارات الثابتة قبل /{id}
// -----------------------------------------------------

Route::get(
    '/operation/accounting/receiptVouchers/next-number',
    [ReceiptVoucherApiController::class, 'nextNumber']
)->name('receiptVouchers.nextNumber');

Route::get(
    '/operation/accounting/receiptVouchers/currencies',
    [ReceiptVoucherApiController::class, 'currencies']
)->name('receiptVouchers.currencies');

Route::get(
    '/operation/accounting/receiptVouchers/picker',
    [ReceiptVoucherApiController::class, 'picker']
)->name('receiptVouchers.picker');

Route::get(
    '/operation/accounting/receiptVouchers/list',
    [ReceiptVoucherApiController::class, 'list']
)->name('receiptVouchers.list');

// ─────────────────────────────────────────────────────
// الترحيل / إلغاء الترحيل
// ─────────────────────────────────────────────────────

Route::post(
    '/operation/accounting/receiptVouchers/{id}/post',
    [ReceiptVoucherApiController::class, 'post']
)->name('receiptVouchers.post');

Route::post(
    '/operation/accounting/receiptVouchers/{id}/unpost',
    [ReceiptVoucherApiController::class, 'unpost']
)->name('receiptVouchers.unpost');

Route::get(
    '/operation/accounting/receiptVouchers/{id}',
    [ReceiptVoucherApiController::class, 'show']
)->name('receiptVouchers.show');

Route::post(
    '/operation/accounting/receiptVouchers',
    [ReceiptVoucherApiController::class, 'store']
)->name('receiptVouchers.store');

Route::put(
    '/operation/accounting/receiptVouchers/{id}',
    [ReceiptVoucherApiController::class, 'update']
)->name('receiptVouchers.update');

Route::delete(
    '/operation/accounting/receiptVouchers/{id}',
    [ReceiptVoucherApiController::class, 'destroy']
)->name('receiptVouchers.destroy');;



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
// Web (Views)
// -----------------------------------------------------

Route::get(
    '/operation/purchases/invoicesPurch',
    [PurchaseInvoiceController::class, 'index']
)->name('invoicesPurch.index');

Route::get(
    '/operation/purchases/invoicesPurch/{id}/print',
    [PurchaseInvoiceController::class, 'print']
)->name('invoicesPurch.print');

// -----------------------------------------------------
// API (JSON)
// ⚠️ المسارات الثابتة قبل /{id}
// -----------------------------------------------------

Route::get(
    '/operation/purchases/invoicesPurch/next-number',
    [PurchaseInvoiceApiController::class, 'nextNumber']
)->name('invoicesPurch.nextNumber');

Route::get(
    '/operation/purchases/banks/list-full',
    [PurchaseInvoiceApiController::class, 'listBanksFull']
)->name('purchases.banks.listFull');

Route::get(
    '/operation/purchases/invoicesPurch/list',
    [PurchaseInvoiceApiController::class, 'list']
)->name('invoicesPurch.list');

Route::get(
    '/operation/purchases/invoicesPurch/{id}',
    [PurchaseInvoiceApiController::class, 'show']
)->name('invoicesPurch.show');

Route::post(
    '/operation/purchases/invoicesPurch',
    [PurchaseInvoiceApiController::class, 'store']
)->name('invoicesPurch.store');

Route::put(
    '/operation/purchases/invoicesPurch/{id}',
    [PurchaseInvoiceApiController::class, 'update']
)->name('invoicesPurch.update');

Route::delete(
    '/operation/purchases/invoicesPurch/{id}',
    [PurchaseInvoiceApiController::class, 'destroy']
)->name('invoicesPurch.destroy');

// -----------------------------------------------------
// الترحيل / إلغاء الترحيل
// -----------------------------------------------------

Route::post(
    '/operation/purchases/invoicesPurch/{id}/post',
    [PurchaseInvoiceApiController::class, 'post']
)->name('invoicesPurch.post');

Route::post(
    '/operation/purchases/invoicesPurch/{id}/unpost',
    [PurchaseInvoiceApiController::class, 'unpost']
)->name('invoicesPurch.unpost');

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

Route::get(
    '/operation/movements/next-number',
    [InventoryMovementController::class, 'nextNumber']
)->name('movements.nextNumber');

Route::get(
    '/operation/movements/list',
    [InventoryMovementController::class, 'list']
)->name('movements.list');

Route::get(
    '/operation/movements/helpers/available',
    [InventoryMovementController::class, 'available']
)->name('movements.helpers.available');

Route::get(
    '/operation/movements/helpers/pricing',
    [InventoryMovementController::class, 'pricing']
)->name('movements.helpers.pricing');

Route::get(
    '/operation/movements/helpers/stock-pricing',
    [InventoryMovementController::class, 'stockPricing']
)->name('movements.helpers.stockPricing');

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

Route::get(
    '/operation/movements/sort/{documentNumber}/print',
    [InventoryMovementController::class, 'printSorting']
)->name('movements.sort.print');

// -----------------------------------------------------
// ✅ طباعة حركة مخزون
// -----------------------------------------------------

Route::get(
    '/operation/movements/{id}/print',
    [InventoryMovementController::class, 'printMovementPage']
)->name('movements.print');


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

// Web
Route::get(
    '/operation/sales/invoices',
    [SalesInvoiceController::class, 'index']
)->name('sales.invoices.index');

Route::get(
    '/operation/sales/invoices/{id}/print',
    [SalesInvoiceController::class, 'print']
)->name('sales.invoices.print');

// API
Route::get(
    '/operation/sales/invoices/list',
    [SalesInvoiceApiController::class, 'list']
)->name('sales.invoices.list');

Route::get(
    '/operation/sales/invoices/next-number',
    [SalesInvoiceApiController::class, 'nextNumber']
)->name('sales.invoices.nextNumber');

Route::get(
    '/operation/sales/invoices/helpers/last-cost',
    [SalesInvoiceApiController::class, 'lastCost']
)->name('sales.invoices.lastCost');

Route::get(
    '/operation/sales/invoices/{id}',
    [SalesInvoiceApiController::class, 'show']
)->name('sales.invoices.show');

Route::post(
    '/operation/sales/invoices',
    [SalesInvoiceApiController::class, 'store']
)->name('sales.invoices.store');

Route::put(
    '/operation/sales/invoices/{id}',
    [SalesInvoiceApiController::class, 'update']
)->name('sales.invoices.update');

Route::delete(
    '/operation/sales/invoices/{id}',
    [SalesInvoiceApiController::class, 'destroy']
)->name('sales.invoices.destroy');

Route::post(
    '/operation/sales/invoices/{id}/post',
    [SalesInvoiceApiController::class, 'post']
)->name('sales.invoices.post');

Route::post(
    '/operation/sales/invoices/{id}/unpost',
    [SalesInvoiceApiController::class, 'unpost']
)->name('sales.invoices.unpost');
// =====================================================
// مرتجعات المبيعات
// =====================================================

// -----------------------------------------------------
// Web (Views)
// -----------------------------------------------------

Route::get(
    '/operation/sales/returns',
    [SalesReturnController::class, 'index']
)->name('sales.returns.index');

Route::get(
    '/operation/sales/returns/{id}/print',
    [SalesReturnController::class, 'print']
)->name('sales.returns.print');

// -----------------------------------------------------
// API (JSON)
// ⚠️ المسارات الثابتة قبل /{id}
// -----------------------------------------------------

Route::get(
    '/operation/sales/returns/next-number',
    [SalesReturnApiController::class, 'nextNumber']
)->name('sales.returns.nextNumber');

Route::get(
    '/operation/sales/returns/available-quantity',
    [SalesReturnApiController::class, 'availableQuantity']
)->name('sales.returns.availableQuantity');

Route::get(
    '/operation/sales/returns/list',
    [SalesReturnApiController::class, 'list']
)->name('sales.returns.list');

Route::get(
    '/operation/sales/returns/{id}',
    [SalesReturnApiController::class, 'show']
)->name('sales.returns.show');

Route::post(
    '/operation/sales/returns',
    [SalesReturnApiController::class, 'store']
)->name('sales.returns.store');

Route::put(
    '/operation/sales/returns/{id}',
    [SalesReturnApiController::class, 'update']
)->name('sales.returns.update');

Route::delete(
    '/operation/sales/returns/{id}',
    [SalesReturnApiController::class, 'destroy']
)->name('sales.returns.destroy');

// -----------------------------------------------------
// الترحيل / إلغاء الترحيل
// -----------------------------------------------------

Route::post(
    '/operation/sales/returns/{id}/post',
    [SalesReturnApiController::class, 'post']
)->name('sales.returns.post');

Route::post(
    '/operation/sales/returns/{id}/unpost',
    [SalesReturnApiController::class, 'unpost']
)->name('sales.returns.unpost');
// =====================================================
// مرتجعات المشتريات
// =====================================================

// -----------------------------------------------------
// Web (Views)
// -----------------------------------------------------

Route::get(
    '/operation/purchases/returns',
    [PurchaseReturnController::class, 'index']
)->name('purchases.returns.index');

Route::get(
    '/operation/purchases/returns/{id}/print',
    [PurchaseReturnController::class, 'print']
)->name('purchases.returns.print');

// -----------------------------------------------------
// API (JSON)
// ⚠️ المسارات الثابتة قبل /{id}
// -----------------------------------------------------

Route::get(
    '/operation/purchases/returns/next-number',
    [PurchaseReturnApiController::class, 'nextNumber']
)->name('purchases.returns.nextNumber');

Route::get(
    '/operation/purchases/returns/available-quantity',
    [PurchaseReturnApiController::class, 'availableQuantity']
)->name('purchases.returns.availableQuantity');

Route::get(
    '/operation/purchases/returns/list',
    [PurchaseReturnApiController::class, 'list']
)->name('purchases.returns.list');

Route::get(
    '/operation/purchases/returns/invoice-picker',
    [PurchaseReturnApiController::class, 'invoicePicker']
)->name('purchases.returns.invoicePicker');

Route::get(
    '/operation/purchases/returns/sorted-batches',
    [PurchaseReturnApiController::class, 'sortedBatches']
)->name('purchases.returns.sortedBatches');

Route::get(
    '/operation/purchases/returns/{id}',
    [PurchaseReturnApiController::class, 'show']
)->name('purchases.returns.show');

Route::post(
    '/operation/purchases/returns',
    [PurchaseReturnApiController::class, 'store']
)->name('purchases.returns.store');

Route::put(
    '/operation/purchases/returns/{id}',
    [PurchaseReturnApiController::class, 'update']
)->name('purchases.returns.update');

Route::delete(
    '/operation/purchases/returns/{id}',
    [PurchaseReturnApiController::class, 'destroy']
)->name('purchases.returns.destroy');

// -----------------------------------------------------
// الترحيل / إلغاء الترحيل
// -----------------------------------------------------

Route::post(
    '/operation/purchases/returns/{id}/post',
    [PurchaseReturnApiController::class, 'post']
)->name('purchases.returns.post');

Route::post(
    '/operation/purchases/returns/{id}/unpost',
    [PurchaseReturnApiController::class, 'unpost']
)->name('purchases.returns.unpost');


// =====================================================
// Entity CSV Exports
// SECURITY GAP NOTE: These routes currently lack authentication/authorization middleware.
// =====================================================
Route::prefix('admin/export')->group(function () {
    Route::get('/units', [\App\Http\Controllers\Admin\ExportController::class, 'units'])->name('admin.export.units');
    Route::get('/type', [\App\Http\Controllers\Admin\ExportController::class, 'type'])->name('admin.export.type');
    Route::get('/types', [\App\Http\Controllers\Admin\ExportController::class, 'type'])->name('admin.export.types');
    Route::get('/coins', [\App\Http\Controllers\Admin\ExportController::class, 'coins'])->name('admin.export.coins');
    Route::get('/characcount', [\App\Http\Controllers\Admin\ExportController::class, 'characcount'])->name('admin.export.characcount');
    Route::get('/characcounts', [\App\Http\Controllers\Admin\ExportController::class, 'characcount'])->name('admin.export.characcounts');
    Route::get('/boxes', [\App\Http\Controllers\Admin\ExportController::class, 'boxes'])->name('admin.export.boxes');
    Route::get('/banks', [\App\Http\Controllers\Admin\ExportController::class, 'banks'])->name('admin.export.banks');
    Route::get('/stocks', [\App\Http\Controllers\Admin\ExportController::class, 'stocks'])->name('admin.export.stocks');
    Route::get('/items', [\App\Http\Controllers\Admin\ExportController::class, 'items'])->name('admin.export.items');
    Route::get('/customers', [\App\Http\Controllers\Admin\ExportController::class, 'customers'])->name('admin.export.customers');
    Route::get('/suppliers', [\App\Http\Controllers\Admin\ExportController::class, 'suppliers'])->name('admin.export.suppliers');
});

// ═══════════════════════════════════════
//  إعدادات النظام — النسخ الاحتياطي
// ═══════════════════════════════════════

Route::prefix('settings/system')
    ->name('settings.system.')
    ->middleware(['web', 'can-manage-backup'])
    ->group(function () {

        // صفحة الإعدادات (مؤجلة — ترجع JSON مؤقتاً)
        Route::get('/', [\App\Http\Controllers\Settings\SystemSettingsController::class, 'index'])
            ->name('index');

        // النسخ الاحتياطي
        Route::prefix('backup')
            ->name('backup.')
            ->group(function () {

                Route::get('/', [\App\Http\Controllers\Settings\BackupController::class, 'index'])
                    ->name('index');

                Route::post('/export', [\App\Http\Controllers\Settings\BackupController::class, 'export'])
                    ->name('export');

                Route::post('/import', [\App\Http\Controllers\Settings\BackupController::class, 'import'])
                    ->name('import');

                Route::get('/operations/{operationId}', [\App\Http\Controllers\Settings\BackupController::class, 'operation'])
                    ->name('operation');

                Route::get('/download/{operationId}', [\App\Http\Controllers\Settings\BackupController::class, 'download'])
                    ->name('download');

                Route::get('/files/{filename}', [\App\Http\Controllers\Settings\BackupController::class, 'downloadExisting'])
                    ->where('filename', '.*')
                    ->name('download-existing');

                Route::delete('/files/{filename}', [\App\Http\Controllers\Settings\BackupController::class, 'destroy'])
                    ->where('filename', '.*')
                    ->name('destroy');

                Route::post('/cleanup', [\App\Http\Controllers\Settings\BackupController::class, 'cleanup'])
                    ->name('cleanup');
            });
    });

    
// ═══════════════════════════════════════
// — شاشة التوزيع
// ═══════════════════════════════════════use App\Http\Controllers\Operation\PurchaseDistribution\PurchaseDistributionController;

Route::prefix('operation/purchases/distribution')
    ->name('purchases.distribution.')
    ->group(function () {
        Route::get('/',                  [PurchaseDistributionController::class, 'index'])->name('index');
        Route::get('/customer-invoices', [PurchaseDistributionController::class, 'customerInvoicesPage'])->name('customer-invoices.page');
        Route::get('/invoices',            [PurchaseDistributionController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{id}/items', [PurchaseDistributionController::class, 'items'])->name('items');
        Route::get('/customers',           [PurchaseDistributionController::class, 'customers'])->name('customers');
        Route::post('/save',               [PurchaseDistributionController::class, 'save'])->name('save');
        Route::get('/list/all',            [PurchaseDistributionController::class, 'list'])->name('list');
        Route::post('/issue-invoice',      [PurchaseDistributionController::class, 'issueInvoice'])->name('issue-invoice');
        Route::get('/customer/{customerId}/distributions', [PurchaseDistributionController::class, 'customerDistributions'])->name('customer-distributions');
        Route::get('/{id}',    [PurchaseDistributionController::class, 'show'])->name('show');
        Route::delete('/{id}', [PurchaseDistributionController::class, 'destroy'])->name('destroy');
    });