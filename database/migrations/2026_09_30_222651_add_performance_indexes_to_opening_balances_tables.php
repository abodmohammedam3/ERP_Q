<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ openingBalances
        Schema::table('openingBalances', function (Blueprint $table) {
            $table->index(['accountID', 'openingBalancesID'], 'idx_ob_account_order');
        });

        // ✅ characcount
        Schema::table('characcount', function (Blueprint $table) {
            $table->index(['accParent', 'IsActive'], 'idx_char_parent_active');
        });

        // ✅ الجداول المرتبطة — فهرس على accountID
        foreach (['boxes', 'banks', 'customers', 'Suppliers', 'stocks'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->index('accountID');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('openingBalances', function (Blueprint $table) {
            $table->dropIndex('idx_ob_account_order');
        });

        Schema::table('characcount', function (Blueprint $table) {
            $table->dropIndex('idx_char_parent_active');
        });

        foreach (['boxes', 'banks', 'customers', 'Suppliers', 'stocks'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['accountID']);
                });
            }
        }
    }
};