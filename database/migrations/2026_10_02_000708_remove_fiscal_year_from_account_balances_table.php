<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * الـ Foreign Key يعتمد على index موجود على accountID.
         * لذلك لا نحاول حذف الـ unique index مباشرة.
         *
         * أولًا نحذف الـ Foreign Keys التي تعتمد عليه،
         * ثم نعيد إنشاءها بعد تعديل الفهارس.
         */

        Schema::table('account_balances', function (Blueprint $table) {
            $table->dropForeign([
                'accountID',
            ]);

            $table->dropForeign([
                'coinsID',
            ]);
        });

        /*
         * الآن أصبح بإمكان MySQL حذف الفهارس.
         */

        Schema::table('account_balances', function (Blueprint $table) {
            $table->dropUnique(
                'uq_account_year_currency'
            );

            $table->dropIndex(
                'idx_year_currency'
            );

            $table->dropColumn(
                'fiscalYear'
            );
        });

        /*
         * إنشاء الـ Unique الجديد:
         *
         * حساب + عملة
         *
         * لأن الرصيد أصبح تراكميًا
         * ولا يوجد إقفال سنوي.
         */

        Schema::table('account_balances', function (Blueprint $table) {
            $table->unique(
                ['accountID', 'coinsID'],
                'uq_account_currency'
            );

            $table->index(
                ['coinsID', 'accountID'],
                'idx_currency_account'
            );

            $table->foreign('accountID')
                ->references('accountID')
                ->on('characcount')
                ->onDelete('cascade');

            $table->foreign('coinsID')
                ->references('coinsID')
                ->on('coins')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('account_balances', function (Blueprint $table) {
            $table->dropForeign([
                'accountID',
            ]);

            $table->dropForeign([
                'coinsID',
            ]);

            $table->dropUnique(
                'uq_account_currency'
            );

            $table->dropIndex(
                'idx_currency_account'
            );

            $table->unsignedSmallInteger('fiscalYear')
                ->default(0)
                ->after('accountID');

            $table->unique(
                ['accountID', 'fiscalYear', 'coinsID'],
                'uq_account_year_currency'
            );

            $table->index(
                ['fiscalYear', 'coinsID'],
                'idx_year_currency'
            );

            $table->foreign('accountID')
                ->references('accountID')
                ->on('characcount')
                ->onDelete('cascade');

            $table->foreign('coinsID')
                ->references('coinsID')
                ->on('coins')
                ->onDelete('restrict');
        });
    }
};