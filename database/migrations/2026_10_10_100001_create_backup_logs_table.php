<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table) {

            $table->id();

            $table->string('operation', 20)
                  ->comment('export | import | cleanup | auto');

            $table->string('format', 10)->default('sql');

            $table->string('status', 20)
                  ->comment('success | failed | warning');

            $table->string('filename', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('message')->nullable();

            $table->foreignId('user_id')->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->timestamps();

            $table->index(['operation', 'created_at'], 'idx_bl_op_created');
            $table->index('user_id', 'idx_bl_user');
            $table->index('status', 'idx_bl_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
