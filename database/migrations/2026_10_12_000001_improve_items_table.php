<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حزمة تحسينات جدول الأصناف (الوحدة 3):
     *  1) itemName2: TEXT ← VARCHAR(255) ليدعم فهرسة البحث Prefix.
     *  2) فهرس UNIQUE على itemName2 — قرار المستخدم: الإبقاء على unique
     *     (نقض قرار Q2 السابق «التكرار مسموح»).
     *  3) حقول التتبع created_by/updated_by بحارس hasColumn —
     *     يعالج إضافة I3-5 (هجرة 000001 تسمّي 'items' صغيرة وتُتجاهَل على Linux).
     */
    public function up(): void
    {
        if (! Schema::hasTable('Items')) {
            return;
        }

        // 1) نوع العمود (قبل الفهرسة — لا فهرس على TEXT في MySQL)
        Schema::table('Items', function (Blueprint $table) {
            $table->string('itemName2', 255)->change();
        });

        // 2) فهرس UNIQUE (حارس ضد التكرار عند إعادة التشغيل اليدوي)
        $hasIndex = collect(Schema::getIndexes('Items'))->contains(
            fn (array $index) => $index['name'] === 'uniq_items_itemname2'
                || in_array('itemName2', $index['columns'], true)
        );

        if (! $hasIndex) {
            Schema::table('Items', function (Blueprint $table) {
                $table->unique('itemName2', 'uniq_items_itemname2');
            });
        }

        // 3) حقول التتبع إن كانت غائبة
        $missing = array_values(array_filter(
            ['created_by', 'updated_by'],
            fn (string $column) => ! Schema::hasColumn('Items', $column)
        ));

        if ($missing !== []) {
            Schema::table('Items', function (Blueprint $table) use ($missing) {
                foreach ($missing as $column) {
                    $table->unsignedBigInteger($column)->nullable()
                        ->comment($column === 'created_by'
                            ? 'المستخدم الذي أنشأ السجل'
                            : 'المستخدم الذي حدّث السجل آخر مرة');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('Items')) {
            return;
        }

        $hasIndex = collect(Schema::getIndexes('Items'))->contains(
            fn (array $index) => $index['name'] === 'uniq_items_itemname2'
        );

        if ($hasIndex) {
            Schema::table('Items', function (Blueprint $table) {
                $table->dropIndex('uniq_items_itemname2');
            });
        }

        $tracked = array_values(array_filter(
            ['created_by', 'updated_by'],
            fn (string $column) => Schema::hasColumn('Items', $column)
        ));

        if ($tracked !== []) {
            Schema::table('Items', function (Blueprint $table) use ($tracked) {
                $table->dropColumn($tracked);
            });
        }

        Schema::table('Items', function (Blueprint $table) {
            $table->text('itemName2')->change();
        });
    }
};
