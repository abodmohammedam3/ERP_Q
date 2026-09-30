<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\Sales\SalesInvoice;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Sales\SalesReturn;
use App\Models\Purchases\PurchaseReturn;
use App\Services\Sales\SalesReturnService;
use App\Services\Purchases\PurchaseReturnService;

class ReturnsSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected int $systemCurrencyId;
    protected int $supplierId;
    protected int $customerId;
    protected int $warehouseId;
    protected int $itemId;
    protected int $typeId;
    protected int $unitId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->createTestData();
    }

    protected function createTestData(): void
    {
        $this->systemCurrencyId = DB::table('coins')->insertGetId([
            'coinsName'         => 'ريال يمني',
            'coinsCode'         => 'YER',
            'coinsExchangeRate' => 1,
            'coinsSystem'       => 1,
        ]);

        $this->supplierId = DB::table('characcount')->insertGetId([
            'accName' => 'مورد اختبار',
            'accCode' => 'S001',
        ]);

        $this->customerId = DB::table('characcount')->insertGetId([
            'accName' => 'عميل اختبار',
            'accCode' => 'C001',
        ]);

        $this->warehouseId = DB::table('stocks')->insertGetId([
            'StockName' => 'مخزن اختبار',
        ]);

        $this->itemId = DB::table('Items')->insertGetId([
            'itemName2' => 'صنف اختبار',
        ]);

        $this->typeId = DB::table('type')->insertGetId([
            'name' => 'نوع اختبار',
        ]);

        $this->unitId = DB::table('units')->insertGetId([
            'UnitName' => 'حبة',
        ]);
    }

    protected function createPurchaseInvoice(int $quantity = 100, float $price = 100): int
    {
        $payload = [
            'invoice_number'     => 'PUR-001',
            'invoice_date'       => now()->format('Y-m-d'),
            'account_id'         => $this->supplierId,
            'payment_method'     => 1,
            'coin_id'            => $this->systemCurrencyId,
            'warehouse_id'       => $this->warehouseId,
            'exchange_rate'      => 1,
            'expenses'           => 0,
            'tax_cost'           => 0,
            'transportation'     => 0,
            'other_cost'         => 0,
            'details'            => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'code'     => 'CODE-01',
                    'quantity' => $quantity,
                    'price'    => $price,
                    'discount' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $response->assertStatus(201);
        return $response->json('purchase_invoice_id');
    }

    protected function createSalesInvoice(int $quantity = 40, float $price = 150): int
    {
        $payload = [
            'invoice_number'     => 'SAL-001',
            'invoice_date'       => now()->format('Y-m-d'),
            'account_id'         => $this->customerId,
            'payment_method'     => 1,
            'coin_id'            => $this->systemCurrencyId,
            'exchange_rate'      => 1,
            'details'            => [
                [
                    'item_id'      => $this->itemId,
                    'type_id'      => $this->typeId,
                    'unit_id'      => $this->unitId,
                    'warehouse_id' => $this->warehouseId,
                    'code'         => 'CODE-01',
                    'quantity'     => $quantity,
                    'price'        => $price,
                    'cost_price'   => 100,
                    'discount'     => 0,
                ],
            ],
        ];

        $response = $this->postJson('/operation/sales/invoices', $payload);
        $response->assertStatus(201);
        return $response->json('sales_invoice_id');
    }

    // =========================================================
    // SALES RETURN TESTS
    // =========================================================

    public function test_01_create_sales_return_syncs_inventory_and_validates_quantity(): void
    {
        $this->createPurchaseInvoice(100, 100);
        $salesInvoiceId = $this->createSalesInvoice(40, 150);

        $salesInvoice = SalesInvoice::with('details')->find($salesInvoiceId);
        $detail = $salesInvoice->details->first();

        $payload = [
            'return_number'             => 'RET-S-001',
            'return_date'               => now()->format('Y-m-d'),
            'original_sales_invoice_id' => $salesInvoiceId,
            'account_id'                 => $this->customerId,
            'payment_method'             => 1,
            'coin_id'                    => $this->systemCurrencyId,
            'exchange_rate'              => 1,
            'details'                    => [
                [
                    'sales_invoice_detail_id' => $detail->sales_invoice_detail_id,
                    'quantity'                 => 15,
                    'price'                    => 150,
                    'discount'                 => 0,
                ],
            ],
        ];

        $response = $this->postJson('/operation/sales/returns', $payload);
        $response->assertStatus(201);

        $returnId = $response->json('sales_return_id');
        $this->assertDatabaseHas('sales_returns', ['sales_return_id' => $returnId]);

        // الحركة المخزنية المضافة عند إرجاع البيع تكون إدخال (in) من نوع sale_return
        $movement = DB::table('inventory_movements')
            ->where('source_type', 'sales_return')
            ->where('source_id', $returnId)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('in', $movement->direction);
        $this->assertEquals('sale_return', $movement->movement_type);

        $movementDetail = DB::table('inventory_movement_details')
            ->where('movement_id', $movement->movement_id)
            ->first();

        $this->assertEquals(15, (float) $movementDetail->quantity);

        // التحقق من الكمية المتبقية للإرجاع = 40 - 15 = 25
        $availResponse = $this->getJson("/operation/sales/returns/available-quantity?sales_invoice_detail_id={$detail->sales_invoice_detail_id}");
        $availResponse->assertStatus(200);
        $this->assertEquals(25, (float) $availResponse->json('available'));
    }

    public function test_02_sales_return_exceeding_available_quantity_fails(): void
    {
        $this->createPurchaseInvoice(100, 100);
        $salesInvoiceId = $this->createSalesInvoice(40, 150);

        $salesInvoice = SalesInvoice::with('details')->find($salesInvoiceId);
        $detail = $salesInvoice->details->first();

        $payload = [
            'return_number'             => 'RET-S-002',
            'return_date'               => now()->format('Y-m-d'),
            'original_sales_invoice_id' => $salesInvoiceId,
            'account_id'                 => $this->customerId,
            'payment_method'             => 1,
            'coin_id'                    => $this->systemCurrencyId,
            'exchange_rate'              => 1,
            'details'                    => [
                [
                    'sales_invoice_detail_id' => $detail->sales_invoice_detail_id,
                    'quantity'                 => 50, // أكبر من 40
                    'price'                    => 150,
                    'discount'                 => 0,
                ],
            ],
        ];

        $response = $this->postJson('/operation/sales/returns', $payload);
        $response->assertStatus(422);
    }

    public function test_03_update_and_delete_sales_return_updates_inventory_movements(): void
    {
        $this->createPurchaseInvoice(100, 100);
        $salesInvoiceId = $this->createSalesInvoice(40, 150);

        $salesInvoice = SalesInvoice::with('details')->find($salesInvoiceId);
        $detail = $salesInvoice->details->first();

        // 1) إنشاء
        $payload = [
            'return_number'             => 'RET-S-003',
            'return_date'               => now()->format('Y-m-d'),
            'original_sales_invoice_id' => $salesInvoiceId,
            'account_id'                 => $this->customerId,
            'payment_method'             => 1,
            'coin_id'                    => $this->systemCurrencyId,
            'exchange_rate'              => 1,
            'details'                    => [
                [
                    'sales_invoice_detail_id' => $detail->sales_invoice_detail_id,
                    'quantity'                 => 10,
                    'price'                    => 150,
                    'discount'                 => 0,
                ],
            ],
        ];

        $response = $this->postJson('/operation/sales/returns', $payload);
        $response->assertStatus(201);
        $returnId = $response->json('sales_return_id');

        // 2) تعديل الكمية إلى 20
        $updatePayload = array_merge($payload, [
            'details' => [
                [
                    'sales_invoice_detail_id' => $detail->sales_invoice_detail_id,
                    'quantity'                 => 20,
                    'price'                    => 150,
                    'discount'                 => 0,
                ],
            ],
        ]);

        $updateResponse = $this->putJson("/operation/sales/returns/{$returnId}", $updatePayload);
        $updateResponse->assertStatus(200);

        $movement = DB::table('inventory_movements')
            ->where('source_type', 'sales_return')
            ->where('source_id', $returnId)
            ->first();

        $movementDetail = DB::table('inventory_movement_details')
            ->where('movement_id', $movement->movement_id)
            ->first();

        $this->assertEquals(20, (float) $movementDetail->quantity);

        // 3) حذف المرتجع
        $deleteResponse = $this->deleteJson("/operation/sales/returns/{$returnId}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('sales_returns', ['sales_return_id' => $returnId]);
        $this->assertDatabaseMissing('inventory_movements', [
            'source_type' => 'sales_return',
            'source_id'   => $returnId,
        ]);
    }

    // =========================================================
    // PURCHASE RETURN TESTS
    // =========================================================

    public function test_04_create_purchase_return_syncs_inventory_and_copies_original_unit_cost(): void
    {
        $purchaseInvoiceId = $this->createPurchaseInvoice(50, 120);
        $purchaseInvoice = PurchaseInvoice::with('details')->find($purchaseInvoiceId);
        $detail = $purchaseInvoice->details->first();

        $payload = [
            'return_number'                => 'RET-P-001',
            'return_date'                  => now()->format('Y-m-d'),
            'original_purchase_invoice_id' => $purchaseInvoiceId,
            'account_id'                    => $this->supplierId,
            'payment_method'                => 1,
            'coin_id'                       => $this->systemCurrencyId,
            'warehouse_id'                  => $this->warehouseId,
            'exchange_rate'                 => 1,
            'details'                       => [
                [
                    'purchase_invoice_detail_id' => $detail->purchase_invoice_detail_id,
                    'quantity'                    => 10,
                    'price'                       => 120,
                    'discount'                    => 0,
                ],
            ],
        ];

        $response = $this->postJson('/operation/purchases/returns', $payload);
        $response->assertStatus(201);

        $returnId = $response->json('purchase_return_id');
        $this->assertDatabaseHas('purchase_returns', ['purchase_return_id' => $returnId]);

        // الحركة المخزنية لمرتجع الشراء تكون إخراج (out) من نوع purchase_return
        $movement = DB::table('inventory_movements')
            ->where('source_type', 'purchase_return')
            ->where('source_id', $returnId)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('out', $movement->direction);
        $this->assertEquals('purchase_return', $movement->movement_type);

        $movementDetail = DB::table('inventory_movement_details')
            ->where('movement_id', $movement->movement_id)
            ->first();

        $this->assertEquals(10, (float) $movementDetail->quantity);
        $this->assertEquals(120, (float) $movementDetail->unit_cost);

        // الكمية المتاحة للإرجاع = 50 - 10 = 40
        $availResponse = $this->getJson("/operation/purchases/returns/available-quantity?purchase_invoice_detail_id={$detail->purchase_invoice_detail_id}");
        $availResponse->assertStatus(200);
        $this->assertEquals(40, (float) $availResponse->json('available'));
    }

    public function test_05_purchase_return_exceeding_available_quantity_fails(): void
    {
        $purchaseInvoiceId = $this->createPurchaseInvoice(50, 120);
        $purchaseInvoice = PurchaseInvoice::with('details')->find($purchaseInvoiceId);
        $detail = $purchaseInvoice->details->first();

        $payload = [
            'return_number'                => 'RET-P-002',
            'return_date'                  => now()->format('Y-m-d'),
            'original_purchase_invoice_id' => $purchaseInvoiceId,
            'account_id'                    => $this->supplierId,
            'payment_method'                => 1,
            'coin_id'                       => $this->systemCurrencyId,
            'warehouse_id'                  => $this->warehouseId,
            'exchange_rate'                 => 1,
            'details'                       => [
                [
                    'purchase_invoice_detail_id' => $detail->purchase_invoice_detail_id,
                    'quantity'                    => 60, // أكبر من 50
                    'price'                       => 120,
                    'discount'                    => 0,
                ],
            ],
        ];

        $response = $this->postJson('/operation/purchases/returns', $payload);
        $response->assertStatus(422);
    }

    public function test_06_update_and_delete_purchase_return_updates_inventory_movements(): void
    {
        $purchaseInvoiceId = $this->createPurchaseInvoice(50, 120);
        $purchaseInvoice = PurchaseInvoice::with('details')->find($purchaseInvoiceId);
        $detail = $purchaseInvoice->details->first();

        // 1) إنشاء
        $payload = [
            'return_number'                => 'RET-P-003',
            'return_date'                  => now()->format('Y-m-d'),
            'original_purchase_invoice_id' => $purchaseInvoiceId,
            'account_id'                    => $this->supplierId,
            'payment_method'                => 1,
            'coin_id'                       => $this->systemCurrencyId,
            'warehouse_id'                  => $this->warehouseId,
            'exchange_rate'                 => 1,
            'details'                       => [
                [
                    'purchase_invoice_detail_id' => $detail->purchase_invoice_detail_id,
                    'quantity'                    => 15,
                    'price'                       => 120,
                    'discount'                    => 0,
                ],
            ],
        ];

        $response = $this->postJson('/operation/purchases/returns', $payload);
        $response->assertStatus(201);
        $returnId = $response->json('purchase_return_id');

        // 2) تعديل إلى 25
        $updatePayload = array_merge($payload, [
            'details' => [
                [
                    'purchase_invoice_detail_id' => $detail->purchase_invoice_detail_id,
                    'quantity'                    => 25,
                    'price'                       => 120,
                    'discount'                    => 0,
                ],
            ],
        ]);

        $updateResponse = $this->putJson("/operation/purchases/returns/{$returnId}", $updatePayload);
        $updateResponse->assertStatus(200);

        $movement = DB::table('inventory_movements')
            ->where('source_type', 'purchase_return')
            ->where('source_id', $returnId)
            ->first();

        $movementDetail = DB::table('inventory_movement_details')
            ->where('movement_id', $movement->movement_id)
            ->first();

        $this->assertEquals(25, (float) $movementDetail->quantity);

        // 3) حذف
        $deleteResponse = $this->deleteJson("/operation/purchases/returns/{$returnId}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('purchase_returns', ['purchase_return_id' => $returnId]);
        $this->assertDatabaseMissing('inventory_movements', [
            'source_type' => 'purchase_return',
            'source_id'   => $returnId,
        ]);
    }
}
