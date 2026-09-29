/**
 * =========================================================
 * customers.js
 * إدارة العملاء
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
// طباعة تقرير العملاء (بدون فتح نافذة جديدة — via iframe)
// =====================================================

function printCustomers() {

    const params = new URLSearchParams();

    const searchName  = document.getElementById('searchName');
    const searchPhone = document.getElementById('searchPhone');
    const searchCode  = document.getElementById('searchCode');

    if (searchName  && searchName.value.trim())  params.set('search_name',  searchName.value.trim());
    if (searchPhone && searchPhone.value.trim()) params.set('search_phone', searchPhone.value.trim());
    if (searchCode  && searchCode.value.trim())  params.set('search_code',  searchCode.value.trim());

    const query = params.toString();

    const url = query
        ? `/setting/customers/print?${query}`
        : '/setting/customers/print';

    // =================================================
    // إنشاء iframe مخفي
    // =================================================

    const iframe = document.createElement('iframe');

    iframe.style.position     = 'fixed';
    iframe.style.right        = '0';
    iframe.style.bottom       = '0';
    iframe.style.width        = '0';
    iframe.style.height       = '0';
    iframe.style.border       = '0';
    iframe.style.visibility   = 'hidden';

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

function initCustomersPage() {

    // =====================================================
    // عناصر الصفحة
    // =====================================================

    const addCustomerBtn       = document.getElementById('addCustomerBtn');
    const printCustomersBtn    = document.getElementById('printCustomersBtn');
    const customerForm         = document.getElementById('customerForm');
    const customerModalElement = document.getElementById('customerModal');
    const saveCustomerBtn      = document.getElementById('saveCustomerBtn');
    const customerModalTitle   = document.getElementById('customerModalLabel');

    let customersTableContainer = document.getElementById('customersTableContainer');
    let customersTbody          = document.getElementById('customersTableBody');

    const searchName  = document.getElementById('searchName');
    const searchPhone = document.getElementById('searchPhone');
    const searchCode  = document.getElementById('searchCode');

    const customerID          = document.getElementById('customerID');
    const cusName             = document.getElementById('cusName');
    const cusPhone            = document.getElementById('cusPhone');
    const cusAddress          = document.getElementById('cusAddress');
    const cusStatus           = document.getElementById('cusStatus');
    const customerAccountCode = document.getElementById('customerAccountCode');

    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const deleteCancelBtn    = document.getElementById('deleteCancelBtn');
    const deleteConfirmBtn   = document.getElementById('deleteConfirmBtn');

    let customerModal = null;

    if (customerModalElement && typeof bootstrap !== 'undefined') {
        customerModal = bootstrap.Modal.getOrCreateInstance(customerModalElement);
    }

    let formMode            = 'add';
    let editingCustomerId   = null;
    let deletingCustomerId  = null;
    let searchController    = null;
    let currentPage         = 1;
    const rowsPerPage       = 10;

    let originalCustomerData = null;

    function getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    function isEditMode() {
        return formMode === 'edit' && editingCustomerId != null;
    }

    function setAddMode() {
        formMode = 'add';
        editingCustomerId = null;
        originalCustomerData = null;

        if (customerModalTitle) customerModalTitle.textContent = 'إضافة عميل جديد';
        if (saveCustomerBtn)   saveCustomerBtn.textContent = 'حفظ البيانات';
        if (customerForm)      customerForm.reset();
        if (customerID)        customerID.value = '';
        if (customerAccountCode) customerAccountCode.value = '';
        if (cusStatus)         cusStatus.value = '1';
    }

    window.editCustomer = async function (id) {
        try {
            const response = await fetch(`/setting/customers/${id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'تعذر تحميل بيانات العميل');
            }

            const customer = data.customer;
            const currentIsActive = Number(customer.is_active) === 1 ? '1' : '0';

            originalCustomerData = {
                CustomersName2: customer.CustomersName2 ?? '',
                CusPhone:       customer.CusPhone ?? '',
                CusAddress:     customer.CusAddress ?? '',
                is_active:      currentIsActive
            };

            formMode          = 'edit';
            editingCustomerId = customer.CustomersID;

            if (customerModalTitle) customerModalTitle.textContent = 'تعديل العميل';
            if (saveCustomerBtn)    saveCustomerBtn.textContent = 'تحديث البيانات';
            if (customerID)         customerID.value = customer.CustomersID ?? '';
            if (cusName)            cusName.value = customer.CustomersName2 ?? '';
            if (cusPhone)           cusPhone.value = customer.CusPhone ?? '';
            if (cusAddress)         cusAddress.value = customer.CusAddress ?? '';

            if (cusStatus) {
                cusStatus.value = currentIsActive;
                if (cusStatus.value !== currentIsActive) {
                    const opt = Array.from(cusStatus.options)
                        .find(o => String(o.value) === currentIsActive);
                    if (opt) cusStatus.selectedIndex = opt.index;
                }
            }

            if (customerAccountCode) customerAccountCode.value = customer.accountCode ?? '';

            if (customerModal) customerModal.show();

        } catch (error) {
            console.error('خطأ:', error);
            notify(error.message, 'danger');
        }
    };

    if (customerForm) {
        customerForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (saveCustomerBtn && saveCustomerBtn.disabled) return;

            if (formMode === 'edit' && originalCustomerData) {
                const currentCustomerData = {
                    CustomersName2: cusName?.value.trim() ?? '',
                    CusPhone:       cusPhone?.value.trim() ?? '',
                    CusAddress:     cusAddress?.value.trim() ?? '',
                    is_active:      String(cusStatus?.value ?? '')
                };

                const hasChanges =
                    currentCustomerData.CustomersName2 !== originalCustomerData.CustomersName2 ||
                    currentCustomerData.CusPhone       !== originalCustomerData.CusPhone ||
                    currentCustomerData.CusAddress     !== originalCustomerData.CusAddress ||
                    currentCustomerData.is_active      !== originalCustomerData.is_active;

                if (!hasChanges) {
                    notify('لم يتم إجراء أي تعديل على بيانات العميل', 'danger');
                    return;
                }
            }

            if (saveCustomerBtn) saveCustomerBtn.disabled = true;

            try {
                const formData = new FormData(customerForm);
                formData.delete('accountID');

                const isEdit = isEditMode();
                let url = '/setting/customers';

                if (isEdit) {
                    url = `/setting/customers/${editingCustomerId}`;
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
                        if (Array.isArray(firstError)) message = firstError[0];
                    }
                    throw new Error(message);
                }

                if (customerAccountCode && data.customer && data.customer.accountCode) {
                    customerAccountCode.value = data.customer.accountCode;
                }

                await reloadCustomersTable();

                notify(
                    data.message || (formMode === 'edit' ? 'تم التحديث بنجاح' : 'تمت الإضافة بنجاح'),
                    'success'
                );

                if (customerModal) customerModal.hide();

            } catch (error) {
                console.error('خطأ:', error);
                notify(error.message, 'danger');
            } finally {
                if (saveCustomerBtn) saveCustomerBtn.disabled = false;
            }
        });
    }

    if (addCustomerBtn) {
        addCustomerBtn.addEventListener('click', function () {
            setAddMode();
            if (customerModal) customerModal.show();
        });
    }

    // =====================================================
    // زر طباعة تقرير العملاء
    // =====================================================

    if (printCustomersBtn) {
        printCustomersBtn.addEventListener('click', function (e) {
            e.preventDefault();
            printCustomers();
        });
    } else {
        console.warn('⚠️ زر الطباعة #printCustomersBtn غير موجود في الصفحة');
    }

    if (customerModalElement) {
        customerModalElement.addEventListener('hidden.bs.modal', function () {
            setAddMode();
        });
    }

    window.deleteCustomer = function (id) {
        deletingCustomerId = id;

        if (deleteConfirmModal) {
            deleteConfirmModal.classList.add('show');
            deleteConfirmModal.style.display = 'flex';
            document.body.classList.add('delete-confirm-open');
        }
    };

    function closeDeleteConfirm() {
        deletingCustomerId = null;

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
            if (!deletingCustomerId) return;

            const idToDelete = deletingCustomerId;
            deleteConfirmBtn.disabled = true;

            try {
                const response = await fetch(`/setting/customers/${idToDelete}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'لا يمكن حذف العميل لوجود حركات مرتبطة به');
                }

                await reloadCustomersTable();
                closeDeleteConfirm();
                notify(data.message || 'تم حذف العميل بنجاح', 'success');

            } catch (error) {
                closeDeleteConfirm();
                notify(error.message, 'danger');
            } finally {
                deleteConfirmBtn.disabled = false;
            }
        });
    }

    function applyPagination() {
        if (!customersTbody) return;

        const rows = Array.from(customersTbody.querySelectorAll('tr.customer-row'));
        const totalRows = rows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));

        if (currentPage > totalPages) currentPage = totalPages;

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
        const paginationList = document.getElementById('customersPaginationList');
        const paginationInfo = document.getElementById('customersPaginationInfo');

        if (paginationInfo) {
            if (totalRows === 0) {
                paginationInfo.textContent = 'عرض 0-0 من 0 عميل';
            } else {
                paginationInfo.textContent =
                    `عرض ${start + 1}-${Math.min(end, totalRows)} من ${totalRows} عميل`;
            }
        }

        if (!paginationList) return;
        paginationList.innerHTML = '';

        if (totalPages <= 1) return;

        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<button type="button" class="page-link" data-page="${currentPage - 1}">السابق</button>`;
        paginationList.appendChild(prevLi);

        for (let page = 1; page <= totalPages; page++) {
            const li = document.createElement('li');
            li.className = `page-item ${page === currentPage ? 'active' : ''}`;
            li.innerHTML = `<button type="button" class="page-link" data-page="${page}">${page}</button>`;
            paginationList.appendChild(li);
        }

        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<button type="button" class="page-link" data-page="${currentPage + 1}">التالي</button>`;
        paginationList.appendChild(nextLi);
    }

    let searchTimer = null;

    function handleSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentPage = 1;
            reloadCustomersTable();
        }, 300);
    }

    if (searchName) {
        searchName.addEventListener('focus', function () {
            if (searchName.value.trim() !== '') return;
            if (searchPhone) searchPhone.value = '';
            if (searchCode)  searchCode.value  = '';
            currentPage = 1;
            reloadCustomersTable();
        });
        searchName.addEventListener('input', handleSearch);
    }

    if (searchPhone) {
        searchPhone.addEventListener('focus', function () {
            if (searchPhone.value.trim() !== '') return;
            if (searchName) searchName.value = '';
            if (searchCode) searchCode.value = '';
            currentPage = 1;
            reloadCustomersTable();
        });
        searchPhone.addEventListener('input', handleSearch);
    }

    if (searchCode) {
        searchCode.addEventListener('focus', function () {
            if (searchCode.value.trim() !== '') return;
            if (searchName)  searchName.value  = '';
            if (searchPhone) searchPhone.value = '';
            currentPage = 1;
            reloadCustomersTable();
        });
        searchCode.addEventListener('input', handleSearch);
    }

    async function reloadCustomersTable() {
        if (searchController) searchController.abort();
        searchController = new AbortController();

        try {
            const params = new URLSearchParams();

            if (searchName && searchName.value.trim()) params.set('search_name', searchName.value.trim());
            if (searchPhone && searchPhone.value.trim()) params.set('search_phone', searchPhone.value.trim());
            if (searchCode && searchCode.value.trim()) params.set('search_code', searchCode.value.trim());

            const query = params.toString();
            const url = query
                ? `/setting/customers/list?${query}`
                : '/setting/customers/list';

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: searchController.signal
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'تعذر تحميل العملاء');
            }

            if (customersTableContainer) {
                customersTableContainer.innerHTML = data.html || '';
                customersTableContainer = document.getElementById('customersTableContainer');
                customersTbody = document.getElementById('customersTableBody');
            }

            applyPagination();

        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('خطأ:', error);
            notify(error.message, 'danger');
        }
    }

    if (customersTableContainer) {
        customersTableContainer.addEventListener('click', function (event) {
            const paginationBtn = event.target.closest('[data-page]');
            if (paginationBtn) {
                event.preventDefault();
                const page = parseInt(paginationBtn.dataset.page, 10);
                if (!page || page < 1) return;
                currentPage = page;
                applyPagination();
                return;
            }

            const editBtn = event.target.closest('.edit-customer');
            if (editBtn) {
                event.preventDefault();
                editCustomer(editBtn.dataset.id);
                return;
            }

            const deleteBtn = event.target.closest('.delete-customer');
            if (deleteBtn) {
                event.preventDefault();
                deleteCustomer(deleteBtn.dataset.id);
                return;
            }
        });
    }

    if (customersTbody) {
        applyPagination();
    }
}

// =====================================================
// ✅ نقطة الدخول — تعمل مع Vite type="module"
// =====================================================

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCustomersPage);
} else {
    initCustomersPage();
}