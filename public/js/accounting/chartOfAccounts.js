/**
 * chartOfAccounts.js
 * إدارة دليل الحسابات
 *
 * المعتمد:
 * - الشجرة تعرض الحسابات التجميعية فقط.
 * - الحسابات التحليلية لا يتم تحميلها داخل الشجرة.
 * - الحساب النظامي لا تظهر له أزرار التعديل والحذف.
 * - الحذف يعتمد على Backend في جميع التحققات.
 * - التعديل بدون تغيير يعرض رسالة تحقق ولا يرسل Request.
 * - البحث + Pagination + فتح/إغلاق الشجرة.
 */

document.addEventListener('DOMContentLoaded', function () {

    // =====================================================
    // العناصر
    // =====================================================

    const addAccountBtn = document.getElementById('addAccountBtn');

    const accountForm = document.getElementById('accountForm');

    const accountModalElement =
        document.getElementById('addAccountModal');

    const saveAccountBtn =
        document.getElementById('saveAccountBtn');

    const accountModalTitle =
        document.getElementById('accountModalTitle');

    const accountsTreeBody =
        document.getElementById('accountsTreeBody');

    const searchCode =
        document.getElementById('searchCode');

    const searchName =
        document.getElementById('searchName');

    const searchNature =
        document.getElementById('searchNature');

    // الحقول
    const accountID =
        document.getElementById('accountID');

    const accTypeID =
        document.getElementById('accTypeID');

    const accCode =
        document.getElementById('accCode');

    const accParent =
        document.getElementById('accParent');

    const accName =
        document.getElementById('accName');

    const nature =
        document.getElementById('nature');

    const accLevel =
        document.getElementById('accLevel');

    const IsActive =
        document.getElementById('IsActive');

    const isPostable =
        document.getElementById('isPostable');

    const accParentDisplay =
        document.getElementById('accParentDisplay');

            // =====================================================
    // الحسابات التحليلية
    // =====================================================

    const analyticalAccountsModalElement =
    document.getElementById('analyticalAccountsModal');

let analyticalAccountsModal = null;

if (
    analyticalAccountsModalElement &&
    typeof bootstrap !== 'undefined'
) {
    analyticalAccountsModal =
        bootstrap.Modal.getOrCreateInstance(
            analyticalAccountsModalElement
        );
}

    const analyticalAccountsParent =
        document.getElementById('analyticalAccountsParent');

    const analyticalAccountsBody =
        document.getElementById('analyticalAccountsBody');

    const analyticalSearchCode =
        document.getElementById('analyticalSearchCode');

    const analyticalSearchName =
        document.getElementById('analyticalSearchName');

    const analyticalSearchNature =
        document.getElementById('analyticalSearchNature');

    const analyticalPaginationInfo =
        document.getElementById('analyticalPaginationInfo');

    const analyticalPaginationList =
        document.getElementById('analyticalPaginationList');

    

    let selectedAnalyticalParentId = null;

    let analyticalCurrentPage = 1;

    const analyticalPageSize = 10;

    let analyticalSearchTimer = null;


    // =====================================================
    // الحذف
    // =====================================================

    const deleteConfirmModal =
        document.getElementById('deleteConfirmModal');

    const deleteConfirmBtn =
        document.getElementById('deleteConfirmBtn');


    // =====================================================
    // الحساب الأب
    // =====================================================

    const parentAccountSearchModal =
        document.getElementById(
            'parentAccountSearchModal'
        );

    const parentAccountSearchInput =
        document.getElementById(
            'parentAccountSearchInput'
        );

    const parentAccountSearchList =
        document.getElementById(
            'parentAccountSearchList'
        );


    // =====================================================
    // الحالة
    // =====================================================

    let formMode = 'add';

    let editingAccountId = null;

    let deletingAccountId = null;

    let accounts = [];

    let currentPage = 1;

    const pageSize = 10;

    let searchTimer = null;

    let savedEditData = null;


    // =====================================================
    // Bootstrap Modal
    // =====================================================

    let accountModal = null;

    if (
        accountModalElement &&
        typeof bootstrap !== 'undefined'
    ) {
        accountModal =
            bootstrap.Modal.getOrCreateInstance(
                accountModalElement
            );
    }


    // =====================================================
    // CSRF
    // =====================================================

    function getCsrfToken() {

        const meta =
            document.querySelector(
                'meta[name="csrf-token"]'
            );

        return meta ? meta.getAttribute('content') : '';

    }


    // =====================================================
    // الرسائل
    // =====================================================

    function showToast(message, type = 'success') {

        if (typeof showSystemToast === 'function') {

            showSystemToast(
                message,
                type
            );

            return;
        }

        alert(message);

    }


    // =====================================================
    // استخراج رسالة Backend
    // =====================================================

    function getResponseMessage(
        data,
        fallback = 'حدث خطأ غير متوقع'
    ) {

        if (
            data &&
            data.errors
        ) {

            const firstError =
                Object.values(data.errors)[0];

            if (
                Array.isArray(firstError) &&
                firstError.length
            ) {
                return firstError[0];
            }

            if (typeof firstError === 'string') {
                return firstError;
            }
        }

        if (
            data &&
            data.message
        ) {
            return data.message;
        }

        return fallback;
    }


    // =====================================================
    // إعداد Headers
    // =====================================================

    function jsonHeaders() {

        return {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
        };

    }


    // =====================================================
    // تحديث مستوى الحساب
    // =====================================================

    function updateAccountLevel() {

        if (!accLevel) {
            return;
        }

        if (!accParent || !accParent.value) {

            accLevel.value = 1;

            return;
        }

        const option =
            accParent.options[
                accParent.selectedIndex
            ];

        if (!option) {

            accLevel.value = 1;

            return;
        }

        const parentLevel =
            Number(
                option.dataset.level || 1
            );

        accLevel.value =
            parentLevel + 1;

    }


    // =====================================================
    // تحديد نوع الحساب تلقائياً
    // =====================================================

    function updatePostableState() {

        if (!isPostable) {
            return;
        }

        isPostable.value =
            accParent && accParent.value
                ? '1'
                : '0';

    }


    // =====================================================
    // جلب رقم الحساب التالي
    // =====================================================

    async function loadNextAccountCode(parentId) {

        if (!accCode) {
            return;
        }

        if (!parentId) {

            accCode.value = '';

            return;
        }

        try {

            const response =
                await fetch(
                    `/settings/accounting/chartOfAccounts/next-code/${parentId}`,
                    {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

            const data =
                await response.json();

            if (!response.ok || !data.success) {

                throw new Error(
                    getResponseMessage(
                        data,
                        'تعذر توليد رقم الحساب'
                    )
                );
            }

            accCode.value =
                data.code || '';

        } catch (error) {

            showToast(
                error.message,
                'danger'
            );

        }

    }


    // =====================================================
    // تغيير الحساب الأب
    // =====================================================

    if (accParent) {

        accParent.addEventListener(
            'change',
            async function () {

                updateAccountLevel();

                updatePostableState();

                syncParentDisplay();

                if (
                    formMode === 'add' &&
                    accParent.value
                ) {

                    await loadNextAccountCode(
                        accParent.value
                    );
                }

            }
        );

    }


    // =====================================================
    // تحميل الحسابات التجميعية لاختيار الحساب الأب
    // =====================================================

    async function loadParentAccounts() {

        if (!parentAccountSearchList || !accParent) {
            return;
        }

        parentAccountSearchList.innerHTML = `
            <li
                class="list-group-item text-center text-muted py-4"
            >
                جاري تحميل الحسابات...
            </li>
        `;

        try {

            const response =
                await fetch(
                    '/settings/accounting/chartOfAccounts/tree',
                    {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
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
                    getResponseMessage(
                        data,
                        'تعذر تحميل الحسابات الأب'
                    )
                );

            }

            const parentAccounts =
                Array.isArray(data.accounts)
                    ? data.accounts
                    : [];


            // ---------------------------------------------
            // إعادة بناء select المخفي
            // ---------------------------------------------

            accParent.innerHTML = `
                <option value="">
                    لا يوجد (حساب رئيسي)
                </option>
            `;


            // ---------------------------------------------
            // إعادة بناء قائمة البحث
            // ---------------------------------------------

            parentAccountSearchList.innerHTML = `
                <li
                    class="list-group-item list-group-item-action parent-search-item"
                    data-id=""
                    data-code=""
                    data-name="لا يوجد (حساب رئيسي)"
                    data-level="0"
                    style="cursor: pointer;"
                >
                    <strong class="text-muted">
                        لا يوجد (حساب رئيسي)
                    </strong>
                </li>
            `;


            parentAccounts.forEach(
                function (parent) {

                    const option =
                        document.createElement('option');

                    option.value =
                        parent.accountID;

                    option.dataset.code =
                        parent.accCode ?? '';

                    option.dataset.type =
                        parent.accTypeID ?? '';

                    option.dataset.nature =
                        parent.nature ?? '';

                    option.dataset.level =
                        parent.accLevel ?? 1;

                    option.dataset.postable =
                        parent.isPostable ?? 0;

                    option.dataset.name =
                        parent.accName ?? '';

                    option.textContent =
                        `${parent.accCode ?? ''} - ${parent.accName ?? ''}`;

                    accParent.appendChild(
                        option
                    );


                    const item =
                        document.createElement('li');

                    item.className =
                        'list-group-item list-group-item-action parent-search-item';

                    item.dataset.id =
                        parent.accountID;

                    item.dataset.code =
                        parent.accCode ?? '';

                    item.dataset.name =
                        parent.accName ?? '';

                    item.dataset.level =
                        parent.accLevel ?? 1;

                    item.style.cursor =
                        'pointer';

                    item.innerHTML = `
                        <span class="badge bg-secondary me-2">
                            ${escapeHtml(parent.accCode ?? '')}
                        </span>
                        ${escapeHtml(parent.accName ?? '')}
                    `;

                    parentAccountSearchList.appendChild(
                        item
                    );

                }
            );


            // ---------------------------------------------
            // إعادة تحديد الحساب الحالي في وضع التعديل
            // ---------------------------------------------

            if (
                formMode === 'edit' &&
                editingAccountId
            ) {

                disableInvalidParentOptions();

            }

        } catch (error) {

            parentAccountSearchList.innerHTML = `
                <li
                    class="list-group-item text-center text-danger py-4"
                >
                    ${escapeHtml(
                        error.message ||
                        'تعذر تحميل الحسابات الأب'
                    )}
                </li>
            `;

        }

    }


    // =====================================================
    // وضع الإضافة
    // =====================================================

    function setAddMode() {

        formMode = 'add';

        editingAccountId = null;

        savedEditData = null;

        if (accountForm) {
            accountForm.reset();
        }

        if (accountID) {
            accountID.value = '';
        }

        if (accountModalTitle) {
            accountModalTitle.textContent =
                'إضافة حساب';
        }

        if (saveAccountBtn) {
            saveAccountBtn.textContent =
                'حفظ';
        }

        if (accLevel) {
            accLevel.value = 1;
        }

        if (IsActive) {
            IsActive.value = '1';
        }

        if (accParent) {
            accParent.value = '';
        }

        if (accParentDisplay) {
            accParentDisplay.value = '';
        }

        if (isPostable) {
            isPostable.value = '0';
        }

        enableAllParentOptions();

    }


    // =====================================================
    // فتح نافذة الإضافة
    // =====================================================

    if (addAccountBtn) {

        addAccountBtn.addEventListener(
            'click',
            async function () {

                setAddMode();

                await loadParentAccounts();

                if (accountModal) {
                    accountModal.show();
                }

            }
        );

    }


    // =====================================================
    // تعطيل الحساب الحالي كأب
    // =====================================================

    function disableCurrentAccountAsParent() {

        enableAllParentOptions();

        if (!accParent || !editingAccountId) {
            return;
        }

        Array.from(
            accParent.options
        ).forEach(function (option) {

            if (
                String(option.value) ===
                String(editingAccountId)
            ) {

                option.disabled = true;

            }

        });

    }


    // =====================================================
    // تعطيل الحساب الحالي وأبنائه
    // =====================================================

    function disableInvalidParentOptions() {

        disableCurrentAccountAsParent();

        if (
            !accParent ||
            !editingAccountId ||
            !accounts.length
        ) {
            return;
        }

        const invalidIds = new Set([
            Number(editingAccountId)
        ]);

        let changed = true;

        while (changed) {

            changed = false;

            accounts.forEach(function (account) {

                const id =
                    Number(account.accountID);

                const parentId =
                    Number(account.accParent);

                if (
                    invalidIds.has(parentId) &&
                    !invalidIds.has(id)
                ) {

                    invalidIds.add(id);

                    changed = true;

                }

            });

        }

        Array.from(
            accParent.options
        ).forEach(function (option) {

            const optionId =
                Number(option.value);

            if (
                invalidIds.has(optionId)
            ) {

                option.disabled = true;

            }

        });

    }


    // =====================================================
    // إعادة تفعيل خيارات الأب
    // =====================================================

    function enableAllParentOptions() {

        if (!accParent) {
            return;
        }

        Array.from(
            accParent.options
        ).forEach(function (option) {

            option.disabled = false;

        });

    }




    // =====================================================
    // تنظيف قيمة
    // =====================================================

    function normalizeValue(value) {

        if (
            value === null ||
            value === undefined
        ) {
            return '';
        }

        return String(value).trim();

    }


    // =====================================================
    // بيانات النموذج الحالية
    // =====================================================

    function getFormDataForComparison() {

        return {

            accTypeID:
                normalizeValue(
                    accTypeID?.value
                ),

            accCode:
                normalizeValue(
                    accCode?.value
                ),

            accParent:
                normalizeValue(
                    accParent?.value
                ),

            accName:
                normalizeValue(
                    accName?.value
                ),

            nature:
                normalizeValue(
                    nature?.value
                ),

            accLevel:
                normalizeValue(
                    accLevel?.value
                ),

            IsActive:
                normalizeValue(
                    IsActive?.value
                ),

            isPostable:
                normalizeValue(
                    isPostable?.value
                )

        };

    }


    // =====================================================
    // مقارنة البيانات
    // =====================================================

    function hasFormChanges() {

        if (!savedEditData) {
            return true;
        }

        const currentData =
            getFormDataForComparison();

        const fields =
            Object.keys(savedEditData);

        return fields.some(function (field) {

            return normalizeValue(
                savedEditData[field]
            ) !== normalizeValue(
                currentData[field]
            );

        });

    }


    // =====================================================
    // جلب الحساب للتعديل
    // =====================================================

    async function editAccount(id) {

        try {

            const response =
                await fetch(
                    `/settings/accounting/chartOfAccounts/${id}`,
                    {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

            const data =
                await response.json();

            if (!response.ok || !data.success) {

                throw new Error(
                    getResponseMessage(
                        data,
                        'تعذر جلب بيانات الحساب'
                    )
                );

            }

            const account =
                data.account;

            formMode = 'edit';

            editingAccountId =
                account.accountID;

            if (accountID) {
                accountID.value =
                    account.accountID ?? '';
            }

            if (accTypeID) {
                accTypeID.value =
                    account.accTypeID ?? '';
            }

            if (accCode) {
                accCode.value =
                    account.accCode ?? '';
            }

            if (accParent) {
                accParent.value =
                    account.accParent ?? '';
            }

            if (accName) {
                accName.value =
                    account.accName ?? '';
            }

            if (nature) {
                nature.value =
                    account.nature ?? '';
            }

            if (accLevel) {
                accLevel.value =
                    account.accLevel ?? 1;
            }

            if (IsActive) {
                IsActive.value =
                    account.IsActive ?? 1;
            }

            if (isPostable) {
                isPostable.value =
                    account.isPostable ?? 0;
            }

            await loadParentAccounts();

            if (accParent) {
                accParent.value =
                    account.accParent ?? '';
            }

            syncParentDisplay();

            disableInvalidParentOptions();

            savedEditData =
                getFormDataForComparison();

            if (accountModalTitle) {
                accountModalTitle.textContent =
                    'تعديل الحساب';
            }

            if (saveAccountBtn) {
                saveAccountBtn.textContent =
                    'حفظ التعديل';
            }

            if (accountModal) {
                accountModal.show();
            }

        } catch (error) {

            showToast(
                error.message,
                'danger'
            );

        }

    }


    // =====================================================
    // التحقق من بيانات النموذج
    // =====================================================

    function validateAccountForm() {

        const name =
            normalizeValue(
                accName?.value
            );

        const code =
            normalizeValue(
                accCode?.value
            );

        if (!code) {

            showToast(
                'رقم الحساب مطلوب',
                'danger'
            );

            accCode?.focus();

            return false;

        }

        if (!/^\d+$/.test(code)) {

            showToast(
                'رقم الحساب يجب أن يكون رقماً صحيحاً',
                'danger'
            );

            accCode?.focus();

            return false;

        }

        if (!name) {

            showToast(
                'اسم الحساب مطلوب',
                'danger'
            );

            accName?.focus();

            return false;

        }

        return true;

    }


    // =====================================================
    // حفظ الحساب
    // =====================================================

    if (accountForm) {

        accountForm.addEventListener(
            'submit',
            async function (event) {

                event.preventDefault();

                if (!validateAccountForm()) {
                    return;
                }

                if (
                    formMode === 'edit' &&
                    !hasFormChanges()
                ) {

                    showToast(
                        'لم يتم إجراء أي تعديل على بيانات الحساب',
                        'danger'
                    );

                    return;

                }

                updateAccountLevel();

                updatePostableState();

                const isEdit =
                    formMode === 'edit' &&
                    editingAccountId;

                const url =
                    isEdit
                        ? `/settings/accounting/chartOfAccounts/${editingAccountId}`
                        : `/settings/accounting/chartOfAccounts`;

                const formData =
                    new FormData(accountForm);

                if (isEdit) {

                    formData.set(
                        '_method',
                        'PUT'
                    );

                } else {

                    formData.delete(
                        '_method'
                    );

                }

                formData.set(
                    'isPostable',
                    isPostable?.value ?? '0'
                );

                if (saveAccountBtn) {
                    saveAccountBtn.disabled = true;
                }

                try {

                    const response =
                        await fetch(
                            url,
                            {
                                method: 'POST',
                                headers: jsonHeaders(),
                                body: formData
                            }
                        );

                    const data =
                        await response.json();

                    if (
                        !response.ok ||
                        !data.success
                    ) {

                        throw new Error(
                            getResponseMessage(
                                data,
                                isEdit
                                    ? 'تعذر تعديل الحساب'
                                    : 'تعذر إضافة الحساب'
                            )
                        );

                    }

                    showToast(
                        data.message ||
                        (
                            isEdit
                                ? 'تم تعديل الحساب بنجاح'
                                : 'تمت إضافة الحساب بنجاح'
                        ),
                        'success'
                    );

                    if (accountModal) {
                        accountModal.hide();
                    }

                    await reloadAccountsTable();

                } catch (error) {

                    showToast(
                        error.message,
                        'danger'
                    );

                } finally {

                    if (saveAccountBtn) {
                        saveAccountBtn.disabled = false;
                    }

                }

            }
        );

    }


    // =====================================================
    // تأكيد الحذف
    // =====================================================

   function openDeleteModal(id) {

    deletingAccountId = id;

    if (!deleteConfirmModal) {
        return;
    }

    deleteConfirmModal.classList.add('show');

    deleteConfirmModal.style.display = 'flex';

    document.body.classList.add('modal-open');

}


    // =====================================================
    // إغلاق نافذة الحذف
    // =====================================================

    function closeDeleteModal() {

    if (!deleteConfirmModal) {
        return;
    }

    deleteConfirmModal.classList.remove('show');

    deleteConfirmModal.style.display = 'none';

    document.body.classList.remove('modal-open');

    deletingAccountId = null;

}


    // =====================================================
    // تنفيذ الحذف
    // =====================================================

    async function deleteAccount() {

        if (!deletingAccountId) {
            return;
        }

        const id =
            deletingAccountId;

        if (deleteConfirmBtn) {
            deleteConfirmBtn.disabled = true;
        }

        try {

            const response =
                await fetch(
                    `/settings/accounting/chartOfAccounts/${id}`,
                    {
                        method: 'DELETE',
                        headers: jsonHeaders()
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
                    'تعذر حذف الحساب'
                );

            }

            closeDeleteModal();

            showToast(
                data.message ||
                'تم حذف الحساب بنجاح',
                'success'
            );

            await reloadAccountsTable();

        } catch (error) {

            closeDeleteModal();

            showToast(
                error.message ||
                'تعذر حذف الحساب',
                'danger'
            );

        } finally {

            if (deleteConfirmBtn) {
                deleteConfirmBtn.disabled = false;
            }

        }

    }


    // =====================================================
    // زر تأكيد الحذف
    // =====================================================

    if (deleteConfirmBtn) {

        deleteConfirmBtn.addEventListener(
            'click',
            deleteAccount
        );

    }


    // =====================================================
    // إغلاق نافذة الحذف
    // =====================================================

    const deleteCancelBtn =
    document.getElementById('deleteCancelBtn');

if (deleteCancelBtn) {

    deleteCancelBtn.addEventListener(
        'click',
        closeDeleteModal
    );

}


    // =====================================================
    // جعل الدوال متاحة للـHTML
    // =====================================================

    window.deleteAccount =
        function (id) {

            openDeleteModal(id);

        };


    // =====================================================
    // الحساب الأب
    // =====================================================

    function syncParentDisplay() {

        if (
            !accParentDisplay ||
            !accParent
        ) {
            return;
        }

        const option =
            accParent.options[
                accParent.selectedIndex
            ];

        if (
            option &&
            accParent.value
        ) {

            accParentDisplay.value =
                option.textContent.trim();

        } else {

            accParentDisplay.value = '';

        }

    }


    // =====================================================
    // فتح نافذة اختيار الحساب الأب
    // =====================================================

    if (accParentDisplay) {

        accParentDisplay.addEventListener(
            'click',
            async function () {

                await loadParentAccounts();

                if (
                    parentAccountSearchInput
                ) {
                    parentAccountSearchInput.value =
                        '';

                }

                if (
                    parentAccountSearchModal &&
                    typeof bootstrap !== 'undefined'
                ) {

                    const modal =
                        bootstrap.Modal.getOrCreateInstance(
                            parentAccountSearchModal
                        );

                    modal.show();

                }

            }
        );

    }


    // =====================================================
    // البحث داخل نافذة الحساب الأب
    // =====================================================

    if (parentAccountSearchInput) {

        parentAccountSearchInput.addEventListener(
            'input',
            function () {

                const value =
                    normalizeValue(
                        parentAccountSearchInput.value
                    ).toLowerCase();

                document
                    .querySelectorAll(
                        '.parent-search-item'
                    )
                    .forEach(function (item) {

                        const text =
                            item.textContent
                                .toLowerCase();

                        item.style.display =
                            text.includes(value)
                                ? ''
                                : 'none';

                    });

            }
        );

    }


    // =====================================================
    // اختيار الحساب الأب
    // =====================================================

    document.addEventListener(
        'click',
        function (event) {

            const item =
                event.target.closest(
                    '.parent-search-item'
                );

            if (!item) {
                return;
            }

            const parentId =
                item.dataset.id || '';

            if (accParent) {

                accParent.value =
                    parentId;

                accParent.dispatchEvent(
                    new Event('change')
                );

            }

            if (
                parentAccountSearchModal &&
                typeof bootstrap !== 'undefined'
            ) {

                const modal =
                    bootstrap.Modal.getInstance(
                        parentAccountSearchModal
                    );

                if (modal) {
                    modal.hide();
                }

            }

        }
    );

    // =====================================================
    // تحميل الحسابات التحليلية
    // =====================================================

    async function loadAnalyticalAccounts(
        parentId,
        page = 1
    ) {

        if (
    !analyticalAccountsModalElement ||
    !analyticalAccountsBody
) {
    return;
}

        selectedAnalyticalParentId =
            Number(parentId);

        analyticalCurrentPage = page;

        if (analyticalAccountsModal) {
            analyticalAccountsModal.show();
        }

        analyticalAccountsBody.innerHTML = `
            <tr>
                <td
                    colspan="7"
                    class="text-center py-4 text-muted"
                >
                    <div
                        class="d-flex justify-content-center align-items-center gap-2"
                    >
                        <div
                            class="spinner-border spinner-border-sm"
                            role="status"
                        ></div>

                        <span>
                            جاري تحميل الحسابات التحليلية...
                        </span>
                    </div>
                </td>
            </tr>
        `;

        try {

            const params =
                new URLSearchParams();

            params.set(
                'page',
                analyticalCurrentPage
            );

            params.set(
                'per_page',
                analyticalPageSize
            );


            if (
                analyticalSearchCode &&
                normalizeValue(
                    analyticalSearchCode.value
                )
            ) {

                params.set(
                    'search_code',
                    normalizeValue(
                        analyticalSearchCode.value
                    )
                );
            }


            if (
                analyticalSearchName &&
                normalizeValue(
                    analyticalSearchName.value
                )
            ) {

                params.set(
                    'search_name',
                    normalizeValue(
                        analyticalSearchName.value
                    )
                );
            }



            const response = await fetch(
                `/settings/accounting/chartOfAccounts/${parentId}/analytical?${params.toString()}`,
                {
                    method: 'GET',

                    headers: {
                        'Accept':
                            'application/json'
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
                    getResponseMessage(
                        data,
                        'تعذر تحميل الحسابات التحليلية'
                    )
                );
            }


            // =============================================
            // عرض الحساب الأب
            // =============================================

            if (
                analyticalAccountsParent &&
                data.parent
            ) {

                analyticalAccountsParent.textContent =
                    `${data.parent.accCode} - ${data.parent.accName}`;
            }


            // =============================================
            // الحسابات
            // =============================================

            const paginator =
                data.accounts || {};

            const rows =
                Array.isArray(
                    paginator.data
                )
                    ? paginator.data
                    : [];


            analyticalAccountsBody.innerHTML = '';


            if (!rows.length) {

                analyticalAccountsBody.innerHTML = `
                    <tr>
                        <td
                            colspan="7"
                            class="text-center py-4 text-muted"
                        >
                            لا توجد حسابات تحليلية لهذا الحساب.
                        </td>
                    </tr>
                `;

                renderAnalyticalPagination(
                    paginator
                );

                return;
            }


            // =============================================
            // عرض الصفوف
            // =============================================

            rows.forEach(
                function (account, index) {

                    const row =
                        document.createElement('tr');


                    // الرقم التسلسلي

                    const numberCell =
                        document.createElement('td');

                    numberCell.textContent =
                        (
                            (
                                Number(
                                    paginator.current_page
                                ) - 1
                            ) *
                            Number(
                                paginator.per_page
                            )
                        ) +
                        index +
                        1;

                    row.appendChild(
                        numberCell
                    );


                    // رقم الحساب

                    const codeCell =
                        document.createElement('td');

                    codeCell.textContent =
                        account.accCode ?? '';

                    row.appendChild(
                        codeCell
                    );


                    // اسم الحساب

                    const nameCell =
                        document.createElement('td');

                    nameCell.textContent =
                        account.accName ?? '';

                    row.appendChild(
                        nameCell
                    );


                    // الطبيعة

                    const natureCell =
                        document.createElement('td');

                    if (
                        Number(account.nature) === 0
                    ) {

                        natureCell.innerHTML =
                            '<span class="badge bg-primary-subtle text-primary">مدين</span>';

                    } else {

                        natureCell.innerHTML =
                            '<span class="badge bg-info-subtle text-info">دائن</span>';
                    }

                    row.appendChild(
                        natureCell
                    );


                    // الحساب الأب

                    const parentCell =
                        document.createElement('td');

                    if (
                        data.parent
                    ) {

                        parentCell.textContent =
                            data.parent.accName ?? '';

                    } else {

                        parentCell.textContent =
                            '';
                    }

                    row.appendChild(
                        parentCell
                    );


                    // الحالة

                    const statusCell =
                        document.createElement('td');

                    if (
                        Number(account.IsActive) === 1
                    ) {

                        statusCell.innerHTML =
                            '<span class="badge bg-success-subtle text-success">نشط</span>';

                    } else {

                        statusCell.innerHTML =
                            '<span class="badge bg-secondary-subtle text-secondary">غير نشط</span>';
                    }

                    row.appendChild(
                        statusCell
                    );


                    // الإجراءات

                    const actionsCell =
                        document.createElement('td');

                    const actionsWrapper =
                        document.createElement('div');

                    actionsWrapper.className =
                        'd-flex gap-1';


                    // زر التعديل

                    const editButton =
                        document.createElement('button');

                    editButton.type =
                        'button';

                    editButton.className =
                        'btn btn-sm btn-outline-primary edit-account';

                    editButton.dataset.id =
                        account.accountID;

                    editButton.title =
                        'تعديل';

                    editButton.innerHTML =
                        '<i class="bi bi-pencil"></i>';


                    actionsWrapper.appendChild(
                        editButton
                    );


                    // زر الحذف

                    const deleteButton =
                        document.createElement('button');

                    deleteButton.type =
                        'button';

                    deleteButton.className =
                        'btn btn-sm btn-outline-danger delete-account';

                    deleteButton.dataset.id =
                        account.accountID;

                    deleteButton.title =
                        'حذف';

                    deleteButton.innerHTML =
                        '<i class="bi bi-trash"></i>';


                    actionsWrapper.appendChild(
                        deleteButton
                    );


                    actionsCell.appendChild(
                        actionsWrapper
                    );

                    row.appendChild(
                        actionsCell
                    );


                    analyticalAccountsBody.appendChild(
                        row
                    );
                }
            );


            renderAnalyticalPagination(
                paginator
            );


        } catch (error) {

            analyticalAccountsBody.innerHTML = `
                <tr>
                    <td
                        colspan="7"
                        class="text-center py-4 text-danger"
                    >
                        ${escapeHtml(
                            error.message ||
                            'تعذر تحميل الحسابات التحليلية'
                        )}
                    </td>
                </tr>
            `;

            renderAnalyticalPagination(
                {}
            );
        }
    }

        // =====================================================
    // Pagination للحسابات التحليلية
    // =====================================================

    function renderAnalyticalPagination(
        paginator
    ) {

        if (
            !analyticalPaginationInfo ||
            !analyticalPaginationList
        ) {
            return;
        }

        analyticalPaginationList.innerHTML = '';


        const total =
            Number(paginator.total || 0);

        const currentPage =
            Number(
                paginator.current_page || 1
            );

        const perPage =
            Number(
                paginator.per_page ||
                analyticalPageSize
            );

        const lastPage =
            Number(
                paginator.last_page || 1
            );


        if (!total) {

            analyticalPaginationInfo.textContent =
                'عرض 0-0 من 0 حساب';

            return;
        }


        const start =
            ((currentPage - 1) * perPage) + 1;

        const end =
            Math.min(
                currentPage * perPage,
                total
            );


        analyticalPaginationInfo.textContent =
            `عرض ${start}-${end} من ${total} حساب`;


        // السابق

        const previous =
            document.createElement('li');

        previous.className =
            `page-item ${
                currentPage === 1
                    ? 'disabled'
                    : ''
            }`;

        previous.innerHTML = `
            <button
                type="button"
                class="page-link"
            >
                السابق
            </button>
        `;


        if (currentPage > 1) {

            previous
                .querySelector('button')
                .addEventListener(
                    'click',
                    function () {

                        loadAnalyticalAccounts(
                            selectedAnalyticalParentId,
                            currentPage - 1
                        );
                    }
                );
        }


        analyticalPaginationList.appendChild(
            previous
        );


        // أرقام الصفحات

        for (
            let page = 1;
            page <= lastPage;
            page++
        ) {

            const item =
                document.createElement('li');

            item.className =
                `page-item ${
                    page === currentPage
                        ? 'active'
                        : ''
                }`;


            const button =
                document.createElement('button');

            button.type =
                'button';

            button.className =
                'page-link';

            button.textContent =
                page;


            button.addEventListener(
                'click',
                function () {

                    loadAnalyticalAccounts(
                        selectedAnalyticalParentId,
                        page
                    );
                }
            );


            item.appendChild(
                button
            );

            analyticalPaginationList.appendChild(
                item
            );
        }


        // التالي

        const next =
            document.createElement('li');

        next.className =
            `page-item ${
                currentPage === lastPage
                    ? 'disabled'
                    : ''
            }`;

        next.innerHTML = `
            <button
                type="button"
                class="page-link"
            >
                التالي
            </button>
        `;


        if (currentPage < lastPage) {

            next
                .querySelector('button')
                .addEventListener(
                    'click',
                    function () {

                        loadAnalyticalAccounts(
                            selectedAnalyticalParentId,
                            currentPage + 1
                        );
                    }
                );
        }


        analyticalPaginationList.appendChild(
            next
        );
    }

    // =====================================================
    // بناء صف الحساب
    // =====================================================
function createAccountRow(account) {

    const row = document.createElement('tr');

    const id = Number(account.accountID);

    const parentId =
        account.accParent
            ? Number(account.accParent)
            : null;

    const level =
        Number(account.accLevel || 1);

    const hasChildren =
        accounts.some(function (child) {
            return Number(child.accParent) === id;
        });

    row.dataset.id = id;
    row.dataset.parentId = parentId || '';
    row.dataset.level = level;

    // =================================================
    // #
    // =================================================

    const numberCell = document.createElement('td');

    row.appendChild(numberCell);


   // =================================================
// رقم الحساب
// =================================================

const codeCell = document.createElement('td');
codeCell.style.paddingRight =
    `${20 + ((level - 1) * 25)}px`;

if (hasChildren) {

    const wrapper = document.createElement('div');

    wrapper.className =
        'd-flex align-items-center gap-2';

    const toggleButton =
        document.createElement('button');

    toggleButton.type = 'button';

    toggleButton.className =
        'btn btn-sm btn-link p-0 tree-toggle';

    toggleButton.dataset.id =
        account.accountID;

    toggleButton.innerHTML =
        '<i class="bi bi-chevron-left"></i>';

    wrapper.appendChild(toggleButton);

    const codeText =
        document.createElement('span');

    codeText.textContent =
        account.accCode;

    wrapper.appendChild(codeText);

    codeCell.appendChild(wrapper);

} else {

    codeCell.textContent =
        account.accCode;
}

row.appendChild(codeCell);


// =================================================
// اسم الحساب
// =================================================

const nameCell =
    document.createElement('td');

nameCell.className =
    'account-name-cell';

nameCell.textContent =
    account.accName ?? '';

row.appendChild(nameCell);


    // =================================================
    // طبيعة الحساب
    // =================================================

    const natureCell =
        document.createElement('td');

    const natureValue = Number(account.nature);

if (natureValue === 0) {
    natureCell.innerHTML =
        '<span class="badge bg-primary-subtle text-primary">مدين</span>';
} else {
    natureCell.innerHTML =
        '<span class="badge bg-info-subtle text-info">دائن</span>';
}
    row.appendChild(natureCell);


    // =================================================
    // الحساب الأب
    // =================================================

    const parentCell =
        document.createElement('td');

    if (parentId) {

        const parent =
            accounts.find(function (item) {

                return Number(item.accountID) === parentId;

            });

        parentCell.textContent =
            parent
                ? parent.accName
                : '';

    } else {

        parentCell.textContent =
            'رئيسي';

    }

    row.appendChild(parentCell);


    // =================================================
    // الحالة
    // =================================================

    const statusCell =
        document.createElement('td');

    if (Number(account.IsActive) === 1) {

        statusCell.innerHTML =
            '<span class="badge bg-success-subtle text-success">نشط</span>';

    } else {

        statusCell.innerHTML =
            '<span class="badge bg-secondary-subtle text-secondary">غير نشط</span>';

    }

    row.appendChild(statusCell);


    // =================================================
    // الإجراءات
    // =================================================

    const actionsCell =
        document.createElement('td');

    const actionsWrapper =
        document.createElement('div');

    actionsWrapper.className =
        'd-flex gap-1';

 // =================================================
// عرض الحسابات التحليلية
// =================================================

if (account.hasAnalytical) {

    const analyticalButton =
        document.createElement('button');

    analyticalButton.type =
        'button';

    analyticalButton.className =
        'btn btn-sm btn-outline-success show-analytical';

    analyticalButton.dataset.id =
        id;

    analyticalButton.title =
        'عرض الحسابات التحليلية';

    analyticalButton.innerHTML =
        '<i class="bi bi-list-ul"></i>';

    actionsWrapper.appendChild(
        analyticalButton
    );
}


    if (Number(account.is_system) === 1) {

        const systemBadge =
            document.createElement('span');

        systemBadge.className =
            'badge bg-warning-subtle text-warning';

        systemBadge.textContent =
            'نظامي';

        actionsWrapper.appendChild(systemBadge);

    } else {

        // ---------------------------------------------
        // تعديل
        // ---------------------------------------------

        const editButton =
            document.createElement('button');

        editButton.type = 'button';

        editButton.className =
            'btn btn-sm btn-outline-primary edit-account';

        editButton.dataset.id = id;

        editButton.title = 'تعديل';

        editButton.innerHTML =
            '<i class="bi bi-pencil"></i>';

        actionsWrapper.appendChild(editButton);


        // ---------------------------------------------
        // حذف
        // ---------------------------------------------

        const deleteButton =
            document.createElement('button');

        deleteButton.type = 'button';

        deleteButton.className =
            'btn btn-sm btn-outline-danger delete-account';

        deleteButton.dataset.id = id;

        deleteButton.title = 'حذف';

        deleteButton.innerHTML =
            '<i class="bi bi-trash"></i>';

        actionsWrapper.appendChild(deleteButton);

    }

    actionsCell.appendChild(actionsWrapper);

    row.appendChild(actionsCell);

    return row;
}


    // =====================================================
    // هل الصف يجب أن يظهر؟
    // =====================================================

    function isRowVisible(
        account
    ) {

        let parentId =
            account.accParent
                ? Number(account.accParent)
                : null;

        while (parentId) {

            const parent =
                accounts.find(function (item) {

                    return Number(
                        item.accountID
                    ) === parentId;

                });

            if (!parent) {
                break;
            }

            if (
                parent.__open !== true
            ) {

                return false;

            }

            parentId =
                parent.accParent
                    ? Number(parent.accParent)
                    : null;

        }

        return true;

    }


    // =====================================================
    // الحسابات المرئية
    // =====================================================

    function getVisibleAccounts() {

        return accounts.filter(
            isRowVisible
        );

    }


    // =====================================================
    // عرض الشجرة
    // =====================================================

    function renderTree() {

        if (!accountsTreeBody) {
            return;
        }

        accountsTreeBody.innerHTML = '';

        const visibleAccounts =
            getVisibleAccounts();

        if (!visibleAccounts.length) {

            renderEmptyTreeMessage();

            renderPagination(
                0
            );

            return;

        }

        const total =
            visibleAccounts.length;

        const totalPages =
            Math.max(
                1,
                Math.ceil(
                    total / pageSize
                )
            );

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const start =
            (currentPage - 1) *
            pageSize;

        const pageAccounts =
            visibleAccounts.slice(
                start,
                start + pageSize
            );

        pageAccounts.forEach(
            function (account, index) {

                const row =
                    createAccountRow(
                        account
                    );

                const numberCell =
                    row.children[0];

                numberCell.textContent =
                    start + index + 1;

                accountsTreeBody.appendChild(
                    row
                );

            }
        );

        renderPagination(
            total
        );

        updateTreeIcons();

    }


    // =====================================================
    // رسالة عدم وجود بيانات
    // =====================================================

    function renderEmptyTreeMessage() {

        accountsTreeBody.innerHTML = `

            <tr>
                <td
                    colspan="7"
                    class="text-center py-4 text-muted"
                >
                    لا توجد حسابات
                </td>
            </tr>

        `;

    }


    // =====================================================
    // Pagination
    // =====================================================

    function renderPagination(total) {

        const info =
            document.getElementById(
                'accountsPaginationInfo'
            );

        const list =
            document.getElementById(
                'accountsPaginationList'
            );

        if (!info || !list) {
            return;
        }

        list.innerHTML = '';

        if (!total) {

            info.textContent =
                'عرض 0-0 من 0 حساب';

            return;

        }

        const totalPages =
            Math.ceil(
                total / pageSize
            );

        const start =
            ((currentPage - 1) * pageSize) + 1;

        const end =
            Math.min(
                currentPage * pageSize,
                total
            );

        info.textContent =
            `عرض ${start}-${end} من ${total} حساب`;


        // =================================================
        // السابق
        // =================================================

        const previous =
            document.createElement('li');

        previous.className =
            `page-item ${
                currentPage === 1
                    ? 'disabled'
                    : ''
            }`;

        previous.innerHTML =
            `
                <button
                    class="page-link"
                    type="button"
                >
                    السابق
                </button>
            `;

        if (currentPage > 1) {

            previous
                .querySelector('button')
                .addEventListener(
                    'click',
                    function () {

                        currentPage--;

                        renderTree();

                    }
                );

        }

        list.appendChild(
            previous
        );


        // =================================================
        // أرقام الصفحات
        // =================================================

        for (
            let page = 1;
            page <= totalPages;
            page++
        ) {

            const item =
                document.createElement('li');

            item.className =
                `page-item ${
                    page === currentPage
                        ? 'active'
                        : ''
                }`;

            const button =
                document.createElement('button');

            button.type = 'button';

            button.className =
                'page-link';

            button.textContent =
                page;

            button.addEventListener(
                'click',
                function () {

                    currentPage =
                        page;

                    renderTree();

                }
            );

            item.appendChild(
                button
            );

            list.appendChild(
                item
            );

        }


        // =================================================
        // التالي
        // =================================================

        const next =
            document.createElement('li');

        next.className =
            `page-item ${
                currentPage === totalPages
                    ? 'disabled'
                    : ''
            }`;

        next.innerHTML =
            `
                <button
                    class="page-link"
                    type="button"
                >
                    التالي
                </button>
            `;

        if (
            currentPage < totalPages
        ) {

            next
                .querySelector('button')
                .addEventListener(
                    'click',
                    function () {

                        currentPage++;

                        renderTree();

                    }
                );

        }

        list.appendChild(
            next
        );

    }


    // =====================================================
    // تحديث أيقونات فتح وإغلاق الشجرة
    // =====================================================

    function updateTreeIcons() {

        document
            .querySelectorAll(
                '.account-tree-toggle'
            )
            .forEach(function (button) {

                const id =
                    Number(
                        button.dataset.id
                    );

                const account =
                    accounts.find(
                        function (item) {

                            return Number(
                                item.accountID
                            ) === id;

                        }
                    );

                if (!account) {
                    return;
                }

                const icon =
                    button.querySelector(
                        'i'
                    );

                if (!icon) {
                    return;
                }

                if (
                    account.__open === true
                ) {

                    icon.className =
                        'bi bi-chevron-down';

                } else {

                    icon.className =
                        'bi bi-chevron-left';

                }

            });

    }


    // =====================================================
    // فتح / إغلاق حساب
    // =====================================================

    function toggleAccountTree(id) {

        const account =
            accounts.find(
                function (item) {

                    return Number(
                        item.accountID
                    ) === Number(id);

                }
            );

        if (!account) {
            return;
        }

        account.__open =
            account.__open !== true;

        currentPage = 1;

        renderTree();

    }


        // =====================================================
    // أحداث الشجرة
    // =====================================================

    if (accountsTreeBody) {

        accountsTreeBody.addEventListener(
            'click',
            function (event) {

                // -----------------------------------------
                // فتح / إغلاق الشجرة
                // -----------------------------------------

                const toggle =
                    event.target.closest(
                        '.tree-toggle'
                    );

                if (toggle) {

                    toggleAccountTree(
                        toggle.dataset.id
                    );

                    return;
                }


                // -----------------------------------------
                // عرض الحسابات التحليلية
                // -----------------------------------------

                const analyticalButton =
                    event.target.closest(
                        '.show-analytical'
                    );

                if (analyticalButton) {

                    loadAnalyticalAccounts(
                        analyticalButton.dataset.id,
                        1
                    );

                    return;
                }


                // -----------------------------------------
                // تعديل
                // -----------------------------------------

                const editButton =
                    event.target.closest(
                        '.edit-account'
                    );

                if (editButton) {

                    editAccount(
                        editButton.dataset.id
                    );

                    return;
                }


                // -----------------------------------------
                // حذف
                // -----------------------------------------

                const deleteButton =
                    event.target.closest(
                        '.delete-account'
                    );

                if (deleteButton) {

                    openDeleteModal(
                        deleteButton.dataset.id
                    );

                }

            }
        );

    }


    // =====================================================
    // البحث
    // =====================================================

    function getSearchParams() {

        const params =
            new URLSearchParams();

        if (
            searchCode &&
            normalizeValue(
                searchCode.value
            )
        ) {

            params.set(
                'search_code',
                normalizeValue(
                    searchCode.value
                )
            );

        }

        if (
            searchName &&
            normalizeValue(
                searchName.value
            )
        ) {

            params.set(
                'search_name',
                normalizeValue(
                    searchName.value
                )
            );

        }

        if (
            searchNature &&
            normalizeValue(
                searchNature.value
            )
        ) {

            params.set(
                'search_nature',
                normalizeValue(
                    searchNature.value
                )
            );

        }

        return params;

    }


    // =====================================================
    // تحميل الشجرة من Backend
    // =====================================================

    async function reloadAccountsTable() {

        if (!accountsTreeBody) {
            return;
        }

        const openIds =
            new Set(
                accounts
                    .filter(
                        function (account) {

                            return account.__open === true;

                        }
                    )
                    .map(
                        function (account) {

                            return Number(
                                account.accountID
                            );

                        }
                    )
            );


        accountsTreeBody.innerHTML = `

            <tr>
                <td
                    colspan="7"
                    class="text-center py-4 text-muted"
                >
                    جاري تحميل الحسابات...
                </td>
            </tr>

        `;


        try {

            const params =
                getSearchParams();

            const queryString =
                params.toString()
                    ? `?${params.toString()}`
                    : '';


            const response =
                await fetch(
                    `/settings/accounting/chartOfAccounts/tree${queryString}`,
                    {
                        method: 'GET',
                        headers: {
                            'Accept':
                                'application/json'
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
                    getResponseMessage(
                        data,
                        'تعذر تحميل شجرة الحسابات'
                    )
                );

            }


            accounts =
                Array.isArray(
                    data.accounts
                )
                    ? data.accounts
                    : [];


            accounts.forEach(
                function (account) {

                    account.__open =
                        openIds.has(
                            Number(
                                account.accountID
                            )
                        );

                }
            );


            currentPage = 1;

            renderTree();


        } catch (error) {

            accounts = [];

            accountsTreeBody.innerHTML = `

                <tr>
                    <td
                        colspan="7"
                        class="text-center py-4 text-danger"
                    >
                        ${escapeHtml(
                            error.message ||
                            'تعذر تحميل الحسابات'
                        )}
                    </td>
                </tr>

            `;

            renderPagination(0);

        }

    }

        // =====================================================
    // البحث في الحسابات التحليلية
    // =====================================================

    function handleAnalyticalSearch() {

        clearTimeout(
            analyticalSearchTimer
        );

        analyticalSearchTimer =
            setTimeout(
                function () {

                    if (
                        !selectedAnalyticalParentId
                    ) {
                        return;
                    }

                    loadAnalyticalAccounts(
                        selectedAnalyticalParentId,
                        1
                    );

                },
                350
            );
    }


    if (analyticalSearchCode) {

        analyticalSearchCode.addEventListener(
            'input',
            handleAnalyticalSearch
        );
    }


    if (analyticalSearchName) {

        analyticalSearchName.addEventListener(
            'input',
            handleAnalyticalSearch
        );
    }


    if (analyticalSearchNature) {

        analyticalSearchNature.addEventListener(
            'change',
            handleAnalyticalSearch
        );
    }


    // =====================================================
    // البحث مع Debounce
    // =====================================================

    function handleSearch() {

        clearTimeout(
            searchTimer
        );

        searchTimer =
            setTimeout(
                function () {

                    currentPage = 1;

                    reloadAccountsTable();

                },
                350
            );

    }


    if (searchCode) {

        searchCode.addEventListener(
            'input',
            handleSearch
        );

    }

    if (searchName) {

        searchName.addEventListener(
            'input',
            handleSearch
        );

    }

    if (searchNature) {

        searchNature.addEventListener(
            'change',
            handleSearch
        );

    }


    // =====================================================
    // Reset عند إغلاق نافذة الحساب
    // =====================================================
  

    if (accountModalElement) {

        accountModalElement.addEventListener(
            'hidden.bs.modal',
            function () {

                if (accountForm) {
                    accountForm.reset();
                }

                formMode = 'add';

                editingAccountId = null;

                savedEditData = null;

                enableAllParentOptions();

            }
        );

    }


    // =====================================================
    // Escape HTML
    // =====================================================

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;

    }


    // =====================================================
    // إتاحة إعادة التحميل خارجياً
    // =====================================================

    window.reloadAccountsTable =
        reloadAccountsTable;


    // =====================================================
    // التحميل الأول
    // =====================================================

    reloadAccountsTable();

});