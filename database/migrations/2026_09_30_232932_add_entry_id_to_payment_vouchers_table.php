<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->unsignedBigInteger('entryID')
                  ->nullable()
                  ->after('coinsID');

            $table->foreign('entryID')
                  ->references('entryID')
                  ->on('Journal_Entries')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            $table->index('entryID', 'idx_pv_entry');
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropForeign(['entryID']);
            $table->dropIndex('idx_pv_entry');
            $table->dropColumn('entryID');
        });
    }
};