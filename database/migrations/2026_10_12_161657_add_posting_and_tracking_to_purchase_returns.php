<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================
        // 1) حذف الفهارس القديمة (إن وُجدت) — التفاصيل
        // ============================================
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_purchase_return_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_invoice_detail_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_item_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_type_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_unit_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_warehouse_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_code');

        // ============================================
        // 2) حذف الفهارس القديمة (إن وُجدت) — الرأس
        // ============================================
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_return_number');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_return_date');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_original_invoice');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_account_id');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_warehouse_id');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_coin_id');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_payment_method');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_is_posted');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_created_by');

        // ============================================
        // 3) حذف الأعمدة القديمة (إن وُجدت)
        // ============================================
        Schema::table('purchase_returns', function (Blueprint $table) {
            $this->dropColumnIfExists($table, 'purchase_returns', 'created_by');
            $this->dropColumnIfExists($table, 'purchase_returns', 'updated_by');
            $this->dropColumnIfExists($table, 'purchase_returns', 'entryID');
            $this->dropColumnIfExists($table, 'purchase_returns', 'is_posted');
            $this->dropColumnIfExists($table, 'purchase_returns', 'posted_at');
            $this->dropColumnIfExists($table, 'purchase_returns', 'posted_by');
        });

        // ============================================
        // 4) إضافة الأعمدة الجديدة
        // ============================================
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')
                  ->nullable()
                  ->after('purchase_return_id');

            $table->unsignedBigInteger('updated_by')
                  ->nullable()
                  ->after('created_by');

            $table->unsignedBigInteger('entryID')
                  ->nullable()
                  ->after('reference');

            $table->boolean('is_posted')
                  ->default(false)
                  ->after('entryID');

            $table->timestamp('posted_at')
                  ->nullable()
                  ->after('is_posted');

            $table->unsignedBigInteger('posted_by')
                  ->nullable()
                  ->after('posted_at');
        });

        // ============================================
        // 5) فهارس الأداء على الرأس
        // ============================================
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->index('return_number',               'idx_pr_return_number');
            $table->index('return_date',                 'idx_pr_return_date');
            $table->index('original_purchase_invoice_id','idx_pr_original_invoice');
            $table->index('account_id',                  'idx_pr_account_id');
            $table->index('warehouse_id',                'idx_pr_warehouse_id');
            $table->index('coin_id',                     'idx_pr_coin_id');
            $table->index('payment_method',              'idx_pr_payment_method');
            $table->index('is_posted',                   'idx_pr_is_posted');
            $table->index('created_by',                  'idx_pr_created_by');
        });

        // ============================================
        // 6) فهارس الأداء على التفاصيل
        // ============================================
        if (Schema::hasTable('purchase_return_details')) {
            Schema::table('purchase_return_details', function (Blueprint $table) {
                $table->index('purchase_return_id',        'idx_prd_purchase_return_id');
                $table->index('purchase_invoice_detail_id','idx_prd_invoice_detail_id');
                $table->index('item_id',                   'idx_prd_item_id');
                $table->index('type_id',                   'idx_prd_type_id');
                $table->index('unit_id',                   'idx_prd_unit_id');
                $table->index('warehouse_id',              'idx_prd_warehouse_id');
                $table->index('code',                      'idx_prd_code');
            });
        }
    }

    public function down(): void
    {
        // حذف الفهارس — التفاصيل
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_purchase_return_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_invoice_detail_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_item_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_type_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_unit_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_warehouse_id');
        $this->dropIndexIfExists('purchase_return_details', 'idx_prd_code');

        // حذف الفهارس — الرأس
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_return_number');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_return_date');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_original_invoice');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_account_id');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_warehouse_id');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_coin_id');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_payment_method');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_is_posted');
        $this->dropIndexIfExists('purchase_returns', 'idx_pr_created_by');

        // حذف الأعمدة
        Schema::table('purchase_returns', function (Blueprint $table) {
            $this->dropColumnIfExists($table, 'purchase_returns', 'posted_by');
            $this->dropColumnIfExists($table, 'purchase_returns', 'posted_at');
            $this->dropColumnIfExists($table, 'purchase_returns', 'is_posted');
            $this->dropColumnIfExists($table, 'purchase_returns', 'entryID');
            $this->dropColumnIfExists($table, 'purchase_returns', 'updated_by');
            $this->dropColumnIfExists($table, 'purchase_returns', 'created_by');
        });
    }

    // ============================================
    // Helpers
    // ============================================

    private function dropColumnIfExists(Blueprint $table, string $tableName, string $column): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            $table->dropColumn($column);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $exists = collect(Schema::getIndexes($table))
            ->contains(fn ($idx) => $idx['name'] === $indexName);

        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        }
    }
};