<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول تفاصيل مرتجع البيع
     * -----------------------------------------------------
     * المفاتيح الأجنبية:
     *  - sales_return_id        → sales_returns.sales_return_id (Cascade)
     *  - sales_invoice_detail_id→ sales_invoice_details.sales_invoice_detail_id
     *  - item_id                → Items.itemID
     *  - type_id                → type.id
     *  - unit_id                → units.UnitID
     *  - warehouse_id           → stocks.StockID
     *
     * cost_price: تُنسخ من sales_invoice_details.cost_price
     *             لعكس أثر تكلفة البضاعة المبيعة في تقارير الربح.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales_return_details')) {
            return;
        }

        Schema::create('sales_return_details', function (Blueprint $table) {

            // PK
            $table->id('sales_return_detail_id');

            // ربط برأس المرتجع
            $table->unsignedBigInteger('sales_return_id');

            // ⭐ ربط بسطر الفاتورة الأصلية (لحساب الكمية المتبقية)
            $table->unsignedBigInteger('sales_invoice_detail_id')
                  ->comment('سطر الفاتورة الأصلية');

            // بيانات الصنف (منسوخة للحفاظ على ثبات التقارير التاريخية)
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');
            $table->string('code', 50)->nullable();

            // الكميات والأسعار
            $table->decimal('quantity', 18, 6)->default(0)
                  ->comment('الكمية المرتجعة');

            $table->decimal('price', 18, 6)->default(0)
                  ->comment('سعر البيع للوحدة (منسوخ من السطر الأصلي)');

            $table->decimal('cost_price', 18, 6)->nullable()
                  ->comment('تكلفة الوحدة الأصلية لحظة البيع — لعكس أثر COGS');

            $table->decimal('discount', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0)
                  ->comment('(quantity × price) − discount');

            // Timestamps
            $table->timestamps();

            // فهارس
            $table->index('sales_return_id');
            $table->index('sales_invoice_detail_id');
            $table->index('item_id');
            $table->index('warehouse_id');

            // FKs
            $table->foreign('sales_return_id')
                  ->references('sales_return_id')->on('sales_returns')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('sales_invoice_detail_id')
                  ->references('sales_invoice_detail_id')->on('sales_invoice_details')
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
        Schema::dropIfExists('sales_return_details');
    }
};