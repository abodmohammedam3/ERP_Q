<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول رأس فاتورة الشراء
     * -----------------------------------------------------
     * المفاتيح الأجنبية:
     *  - account_id          → characcount.accountID   (حساب المورد)
     *  - payment_account_id  → characcount.accountID   (حساب الدفع الفوري)
     *  - coin_id             → coins.coinsID           (العملة)
     *  - warehouse_id        → stocks.StockID          (المخزن)
     *
     * طريقة الدفع (payment_method):
     *  1 = credit  (أجل)
     *  2 = cash    (نقد)
     *  3 = bank    (تحويل بنكي)
     *  4 = network (شبكة)
     */
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {

            // =========================================
            // المفتاح الأساسي
            // =========================================
            $table->id('purchase_invoice_id');

            // =========================================
            // بيانات الفاتورة الأساسية
            // =========================================
            $table->string('invoice_number', 50)->unique()
                  ->comment('رقم الفاتورة');

            $table->date('invoice_date')
                  ->comment('تاريخ الفاتورة');

            // =========================================
            // العلاقات
            // =========================================
            $table->unsignedBigInteger('account_id')->nullable()
                  ->comment('حساب المورد في characcount');

            $table->unsignedBigInteger('payment_account_id')->nullable()
                  ->comment('حساب الدفع (صندوق/بنك/محفظة) في characcount');

            $table->unsignedBigInteger('coin_id')->nullable()
                  ->comment('العملة من جدول coins');

            $table->unsignedBigInteger('warehouse_id')->nullable()
                  ->comment('المخزن من جدول stocks');

            // =========================================
            // سعر الصرف وطريقة الدفع
            // =========================================
            $table->decimal('exchange_rate', 18, 6)->default(1)
                  ->comment('سعر الصرف المحفوظ وقت الإنشاء');

            $table->unsignedTinyInteger('payment_method')->default(1)
                  ->comment('1=credit, 2=cash, 3=bank, 4=network');

            // =========================================
            // الإجماليات
            // =========================================
            $table->decimal('items_total', 18, 6)->default(0)
                  ->comment('قيمة الأصناف قبل الخصم = Σ (quantity × price)');

            $table->decimal('discount_total', 18, 6)->default(0)
                  ->comment('إجمالي الخصم = Σ row.discount');

            $table->decimal('expenses', 18, 6)->default(0)
                  ->comment('النفقات');

            $table->decimal('tax_cost', 18, 6)->default(0)
                  ->comment('تكلفة الضريبة');

            $table->decimal('transportation', 18, 6)->default(0)
                  ->comment('تكلفة النقل');

            $table->decimal('other_cost', 18, 6)->default(0)
                  ->comment('تكاليف أخرى');

            $table->text('other_cost_description')->nullable()
                  ->comment('وصف التكاليف الأخرى');

            // =========================================
            // بيانات نصية
            // =========================================
            $table->text('statement')->nullable()
                  ->comment('البيان');

            $table->text('reference')->nullable()
                  ->comment('مرجع خارجي');

            // =========================================
            // Timestamps
            // =========================================
            $table->timestamps();

            // =========================================
            // الفهارس
            // =========================================
            $table->index('invoice_number');
            $table->index('invoice_date');
            $table->index('payment_method');

            // =========================================
            // المفاتيح الأجنبية
            // =========================================

            // حساب المورد → characcount
            $table->foreign('account_id')
                  ->references('accountID')->on('characcount')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // حساب الدفع → characcount
            $table->foreign('payment_account_id')
                  ->references('accountID')->on('characcount')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // العملة → coins
            $table->foreign('coin_id')
                  ->references('coinsID')->on('coins')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // المخزن → stocks
            $table->foreign('warehouse_id')
                  ->references('StockID')->on('stocks')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};