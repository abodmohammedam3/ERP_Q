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
        Schema::create('type', function (Blueprint $table) {
            $table->id();                 // auto-increment bigint
            $table->string('name');       // اسم النوع
            $table->string('code', 50)->nullable(); // الكود (اختياري، نصي)
            $table->boolean('is_active')->default(1); // حالة التفعيل
            // لا يوجد timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type');
    }
};