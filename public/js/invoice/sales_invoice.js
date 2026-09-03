/* =========================================================
   فاتورة البيع
   الملف الرئيسي للشاشة
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
   وظيفة تجريبية
   ========================================================= */

window.saveSalesInvoice = function () {

    const state =
        getSalesInvoiceState();


    if (!state) {

        alert(
            'خطأ: لم يتم تحميل حالة فاتورة البيع'
        );

        return;

    }


    /* -----------------------------------------
       قراءة البيانات الأساسية
       ----------------------------------------- */

    const invoiceNo =
        document.getElementById(
            'SalesInvoiceNo'
        )?.value || '';


    const invoiceDate =
        document.getElementById(
            'SalesInvoiceDate'
        )?.value || '';


    const customer =
        document.getElementById(
            'customerName'
        )?.value || '';


    const currency =
        document.getElementById(
            'salesCurrencyName'
        )?.value || '';


    const paymentMethod =
        document.getElementById(
            'SalesPaymentMethod'
        )?.value || '';


    /* -----------------------------------------
       تحقق تجريبي
       ----------------------------------------- */

    if (!invoiceDate) {

        alert(
            'يرجى إدخال تاريخ الفاتورة'
        );

        return;

    }


    if (!customer) {

        alert(
            'يرجى اختيار العميل'
        );

        return;

    }


    if (!currency) {

        alert(
            'يرجى اختيار العملة'
        );

        return;

    }


    if (!paymentMethod) {

        alert(
            'يرجى اختيار طريقة الدفع'
        );

        return;

    }


    /* -----------------------------------------
       حساب الإجماليات
       ----------------------------------------- */

    if (
        typeof window.calculateSalesTotals ===
        'function'
    ) {

        window.calculateSalesTotals();

    }


    const total =
        document.getElementById(
            'salesInvoiceTotalDisplay'
        )?.textContent || '0.00';


    /* -----------------------------------------
       بيانات تجريبية
       ----------------------------------------- */

    const invoiceData = {

        number: invoiceNo,

        date: invoiceDate,

        customer: customer,

        currency: currency,

        paymentMethod: paymentMethod,

        total: total

    };


    console.log(
        'بيانات فاتورة البيع:',
        invoiceData
    );


    /* -----------------------------------------
       رسالة نجاح تجريبية
       ----------------------------------------- */

    alert(
        'تم حفظ فاتورة البيع بنجاح\n\n' +
        'رقم الفاتورة: ' + invoiceNo + '\n' +
        'العميل: ' + customer + '\n' +
        'الإجمالي: ' + total
    );


    /* -----------------------------------------
       بعد الحفظ ننتقل إلى وضع العرض
       ----------------------------------------- */

    if (
        typeof window.setSalesInvoiceMode ===
        'function'
    ) {

        window.setSalesInvoiceMode(
            'view'
        );

    }

};


/* =========================================================
   حفظ وإضافة فاتورة جديدة
   وظيفة تجريبية
   ========================================================= */

window.saveAndNewSalesInvoice = function () {

    /* -----------------------------------------
       التحقق من البيانات أولًا
       ----------------------------------------- */

    const customer =
        document.getElementById(
            'customerName'
        )?.value || '';


    const date =
        document.getElementById(
            'SalesInvoiceDate'
        )?.value || '';


    if (!date) {

        alert(
            'يرجى إدخال تاريخ الفاتورة أولًا'
        );

        return;

    }


    if (!customer) {

        alert(
            'يرجى اختيار العميل أولًا'
        );

        return;

    }


    /* -----------------------------------------
       حساب الإجماليات
       ----------------------------------------- */

    if (
        typeof window.calculateSalesTotals ===
        'function'
    ) {

        window.calculateSalesTotals();

    }


    const total =
        document.getElementById(
            'salesInvoiceTotalDisplay'
        )?.textContent || '0.00';


    console.log(
        'حفظ الفاتورة ثم إنشاء فاتورة جديدة',
        {
            customer: customer,
            date: date,
            total: total
        }
    );


    /* -----------------------------------------
       رسالة تجريبية
       ----------------------------------------- */

    alert(
        'تم حفظ الفاتورة بنجاح.\n\n' +
        'الإجمالي: ' + total +
        '\n\n' +
        'سيتم الآن فتح فاتورة بيع جديدة.'
    );


    /* -----------------------------------------
       إنشاء فاتورة جديدة
       ----------------------------------------- */

    if (
        typeof window.resetSalesInvoice ===
        'function'
    ) {

        window.resetSalesInvoice();

        return;

    }


    /* -----------------------------------------
       احتياط إذا لم تكن الدالة موجودة
       ----------------------------------------- */

    if (
        typeof window.setSalesInvoiceMode ===
        'function'
    ) {

        window.setSalesInvoiceMode(
            'new'
        );

    }

};


/* =========================================================
   زر تعديل الفاتورة
   وظيفة تجريبية
   ========================================================= */

window.editSalesInvoice = function () {

    const state =
        getSalesInvoiceState();


    if (!state) {

        alert(
            'خطأ: حالة الفاتورة غير موجودة'
        );

        return;

    }


    /* -----------------------------------------
       التأكد من وجود فاتورة للعرض
       ----------------------------------------- */

    const invoiceNo =
        document.getElementById(
            'SalesInvoiceNo'
        )?.value || '';


    if (!invoiceNo) {

        alert(
            'لا توجد فاتورة محددة للتعديل'
        );

        return;

    }


    /* -----------------------------------------
       الانتقال إلى وضع التعديل
       ----------------------------------------- */

    if (
        typeof window.setSalesInvoiceMode ===
        'function'
    ) {

        window.setSalesInvoiceMode(
            'edit'
        );

    }


    console.log(
        'تم الانتقال إلى وضع تعديل الفاتورة:',
        invoiceNo
    );


    alert(
        'تم تفعيل وضع تعديل الفاتورة\n\n' +
        'رقم الفاتورة: ' + invoiceNo
    );

};


/* =========================================================
   زر إلغاء التعديل
   وظيفة تجريبية
   ========================================================= */

window.cancelSalesInvoice = function () {

    const state =
        getSalesInvoiceState();


    if (!state) {

        alert(
            'خطأ: حالة الفاتورة غير موجودة'
        );

        return;

    }


    /* -----------------------------------------
       رسالة تأكيد
       ----------------------------------------- */

    const confirmed =
        confirm(
            'هل تريد إلغاء التعديل والعودة إلى وضع العرض؟'
        );


    if (!confirmed) {

        return;

    }


    /* -----------------------------------------
       العودة إلى وضع العرض
       ----------------------------------------- */

    if (
        typeof window.setSalesInvoiceMode ===
        'function'
    ) {

        window.setSalesInvoiceMode(
            'view'
        );

    }


    console.log(
        'تم إلغاء تعديل فاتورة البيع'
    );

};


/* =========================================================
   زر إضافة فاتورة جديدة
   ========================================================= */

window.newSalesInvoice = function () {

    if (
        typeof window.resetSalesInvoice ===
        'function'
    ) {

        window.resetSalesInvoice();

        return;

    }


    alert(
        'دالة إنشاء فاتورة جديدة غير محملة'
    );

};


/* =========================================================
   تهيئة الشاشة
   ========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        console.log(
            'تم تحميل شاشة فواتير البيع'
        );


        console.log(
            'SalesInvoiceState:',
            window.SalesInvoiceState
        );


        /*
         * إذا كانت دالة تهيئة الشاشة موجودة
         * يتم تشغيلها.
         */

        if (
            typeof window.initSalesInvoice ===
            'function'
        ) {

            window.initSalesInvoice();

        }

    }
);