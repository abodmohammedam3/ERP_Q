/* =========================================================
   حالة شاشة فاتورة البيع
   ========================================================= */

window.SalesInvoiceState = {

    mode: 'view',

    activeRow: null,

    lastInvoiceNumber: 0,

    currentInvoiceId: null,

    modals: {

        customer: null,

        currency: null,

        item: null,

        type: null,

        warehouse: null,

        unit: null,

        search: null

    }

};