<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول رأس حركة المخزون
     * -----------------------------------------------------
     * المفاتيح الأجنبية:
     *  - warehouse_id → stocks.StockID (المخزن الرئيسي/الافتراضي)
     *
     * الحقول الجديدة:
     *  - source_type / source_id: لربط الحركة بالفاتورة المصدر
     *    (purchase_invoice / sales_invoice) - polymorphic منطقي
     *
     * أنواع الحركات (movement_type):
     *  supply           = توريد مخزني (يدوي)
     *  issue            = صرف مخزني (يدوي)
     *  purchase         = توريد من فاتورة شراء
     *  sale             = صرف من فاتورة بيع
     *  purchase_return  = مرتجع شراء
     *  sale_return      = مرتجع بيع
     *
     * الاتجاه (direction):
     *  in  = دخول
     *  out = خروج
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {

            // =========================================
            // المفتاح الأساسي
            // =========================================
            $table->id('movement_id');

            // =========================================
            // رقم الحركة التسلسلي (يُولَّد على الخادم)
            // =========================================
            $table->string('display_id', 50)->unique()
                  ->comment('رقم الحركة التسلسلي مثل رقم الفاتورة');

            // =========================================
            // نوع الحركة واتجاهها
            // =========================================
            $table->string('movement_type', 30)
                  ->comment('supply/issue/purchase/sale/purchase_return/sale_return');

            $table->string('direction', 10)
                  ->comment('in = دخول / out = خروج');

            // =========================================
            // التواريخ والمستندات
            // =========================================
            $table->date('movement_date');

            $table->string('document_number', 100)->nullable()
                  ->comment('مرجع خارجي (رقم الفاتورة أو إذن يدوي)');

            // =========================================
            // المخزن الرئيسي (يُستخدم كقيمة افتراضية للصفوف)
            // =========================================
            $table->unsignedBigInteger('warehouse_id')
                  ->comment('المخزن الرئيسي/الافتراضي');

            // =========================================
            // البيان
            // =========================================
            $table->text('statement')->nullable();

            // =========================================
            // ربط بالفاتورة المصدر (Polymorphic منطقي)
            // =========================================
            $table->string('source_type', 50)->nullable()
                  ->comment('purchase_invoice / sales_invoice / null للحركات اليدوية');

            $table->unsignedBigInteger('source_id')->nullable()
                  ->comment('معرف الفاتورة في جدولها الأصلي');

            // =========================================
            // الإجمالي
            // =========================================
            $table->decimal('total', 18, 6)->default(0)
                  ->comment('إجمالي الحركة = Σ (quantity × unit_cost)');

            // =========================================
            // Timestamps
            // =========================================
            $table->timestamps();

            // =========================================
            // الفهارس
            // =========================================
            $table->index('movement_type');
            $table->index('direction');
            $table->index('movement_date');
            $table->index('warehouse_id');
            $table->index(['source_type', 'source_id']);

            // =========================================
            // المفاتيح الأجنبية
            // =========================================
            $table->foreign('warehouse_id')
                  ->references('StockID')->on('stocks')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};