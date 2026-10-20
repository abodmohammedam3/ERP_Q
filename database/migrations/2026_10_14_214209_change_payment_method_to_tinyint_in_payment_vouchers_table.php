<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تحويل عمود paymentMethod من VARCHAR(20) إلى TINYINT
     * ليطابق بنية receipt_vouchers:
     *   2 = نقد  (CASH)
     *   3 = بنك  (BANK)
     *
     * الخطوات:
     *   1) فحص نوع العمود الحالي
     *   2) ترحيل القيم النصية → رقمية (cash/نقد → 2, bank/بنك → 3)
     *   3) تغيير النوع إلى TINYINT NOT NULL DEFAULT 2
     */
    public function up(): void
    {
        if (! Schema::hasColumn('payment_vouchers', 'paymentMethod')) {
            return;
        }

        // ─── 1) فحص النوع الحالي ──────────────────────────────────────
        $type = strtolower((string) (Schema::getConnection()->selectOne(
            "SELECT DATA_TYPE
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'payment_vouchers'
                AND COLUMN_NAME  = 'paymentMethod'
              LIMIT 1"
        )->DATA_TYPE ?? ''));

        // إن كان رقمياً بالفعل → لا حاجة للتحويل
        if (! in_array($type, ['varchar', 'char', 'text'], true)) {
            return;
        }

        // ─── 2) ترحيل القيم النصية → رقمية ────────────────────────────
        DB::statement("
            UPDATE `payment_vouchers`
               SET `paymentMethod` = CASE
                     WHEN `paymentMethod` IN ('2','cash','CASH','Cash','نقد','نقدي','نقداً') THEN '2'
                     WHEN `paymentMethod` IN ('3','bank','BANK','Bank','بنك','تحويل بنكي','حوالة') THEN '3'
                     WHEN `paymentMethod` IS NULL OR `paymentMethod` = '' THEN '2'
                     WHEN `paymentMethod` REGEXP '^[0-9]+$' THEN `paymentMethod`
                     ELSE '2'
                   END
        ");

        // ─── 3) تغيير النوع ────────────────────────────────────────────
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->tinyInteger('paymentMethod')
                  ->default(2)
                  ->comment('طريقة الدفع: 2=نقد, 3=بنك')
                  ->change();
        });

        Log::info('[Migration] payment_vouchers.paymentMethod converted to TINYINT (2=cash, 3=bank)');
    }

    /**
     * التراجع: إعادة العمود إلى VARCHAR(20).
     * ملاحظة: لا يمكن استعادة النصوص الأصلية — تُرجع '2'/'3' كنصوص.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('payment_vouchers', 'paymentMethod')) {
            return;
        }

        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->string('paymentMethod', 20)
                  ->default('2')
                  ->comment('طريقة الدفع (نصية)')
                  ->change();
        });
    }
};