<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->decimal('boxID', 20, 0)->primary();
            $table->decimal('coinsID', 20, 0)->nullable();
            $table->decimal('accountID', 20, 0)->nullable();
            $table->text('boxName');

            // العلاقة مع جدول العملات
            $table->foreign('coinsID')
                  ->references('coinsID')->on('coins')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            // فهارس للبحث السريع
            $table->index('coinsID');
            $table->index('accountID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropForeign(['coinsID']);
        });

        Schema::dropIfExists('boxes');
    }
};