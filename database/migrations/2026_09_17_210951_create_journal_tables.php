<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ══════════════════════════════════════════════════════════
        //  1) جدول رأس القيد — Journal_Entries
        // ══════════════════════════════════════════════════════════
        Schema::create('Journal_Entries', function (Blueprint $table) {

            // ─── المفتاح الأساسي ───
            $table->bigIncrements('entryID')
                  ->comment('معرف القيد');

            // ─── نوع المستند ───
            $table->string('docType', 50)
                  ->nullable()
                  ->comment('نوع المستند: قيد افتتاحي / سند قبض / فاتورة بيع ...');

            // ─── رقم المستند ───
            $table->string('docNumber', 50)
                  ->nullable()
                  ->comment('رقم المستند');

            // ─── رقم القيد ───
            $table->unsignedBigInteger('entryNo')
                  ->comment('رقم القيد المتسلسل');

            // ─── التاريخ ───
            $table->date('entryDate')
                  ->comment('تاريخ القيد');

            // ─── البيان ───
            $table->text('description2')
                  ->comment('بيان القيد');

            // ─── المبلغ الإجمالي ───
            $table->decimal('totalAmount', 18, 2)
                  ->default(0)
                  ->comment('المبلغ الإجمالي بالعملة الأساسية');

            // ─── تاريخ الإنشاء ───
            $table->dateTime('createdAt')
                  ->useCurrent()
                  ->comment('تاريخ الإنشاء');

            // ─── الفهارس ───
            $table->unique('entryNo', 'uq_je_entry_no');
            $table->index('entryDate', 'idx_je_date');
            $table->index('docType', 'idx_je_doc_type');
            $table->index('docNumber', 'idx_je_doc_number');
            $table->index(['entryDate', 'entryNo'], 'idx_je_date_no');
        });

        // ══════════════════════════════════════════════════════════
        //  2) جدول أسطر القيد — JournalEntrryLine
        // ══════════════════════════════════════════════════════════
        Schema::create('JournalEntrryLine', function (Blueprint $table) {

            // ─── المفتاح الأساسي ───
            $table->bigIncrements('entryLineID')
                  ->comment('معرف السطر');

            // ─── الربط بالرأس ───
            $table->unsignedBigInteger('entryID')
                  ->comment('معرف القيد');

            // ─── الحساب ───
            $table->unsignedBigInteger('accountID')
                  ->comment('معرف الحساب');

            // ─── العملة ───
            $table->unsignedBigInteger('coinsID')
                  ->nullable()
                  ->comment('العملة');

            // ─── البيان ───
            $table->text('description2')
                  ->nullable()
                  ->comment('بيان السطر');

            // ─── سعر الصرف ───
            $table->decimal('exchangRate', 18, 6)
                  ->default(1)
                  ->comment('سعر الصرف');

            // ─── المبالغ بالعملة الأجنبية ───
            $table->decimal('debit', 18, 2)
                  ->default(0)
                  ->comment('مدين بالعملة الأجنبية');

            $table->decimal('credit', 18, 2)
                  ->default(0)
                  ->comment('دائن بالعملة الأجنبية');

            // ─── المبالغ بالعملة الأساسية ───
            $table->decimal('localDebit', 18, 2)
                  ->default(0)
                  ->comment('مدين بالعملة الأساسية');

            $table->decimal('localCredit', 18, 2)
                  ->default(0)
                  ->comment('دائن بالعملة الأساسية');

            // ─── الفهارس ───
            $table->index('entryID', 'idx_jel_entry');
            $table->index('accountID', 'idx_jel_account');
            $table->index('coinsID', 'idx_jel_coins');
            $table->index(['accountID', 'entryID'], 'idx_jel_account_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('JournalEntrryLine');
        Schema::dropIfExists('Journal_Entries');
    }
};