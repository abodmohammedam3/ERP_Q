/* =========================================================
   حسابات فاتورة البيع
   ========================================================= */


/* =========================================================
   حساب إجمالي الصف
   ========================================================= */

window.calculateSalesRow = function (input) {

    const row = input.closest('tr');

    if (!row) return;

    const quantity =
        parseFloat(row.querySelector('.row-measure')?.value) || 0;

    const price =
        parseFloat(row.querySelector('.row-price')?.value) || 0;

    const discount =
        parseFloat(row.querySelector('.row-discount')?.value) || 0;

    const subtotal = quantity * price;
    const total = Math.max(0, subtotal - discount);

    const totalInput = row.querySelector('.row-total');

    if (totalInput) {
        totalInput.value = total.toFixed(2);
    }

    calculateSalesTotals();

};


/* =========================================================
   حساب إجماليات الفاتورة مع سعر الصرف
   ========================================================= */

window.calculateSalesTotals = function () {

    let itemsTotal = 0;
    let totalDiscount = 0;

    document
        .querySelectorAll('#salesInvoiceDetails .sales-detail-row')
        .forEach(function (row) {

            const total =
                parseFloat(row.querySelector('.row-total')?.value) || 0;

            const discount =
                parseFloat(row.querySelector('.row-discount')?.value) || 0;

            itemsTotal += total;
            totalDiscount += discount;

        });

    // تطبيق سعر الصرف
    const exchangeRate =
        parseFloat(document.getElementById('SalesExchangeRate')?.value) || 1;

    const adjustedTotal = itemsTotal * exchangeRate;

    const discountDisplay =
        document.getElementById('totalSalesDiscountDisplay');

    const totalDisplay =
        document.getElementById('salesInvoiceTotalDisplay');

    if (discountDisplay) {
        discountDisplay.textContent = totalDiscount.toFixed(2);
    }

    if (totalDisplay) {
        totalDisplay.textContent = adjustedTotal.toFixed(2);
    }

};


/* =========================================================
   تغيير سعر الصرف
   ========================================================= */

window.salesExchangeRateChanged = function () {

    calculateSalesTotals();

};