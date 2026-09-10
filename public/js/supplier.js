/**
 * =========================================================
 * supplier.js
 * إدارة الموردين
 * =========================================================
 */

document.addEventListener('DOMContentLoaded', function () {

    // =====================================================
    // عناصر الصفحة
    // =====================================================

    const addSupplierBtn =
        document.getElementById('addSupplierBtn');

    const supplierForm =
        document.getElementById('supplierForm');

    const supplierModalElement =
        document.getElementById('supplierModal');

    const saveSupplierBtn =
        document.getElementById('saveSupplierBtn');

    const supplierModalTitle =
        document.getElementById('supplierModalLabel');

    let suppliersTableContainer =
        document.getElementById(
            'suppliersTableContainer'
        );

    let suppliersTbody =
        document.getElementById(
            'suppliersTableBody'
        );

    // عناصر البحث
    const searchName =
        document.getElementById('searchSupplierName');

    const searchPhone =
        document.getElementById('searchSupplierPhone');

    const searchCode =
        document.getElementById('searchSupplierCode');

    // حقول النموذج
    const supplierID =
        document.getElementById('supplierID');

    const supName =
        document.getElementById('supName');

    const supPhone =
        document.getElementById('supPhone');

    const supArea =
        document.getElementById('supArea');

    const supStatus =
        document.getElementById('supStatus');

    // رقم الحساب التحليلي
    const supplierAccountCode =
        document.getElementById('supplierAccountCode');

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

    let supplierModal = null;

    if (supplierModalElement) {

        supplierModal =
            bootstrap.Modal.getOrCreateInstance(
                supplierModalElement
            );
    }

    let formMode = 'add';

    let editingSupplierId = null;

    let deletingSupplierId = null;

    let searchController = null;

    let currentPage = 1;

    const rowsPerPage = 10;

    // =====================================================
    // بيانات المورد الأصلية قبل التعديل
    // =====================================================

    let originalSupplierData = null;

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

        editingSupplierId = null;

        originalSupplierData = null;

        if (supplierModalTitle) {

            supplierModalTitle.textContent =
                'إضافة مورد جديد';
        }

        if (saveSupplierBtn) {

            saveSupplierBtn.textContent =
                'حفظ البيانات';
        }

        if (supplierForm) {

            supplierForm.reset();
        }

        if (supplierID) {

            supplierID.value = '';
        }

        if (supplierAccountCode) {

            supplierAccountCode.value = '';
        }

        if (supStatus) {

            supStatus.value = '0';
        }

        setFormMethod('POST');
    }

    // =====================================================
    // تعديل المورد
    // =====================================================

    window.editSupplier = async function (id) {

        try {

            const response =
                await fetch(
                    `/setting/suppliers/${id}`,
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
                    'تعذر تحميل بيانات المورد'
                );
            }

            const supplier =
                data.supplier;

            // =================================================
            // حفظ البيانات الأصلية قبل التعديل
            // =================================================

            originalSupplierData = {

                supName:
                    supplier.supName ?? '',

                supPhone:
                    supplier.supPhone ?? '',

                supArea:
                    supplier.supArea ?? '',

                supStoped:
                    String(
                        supplier.supStoped ?? 0
                    )
            };

            formMode = 'edit';

            editingSupplierId =
                supplier.suplierID;

            if (supplierModalTitle) {

                supplierModalTitle.textContent =
                    'تعديل المورد';
            }

            if (saveSupplierBtn) {

                saveSupplierBtn.textContent =
                    'تحديث البيانات';
            }

            if (supplierID) {

                supplierID.value =
                    supplier.suplierID ?? '';
            }

            if (supName) {

                supName.value =
                    supplier.supName ?? '';
            }

            if (supPhone) {

                supPhone.value =
                    supplier.supPhone ?? '';
            }

            if (supArea) {

                supArea.value =
                    supplier.supArea ?? '';
            }

            if (supStatus) {

                supStatus.value =
                    String(
                        supplier.supStoped ?? 0
                    );
            }

            // =================================================
            // رقم الحساب التحليلي
            // =================================================

            if (supplierAccountCode) {

                supplierAccountCode.value =
                    supplier.accountCode ?? '';
            }

            setFormMethod('PUT');

            if (supplierModal) {

                supplierModal.show();
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

        if (!supplierForm) return;

        let methodInput =
            supplierForm.querySelector(
                'input[name="_method"]'
            );

        if (
            method.toUpperCase() ===
            'POST'
        ) {

            if (methodInput) {

                methodInput.remove();
            }

            supplierForm.method =
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

            supplierForm.appendChild(
                methodInput
            );
        }

        methodInput.value =
            method.toUpperCase();

        supplierForm.method =
            'POST';
    }

    // =====================================================
    // حفظ / تحديث المورد
    // =====================================================

    if (supplierForm) {

        supplierForm.addEventListener(
            'submit',
            async function (event) {

                event.preventDefault();

                if (
                    saveSupplierBtn &&
                    saveSupplierBtn.disabled
                ) {

                    return;
                }

                // =================================================
                // التحقق من وجود تعديل
                // =================================================

                if (
                    formMode === 'edit' &&
                    originalSupplierData
                ) {

                    const currentSupplierData = {

                        supName:
                            supName?.value.trim() ?? '',

                        supPhone:
                            supPhone?.value.trim() ?? '',

                        supArea:
                            supArea?.value.trim() ?? '',

                        supStoped:
                            String(
                                supStatus?.value ?? '0'
                            )
                    };

                    const hasChanges =
                        currentSupplierData.supName !==
                            originalSupplierData.supName ||

                        currentSupplierData.supPhone !==
                            originalSupplierData.supPhone ||

                        currentSupplierData.supArea !==
                            originalSupplierData.supArea ||

                        currentSupplierData.supStoped !==
                            originalSupplierData.supStoped;

                    if (!hasChanges) {

                        if (
                            typeof showSystemToast ===
                            'function'
                        ) {

                            showSystemToast(
                                'لم يتم إجراء أي تعديل على بيانات المورد',
                                'danger'
                            );
                        }

                        return;
                    }
                }

                if (saveSupplierBtn) {

                    saveSupplierBtn.disabled =
                        true;
                }

                try {

                    const formData =
                        new FormData(
                            supplierForm
                        );

                    // لا نرسل accountID
                    formData.delete(
                        'accountID'
                    );

                    let url =
                        '/setting/suppliers';

                    const isEdit =
                        formMode === 'edit' &&
                        editingSupplierId;

                    if (isEdit) {

                        url =
                            `/setting/suppliers/${editingSupplierId}`;

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
                        supplierAccountCode &&
                        data.supplier &&
                        data.supplier.accountCode
                    ) {

                        supplierAccountCode.value =
                            data.supplier.accountCode;
                    }

                    // =================================================
                    // إعادة تحميل الجدول من Blade
                    // =================================================

                    await reloadSuppliersTable();

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

                    if (supplierModal) {

                        supplierModal.hide();
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

                    if (saveSupplierBtn) {

                        saveSupplierBtn.disabled =
                            false;
                    }
                }
            }
        );
    }

    // =====================================================
    // إضافة مورد
    // =====================================================

    if (addSupplierBtn) {

        addSupplierBtn.addEventListener(
            'click',
            function () {

                setAddMode();

                if (supplierModal) {

                    supplierModal.show();
                }
            }
        );
    }

    // =====================================================
    // إغلاق نافذة المورد
    // =====================================================

    if (supplierModalElement) {

        supplierModalElement.addEventListener(
            'hidden.bs.modal',
            function () {

                setAddMode();
            }
        );
    }

    // =====================================================
    // الحذف
    // =====================================================

    window.deleteSupplier =
        function (id) {

            deletingSupplierId = id;

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

        deletingSupplierId = null;

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

                if (!deletingSupplierId) {

                    return;
                }

                const supplierID =
                    deletingSupplierId;

                deleteConfirmBtn.disabled =
                    true;

                try {

                    const response =
                        await fetch(
                            `/setting/suppliers/${supplierID}`,
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
                            'لا يمكن حذف المورد لوجود حركات مرتبطة به'
                        );
                    }

                    // =================================================
                    // إعادة تحميل الجدول من Blade
                    // =================================================

                    await reloadSuppliersTable();

                    closeDeleteConfirm();

                    if (
                        typeof showSystemToast ===
                        'function'
                    ) {

                        showSystemToast(
                            data.message ||
                            'تم حذف المورد بنجاح',
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

        if (!suppliersTbody) return;

        const rows =
            Array.from(
                suppliersTbody.querySelectorAll(
                    'tr.supplier-row'
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
                'suppliersPaginationList'
            );

        const paginationInfo =
            document.getElementById(
                'suppliersPaginationInfo'
            );

        if (paginationInfo) {

            if (totalRows === 0) {

                paginationInfo.textContent =
                    'عرض 0-0 من 0 مورد';

            } else {

                paginationInfo.textContent =
                    `عرض ${start + 1}-${Math.min(
                        end,
                        totalRows
                    )} من ${totalRows} مورد`;
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
            'suppliersPaginationList'
        );

    // =====================================================
    // البحث
    // =====================================================

    let searchTimer = null;

    function handleSearch() {

        clearTimeout(searchTimer);

        searchTimer = setTimeout(() => {

            currentPage = 1;

            reloadSuppliersTable();

        }, 300);
    }

    if (searchName) {

        searchName.addEventListener(
            'focus',
            function () {

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

                reloadSuppliersTable();
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

                reloadSuppliersTable();
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

                reloadSuppliersTable();
            }
        );

        searchCode.addEventListener(
            'input',
            handleSearch
        );
    }

    // =====================================================
    // إعادة تحميل جدول الموردين
    // =====================================================

    async function reloadSuppliersTable() {

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
                    ? `/setting/suppliers/list?${query}`
                    : '/setting/suppliers/list';

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
                    'تعذر تحميل الموردين'
                );
            }

            // =================================================
            // Blade هو الذي أنشأ الجدول والصفوف
            // =================================================

            if (
                suppliersTableContainer
            ) {

                suppliersTableContainer.innerHTML =
                    data.html || '';

                suppliersTbody =
                    document.getElementById(
                        'suppliersTableBody'
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
        suppliersTableContainer
    ) {

        suppliersTableContainer.addEventListener(
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
                // تعديل المورد
                // =================================================

                const editBtn =
                    event.target.closest(
                        '.edit-supplier'
                    );

                if (editBtn) {

                    event.preventDefault();

                    editSupplier(
                        editBtn.dataset.id
                    );

                    return;
                }

                // =================================================
                // حذف المورد
                // =================================================

                const deleteBtn =
                    event.target.closest(
                        '.delete-supplier'
                    );

                if (deleteBtn) {

                    event.preventDefault();

                    deleteSupplier(
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

    if (suppliersTbody) {

        applyPagination();
    }

    // تحميل الموردين عند فتح الصفحة

});


// =====================================================
// طباعة التقرير
// =====================================================

function printSuppliers() {

    const table =
        document.getElementById(
            'suppliersTable'
        );

    if (!table) {

        return;
    }

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
    // جلب جميع صفوف الموردين
    // =================================================

    const allRows =
        Array.from(
            table.querySelectorAll(
                'tbody tr.supplier-row'
            )
        );

    const printTbody =
        printTable.querySelector(
            'tbody'
        );

    if (printTbody) {

        printTbody.innerHTML = '';

        allRows.forEach(
            row => {

                const clonedRow =
                    row.cloneNode(true);

                clonedRow.style.display =
                    'table-row';

                clonedRow.classList.remove(
                    'd-none'
                );

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
    // حساب عدد الموردين
    // =================================================

    const totalSuppliers =
        allRows.length;

    let activeSuppliers =
        0;

    let inactiveSuppliers =
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

                activeSuppliers++;

            } else if (
                status === 'غير نشط'
            ) {

                inactiveSuppliers++;
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

            <title>تقرير الموردين</title>

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
                    تقرير الموردين
                </h2>

                <div class="report-date">
                    تاريخ الطباعة: ${printDate}
                </div>

            </div>

            ${printTable.outerHTML}

            <div class="report-footer">

                <span>
                    إجمالي الموردين:
                    ${totalSuppliers}
                </span>

                <span>
                    الموردون النشطون:
                    ${activeSuppliers}
                </span>

                <span>
                    الموردون غير النشطين:
                    ${inactiveSuppliers}
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