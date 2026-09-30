<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('openingBalances', function (Blueprint $table) {
            // إضافة عمود entryID مباشرة بعد coinsID
            $table->unsignedBigInteger('entryID')
                  ->nullable()
                  ->after('coinsID');

            // ربط مع جدول القيود
            $table->foreign('entryID')
                  ->references('entryID')
                  ->on('Journal_Entries')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            $table->index('entryID', 'idx_ob_entry');
        });
    }

    public function down(): void
    {
        Schema::table('openingBalances', function (Blueprint $table) {
            $table->dropForeign(['entryID']);
            $table->dropIndex('idx_ob_entry');
            $table->dropColumn('entryID');
        });
    }
};