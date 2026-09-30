<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول رأس مرتجع البيع
     * -----------------------------------------------------
     * مستند مستقل تمامًا — لا يُعدَّل أي شيء على فاتورة البيع الأصلية.
     *
     * المفاتيح الأجنبية:
     *  - original_sales_invoice_id → sales_invoices.sales_invoice_id
     *  - account_id                → characcount.accountID (حساب العميل)
     *  - payment_account_id        → characcount.accountID (حساب الدفع الفوري)
     *  - coin_id                   → coins.coinsID
     *
     * payment_method: 1=credit, 2=cash, 3=bank, 4=network
     */
    public function up(): void
    {
        if (Schema::hasTable('sales_returns')) {
            return;
        }

        Schema::create('sales_returns', function (Blueprint $table) {

            // PK
            $table->id('sales_return_id');

            // رقم المرتجع (تسلسل مستقل)
            $table->string('return_number', 50)->unique();

            // التاريخ
            $table->date('return_date');

            // الفاتورة الأصلية
            $table->unsignedBigInteger('original_sales_invoice_id')
                  ->comment('الفاتورة الأصلية في sales_invoices');

            // العميل (منسوخ من الفاتورة الأصلية)
            $table->unsignedBigInteger('account_id')->nullable()
                  ->comment('حساب العميل في characcount');

            // حساب الدفع الفوري
            $table->unsignedBigInteger('payment_account_id')->nullable()
                  ->comment('حساب الدفع (صندوق/بنك/محفظة)');

            // العملة وسعر الصرف
            $table->unsignedBigInteger('coin_id')->nullable()
                  ->comment('العملة من جدول coins');

            $table->decimal('exchange_rate', 18, 6)->default(1);

            // طريقة الدفع
            $table->unsignedTinyInteger('payment_method')->default(1)
                  ->comment('1=credit, 2=cash, 3=bank, 4=network');

            // الإجماليات
            $table->decimal('items_total', 18, 6)->default(0)
                  ->comment('Σ (quantity × price)');

            $table->decimal('discount_total', 18, 6)->default(0)
                  ->comment('Σ discount');

            // نصوص
            $table->text('statement')->nullable();
            $table->text('reference')->nullable();

            // Timestamps
            $table->timestamps();

            // فهارس
            $table->index('return_date');
            $table->index('original_sales_invoice_id');
            $table->index('account_id');

            // FKs
            $table->foreign('original_sales_invoice_id')
                  ->references('sales_invoice_id')->on('sales_invoices')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            $table->foreign('account_id')
                  ->references('accountID')->on('characcount')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            $table->foreign('payment_account_id')
                  ->references('accountID')->on('characcount')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            $table->foreign('coin_id')
                  ->references('coinsID')->on('coins')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};