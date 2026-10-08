<?php

namespace Tests\Feature\Reports;

use App\Reports\ReportEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B1 — صحة الإجماليات (Totals Correctness).
 *
 * العقد:
 *  - totals = مجموع كل البيانات المطابقة للفلاتر (120 سجلاً) — لا صفحة واحدة.
 *  - rows   = صفوف الصفحة الحالية فقط (50).
 *  - meta.pagination.total = العدد الكلي.
 *  - مفاتيح totals وترتيبها وround(2) محفوظة.
 */
class B1_TotalsCorrectnessTest extends TestCase
{
    use DatabaseTransactions;

    /** عدد السجلات في كل سيناريو */
    private const RECORDS = 120;

    /** PER_PAGE في التقارير الثلاثة */
    private const PER_PAGE = 50;

    private const SALES_ITEMS = 1000.00;
    private const SALES_DISCOUNT = 100.00;
    private const PURCHASE_BASE = 1100.00;
    private const RECEIPT_AMOUNT = 500.00;
    private const PAYMENT_AMOUNT = 1000.00;

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
        DB::table('receipt_vouchers')->delete();
        DB::table('payment_vouchers')->delete();
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
                $table->string('invoice_number', 50)->unique();
                $table->date('invoice_date');
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('payment_account_id')->nullable();
                $table->unsignedBigInteger('coin_id')->nullable();
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->unsignedTinyInteger('payment_method')->default(1);
                $table->decimal('items_total', 18, 6)->default(0);
                $table->decimal('discount_total', 18, 6)->default(0);
                $table->text('statement')->nullable();
                $table->text('reference')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('purchase_invoices')) {
            Schema::create('purchase_invoices', function (Blueprint $table) {
                $table->id('purchase_invoice_id');
                $table->string('invoice_number', 50)->unique();
                $table->date('invoice_date');
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('payment_account_id')->nullable();
                $table->unsignedBigInteger('coin_id')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->decimal('total_in_base_currency', 20, 6)->default(0);
                $table->unsignedTinyInteger('payment_method')->default(1);
                $table->decimal('items_total', 18, 6)->default(0);
                $table->decimal('discount_total', 18, 6)->default(0);
                $table->text('statement')->nullable();
                $table->text('reference')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('receipt_vouchers')) {
            Schema::create('receipt_vouchers', function (Blueprint $table) {
                $table->id('receiptID');
                $table->string('voucherNumber')->nullable();
                $table->date('voucherDate');
                $table->integer('creditAccountID');
                $table->integer('debitAccountID');
                $table->integer('coinsID')->nullable();
                $table->integer('entryID')->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('exchangeRate', 12, 6)->default(1);
                $table->decimal('localAmount', 15, 2)->default(0);
                $table->integer('paymentMethod')->default(1);
                $table->string('chequeNumber')->nullable();
                $table->text('notes')->nullable();
            });
        }

        if (!Schema::hasTable('payment_vouchers')) {
            Schema::create('payment_vouchers', function (Blueprint $table) {
                $table->id('paymentID');
                $table->string('voucherNumber')->nullable();
                $table->date('voucherDate');
                $table->integer('beneficiaryAccountID');
                $table->integer('paymentAccountID');
                $table->integer('coinsID')->nullable();
                $table->integer('entryID')->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('exchangeRate', 12, 6)->default(1);
                $table->decimal('localAmount', 15, 2)->default(0);
                $table->integer('paymentMethod')->default(1);
                $table->text('notes')->nullable();
            });
        }
    }

    private function seedSalesInvoices(): void
    {
        $accountId = $this->ensureAccount();

        $rows = [];
        for ($i = 1; $i <= self::RECORDS; $i++) {
            $rows[] = [
                'invoice_number'      => 'INV-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'invoice_date'        => '2026-01-15',
                'account_id'          => $accountId,
                'payment_method'      => 1,
                'items_total'         => self::SALES_ITEMS,
                'discount_total'      => self::SALES_DISCOUNT,
            ];
        }
        DB::table('sales_invoices')->insert($rows);
    }

    private function seedPurchaseInvoices(): void
    {
        $accountId = $this->ensureAccount();

        $rows = [];
        for ($i = 1; $i <= self::RECORDS; $i++) {
            $rows[] = [
                'invoice_number'         => 'PINV-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'invoice_date'           => '2026-01-15',
                'account_id'             => $accountId,
                'payment_method'         => 1,
                'items_total'            => self::SALES_ITEMS,
                'discount_total'         => self::SALES_DISCOUNT,
                'total_in_base_currency' => self::PURCHASE_BASE,
            ];
        }
        DB::table('purchase_invoices')->insert($rows);
    }

    /** حساب تفصيلي واحد يشترك فيه كل بذور الفواتير (account_id NOT NULL) */
    private function ensureAccount(): int
    {
        return (int) DB::table('characcount')->insertGetId([
            'accCode' => '110101', 'accName' => 'الصندوق الرئيسي', 'isPostable' => 1,
        ]);
    }

    private function seedVouchers(): void
    {
        $debitAcc = DB::table('characcount')->insertGetId([
            'accCode' => '1101', 'accName' => 'الصندوق', 'isPostable' => 1,
        ]);
        $creditAcc = DB::table('characcount')->insertGetId([
            'accCode' => '1103', 'accName' => 'العملاء', 'isPostable' => 1,
        ]);

        $half = self::RECORDS / 2; // 60 قبض + 60 صرف = 120

        $receipts = [];
        for ($i = 1; $i <= $half; $i++) {
            $receipts[] = [
                'voucherNumber'   => 'RC-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'voucherDate'     => '2026-02-01',
                'debitAccountID'  => $debitAcc,
                'creditAccountID' => $creditAcc,
                'localAmount'     => self::RECEIPT_AMOUNT,
            ];
        }
        DB::table('receipt_vouchers')->insert($receipts);

        $payments = [];
        for ($i = 1; $i <= $half; $i++) {
            $payments[] = [
                'voucherNumber'        => 'PV-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'voucherDate'          => '2026-02-02',
                'beneficiaryAccountID' => $creditAcc,
                'paymentAccountID'     => $debitAcc,
                'localAmount'          => self::PAYMENT_AMOUNT,
            ];
        }
        DB::table('payment_vouchers')->insert($payments);
    }

    /** فحص مشترك: مفاتيح/ترتيب/round + القيم الكبرى + شكل pagination */
    private function assertTotalsContract(array $result, array $expectedTotals, string $label): void
    {
        // AC-B1-6: أسماء مفاتيح totals وترتيبها لم يتغيّر
        $this->assertSame(
            array_keys($expectedTotals),
            array_keys($result['totals']),
            "{$label}: مفاتيح totals أو ترتيبها تغيّر"
        );

        foreach ($expectedTotals as $key => $value) {
            // AC-B1-1: totals = مجموع كل الـ120 (لا 50)
            $this->assertEqualsWithDelta(
                $value,
                $result['totals'][$key],
                0.001,
                "{$label}: totals.{$key} لا يساوي مجموع الـ" . self::RECORDS
            );

            // AC-B1-7: round 2 محفوظ
            $this->assertEqualsWithDelta(
                round($result['totals'][$key], 2),
                $result['totals'][$key],
                0.0000001,
                "{$label}: totals.{$key} ليست مقرّبة لخانتين"
            );
        }

        // AC-B1-2: عدد صفوف الصفحة = 50
        $this->assertCount(self::PER_PAGE, $result['rows'], "{$label}: صفوف الصفحة الأولى ≠ 50");

        // AC-B1-4: العدد الكلي
        $this->assertSame(self::RECORDS, $result['meta']['pagination']['total'], "{$label}: pagination.total ≠ 120");
        $this->assertSame(1, $result['meta']['pagination']['current_page']);
        $this->assertSame(3, $result['meta']['pagination']['last_page']);
        $this->assertSame(self::PER_PAGE, $result['meta']['pagination']['per_page']);
    }

    /** AC-B1-3: totals الصفحة 2 = totals الصفحة 1 (الإجمالي لا يعتمد على الصفحة) */
    private function assertPage2TotalsMatch(array $page1Totals, string $key, string $label): void
    {
        $page2 = ReportEngine::execute($key, ['page' => 2]);

        $this->assertSame(2, $page2['meta']['pagination']['current_page'], "{$label}: current_page للصفحة 2");
        $this->assertCount(self::PER_PAGE, $page2['rows'], "{$label}: صفوف الصفحة الثانية ≠ 50");
        $this->assertEquals($page1Totals, $page2['totals'], "{$label}: totals الصفحة 2 ≠ الصفحة 1");
    }

    /**
     * 1. فواتير البيع — 120 فاتورة × (1000 − 100).
     */
    public function test_sales_invoices_totals_cover_all_120_records(): void
    {
        $this->seedSalesInvoices();

        $result = ReportEngine::execute('sales-invoices', ['page' => 1]);

        $this->assertTotalsContract($result, [
            'items_total'    => self::SALES_ITEMS * self::RECORDS,                    // 120,000.00
            'discount_total' => self::SALES_DISCOUNT * self::RECORDS,                 // 12,000.00
            'total'          => (self::SALES_ITEMS - self::SALES_DISCOUNT) * self::RECORDS, // 108,000.00
        ], 'sales');

        // البرهان الحاسم: مجموع صفوف الصفحة ≠ totals (كان سيتساوي قبل الإصلاح)
        $pageSum = array_sum(array_column($result['rows'], 'total'));
        $this->assertNotEqualsWithDelta(
            $result['totals']['total'],
            $pageSum,
            0.001,
            'sales: totals ما زالت محسوبة من صفحة واحدة'
        );

        $this->assertPage2TotalsMatch($result['totals'], 'sales-invoices', 'sales');
    }

    /**
     * 2. فواتير الشراء — 120 فاتورة × (1000 − 100) + قاعدة 1100.
     */
    public function test_purchase_invoices_totals_cover_all_120_records(): void
    {
        $this->seedPurchaseInvoices();

        $result = ReportEngine::execute('purchase-invoices', ['page' => 1]);

        $this->assertTotalsContract($result, [
            'items_total'    => self::SALES_ITEMS * self::RECORDS,      // 120,000.00
            'discount_total' => self::SALES_DISCOUNT * self::RECORDS,   // 12,000.00
            'total_local'    => self::PURCHASE_BASE * self::RECORDS,    // 132,000.00
        ], 'purchase');

        $pageSum = array_sum(array_column($result['rows'], 'total_local'));
        $this->assertNotEqualsWithDelta(
            $result['totals']['total_local'],
            $pageSum,
            0.001,
            'purchase: totals ما زالت محسوبة من صفحة واحدة'
        );

        $this->assertPage2TotalsMatch($result['totals'], 'purchase-invoices', 'purchase');
    }

    /**
     * 3. السندات — 60 قبض × 500 + 60 صرف × 1000 = 90,000.
     */
    public function test_vouchers_totals_cover_all_120_records(): void
    {
        $this->seedVouchers();

        $result = ReportEngine::execute('vouchers', ['page' => 1]);

        $half = self::RECORDS / 2;
        $expected = (self::RECEIPT_AMOUNT * $half) + (self::PAYMENT_AMOUNT * $half); // 90,000.00

        $this->assertTotalsContract($result, [
            'local_amount' => $expected,
        ], 'vouchers');

        $pageSum = array_sum(array_column($result['rows'], 'local_amount'));
        $this->assertNotEqualsWithDelta(
            $result['totals']['local_amount'],
            $pageSum,
            0.001,
            'vouchers: totals ما زالت محسوبة من صفحة واحدة'
        );

        $this->assertPage2TotalsMatch($result['totals'], 'vouchers', 'vouchers');
    }
}
