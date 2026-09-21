/**
 * =========================================================
 * customers.js
 * إدارة العملاء
 * =========================================================
 */

document.addEventListener('DOMContentLoaded', function () {

    // =====================================================
    // عناصر الصفحة
    // =====================================================
    const addCustomerBtn =
        document.getElementById('addCustomerBtn');

    const customerForm =
        document.getElementById('customerForm');

    const customerModalElement =
        document.getElementById('customerModal');

    const saveCustomerBtn =
        document.getElementById('saveCustomerBtn');

    const customerModalTitle =
        document.getElementById('customerModalLabel');

    let customersTableContainer =
        document.getElementById(
            'customersTableContainer'
        );

    let customersTbody =
        document.getElementById(
            'customersTableBody'
        );

    // عناصر البحث
    const searchName =
        document.getElementById('searchName');

    const searchPhone =
        document.getElementById('searchPhone');

    const searchCode =
        document.getElementById('searchCode');

    // حقول النموذج
    const customerID =
        document.getElementById('customerID');

    const cusName =
        document.getElementById('cusName');

    const cusPhone =
        document.getElementById('cusPhone');

    const cusAddress =
        document.getElementById('cusAddress');

    const cusStatus =
        document.getElementById('cusStatus');

    // رقم الحساب التحليلي
    const customerAccountCode =
        document.getElementById('customerAccountCode');

    // نافذة الحذف
    const deleteConfirmModal =
        document.getElementById('deleteConfirmModal');

    const deleteCancelBtn =
        document.getElementById('deleteCancelBtn');

    const deleteConfirmBtn =
        document.getElementById('deleteConfirmBtn');

    // =====================================================
    // Modals
    // =====================================================

    let customerModal = null;

    if (customerModalElement) {

        customerModal =
            bootstrap.Modal.getOrCreateInstance(
                customerModalElement
            );
    }

    let formMode = 'add';

    let editingCustomerId = null;

    let deletingCustomerId = null;

    let searchController = null;

    let currentPage = 1;

    const rowsPerPage = 10;

    // =====================================================
    // بيانات العميل الأصلية قبل التعديل
    // =====================================================

    let originalCustomerData = null;

    // =====================================================
    // CSRF Token
    // =====================================================

    function getCsrfToken() {

        const token =
            document.querySelector(
                'meta[name="csrf-token"]'
            );

        return token
            ? token.getAttribute('content')
            : '';
    }

    // =====================================================
    // إدارة النموذج
    // =====================================================

    function setAddMode() {

        formMode = 'add';

        editingCustomerId = null;

        originalCustomerData = null;

        if (customerModalTitle) {

            customerModalTitle.textContent =
                'إضافة عميل جديد';
        }

        if (saveCustomerBtn) {

            saveCustomerBtn.textContent =
                'حفظ البيانات';
        }

        if (customerForm) {

            customerForm.reset();
        }

        if (customerID) {

            customerID.value = '';
        }

        if (customerAccountCode) {

            customerAccountCode.value = '';
        }

        if (cusStatus) {

            cusStatus.value = '0';
        }

        setFormMethod('POST');
    }

    // =====================================================
    // تعديل العميل
    // =====================================================

    window.editCustomer = async function (id) {

        try {

            const response =
                await fetch(
                    `/setting/customers/${id}`,
                    {
                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest'
                        }
                    }
                );

            const data =
                await response.json();

            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data.message ||
                    'تعذر تحميل بيانات العميل'
                );
            }

            const customer =
                data.customer;

            // =================================================
            // حفظ البيانات الأصلية قبل التعديل
            // =================================================

            originalCustomerData = {

                CustomersName2:
                    customer.CustomersName2 ?? '',

                CusPhone:
                    customer.CusPhone ?? '',

                CusAddress:
                    customer.CusAddress ?? '',

                CusIsStopeed:
                    String(
                        customer.CusIsStopeed ?? 0
                    )
            };

            formMode = 'edit';

            editingCustomerId =
                customer.CustomersID;

            if (customerModalTitle) {

                customerModalTitle.textContent =
                    'تعديل العميل';
            }

            if (saveCustomerBtn) {

                saveCustomerBtn.textContent =
                    'تحديث البيانات';
            }

            if (customerID) {

                customerID.value =
                    customer.CustomersID ?? '';
            }

            if (cusName) {

                cusName.value =
                    customer.CustomersName2 ?? '';
            }

            if (cusPhone) {

                cusPhone.value =
                    customer.CusPhone ?? '';
            }

            if (cusAddress) {

                cusAddress.value =
                    customer.CusAddress ?? '';
            }

            if (cusStatus) {

                cusStatus.value =
                    String(
                        customer.CusIsStopeed ?? 0
                    );
            }

            // =================================================
            // رقم الحساب التحليلي
            // =================================================

            if (customerAccountCode) {

                customerAccountCode.value =
                    customer.accountCode ?? '';
            }

            setFormMethod('PUT');

            if (customerModal) {

                customerModal.show();
            }

        } catch (error) {

            console.error('خطأ:', error);

            if (
                typeof showSystemToast ===
                'function'
            ) {

                showSystemToast(
                    error.message,
                    'danger'
                );
            }
        }
    };

    // =====================================================
    // تحديد طريقة الإرسال
    // =====================================================

    function setFormMethod(method) {

        if (!customerForm) return;

        let methodInput =
            customerForm.querySelector(
                'input[name="_method"]'
            );

        if (
            method.toUpperCase() ===
            'POST'
        ) {

            if (methodInput) {

                methodInput.remove();
            }

            customerForm.method =
                'POST';

            return;
        }

        if (!methodInput) {

            methodInput =
                document.createElement(
                    'input'
                );

            methodInput.type =
                'hidden';

            methodInput.name =
                '_method';

            customerForm.appendChild(
                methodInput
            );
        }

        methodInput.value =
            method.toUpperCase();

        customerForm.method =
            'POST';
    }

    // =====================================================
    // حفظ / تحديث العميل
    // =====================================================

    if (customerForm) {

        customerForm.addEventListener(
            'submit',
            async function (event) {

                event.preventDefault();

                if (
                    saveCustomerBtn &&
                    saveCustomerBtn.disabled
                ) {

                    return;
                }

                // =================================================
                // التحقق من وجود تعديل
                // =================================================

                if (
                    formMode === 'edit' &&
                    originalCustomerData
                ) {

                    const currentCustomerData = {

                        CustomersName2:
                            cusName?.value.trim() ?? '',

                        CusPhone:
                            cusPhone?.value.trim() ?? '',

                        CusAddress:
                            cusAddress?.value.trim() ?? '',

                        CusIsStopeed:
                            String(
                                cusStatus?.value ?? '0'
                            )
                    };

                    const hasChanges =
                        currentCustomerData.CustomersName2 !==
                            originalCustomerData.CustomersName2 ||

                        currentCustomerData.CusPhone !==
                            originalCustomerData.CusPhone ||

                        currentCustomerData.CusAddress !==
                            originalCustomerData.CusAddress ||

                        currentCustomerData.CusIsStopeed !==
                            originalCustomerData.CusIsStopeed;

                    if (!hasChanges) {

                        if (
                            typeof showSystemToast ===
                            'function'
                        ) {

                            showSystemToast(
                                'لم يتم إجراء أي تعديل على بيانات العميل',
                                'danger'
                            );
                        }

                        return;
                    }
                }

                if (saveCustomerBtn) {

                    saveCustomerBtn.disabled =
                        true;
                }

                try {

                    const formData =
                        new FormData(
                            customerForm
                        );

                    // لا نرسل accountID
                    formData.delete(
                        'accountID'
                    );

                    let url =
                        '/setting/customers';

                    const isEdit =
                        formMode === 'edit' &&
                        editingCustomerId;

                    if (isEdit) {

                        url =
                            `/setting/customers/${editingCustomerId}`;

                        formData.set(
                            '_method',
                            'PUT'
                        );
                    }

                    const response =
                        await fetch(
                            url,
                            {
                                method: 'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                    'X-CSRF-TOKEN':
                                        getCsrfToken()
                                },

                                body: formData
                            }
                        );

                    const data =
                        await response.json();

                    if (
                        !response.ok ||
                        !data.success
                    ) {

                        let message =
                            data.message ||
                            'حدث خطأ أثناء الحفظ';

                        if (
                            data.errors &&
                            typeof data.errors ===
                                'object'
                        ) {

                            const firstError =
                                Object.values(
                                    data.errors
                                )[0];

                            if (
                                Array.isArray(
                                    firstError
                                )
                            ) {

                                message =
                                    firstError[0];
                            }
                        }

                        throw new Error(
                            message
                        );
                    }

                    // =================================================
                    // عرض الرقم التحليلي
                    // =================================================

                    if (
                        customerAccountCode &&
                        data.customer &&
                        data.customer.accountCode
                    ) {

                        customerAccountCode.value =
                            data.customer.accountCode;
                    }

                    // =================================================
                    // إعادة تحميل الجدول من Blade
                    // =================================================

                    await reloadCustomersTable();

                    if (
                        typeof showSystemToast ===
                        'function'
                    ) {

                        showSystemToast(
                            data.message ||
                            (
                                formMode === 'edit'
                                    ? 'تم التحديث بنجاح'
                                    : 'تمت الإضافة بنجاح'
                            ),
                            'success'
                        );
                    }

                    if (customerModal) {

                        customerModal.hide();
                    }

                    setAddMode();

                } catch (error) {

                    console.error(
                        'خطأ:',
                        error
                    );

                    if (
                        typeof showSystemToast ===
                        'function'
                    ) {

                        showSystemToast(
                            error.message,
                            'danger'
                        );
                    }

                } finally {

                    if (saveCustomerBtn) {

                        saveCustomerBtn.disabled =
                            false;
                    }
                }
            }
        );
    }

    // =====================================================
    // إضافة عميل
    // =====================================================

    if (addCustomerBtn) {

        addCustomerBtn.addEventListener(
            'click',
            function () {

                setAddMode();

                if (customerModal) {

                    customerModal.show();
                }
            }
        );
    }

    // =====================================================
    // إغلاق نافذة العميل
    // =====================================================

    if (customerModalElement) {

        customerModalElement.addEventListener(
            'hidden.bs.modal',
            function () {

                setAddMode();
            }
        );
    }

    // =====================================================
    // الحذف
    // =====================================================

    window.deleteCustomer =
        function (id) {

            deletingCustomerId = id;

            if (deleteConfirmModal) {

                deleteConfirmModal.classList.add(
                    'show'
                );

                deleteConfirmModal.style.display =
                    'flex';

                document.body.classList.add(
                    'delete-confirm-open'
                );
            }
        };

    function closeDeleteConfirm() {

        deletingCustomerId = null;

        if (deleteConfirmModal) {

            deleteConfirmModal.classList.remove(
                'show'
            );

            deleteConfirmModal.style.display =
                'none';
        }

        document.body.classList.remove(
            'delete-confirm-open'
        );
    }

    if (deleteCancelBtn) {

        deleteCancelBtn.addEventListener(
            'click',
            closeDeleteConfirm
        );
    }

    if (deleteConfirmBtn) {

        deleteConfirmBtn.addEventListener(
            'click',
            async function () {

                if (!deletingCustomerId) {

                    return;
                }

                const customerID =
                    deletingCustomerId;

                deleteConfirmBtn.disabled =
                    true;

                try {

                    const response =
                        await fetch(
                            `/setting/customers/${customerID}`,
                            {
                                method: 'DELETE',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                    'X-CSRF-TOKEN':
                                        getCsrfToken()
                                }
                            }
                        );

                    const data =
                        await response.json();

                    if (
                        !response.ok ||
                        !data.success
                    ) {

                        throw new Error(
                            data.message ||
                            'لا يمكن حذف العميل لوجود حركات مرتبطة به'
                        );
                    }

                    // =================================================
                    // إعادة تحميل الجدول من Blade
                    // =================================================

                    await reloadCustomersTable();

                    closeDeleteConfirm();

                    if (
                        typeof showSystemToast ===
                        'function'
                    ) {

                        showSystemToast(
                            data.message ||
                            'تم حذف العميل بنجاح',
                            'success'
                        );
                    }

                } catch (error) {

                    closeDeleteConfirm();

                    if (
                        typeof showSystemToast ===
                        'function'
                    ) {

                        showSystemToast(
                            error.message,
                            'danger'
                        );
                    }

                } finally {

                    deleteConfirmBtn.disabled =
                        false;
                }
            }
        );
    }

    // =====================================================
    // Pagination
    // =====================================================

    function applyPagination() {

        if (!customersTbody) return;

        const rows =
            Array.from(
                customersTbody.querySelectorAll(
                    'tr.customer-row'
                )
            );

        const totalRows =
            rows.length;

        const totalPages =
            Math.max(
                1,
                Math.ceil(
                    totalRows /
                    rowsPerPage
                )
            );

        if (
            currentPage >
            totalPages
        ) {

            currentPage =
                totalPages;
        }

        const start =
            (currentPage - 1) *
            rowsPerPage;

        const end =
            start +
            rowsPerPage;

        rows.forEach(
            function (row, index) {

                row.style.display =
                    (
                        index >= start &&
                        index < end
                    )
                        ? ''
                        : 'none';

                const firstCell =
                    row.querySelector(
                        'td:first-child'
                    );

                if (firstCell) {

                    firstCell.textContent =
                        index + 1;
                }
            }
        );

        renderPagination(
            totalRows,
            totalPages,
            start,
            end
        );
    }

    function renderPagination(
        totalRows,
        totalPages,
        start,
        end
    ) {

        const paginationList =
            document.getElementById(
                'customersPaginationList'
            );

        const paginationInfo =
            document.getElementById(
                'customersPaginationInfo'
            );

        if (paginationInfo) {

            if (totalRows === 0) {

                paginationInfo.textContent =
                    'عرض 0-0 من 0 عميل';

            } else {

                paginationInfo.textContent =
                    `عرض ${start + 1}-${Math.min(
                        end,
                        totalRows
                    )} من ${totalRows} عميل`;
            }
        }

        if (!paginationList) return;

        paginationList.innerHTML = '';

        if (totalPages <= 1) return;

        const prevLi =
            document.createElement(
                'li'
            );

        prevLi.className =
            `page-item ${
                currentPage === 1
                    ? 'disabled'
                    : ''
            }`;

        prevLi.innerHTML =
            `<button
                type="button"
                class="page-link"
                data-page="${currentPage - 1}">
                السابق
            </button>`;

        paginationList.appendChild(
            prevLi
        );

        for (
            let page = 1;
            page <= totalPages;
            page++
        ) {

            const li =
                document.createElement(
                    'li'
                );

            li.className =
                `page-item ${
                    page === currentPage
                        ? 'active'
                        : ''
                }`;

            li.innerHTML =
                `<button
                    type="button"
                    class="page-link"
                    data-page="${page}">
                    ${page}
                </button>`;

            paginationList.appendChild(
                li
            );
        }

        const nextLi =
            document.createElement(
                'li'
            );

        nextLi.className =
            `page-item ${
                currentPage === totalPages
                    ? 'disabled'
                    : ''
            }`;

        nextLi.innerHTML =
            `<button
                type="button"
                class="page-link"
                data-page="${currentPage + 1}">
                التالي
            </button>`;

        paginationList.appendChild(
            nextLi
        );
    }

    const paginationList =
        document.getElementById(
            'customersPaginationList'
        );


    // =====================================================
    // البحث
    // =====================================================

    let searchTimer = null;
