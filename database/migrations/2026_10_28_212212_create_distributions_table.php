<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('distribution_date');
            $table->enum('status', ['draft', 'invoiced'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->unsignedBigInteger('invoiced_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('distribution_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributions');
    }
};