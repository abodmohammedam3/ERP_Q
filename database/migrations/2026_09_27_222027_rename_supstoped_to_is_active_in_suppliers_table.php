<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إضافة عمود is_active
        Schema::table('Suppliers', function (Blueprint $table) {
            $table->boolean('is_active')->default(1)->after('supArea');
        });

        // 2. عكس القيم: supStoped=0 (نشط) → is_active=1
        //             supStoped=1 (موقوف) → is_active=0
        DB::table('Suppliers')->update([
            'is_active' => DB::raw('CASE WHEN supStoped = 0 THEN 1 ELSE 0 END'),
        ]);

        // 3. حذف العمود القديم
        Schema::table('Suppliers', function (Blueprint $table) {
            $table->dropColumn('supStoped');
        });
    }

    public function down(): void
    {
        Schema::table('Suppliers', function (Blueprint $table) {
            $table->tinyInteger('supStoped')->default(0)->after('supArea');
        });

        DB::table('Suppliers')->update([
            'supStoped' => DB::raw('CASE WHEN is_active = 1 THEN 0 ELSE 1 END'),
        ]);

        Schema::table('Suppliers', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};