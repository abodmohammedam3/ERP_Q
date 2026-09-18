<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول تفاصيل فاتورة البيع
     * -----------------------------------------------------
     * ملاحظة: warehouse_id على مستوى الصف (عكس فاتورة الشراء)
     * cost_price: يُخزَّن لحساب الربح لاحقًا
     */
    public function up(): void
    {
        Schema::create('sales_invoice_details', function (Blueprint $table) {

            // PK
            $table->id('sales_invoice_detail_id');

            // ربط برأس الفاتورة
            $table->unsignedBigInteger('sales_invoice_id');

            // بيانات الصنف
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();

            // المخزن (على مستوى الصف)
            $table->unsignedBigInteger('warehouse_id')
                  ->comment('مخزن الصرف');

            // الرمز
            $table->string('code', 50)->nullable();

            // الكميات والأسعار
            $table->decimal('quantity', 18, 6)->default(0);
            $table->decimal('price', 18, 6)->default(0)
                  ->comment('سعر البيع للوحدة');

            $table->decimal('cost_price', 18, 6)->nullable()
                  ->comment('تكلفة الوحدة لحظة البيع');

            $table->decimal('discount', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0)
                  ->comment('(quantity × price) − discount');

            // Timestamps
            $table->timestamps();

            // فهارس
            $table->index('sales_invoice_id');
            $table->index('item_id');
            $table->index('warehouse_id');

            // FKs
            $table->foreign('sales_invoice_id')
                  ->references('sales_invoice_id')->on('sales_invoices')
                  ->onDelete('cascade')
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
        Schema::dropIfExists('sales_invoice_details');
    }
};