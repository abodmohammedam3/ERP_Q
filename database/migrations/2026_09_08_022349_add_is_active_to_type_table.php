<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('type') || Schema::hasColumn('type', 'is_active')) {
            return;
        }

        Schema::table('type', function (Blueprint $table) {
            $table->boolean('is_active')->default(1)->after('name');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('type') && Schema::hasColumn('type', 'is_active')) {
            Schema::table('type', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};