<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('Items', function (Blueprint $table) {
            $table->id('itemID');
            $table->text('itemName2');
            $table->boolean('is_active')->default(1);
        });
    }

    public function down()
    {
        Schema::dropIfExists('Items');
    }
};