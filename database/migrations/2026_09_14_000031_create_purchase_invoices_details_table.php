<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول تفاصيل فاتورة الشراء (أصناف الفاتورة)
     * -----------------------------------------------------
     * المفاتيح الأجنبية:
     *  - purchase_invoice_id → purchase_invoices.purchase_invoice_id
     *  - item_id             → Items.itemID
     *  - type_id             → type.id
     *  - unit_id             → units.UnitID
     *
     * ملاحظات:
     *  - code يُخزَّن للتاريخ حتى لو تغيّر لاحقًا.
     *  - total = (quantity × price) − discount
     *  - Cascade على purchase_invoice_id: حذف الرأس يحذف التفاصيل تلقائيًا.
     */
    public function up(): void
    {
        Schema::create('purchase_invoice_details', function (Blueprint $table) {

            // =========================================
            // المفتاح الأساسي
            // =========================================
            $table->id('purchase_invoice_detail_id');

            // =========================================
            // ربط برأس الفاتورة
            // =========================================
            $table->unsignedBigInteger('purchase_invoice_id')
                  ->comment('FK → purchase_invoices');

            // =========================================
            // بيانات الصنف
            // =========================================
            $table->unsignedBigInteger('item_id')->nullable()
                  ->comment('الصنف من Items');

            $table->unsignedBigInteger('type_id')->nullable()
                  ->comment('النوع من type');

            $table->unsignedBigInteger('unit_id')->nullable()
                  ->comment('الوحدة من units');

            $table->string('code', 50)->nullable()
                  ->comment('رمز الصنف (يُخزَّن للتاريخ)');

            // =========================================
            // الكميات والأسعار
            // =========================================
            $table->decimal('quantity', 18, 6)->default(0)
                  ->comment('الكمية أو الوزن');

            $table->decimal('price', 18, 6)->default(0)
                  ->comment('سعر الوحدة');

            $table->decimal('discount', 18, 6)->default(0)
                  ->comment('الخصم على الصف');

            $table->decimal('total', 18, 6)->default(0)
                  ->comment('إجمالي الصف = (quantity × price) − discount');

            // =========================================
            // Timestamps
            // =========================================
            $table->timestamps();

            // =========================================
            // الفهارس
            // =========================================
            $table->index('purchase_invoice_id');
            $table->index('item_id');
            $table->index('type_id');
            $table->index('unit_id');

            // =========================================
            // المفاتيح الأجنبية
            // =========================================

            // الربط برأس الفاتورة (Cascade)
            $table->foreign('purchase_invoice_id')
                  ->references('purchase_invoice_id')
                  ->on('purchase_invoices')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // الصنف → Items
            $table->foreign('item_id')
                  ->references('itemID')->on('Items')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // النوع → type
            $table->foreign('type_id')
                  ->references('id')->on('type')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // الوحدة → units
            $table->foreign('unit_id')
                  ->references('UnitID')->on('units')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_details');
    }
};