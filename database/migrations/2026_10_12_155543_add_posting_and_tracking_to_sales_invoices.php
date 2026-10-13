<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================
        // 1) حقول التتبع + الترحيل
        // ============================================
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('sales_invoice_id');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            $table->unsignedBigInteger('entryID')->nullable()->after('reference');
            $table->decimal('total_in_base_currency', 15, 6)->nullable()->after('entryID');

            $table->boolean('is_posted')->default(false)->after('total_in_base_currency');
            $table->timestamp('posted_at')->nullable()->after('is_posted');
            $table->unsignedBigInteger('posted_by')->nullable()->after('posted_at');
        });

        // ============================================
        // 2) فهارس الأداء على الرأس
        // ============================================
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->index('invoice_number', 'idx_si_invoice_number');
            $table->index('invoice_date', 'idx_si_invoice_date');
            $table->index('account_id', 'idx_si_account_id');
            $table->index('coin_id', 'idx_si_coin_id');
            $table->index('payment_method', 'idx_si_payment_method');
            $table->index('is_posted', 'idx_si_is_posted');
            $table->index('created_by', 'idx_si_created_by');
        });

        // ============================================
        // 3) فهارس الأداء على التفاصيل
        // ============================================
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->index('sales_invoice_id', 'idx_sid_sales_invoice_id');
            $table->index('item_id', 'idx_sid_item_id');
            $table->index('type_id', 'idx_sid_type_id');
            $table->index('unit_id', 'idx_sid_unit_id');
            $table->index('warehouse_id', 'idx_sid_warehouse_id');
            $table->index('code', 'idx_sid_code');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->dropIndex('idx_sid_sales_invoice_id');
            $table->dropIndex('idx_sid_item_id');
            $table->dropIndex('idx_sid_type_id');
            $table->dropIndex('idx_sid_unit_id');
            $table->dropIndex('idx_sid_warehouse_id');
            $table->dropIndex('idx_sid_code');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_si_invoice_number');
            $table->dropIndex('idx_si_invoice_date');
            $table->dropIndex('idx_si_account_id');
            $table->dropIndex('idx_si_coin_id');
            $table->dropIndex('idx_si_payment_method');
            $table->dropIndex('idx_si_is_posted');
            $table->dropIndex('idx_si_created_by');

            $table->dropColumn([
                'created_by', 'updated_by',
                'entryID', 'total_in_base_currency',
                'is_posted', 'posted_at', 'posted_by',
            ]);
        });
    }
};