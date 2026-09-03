/* =========================================================
   الوحدات - فاتورة البيع
   ========================================================= */


/* =========================================================
   فتح نافذة الوحدة
   ========================================================= */

window.openSalesUnitModal = function (input) {

    if (
        SalesInvoiceState.mode ===
        'view'
    ) {

        return;

    }


    SalesInvoiceState.activeRow =
        input.closest('tr');


    SalesInvoiceState.modals.unit.show();

};


/* =========================================================
   اختيار الوحدة
   ========================================================= */

window.selectSalesUnit = function (unit) {

    const row =
        SalesInvoiceState.activeRow;


    if (!row) {

        return;

    }


    const unitInput =
        row.querySelector(
            '.row-unit'
        );


    const measureInput =
        row.querySelector(
            '.row-measure'
        );


    if (unitInput) {

        unitInput.value =
            unit;

    }


    if (measureInput) {

        measureInput.value = '';

    }


    updateSalesMeasureHeader(row);


    SalesInvoiceState.modals.unit.hide();


    if (unitInput) {

        calculateSalesRow(
            unitInput
        );

    }

};


/* =========================================================
   تحديث عنوان العدد / الوزن
   ========================================================= */

window.updateSalesMeasureHeader = function (row) {

    const unit =
        row.querySelector(
            '.row-unit'
        )?.value || '';


    const header =
        document.getElementById(
            'salesQuantityWeightHeader'
        );


    if (!header) {

        return;

    }


    if (unit === 'كيلو') {

        header.textContent =
            'الوزن';

    }


    else if (unit === 'حبه') {

        header.textContent =
            'العدد';

    }


    else {

        header.textContent =
            'العدد / الوزن';

    }

};