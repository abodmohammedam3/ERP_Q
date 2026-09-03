/* =========================================================
   تهيئة شاشة فاتورة البيع
   ========================================================= */

window.initSalesInvoice = function () {

    const state = window.SalesInvoiceState;
    

    /*
     * تهيئة النوافذ المنبثقة
     */

    const customerModalElement =
        document.getElementById('customerModal');

    if (customerModalElement) {

        state.modals.customer =
            new bootstrap.Modal(customerModalElement);

    }


    const currencyModalElement =
        document.getElementById('salesCurrencyModal');

    if (currencyModalElement) {

        state.modals.currency =
            new bootstrap.Modal(currencyModalElement);

    }


    const itemModalElement =
        document.getElementById('salesItemModal');

    if (itemModalElement) {

        state.modals.item =
            new bootstrap.Modal(itemModalElement);

    }


    const typeModalElement =
        document.getElementById('salesTypeModal');

    if (typeModalElement) {

        state.modals.type =
            new bootstrap.Modal(typeModalElement);

    }


    const warehouseModalElement =
        document.getElementById('salesWarehouseModal');

    if (warehouseModalElement) {

        state.modals.warehouse =
            new bootstrap.Modal(warehouseModalElement);

    }


    const unitModalElement =
        document.getElementById('salesUnitModal');

    if (unitModalElement) {

        state.modals.unit =
            new bootstrap.Modal(unitModalElement);

    }


    const searchModalElement =
        document.getElementById('salesInvoiceSearchModal');

    if (searchModalElement) {

        state.modals.search =
            new bootstrap.Modal(searchModalElement);

    }


    /*
     * الحالة الابتدائية
     */

    setSalesInvoiceMode('view');

    clearSalesInvoiceForm();

};


/* =========================================================
   تشغيل التهيئة
   ========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        initSalesInvoice();

    }
);