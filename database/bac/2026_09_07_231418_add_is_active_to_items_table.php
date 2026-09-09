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
        Schema::create('Items', function (Blueprint $table) {
            $table->id('itemID');       // bigint(20) auto increment
            $table->text('itemName2');  // text
            $table->boolean('is_active')->default(1);
            // لا يوجد timestamps حسب متطلباتك
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Items');
    }
};