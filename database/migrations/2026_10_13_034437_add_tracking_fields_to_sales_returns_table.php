<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقول التتبع والترحيل إلى جدول sales_returns
 * -----------------------------------------------------
 * - warehouse_id   : المخزن الرئيسي للمرتجع
 * - entryID        : رقم القيد المحاسبي
 * - is_posted      : حالة الترحيل
 * - posted_at      : تاريخ الترحيل
 * - posted_by      : المستخدم الذي رحّل
 * - created_by     : المستخدم الذي أنشأ
 * - updated_by     : المستخدم الذي عدّل
 *
 * + فهارس للأداء
 */
return new class extends Migration
{
    public function up(): void
    {
        // حذف الفهارس القديمة إن وُجدت
        $this->dropIndexIfExists('sales_returns', 'idx_sr_warehouse_id');
        $this->dropIndexIfExists('sales_returns', 'idx_sr_is_posted');
        $this->dropIndexIfExists('sales_returns', 'idx_sr_created_by');
        $this->dropIndexIfExists('sales_returns', 'idx_sr_entry_id');
        $this->dropIndexIfExists('sales_returns', 'idx_sr_posted_date');

        // حذف الأعمدة القديمة إن وُجدت
        Schema::table('sales_returns', function (Blueprint $table) {
            $this->dropColumnIfExists($table, 'sales_returns', 'warehouse_id');
            $this->dropColumnIfExists($table, 'sales_returns', 'entryID');
            $this->dropColumnIfExists($table, 'sales_returns', 'is_posted');
            $this->dropColumnIfExists($table, 'sales_returns', 'posted_at');
            $this->dropColumnIfExists($table, 'sales_returns', 'posted_by');
            $this->dropColumnIfExists($table, 'sales_returns', 'created_by');
            $this->dropColumnIfExists($table, 'sales_returns', 'updated_by');
        });

        // إضافة الأعمدة الجديدة
        Schema::table('sales_returns', function (Blueprint $table) {

            $table->unsignedBigInteger('warehouse_id')
                  ->nullable()
                  ->after('coin_id')
                  ->comment('المخزن الرئيسي للمرتجع');

            $table->unsignedBigInteger('entryID')
                  ->nullable()
                  ->after('reference')
                  ->comment('رقم القيد المحاسبي المرتبط');

            $table->boolean('is_posted')
                  ->default(false)
                  ->after('entryID')
                  ->comment('هل تم ترحيل المرتجع؟');

            $table->timestamp('posted_at')
                  ->nullable()
                  ->after('is_posted')
                  ->comment('تاريخ الترحيل');

            $table->unsignedBigInteger('posted_by')
                  ->nullable()
                  ->after('posted_at')
                  ->comment('المستخدم الذي رحّل المرتجع');

            $table->unsignedBigInteger('created_by')
                  ->nullable()
                  ->after('sales_return_id')
                  ->comment('المستخدم الذي أنشأ المرتجع');

            $table->unsignedBigInteger('updated_by')
                  ->nullable()
                  ->after('created_by')
                  ->comment('آخر مستخدم عدّل المرتجع');
        });

        // الفهارس
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->index('warehouse_id', 'idx_sr_warehouse_id');
            $table->index('entryID',      'idx_sr_entry_id');
            $table->index('is_posted',    'idx_sr_is_posted');
            $table->index('created_by',   'idx_sr_created_by');
            $table->index(['is_posted', 'return_date'], 'idx_sr_posted_date');
        });
    }

    public function down(): void
    {
        // حذف الفهارس
        $this->dropIndexIfExists('sales_returns', 'idx_sr_warehouse_id');
        $this->dropIndexIfExists('sales_returns', 'idx_sr_entry_id');
        $this->dropIndexIfExists('sales_returns', 'idx_sr_is_posted');
        $this->dropIndexIfExists('sales_returns', 'idx_sr_created_by');
        $this->dropIndexIfExists('sales_returns', 'idx_sr_posted_date');

        // حذف الأعمدة
        Schema::table('sales_returns', function (Blueprint $table) {
            $this->dropColumnIfExists($table, 'sales_returns', 'updated_by');
            $this->dropColumnIfExists($table, 'sales_returns', 'created_by');
            $this->dropColumnIfExists($table, 'sales_returns', 'posted_by');
            $this->dropColumnIfExists($table, 'sales_returns', 'posted_at');
            $this->dropColumnIfExists($table, 'sales_returns', 'is_posted');
            $this->dropColumnIfExists($table, 'sales_returns', 'entryID');
            $this->dropColumnIfExists($table, 'sales_returns', 'warehouse_id');
        });
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function dropColumnIfExists(Blueprint $table, string $tableName, string $column): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            $table->dropColumn($column);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $exists = collect(Schema::getIndexes($table))
            ->contains(fn ($idx) => $idx['name'] === $indexName);

        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        }
    }
};