<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('characcount')) {
            return;
        }

        Schema::create('characcount', function (Blueprint $table) {
            $table->bigIncrements('accountID');
            $table->string('accCode', 50)->unique();
            $table->string('accName', 255);
            $table->unsignedBigInteger('accParent')->nullable();
            $table->unsignedInteger('accLevel')->default(1);
            $table->unsignedBigInteger('accTypeID')->nullable();
            $table->tinyInteger('nature')->default(1);
            $table->tinyInteger('isPostable')->default(0);
            $table->tinyInteger('IsActive')->default(1);
            $table->tinyInteger('is_system')->default(0);
            $table->string('system_key', 50)->nullable()->unique();

            $table->foreign('accParent')
                ->references('accountID')
                ->on('characcount')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('accParent');
            $table->index('accLevel');
            $table->index('accTypeID');
            $table->index('nature');
            $table->index('IsActive');
            $table->index('isPostable');
            $table->index('is_system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characcount');
    }
};