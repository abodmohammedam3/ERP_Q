/* =========================================================
   طرق الدفع - فاتورة البيع
   ========================================================= */


/* =========================================================
   تغيير طريقة الدفع
   ========================================================= */

window.salesPaymentMethodChanged = function () {

    hideSalesPaymentAccounts();


    const method =
        document.getElementById(
            'SalesPaymentMethod'
        )?.value;


    if (method === 'cash') {

        const container =
            document.getElementById(
                'salesCashAccountContainer'
            );

        if (container) {

            container.classList.remove(
                'd-none'
            );

        }

    }


    else if (method === 'bank') {

        const container =
            document.getElementById(
                'salesBankAccountContainer'
            );

        if (container) {

            container.classList.remove(
                'd-none'
            );

        }

    }


    else if (method === 'network') {

        const container =
            document.getElementById(
                'salesWalletAccountContainer'
            );

        if (container) {

            container.classList.remove(
                'd-none'
            );

        }

    }

};


/* =========================================================
   إخفاء حسابات الدفع
   ========================================================= */

window.hideSalesPaymentAccounts = function () {

    const cash =
        document.getElementById(
            'salesCashAccountContainer'
        );

    const bank =
        document.getElementById(
            'salesBankAccountContainer'
        );

    const wallet =
        document.getElementById(
            'salesWalletAccountContainer'
        );


    if (cash) {

        cash.classList.add(
            'd-none'
        );

    }


    if (bank) {

        bank.classList.add(
            'd-none'
        );

    }


    if (wallet) {

        wallet.classList.add(
            'd-none'
        );

    }

};