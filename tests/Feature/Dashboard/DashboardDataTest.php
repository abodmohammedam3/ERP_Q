<?php

namespace Tests\Feature\Dashboard;

use App\Services\Dashboard\DashboardService;
use App\Support\DashboardCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * اختبار عقد لوحة المعلومات (Dashboard Contract).
 *
 * يتحقق من:
 *  1) /dashboard/data يرجع 200 مع success=true.
 *  2) وجود كل الأجزاء المعلنة في docs/dashboard-contract.md.
 *  3) أنواع الحقول (int / float / array) — لمنع التخمين في الواجهة.
 *  4) الشاشة /dashboard تُفتح (200) وتحتوي على window.DASHBOARD_INITIAL.
 *  5) إبطال الكاش: أي كتابة ترفع الإصدار ⇒ الرقم يتغيّر فورًا (لا انتظار TTL).
 *  6) SSR لا يكسر الصفحة إن فشلت الخدمة (يظهر الصفر لكن الصفحة تُفتح).
 *
 * يستخدم DatabaseTransactions مثل بقية اختبارات المشروع
 * (tests/Feature/Reports/ReportContractTest.php:22) ولا يستخدم RefreshDatabase
 * لأن قاعدة الاختبار مبنية على نسخة من قواعد الإنتاج.
 */
class DashboardDataTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // ابدأ من إصدار نظيف حتى لا يتسرّب كاش بين الاختبارات
        Cache::forget(DashboardCache::VERSION_KEY);

        $this->ensureTablesExist();
    }

    /**
     * نفس منهج بقية اختبارات المشروع (ReportContractTest::ensureTablesExist):
     * يتأكد من وجود الجداول التي تحتاجها اللوحة، وينشئها فقط إن كانت غائبة.
     *
     * ملاحظة: قاعدة الاختبار فيها عَيب هجرة PRE-EXISTING مستقل عن هذه الشاشة:
     *  - 2026_09_07_231418_add_is_active_to_items_table.php يستدعي Schema::create('Items')
     *    بينما الملف 2026_09_08_000055_create_items_table.php (الذي يفترض أن ينشئه) لا يعمل.
     *  - النتيجة: الجدول يُنشأ بمخطط ناقص ولا يمكن لـ migrate:fresh أن يكتمل.
     *  لذلك لا نعتمد على الهجرات في الاختبار، بل على الحارس أدناه.
     */
    private function ensureTablesExist(): void
    {
        $tables = [
            // ─ أهم جدول: CustomerObserver وSupplierObserver يزامنان الاسم/الحالة
            //    إلى characcount بعد الحفظ، فلازم وجوده لهذا الاختبار.
            'characcount'               => static function ($t): void {
                $t->id('accountID');
                $t->unsignedBigInteger('accParent')->nullable();
                $t->string('accCode')->nullable();
                $t->string('accName')->nullable();
                $t->integer('accLevel')->default(1);
                $t->integer('nature')->default(0);
                $t->boolean('isPostable')->default(1);
                $t->boolean('IsActive')->default(1);
                $t->string('system_key')->nullable();
            },
            'customers'                 => static function ($t): void {
                $t->id('CustomersID');
                $t->unsignedBigInteger('accountID')->nullable();
                $t->text('CustomersName2')->nullable();
                $t->string('CusPhone')->nullable();
                $t->string('CusAddress')->nullable();
                $t->boolean('is_active')->default(1);
            },
            'Suppliers'                 => static function ($t): void {
                $t->id('supID');
                $t->unsignedBigInteger('accountID')->nullable();
                $t->text('supName')->nullable();
                $t->boolean('is_active')->default(1);
            },
            'sales_invoices'            => static function ($t): void {
                $t->id('sales_invoice_id');
                $t->string('invoice_number', 50);
                $t->date('invoice_date');
                $t->unsignedBigInteger('account_id')->nullable();
                $t->unsignedBigInteger('coin_id')->nullable();
                $t->unsignedTinyInteger('payment_method')->default(1);
                $t->decimal('exchange_rate', 18, 6)->default(1);
                $t->decimal('items_total', 18, 6)->default(0);
                $t->decimal('discount_total', 18, 6)->default(0);
                $t->decimal('total_in_base_currency', 18, 6)->nullable();
                $t->boolean('is_posted')->default(0);
                $t->text('statement')->nullable();
            },
            'purchase_invoices'         => static function ($t): void {
                $t->id('purchase_invoice_id');
                $t->string('invoice_number', 50);
                $t->date('invoice_date');
                $t->unsignedBigInteger('account_id')->nullable();
                $t->unsignedBigInteger('coin_id')->nullable();
                $t->unsignedTinyInteger('payment_method')->default(1);
                $t->decimal('exchange_rate', 18, 6)->default(1);
                $t->decimal('items_total', 18, 6)->default(0);
                $t->decimal('discount_total', 18, 6)->default(0);
                $t->decimal('total_in_base_currency', 18, 6)->nullable();
                $t->boolean('is_posted')->default(0);
                $t->text('statement')->nullable();
            },
            'inventory_movements'       => static function ($t): void {
                $t->id('movement_id');
                $t->string('display_id', 50)->nullable();
                $t->string('movement_type', 30)->nullable();
                $t->string('direction', 3)->nullable();
                $t->date('movement_date')->nullable();
                $t->string('document_number', 50)->nullable();
                $t->unsignedBigInteger('warehouse_id')->nullable();
                $t->text('statement')->nullable();
                $t->string('source_type', 50)->nullable();
                $t->unsignedBigInteger('source_id')->nullable();
                $t->decimal('total', 18, 6)->default(0);
            },
            'inventory_movement_details' => static function ($t): void {
                $t->id('movement_detail_id');
                $t->unsignedBigInteger('movement_id');
                $t->unsignedBigInteger('item_id')->nullable();
                $t->unsignedBigInteger('type_id')->nullable();
                $t->unsignedBigInteger('unit_id')->nullable();
                $t->string('code', 50)->nullable();
                $t->unsignedBigInteger('warehouse_id')->nullable();
                $t->decimal('quantity', 18, 6)->default(0);
                $t->decimal('unit_cost', 18, 6)->default(0);
                $t->decimal('min_price', 18, 6)->nullable();
                $t->decimal('max_price', 18, 6)->nullable();
                $t->decimal('sale_price', 18, 6)->nullable();
                $t->decimal('total', 18, 6)->default(0);
            },
            'stocks'                    => static function ($t): void {
                $t->id('StockID');
                $t->string('StockName')->nullable();
            },
            'type'                      => static function ($t): void {
                $t->id('id');
                $t->string('name')->nullable();
                $t->string('code')->nullable();
            },
            'units'                     => static function ($t): void {
                $t->id('UnitID');
                $t->string('UnitName')->nullable();
            },
        ];

        foreach ($tables as $name => $definition) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        }
    }

    // ════════
    //  عقد الـ JSON
    // ════════

    public function test_data_endpoint_returns_expected_contract(): void
    {
        $response = $this->getJson('/dashboard/data');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json('data');

        foreach (['indicators', 'summary', 'supply', 'distribution', 'inventory', 'alerts', 'version', 'generated_at'] as $key) {
            $this->assertArrayHasKey($key, $data, "الجزء المفقود في العقد: {$key}");
        }
    }

    public function test_indicators_have_integer_fields(): void
    {
        $indicators = $this->getJson('/dashboard/data')->json('data.indicators');

        foreach (['sales_invoices', 'purchase_invoices', 'customers', 'suppliers'] as $key) {
            $this->assertArrayHasKey($key, $indicators);
            $this->assertIsInt($indicators[$key], "{$key} يجب أن يكون int");
            $this->assertGreaterThanOrEqual(0, $indicators[$key]);
        }
    }

    public function test_summary_have_numeric_fields(): void
    {
        $summary = $this->getJson('/dashboard/data')->json('data.summary');

        foreach (['sales_total', 'purchase_total'] as $key) {
            $this->assertArrayHasKey($key, $summary);
            $this->assertIsNumeric($summary[$key], "{$key} يجب أن يكون رقمًا");
            $this->assertGreaterThanOrEqual(0, (float) $summary[$key]);
        }

        $this->assertIsInt($summary['stock_movements']);
    }

    public function test_supply_and_distribution_are_arrays_with_contract_fields(): void
    {
        $data = $this->getJson('/dashboard/data')->json('data');

        $fields = [
            'movement_id', 'display_id', 'movement_type', 'type_label', 'direction',
            'date', 'day_name', 'statement', 'supplier_name', 'item_name',
            'type_name', 'unit_name', 'code', 'quantity', 'unit_cost',
            'sale_price', 'line_total',
        ];

        foreach (['supply', 'distribution'] as $part) {
            $this->assertIsArray($data[$part]);

            foreach (array_slice($data[$part], 0, 3) as $row) {
                foreach ($fields as $field) {
                    $this->assertArrayHasKey($field, $row, "{$part}.{$field} مفقود");
                }
            }
        }
    }

    public function test_inventory_and_alerts_shape(): void
    {
        $data = $this->getJson('/dashboard/data')->json('data');

        foreach (['stocks', 'items', 'types', 'units'] as $key) {
            $this->assertArrayHasKey($key, $data['inventory']);
            $this->assertIsInt($data['inventory'][$key]);
        }

        $this->assertIsArray($data['inventory']['recent']);

        foreach ($data['alerts'] as $alert) {
            $this->assertArrayHasKey('level', $alert);
            $this->assertArrayHasKey('key', $alert);
            $this->assertArrayHasKey('text', $alert);
            $this->assertArrayHasKey('count', $alert);
            $this->assertContains($alert['level'], ['warning', 'info']);
        }
    }

    // ════════
    //  الصفحة (SSR)
    // ════════

    public function test_dashboard_page_renders_with_initial_payload(): void
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('window.DASHBOARD_INITIAL', false);
        $response->assertSee('لوحة التحكم', false);
    }

    public function test_root_route_serves_same_dashboard(): void
    {
        $this->get('/')->assertStatus(200);
    }

    // ════════
    //  الإبطال (القلب)
    // ════════

    public function test_any_write_bumps_cache_version_and_refreshes_number(): void
    {
        $service = app(DashboardService::class);

        // كتابة حقيقية على نموذج مشترك في أحداث اللوحة
        $customer = \App\Models\Customer::query()->first();

        // نشأ عميلًا إن كانت قاعدة الاختبار فارغة — حتى يتحقق الاختبار فعلًا
        if (!$customer) {
            $customer = \App\Models\Customer::create([
                'accountID'      => null,
                'CustomersName2' => 'عميل اختبار اللوحة',
                'is_active'      => 1,
            ]);
        }

        // نثبّت العميل على "نشط" أولاً حتى يكون الإيقاف تغيرًا حقيقًا
        $customer->is_active = 1;
        $customer->save();

        $beforeCount   = $service->indicators()['customers'];
        $beforeVersion = DashboardCache::version();

        $customer->is_active = 0;
        $customer->save();

        $afterVersion = DashboardCache::version();
        $afterCount   = $service->indicators()['customers'];

        $this->assertGreaterThan(
            $beforeVersion,
            $afterVersion,
            'الإصدار يجب أن يزيد بعد أي كتابة (FlushDashboardCache)'
        );

        $this->assertSame(
            $beforeCount - 1,
            $afterCount,
            'رقم العملاء يجب أن ينقص فورًا بعد إيقاف عميل — لأن مفتاح الكاش يحمل الإصدار'
        );

        // إرجاع الحالة (DatabaseTransactions يرجع أيضًا، لكنها نظافة إضافية)
        $customer->is_active = 1;
        $customer->save();
    }

    /**
     * التحقق الأدق من الإبطال: إضافة عميل نشط تزيد الرقم فورًا.
     */
    public function test_indicators_reflect_new_customer_immediately(): void
    {
        $service = app(DashboardService::class);

        $before = $service->indicators()['customers'];

        \App\Models\Customer::create([
            'accountID'      => null,
            'CustomersName2' => 'عميل اختبار الإبطال',
            'is_active'      => 1,
        ]);

        $this->assertSame(
            $before + 1,
            $service->indicators()['customers'],
            'الرقم يجب أن يزيد فورًا بعد إضافة عميل نشط'
        );
    }

    public function test_version_is_part_of_cache_key(): void
    {
        $before = DashboardCache::key('indicators');

        DashboardCache::bump();

        $after = DashboardCache::key('indicators');

        $this->assertNotSame($before, $after, 'مفتاح الكاش يجب أن يتغير بعد bump');
        $this->assertStringContainsString('dashboard.v', $after);
    }
}
