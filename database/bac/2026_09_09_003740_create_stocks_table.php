<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // إذا كان الجدول موجوداً مسبقاً، احذفه وأعد إنشاءه (فقط في بيئة التطوير)
        // أو استخدم Schema::table إذا كنت تريد الحفاظ على البيانات.
        if (!Schema::hasTable('stocks')) {
            Schema::create('stocks', function (Blueprint $table) {
                $table->id('StockID');
                $table->string('StockName');
                $table->unsignedBigInteger('accountID');
                $table->foreign('accountID')
                      ->references('accountID')
                      ->on('characcount')
                      ->onDelete('restrict');
                $table->boolean('is_active')->default(1);
            });
        } else {
            // إذا كان الجدول موجوداً، نضيف الأعمدة المفقودة
            Schema::table('stocks', function (Blueprint $table) {
                if (!Schema::hasColumn('stocks', 'accountID')) {
                    $table->unsignedBigInteger('accountID')->nullable()->after('StockName');
                    $table->foreign('accountID')->references('accountID')->on('characcount')->onDelete('restrict');
                }
                if (!Schema::hasColumn('stocks', 'is_active')) {
                    $table->boolean('is_active')->default(1);
                }
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('stocks');
    }
};