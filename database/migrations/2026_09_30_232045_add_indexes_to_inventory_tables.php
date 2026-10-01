<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فهارس مركّبة لتحسين أداء استعلامات المخزون.
     *
     * الخلفية:
     *  - availableQuantity() يستعلم بـ (item_id + warehouse_id + unit_id)
     *  - lastCost() يستعلم بنفس المفاتيح + ترتيب على movement_detail_id
     *  - التقارير تحتاج (direction, movement_type) + (movement_date, direction)
     *
     * هذه الفهارس ضرورية مع نمو البيانات (ملايين السطور).
     */
    public function up(): void
    {
        // ═══════════════════════════════════════════════════
        // inventory_movement_details
        // ═══════════════════════════════════════════════════

        Schema::table('inventory_movement_details', function (Blueprint $table) {
            // ✅ فهرس رئيسي لـ availableQuantity و lastCost
            $table->index(
                ['item_id', 'warehouse_id', 'unit_id'],
                'imd_item_wh_unit_idx'
            );

            // ✅ فهرس للتسعير (currentUnitCost مع type_id)
            $table->index(
                ['item_id', 'warehouse_id', 'unit_id', 'type_id'],
                'imd_item_wh_unit_type_idx'
            );
        });

        // ═══════════════════════════════════════════════════
        // inventory_movements
        // ═══════════════════════════════════════════════════

        Schema::table('inventory_movements', function (Blueprint $table) {
            // ✅ فهرس مركّب (direction + movement_type) للتقارير
            $table->index(
                ['direction', 'movement_type'],
                'im_direction_type_idx'
            );

            // ✅ فهرس للتقارير الزمنية
            $table->index(
                ['movement_date', 'direction'],
                'im_date_direction_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movement_details', function (Blueprint $table) {
            $table->dropIndex('imd_item_wh_unit_idx');
            $table->dropIndex('imd_item_wh_unit_type_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('im_direction_type_idx');
            $table->dropIndex('im_date_direction_idx');
        });
    }
};