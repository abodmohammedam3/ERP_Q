<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ============================================================
        // فهارس جدول الأرصدة الافتتاحية
        // ============================================================
        Schema::table('openingBalances', function (Blueprint $table) {
            // فهرس على accountID — يُستخدم في كل استعلام لقائمة الأرصدة
            if (!$this->indexExists('openingBalances', 'idx_opening_balances_account')) {
                $table->index('accountID', 'idx_opening_balances_account');
            }
        });

        // ============================================================
        // فهارس جدول دليل الحسابات
        // ============================================================
        Schema::table('characcount', function (Blueprint $table) {
            // فهرس على accParent — للبحث عن الأبناء في الشجرة
            if (!$this->indexExists('characcount', 'idx_characcount_parent')) {
                $table->index('accParent', 'idx_characcount_parent');
            }

            // فهرس على system_key — للبحث عن الحسابات الأب
            if (!$this->indexExists('characcount', 'idx_characcount_system_key')) {
                $table->index('system_key', 'idx_characcount_system_key');
            }

            // فهرس على IsActive — للفلترة على الحسابات النشطة
            if (!$this->indexExists('characcount', 'idx_characcount_active')) {
                $table->index('IsActive', 'idx_characcount_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('openingBalances', function (Blueprint $table) {
            if ($this->indexExists('openingBalances', 'idx_opening_balances_account')) {
                $table->dropIndex('idx_opening_balances_account');
            }
        });

        Schema::table('characcount', function (Blueprint $table) {
            if ($this->indexExists('characcount', 'idx_characcount_parent')) {
                $table->dropIndex('idx_characcount_parent');
            }

            if ($this->indexExists('characcount', 'idx_characcount_system_key')) {
                $table->dropIndex('idx_characcount_system_key');
            }

            if ($this->indexExists('characcount', 'idx_characcount_active')) {
                $table->dropIndex('idx_characcount_active');
            }
        });
    }

    /**
     * التحقق من وجود الفهرس مسبقًا
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database   = $connection->getDatabaseName();

        $result = $connection->selectOne(
            "SELECT COUNT(*) as count
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?",
            [$database, $table, $indexName]
        );

        return ($result->count ?? 0) > 0;
    }
};