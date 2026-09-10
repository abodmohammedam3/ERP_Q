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
        Schema::create('Customers', function (Blueprint $table) {

            // المفتاح الأساسي: CustomersID (BIGINT AUTO_INCREMENT)
            $table->bigIncrements('CustomersID');

            // المفتاح الأجنبي: accountID (BIGINT UNSIGNED NULL)
            $table->unsignedBigInteger('accountID')->nullable();

            // اسم العميل
            $table->text('CustomersName2')->nullable();

            // رقم الهاتف (VARCHAR 20)
            $table->string('CusPhone', 20)->nullable();

            // العنوان
            $table->text('CusAddress')->nullable();

            // حالة الإيقاف (TINYINT DEFAULT 0)
            $table->tinyInteger('CusIsStopeed')->default(0);

            // =================================================
            // Foreign Key
            // =================================================
            $table->foreign(
                    'accountID',
                    'FK_Customers_ChartOfAccounts'
                )
                ->references('accountID')
                ->on('characcount')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Customers');
    }
};