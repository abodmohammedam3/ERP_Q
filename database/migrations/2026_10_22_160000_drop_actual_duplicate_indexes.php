<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جدول purchase_invoices
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex('purchase_invoices_invoice_number_index');
        });

        // جدول purchase_invoice_details
        Schema::table('purchase_invoice_details', function (Blueprint $table) {
            $table->dropIndex('purchase_invoice_details_item_id_index');
            $table->dropIndex('purchase_invoice_details_purchase_invoice_id_index');
        });

        // جدول sales_invoice_details
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->dropIndex('sales_invoice_details_sales_invoice_id_index');
        });

        // جدول characcount
        Schema::table('characcount', function (Blueprint $table) {
            $table->dropIndex('idx_characcount_parent');
            $table->dropIndex('idx_characcount_active');
            $table->dropIndex('idx_characcount_system_key');
        });

        // جدول journalentrryline
        Schema::table('journalentrryline', function (Blueprint $table) {
            $table->dropIndex('idx_jel_account');
        });

        // جدول journal_entries
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_je_date');
        });
    }

    public function down(): void
    {
        // إعادة الإنشاء
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->index('invoice_number', 'purchase_invoices_invoice_number_index');
        });
        Schema::table('purchase_invoice_details', function (Blueprint $table) {
            $table->index('item_id', 'purchase_invoice_details_item_id_index');
            $table->index('purchase_invoice_id', 'purchase_invoice_details_purchase_invoice_id_index');
        });
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->index('sales_invoice_id', 'sales_invoice_details_sales_invoice_id_index');
        });
        Schema::table('characcount', function (Blueprint $table) {
            $table->index('accParent', 'idx_characcount_parent');
            $table->index('IsActive', 'idx_characcount_active');
            $table->index('system_key', 'idx_characcount_system_key');
        });
        Schema::table('journalentrryline', function (Blueprint $table) {
            $table->index('accountID', 'idx_jel_account');
        });
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('entryDate', 'idx_je_date');
        });
    }
};
