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
        Schema::create('coins', function (Blueprint $table) {
            $table->decimal('coinsID', 20, 0)->primary();
            $table->text('coinsCode');
            $table->decimal('coinsExchangeRate', 18, 6)->nullable();
            $table->tinyInteger('coinsSystem')->nullable();

            // فهارس للبحث السريع
            $table->index('coinsCode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coins');
    }
};