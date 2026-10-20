<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة الحقول التدقيقية إلى جدول سندات الصرف.
     *
     * الحقول المضافة:
     *   - created_by : معرّف المستخدم المُنشئ
     *   - updated_by : معرّف آخر مستخدم عدّل السند
     *   - is_posted  : حالة الترحيل (0=مسودة, 1=مُرحّل)
     *   - posted_at  : تاريخ ووقت الترحيل الفعلي
     *   - posted_by  : معرّف المستخدم الذي رحّل السند
     *
     * مطابقة تماماً لبنية receipt_vouchers.
     */
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {

            if (! Schema::hasColumn('payment_vouchers', 'created_by')) {
                $table->unsignedBigInteger('created_by')
                      ->nullable()
                      ->after('notes')
                      ->comment('المستخدم الذي أنشأ السند');

                $table->foreign('created_by')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_vouchers', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')
                      ->nullable()
                      ->after('created_by')
                      ->comment('المستخدم الذي عدّل السند آخر مرة');

                $table->foreign('updated_by')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_vouchers', 'is_posted')) {
                $table->boolean('is_posted')
                      ->default(false)
                      ->after('updated_by')
                      ->comment('حالة الترحيل: 0=مسودة, 1=مُرحّل');

                $table->index('is_posted', 'idx_payment_vouchers_is_posted');
            }

            if (! Schema::hasColumn('payment_vouchers', 'posted_at')) {
                $table->timestamp('posted_at')
                      ->nullable()
                      ->after('is_posted')
                      ->comment('تاريخ ووقت الترحيل');
            }

            if (! Schema::hasColumn('payment_vouchers', 'posted_by')) {
                $table->unsignedBigInteger('posted_by')
                      ->nullable()
                      ->after('posted_at')
                      ->comment('المستخدم الذي رحّل السند');

                $table->foreign('posted_by')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {

            // إسقاط الـ Foreign Keys أولاً
            foreach ([
                'payment_vouchers_created_by_foreign',
                'payment_vouchers_updated_by_foreign',
                'payment_vouchers_posted_by_foreign',
            ] as $fk) {
                try { $table->dropForeign($fk); } catch (\Throwable $e) {}
            }

            // إسقاط الفهرس
            try { $table->dropIndex('idx_payment_vouchers_is_posted'); } catch (\Throwable $e) {}

            // إسقاط الأعمدة
            foreach (['created_by', 'updated_by', 'is_posted', 'posted_at', 'posted_by'] as $col) {
                if (Schema::hasColumn('payment_vouchers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};