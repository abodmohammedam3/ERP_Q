<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('type', function (Blueprint $table) {
            $table->boolean('is_active')->default(1)->after('name');
        });
    }

    public function down()
    {
        Schema::table('type', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};