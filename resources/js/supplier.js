/**
 * =========================================================
 * suppliers.js
 * إدارة الموردين
 * =========================================================
 */

// =====================================================
// Toast Helper موحّد
// =====================================================

function notify(message, type = 'danger') {
    if (typeof showSystemToast === 'function') {
        showSystemToast(message, type);
    } else {
        console.warn(`[${type}]`, message);
    }
}

// =====================================================
// طباعة تقرير الموردين (بدون فتح نافذة جديدة — via iframe)
// =====================================================

function printSuppliers() {

    const params = new URLSearchParams();

    const searchName  = document.getElementById('searchSupplierName');
    const searchPhone = document.getElementById('searchSupplierPhone');
    const searchCode  = document.getElementById('searchSupplierCode');

    if (searchName  && searchName.value.trim())  params.set('search_name',  searchName.value.trim());
    if (searchPhone && searchPhone.value.trim()) params.set('search_phone', searchPhone.value.trim());
    if (searchCode  && searchCode.value.trim())  params.set('search_code',  searchCode.value.trim());

    const query = params.toString();

    const url = query
        ? `/setting/suppliers/print?${query}`
        : '/setting/suppliers/print';

    // =================================================
    // إنشاء iframe مخفي
    // =================================================

    const iframe = document.createElement('iframe');

    iframe.style.position   = 'fixed';
    iframe.style.right      = '0';
    iframe.style.bottom     = '0';
    iframe.style.width      = '0';
    iframe.style.height     = '0';
    iframe.style.border     = '0';
    iframe.style.visibility = 'hidden';

    document.body.appendChild(iframe);

    // =================================================
    // عند اكتمال التحميل → استدعاء الطباعة
    // =================================================

    iframe.onload = function () {

        try {

            iframe.contentWindow.focus();
            iframe.contentWindow.print();

        } catch (error) {

            console.error('خطأ الطباعة:', error);
            notify('تعذر تشغيل الطباعة', 'danger');
        }

        // إزالة الـ iframe بعد فترة كافية
        setTimeout(function () {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 2000);
    };

    // =================================================
    // تحميل الرابط داخل الـ iframe
    // =================================================

    iframe.src = url;
}

// =====================================================
// التهيئة الرئيسية
// =====================================================

function initSuppliersPage() {

    // =====================================================
    // عناصر الصفحة
    // =====================================================

    const addSupplierBtn       = document.getElementById('addSupplierBtn');
    const printSuppliersBtn    = document.getElementById('printSuppliersBtn');
    const supplierForm         = document.getElementById('supplierForm');
    const supplierModalElement = document.getElementById('supplierModal');
    const saveSupplierBtn      = document.getElementById('saveSupplierBtn');
    const supplierModalTitle   = document.getElementById('supplierModalLabel');

    let suppliersTableContainer = document.getElementById('suppliersTableContainer');
    let suppliersTbody          = document.getElementById('suppliersTableBody');

    // عناصر البحث
    const searchName  = document.getElementById('searchSupplierName');
    const searchPhone = document.getElementById('searchSupplierPhone');
    const searchCode  = document.getElementById('searchSupplierCode');

    // حقول النموذج
    const supplierID          = document.getElementById('supplierID');
    const supName             = document.getElementById('supName');
    const supPhone            = document.getElementById('supPhone');
    const supArea             = document.getElementById('supArea');
    const supStatus           = document.getElementById('supStatus');
    const supplierAccountCode = document.getElementById('supplierAccountCode');

    // نافذة الحذف
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const deleteCancelBtn    = document.getElementById('deleteCancelBtn');
    const deleteConfirmBtn   = document.getElementById('deleteConfirmBtn');

    // =====================================================
    // Modals
    // =====================================================

    let supplierModal = null;

    if (supplierModalElement && typeof bootstrap !== 'undefined') {
        supplierModal = bootstrap.Modal.getOrCreateInstance(supplierModalElement);
    }

    let formMode           = 'add';
    let editingSupplierId  = null;
    let deletingSupplierId = null;
    let searchController   = null;
    let currentPage        = 1;
    const rowsPerPage      = 10;

    let originalSupplierData = null;

    // =====================================================
    // CSRF Token
    // =====================================================

    function getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    function isEditMode() {
        return formMode === 'edit' && editingSupplierId != null;
    }

    // =====================================================
    // إدارة النموذج
    // =====================================================

    function setAddMode() {

        formMode = 'add';
        editingSupplierId = null;
        originalSupplierData = null;

        if (supplierModalTitle) supplierModalTitle.textContent = 'إضافة مورد جديد';
        if (saveSupplierBtn)    saveSupplierBtn.textContent    = 'حفظ البيانات';

        if (supplierForm) supplierForm.reset();
        if (supplierID)   supplierID.value = '';
        if (supplierAccountCode) supplierAccountCode.value = '';

        // ✅ الافتراضي عند الإضافة: نشط
        if (supStatus) supStatus.value = '1';
    }

    // =====================================================
    // تعديل المورد
    // =====================================================

    window.editSupplier = async function (id) {

        try {
            const response = await fetch(`/setting/suppliers/${id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'تعذر تحميل بيانات المورد');
            }

            const supplier = data.supplier;

            // =================================================
            // ✅ تحويل صريح وآمن للحالة (0 أو 1)
            // =================================================

            const currentIsActive =
                Number(supplier.is_active) === 1 ? '1' : '0';

            originalSupplierData = {
                supName:   supplier.supName ?? '',
                supPhone:  supplier.supPhone ?? '',
                supArea:   supplier.supArea ?? '',
                is_active: currentIsActive
            };

            formMode          = 'edit';
            editingSupplierId = supplier.suplierID;

            if (supplierModalTitle) supplierModalTitle.textContent = 'تعديل المورد';
            if (saveSupplierBtn)    saveSupplierBtn.textContent    = 'تحديث البيانات';

            if (supplierID) supplierID.value = supplier.suplierID ?? '';
            if (supName)    supName.value    = supplier.supName ?? '';
            if (supPhone)   supPhone.value   = supplier.supPhone ?? '';
            if (supArea)    supArea.value    = supplier.supArea ?? '';

            // =================================================
            // ✅ تعيين الحالة بعد كل الحقول
            // =================================================

            if (supStatus) {
                supStatus.value = currentIsActive;

                if (supStatus.value !== currentIsActive) {
                    const opt = Array.from(supStatus.options)
                        .find(o => String(o.value) === currentIsActive);

                    if (opt) supStatus.selectedIndex = opt.index;
                }
            }

            if (supplierAccountCode) {
                supplierAccountCode.value = supplier.accountCode ?? '';
            }

            if (supplierModal) supplierModal.show();

        } catch (error) {
            console.error('خطأ:', error);
            notify(error.message, 'danger');
        }
    };

    // =====================================================
    // حفظ / تحديث المورد
    // =====================================================

    if (supplierForm) {
        supplierForm.addEventListener('submit', async function (event) {

            event.preventDefault();

            if (saveSupplierBtn && saveSupplierBtn.disabled) return;

            // =================================================
            // التحقق من وجود تعديل
            // =================================================

            if (formMode === 'edit' && originalSupplierData) {

                const currentSupplierData = {
                    supName:   supName?.value.trim() ?? '',
                    supPhone:  supPhone?.value.trim() ?? '',
                    supArea:   supArea?.value.trim() ?? '',
                    is_active: String(supStatus?.value ?? '')
                };

                const hasChanges =
                    currentSupplierData.supName   !== originalSupplierData.supName ||
                    currentSupplierData.supPhone  !== originalSupplierData.supPhone ||
                    currentSupplierData.supArea   !== originalSupplierData.supArea ||
                    currentSupplierData.is_active !== originalSupplierData.is_active;

                if (!hasChanges) {
                    notify('لم يتم إجراء أي تعديل على بيانات المورد', 'danger');
                    return;
                }
            }

            if (saveSupplierBtn) saveSupplierBtn.disabled = true;

            try {
                const formData = new FormData(supplierForm);

                // لا نرسل accountID
                formData.delete('accountID');

                const isEdit = isEditMode();

                let url = '/setting/suppliers';

                if (isEdit) {
                    url = `/setting/suppliers/${editingSupplierId}`;
                    formData.set('_method', 'PUT');
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: formData
                });

                const data = await response.json();

                if (!response.ok || !data.success) {

                    let message = data.message || 'حدث خطأ أثناء الحفظ';

                    if (data.errors && typeof data.errors === 'object') {
                        const firstError = Object.values(data.errors)[0];
                        if (Array.isArray(firstError)) {
                            message = firstError[0];
                        }
                    }

                    throw new Error(message);
                }

                // عرض الرقم التحليلي (يظهر فقط عند الإضافة)
                if (supplierAccountCode && data.supplier && data.supplier.accountCode) {
                    supplierAccountCode.value = data.supplier.accountCode;
                }

                await reloadSuppliersTable();

                notify(
                    data.message || (formMode === 'edit'
                        ? 'تم التحديث بنجاح'
                        : 'تمت الإضافة بنجاح'),
                    'success'
                );

                if (supplierModal) supplierModal.hide();

            } catch (error) {
                console.error('خطأ:', error);
                notify(error.message, 'danger');

            } finally {
                if (saveSupplierBtn) saveSupplierBtn.disabled = false;
            }
        });
    }

    // =====================================================
    // إضافة مورد
    // =====================================================

    if (addSupplierBtn) {
        addSupplierBtn.addEventListener('click', function () {
            setAddMode();
            if (supplierModal) supplierModal.show();
        });
    }

    // =====================================================
    // زر طباعة تقرير الموردين
    // =====================================================

    if (printSuppliersBtn) {
        printSuppliersBtn.addEventListener('click', function (e) {
            e.preventDefault();
            printSuppliers();
        });
    } else {
        console.warn('⚠️ زر الطباعة #printSuppliersBtn غير موجود في الصفحة');
    }

    // =====================================================
    // إغلاق نافذة المورد
    // =====================================================

    if (supplierModalElement) {
        supplierModalElement.addEventListener('hidden.bs.modal', function () {
            setAddMode();
        });
    }

    // =====================================================
    // الحذف
    // =====================================================

    window.deleteSupplier = function (id) {
        deletingSupplierId = id;

        if (deleteConfirmModal) {
            deleteConfirmModal.classList.add('show');
            deleteConfirmModal.style.display = 'flex';
            document.body.classList.add('delete-confirm-open');
        }
    };

    function closeDeleteConfirm() {
        deletingSupplierId = null;

        if (deleteConfirmModal) {
            deleteConfirmModal.classList.remove('show');
            deleteConfirmModal.style.display = 'none';
        }

        document.body.classList.remove('delete-confirm-open');
    }

    if (deleteCancelBtn) {
        deleteCancelBtn.addEventListener('click', closeDeleteConfirm);
    }

    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', async function () {

            if (!deletingSupplierId) return;

            const idToDelete = deletingSupplierId;

            deleteConfirmBtn.disabled = true;

            try {
                const response = await fetch(`/setting/suppliers/${idToDelete}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message ||
                        'لا يمكن حذف المورد لوجود حركات مرتبطة به'
                    );
                }

                await reloadSuppliersTable();

                closeDeleteConfirm();

                notify(data.message || 'تم حذف المورد بنجاح', 'success');

            } catch (error) {
                closeDeleteConfirm();
                notify(error.message, 'danger');

            } finally {
                deleteConfirmBtn.disabled = false;
            }
        });
    }

    // =====================================================
    // Pagination
    // =====================================================

    function applyPagination() {

        if (!suppliersTbody) return;

        const rows = Array.from(
            suppliersTbody.querySelectorAll('tr.supplier-row')
        );

        const totalRows  = rows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const start = (currentPage - 1) * rowsPerPage;
        const end   = start + rowsPerPage;

        rows.forEach(function (row, index) {

            row.style.display = (index >= start && index < end) ? '' : 'none';

            const firstCell = row.querySelector('td:first-child');
            if (firstCell) firstCell.textContent = index + 1;
        });

        renderPagination(totalRows, totalPages, start, end);
    }

    function renderPagination(totalRows, totalPages, start, end) {

        const paginationList = document.getElementById('suppliersPaginationList');
        const paginationInfo = document.getElementById('suppliersPaginationInfo');

        if (paginationInfo) {
            if (totalRows === 0) {
                paginationInfo.textContent = 'عرض 0-0 من 0 مورد';
            } else {
                paginationInfo.textContent =
                    `عرض ${start + 1}-${Math.min(end, totalRows)} من ${totalRows} مورد`;
            }
        }

        if (!paginationList) return;

        paginationList.innerHTML = '';

        if (totalPages <= 1) return;

        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `
            <button type="button" class="page-link" data-page="${currentPage - 1}">
                السابق
            </button>`;
        paginationList.appendChild(prevLi);

        for (let page = 1; page <= totalPages; page++) {
            const li = document.createElement('li');
            li.className = `page-item ${page === currentPage ? 'active' : ''}`;
            li.innerHTML = `
                <button type="button" class="page-link" data-page="${page}">
                    ${page}
                </button>`;
            paginationList.appendChild(li);
        }

        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `
            <button type="button" class="page-link" data-page="${currentPage + 1}">
                التالي
            </button>`;
        paginationList.appendChild(nextLi);
    }

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
        searchName.addEventListener('focus', function () {
            if (searchName.value.trim() !== '') return;
            if (searchPhone) searchPhone.value = '';
            if (searchCode)  searchCode.value  = '';
            currentPage = 1;
            reloadSuppliersTable();
        });
        searchName.addEventListener('input', handleSearch);
    }

    if (searchPhone) {
        searchPhone.addEventListener('focus', function () {
            if (searchPhone.value.trim() !== '') return;
            if (searchName) searchName.value = '';
            if (searchCode) searchCode.value = '';
            currentPage = 1;
            reloadSuppliersTable();
        });
        searchPhone.addEventListener('input', handleSearch);
    }

    if (searchCode) {
        searchCode.addEventListener('focus', function () {
            if (searchCode.value.trim() !== '') return;
            if (searchName)  searchName.value  = '';
            if (searchPhone) searchPhone.value = '';
            currentPage = 1;
            reloadSuppliersTable();
        });
        searchCode.addEventListener('input', handleSearch);
    }

    // =====================================================
    // إعادة تحميل جدول الموردين
    // =====================================================

    async function reloadSuppliersTable() {

        if (searchController) searchController.abort();

        searchController = new AbortController();

        try {
            const params = new URLSearchParams();

            if (searchName && searchName.value.trim()) {
                params.set('search_name', searchName.value.trim());
            }

            if (searchPhone && searchPhone.value.trim()) {
                params.set('search_phone', searchPhone.value.trim());
            }

            if (searchCode && searchCode.value.trim()) {
                params.set('search_code', searchCode.value.trim());
            }

            const query = params.toString();
            const url = query
                ? `/setting/suppliers/list?${query}`
                : '/setting/suppliers/list';

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: searchController.signal
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'تعذر تحميل الموردين');
            }

            if (suppliersTableContainer) {
                suppliersTableContainer.innerHTML = data.html || '';

                // إعادة الاستعلام بعد استبدال المحتوى
                suppliersTableContainer = document.getElementById('suppliersTableContainer');
                suppliersTbody = document.getElementById('suppliersTableBody');
            }

            applyPagination();

        } catch (error) {

            if (error.name === 'AbortError') return;

            console.error('خطأ:', error);
            notify(error.message, 'danger');
        }
    }

    // =====================================================
    // أحداث الجدول
    // =====================================================

    if (suppliersTableContainer) {
        suppliersTableContainer.addEventListener('click', function (event) {

            // Pagination
            const paginationBtn = event.target.closest('[data-page]');
            if (paginationBtn) {
                event.preventDefault();

                const page = parseInt(paginationBtn.dataset.page, 10);
                if (!page || page < 1) return;

                currentPage = page;
                applyPagination();
                return;
            }

            // Edit
            const editBtn = event.target.closest('.edit-supplier');
            if (editBtn) {
                event.preventDefault();
                editSupplier(editBtn.dataset.id);
                return;
            }

            // Delete
            const deleteBtn = event.target.closest('.delete-supplier');
            if (deleteBtn) {
                event.preventDefault();
                deleteSupplier(deleteBtn.dataset.id);
                return;
            }
        });
    }

    // =====================================================
    // التهيئة الأولية
    // =====================================================

    if (suppliersTbody) {
        applyPagination();
    }
}

// =====================================================
//  نقطة الدخول — تعمل مع Vite type="module"
// =====================================================

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSuppliersPage);
} else {
    initSuppliersPage();
}