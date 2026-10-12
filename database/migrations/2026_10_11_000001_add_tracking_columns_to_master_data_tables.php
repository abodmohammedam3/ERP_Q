<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقلي التتبع created_by / updated_by لكل جداول الإعدادات (Master Data).
 *
 * @deprecated أصبحت no-op محمية — فشلت أصلاً بسبب أسماء جداول خاطئة
 * (openingBalances وcharaccount) واستُكملت بـ 2026_10_11_000002.
 * أُعيدت كتابتها بحماية hasTable/hasColumn كي تُسجَّل بلا خطأ عند migrate.
 * أسماء الجداول الآن مطابقة لـ database/bac (Customers/Suppliers/openingBalances).
 *
 * مرة واحدة وفق قرار المستخدم (بند 3 من قرارات الوحدة 1).
 * القيم قابلة للـ NULL لأن النظام لا يملك مصادقة بعد (H3/H4) —
 * وتُعبأ تدريجياً مع ترقية كل وحدة إلى نمط Service (بدأنا بالوحدات).
 */
return new class extends Migration
{
    /** الجداول (الأسماء مطابقة لـ database/bac) */
    private const TABLES = [
        'units',
        'type',
        'items',
        'coins',
        'stocks',
        'boxes',
        'banks',
        'Customers',
        'Suppliers',
        'openingBalances',
        'characcount',
    ];

    /** الأعمدة المطلوبة */
    private const COLUMNS = ['created_by', 'updated_by'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                Log::error("Migration 000001: الجدول غير موجود: {$table}");

                continue;
            }

            try {
                $missing = array_filter(
                    self::COLUMNS,
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

                Log::info("Migration 000001: أُضيفت الأعمدة إلى {$table}");
            } catch (\Throwable $e) {
                Log::error("Migration 000001 فشل على {$table}", [
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
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_filter(
                self::COLUMNS,
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
