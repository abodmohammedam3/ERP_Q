<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openingBalances', function (Blueprint $table) {

            // ============ المفتاح الأساسي ============
            $table->bigIncrements('openingBalancesID');

            // ============ الحساب والعملة ============
            $table->unsignedBigInteger('accountID')->nullable();
            $table->unsignedBigInteger('coinsID')->nullable();

            // ============ القيم المالية ============
            $table->decimal('opeExchangeRate', 18, 6)->default(1.000000);
            $table->decimal('opeDebit', 18, 2)->default(0.00);
            $table->decimal('opeCredit', 18, 2)->default(0.00);

            // ============ السنة المالية والتاريخ ============
            $table->date('opeFiscalYear');
            $table->dateTime('opeData')->useCurrent();

            // ============ المفاتيح الأجنبية ============
            $table->foreign('accountID')
                  ->references('accountID')
                  ->on('charAccount')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            $table->foreign('coinsID')
                  ->references('coinsID')
                  ->on('Coins')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openingBalances');
    }
};