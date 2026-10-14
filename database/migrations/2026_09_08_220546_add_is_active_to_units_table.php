<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id('UnitID');           // primary key
            $table->string('UnitName');     // اسم الوحدة
            $table->boolean('is_active')->default(1);
            // لا نضيف timestamps
        });
    }

    public function down()
    {
        Schema::dropIfExists('units');
    }
};