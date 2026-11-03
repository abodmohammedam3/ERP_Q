<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ✅ فهارس مسارات القراءة (خطة إصلاح الأداء — مرحلة 1، خطر صفر):
 *
 * 1) imd_lot_read_idx على inventory_movement_details:
 *    استعلامات الرصيد الموردي (availableQuantityForSupplier/ForSupplierBatch
 *    وlockRowsForPairs) تفلتر بـ (item_id, warehouse_id, unit_id, supplier_account_id)
 *    بدون type_id — الفهرس القديم imd_supplier_lot_idx (item_id, type_id, ...)
 *    يتوقف المحسّن بعده بعد item_id. هذا الفهرس يجعل البحث seek كامل.
 *
 * 2) idx_im_source_doc على inventory_movements:
 *    generateSortingNumber يفلتر بـ (source_type + document_number LIKE 'SORT-%').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movement_details', function (Blueprint $table) {
            $table->index(
                ['item_id', 'warehouse_id', 'unit_id', 'supplier_account_id'],
                'imd_lot_read_idx'
            );
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index(
                ['source_type', 'document_number'],
                'idx_im_source_doc'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movement_details', function (Blueprint $table) {
            $table->dropIndex('imd_lot_read_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('idx_im_source_doc');
        });
    }
};
