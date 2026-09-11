<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // تعطيل فحص المفاتيح الأجنبية مؤقتاً
        Schema::disableForeignKeyConstraints();

        // حذف العلاقات من الجداول المرتبطة إن وُجدت
        if (Schema::hasTable('boxes')) {
            Schema::table('boxes', function (Blueprint $table) {
                try {
                    $table->dropForeign(['coinsID']);
                } catch (\Throwable $e) {
                    // تجاهل إن لم توجد
                }
            });
        }

        if (Schema::hasTable('banks')) {
            Schema::table('banks', function (Blueprint $table) {
                try {
                    $table->dropForeign(['coinsID']);
                } catch (\Throwable $e) {
                    // تجاهل إن لم توجد
                }
            });
        }

        // إعادة إنشاء جدول العملات بالبنية الصحيحة
        Schema::dropIfExists('coins');

        Schema::create('coins', function (Blueprint $table) {
            $table->bigIncrements('coinsID');           // bigint unsigned auto_increment
            $table->string('coinsName', 100);           // اسم العملة
            $table->string('coinsCode', 10)->unique();  // رمز العملة
            $table->decimal('coinsExchangeRate', 18, 6)->nullable();
            $table->tinyInteger('coinsSystem')->default(0);  // 1 = عملة النظام الأساسية
            $table->tinyInteger('is_active')->default(1);    // 1 = نشطة

            $table->index('coinsName');
        });

        // إعادة العلاقات في الجداول المرتبطة
        if (Schema::hasTable('boxes')) {
            Schema::table('boxes', function (Blueprint $table) {
                // تعديل نوع العمود ليطابق coinsID الجديد
                $table->unsignedBigInteger('coinsID')->nullable()->change();

                $table->foreign('coinsID')
                      ->references('coinsID')->on('coins')
                      ->onUpdate('cascade')
                      ->onDelete('restrict');
            });
        }

        if (Schema::hasTable('banks')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->unsignedBigInteger('coinsID')->change();

                $table->foreign('coinsID')
                      ->references('coinsID')->on('coins')
                      ->onUpdate('cascade')
                      ->onDelete('restrict');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // لا شيء
    }
};