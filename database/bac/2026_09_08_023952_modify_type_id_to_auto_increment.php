<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('type', function (Blueprint $table) {
            $table->id('id')->change();  // يحول العمود إلى auto-increment
        });
    }

    public function down()
    {
        Schema::table('type', function (Blueprint $table) {
            $table->integer('id')->change();  // يعيده إلى int عادي
        });
    }
};