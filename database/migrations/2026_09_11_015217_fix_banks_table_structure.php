<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('banks')) {
            Schema::table('banks', function (Blueprint $table) {
                try { $table->dropForeign(['coinsID']); }   catch (\Throwable $e) {}
                try { $table->dropForeign(['accountID']); } catch (\Throwable $e) {}
            });
        }

        Schema::dropIfExists('banks');

        Schema::create('banks', function (Blueprint $table) {
            $table->bigIncrements('bankID');
            $table->string('bankName', 255);
            $table->unsignedBigInteger('accountID');
            $table->unsignedBigInteger('coinsID')->nullable();
            $table->string('accountNumber', 50)->nullable();  // رقم حساب البنك
            $table->tinyInteger('is_active')->default(1);

            $table->foreign('accountID')
                  ->references('accountID')->on('characcount')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            $table->foreign('coinsID')
                  ->references('coinsID')->on('coins')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            $table->index('accountID');
            $table->index('coinsID');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};