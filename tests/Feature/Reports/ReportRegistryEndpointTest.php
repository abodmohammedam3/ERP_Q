<?php

namespace Tests\Feature\Reports;

use App\Reports\ReportRegistry;
use App\Reports\ReportEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportRegistryEndpointTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTablesExist();
        $this->truncateTables();
    }

    private function truncateTables(): void
    {
        DB::table('sales_invoices')->delete();
        DB::table('purchase_invoices')->delete();
        DB::table('characcount')->delete();
        DB::table('coins')->delete();
    }

    private function ensureTablesExist(): void
    {
        if (!Schema::hasTable('characcount')) {
            Schema::create('characcount', function (Blueprint $table) {
                $table->id('accountID');
                $table->string('accCode');
                $table->string('accName');
                $table->integer('accParent')->nullable();
                $table->integer('accLevel')->default(1);
                $table->integer('nature')->default(0);
                $table->boolean('isPostable')->default(1);
                $table->string('system_key')->nullable();
            });
        }

        if (!Schema::hasTable('coins')) {
            Schema::create('coins', function (Blueprint $table) {
                $table->id('coinsID');
                $table->string('coinsName');
                $table->string('coinsCode');
                $table->decimal('coinsExchangeRate', 12, 6)->default(1);
                $table->boolean('coinsSystem')->default(1);
            });
        }

        if (!Schema::hasTable('sales_invoices')) {
            Schema::create('sales_invoices', function (Blueprint $table) {
                $table->id('sales_invoice_id');
                $table->string('invoice_number');
                $table->date('invoice_date');
                $table->integer('account_id');
                $table->integer('payment_method')->default(1);
                $table->integer('coin_id')->nullable();
                $table->string('statement')->nullable();
                $table->decimal('items_total', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('total_in_invoice_currency', 15, 2)->default(0);
            });
        }

        if (!Schema::hasTable('purchase_invoices')) {
            Schema::create('purchase_invoices', function (Blueprint $table) {
                $table->id('purchase_invoice_id');
                $table->string('invoice_number');
                $table->date('invoice_date');
                $table->integer('account_id');
                $table->integer('payment_method')->default(1);
                $table->integer('coin_id')->nullable();
                $table->string('statement')->nullable();
                $table->decimal('items_total', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('total_in_base_currency', 15, 2)->default(0);
            });
        }
    }

    public function test_registry_contains_all_expected_reports(): void
    {
        $all = ReportRegistry::all();

        $this->assertArrayHasKey('trial-balance', $all);
        $this->assertArrayHasKey('general-ledger', $all);
        $this->assertArrayHasKey('account-statement', $all);
        $this->assertArrayHasKey('vouchers', $all);
        $this->assertArrayHasKey('item-ledger', $all);
        $this->assertArrayHasKey('sales-invoices', $all);
        $this->assertArrayHasKey('purchase-invoices', $all);
    }

    public function test_sales_invoices_endpoint_returns_data(): void
    {
        $coinId = DB::table('coins')->insertGetId([
            'coinsName' => 'ريال يمني',
            'coinsCode' => 'YER',
            'coinsExchangeRate' => 1,
            'coinsSystem' => 1,
        ]);

        $accId = DB::table('characcount')->insertGetId([
            'accCode' => '110301',
            'accName' => 'عميل اختبار',
            'isPostable' => 1,
        ]);

        DB::table('sales_invoices')->insert([
            'invoice_number' => 'INV-001',
            'invoice_date' => now()->toDateString(),
            'account_id' => $accId,
            'payment_method' => 1,
            'coin_id' => $coinId,
            'statement' => 'فاتورة بيع اختباري',
            'items_total' => 1000.00,
            'discount_total' => 100.00,
            'total_in_invoice_currency' => 900.00,
        ]);

        $response = $this->getJson('/reports/data/sales-invoices');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json();
        $this->assertArrayHasKey('rows', $data);
        $this->assertArrayHasKey('totals', $data);
        $this->assertArrayHasKey('columns', $data);
        $this->assertNotEmpty($data['rows']);
        $this->assertEquals(900.00, $data['totals']['total']);
    }

    public function test_purchase_invoices_endpoint_returns_data(): void
    {
        $coinId = DB::table('coins')->insertGetId([
            'coinsName' => 'ريال يمني',
            'coinsCode' => 'YER',
            'coinsExchangeRate' => 1,
            'coinsSystem' => 1,
        ]);

        $accId = DB::table('characcount')->insertGetId([
            'accCode' => '210101',
            'accName' => 'مورد اختبار',
            'isPostable' => 1,
        ]);

        DB::table('purchase_invoices')->insert([
            'invoice_number' => 'PINV-001',
            'invoice_date' => now()->toDateString(),
            'account_id' => $accId,
            'payment_method' => 1,
            'coin_id' => $coinId,
            'statement' => 'فاتورة شراء اختباري',
            'items_total' => 2000.00,
            'discount_total' => 200.00,
            'total_in_base_currency' => 1800.00,
        ]);

        $response = $this->getJson('/reports/data/purchase-invoices');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json();
        $this->assertArrayHasKey('rows', $data);
        $this->assertArrayHasKey('totals', $data);
        $this->assertArrayHasKey('columns', $data);
        $this->assertNotEmpty($data['rows']);
        $this->assertEquals(1800.00, $data['totals']['total_local']);
    }
}
