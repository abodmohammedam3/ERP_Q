<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول رأس فاتورة البيع
     * -----------------------------------------------------
     * FKs:
     *  - account_id          → characcount.accountID (حساب العميل)
     *  - payment_account_id  → characcount.accountID (حساب الدفع الفوري)
     *  - coin_id             → coins.coinsID
     *
     * payment_method: 1=credit, 2=cash, 3=bank, 4=network
     */
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {

            // PK
            $table->id('sales_invoice_id');

            // رقم الفاتورة
            $table->string('invoice_number', 50)->unique();

            // التاريخ
            $table->date('invoice_date');

            // العميل (حساب محاسبي)
            $table->unsignedBigInteger('account_id')->nullable()
                  ->comment('حساب العميل في characcount');

            // حساب الدفع الفوري
            $table->unsignedBigInteger('payment_account_id')->nullable()
                  ->comment('حساب الدفع (صندوق/بنك/محفظة)');

            // العملة
            $table->unsignedBigInteger('coin_id')->nullable()
                  ->comment('العملة من جدول coins');

            // سعر الصرف
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
            $table->index('invoice_number');
            $table->index('invoice_date');
            $table->index('payment_method');

            // FKs
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
        Schema::dropIfExists('sales_invoices');
    }
};