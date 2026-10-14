<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * نحذف العمود القديم (string) ثم نُضيف الجديد (tinyint).
         *
         * السبب: تغيير النوع مباشرة عبر change() يتطلب doctrine/dbal
         * وقد يسبب مشاكل مع MySQL. الحذف + الإضافة أضمن.
         *
         * الجدول فارغ حالياً (0 سند)، لذا لا خسارة في البيانات.
         */
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->dropColumn('paymentMethod');
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->tinyInteger('paymentMethod')
                ->default(2)
                ->after('localAmount')
                ->comment('طريقة الدفع: 2=نقدي (cash)، 3=بنك (bank)');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->dropColumn('paymentMethod');
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->string('paymentMethod', 20)
                ->nullable()
                ->after('localAmount')
                ->comment('طريقة الدفع: cash, cheque, bank');
        });
    }
};