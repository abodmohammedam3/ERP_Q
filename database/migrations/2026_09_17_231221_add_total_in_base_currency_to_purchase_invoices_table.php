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
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_invoices', 'total_in_base_currency')) {
                $table->decimal('total_in_base_currency', 20, 6)
                      ->default(0)
                      ->after('exchange_rate')
                      ->comment('الإجمالي بالعملة الأساسية للنظام');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_invoices', 'total_in_base_currency')) {
                $table->dropColumn('total_in_base_currency');
            }
        });
    }
};