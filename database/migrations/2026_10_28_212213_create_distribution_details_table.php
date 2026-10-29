<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('distribution_details', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('distribution_id');
            $table->unsignedBigInteger('purchase_invoice_id');
            $table->unsignedBigInteger('movement_id');
            $table->unsignedBigInteger('movement_detail_id');
            $table->unsignedBigInteger('customer_id');

            $table->decimal('quantity', 15, 6)->default(0);
            $table->decimal('unit_cost', 15, 6)->default(0);
            $table->decimal('sale_price', 15, 6)->default(0);

            $table->unsignedBigInteger('sales_invoice_id')->nullable();
            $table->timestamp('invoiced_at')->nullable();

            $table->timestamps();

            $table->foreign('distribution_id')
                  ->references('id')->on('distributions')
                  ->onDelete('cascade');

            $table->index('purchase_invoice_id');
            $table->index('movement_id');
            $table->index('movement_detail_id');
            $table->index('customer_id');
            $table->index('sales_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_details');
    }
};