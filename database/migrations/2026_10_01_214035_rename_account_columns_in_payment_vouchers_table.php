<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * إزالة العلاقات والفهارس القديمة
         */
        Schema::table('payment_vouchers', function (Blueprint $table) {

            $table->dropForeign([
                'creditAccountID',
            ]);

            $table->dropForeign([
                'debitAccountID',
            ]);

            $table->dropIndex([
                'creditAccountID',
            ]);

            $table->dropIndex([
                'debitAccountID',
            ]);
        });

        /*
         * إعادة تسمية الأعمدة
         */
        Schema::table('payment_vouchers', function (Blueprint $table) {

            $table->renameColumn(
                'creditAccountID',
                'beneficiaryAccountID'
            );

            $table->renameColumn(
                'debitAccountID',
                'paymentAccountID'
            );
        });

        /*
         * إعادة إنشاء العلاقات والفهارس
         */
        Schema::table('payment_vouchers', function (Blueprint $table) {

            $table->foreign(
                'beneficiaryAccountID',
                'payment_vouchers_beneficiary_account_foreign'
            )
            ->references('accountID')
            ->on('characcount')
            ->cascadeOnDelete();

            $table->foreign(
                'paymentAccountID',
                'payment_vouchers_payment_account_foreign'
            )
            ->references('accountID')
            ->on('characcount')
            ->cascadeOnDelete();

            $table->index(
                'beneficiaryAccountID',
                'payment_vouchers_beneficiary_account_index'
            );

            $table->index(
                'paymentAccountID',
                'payment_vouchers_payment_account_index'
            );
        });
    }

    public function down(): void
    {
        /*
         * إزالة العلاقات والفهارس الجديدة
         */
        Schema::table('payment_vouchers', function (Blueprint $table) {

            $table->dropForeign(
                'payment_vouchers_beneficiary_account_foreign'
            );

            $table->dropForeign(
                'payment_vouchers_payment_account_foreign'
            );

            $table->dropIndex(
                'payment_vouchers_beneficiary_account_index'
            );

            $table->dropIndex(
                'payment_vouchers_payment_account_index'
            );
        });

        /*
         * إعادة أسماء الأعمدة القديمة
         */
        Schema::table('payment_vouchers', function (Blueprint $table) {

            $table->renameColumn(
                'beneficiaryAccountID',
                'creditAccountID'
            );

            $table->renameColumn(
                'paymentAccountID',
                'debitAccountID'
            );
        });

        /*
         * إعادة العلاقات والفهارس القديمة
         */
        Schema::table('payment_vouchers', function (Blueprint $table) {

            $table->foreign(
                'creditAccountID'
            )
            ->references('accountID')
            ->on('characcount')
            ->cascadeOnDelete();

            $table->foreign(
                'debitAccountID'
            )
            ->references('accountID')
            ->on('characcount')
            ->cascadeOnDelete();

            $table->index(
                'creditAccountID'
            );

            $table->index(
                'debitAccountID'
            );
        });
    }
};