function handleSearch() {

    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {

        currentPage = 1;

        reloadCustomersTable();

    }, 300);
}

if (searchName) {

    searchName.addEventListener(
        'focus',
        function () {

            // إذا كان هذا الحقل يحتوي قيمة
            // نبقى على البحث الحالي
            if (
                searchName.value.trim() !== ''
            ) {
                return;
            }

            if (searchPhone) {
                searchPhone.value = '';
            }

            if (searchCode) {
                searchCode.value = '';
            }

            currentPage = 1;

            reloadCustomersTable();
        }
    );

    searchName.addEventListener(
        'input',
        handleSearch
    );
}

if (searchPhone) {

    searchPhone.addEventListener(
        'focus',
        function () {

            if (
                searchPhone.value.trim() !== ''
            ) {
                return;
            }

            if (searchName) {
                searchName.value = '';
            }

            if (searchCode) {
                searchCode.value = '';
            }

            currentPage = 1;

            reloadCustomersTable();
        }
    );

    searchPhone.addEventListener(
        'input',
        handleSearch
    );
}

if (searchCode) {

    searchCode.addEventListener(
        'focus',
        function () {

            if (
                searchCode.value.trim() !== ''
            ) {
                return;
            }

            if (searchName) {
                searchName.value = '';
            }

            if (searchPhone) {
                searchPhone.value = '';
            }

            currentPage = 1;

            reloadCustomersTable();
        }
    );

    searchCode.addEventListener(
        'input',
        handleSearch
    );
}
    // =====================================================
    // إعادة تحميل جدول العملاء
    // =====================================================

    async function reloadCustomersTable() {

        // إلغاء الطلب السابق إن وجد
        if (searchController) {

            searchController.abort();
        }

        searchController =
            new AbortController();

        try {

            const params =
                new URLSearchParams();

            if (
                searchName &&
                searchName.value.trim()
            ) {

                params.set(
                    'search_name',
                    searchName.value.trim()
                );
            }

            if (
                searchPhone &&
                searchPhone.value.trim()
            ) {

                params.set(
                    'search_phone',
                    searchPhone.value.trim()
                );
            }

            if (
                searchCode &&
                searchCode.value.trim()
            ) {

                params.set(
                    'search_code',
                    searchCode.value.trim()
                );
            }

            const query =
                params.toString();

            const url =
                query
                    ? `/setting/customers/list?${query}`
                    : '/setting/customers/list';

            const response =
                await fetch(
                    url,
                    {
                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest'
                        },

                        signal:
                            searchController.signal
                    }
                );

            const data =
                await response.json();

            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data.message ||
                    'تعذر تحميل العملاء'
                );
            }

            // =================================================
            // Blade هو الذي أنشأ الجدول والصفوف
            // =================================================

            if (
                customersTableContainer
            ) {

                customersTableContainer.innerHTML =
                    data.html || '';

                customersTbody =
                    document.getElementById(
                        'customersTableBody'
                    );
            }

            applyPagination();

        } catch (error) {

            // تجاهل خطأ الإلغاء
            if (
                error.name ===
                'AbortError'
            ) {

                return;
            }

            console.error(
                'خطأ:',
                error
            );

            if (
                typeof showSystemToast ===
                'function'
            ) {

                showSystemToast(
                    error.message,
                    'danger'
                );
            }
        }
    }

    // =====================================================
    // أحداث الجدول
    // =====================================================
