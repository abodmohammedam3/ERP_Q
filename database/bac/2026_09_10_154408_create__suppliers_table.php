<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('Suppliers', function (Blueprint $table) {

            $table->unsignedBigInteger('suplierID')
                ->autoIncrement();

            $table->unsignedBigInteger('accountID')
                ->nullable();

            $table->text('supName');

            $table->unsignedBigInteger('supPhone')
                ->nullable();

            $table->text('supArea')
                ->nullable();

            $table->tinyInteger('supStoped')
                ->default(0);

            $table->primary('suplierID');

            // الربط مع دليل الحسابات
            $table->foreign('accountID')
                ->references('accountID')
                ->on('characcount')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('Suppliers');
    }
};