<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('characcount', function (Blueprint $table) {
            // المفتاح الأساسي كما هو محدد في الموديل
            $table->integer('accountID')->autoIncrement();
            
            $table->integer('accTypeID');
            $table->integer('accCode')->unique();
            $table->integer('accParent')->nullable();
            $table->string('accName');
            $table->integer('nature');
            $table->integer('accLevel');
            $table->integer('IsActive');
            $table->integer('isPostable');
            
            // بما أن $timestamps = false في الموديل، لا نضيف $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('characcount');
    }
};