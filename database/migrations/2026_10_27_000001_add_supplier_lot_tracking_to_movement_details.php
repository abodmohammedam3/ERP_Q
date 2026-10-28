<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تتبع الدفعة الموردية (Supplier-Tracked Lots)
 * ------------------------------------------------------------
 * 1) inventory_movement_details.supplier_account_id
 *    المورد الفعلي للدفعة:
 *      - شراء: من purchase_invoices.account_id
 *      - مرتجع شراء: من فاتورة الأصل
 *      - مرتجع بيع / فرز: يرث الدفعة الأصلية عبر lot_source_detail_id
 *      - توريد يدوي: NULL (توريد عام)
 *
 * 2) inventory_movement_details.lot_source_detail_id
 *    يشير إلى movement_detail_id الأصلي الذي جاءت منه هذه الدفعة
 *    (مرتجع بيع يرجع لدفعة البيع، الفرز يشير إلى الدفعة المصروفة)
 *    → يتيح تصفية حساب أي مورد بدقة كاملة.
 *
 * 3) sales_invoice_details.supplier_account_id
 *    المورد الذي اختاره البائع لسطر البيع (اختياري — الفارغ يعني FIFO تلقائي)
 *
 * ✅ لا يُشغَّل تلقائياً — التنفيذ: php artisan migrate --path=... هذا الملف
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movement_details', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_account_id')
                  ->nullable()
                  ->after('warehouse_id')
                  ->comment('المورد الفعلي للدفعة (NULL = توريد عام)');

            $table->unsignedBigInteger('lot_source_detail_id')
                  ->nullable()
                  ->after('supplier_account_id')
                  ->comment('مرجع دفعة الأصل (movement_detail_id) للمرتجعات/الفرز');

            $table->index(['item_id', 'type_id', 'warehouse_id', 'unit_id', 'supplier_account_id'],
                'imd_supplier_lot_idx');
            $table->index('lot_source_detail_id', 'imd_lot_source_idx');
        });

        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_account_id')
                  ->nullable()
                  ->after('warehouse_id')
                  ->comment('المورد المختار لسطر البيع (NULL = FIFO تلقائي)');

            $table->index(['item_id', 'warehouse_id', 'unit_id', 'supplier_account_id'],
                'sid_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->dropIndex('sid_supplier_idx');
            $table->dropColumn('supplier_account_id');
        });

        Schema::table('inventory_movement_details', function (Blueprint $table) {
            $table->dropIndex('imd_lot_source_idx');
            $table->dropIndex('imd_supplier_lot_idx');
            $table->dropColumn(['supplier_account_id', 'lot_source_detail_id']);
        });
    }
};
