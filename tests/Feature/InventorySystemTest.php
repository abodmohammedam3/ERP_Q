<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Sales\SalesInvoice;
use App\Services\Inventory\InventoryService;

/**
 * ============================================================
 *  اختبار شامل لنظام المخزون — المرحلتان 1 و 2
 * ============================================================
 *
 *  يغطي 14 سيناريو:
 *   1. فاتورة شراء بعملة أجنبية بسعر صرف مخصص
 *   2. فاتورة شراء بالعملة الأساسية
 *   3. تعديل فاتورة شراء (إعادة إنشاء الحركة)
 *   4. حذف فاتورة شراء (Observer)
 *   5. توزيع التكاليف الإضافية (Landed Cost)
 *   6. حركة توريد يدوية
 *   7. حركة صرف يدوية
 *   8. حماية source_type في الحركات اليدوية
 *   9. البحث في الحركات (اختبار endpoint)
 *  10. كاش availableQuantity
 *  11. تنظيف other_cost_description
 *  12. تخزين exchange_rate بشكل صحيح
 *  13. التكامل: شراء → بيع
 *  14. فشل البيع بسبب نقص الرصيد
 *
 * ⚠️ ملاحظة: أسماء الأعمدة مبنية على تحليل الكود المرفق.
 *    إذا اختلف schema الفعلي، عدّل insertGetId() في createTestData().
 * ============================================================
 */
class InventorySystemTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================
    // معرّفات بيانات الاختبار
    // =========================================================

    protected int $systemCurrencyId;
    protected int $foreignCurrencyId;
    protected int $supplierId;
    protected int $customerId;
    protected int $paymentAccountId;
    protected int $warehouseId;
    protected int $itemId;
    protected int $typeId;
    protected int $unitId;

    // =========================================================
    // تهيئة
    // =========================================================

    protected function setUp(): void
    {
        parent::setUp();

        // تعطيل كل الـ middleware (auth, csrf, session)
        $this->withoutMiddleware();

        $this->createTestData();
    }

    /**
     * إنشاء بيانات الاختبار الأساسية
     */
    protected function createTestData(): void
    {
        // العملة الأساسية (YER)
        $this->systemCurrencyId = DB::table('coins')->insertGetId([
            'coinsName'         => 'ريال يمني',
            'coinsCode'         => 'YER',
            'coinsExchangeRate' => 1,
            'coinsSystem'       => 1,
        ]);

        // عملة أجنبية (SAR)
        $this->foreignCurrencyId = DB::table('coins')->insertGetId([
            'coinsName'         => 'ريال سعودي',
            'coinsCode'         => 'SAR',
            'coinsExchangeRate' => 140,
            'coinsSystem'       => 0,
        ]);

        // المورد
        $this->supplierId = DB::table('characcount')->insertGetId([
            'accName' => 'مورد اختبار',
            'accCode' => 'S001',
        ]);

        // العميل
        $this->customerId = DB::table('characcount')->insertGetId([
            'accName' => 'عميل اختبار',
            'accCode' => 'C001',
        ]);

        // حساب الدفع (صندوق)
        $this->paymentAccountId = DB::table('characcount')->insertGetId([
            'accName' => 'صندوق اختبار',
            'accCode' => 'B001',
        ]);

        // المخزن
        $this->warehouseId = DB::table('stocks')->insertGetId([
            'StockName' => 'مخزن اختبار',
        ]);

        // الصنف
        $this->itemId = DB::table('Items')->insertGetId([
            'itemName2' => 'صنف اختبار',
        ]);

        // النوع
        $this->typeId = DB::table('type')->insertGetId([
            'name' => 'نوع اختبار',
        ]);

        // الوحدة
        $this->unitId = DB::table('units')->insertGetId([
            'UnitName' => 'حبة',
        ]);
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * إنشاء فاتورة شراء عبر HTTP
     */
    protected function makePurchasePayload(array $overrides = []): array
    {
        $defaults = [
            'invoice_number'         => '1',
            'invoice_date'           => now()->format('Y-m-d'),
            'account_id'             => $this->supplierId,
            'payment_method'         => 1,
            'payment_account_id'     => null,
            'coin_id'                => $this->systemCurrencyId,
            'warehouse_id'           => $this->warehouseId,
            'exchange_rate'          => 1,
            'expenses'               => 0,
            'tax_cost'               => 0,
            'transportation'         => 0,
            'other_cost'             => 0,
            'other_cost_description' => null,
            'statement'              => null,
            'reference'              => null,
            'details'                => [
                [
                    'item_id'   => $this->itemId,
                    'type_id'   => $this->typeId,
                    'unit_id'   => $this->unitId,
                    'code'      => 'CODE-01',
                    'quantity'  => 10,
                    'price'     => 100,
                    'discount'  => 0,
                ],
            ],
        ];

        // دمج عميق للـ details إذا تم تمريرها
        if (isset($overrides['details'])) {
            $defaults['details'] = $overrides['details'];
            unset($overrides['details']);
        }

        return array_merge($defaults, $overrides);
    }

    /**
     * إنشاء فاتورة بيع عبر HTTP
     */
    protected function makeSalePayload(array $overrides = []): array
    {
        $defaults = [
            'invoice_number'     => '1',
            'invoice_date'       => now()->format('Y-m-d'),
            'account_id'         => $this->customerId,
            'payment_method'     => 1,
            'payment_account_id' => null,
            'coin_id'            => $this->systemCurrencyId,
            'exchange_rate'      => 1,
            'statement'          => null,
            'reference'          => null,
            'details'            => [
                [
                    'item_id'      => $this->itemId,
                    'type_id'      => $this->typeId,
                    'unit_id'      => $this->unitId,
                    'warehouse_id' => $this->warehouseId,
                    'code'         => 'CODE-01',
                    'quantity'     => 5,
                    'price'        => 150,
                    'cost_price'   => 100,
                    'discount'     => 0,
                ],
            ],
        ];

        if (isset($overrides['details'])) {
            $defaults['details'] = $overrides['details'];
            unset($overrides['details']);
        }

        return array_merge($defaults, $overrides);
    }

    /**
     * إنشاء حركة يدوية عبر HTTP
     */
    protected function makeMovementPayload(array $overrides = []): array
    {
        $defaults = [
            'movement_type'   => 'supply',
            'movement_date'   => now()->format('Y-m-d'),
            'warehouse_id'    => $this->warehouseId,
            'document_number' => 'DOC-001',
            'statement'       => 'اختبار',
            'source_type'     => null,
            'source_id'       => null,
            'details'         => [
                [
                    'item_id'      => $this->itemId,
                    'type_id'      => $this->typeId,
                    'unit_id'      => $this->unitId,
                    'code'         => 'M-01',
                    'warehouse_id' => $this->warehouseId,
                    'quantity'     => 100,
                    'unit_cost'    => 100,
                    'min_price'    => 90,
                    'max_price'    => 110,
                    'sale_price'   => 100,
                ],
            ],
        ];

        if (isset($overrides['details'])) {
            $defaults['details'] = $overrides['details'];
            unset($overrides['details']);
        }

        return array_merge($defaults, $overrides);
    }

    // =========================================================
    // TEST 1: فاتورة شراء بعملة أجنبية بسعر صرف مخصص
    // =========================================================

    public function test_01_purchase_invoice_foreign_currency_custom_rate(): void
    {
        $payload = $this->makePurchasePayload([
            'coin_id'       => $this->foreignCurrencyId,
            'exchange_rate' => 200,
            'details'       => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'code'     => 'C1',
                    'quantity' => 10,
                    'price'    => 50,
                    'discount' => 0,
                ],
            ],
        ]);

        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $response->assertStatus(201);

        $invoiceId = $response->json('purchase_invoice_id');

        // 1) سعر الصرف محفوظ = 200
        $invoice = DB::table('purchase_invoices')->find($invoiceId);
        $this->assertEquals(200, (float) $invoice->exchange_rate);

        // 2) total_in_base_currency = 500 × 200 = 100,000
        $this->assertEquals(100000, (float) $invoice->total_in_base_currency);

        // 3) سعر صرف SAR في جدول coins لم يتغير
        $sar = DB::table('coins')->find($this->foreignCurrencyId);
        $this->assertEquals(140, (float) $sar->coinsExchangeRate, 'SAR rate should remain 140');

        // 4) حركة المخزون تحمل unit_cost = 50 × 200 = 10,000
        $movement = DB::table('inventory_movements')
            ->where('source_type', 'purchase_invoice')
            ->where('source_id', $invoiceId)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('in', $movement->direction);
        $this->assertEquals('purchase', $movement->movement_type);

        $detail = DB::table('inventory_movement_details')
            ->where('movement_id', $movement->movement_id)
            ->first();

        $this->assertEquals(10000, (float) $detail->unit_cost);
        $this->assertEquals(100000, (float) $detail->total);
    }

    // =========================================================
    // TEST 2: فاتورة شراء بالعملة الأساسية
    // =========================================================

    public function test_02_purchase_invoice_system_currency(): void
    {
        $payload = $this->makePurchasePayload([
            'coin_id'       => $this->systemCurrencyId,
            'exchange_rate' => 1,
            'details'       => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'code'     => 'C2',
                    'quantity' => 5,
                    'price'    => 1000,
                    'discount' => 0,
                ],
            ],
        ]);

        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $response->assertStatus(201);

        $invoiceId = $response->json('purchase_invoice_id');
        $invoice   = DB::table('purchase_invoices')->find($invoiceId);

        // exchange_rate = 1
        $this->assertEquals(1, (float) $invoice->exchange_rate);

        // total_in_base = total_in_invoice = 5 × 1000 = 5000
        $this->assertEquals(5000, (float) $invoice->total_in_base_currency);
    }

    // =========================================================
    // TEST 3: تعديل فاتورة شراء
    // =========================================================

    public function test_03_update_purchase_invoice(): void
    {
        // 1) إنشاء فاتورة
        $payload = $this->makePurchasePayload([
            'coin_id'       => $this->foreignCurrencyId,
            'exchange_rate' => 200,
            'details'       => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'quantity' => 10,
                    'price'    => 50,
                    'discount' => 0,
                ],
            ],
        ]);

        $createResponse = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $createResponse->assertStatus(201);
        $invoiceId = $createResponse->json('purchase_invoice_id');

        // 2) تسجيل movement_id الأصلي
        $originalMovement = DB::table('inventory_movements')
            ->where('source_type', 'purchase_invoice')
            ->where('source_id', $invoiceId)
            ->first();
        $this->assertNotNull($originalMovement);

        // 3) تعديل الكمية
        $updatePayload = array_merge($payload, [
            'details' => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'quantity' => 20,
                    'price'    => 50,
                    'discount' => 0,
                ],
            ],
        ]);

        $updateResponse = $this->putJson(
            "/operation/purchases/invoicesPurch/{$invoiceId}",
            $updatePayload
        );
        $updateResponse->assertStatus(200);

        // 4) تأكيد:
        //    - items_total = 1000
        //    - total_in_base = 1000 × 200 = 200,000
        $invoice = DB::table('purchase_invoices')->find($invoiceId);
        $this->assertEquals(1000, (float) $invoice->items_total);
        $this->assertEquals(200000, (float) $invoice->total_in_base_currency);

        // 5) الحركة القديمة حُذفت — توجد حركة واحدة فقط
        $movementsCount = DB::table('inventory_movements')
            ->where('source_type', 'purchase_invoice')
            ->where('source_id', $invoiceId)
            ->count();
        $this->assertEquals(1, $movementsCount, 'Only one movement should exist');

        // 6) الحركة الجديدة تحمل الكمية الجديدة
        $newMovement = DB::table('inventory_movements')
            ->where('source_type', 'purchase_invoice')
            ->where('source_id', $invoiceId)
            ->first();

        $newDetail = DB::table('inventory_movement_details')
            ->where('movement_id', $newMovement->movement_id)
            ->first();

        $this->assertEquals(20, (float) $newDetail->quantity);
        $this->assertEquals(10000, (float) $newDetail->unit_cost);
    }

    // =========================================================
    // TEST 4: حذف فاتورة شراء (Observer)
    // =========================================================

    public function test_04_delete_purchase_invoice_observer(): void
    {
        // 1) إنشاء فاتورة
        $payload = $this->makePurchasePayload();
        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $response->assertStatus(201);
        $invoiceId = $response->json('purchase_invoice_id');

        $this->assertDatabaseHas('inventory_movements', [
            'source_type' => 'purchase_invoice',
            'source_id'   => $invoiceId,
        ]);

        // 2) حذف مباشر عبر Model (لتشغيل Observer)
        $invoice = PurchaseInvoice::find($invoiceId);
        $this->assertNotNull($invoice);
        $invoice->delete();

        // 3) لا حركات متبقية
        $this->assertDatabaseMissing('inventory_movements', [
            'source_type' => 'purchase_invoice',
            'source_id'   => $invoiceId,
        ]);

        // 4) لا تفاصيل متبقية
        $movementIds = DB::table('inventory_movements')
            ->where('source_type', 'purchase_invoice')
            ->where('source_id', $invoiceId)
            ->pluck('movement_id')
            ->toArray();
        $this->assertEmpty($movementIds);
    }

    // =========================================================
    // TEST 5: توزيع التكاليف الإضافية (Landed Cost)
    // =========================================================

    public function test_05_landed_cost_distribution(): void
    {
        $payload = $this->makePurchasePayload([
            'expenses'       => 500,
            'tax_cost'       => 200,
            'transportation' => 300,
            'other_cost'     => 0,
            'exchange_rate'  => 1,
            'details'        => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'quantity' => 10,
                    'price'    => 100,
                    'discount' => 0,
                ],
            ],
        ]);

        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $response->assertStatus(201);
        $invoiceId = $response->json('purchase_invoice_id');

        // الحساب المتوقع:
        //   net = 10 × 100 = 1000
        //   extraCosts = 500 + 200 + 300 = 1000
        //   totalFC = 2000
        //   shareFC = (1000/1000) × 1000 = 1000
        //   landedFC = 2000
        //   unitCost = 2000 / 10 = 200
        //   rate = 1 → unitCostBC = 200

        $movement = DB::table('inventory_movements')
            ->where('source_type', 'purchase_invoice')
            ->where('source_id', $invoiceId)
            ->first();

        $detail = DB::table('inventory_movement_details')
            ->where('movement_id', $movement->movement_id)
            ->first();

        $this->assertEquals(200, (float) $detail->unit_cost, 'unit_cost should include extra costs');
        $this->assertEquals(2000, (float) $detail->total);
        $this->assertEquals(2000, (float) $movement->total);

        // total_in_base_currency
        $invoice = DB::table('purchase_invoices')->find($invoiceId);
        $this->assertEquals(2000, (float) $invoice->total_in_base_currency);
    }

    // =========================================================
    // TEST 6: حركة توريد يدوية
    // =========================================================

    public function test_06_manual_supply_movement(): void
    {
        $payload = $this->makeMovementPayload([
            'movement_type' => 'supply',
        ]);

        $response = $this->postJson('/operation/movements', $payload);
        $response->assertStatus(201);

        $movementId = $response->json('movement_id');
        $movement = DB::table('inventory_movements')->find($movementId);

        $this->assertEquals('supply', $movement->movement_type);
        $this->assertEquals('in', $movement->direction);
        $this->assertNull($movement->source_type);
        $this->assertNull($movement->source_id);
        $this->assertEquals(10000, (float) $movement->total); // 100 × 100
    }

    // =========================================================
    // TEST 7: حركة صرف يدوية
    // =========================================================

    public function test_07_manual_issue_movement(): void
    {
        $payload = $this->makeMovementPayload([
            'movement_type' => 'issue',
            'details'       => [
                [
                    'item_id'      => $this->itemId,
                    'type_id'      => $this->typeId,
                    'unit_id'      => $this->unitId,
                    'code'         => 'M-02',
                    'warehouse_id' => $this->warehouseId,
                    'quantity'     => 5,
                    'unit_cost'    => 100,
                ],
            ],
        ]);

        $response = $this->postJson('/operation/movements', $payload);
        $response->assertStatus(201);

        $movement = DB::table('inventory_movements')
            ->find($response->json('movement_id'));

        $this->assertEquals('issue', $movement->movement_type);
        $this->assertEquals('out', $movement->direction);
    }

    // =========================================================
    // TEST 8: حماية source_type/source_id
    // =========================================================

    public function test_08_source_type_protection(): void
    {
        $payload = $this->makeMovementPayload([
            'source_type' => 'purchase_invoice',
            'source_id'   => 9999,
        ]);

        // المحاولة الأولى: نجاح
        $first = $this->postJson('/operation/movements', $payload);
        $first->assertStatus(201);

        // المحاولة الثانية: رفض بسبب التكرار
        $second = $this->postJson('/operation/movements', $payload);
        $second->assertStatus(409);
        $this->assertStringContainsString('مسبقًا', $second->json('message'));
    }

    // =========================================================
    // TEST 9: البحث في الحركات (endpoint)
    // =========================================================

    public function test_09_search_endpoints_return_valid_data(): void
    {
        // إنشاء حركة
        $payload = $this->makeMovementPayload();
        $this->postJson('/operation/movements', $payload)->assertStatus(201);

        // البحث
        $response = $this->getJson('/operation/movements/list');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'movement_id',
                    'display_id',
                    'movement_type',
                    'direction',
                    'movement_date',
                    'warehouse_name',
                    'total',
                ],
            ],
        ]);

        $this->assertNotEmpty($response->json('data'));
    }

    // =========================================================
    // TEST 10: كاش availableQuantity
    // =========================================================

    public function test_10_available_quantity_cache(): void
    {
        // إنشاء حركة توريد
        $payload = $this->makeMovementPayload([
            'details' => [
                [
                    'item_id'      => $this->itemId,
                    'type_id'      => $this->typeId,
                    'unit_id'      => $this->unitId,
                    'warehouse_id' => $this->warehouseId,
                    'quantity'     => 100,
                    'unit_cost'    => 50,
                ],
            ],
        ]);
        $this->postJson('/operation/movements', $payload)->assertStatus(201);

        // احسب عدد الاستعلامات
        DB::enableQueryLog();
        DB::flushQueryLog();

        /** @var InventoryService $service */
        $service = app(InventoryService::class);

        $q1 = $service->availableQuantity($this->itemId, $this->warehouseId);
        $q2 = $service->availableQuantity($this->itemId, $this->warehouseId);
        $q3 = $service->availableQuantity($this->itemId, $this->warehouseId);

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        // 1) القيمة صحيحة
        $this->assertEquals(100, $q1);
        $this->assertEquals(100, $q2);
        $this->assertEquals(100, $q3);

        // 2) عدد الاستعلامات = 2 فقط (بدل 6)
        $this->assertLessThanOrEqual(
            2,
            $queryCount,
            "Expected ≤ 2 queries thanks to cache, got {$queryCount}"
        );
    }

    // =========================================================
    // TEST 11: تنظيف other_cost_description
    // =========================================================

    public function test_11a_other_cost_without_description_fails(): void
    {
        $payload = $this->makePurchasePayload([
            'other_cost'             => 500,
            'other_cost_description' => '', // فارغ
        ]);

        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['other_cost_description']);
    }

    public function test_11b_other_cost_zero_clears_description(): void
    {
        $payload = $this->makePurchasePayload([
            'other_cost'             => 0,
            'other_cost_description' => 'شحن', // قيمة قديمة
        ]);

        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $response->assertStatus(201);

        $invoiceId = $response->json('purchase_invoice_id');
        $invoice = DB::table('purchase_invoices')->find($invoiceId);

        // يُنظَّف على السيرفر
        $this->assertEquals(0, (float) $invoice->other_cost);
        $this->assertNull($invoice->other_cost_description);
    }

    // =========================================================
    // TEST 12: تخزين exchange_rate للعملة الأساسية
    // =========================================================

    public function test_12_exchange_rate_stored_correctly_for_system_currency(): void
    {
        $payload = $this->makePurchasePayload([
            'coin_id'       => $this->systemCurrencyId,
            'exchange_rate' => 1,
        ]);

        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $response->assertStatus(201);

        $invoice = DB::table('purchase_invoices')
            ->find($response->json('purchase_invoice_id'));

        $this->assertEquals(1, (float) $invoice->exchange_rate);

        // total_in_base = total_in_invoice
        $expectedTotal = (float) $invoice->items_total
                       - (float) $invoice->discount_total
                       + (float) $invoice->expenses
                       + (float) $invoice->tax_cost
                       + (float) $invoice->transportation
                       + (float) $invoice->other_cost;

        $this->assertEquals($expectedTotal, (float) $invoice->total_in_base_currency);
    }

    // =========================================================
    // TEST 13: التكامل — شراء ثم بيع
    // =========================================================

    public function test_13_integration_purchase_then_sale(): void
    {
        // 1) فاتورة شراء: 100 وحدة بـ 50 SAR (سعر صرف 200)
        $purchasePayload = $this->makePurchasePayload([
            'coin_id'       => $this->foreignCurrencyId,
            'exchange_rate' => 200,
            'details'       => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'quantity' => 100,
                    'price'    => 50,
                    'discount' => 0,
                ],
            ],
        ]);
        $this->postJson('/operation/purchases/invoicesPurch', $purchasePayload)
            ->assertStatus(201);

        // 2) الرصيد = 100
        /** @var InventoryService $service */
        $service = app(InventoryService::class);
        $qty = $service->availableQuantity($this->itemId, $this->warehouseId);
        $this->assertEquals(100, $qty);

        // 3) lastCost = 50 × 200 = 10,000
        $lastCost = $service->lastCost($this->itemId, $this->warehouseId);
        $this->assertEquals(10000, $lastCost);

        // 4) فاتورة بيع: 30 وحدة
        $salePayload = $this->makeSalePayload([
            'details' => [
                [
                    'item_id'      => $this->itemId,
                    'type_id'      => $this->typeId,
                    'unit_id'      => $this->unitId,
                    'warehouse_id' => $this->warehouseId,
                    'quantity'     => 30,
                    'price'        => 200,
                    'cost_price'   => 10000,
                    'discount'     => 0,
                ],
            ],
        ]);
        $saleResponse = $this->postJson('/operation/sales/invoices', $salePayload);
        $saleResponse->assertStatus(201);

        // 5) الرصيد = 70
        $qtyAfterSale = $service->availableQuantity($this->itemId, $this->warehouseId);
        $this->assertEquals(70, $qtyAfterSale);

        // 6) حركة البيع direction = out
        $saleMovement = DB::table('inventory_movements')
            ->where('source_type', 'sales_invoice')
            ->first();
        $this->assertNotNull($saleMovement);
        $this->assertEquals('out', $saleMovement->direction);
    }

    // =========================================================
    // TEST 14: فشل البيع بسبب نقص الرصيد
    // =========================================================

    public function test_14_sale_exceeding_stock_fails(): void
    {
        // 1) رصيد 10 فقط
        $purchasePayload = $this->makePurchasePayload([
            'details' => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'quantity' => 10,
                    'price'    => 100,
                    'discount' => 0,
                ],
            ],
        ]);
        $this->postJson('/operation/purchases/invoicesPurch', $purchasePayload)
            ->assertStatus(201);

        // 2) محاولة بيع 500
        $salePayload = $this->makeSalePayload([
            'details' => [
                [
                    'item_id'      => $this->itemId,
                    'type_id'      => $this->typeId,
                    'unit_id'      => $this->unitId,
                    'warehouse_id' => $this->warehouseId,
                    'quantity'     => 500,
                    'price'        => 200,
                    'cost_price'   => 100,
                    'discount'     => 0,
                ],
            ],
        ]);

        $response = $this->postJson('/operation/sales/invoices', $salePayload);

        // 3) يفشل بسبب نقص الرصيد
        $this->assertContains(
            $response->status(),
            [422, 500],
            'Sale exceeding stock should fail'
        );

        // 4) لا توجد فاتورة بيع محفوظة
        $this->assertEquals(0, DB::table('sales_invoices')->count());

        // 5) لا توجد حركة بيع
        $this->assertEquals(
            0,
            DB::table('inventory_movements')->where('source_type', 'sales_invoice')->count()
        );
    }

    // =========================================================
    // اختبارات تشخيصية إضافية
    // =========================================================

    public function test_no_orphan_movements(): void
    {
        // إنشاء + حذف فاتورة
        $payload = $this->makePurchasePayload();
        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $invoiceId = $response->json('purchase_invoice_id');

        PurchaseInvoice::find($invoiceId)->delete();

        // لا حركات يتيمة
        $orphanMovements = DB::table('inventory_movements as im')
            ->leftJoin('purchase_invoices as pi', function ($join) {
                $join->on('im.source_id', '=', 'pi.purchase_invoice_id')
                     ->where('im.source_type', '=', 'purchase_invoice');
            })
            ->leftJoin('sales_invoices as si', function ($join) {
                $join->on('im.source_id', '=', 'si.sales_invoice_id')
                     ->where('im.source_type', '=', 'sales_invoice');
            })
            ->whereNotNull('im.source_type')
            ->whereNull('pi.purchase_invoice_id')
            ->whereNull('si.sales_invoice_id')
            ->count();

        $this->assertEquals(0, $orphanMovements);
    }

    public function test_movement_total_matches_details_sum(): void
    {
        // إنشاء 3 حركات
        for ($i = 0; $i < 3; $i++) {
            $payload = $this->makeMovementPayload([
                'details' => [
                    [
                        'item_id'      => $this->itemId,
                        'type_id'      => $this->typeId,
                        'unit_id'      => $this->unitId,
                        'warehouse_id' => $this->warehouseId,
                        'quantity'     => 10 + $i,
                        'unit_cost'    => 100,
                    ],
                ],
            ]);
            $this->postJson('/operation/movements', $payload)->assertStatus(201);
        }

        // تحقق من التطابق
        $mismatches = DB::table('inventory_movements as im')
            ->join('inventory_movement_details as imd', 'imd.movement_id', '=', 'im.movement_id')
            ->groupBy('im.movement_id', 'im.total')
            ->havingRaw('ABS(im.total - SUM(imd.total)) > 0.01')
            ->get();

        $this->assertCount(0, $mismatches, 'Movement totals should match details sum');
    }

    public function test_purchase_total_in_base_is_consistent(): void
    {
        // فاتورة شراء معقدة
        $payload = $this->makePurchasePayload([
            'coin_id'        => $this->foreignCurrencyId,
            'exchange_rate'  => 200,
            'expenses'       => 100,
            'tax_cost'       => 50,
            'transportation' => 25,
            'other_cost'     => 0,
            'details'        => [
                [
                    'item_id'  => $this->itemId,
                    'type_id'  => $this->typeId,
                    'unit_id'  => $this->unitId,
                    'quantity' => 10,
                    'price'    => 50,
                    'discount' => 0,
                ],
            ],
        ]);

        $response = $this->postJson('/operation/purchases/invoicesPurch', $payload);
        $response->assertStatus(201);
        $invoiceId = $response->json('purchase_invoice_id');

        $invoice = DB::table('purchase_invoices')->find($invoiceId);

        // computed = (500 + 175) × 200 = 135,000
        $computed = (
            (float) $invoice->items_total
            - (float) $invoice->discount_total
            + (float) $invoice->expenses
            + (float) $invoice->tax_cost
            + (float) $invoice->transportation
            + (float) $invoice->other_cost
        ) * (float) $invoice->exchange_rate;

        $this->assertEquals(
            $computed,
            (float) $invoice->total_in_base_currency,
            'total_in_base_currency should equal computed value'
        );
    }
}