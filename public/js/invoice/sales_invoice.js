/* =========================================================
   فاتورة البيع - الملف الرئيسي
   ========================================================= */


/* =========================================================
   التحقق من وجود حالة الفاتورة
   ========================================================= */

function getSalesInvoiceState() {

    if (typeof window.SalesInvoiceState === 'undefined') {

        console.error(
            'SalesInvoiceState غير موجود'
        );

        return null;

    }

    return window.SalesInvoiceState;

}


/* =========================================================
   زر حفظ الفاتورة
   ========================================================= */

window.saveSalesInvoice = function () {

    const state = getSalesInvoiceState();

    if (!state) {

        alert('خطأ: لم يتم تحميل حالة فاتورة البيع');
        return;

    }

    // التحقق من البيانات الأساسية
    const invoiceNo = document.getElementById('SalesInvoiceNo')?.value || '';
    const invoiceDate = document.getElementById('SalesInvoiceDate')?.value || '';
    const customer = document.getElementById('customerName')?.value || '';
    const currency = document.getElementById('salesCurrencyName')?.value || '';
    const paymentMethod = document.getElementById('SalesPaymentMethod')?.value || '';

    if (!invoiceDate) {
        alert('يرجى إدخال تاريخ الفاتورة');
        return;
    }

    if (!customer) {
        alert('يرجى اختيار العميل');
        return;
    }

    if (!currency) {
        alert('يرجى اختيار العملة');
        return;
    }

    if (!paymentMethod) {
        alert('يرجى اختيار طريقة الدفع');
        return;
    }

    // التحقق من وجود أصناف
    const rows = document.querySelectorAll('#salesInvoiceDetails .sales-detail-row');
    if (rows.length === 0) {
        alert('يرجى إضافة صنف واحد على الأقل');
        return;
    }

    // حساب الإجماليات
    if (typeof window.calculateSalesTotals === 'function') {
        window.calculateSalesTotals();
    }

    const total = document.getElementById('salesInvoiceTotalDisplay')?.textContent || '0.00';

    // بيانات الفاتورة
    const invoiceData = {
        number: invoiceNo,
        date: invoiceDate,
        customer: customer,
        currency: currency,
        paymentMethod: paymentMethod,
        total: total
    };

    console.log('بيانات فاتورة البيع:', invoiceData);

    alert(
        'تم حفظ فاتورة البيع بنجاح\n\n' +
        'رقم الفاتورة: ' + invoiceNo + '\n' +
        'العميل: ' + customer + '\n' +
        'الإجمالي: ' + total
    );

    // بعد الحفظ ننتقل إلى وضع العرض
    if (typeof window.setSalesInvoiceMode === 'function') {
        window.setSalesInvoiceMode('view');
    }

};


/* =========================================================
   حفظ وإضافة فاتورة جديدة
   ========================================================= */

window.saveAndNewSalesInvoice = function () {

    const customer = document.getElementById('customerName')?.value || '';
    const date = document.getElementById('SalesInvoiceDate')?.value || '';

    if (!date) {
        alert('يرجى إدخال تاريخ الفاتورة أولاً');
        return;
    }

    if (!customer) {
        alert('يرجى اختيار العميل أولاً');
        return;
    }

    // حفظ الفاتورة أولاً
    saveSalesInvoice();

    // ثم إنشاء فاتورة جديدة
    if (typeof window.resetSalesInvoice === 'function') {
        window.resetSalesInvoice();
    }

};


/* =========================================================
   زر الطباعة
   ========================================================= */

window.printButton = function () {

    if (typeof window.printSalesInvoice === 'function') {

        window.printSalesInvoice();

    } else {

        alert('دالة الطباعة غير متوفرة');

    }

};


/* =========================================================
   تهيئة الشاشة
   ========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        console.log('تم تحميل شاشة فواتير البيع');

        if (typeof window.initSalesInvoice === 'function') {

            window.initSalesInvoice();

        }

    }
);