if (
    customersTableContainer
) {

    customersTableContainer.addEventListener(
        'click',
        function (event) {

            // =================================================
            // التنقل بين الصفحات
            // =================================================

            const paginationBtn =
                event.target.closest(
                    '[data-page]'
                );

            if (paginationBtn) {

                event.preventDefault();

                const page =
                    parseInt(
                        paginationBtn.dataset.page,
                        10
                    );

                if (
                    !page ||
                    page < 1
                ) {
                    return;
                }

                currentPage =
                    page;

                applyPagination();

                return;
            }

            // =================================================
            // تعديل العميل
            // =================================================

            const editBtn =
                event.target.closest(
                    '.edit-customer'
                );

            if (editBtn) {

                event.preventDefault();

                editCustomer(
                    editBtn.dataset.id
                );

                return;
            }

            // =================================================
            // حذف العميل
            // =================================================

            const deleteBtn =
                event.target.closest(
                    '.delete-customer'
                );

            if (deleteBtn) {

                event.preventDefault();

                deleteCustomer(
                    deleteBtn.dataset.id
                );

                return;
            }
        }
    );
}

    // =====================================================
    // التهيئة الأولية
    // =====================================================

    if (customersTbody) {

        applyPagination();
    }

});


// =====================================================
// طباعة التقرير
// =====================================================

