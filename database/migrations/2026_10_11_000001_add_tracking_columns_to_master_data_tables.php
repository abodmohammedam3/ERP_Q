<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقلي التتبع created_by / updated_by لكل جداول الإعدادات (Master Data).
 *
 * @deprecated فشل بسبب خطأ في اسم الجدول — استُبدل بـ 2026_10_11_000002.
 * (openingBalances وcharaccount فشلا؛ الناجح فقط 9 جداول).
 *
 * مرة واحدة وفق قرار المستخدم (بند 3 من قرارات الوحدة 1).
 * القيم قابلة للـ NULL لأن النظام لا يملك مصادقة بعد (H3/H4) —
 * وتُعبأ تدريجياً مع ترقية كل وحدة إلى نمط Service (بدأنا بالوحدات).
 */
return new class extends Migration
{
    private const TABLES = [
        'units',
        'type',
        'items',
        'coins',
        'stocks',
        'boxes',
        'banks',
        'customers',
        'suppliers',
        'openingbalances',
        'characcount',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('created_by')->nullable()
                    ->comment('المستخدم الذي أنشأ السجل');
                $t->unsignedBigInteger('updated_by')->nullable()
                    ->comment('المستخدم الذي عدّل السجل آخر مرة');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['created_by', 'updated_by']);
            });
        }
    }
};
