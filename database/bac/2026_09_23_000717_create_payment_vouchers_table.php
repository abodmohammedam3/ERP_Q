<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {

            // ══════════════════════════════════════════════════════
            //  المفتاح الأساسي
            // ══════════════════════════════════════════════════════
            $table->id('paymentID');

            // ══════════════════════════════════════════════════════
            //  بيانات السند الأساسية
            // ══════════════════════════════════════════════════════
            $table->string('voucherNumber', 50)->unique()
                ->comment('رقم السند الفريد');

            $table->date('voucherDate')
                ->comment('تاريخ السند');

            // ══════════════════════════════════════════════════════
            //  الحساب الدائن (الصندوق/البنك) — النقدية تخرج منه
            // ══════════════════════════════════════════════════════
            $table->unsignedBigInteger('creditAccountID')
                ->comment('الحساب الدائن (الصندوق/البنك) من دليل الحسابات');

            $table->foreign('creditAccountID')
                ->references('accountID')
                ->on('characcount')
                ->cascadeOnDelete();

            // ══════════════════════════════════════════════════════
            //  الحساب المدين (المورد) — النقدية تدخل إليه
            // ══════════════════════════════════════════════════════
            $table->unsignedBigInteger('debitAccountID')
                ->comment('الحساب المدين (المورد) من دليل الحسابات');

            $table->foreign('debitAccountID')
                ->references('accountID')
                ->on('characcount')
                ->cascadeOnDelete();

            // ══════════════════════════════════════════════════════
            //  العملة
            // ══════════════════════════════════════════════════════
            $table->unsignedBigInteger('coinsID')->nullable()
                ->comment('معرف العملة');

            $table->foreign('coinsID')
                ->references('coinsID')
                ->on('coins')
                ->nullOnDelete();

            // ══════════════════════════════════════════════════════
            //  المبالغ
            // ══════════════════════════════════════════════════════
            $table->decimal('amount', 15, 2)->default(0)
                ->comment('المبلغ بعملة السند');

            $table->decimal('exchangeRate', 15, 6)->default(1)
                ->comment('سعر الصرف');

            $table->decimal('localAmount', 15, 2)->default(0)
                ->comment('المبلغ بالعملة المحلية');

            // ══════════════════════════════════════════════════════
            //  طريقة الدفع
            // ══════════════════════════════════════════════════════
            $table->string('paymentMethod', 20)->nullable()
                ->comment('طريقة الدفع: cash, bank');

            // ══════════════════════════════════════════════════════
            //  الملاحظات
            // ══════════════════════════════════════════════════════
            $table->text('notes')->nullable()
                ->comment('ملاحظات إضافية');

            // ══════════════════════════════════════════════════════
            //  الفهارس (Indexes) لتسريع البحث
            // ══════════════════════════════════════════════════════
            $table->index('voucherNumber');
            $table->index('voucherDate');
            $table->index('creditAccountID');
            $table->index('debitAccountID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
    }
};