function printCustomers() {

    const table =
        document.getElementById(
            'customersTable'
        );

    if (!table) {

        return;
    }

    // =================================================
    // إنشاء نسخة من الجدول
    // =================================================

    const printTable =
        table.cloneNode(true);

    // حذف عمود الإجراءات
    printTable
        .querySelectorAll(
            'th:last-child, td:last-child'
        )
        .forEach(
            cell => cell.remove()
        );

    // =================================================
    // جلب جميع صفوف العملاء
    // =================================================

    const allRows =
        Array.from(
            table.querySelectorAll(
                'tbody tr.customer-row'
            )
        );

    const printTbody =
        printTable.querySelector(
            'tbody'
        );

    if (printTbody) {

        // حذف الصفوف الموجودة في النسخة
        printTbody.innerHTML = '';

        // إضافة جميع العملاء
        allRows.forEach(
            row => {

                const clonedRow =
                    row.cloneNode(true);

                // إظهار الصف حتى لو كان مخفياً
                // بسبب الترقيم
                clonedRow.style.display =
                    'table-row';

                clonedRow.classList.remove(
                    'd-none'
                );

                // حذف عمود الإجراءات
                clonedRow
                    .querySelectorAll(
                        'td:last-child'
                    )
                    .forEach(
                        cell => cell.remove()
                    );

                printTbody.appendChild(
                    clonedRow
                );
            }
        );
    }

    // =================================================
    // إعداد عرض الجدول للطباعة
    // =================================================

    printTable.style.width =
        '100%';

    printTable.style.tableLayout =
        'auto';

    // السماح للنصوص الطويلة بالظهور كاملة
    printTable
        .querySelectorAll(
            'th, td'
        )
        .forEach(
            cell => {

                cell.style.overflow =
                    'visible';

                cell.style.textOverflow =
                    'clip';

                cell.style.whiteSpace =
                    'normal';

                cell.style.wordBreak =
                    'normal';

                cell.style.overflowWrap =
                    'break-word';

            }
        );

    // =================================================
    // حساب عدد العملاء
    // =================================================

    const totalCustomers =
        allRows.length;

    let activeCustomers =
        0;

    let inactiveCustomers =
        0;

    allRows.forEach(
        row => {

            const statusCell =
                row.cells[5];

            if (!statusCell) {

                return;
            }

            const status =
                statusCell.textContent.trim();

            if (status === 'نشط') {

                activeCustomers++;

            } else if (
                status === 'غير نشط'
            ) {

                inactiveCustomers++;
            }

        }
    );

    // =================================================
    // التاريخ والوقت
    // =================================================

    const printDate =
        new Date().toLocaleString(
            'ar-YE',
            {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            }
        );

    // =================================================
    // فتح نافذة الطباعة
    // =================================================

    const printWindow =
        window.open(
            '',
            '_blank'
        );

    if (!printWindow) {

        return;
    }

    printWindow.document.write(`
        <!DOCTYPE html>

        <html lang="ar" dir="rtl">

        <head>

            <meta charset="UTF-8">

            <title>تقرير العملاء</title>

            <style>

                body {
                    font-family: Arial, sans-serif;
                    direction: rtl;
                    margin: 30px;
                    color: #000;
                }

                .report-header {
                    text-align: center;
                    margin-bottom: 20px;
                }

                .report-header h2 {
                    margin-bottom: 10px;
                }

                .report-date {
                    font-size: 14px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: auto !important;
                }

                /*
                 * تكرار ترويسة الجدول
                 * في كل ورقة
                 */
                thead {
                    display: table-header-group;
                }

                tbody {
                    display: table-row-group;
                }

                th,
                td {
                    border: 1px solid #000;
                    padding: 8px;
                    vertical-align: middle;

                    white-space: normal !important;

                    overflow: visible !important;

                    text-overflow: clip !important;

                    word-break: normal;

                    overflow-wrap: break-word;
                }

                th {
                    font-weight: bold;
                    text-align: center;
                }

                td {
                    text-align: right;
                }

                /*
                 * عدم تقسيم صف العميل
                 * بين ورقتين
                 */
                tr {
                    page-break-inside: avoid;
                    break-inside: avoid;
                }

                .report-footer {
                    margin-top: 25px;
                    padding-top: 15px;

                    border-top: 1px solid #000;

                    display: flex;

                    justify-content: space-between;

                    font-weight: bold;
                }

                @media print {

                    body {
                        margin: 15mm;
                    }

                    table {
                        page-break-inside: auto;
                    }

                    thead {
                        display: table-header-group;
                    }

                    tbody {
                        display: table-row-group;
                    }

                    tr {
                        page-break-inside: avoid;
                        break-inside: avoid;
                    }

                }

            </style>

        </head>

        <body>

            <div class="report-header">

                <h2>
                    تقرير العملاء
                </h2>

                <div class="report-date">
                    تاريخ الطباعة: ${printDate}
                </div>

            </div>

            ${printTable.outerHTML}

            <div class="report-footer">

                <span>
                    إجمالي العملاء:
                    ${totalCustomers}
                </span>

                <span>
                    العملاء النشطون:
                    ${activeCustomers}
                </span>

                <span>
                    العملاء غير النشطين:
                    ${inactiveCustomers}
                </span>

            </div>

        </body>

        </html>
    `);

    printWindow.document.close();

    printWindow.focus();

    printWindow.onload =
        function () {

            printWindow.print();

            printWindow.close();

        };
}