<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->decimal('bankID', 20, 0)->primary();
            $table->text('bankName');
            $table->unsignedBigInteger('accountID');  // ✅ bigint unsigned = characcount.accountID
            $table->decimal('coinsID', 20, 0);        // ✅ decimal(20,0)   = coins.coinsID
            $table->decimal('accountNumber', 30, 0);

            // العلاقة مع الدليل المحاسبي
            $table->foreign('accountID')
                  ->references('accountID')->on('characcount')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            // العلاقة مع العملات
            $table->foreign('coinsID')
                  ->references('coinsID')->on('coins')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            $table->index('accountID');
            $table->index('coinsID');
            $table->index('accountNumber');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropForeign(['accountID']);
            $table->dropForeign(['coinsID']);
        });

        Schema::dropIfExists('banks');
    }
};