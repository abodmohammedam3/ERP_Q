<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            // ═══════════════════════════════════════════
            // التتبع (من أنشأ / عدّل)
            // ═══════════════════════════════════════════
            $table->unsignedBigInteger('created_by')
                ->nullable()
                ->after('notes')
                ->comment('معرّف المستخدم الذي أنشأ السند');

            $table->unsignedBigInteger('updated_by')
                ->nullable()
                ->after('created_by')
                ->comment('معرّف المستخدم الذي عدّل السند');

            // ═══════════════════════════════════════════
            // الترحيل
            // ═══════════════════════════════════════════
            $table->boolean('is_posted')
                ->default(false)
                ->after('entryID')
                ->comment('هل السند مرحّل محاسبياً؟');

            $table->timestamp('posted_at')
                ->nullable()
                ->after('is_posted')
                ->comment('تاريخ ووقت الترحيل');

            $table->unsignedBigInteger('posted_by')
                ->nullable()
                ->after('posted_at')
                ->comment('معرّف المستخدم الذي رحّل السند');

            // ═══════════════════════════════════════════
            // إصلاح: cascade → restrict على الحسابات
            // ═══════════════════════════════════════════
            $table->dropForeign(['creditAccountID']);
            $table->dropForeign(['debitAccountID']);

            $table->foreign('creditAccountID')
                ->references('accountID')
                ->on('characcount')
                ->restrictOnDelete();

            $table->foreign('debitAccountID')
                ->references('accountID')
                ->on('characcount')
                ->restrictOnDelete();

            // ═══════════════════════════════════════════
            // فهارس جديدة
            // ═══════════════════════════════════════════
            $table->index('coinsID', 'idx_rv_coins');
            $table->index(['is_posted', 'voucherDate'], 'idx_rv_posted_date');
            $table->index('created_by', 'idx_rv_created_by');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            // حذف الفهارس
            $table->dropIndex('idx_rv_coins');
            $table->dropIndex('idx_rv_posted_date');
            $table->dropIndex('idx_rv_created_by');

            // إرجاع cascade
            $table->dropForeign(['creditAccountID']);
            $table->dropForeign(['debitAccountID']);

            $table->foreign('creditAccountID')
                ->references('accountID')
                ->on('characcount')
                ->cascadeOnDelete();

            $table->foreign('debitAccountID')
                ->references('accountID')
                ->on('characcount')
                ->cascadeOnDelete();

            // حذف الحقول الجديدة
            $table->dropColumn([
                'created_by', 'updated_by',
                'is_posted', 'posted_at', 'posted_by',
            ]);
        });
    }
};