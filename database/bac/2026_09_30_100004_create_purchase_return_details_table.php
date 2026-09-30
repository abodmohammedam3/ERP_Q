<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول تفاصيل مرتجع الشراء
     * -----------------------------------------------------
     * المفاتيح الأجنبية:
     *  - purchase_return_id          → purchase_returns.purchase_return_id (Cascade)
     *  - purchase_invoice_detail_id  → purchase_invoice_details.purchase_invoice_detail_id
     *  - item_id                     → Items.itemID
     *  - type_id                     → type.id
     *  - unit_id                     → units.UnitID
     *  - warehouse_id                → stocks.StockID
     *
     * unit_cost: التكلفة الفعلية (Landed Cost) المأخوذة من
     *            inventory_movement_details.unit_cost للسطر الأصلي،
     *            لأنها القيمة التي خرجت فعلاً من المخزون عند الشراء
     *            (تشمل النقل/الضريبة/المصاريف) وليس سعر الفاتورة الخام.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchase_return_details')) {
            return;
        }

        Schema::create('purchase_return_details', function (Blueprint $table) {

            // PK
            $table->id('purchase_return_detail_id');

            // ربط برأس المرتجع
            $table->unsignedBigInteger('purchase_return_id');

            // ⭐ ربط بسطر الفاتورة الأصلية (لحساب الكمية المتبقية)
            $table->unsignedBigInteger('purchase_invoice_detail_id')
                  ->comment('سطر فاتورة الشراء الأصلية');

            // بيانات الصنف (منسوخة للحفاظ على ثبات التقارير التاريخية)
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');
            $table->string('code', 50)->nullable();

            // الكميات والتكلفة
            $table->decimal('quantity', 18, 6)->default(0)
                  ->comment('الكمية المرتجعة للمورد');

            $table->decimal('price', 18, 6)->default(0)
                  ->comment('سعر الشراء للوحدة (منسوخ من السطر الأصلي)');

            $table->decimal('unit_cost', 18, 6)->nullable()
                  ->comment('التكلفة الفعلية Landed Cost — مصدرها حركة الشراء');

            $table->decimal('discount', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0)
                  ->comment('(quantity × unit_cost) − discount');

            // Timestamps
            $table->timestamps();

            // فهارس
            $table->index('purchase_return_id');
            $table->index('purchase_invoice_detail_id');
            $table->index('item_id');
            $table->index('warehouse_id');

            // FKs
            $table->foreign('purchase_return_id')
                  ->references('purchase_return_id')->on('purchase_returns')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('purchase_invoice_detail_id')
                  ->references('purchase_invoice_detail_id')->on('purchase_invoice_details')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            $table->foreign('item_id')
                  ->references('itemID')->on('Items')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            $table->foreign('type_id')
                  ->references('id')->on('type')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            $table->foreign('unit_id')
                  ->references('UnitID')->on('units')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            $table->foreign('warehouse_id')
                  ->references('StockID')->on('stocks')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_details');
    }
};