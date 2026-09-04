/* =========================================================
   تهيئة شاشة فاتورة البيع
   ========================================================= */

window.initSalesInvoice = function () {
    var state = window.SalesInvoiceState;

    // تهيئة النوافذ المنبثقة
    var customerModalElement = document.getElementById('customerModal');
    if (customerModalElement) {
        state.modals.customer = new bootstrap.Modal(customerModalElement);
    }

    var currencyModalElement = document.getElementById('salesCurrencyModal');
    if (currencyModalElement) {
        state.modals.currency = new bootstrap.Modal(currencyModalElement);
    }

    var itemModalElement = document.getElementById('salesItemModal');
    if (itemModalElement) {
        state.modals.item = new bootstrap.Modal(itemModalElement);
    }

    var typeModalElement = document.getElementById('salesTypeModal');
    if (typeModalElement) {
        state.modals.type = new bootstrap.Modal(typeModalElement);
    }

    var warehouseModalElement = document.getElementById('salesWarehouseModal');
    if (warehouseModalElement) {
        state.modals.warehouse = new bootstrap.Modal(warehouseModalElement);
    }

    var unitModalElement = document.getElementById('salesUnitModal');
    if (unitModalElement) {
        state.modals.unit = new bootstrap.Modal(unitModalElement);
    }

    var searchModalElement = document.getElementById('salesInvoiceSearchModal');
    if (searchModalElement) {
        state.modals.search = new bootstrap.Modal(searchModalElement);
    }

    // الحالة الابتدائية
    setSalesInvoiceMode('view');
    clearSalesInvoiceForm();
};

document.addEventListener('DOMContentLoaded', function () {
    initSalesInvoice();
});