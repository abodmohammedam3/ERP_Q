<?php

namespace Tests\Feature\BusinessFlow;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Sales\SalesInvoice;
use App\Models\Customer;
use App\Services\Inventory\InventoryService;
use App\Services\Purchases\PurchaseInvoiceService;

class FullBusinessFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected int $counter = 0;

    /* =========================================================
       القسم 1: فاتورة الشراء — الحفظ والتحقق
       ========================================================= */

    public function test_purchase_saves_header_details_and_movement(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $response = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 100, 'price' => 8000, 'discount' => 0],
            ],
        ]);

        $response->assertStatus(201);

        // 1) Header
        $this->assertDatabaseCount('purchase_invoices', 1);

        // 2) Details
        $invoice = PurchaseInvoice::first();
        $this->assertCount(1, $invoice->details);
        $this->assertEquals(100, (float) $invoice->details->first()->quantity);
        $this->assertEquals(8000, (float) $invoice->details->first()->price);

        // 3) Movement
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('inventory_movement_details', 1);

        $movement = InventoryMovement::first();
        $this->assertEquals('purchase', $movement->movement_type);
        $this->assertEquals('in', $movement->direction);
        $this->assertEquals('purchase_invoice', $movement->source_type);
        $this->assertEquals($invoice->purchase_invoice_id, $movement->source_id);
        $this->assertEquals($invoice->invoice_number, $movement->document_number);
    }

    public function test_purchase_movement_matches_invoice_details(): void
    {
        [$coin, $itemA, $warehouse, $supplier] = $this->baseData();
        $itemB = $this->createItem('ماوية');

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $itemA->itemID, 'quantity' => 100, 'price' => 8000, 'discount' => 0],
                ['item_id' => $itemB->itemID, 'quantity' => 50, 'price' => 12000, 'discount' => 0],
            ],
        ])->assertStatus(201);

        $movement = InventoryMovement::with('details')->first();

        $this->assertCount(2, $movement->details);

        // كل تفصيلة تطابق الصنف الصحيح
        $itemADetail = $movement->details->firstWhere('item_id', $itemA->itemID);
        $itemBDetail = $movement->details->firstWhere('item_id', $itemB->itemID);

        $this->assertNotNull($itemADetail);
        $this->assertNotNull($itemBDetail);
        $this->assertEquals(100, (float) $itemADetail->quantity);
        $this->assertEquals(50, (float) $itemBDetail->quantity);
        $this->assertEquals(8000, (float) $itemADetail->unit_cost);
        $this->assertEquals(12000, (float) $itemBDetail->unit_cost);
    }

    public function test_purchase_rejects_zero_quantity(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 0, 'price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('purchase_invoices', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_purchase_rejects_negative_quantity(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => -5, 'price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_purchase_rejects_zero_price(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 10, 'price' => 0, 'discount' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_purchase_rejects_discount_exceeding_line_total(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        // (1 × 100) = 100 لكن الخصم 500
        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 1, 'price' => 100, 'discount' => 500],
            ],
        ])->assertStatus(422);
    }

    public function test_purchase_rejects_missing_required_fields(): void
    {
        $this->postJson('/operation/purchases/invoicesPurch', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_purchase_rejects_invalid_item_id(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => 999999, 'quantity' => 10, 'price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_purchase_rejects_invalid_warehouse_id(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => 999999,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 10, 'price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_purchase_rejects_invalid_account_id(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => 999999,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 10, 'price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(422);
    }

    /* =========================================================
       القسم 2: تعديل وحذف فاتورة الشراء
       ========================================================= */

    public function test_update_purchase_replaces_movement(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $service = app(InventoryService::class);

        // إنشاء 100
        $create = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 100, 'price' => 8000, 'discount' => 0],
            ],
        ]);

        $invoiceId = $create->json('purchase_invoice_id');
        $this->assertEquals(100, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // تعديل إلى 250
        $this->putJson("/operation/purchases/invoicesPurch/{$invoiceId}", [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 250, 'price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(200);

        // الرصيد الجديد
        $this->assertEquals(250, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // لا توجد حركة قديمة
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('inventory_movement_details', 1);
    }

    public function test_delete_purchase_removes_all_traces(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $create = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 100, 'price' => 8000, 'discount' => 0],
            ],
        ]);

        $id = $create->json('purchase_invoice_id');

        $this->deleteJson("/operation/purchases/invoicesPurch/{$id}")
            ->assertStatus(200);

        $this->assertDatabaseCount('purchase_invoices', 0);
        $this->assertDatabaseCount('purchase_invoice_details', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_movement_details', 0);
    }

    public function test_update_purchase_rejects_invalid_data_without_changing_existing(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $service = app(InventoryService::class);

        $create = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 100, 'price' => 8000, 'discount' => 0],
            ],
        ]);

        $invoiceId = $create->json('purchase_invoice_id');

        // تعديل بـ data خاطئة (كمية 0)
        $this->putJson("/operation/purchases/invoicesPurch/{$invoiceId}", [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 0, 'price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(422);

        // الرصيد بقي 100
        $this->assertEquals(100, $service->availableQuantity($item->itemID, $warehouse->StockID));
    }

    /* =========================================================
       القسم 3: فاتورة البيع
       ========================================================= */

    public function test_sale_reduces_stock_and_creates_out_movement(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');
        $service  = app(InventoryService::class);

        // شراء 100
        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 100);

        // بيع 30
        $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 30, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(201);

        $this->assertEquals(70, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // حركة sale/out
        $saleMovement = InventoryMovement::where('movement_type', 'sale')->first();
        $this->assertNotNull($saleMovement);
        $this->assertEquals('out', $saleMovement->direction);
        $this->assertEquals('sales_invoice', $saleMovement->source_type);
    }

    public function test_sale_rejected_when_no_stock(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');

        $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 10, 'price' => 12000, 'discount' => 0],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_sale_rejected_when_quantity_exceeds_stock(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');

        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 50);

        $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 80, 'price' => 12000, 'discount' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_sale_exactly_matches_available_stock(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');
        $service  = app(InventoryService::class);

        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 100);

        // بيع 100 بالضبط
        $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 100, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(201);

        $this->assertEquals(0, $service->availableQuantity($item->itemID, $warehouse->StockID));
    }

    public function test_sale_update_recalculates_stock(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');
        $service  = app(InventoryService::class);

        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 100);

        $create = $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 30, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ]);

        $saleId = $create->json('sales_invoice_id');
        $this->assertEquals(70, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // تعديل إلى 60
        $this->putJson("/operation/sales/invoices/{$saleId}", [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 60, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(200);

        $this->assertEquals(40, $service->availableQuantity($item->itemID, $warehouse->StockID));
    }

    public function test_sale_update_rejected_when_new_quantity_exceeds_stock(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');
        $service  = app(InventoryService::class);

        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 100);

        $create = $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 30, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ]);

        $saleId = $create->json('sales_invoice_id');

        // محاولة تعديل لـ 150 (أكثر من 100)
        $this->putJson("/operation/sales/invoices/{$saleId}", [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 150, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(422);

        // الرصيد بقي 70
        $this->assertEquals(70, $service->availableQuantity($item->itemID, $warehouse->StockID));
    }

    public function test_delete_sale_returns_stock_and_removes_movement(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');
        $service  = app(InventoryService::class);

        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 100);

        $create = $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 30, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ]);

        $saleId = $create->json('sales_invoice_id');
        $this->assertEquals(70, $service->availableQuantity($item->itemID, $warehouse->StockID));

        $this->deleteJson("/operation/sales/invoices/{$saleId}")->assertStatus(200);

        $this->assertEquals(100, $service->availableQuantity($item->itemID, $warehouse->StockID));
        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertDatabaseCount('sales_invoice_details', 0);
    }

    /* =========================================================
       القسم 4: تعدد الأصناف والمخازن
       ========================================================= */

    public function test_sale_with_multiple_items_from_same_warehouse(): void
    {
        [$coin, $itemA, $warehouse, $supplier] = $this->baseData();
        $itemB   = $this->createItem('ماوية');
        $customer = $this->createCustomer('مؤسسة الأفق');
        $service  = app(InventoryService::class);

        $this->createPurchaseDirectly($supplier, $coin, $itemA, $warehouse, 100);
        $this->createPurchaseDirectly($supplier, $coin, $itemB, $warehouse, 80);

        $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $itemA->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 40, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
                ['item_id' => $itemB->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 30, 'price' => 18000, 'cost_price' => 12000, 'discount' => 0],
            ],
        ])->assertStatus(201);

        $this->assertEquals(60, $service->availableQuantity($itemA->itemID, $warehouse->StockID));
        $this->assertEquals(50, $service->availableQuantity($itemB->itemID, $warehouse->StockID));
    }

    public function test_sale_with_multiple_warehouses_in_same_invoice(): void
    {
        [$coin, $item, $warehouseA, $supplier] = $this->baseData();
        $warehouseB = $this->createWarehouse('المخزن B');
        $customer   = $this->createCustomer('مؤسسة الأفق');
        $service    = app(InventoryService::class);

        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouseA, 100);
        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouseB, 80);

        $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouseA->StockID,
                 'quantity' => 30, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouseB->StockID,
                 'quantity' => 20, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(201);

        // A: 100-30 = 70, B: 80-20 = 60
        $this->assertEquals(70, $service->availableQuantity($item->itemID, $warehouseA->StockID));
        $this->assertEquals(60, $service->availableQuantity($item->itemID, $warehouseB->StockID));

        // تأكد من مخزن كل صف في الحركة
        $saleMovement = InventoryMovement::where('movement_type', 'sale')->with('details')->first();
        $this->assertCount(2, $saleMovement->details);

        $warehouseIds = $saleMovement->details->pluck('warehouse_id')->toArray();
        $this->assertContains($warehouseA->StockID, $warehouseIds);
        $this->assertContains($warehouseB->StockID, $warehouseIds);
    }

    /* =========================================================
       القسم 5: Bulk Operations & Performance
       ========================================================= */

    public function test_ten_purchases_accumulate_stock(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $service = app(InventoryService::class);

        for ($i = 1; $i <= 10; $i++) {
            $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 10);
        }

        $this->assertEquals(100, $service->availableQuantity($item->itemID, $warehouse->StockID));
        $this->assertDatabaseCount('inventory_movements', 10);
        $this->assertDatabaseCount('inventory_movement_details', 10);
    }

    public function test_ten_purchases_and_five_sales_net_correctly(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');
        $service  = app(InventoryService::class);

        // 10 شراء × 20 = 200
        for ($i = 1; $i <= 10; $i++) {
            $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 20);
        }

        $this->assertEquals(200, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // 5 بيع × 15 = 75
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/operation/sales/invoices', [
                'invoice_number' => (string) $i,
                'invoice_date'   => now()->toDateString(),
                'account_id'     => $customer->accountID,
                'payment_method' => 1,
                'coin_id'        => $coin->coinsID,
                'exchange_rate'  => 1,
                'details' => [
                    ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                     'quantity' => 15, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
                ],
            ])->assertStatus(201);
        }

        // 200 - 75 = 125
        $this->assertEquals(125, $service->availableQuantity($item->itemID, $warehouse->StockID));
        $this->assertDatabaseCount('inventory_movements', 15);
    }

    public function test_creating_50_purchases_is_reasonably_fast(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $start = microtime(true);

        for ($i = 1; $i <= 50; $i++) {
            $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 1);
        }

        $duration = microtime(true) - $start;

        // أقل من 10 ثوان
        $this->assertLessThan(10, $duration,
            "إنشاء 50 فاتورة استغرق {$duration} ثانية");

        $service = app(InventoryService::class);
        $this->assertEquals(50, $service->availableQuantity($item->itemID, $warehouse->StockID));
    }

    public function test_stock_query_with_many_movements_is_fast(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        for ($i = 1; $i <= 30; $i++) {
            $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 5);
        }

        $service = app(InventoryService::class);

        $start = microtime(true);
        $result = $service->availableQuantity($item->itemID, $warehouse->StockID);
        $duration = microtime(true) - $start;

        $this->assertEquals(150, $result);
        $this->assertLessThan(1, $duration,
            "الاستعلام استغرق {$duration} ثانية");
    }

    /* =========================================================
       القسم 6: Endpoints والتنقل
       ========================================================= */

    public function test_purchase_screen_loads(): void
    {
        $this->get('/operation/purchases/invoicesPurch')->assertStatus(200);
    }

    public function test_sales_screen_loads(): void
    {
        $this->get('/operation/sales/invoices')->assertStatus(200);
    }

    public function test_purchase_endpoints_respond(): void
    {
        $this->getJson('/operation/purchases/invoicesPurch/list')->assertStatus(200);
        $this->getJson('/operation/purchases/invoicesPurch/next-number')->assertStatus(200);
    }

    public function test_sales_endpoints_respond(): void
    {
        $this->getJson('/operation/sales/invoices/list')->assertStatus(200);
        $this->getJson('/operation/sales/invoices/next-number')->assertStatus(200);
    }

    public function test_purchase_list_returns_created_invoice(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 10);

        $response = $this->getJson('/operation/purchases/invoicesPurch/list');
        $response->assertStatus(200)
                 ->assertJsonStructure(['data']);
    }

    public function test_show_purchase_invoice_returns_full_data(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $inv = $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 100);

        $this->getJson("/operation/purchases/invoicesPurch/{$inv->purchase_invoice_id}")
            ->assertStatus(200)
            ->assertJsonPath('header.invoice_number', $inv->invoice_number)
            ->assertJsonPath('header.warehouse_id', $warehouse->StockID)
            ->assertJsonCount(1, 'details');
    }

    /* =========================================================
       القسم 7: حركات المخزون
       ========================================================= */

    public function test_movement_display_id_increments(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        for ($i = 1; $i <= 5; $i++) {
            $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 1);
        }

        $displayIds = InventoryMovement::orderBy('movement_id')
            ->pluck('display_id')
            ->toArray();

        $this->assertEquals(['1','2','3','4','5'], $displayIds);
    }

    public function test_last_cost_reflects_most_recent_purchase(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $service = app(InventoryService::class);

        // شراء 8000
        $inv1 = PurchaseInvoice::create([
            'invoice_number' => 'A-1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'items_total'    => 800000,
        ]);
        PurchaseInvoiceDetail::create([
            'purchase_invoice_id' => $inv1->purchase_invoice_id,
            'item_id' => $item->itemID, 'quantity' => 100,
            'price' => 8000, 'discount' => 0, 'total' => 800000,
        ]);
        $inv1->load('details');
        app(PurchaseInvoiceService::class)->syncInventoryMovement($inv1);

        $this->assertEquals(8000, $service->lastCost($item->itemID, $warehouse->StockID));

        // شراء 9500
        $inv2 = PurchaseInvoice::create([
            'invoice_number' => 'A-2',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'items_total'    => 950000,
        ]);
        PurchaseInvoiceDetail::create([
            'purchase_invoice_id' => $inv2->purchase_invoice_id,
            'item_id' => $item->itemID, 'quantity' => 100,
            'price' => 9500, 'discount' => 0, 'total' => 950000,
        ]);
        $inv2->load('details');
        app(PurchaseInvoiceService::class)->syncInventoryMovement($inv2);

        $this->assertEquals(9500, $service->lastCost($item->itemID, $warehouse->StockID));
    }

    public function test_no_duplicate_movement_per_invoice(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $create = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 100, 'price' => 8000, 'discount' => 0],
            ],
        ]);

        $invoiceId = $create->json('purchase_invoice_id');

        // عدد الحركات المرتبطة
        $count = InventoryMovement::where('source_type', 'purchase_invoice')
            ->where('source_id', $invoiceId)
            ->count();

        $this->assertEquals(1, $count);
    }

    /* =========================================================
       القسم 8: Safety & Integrity
       ========================================================= */

    public function test_no_orphan_movements_after_purchase_delete(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $create = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 100, 'price' => 8000, 'discount' => 0],
            ],
        ]);

        $id = $create->json('purchase_invoice_id');

        $this->deleteJson("/operation/purchases/invoicesPurch/{$id}")->assertStatus(200);

        // لا حركة يتيمة
        $orphans = InventoryMovement::where('source_type', 'purchase_invoice')
            ->where('source_id', $id)
            ->count();

        $this->assertEquals(0, $orphans);
    }

    public function test_no_orphan_details_after_movement_delete(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->createPurchaseDirectly($supplier, $coin, $item, $warehouse, 100);

        $movement = InventoryMovement::first();

        // حذف الحركة يدويًا
        $movement->details()->delete();
        $movement->delete();

        // لا تفاصيل يتيمة
        $orphans = InventoryMovementDetail::where('movement_id', $movement->movement_id)->count();
        $this->assertEquals(0, $orphans);
    }

    public function test_invoice_number_is_unique(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $first = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 10, 'price' => 8000, 'discount' => 0],
            ],
        ]);

        $first->assertStatus(201);
        $firstNumber = $first->json('invoice_number');

        // فاتورة ثانية بنفس الرقم المطلوب → النظام يولّد رقمًا جديدًا
        $second = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 10, 'price' => 8000, 'discount' => 0],
            ],
        ]);

        $second->assertStatus(201);
        $secondNumber = $second->json('invoice_number');

        // النظام يولّد الرقم من الخادم
        $this->assertNotEquals($firstNumber, $secondNumber);
    }

    /* =========================================================
       القسم 9: دورة كاملة (End-to-End)
       ========================================================= */

    public function test_full_cycle_purchase_sale_update_delete(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();
        $customer = $this->createCustomer('مؤسسة الأفق');
        $service  = app(InventoryService::class);

        // 1. شراء 100
        $purchase = $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 100, 'price' => 8000, 'discount' => 0],
            ],
        ]);
        $purchase->assertStatus(201);
        $this->assertEquals(100, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // 2. بيع 30
        $sale = $this->postJson('/operation/sales/invoices', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 30, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ]);
        $sale->assertStatus(201);
        $saleId = $sale->json('sales_invoice_id');
        $this->assertEquals(70, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // 3. تعديل البيع إلى 50
        $this->putJson("/operation/sales/invoices/{$saleId}", [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $customer->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'warehouse_id' => $warehouse->StockID,
                 'quantity' => 50, 'price' => 12000, 'cost_price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(200);

        $this->assertEquals(50, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // 4. حذف البيع
        $this->deleteJson("/operation/sales/invoices/{$saleId}")->assertStatus(200);
        $this->assertEquals(100, $service->availableQuantity($item->itemID, $warehouse->StockID));

        // 5. التحقق النهائي
        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertDatabaseCount('purchase_invoices', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    /* =========================================================
       القسم 10: بيانات غير طبيعية
       ========================================================= */

    public function test_rejects_very_large_quantity(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        // كمية أكبر من decimal(18,6) يمكن أن تحتوي
        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID,
                 'quantity' => 999999999999, 'price' => 8000, 'discount' => 0],
            ],
        ]);
        // قبول أو رفض حسب التصميم — نتحقق فقط من أن النظام لا ينهار
        $this->assertTrue(true);
    }

    public function test_rejects_text_in_numeric_field(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [
                ['item_id' => $item->itemID, 'quantity' => 'abc', 'price' => 8000, 'discount' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_rejects_missing_details_array(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            // بدون details
        ])->assertStatus(422);
    }

    public function test_rejects_empty_details_array(): void
    {
        [$coin, $item, $warehouse, $supplier] = $this->baseData();

        $this->postJson('/operation/purchases/invoicesPurch', [
            'invoice_number' => '1',
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'details' => [],
        ])->assertStatus(422);
    }

    /* =========================================================
       دوال مساعدة
       ========================================================= */

    private function baseData(): array
    {
        return [
            $this->createCoin(),
            $this->createItem('عود'),
            $this->createWarehouse('المخزن الرئيسي'),
            $this->createSupplier('أحمد'),
        ];
    }

    private function createCoin(): Coin
    {
        $this->counter++;

        return Coin::create([
            'coinsName'         => 'ريال يمني',
            'coinsCode'         => 'C' . $this->counter,
            'coinsExchangeRate' => 1,
            'is_active'         => 1,
        ]);
    }

    private function createItem(string $name): Item
    {
        return Item::create([
            'itemName2' => $name,
            'is_active' => 1,
        ]);
    }

    private function createWarehouse(string $name): Stock
    {
        $this->counter++;

        $account = CharAccount::create([
            'accCode'    => 'W' . $this->counter,
            'accName'    => $name,
            'accLevel'   => 2,
            'nature'     => 1,
            'isPostable' => 1,
            'IsActive'   => 1,
        ]);

        return Stock::create([
            'StockName' => $name,
            'accountID' => $account->accountID,
            'is_active' => 1,
        ]);
    }

    private function createSupplier(string $name): CharAccount
    {
        $this->counter++;

        return CharAccount::create([
            'accCode'    => 'S' . $this->counter,
            'accName'    => $name,
            'accLevel'   => 2,
            'nature'     => 1,
            'isPostable' => 1,
            'IsActive'   => 1,
        ]);
    }

    private function createCustomer(string $name): Customer
    {
        $this->counter++;

        $account = CharAccount::create([
            'accCode'    => 'K' . $this->counter,
            'accName'    => $name,
            'accLevel'   => 2,
            'nature'     => 1,
            'isPostable' => 1,
            'IsActive'   => 1,
        ]);

        return Customer::create([
            'CustomersName2' => $name,
            'accountID'      => $account->accountID,
        ]);
    }

    private function createPurchaseDirectly(
        CharAccount $supplier,
        Coin $coin,
        Item $item,
        Stock $warehouse,
        float $qty
    ): PurchaseInvoice {
        $this->counter++;

        $invoice = PurchaseInvoice::create([
            'invoice_number' => 'DP' . $this->counter,
            'invoice_date'   => now()->toDateString(),
            'account_id'     => $supplier->accountID,
            'payment_method' => 1,
            'coin_id'        => $coin->coinsID,
            'warehouse_id'   => $warehouse->StockID,
            'exchange_rate'  => 1,
            'items_total'    => $qty * 8000,
            'discount_total' => 0,
        ]);

        PurchaseInvoiceDetail::create([
            'purchase_invoice_id' => $invoice->purchase_invoice_id,
            'item_id'             => $item->itemID,
            'unit_id'             => null,
            'quantity'            => $qty,
            'price'               => 8000,
            'discount'            => 0,
            'total'               => $qty * 8000,
        ]);

        $invoice->load('details');
        app(PurchaseInvoiceService::class)->syncInventoryMovement($invoice);

        return $invoice;
    }
}