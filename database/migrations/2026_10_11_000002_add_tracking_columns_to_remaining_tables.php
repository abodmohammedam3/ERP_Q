<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقلي التتبع created_by / updated_by للجداول المتبقية
 * بعد فشل 2026_10_11_000001 (deprecated) عند:
 *   - openingBalances  (الاسم الصحيح للجدول)
 *   - characcount      (بلا s)
 *
 * حماية إلزامية: hasTable / hasColumn خارج الـ closure + try/catch لكل جدول.
 */
return new class extends Migration
{
    /** الجدول => الأعمدة المطلوبة */
    private const TABLES = [
        'openingBalances' => ['created_by', 'updated_by'],
        'characcount'     => ['created_by', 'updated_by'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $columns) {
            // ── حماية: الجدول موجود؟ ──
            if (! Schema::hasTable($table)) {
                Log::error("Migration 000002: الجدول غير موجود: {$table}");

                continue;
            }

            try {
                // ── حماية خارج الـ closure: الأعمدة موجودة أصلاً؟ ──
                $missing = array_filter(
                    $columns,
                    fn (string $col) => ! Schema::hasColumn($table, $col)
                );

                if (empty($missing)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($missing) {
                    foreach ($missing as $col) {
                        $t->unsignedBigInteger($col)->nullable()
                            ->comment($col === 'created_by'
                                ? 'المستخدم الذي أنشأ السجل'
                                : 'المستخدم الذي عدّل السجل آخر مرة');
                    }
                });

                Log::info("Migration 000002: أُضيفت الأعمدة إلى {$table}");
            } catch (\Throwable $e) {
                Log::error("Migration 000002 فشل على {$table}", [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ]);

                throw $e;
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $columns) {
            // ── حماية down: الجدول والأعمدة موجودان قبل الحذف ──
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_filter(
                $columns,
                fn (string $col) => Schema::hasColumn($table, $col)
            );

            if (empty($existing)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($existing) {
                $t->dropColumn($existing);
            });
        }
    }
};
