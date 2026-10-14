<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول رأس مرتجع الشراء
     * -----------------------------------------------------
     * مستند مستقل تمامًا — لا يُعدَّل أي شيء على فاتورة الشراء الأصلية.
     *
     * المفاتيح الأجنبية:
     *  - original_purchase_invoice_id → purchase_invoices.purchase_invoice_id
     *  - account_id                   → characcount.accountID (حساب المورد)
     *  - payment_account_id           → characcount.accountID (حساب الدفع الفوري)
     *  - coin_id                      → coins.coinsID
     *  - warehouse_id                 → stocks.StockID (المستودع المُرتجع منه)
     *
     * payment_method: 1=credit, 2=cash, 3=bank, 4=network
     */
    public function up(): void
    {
        if (Schema::hasTable('purchase_returns')) {
            return;
        }

        Schema::create('purchase_returns', function (Blueprint $table) {

            // PK
            $table->id('purchase_return_id');

            // رقم المرتجع (تسلسل مستقل)
            $table->string('return_number', 50)->unique();

            // التاريخ
            $table->date('return_date');

            // الفاتورة الأصلية
            $table->unsignedBigInteger('original_purchase_invoice_id')
                  ->comment('الفاتورة الأصلية في purchase_invoices');

            // المورد (منسوخ من الفاتورة الأصلية)
            $table->unsignedBigInteger('account_id')->nullable()
                  ->comment('حساب المورد في characcount');

            // حساب الدفع الفوري
            $table->unsignedBigInteger('payment_account_id')->nullable()
                  ->comment('حساب الدفع (صندوق/بنك/محفظة)');

            // العملة
            $table->unsignedBigInteger('coin_id')->nullable()
                  ->comment('العملة من جدول coins');

            // المستودع
            $table->unsignedBigInteger('warehouse_id')->nullable()
                  ->comment('المستودع المُرتجع منه');

            // سعر الصرف وطريقة الدفع
            $table->decimal('exchange_rate', 18, 6)->default(1);

            $table->unsignedTinyInteger('payment_method')->default(1)
                  ->comment('1=credit, 2=cash, 3=bank, 4=network');

            // الإجماليات
            $table->decimal('items_total', 18, 6)->default(0)
                  ->comment('Σ (quantity × unit_cost)');

            $table->decimal('discount_total', 18, 6)->default(0)
                  ->comment('Σ discount');

            // نصوص
            $table->text('statement')->nullable();
            $table->text('reference')->nullable();

            // Timestamps
            $table->timestamps();

            // فهارس
            $table->index('return_date');
            $table->index('original_purchase_invoice_id');
            $table->index('account_id');

            // FKs
            $table->foreign('original_purchase_invoice_id')
                  ->references('purchase_invoice_id')->on('purchase_invoices')
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

            $table->foreign('warehouse_id')
                  ->references('StockID')->on('stocks')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};