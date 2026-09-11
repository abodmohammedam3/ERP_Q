<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // حذف العلاقة القديمة مع العملات
        if (Schema::hasTable('boxes')) {
            Schema::table('boxes', function (Blueprint $table) {
                try { $table->dropForeign(['coinsID']); } catch (\Throwable $e) {}
            });
        }

        // حذف الجدول القديم وإعادة إنشائه بالبنية الصحيحة
        Schema::dropIfExists('boxes');

        Schema::create('boxes', function (Blueprint $table) {
            $table->bigIncrements('boxID');                    // bigint unsigned auto_increment
            $table->unsignedBigInteger('coinsID');             // ← مطابق لـ coins.coinsID
            $table->unsignedBigInteger('accountID');           // ← مطابق لـ characcount.accountID
            $table->string('boxName', 255);                    // اسم الصندوق
            $table->tinyInteger('is_active')->default(1);      // نشط / غير نشط

            // العلاقات
            $table->foreign('coinsID')
                  ->references('coinsID')->on('coins')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            $table->foreign('accountID')
                  ->references('accountID')->on('characcount')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            $table->index('coinsID');
            $table->index('accountID');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};