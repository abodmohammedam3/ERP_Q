<?php

namespace Tests\Feature\Reports;

use App\Reports\ReportEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportResultCorrectnessTest extends TestCase
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
        DB::table('JournalEntrryLine')->delete();
        DB::table('Journal_Entries')->delete();
        DB::table('characcount')->delete();
        DB::table('inventory_movement_details')->delete();
        DB::table('inventory_movements')->delete();
        DB::table('Items')->delete();
        DB::table('receipt_vouchers')->delete();
        DB::table('payment_vouchers')->delete();
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

        if (!Schema::hasTable('Journal_Entries')) {
            Schema::create('Journal_Entries', function (Blueprint $table) {
                $table->id('entryID');
                $table->string('entryNo');
                $table->date('entryDate');
                $table->string('docType')->nullable();
                $table->string('docNumber')->nullable();
                $table->text('description2')->nullable();
            });
        }

        if (!Schema::hasTable('JournalEntrryLine')) {
            Schema::create('JournalEntrryLine', function (Blueprint $table) {
                $table->id('entryLineID');
                $table->integer('entryID');
                $table->integer('accountID');
                $table->decimal('localDebit', 15, 2)->default(0);
                $table->decimal('localCredit', 15, 2)->default(0);
            });
        }

        if (!Schema::hasTable('Items')) {
            Schema::create('Items', function (Blueprint $table) {
                $table->id('itemID');
                $table->text('itemName2');
                $table->boolean('is_active')->default(1);
            });
        }

        if (!Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id('movement_id');
                $table->string('display_id')->nullable();
                $table->date('movement_date');
                $table->string('movement_type');
                $table->string('direction');
                $table->text('statement')->nullable();
                $table->string('source_type')->nullable();
                $table->integer('source_id')->nullable();
                $table->string('document_number')->nullable();
                $table->integer('warehouse_id')->nullable();
                $table->decimal('total', 15, 2)->default(0);
            });
        }

        if (!Schema::hasTable('inventory_movement_details')) {
            Schema::create('inventory_movement_details', function (Blueprint $table) {
                $table->id('movement_detail_id');
                $table->integer('movement_id');
                $table->integer('item_id');
                $table->integer('warehouse_id')->nullable();
                $table->integer('type_id')->nullable();
                $table->integer('unit_id')->nullable();
                $table->string('code')->nullable();
                $table->decimal('quantity', 15, 2)->default(0);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
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

    /**
     * 1. test_trial_balance_debits_equal_credits
     * Verifies that total debits equal total credits across all accounting entries.
     */
    public function test_trial_balance_debits_equal_credits(): void
    {
        $acc1 = DB::table('characcount')->insertGetId([
            'accCode' => '1101',
            'accName' => 'الصندوق',
            'nature' => 0,
            'isPostable' => 1,
        ]);

        $acc2 = DB::table('characcount')->insertGetId([
            'accCode' => '1103',
            'accName' => 'العملاء',
            'nature' => 0,
            'isPostable' => 1,
        ]);

        $entry1 = DB::table('Journal_Entries')->insertGetId([
            'entryNo' => 'JE-001',
            'entryDate' => '2026-01-10',
            'docType' => 'قيد يومية',
        ]);

        DB::table('JournalEntrryLine')->insert([
            ['entryID' => $entry1, 'accountID' => $acc1, 'localDebit' => 5000.00, 'localCredit' => 0.00],
            ['entryID' => $entry1, 'accountID' => $acc2, 'localDebit' => 0.00, 'localCredit' => 5000.00],
        ]);

        $entry2 = DB::table('Journal_Entries')->insertGetId([
            'entryNo' => 'JE-002',
            'entryDate' => '2026-01-15',
            'docType' => 'قيد يومية',
        ]);

        DB::table('JournalEntrryLine')->insert([
            ['entryID' => $entry2, 'accountID' => $acc1, 'localDebit' => 2500.00, 'localCredit' => 0.00],
            ['entryID' => $entry2, 'accountID' => $acc2, 'localDebit' => 0.00, 'localCredit' => 2500.00],
        ]);

        $result = ReportEngine::execute('trial-balance', []);

        $this->assertEquals(7500.00, $result['totals']['debit']);
        $this->assertEquals(7500.00, $result['totals']['credit']);
        $this->assertEquals($result['totals']['debit'], $result['totals']['credit']);
    }

    /**
     * 2. test_general_ledger_opening_plus_movements_equals_closing
     * Verifies that General Ledger totals accurately sum all individual journal entry line movements.
     */
    public function test_general_ledger_opening_plus_movements_equals_closing(): void
    {
        $acc = DB::table('characcount')->insertGetId([
            'accCode' => '1101',
            'accName' => 'الصندوق الرئيسي',
            'nature' => 0,
            'isPostable' => 1,
        ]);

        $entry1 = DB::table('Journal_Entries')->insertGetId([
            'entryNo' => 'GL-001',
            'entryDate' => '2026-02-01',
            'docType' => 'سند قبض',
            'docNumber' => 'REC-101',
            'description2' => 'تحصيل من عميل',
        ]);

        DB::table('JournalEntrryLine')->insert([
            'entryID' => $entry1,
            'accountID' => $acc,
            'localDebit' => 12000.00,
            'localCredit' => 0.00,
        ]);

        $entry2 = DB::table('Journal_Entries')->insertGetId([
            'entryNo' => 'GL-002',
            'entryDate' => '2026-02-05',
            'docType' => 'سند صرف',
            'docNumber' => 'PAY-101',
            'description2' => 'سداد مصاريف',
        ]);

        DB::table('JournalEntrryLine')->insert([
            'entryID' => $entry2,
            'accountID' => $acc,
            'localDebit' => 0.00,
            'localCredit' => 4000.00,
        ]);

        $result = ReportEngine::execute('general-ledger', []);

        $sumRowsDebit = array_sum(array_column($result['rows'], 'debit'));
        $sumRowsCredit = array_sum(array_column($result['rows'], 'credit'));

        $this->assertEquals(12000.00, $result['totals']['debit']);
        $this->assertEquals(4000.00, $result['totals']['credit']);
        $this->assertEquals($sumRowsDebit, $result['totals']['debit']);
        $this->assertEquals($sumRowsCredit, $result['totals']['credit']);
    }

    /**
     * 3. test_account_statement_balance_calculation
     * Verifies that running balance calculation (opening + debits - credits) is correct.
     */
    public function test_account_statement_balance_calculation(): void
    {
        $parentAcc = DB::table('characcount')->insertGetId([
            'accCode' => '1100',
            'accName' => 'الأصول المتداولة - رئيسي',
            'accParent' => null,
            'accLevel' => 1,
            'nature' => 0,
            'isPostable' => 0,
            'system_key' => 'current_assets',
        ]);

        $childAcc = DB::table('characcount')->insertGetId([
            'accCode' => '110001',
            'accName' => 'الصندوق الفرعي',
            'accParent' => $parentAcc,
            'accLevel' => 2,
            'nature' => 0,
            'isPostable' => 1,
        ]);

        // Prior entry (Opening balance before date_from)
        $entryPrior = DB::table('Journal_Entries')->insertGetId([
            'entryNo' => 'OPEN-01',
            'entryDate' => '2026-01-01',
        ]);
        DB::table('JournalEntrryLine')->insert([
            'entryID' => $entryPrior,
            'accountID' => $childAcc,
            'localDebit' => 1000.00,
            'localCredit' => 0.00,
        ]);

        // Current period entry 1
        $entryCurrent1 = DB::table('Journal_Entries')->insertGetId([
            'entryNo' => 'CURR-01',
            'entryDate' => '2026-02-10',
            'docType' => 'قبض',
            'description2' => 'إيداع نقدي',
        ]);
        DB::table('JournalEntrryLine')->insert([
            'entryID' => $entryCurrent1,
            'accountID' => $childAcc,
            'localDebit' => 500.00,
            'localCredit' => 0.00,
        ]);

        // Current period entry 2
        $entryCurrent2 = DB::table('Journal_Entries')->insertGetId([
            'entryNo' => 'CURR-02',
            'entryDate' => '2026-02-15',
            'docType' => 'صرف',
            'description2' => 'سحب نقدي',
        ]);
        DB::table('JournalEntrryLine')->insert([
            'entryID' => $entryCurrent2,
            'accountID' => $childAcc,
            'localDebit' => 0.00,
            'localCredit' => 300.00,
        ]);

        $result = ReportEngine::execute('account-statement', [
            'account_parent' => (string) $parentAcc,
            'account_from'   => (string) $childAcc,
            'account_to'     => (string) $childAcc,
            'date_from'      => '2026-02-01',
            'date_to'        => '2026-02-28',
        ]);

        $this->assertNotEmpty($result['rows']);

        // First row is opening balance: 1000.00
        $openingRow = $result['rows'][0];
        $this->assertEquals('opening', $openingRow['doc_type']);
        $this->assertEquals(1000.00, $openingRow['balance']);

        // Second row: +500 debit => balance 1500.00
        $row1 = $result['rows'][1];
        $this->assertEquals(500.00, $row1['debit']);
        $this->assertEquals(1500.00, $row1['balance']);

        // Third row: -300 credit => balance 1200.00
        $row2 = $result['rows'][2];
        $this->assertEquals(300.00, $row2['credit']);
        $this->assertEquals(1200.00, $row2['balance']);

        // Final totals balance check: 1500 debit - 300 credit = 1200 net balance
        $this->assertEquals(1200.00, $result['totals']['balance']);
    }

    /**
     * 4. test_item_ledger_quantity_balance
     * Verifies that total in_qty and out_qty in ItemLedger match movement details.
     */
    public function test_item_ledger_quantity_balance(): void
    {
        $item = DB::table('Items')->insertGetId([
            'itemName2' => 'صنف سكر',
            'is_active' => 1,
        ]);

        $movIn = DB::table('inventory_movements')->insertGetId([
            'display_id' => '101',
            'movement_date' => '2026-03-01',
            'movement_type' => 'supply',
            'direction' => 'in',
            'statement' => 'توريد بضاعة',
        ]);

        DB::table('inventory_movement_details')->insert([
            'movement_id' => $movIn,
            'item_id' => $item,
            'quantity' => 150.00,
            'unit_cost' => 20.00,
            'total' => 3000.00,
        ]);

        $movOut = DB::table('inventory_movements')->insertGetId([
            'display_id' => '102',
            'movement_date' => '2026-03-05',
            'movement_type' => 'issue',
            'direction' => 'out',
            'statement' => 'صرف لمشروع',
        ]);

        DB::table('inventory_movement_details')->insert([
            'movement_id' => $movOut,
            'item_id' => $item,
            'quantity' => 50.00,
            'unit_cost' => 20.00,
            'total' => 1000.00,
        ]);

        $result = ReportEngine::execute('item-ledger', [
            'item_id' => $item,
        ]);

        $this->assertEquals(150.00, $result['totals']['in_qty']);
        $this->assertEquals(50.00, $result['totals']['out_qty']);
        $this->assertEquals(4000.00, $result['totals']['total']);
    }

    /**
     * 5. test_vouchers_totals_match_source
     * Verifies that totals for receipt and payment vouchers match the sum of their source records.
     */
    public function test_vouchers_totals_match_source(): void
    {
        $debitAcc = DB::table('characcount')->insertGetId([
            'accCode' => '1101',
            'accName' => 'الصندوق',
            'isPostable' => 1,
        ]);

        $creditAcc = DB::table('characcount')->insertGetId([
            'accCode' => '1103',
            'accName' => 'العميل',
            'isPostable' => 1,
        ]);

        DB::table('receipt_vouchers')->insert([
            'voucherNumber' => 'RC-001',
            'voucherDate' => '2026-04-01',
            'debitAccountID' => $debitAcc,
            'creditAccountID' => $creditAcc,
            'localAmount' => 3500.00,
        ]);

        DB::table('payment_vouchers')->insert([
            'voucherNumber' => 'PV-001',
            'voucherDate' => '2026-04-02',
            'beneficiaryAccountID' => $creditAcc,
            'paymentAccountID' => $debitAcc,
            'localAmount' => 1500.00,
        ]);

        $result = ReportEngine::execute('vouchers', []);

        $this->assertEquals(5000.00, $result['totals']['local_amount']);
        $this->assertCount(2, $result['rows']);
    }
}
