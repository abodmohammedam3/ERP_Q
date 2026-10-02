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
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->unsignedBigInteger('entryID')
                ->nullable()
                ->after('coinsID')
                ->comment('القيد المحاسبي المرتبط بسند القبض');

            $table->foreign('entryID')
                ->references('entryID')
                ->on('Journal_Entries')
                ->restrictOnDelete();

            $table->index('entryID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->dropForeign(['entryID']);
            $table->dropIndex(['entryID']);
            $table->dropColumn('entryID');
        });
    }
};