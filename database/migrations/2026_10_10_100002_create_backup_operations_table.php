<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_operations', function (Blueprint $table) {

            $table->id();

            $table->uuid('operation_id')->unique()
                  ->comment('يُستخدم في Polling');

            $table->string('type', 20)
                  ->comment('export | import');

            $table->string('format', 10)->default('sql');

            $table->string('status', 20)->default('pending')
                  ->comment('pending | running | done | failed');

            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('stage', 100)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('error_message')->nullable();

            $table->foreignId('user_id')->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_bo_status_created');
            $table->index('user_id', 'idx_bo_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_operations');
    }
};
