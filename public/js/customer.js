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
    const addCustomerBtn = document.getElementById('addCustomerBtn');
    const customerForm = document.getElementById('customerForm');
    const customerModalElement = document.getElementById('customerModal');
    const saveCustomerBtn = document.getElementById('saveCustomerBtn');
    const customerModalTitle = document.getElementById('customerModalLabel');
    const customersTbody = document.getElementById('customersTableBody');

    // عناصر البحث
    const searchName = document.getElementById('searchName');
    const searchPhone = document.getElementById('searchPhone');
    const searchCode = document.getElementById('searchCode');

    // حقول النموذج
    const customerID = document.getElementById('customerID');
    const cusName = document.getElementById('cusName');
    const cusPhone = document.getElementById('cusPhone');
    const cusAddress = document.getElementById('cusAddress');
    const cusStatus = document.getElementById('cusStatus');

    // حقول الحساب المحاسبي
    const accountDisplay = document.getElementById('accountDisplay');
    const accountIDSelect = document.getElementById('accountID');
    const accountSearchModalEl = document.getElementById('accountSearchModal');
    const accountSearchInput = document.getElementById('accountSearchInput');
    const accountSearchList = document.getElementById('accountSearchList');

    // نافذة الحذف
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const deleteCancelBtn = document.getElementById('deleteCancelBtn');
    const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');

    // Modals
    let customerModal = null;
    if (customerModalElement) {
        customerModal = bootstrap.Modal.getOrCreateInstance(customerModalElement);
    }
    let accountSearchModal = null;
    if (accountSearchModalEl) {
        accountSearchModal = bootstrap.Modal.getOrCreateInstance(accountSearchModalEl);
    }

    let formMode = 'add';
    let editingCustomerId = null;
    let deletingCustomerId = null;

    let currentPage = 1;
    const rowsPerPage = 10;

    function getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    // =====================================================
    // اختيار الحساب المحاسبي من المودال
    // =====================================================
    
    if (accountDisplay) {
        accountDisplay.addEventListener('click', function () {
            if (accountSearchModal) {
                if (accountSearchInput) accountSearchInput.value = '';
                filterAccountList('');
                accountSearchModal.show();
                setTimeout(() => accountSearchInput?.focus(), 500);
            }
        });
    }

    function filterAccountList(searchTerm) {
        if (!accountSearchList) return;
        const term = searchTerm.toLowerCase();
        const items = accountSearchList.querySelectorAll('.account-search-item');
        
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(term)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    if (accountSearchInput) {
        accountSearchInput.addEventListener('input', function (e) {
            filterAccountList(e.target.value);
        });
    }

    if (accountSearchList) {
        accountSearchList.addEventListener('click', function (e) {
            const item = e.target.closest('.account-search-item');
            if (item) {
                const id = item.dataset.id;
                const code = item.dataset.code;
                const name = item.dataset.name;

                if (accountIDSelect) {
                    accountIDSelect.value = id;
                }
                if (accountDisplay) {
                    accountDisplay.value = `${code} - ${name}`;
                }

                if (accountSearchModal) accountSearchModal.hide();
            }
        });
    }

    // =====================================================
    // إدارة النموذج (إضافة / تعديل)
    // =====================================================

    function setAddMode() {
        formMode = 'add';
        editingCustomerId = null;
        if (customerModalTitle) customerModalTitle.textContent = 'إضافة عميل جديد';
        if (saveCustomerBtn) saveCustomerBtn.textContent = 'حفظ البيانات';
        
        customerForm.reset();
        if (customerID) customerID.value = '';
        if (accountIDSelect) accountIDSelect.value = '';
        if (accountDisplay) accountDisplay.value = '';
        setFormMethod('POST');
    }

    window.editCustomer = async function (id) {
        try {
            // المسار الجديد: /setting/customers/{id}
            const response = await fetch(`/setting/customers/${id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();

            if (!response.ok || !data.success) throw new Error(data.message || 'تعذر تحميل بيانات العميل');

            const customer = data.customer;
            formMode = 'edit';
            editingCustomerId = customer.CustomersID;

            if (customerModalTitle) customerModalTitle.textContent = 'تعديل العميل';
            if (saveCustomerBtn) saveCustomerBtn.textContent = 'تحديث البيانات';

            if (customerID) customerID.value = customer.CustomersID ?? '';
            if (cusName) cusName.value = customer.CustomersName2 ?? '';
            if (cusPhone) cusPhone.value = customer.CusPhone ?? '';
            if (cusAddress) cusAddress.value = customer.CusAddress ?? '';
            
            // قراءة وتحديد حالة العميل
            const statusVal = customer.CusIsStoopeed !== undefined ? customer.CusIsStoopeed : (customer.CusIsStopped ?? 0);
            if (cusStatus) cusStatus.value = String(statusVal);
            
            // تعيين الحساب المحاسبي
            if (accountIDSelect) accountIDSelect.value = customer.accountID ?? '';
            if (accountDisplay && customer.account) {
                accountDisplay.value = `${customer.account.accCode} - ${customer.account.accName}`;
            } else if (accountDisplay) {
                accountDisplay.value = '';
            }

            setFormMethod('PUT');
            if (customerModal) customerModal.show();

        } catch (error) {
            console.error('خطأ:', error);
            if (typeof showSystemToast === 'function') showSystemToast(error.message, 'danger');
        }
    };

    function setFormMethod(method) {
        let methodInput = customerForm.querySelector('input[name="_method"]');
        if (method.toUpperCase() === 'POST') {
            if (methodInput) methodInput.remove();
            customerForm.method = 'POST';
            return;
        }
        if (!methodInput) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            customerForm.appendChild(methodInput);
        }
        methodInput.value = method.toUpperCase();
        customerForm.method = 'POST';
    }

    customerForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (saveCustomerBtn && saveCustomerBtn.disabled) return;
        if (saveCustomerBtn) saveCustomerBtn.disabled = true;

        try {
            const formData = new FormData(customerForm);
            
            // المسارات الجديدة: /setting/customers
            let url = '/setting/customers';

            if (formMode === 'edit' && editingCustomerId) {
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

            if (typeof showSystemToast === 'function') {
                showSystemToast(data.message || (formMode === 'edit' ? 'تم التحديث بنجاح' : 'تمت الإضافة بنجاح'), 'success');
            }

            if (customerModal) customerModal.hide();
            await reloadCustomersTable();
            setAddMode();

        } catch (error) {
            console.error('خطأ:', error);
            if (typeof showSystemToast === 'function') showSystemToast(error.message, 'danger');
        } finally {
            if (saveCustomerBtn) saveCustomerBtn.disabled = false;
        }
    });

    if (addCustomerBtn) {
        addCustomerBtn.addEventListener('click', function () {
            setAddMode();
            if (customerModal) customerModal.show();
        });
    }

    if (customerModalElement) {
        customerModalElement.addEventListener('hidden.bs.modal', function () {
            setAddMode();
        });
    }

    // =====================================================
    // الحذف
    // =====================================================

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

    if (deleteCancelBtn) deleteCancelBtn.addEventListener('click', closeDeleteConfirm);

    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', async function () {
            if (!deletingCustomerId) return;
            deleteConfirmBtn.disabled = true;

            try {
                // المسار الجديد للحذف: /setting/customers/{id}
                const response = await fetch(`/setting/customers/${deletingCustomerId}`, {
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

                closeDeleteConfirm();
                if (typeof showSystemToast === 'function') showSystemToast(data.message || 'تم حذف العميل بنجاح', 'success');
                await reloadCustomersTable();

            } catch (error) {
                closeDeleteConfirm();
                if (typeof showSystemToast === 'function') showSystemToast(error.message, 'danger');
            } finally {
                deleteConfirmBtn.disabled = false;
            }
        });
    }

    // =====================================================
    // Pagination (ترقيم الصفحات)
    // =====================================================

    function applyPagination() {
        const rows = Array.from(customersTbody.querySelectorAll('tr.customer-row'));
        const totalRows = rows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));

        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

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
                paginationInfo.textContent = `عرض ${start + 1}-${Math.min(end, totalRows)} من ${totalRows} عميل`;
            }
        }

        if (!paginationList) return;
        paginationList.innerHTML = '';
        if (totalPages <= 1) return;

        // السابق
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<button type="button" class="page-link" data-page="${currentPage - 1}">السابق</button>`;
        paginationList.appendChild(prevLi);

        // الأرقام
        for (let page = 1; page <= totalPages; page++) {
            const li = document.createElement('li');
            li.className = `page-item ${page === currentPage ? 'active' : ''}`;
            li.innerHTML = `<button type="button" class="page-link" data-page="${page}">${page}</button>`;
            paginationList.appendChild(li);
        }

        // التالي
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<button type="button" class="page-link" data-page="${currentPage + 1}">التالي</button>`;
        paginationList.appendChild(nextLi);
    }

    const paginationList = document.getElementById('customersPaginationList');
    if (paginationList) {
        paginationList.addEventListener('click', function (event) {
            const button = event.target.closest('[data-page]');
            if (!button) return;
            const page = parseInt(button.dataset.page, 10);
            if (!page || page < 1) return;
            currentPage = page;
            applyPagination();
        });
    }

    // =====================================================
    // البحث وجلب البيانات
    // =====================================================

    let searchTimer = null;
    function handleSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentPage = 1;
            reloadCustomersTable();
        }, 350);
    }

    if (searchName) searchName.addEventListener('input', handleSearch);
    if (searchPhone) searchPhone.addEventListener('input', handleSearch);
    if (searchCode) searchCode.addEventListener('input', handleSearch);

    async function reloadCustomersTable() {
        try {
            const params = new URLSearchParams();
            if (searchName && searchName.value.trim()) params.set('search_name', searchName.value.trim());
            if (searchPhone && searchPhone.value.trim()) params.set('search_phone', searchPhone.value.trim());
            if (searchCode && searchCode.value.trim()) params.set('search_code', searchCode.value.trim());

            const query = params.toString();
            
            // المسار الجديد للقائمة: /setting/customers/list
            const url = query ? `/setting/customers/list?${query}` : '/setting/customers/list';

            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();

            if (!response.ok || !data.success) throw new Error(data.message || 'تعذر تحميل العملاء');

            customersTbody.innerHTML = data.html || '';
            currentPage = 1;
            applyPagination();

        } catch (error) {
            console.error('خطأ:', error);
            if (typeof showSystemToast === 'function') showSystemToast(error.message, 'danger');
        }
    }

    // =====================================================
    // أحداث الجدول (تعديل / حذف)
    // =====================================================
    if (customersTbody) {
        customersTbody.addEventListener('click', function (event) {
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

    // التهيئة الأولية
    if (customersTbody) {
        applyPagination();
    }
});