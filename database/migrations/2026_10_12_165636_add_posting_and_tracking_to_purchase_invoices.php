<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================
        // 1) حذف الفهارس القديمة أولًا (إن وُجدت)
        // ============================================
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_purchase_invoice_id');
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_item_id');
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_type_id');
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_unit_id');
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_code');

        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_invoice_number');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_invoice_date');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_account_id');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_warehouse_id');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_coin_id');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_payment_method');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_is_posted');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_created_by');

        // ============================================
        // 2) حذف الأعمدة القديمة (إن وُجدت)
        // ============================================
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $this->dropColumnIfExists($table, 'purchase_invoices', 'created_by');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'updated_by');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'is_posted');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'posted_at');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'posted_by');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'entryID');
        });

        // ============================================
        // 3) إضافة الأعمدة الجديدة
        // ============================================
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('entryID')
                  ->nullable()
                  ->after('purchase_invoice_id');

            $table->unsignedBigInteger('created_by')
                  ->nullable()
                  ->after('entryID');

            $table->unsignedBigInteger('updated_by')
                  ->nullable()
                  ->after('created_by');

            $table->boolean('is_posted')
                  ->default(false)
                  ->after('updated_by');

            $table->timestamp('posted_at')
                  ->nullable()
                  ->after('is_posted');

            $table->unsignedBigInteger('posted_by')
                  ->nullable()
                  ->after('posted_at');
        });

        // ============================================
        // 4) فهارس الأداء على purchase_invoices
        // ============================================
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->index('invoice_number', 'idx_pi_invoice_number');
            $table->index('invoice_date',   'idx_pi_invoice_date');
            $table->index('account_id',     'idx_pi_account_id');
            $table->index('warehouse_id',   'idx_pi_warehouse_id');
            $table->index('coin_id',        'idx_pi_coin_id');
            $table->index('payment_method', 'idx_pi_payment_method');
            $table->index('is_posted',      'idx_pi_is_posted');
            $table->index('created_by',     'idx_pi_created_by');
        });

        // ============================================
        // 5) فهارس الأداء على purchase_invoice_details
        // ============================================
        Schema::table('purchase_invoice_details', function (Blueprint $table) {
            $table->index('purchase_invoice_id', 'idx_pid_purchase_invoice_id');
            $table->index('item_id',             'idx_pid_item_id');
            $table->index('type_id',             'idx_pid_type_id');
            $table->index('unit_id',             'idx_pid_unit_id');
            $table->index('code',                'idx_pid_code');
        });
    }

    public function down(): void
    {
        // حذف الفهارس
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_purchase_invoice_id');
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_item_id');
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_type_id');
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_unit_id');
        $this->dropIndexIfExists('purchase_invoice_details', 'idx_pid_code');

        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_invoice_number');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_invoice_date');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_account_id');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_warehouse_id');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_coin_id');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_payment_method');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_is_posted');
        $this->dropIndexIfExists('purchase_invoices', 'idx_pi_created_by');

        // حذف الأعمدة
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $this->dropColumnIfExists($table, 'purchase_invoices', 'posted_by');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'posted_at');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'is_posted');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'updated_by');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'created_by');
            $this->dropColumnIfExists($table, 'purchase_invoices', 'entryID');
        });
    }

    // ============================================
    // Helpers
    // ============================================

    /**
     * حذف عمود فقط إذا كان موجودًا
     */
       private function dropColumnIfExists(Blueprint $table, string 	$tableName, string $column): void
    {
        if (!Schema::hasColumn($tableName, $column)) {
            return;
        }

        // احذف الـ FK المرتبط بالعمود (إن وُجد) قبل حذف العمود
        try {
            $foreignKeys = \DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$tableName, $column]);

            foreach ($foreignKeys as $fk) {
                \DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
        } catch (\Throwable $e) {
            // تجاهل
        }

        $table->dropColumn($column);
    }
    /**
     * حذف فهرس فقط إذا كان موجودًا
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = collect(Schema::getIndexes($table))
            ->contains(fn ($idx) => $idx['name'] === $indexName);

        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        }
    }
};