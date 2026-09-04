/* =========================================================
   أوضاع شاشة فاتورة البيع
   ========================================================= */

window.setSalesInvoiceMode = function (mode) {

    SalesInvoiceState.mode = mode;


    /*
     * حقول رأس الفاتورة
     */

    const inputs = document.querySelectorAll(
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
    );


    inputs.forEach(function (input) {

        input.disabled =
            mode === 'view';

    });


    /*
     * تفاصيل الفاتورة
     */

    document
        .querySelectorAll(
            '#salesInvoiceDetails input,' +
            '#salesInvoiceDetails select,' +
            '#salesInvoiceDetails button'
        )
        .forEach(function (element) {

            if (
                !element.classList.contains('row-total')
            ) {

                element.disabled =
                    mode === 'view';

            }

        });


    /*
     * زر إضافة صنف
     */

    const addRowButton =
        document.getElementById(
            'btnAddSalesRow'
        );

    if (addRowButton) {

        addRowButton.disabled =
            mode === 'view';

    }


    /*
     * أزرار الفاتورة
     */

    const saveButton =
        document.getElementById(
            'btnSaveSalesInvoice'
        );

    const saveNewButton =
        document.getElementById(
            'btnSaveAndNewSalesInvoice'
        );

    const editButton =
        document.getElementById(
            'btnEditSalesInvoice'
        );

    const cancelButton =
        document.getElementById(
            'btnCancelSalesInvoice'
        );

    const printButton =
        document.getElementById(
            'btnprintButton'
        );



    if (mode === 'view') {

        if (saveButton) saveButton.disabled = true;
        if (saveNewButton) saveNewButton.disabled = true;
        if (cancelButton) cancelButton.classList.add('d-none');
        if (editButton) editButton.disabled = !hasSalesInvoiceData();

        // الطباعة متاحة فقط في وضع العرض وفي حال وجود بيانات
        if (printButton) {
            printButton.disabled = !hasSalesInvoiceData();
            printButton.style.display = hasSalesInvoiceData() ? '' : 'none';
        }

        showSalesModeMessage(
            'عرض الفاتورة - لا يمكن تعديل البيانات. اضغط "تعديل" للسماح بالتعديل.',
            'secondary'
        );

    }


    else if (mode === 'add') {

        if (saveButton) saveButton.disabled = false;
        if (saveNewButton) saveNewButton.disabled = false;
        if (cancelButton) cancelButton.classList.remove('d-none');
        if (editButton) editButton.disabled = true;

        // تعطيل الطباعة في وضع الإضافة
        if (printButton) {
            printButton.disabled = true;
            printButton.style.display = 'none';
        }

        showSalesModeMessage(
            'إضافة فاتورة جديدة - يمكنك إدخال بيانات الفاتورة.',
            'primary'
        );

    }


    else if (mode === 'edit') {

        if (saveButton) saveButton.disabled = false;
        if (saveNewButton) saveNewButton.disabled = false;
        if (cancelButton) cancelButton.classList.remove('d-none');
        if (editButton) editButton.disabled = true;

        // تعطيل الطباعة في وضع التعديل
        if (printButton) {
            printButton.disabled = true;
            printButton.style.display = 'none';
        }

        showSalesModeMessage(
            'تعديل الفاتورة - يمكنك الآن تعديل البيانات.',
            'warning'
        );

    }

};


/* =========================================================
   رسالة حالة الشاشة
   ========================================================= */

window.showSalesModeMessage = function (
    message,
    type
) {

    const alertBox =
        document.getElementById(
            'salesInvoiceModeAlert'
        );

    const text =
        document.getElementById(
            'salesInvoiceModeText'
        );

    if (!alertBox || !text) return;

    alertBox.className =
        'alert alert-' +
        type +
        ' py-2';

    text.textContent = message;

};


/* =========================================================
   التحقق من وجود فاتورة
   ========================================================= */

window.hasSalesInvoiceData = function () {

    const invoiceNumber =
        document.getElementById(
            'SalesInvoiceNo'
        );

    if (!invoiceNumber) return false;

    return invoiceNumber.value.trim() !== '';

};


/* =========================================================
   فاتورة جديدة
   ========================================================= */

window.resetSalesInvoice = function () {

    clearSalesInvoiceForm();

    // توليد رقم فاتورة جديد
    generateSalesInvoiceNumber();

    // تعيين التاريخ الحالي
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('SalesInvoiceDate').value = today;

    // إضافة صف فارغ
    addSalesRow();

    // تفعيل حقول العملة وسعر الصرف
    document.getElementById('salesCurrencyName').disabled = false;
    document.getElementById('SalesExchangeRate').disabled = false;

    setSalesInvoiceMode('add');

};


/* =========================================================
   تعديل الفاتورة
   ========================================================= */

window.editSalesInvoice = function () {

    if (!hasSalesInvoiceData()) {

        alert(
            'لا توجد فاتورة محددة للتعديل.'
        );

        return;

    }

    setSalesInvoiceMode('edit');

};


/* =========================================================
   طباعة الفاتورة
   ========================================================= */

window.printSalesInvoice = function () {

    if (!hasSalesInvoiceData()) {

        alert(
            'لا توجد فاتورة للطباعة.'
        );

        return;

    }

    window.print();

};