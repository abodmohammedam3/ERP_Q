<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط رأس فاتورة الشراء بالقيد المحاسبي عبر Foreign Key.
     *
     * - entryID يشير إلى journal_entries.entryID
     * - nullOnDelete: عند حذف القيد → يُفرَّغ الحقل تلقائيًا
     * - cascadeOnUpdate: عند تحديث entryID → يُحدَّث تلقائيًا هنا
     */
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('entryID')
                ->nullable()
                ->after('total_in_base_currency');

            $table->foreign('entryID')
                ->references('entryID')
                ->on('journal_entries')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['entryID']);
            $table->dropColumn('entryID');
        });
    }
};