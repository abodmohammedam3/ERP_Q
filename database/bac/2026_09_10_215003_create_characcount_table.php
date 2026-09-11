<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characcount', function (Blueprint $table) {

            // المفتاح الأساسي - تلقائي Auto Increment
            $table->bigIncrements('accountID');

            // رقم الحساب المحاسبي
            $table->string('accCode', 50)->unique();

            // اسم الحساب
            $table->string('accName', 255);

            // الحساب الأب
            $table->unsignedBigInteger('accParent')->nullable();

            // مستوى الحساب
            $table->unsignedInteger('accLevel')->default(1);

            // نوع الحساب - رقم
            $table->unsignedBigInteger('accTypeID')->nullable();

            // طبيعة الحساب
            // 1 = مدين
            // 2 = دائن
            $table->tinyInteger('nature')->default(1);

            // قابل للترحيل
            // 0 = لا
            // 1 = نعم
            $table->tinyInteger('isPostable')->default(0);

            // حالة الحساب
            // 0 = غير فعال
            // 1 = فعال
            $table->tinyInteger('IsActive')->default(1);

            // حساب نظامي
            // 0 = لا
            // 1 = نعم
            $table->tinyInteger('is_system')->default(0);

            // مفتاح الحساب النظامي
            // مثال:
            // customers
            // suppliers
            // cash
            // banks
            $table->string('system_key', 50)->nullable()->unique();


            /*
            |--------------------------------------------------------------------------
            | الحساب الأب
            |--------------------------------------------------------------------------
            */

            $table->foreign('accParent')
                ->references('accountID')
                ->on('characcount')
                ->nullOnDelete()
                ->cascadeOnUpdate();


            /*
            |--------------------------------------------------------------------------
            | الفهارس
            |--------------------------------------------------------------------------
            */

            $table->index('accParent');
            $table->index('accLevel');
            $table->index('accTypeID');
            $table->index('nature');
            $table->index('IsActive');
            $table->index('isPostable');
            $table->index('is_system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characcount');
    }
};

