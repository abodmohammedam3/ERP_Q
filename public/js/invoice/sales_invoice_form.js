/* =========================================================
   نموذج فاتورة البيع
   ========================================================= */

window.clearSalesInvoiceForm = function () {

    /*
     * مسح بيانات رأس الفاتورة
     */

    document
        .querySelectorAll(
            '#SalesInvoiceNo,' +
            '#SalesInvoiceDate,' +
            '#SalesPaymentMethod,' +
            '#salesCashAccount,' +
            '#salesBankAccount,' +
            '#salesWalletAccount,' +
            '#customerName,' +
            '#salesCurrencyName,' +
            '#SalesExchangeRate,' +
            '#SalesStatement,' +
            '#SalesReference'
        )
        .forEach(function (element) {

            element.value = '';

        });


    /*
     * العميل
     */

    const customerID =
        document.getElementById(
            'customerID'
        );

    if (customerID) {

        customerID.value = '';
        

    }


    /*
     * العملة
     */

    const currencyID =
        document.getElementById(
            'salesCoinsID'
        );

    if (currencyID) {

        currencyID.value = '';

    }


    /*
     * تفاصيل الفاتورة
     */

    const details =
        document.getElementById(
            'salesInvoiceDetails'
        );


    if (details) {

        details.innerHTML = `

            <tr>

                <td
                    colspan="11"
                    class="text-center text-muted py-4"
                >
                    لا توجد أصناف مضافة إلى الفاتورة
                </td>

            </tr>

        `;

    }


    /*
     * إجمالي الخصم
     */

    const discount =
        document.getElementById(
            'totalSalesDiscountDisplay'
        );


    if (discount) {

        discount.textContent = '0.00';

    }


    /*
     * إجمالي الفاتورة
     */

    const total =
        document.getElementById(
            'salesInvoiceTotalDisplay'
        );


    if (total) {

        total.textContent = '0.00';

    }


    /*
     * إخفاء حسابات الدفع
     */

    if (
        typeof hideSalesPaymentAccounts ===
        'function'
    ) {

        hideSalesPaymentAccounts();

    }


    /*
     * الوضع الابتدائي
     */

    if (
        typeof setSalesInvoiceMode ===
        'function'
    ) {

        setSalesInvoiceMode('view');

    }

};


/* =========================================================
   إلغاء العملية
   ========================================================= */

window.cancelSalesInvoice = function () {

    if (
        !confirm(
            'هل أنت متأكد من إلغاء العملية؟ سيتم مسح البيانات الحالية.'
        )
    ) {

        return;

    }


    clearSalesInvoiceForm();

};