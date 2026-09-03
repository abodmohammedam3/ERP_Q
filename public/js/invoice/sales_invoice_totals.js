/* =========================================================
   حسابات فاتورة البيع
   ========================================================= */


/* =========================================================
   حساب إجمالي الصف
   ========================================================= */

window.calculateSalesRow = function (input) {

    const row =
        input.closest('tr');


    if (!row) {

        return;

    }


    const quantity =
        parseFloat(
            row.querySelector(
                '.row-measure'
            )?.value
        ) || 0;


    const price =
        parseFloat(
            row.querySelector(
                '.row-price'
            )?.value
        ) || 0;


    const discount =
        parseFloat(
            row.querySelector(
                '.row-discount'
            )?.value
        ) || 0;


    const subtotal =
        quantity * price;


    const total =
        Math.max(
            0,
            subtotal - discount
        );


    const totalInput =
        row.querySelector(
            '.row-total'
        );


    if (totalInput) {

        totalInput.value =
            total.toFixed(2);

    }


    calculateSalesTotals();

};


/* =========================================================
   حساب إجماليات الفاتورة
   ========================================================= */

window.calculateSalesTotals = function () {

    let invoiceTotal = 0;

    let totalDiscount = 0;


    document
        .querySelectorAll(
            '#salesInvoiceDetails .sales-detail-row'
        )
        .forEach(function (row) {

            const total =
                parseFloat(
                    row.querySelector(
                        '.row-total'
                    )?.value
                ) || 0;


            const discount =
                parseFloat(
                    row.querySelector(
                        '.row-discount'
                    )?.value
                ) || 0;


            invoiceTotal += total;

            totalDiscount += discount;

        });


    const discountDisplay =
        document.getElementById(
            'totalSalesDiscountDisplay'
        );


    const totalDisplay =
        document.getElementById(
            'salesInvoiceTotalDisplay'
        );


    if (discountDisplay) {

        discountDisplay.textContent =
            totalDiscount.toFixed(2);

    }


    if (totalDisplay) {

        totalDisplay.textContent =
            invoiceTotal.toFixed(2);

    }

};