<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {

            $table->id();

            // 🔑 المفاتيح
            $table->unsignedBigInteger('accountID');
            $table->unsignedSmallInteger('fiscalYear');       // 2025, 2026
            $table->unsignedBigInteger('coinsID');            // نظام أم عملة أجنبية

            // 💰 الأرصدة
            $table->decimal('debitTotal',  18, 2)->default(0);
            $table->decimal('creditTotal', 18, 2)->default(0);
            $table->decimal('balance',     18, 2)->default(0);  // debit - credit

            // 🕐 تتبع
            $table->timestamp('lastUpdatedAt')->useCurrent();

            // 🔗 العلاقات
            $table->foreign('accountID')
                  ->references('accountID')
                  ->on('characcount')
                  ->onDelete('cascade');

            $table->foreign('coinsID')
                  ->references('coinsID')
                  ->on('coins')
                  ->onDelete('restrict');

            // 📇 الفهارس
            $table->unique(['accountID', 'fiscalYear', 'coinsID'], 'uq_account_year_currency');
            $table->index(['fiscalYear', 'coinsID'], 'idx_year_currency');
            $table->index('balance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};