<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('type', function (Blueprint $table) {
            $table->id();                           // auto-increment bigint
            $table->string('name');                 // اسم النوع
            $table->string('code', 50)->nullable(); // الكود (نصي، اختياري، طول 50)
            $table->boolean('is_active')->default(1);
            // لا نضيف timestamps
        });
    }

    public function down()
    {
        Schema::dropIfExists('type');
    }
};