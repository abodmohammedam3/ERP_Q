<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول تفاصيل حركة المخزون
     * -----------------------------------------------------
     * المفاتيح الأجنبية:
     *  - movement_id  → inventory_movements.movement_id (Cascade)
     *  - item_id      → Items.itemID
     *  - type_id      → type.id
     *  - unit_id      → units.UnitID
     *  - warehouse_id → stocks.StockID (مخزن الصف)
     *
     * الحد الأدنى والأعلى للسعر:
     *  - min_price: الحد الأدنى لسعر البيع
     *  - max_price: الحد الأعلى لسعر البيع
     *  هذان الحقلان يتحكمان في سعر البيع الذي يُدخله المستخدم
     *  في شاشة التسعير أو عند تعديل السعر في فاتورة الشراء.
     */
    public function up(): void
    {
        Schema::create('inventory_movement_details', function (Blueprint $table) {

            // =========================================
            // المفتاح الأساسي
            // =========================================
            $table->id('movement_detail_id');

            // =========================================
            // ربط برأس الحركة
            // =========================================
            $table->unsignedBigInteger('movement_id')
                  ->comment('FK → inventory_movements');

            // =========================================
            // بيانات الصنف
            // =========================================
            $table->unsignedBigInteger('item_id')
                  ->comment('الصنف من Items');

            $table->unsignedBigInteger('type_id')->nullable()
                  ->comment('النوع من type');

            $table->unsignedBigInteger('unit_id')->nullable()
                  ->comment('الوحدة من units');

            $table->string('code', 50)->nullable()
                  ->comment('رمز الصنف (تاريخي)');

            // =========================================
            // مخزن الصف (قد يختلف عن مخزن الرأس)
            // =========================================
            $table->unsignedBigInteger('warehouse_id')
                  ->comment('المخزن الفعلي للصف');

            // =========================================
            // الكميات والأسعار
            // =========================================
            $table->decimal('quantity', 18, 6)->default(0)
                  ->comment('الكمية/الوزن');

            $table->decimal('unit_cost', 18, 6)->default(0)
                  ->comment('سعر تكلفة الوحدة');

            // =========================================
            // الحد الأدنى والأعلى للسعر
            // =========================================
            $table->decimal('min_price', 18, 6)->nullable()
                  ->comment('الحد الأدنى لسعر البيع الذي لا يمكن تجاوزه');

            $table->decimal('max_price', 18, 6)->nullable()
                  ->comment('الحد الأعلى لسعر البيع الذي لا يمكن تجاوزه');

            // =========================================
            // سعر البيع والإجمالي
            // =========================================
            $table->decimal('sale_price', 18, 6)->nullable()
                  ->comment('سعر البيع المقترح للوحدة');

            $table->decimal('total', 18, 6)->default(0)
                  ->comment('إجمالي الصف = quantity × unit_cost');

            // =========================================
            // Timestamps
            // =========================================
            $table->timestamps();

            // =========================================
            // الفهارس
            // =========================================
            $table->index('movement_id');
            $table->index('item_id');
            $table->index('type_id');
            $table->index('unit_id');
            $table->index('warehouse_id');

            // =========================================
            // المفاتيح الأجنبية
            // =========================================

            // Cascade: حذف الرأس يحذف التفاصيل
            $table->foreign('movement_id')
                  ->references('movement_id')->on('inventory_movements')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // الصنف
            $table->foreign('item_id')
                  ->references('itemID')->on('Items')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // النوع
            $table->foreign('type_id')
                  ->references('id')->on('type')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // الوحدة
            $table->foreign('unit_id')
                  ->references('UnitID')->on('units')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // مخزن الصف
            $table->foreign('warehouse_id')
                  ->references('StockID')->on('stocks')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movement_details');
    }
};