<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل تتبع توزيعات المشتريات (Audit Log)
     * -----------------------------------------------------
     * يسجّل كل فعالية على التوزيع:
     *  - created  : إنشاء التوزيع (مع عدد الصفوف)
     *  - updated  : تعديل مسودة
     *  - issued   : إصدار فاتورة بيع (مع رقمها)
     *  - deleted  : حذف مسودة (مع لقطة الصفوف)
     */
    public function up(): void
    {
        Schema::create('distribution_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('distribution_id')
                  ->comment('التوزيع المرتبط');

            $table->string('action', 20)
                  ->comment('created | updated | issued | deleted');

            $table->string('description', 255)->nullable()
                  ->comment('وصف عربي للفعالية');

            $table->unsignedBigInteger('user_id')->nullable()
                  ->comment('المستخدم المنفّذ');

            $table->json('meta')->nullable()
                  ->comment('بيانات إضافية (رقم الفاتورة، لقطة الصفوف...)');

            $table->timestamps();

            $table->index('distribution_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_logs');
    }
};
