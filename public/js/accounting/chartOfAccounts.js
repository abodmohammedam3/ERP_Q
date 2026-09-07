/**
 * =========================================================
 * chartOfAccounts.js
 * إدارة دليل الحسابات
 * =========================================================
 *
 * يعتمد على:
 *
 * showSystemToast(message, type)
 *
 * من System.js
 *
 * ويستخدم نافذة تأكيد الحذف:
 *
 * #deleteConfirmModal
 * #deleteCancelBtn
 * #deleteConfirmBtn
 *
 * =========================================================
 */

document.addEventListener('DOMContentLoaded', function () {

    // =====================================================
    // عناصر الصفحة
    // =====================================================

    const addAccountBtn =
        document.getElementById('addAccountBtn');

    const accountForm =
        document.getElementById('accountForm');

    const accountModalElement =
        document.getElementById('addAccountModal');

    const saveAccountBtn =
        document.getElementById('saveAccountBtn');

    const accountModalTitle =
        document.getElementById('accountModalTitle');

    const accountsTbody =
        document.getElementById('accountsTreeBody');


    // =====================================================
    // البحث
    // =====================================================

    const searchCode =
        document.getElementById('searchCode');

    const searchName =
        document.getElementById('searchName');

    const searchNature =
        document.getElementById('searchNature');


    // =====================================================
    // حقول الحساب
    // =====================================================

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

    const isActive =
        document.getElementById('IsActive');

    const isPostable =
        document.getElementById('isPostable');


    // =====================================================
    // نافذة الحذف
    // =====================================================

    const deleteConfirmModal =
        document.getElementById('deleteConfirmModal');

    const deleteCancelBtn =
        document.getElementById('deleteCancelBtn');

    const deleteConfirmBtn =
        document.getElementById('deleteConfirmBtn');


    // =====================================================
    // التحقق من العناصر الأساسية
    // =====================================================

    if (!accountForm) {

        console.error(
            'لم يتم العثور على accountForm'
        );

        return;
    }


    if (!accountsTbody) {

        console.error(
            'لم يتم العثور على accountsTreeBody'
        );

        return;
    }


    // =====================================================
    // Bootstrap Modal
    // =====================================================

    let accountModal = null;

    if (accountModalElement) {

        accountModal =
            bootstrap.Modal.getOrCreateInstance(
                accountModalElement
            );
    }


    // =====================================================
    // حالة النموذج
    // =====================================================

    let formMode = 'add';

    let editingAccountId = null;


    // =====================================================
    // حالة الحذف
    // =====================================================

    let deletingAccountId = null;


    // =====================================================
    // Pagination
    // =====================================================

    let currentPage = 1;

    const rowsPerPage = 10;


    // =====================================================
    // حالة البحث النشط
    // =====================================================

    let isSearchActive = false;


    // =====================================================
    // CSRF
    // =====================================================

    function getCsrfToken() {

        const metaToken =
            document.querySelector(
                'meta[name="csrf-token"]'
            );

        if (
            metaToken &&
            metaToken.getAttribute('content')
        ) {
            return metaToken.getAttribute('content');
        }


        const inputToken =
            document.querySelector(
                'input[name="_token"]'
            );

        if (
            inputToken &&
            inputToken.value
        ) {
            return inputToken.value;
        }


        return '';
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


        const selectedOption =
            accParent.options[
                accParent.selectedIndex
            ];


        if (!selectedOption) {
            return;
        }


        const parentLevel =
            parseInt(
                selectedOption.dataset.level || '1',
                10
            );


        accLevel.value =
            parentLevel + 1;
    }


    // =====================================================
    // الحصول على كود الحساب التالي
    // =====================================================

    if (accParent) {

        accParent.addEventListener(
            'change',
            async function () {

                updateAccountLevel();


                // في وضع التعديل لا نغير الكود
                if (formMode !== 'add') {
                    return;
                }


                const parentId =
                    accParent.value;


                if (!parentId) {

                    if (accCode) {
                        accCode.value = '';
                    }

                    return;
                }


                try {

                    const response =
                        await fetch(
                            `/settings/accounting/chartOfAccounts/next-code/${parentId}`,
                            {
                                method: 'GET',
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
                        data.success &&
                        accCode
                    ) {

                        accCode.value =
                            data.code;
                    }

                } catch (error) {

                    console.error(
                        'خطأ في جلب الكود التالي:',
                        error
                    );


                    if (typeof showSystemToast === 'function') {

                        showSystemToast(
                            'تعذر الحصول على رقم الحساب التالي',
                            'danger'
                        );
                    }
                }
            }
        );
    }


    // =====================================================
    // وضع الإضافة
    // =====================================================

    function setAddMode() {

        formMode = 'add';

        editingAccountId = null;


        if (accountModalTitle) {

            accountModalTitle.textContent =
                'إضافة حساب';
        }


        if (saveAccountBtn) {

            saveAccountBtn.textContent =
                'حفظ';
        }


        accountForm.reset();


        if (accountID) {
            accountID.value = '';
        }


        setFormMethod('POST');


        enableAllParentOptions();


        if (accLevel) {
            accLevel.value = 1;
        }


        if (isActive) {
            isActive.value = '1';
        }


        if (isPostable) {
            isPostable.value = '1';
        }
    }


    // =====================================================
    // تعديل الحساب
    // =====================================================

    window.editAccount = async function (id) {

        try {

            const response =
                await fetch(
                    `/settings/accounting/chartOfAccounts/${id}`,
                    {
                        method: 'GET',
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
                !data.success ||
                !data.account
            ) {

                throw new Error(
                    data.message ||
                    'تعذر تحميل بيانات الحساب'
                );
            }


            const account =
                data.account;


            formMode = 'edit';

            editingAccountId =
                account.accountID;


            if (accountModalTitle) {

                accountModalTitle.textContent =
                    'تعديل الحساب';
            }


            if (saveAccountBtn) {

                saveAccountBtn.textContent =
                    'تحديث';
            }


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


            if (isActive) {
                isActive.value =
                    account.IsActive ?? 1;
            }


            if (isPostable) {
                isPostable.value =
                    account.isPostable ?? 1;
            }


            enableAllParentOptions();


            if (accParent) {

                accParent.value =
                    account.accParent ?? '';

                disableCurrentAccountAsParent(
                    account.accountID
                );

                // -------------------------------------------------
                // [هنا تم دمج التعديل الأول]
                // تحديث حالة نافذة البحث في وضع التعديل ومزامنة العرض
                // -------------------------------------------------
                if (typeof window.updateParentSearchDisabledState === 'function') {
                    window.updateParentSearchDisabledState(account.accountID);
                }

                if (typeof syncParentDisplay === 'function') {
                    syncParentDisplay();
                }
            }


            setFormMethod('PUT');


            if (accountModal) {
                accountModal.show();
            }

        } catch (error) {

            console.error(
                'خطأ في تعديل الحساب:',
                error
            );


            if (typeof showSystemToast === 'function') {

                showSystemToast(
                    error.message ||
                    'حدث خطأ أثناء تحميل الحساب',
                    'danger'
                );
            }
        }
    };


    // =====================================================
    // منع اختيار الحساب الحالي كحساب أب
    // =====================================================

    function disableCurrentAccountAsParent(
        currentId
    ) {

        if (!accParent) {
            return;
        }


        Array.from(
            accParent.options
        ).forEach(function (option) {

            option.disabled =
                String(option.value) ===
                String(currentId);
        });
    }


    // =====================================================
    // إعادة تفعيل جميع الحسابات في قائمة الأب
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
    // تحديد Method النموذج
    // =====================================================

    function setFormMethod(method) {

        let methodInput =
            accountForm.querySelector(
                'input[name="_method"]'
            );


        if (method.toUpperCase() === 'POST') {

            if (methodInput) {
                methodInput.remove();
            }

            accountForm.method = 'POST';

            return;
        }


        if (!methodInput) {

            methodInput =
                document.createElement('input');

            methodInput.type = 'hidden';

            methodInput.name = '_method';

            accountForm.appendChild(
                methodInput
            );
        }


        methodInput.value =
            method.toUpperCase();


        accountForm.method = 'POST';
    }


    // =====================================================
    // التحقق من صحة النموذج قبل الإرسال
    // =====================================================

    function validateAccountForm() {

        // التحقق من حقل رقم الحساب
        if (accCode) {

            if (!accCode.value || !accCode.value.trim()) {

                if (typeof showSystemToast === 'function') {

                    showSystemToast(
                        'حقل رقم الحساب مطلوب',
                        'danger'
                    );
                }

                accCode.focus();

                return false;
            }
        }

        // التحقق من حقل اسم الحساب
        if (accName) {

            if (!accName.value || !accName.value.trim()) {

                if (typeof showSystemToast === 'function') {

                    showSystemToast(
                        'حقل اسم الحساب مطلوب',
                        'danger'
                    );
                }

                accName.focus();

                return false;
            }
        }

        return true;
    }


    // =====================================================
    // حفظ / تحديث الحساب
    // =====================================================

    accountForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            if (
                saveAccountBtn &&
                saveAccountBtn.disabled
            ) {
                return;
            }


            // -----------------------------------------------
            // التحقق من صحة الحقول قبل الإرسال
            // -----------------------------------------------

            if (!validateAccountForm()) {
                return;
            }


            if (saveAccountBtn) {

                saveAccountBtn.disabled =
                    true;
            }


            try {

                const formData =
                    new FormData(
                        accountForm
                    );


                let url =
                    '/settings/accounting/chartOfAccounts';


                if (
                    formMode === 'edit' &&
                    editingAccountId
                ) {

                    url =
                        `/settings/accounting/chartOfAccounts/${editingAccountId}`;

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


                if (!response.ok || !data.success) {

                    let message =
                        data.message ||
                        'حدث خطأ أثناء حفظ الحساب';


                    // أخطاء التحقق من Laravel
                    if (
                        data.errors &&
                        typeof data.errors === 'object'
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
                // رسالة النظام العامة
                // =================================================

                if (
                    typeof showSystemToast ===
                    'function'
                ) {

                    showSystemToast(
                        data.message ||
                        (
                            formMode === 'edit'
                                ? 'تم تحديث الحساب بنجاح'
                                : 'تم إضافة الحساب بنجاح'
                        ),
                        'success'
                    );
                }


                // إغلاق المودال
                if (accountModal) {
                    accountModal.hide();
                }


                // تحديث الجدول
                await reloadAccountsTable();


                // إعادة النموذج
                resetAccountForm();

            } catch (error) {

                console.error(
                    'خطأ في حفظ الحساب:',
                    error
                );


                if (
                    typeof showSystemToast ===
                    'function'
                ) {

                    showSystemToast(
                        error.message ||
                        'حدث خطأ أثناء حفظ الحساب',
                        'danger'
                    );
                }

            } finally {

                if (saveAccountBtn) {

                    saveAccountBtn.disabled =
                        false;
                }
            }
        }
    );


    // =====================================================
    // فتح نافذة إضافة حساب
    // =====================================================

    if (addAccountBtn) {

        addAccountBtn.addEventListener(
            'click',
            function () {

                setAddMode();


                if (accountModal) {
                    accountModal.show();
                }
            }
        );
    }


    // =====================================================
    // التحقق من وجود حسابات أبناء محلياً
    // =====================================================

    function hasChildAccounts(id) {

        const childRow =
            accountsTbody.querySelector(
                `tr.account-row[data-parent="${id}"]`
            );

        return !!childRow;
    }


    // =====================================================
    // فتح نافذة تأكيد الحذف
    // =====================================================

    function openDeleteConfirm(id) {

        deletingAccountId = id;


        if (!deleteConfirmModal) {

            console.error(
                'لم يتم العثور على deleteConfirmModal'
            );

            return;
        }


        deleteConfirmModal.classList.add(
            'show'
        );

        deleteConfirmModal.style.display =
            'flex';

        document.body.classList.add(
            'delete-confirm-open'
        );
    }


    // =====================================================
    // إغلاق نافذة تأكيد الحذف
    // =====================================================

    function closeDeleteConfirm() {

        deletingAccountId = null;


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


    // =====================================================
    // إلغاء الحذف
    // =====================================================

    if (deleteCancelBtn) {

        deleteCancelBtn.addEventListener(
            'click',
            function () {

                closeDeleteConfirm();
            }
        );
    }


    // =====================================================
    // تنفيذ الحذف بعد التأكيد
    // =====================================================

    if (deleteConfirmBtn) {

        deleteConfirmBtn.addEventListener(
            'click',
            async function () {

                if (!deletingAccountId) {
                    return;
                }


                const id =
                    deletingAccountId;


                deleteConfirmBtn.disabled =
                    true;


                try {

                    const token =
                        getCsrfToken();


                    const deleteFormData =
                        new FormData();

                    deleteFormData.append(
                        '_token',
                        token
                    );

                    deleteFormData.append(
                        '_method',
                        'DELETE'
                    );


                    const response =
                        await fetch(
                            `/settings/accounting/chartOfAccounts/${id}`,
                            {
                                method: 'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                    'X-CSRF-TOKEN':
                                        token
                                },

                                body: deleteFormData
                            }
                        );


                    const data =
                        await response.json();


                    if (
                        !response.ok ||
                        !data.success
                    ) {

                        let errorMessage =
                            data.message ||
                            'تعذر حذف الحساب';


                        // ترجمة رسائل الخطأ المتعلقة بالارتباط
                        if (
                            typeof errorMessage === 'string' &&
                            (
                                errorMessage.includes('مرتبط') ||
                                errorMessage.includes('child') ||
                                errorMessage.includes('children') ||
                                errorMessage.includes('أبناء') ||
                                errorMessage.includes('تابع') ||
                                errorMessage.includes('foreign') ||
                                errorMessage.includes('constraint') ||
                                errorMessage.includes('referenced') ||
                                errorMessage.includes('integrity')
                            )
                        ) {

                            errorMessage =
                                'لا يمكن حذف هذا الحساب لأنه مرتبط بحسابات فرعية أخرى، يرجى حذف الحسابات الفرعية أولاً';
                        }


                        throw new Error(
                            errorMessage
                        );
                    }


                    // إغلاق نافذة التأكيد
                    closeDeleteConfirm();


                    // رسالة النظام العامة
                    if (
                        typeof showSystemToast ===
                        'function'
                    ) {

                        showSystemToast(
                            data.message ||
                            'تم حذف الحساب بنجاح',
                            'success'
                        );
                    }


                    // تحديث الجدول
                    await reloadAccountsTable();

                } catch (error) {

                    console.error(
                        'خطأ في حذف الحساب:',
                        error
                    );


                    closeDeleteConfirm();


                    if (
                        typeof showSystemToast ===
                        'function'
                    ) {

                        showSystemToast(
                            error.message ||
                            'حدث خطأ أثناء حذف الحساب',
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
    // طلب حذف حساب (مع فحص الأبناء محلياً أولاً)
    // =====================================================

    window.deleteAccount = function (id) {

        // فحص محلي: هل يوجد حسابات أبناء في الجدول؟
        if (hasChildAccounts(id)) {

            if (typeof showSystemToast === 'function') {

                showSystemToast(
                    'لا يمكن حذف هذا الحساب لأنه حساب أب لحسابات فرعية مرتبطة به، يرجى حذف الحسابات الفرعية أولاً',
                    'danger'
                );
            }

            return;
        }

        openDeleteConfirm(id);
    };


    // =====================================================
    // فتح / إغلاق الحسابات الأبناء
    // =====================================================

    function toggleAccountTree(id) {

        const parentRow =
            accountsTbody.querySelector(
                `tr.account-row[data-id="${id}"]`
            );


        if (!parentRow) {
            return;
        }


        const isOpen =
            parentRow.dataset.open === 'true';


        parentRow.dataset.open =
            isOpen ? 'false' : 'true';


        // تحديث الأيقونة
        const toggleButton =
            parentRow.querySelector(
                '.tree-toggle'
            );


        if (toggleButton) {

            const icon =
                toggleButton.querySelector('i');


            if (icon) {

                icon.className =
                    isOpen
                        ? 'bi bi-chevron-left'
                        : 'bi bi-chevron-down';
            }
        }


        refreshAccountTree();
    }


    // =====================================================
    // تحديث حالة ظهور الشجرة
    // =====================================================

    function refreshAccountTree() {

        const rows =
            Array.from(
                accountsTbody.querySelectorAll(
                    'tr.account-row'
                )
            );


        if (!rows.length) {
            return;
        }


        const openIds =
            new Set(
                rows
                    .filter(
                        row =>
                            row.dataset.open ===
                            'true'
                    )
                    .map(
                        row =>
                            String(
                                row.dataset.id
                            )
                    )
            );


        const visibleRows = [];


        rows.forEach(function (row) {

            const parentId =
                row.dataset.parent;


            let visible = true;


            if (parentId) {

                let currentParent =
                    String(parentId);


                while (
                    currentParent &&
                    currentParent !== '0'
                ) {

                    const parentRow =
                        rows.find(
                            item =>
                                String(
                                    item.dataset.id
                                ) ===
                                currentParent
                        );


                    if (!parentRow) {
                        break;
                    }


                    if (
                        !openIds.has(
                            currentParent
                        )
                    ) {

                        visible = false;

                        break;
                    }


                    currentParent =
                        parentRow.dataset.parent;
                }
            }


            row.dataset.treeVisible =
                visible
                    ? 'true'
                    : 'false';


            if (visible) {
                visibleRows.push(row);
            }
        });


        applyPagination();
    }


    // =====================================================
    // ترقيم الصفوف
    // =====================================================

    function refreshRowNumbers() {

        const rows =
            Array.from(
                accountsTbody.querySelectorAll(
                    'tr.account-row'
                )
            )
            .filter(
                row =>
                    row.dataset.treeVisible !==
                    'false'
            );


        rows.forEach(
            function (row, index) {

                const firstCell =
                    row.querySelector(
                        'td:first-child'
                    );


                if (firstCell) {

                    firstCell.textContent =
                        (
                            (
                                currentPage - 1
                            ) *
                            rowsPerPage
                        ) +
                        index +
                        1;
                }
            }
        );
    }


    // =====================================================
    // Pagination
    // =====================================================

    function applyPagination() {

        const rows =
            Array.from(
                accountsTbody.querySelectorAll(
                    'tr.account-row'
                )
            )
            .filter(
                row =>
                    row.dataset.treeVisible !==
                    'false'
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
            (
                currentPage - 1
            ) *
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
            }
        );


        // إخفاء الصفوف الموجودة خلف الشجرة
        accountsTbody
            .querySelectorAll(
                'tr.account-row'
            )
            .forEach(
                function (row) {

                    if (
                        row.dataset.treeVisible ===
                        'false'
                    ) {

                        row.style.display =
                            'none';
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


    // =====================================================
    // رسم Pagination
    // =====================================================

    function renderPagination(
        totalRows,
        totalPages,
        start,
        end
    ) {

        const paginationList =
            document.getElementById(
                'accountsPaginationList'
            );


        const paginationInfo =
            document.getElementById(
                'accountsPaginationInfo'
            );


        if (paginationInfo) {

            if (totalRows === 0) {

                paginationInfo.textContent =
                    'عرض 0-0 من 0 حساب';

            } else {

                paginationInfo.textContent =
                    `عرض ${start + 1}-${Math.min(end, totalRows)} من ${totalRows} حساب`;
            }
        }


        if (!paginationList) {
            return;
        }


        paginationList.innerHTML =
            '';


        if (totalPages <= 1) {
            return;
        }


        // السابق
        const previousItem =
            document.createElement('li');


        previousItem.className =
            `page-item ${
                currentPage === 1
                    ? 'disabled'
                    : ''
            }`;


        previousItem.innerHTML =
            `
                <button
                    type="button"
                    class="page-link"
                    data-page="${currentPage - 1}"
                >
                    السابق
                </button>
            `;


        paginationList.appendChild(
            previousItem
        );


        // أرقام الصفحات
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


            item.innerHTML =
                `
                    <button
                        type="button"
                        class="page-link"
                        data-page="${page}"
                    >
                        ${page}
                    </button>
                `;


            paginationList.appendChild(
                item
            );
        }


        // التالي
        const nextItem =
            document.createElement('li');


        nextItem.className =
            `page-item ${
                currentPage === totalPages
                    ? 'disabled'
                    : ''
            }`;


        nextItem.innerHTML =
            `
                <button
                    type="button"
                    class="page-link"
                    data-page="${currentPage + 1}"
                >
                    التالي
                </button>
            `;


        paginationList.appendChild(
            nextItem
        );
    }


    // =====================================================
    // الضغط على Pagination
    // =====================================================

    const paginationList =
        document.getElementById(
            'accountsPaginationList'
        );


    if (paginationList) {

        paginationList.addEventListener(
            'click',
            function (event) {

                const button =
                    event.target.closest(
                        '[data-page]'
                    );


                if (!button) {
                    return;
                }


                const page =
                    parseInt(
                        button.dataset.page,
                        10
                    );


                if (!page || page < 1) {
                    return;
                }


                currentPage =
                    page;


                applyPagination();
            }
        );
    }


    // =====================================================
    // البحث
    // =====================================================

    let searchTimer = null;


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
// تحميل الحسابات
// =====================================================

async function reloadAccountsTable() {

    try {

        const params =
            new URLSearchParams();


        if (
            searchCode &&
            searchCode.value.trim()
        ) {

            params.set(
                'search_code',
                searchCode.value.trim()
            );
        }


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
            searchNature &&
            searchNature.value !== ''
        ) {

            params.set(
                'search_nature',
                searchNature.value
            );
        }


        const query =
            params.toString();


        const url =
            query
                ? `/settings/accounting/chartOfAccounts/list?${query}`
                : '/settings/accounting/chartOfAccounts/list';


        const response =
            await fetch(
                url,
                {
                    method: 'GET',

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
                'تعذر تحميل الحسابات'
            );
        }

        // =================================================
        // منع تكرار رأس الجدول وعناصر الـ Pagination برمجياً
        // =================================================
        const parser = new DOMParser();
        const doc = parser.parseFromString(data.html || '', 'text/html');
        const targetBody = doc.getElementById('accountsTreeBody') || doc.querySelector('tbody');

        if (targetBody) {
            accountsTbody.innerHTML = targetBody.innerHTML;
        } else {
            const parsedRows = doc.body.querySelectorAll('tr');
            if (parsedRows.length > 0) {
                accountsTbody.innerHTML = '';
                parsedRows.forEach(function (row) {
                    accountsTbody.appendChild(row);
                });
            } else {
                accountsTbody.innerHTML = data.html || '';
            }
        }


        currentPage = 1;


        // =================================================
        // إعادة بناء حالة الشجرة
        // =================================================

        const rows =
            Array.from(
                accountsTbody.querySelectorAll(
                    'tr.account-row'
                )
            );


        // -------------------------------------------------
        // بدون بحث
        // -------------------------------------------------

        if (!query) {

            isSearchActive = false;


            // تعيين جميع الصفوف كمغلقة ثم عرض الجذور فقط
            rows.forEach(
                function (row) {

                    row.dataset.open =
                        'false';
                }
            );


            // حساب الرؤية: الجذور فقط ستظهر لعدم وجود آباء مفتوحة
            refreshAccountTree();

            return;
        }


        // -------------------------------------------------
        // مع البحث
        // -------------------------------------------------

        isSearchActive = true;


        const matchedRows =
            rows.filter(
                function (row) {

                    return (
                        row.textContent
                            .toLowerCase()
                            .includes(
                                (
                                    searchCode?.value ||
                                    searchName?.value ||
                                    ''
                                )
                                .toLowerCase()
                            )
                    );
                }
            );


        const pathIds =
            new Set();


        matchedRows.forEach(
            function (row) {

                let current =
                    row;


                while (current) {

                    const id =
                        current.dataset.id;


                    if (id) {
                        pathIds.add(
                            String(id)
                        );
                    }


                    const parentId =
                        current.dataset.parent;


                    if (
                        !parentId ||
                        parentId === '0'
                    ) {
                        break;
                    }


                    current =
                        rows.find(
                            item =>
                                String(
                                    item.dataset.id
                                ) ===
                                String(
                                    parentId
                                )
                        );
                }
            }
        );


        rows.forEach(
            function (row) {

                const id =
                    String(
                        row.dataset.id
                    );


                const shouldShow =
                    pathIds.has(id);


                row.dataset.treeVisible =
                    shouldShow
                        ? 'true'
                        : 'false';


                // فتح الأبناء للوصول للنتيجة
                if (
                    shouldShow &&
                    row.querySelector(
                        '.tree-toggle'
                    )
                ) {

                    const hasMatchedChild =
                        rows.some(
                            child => {

                                if (
                                    String(
                                        child.dataset.parent
                                    ) !== id
                                ) {
                                    return false;
                                }


                                return pathIds.has(
                                    String(
                                        child.dataset.id
                                    )
                                );
                            }
                        );


                    row.dataset.open =
                        hasMatchedChild
                            ? 'true'
                            : 'false';


                    const icon =
                        row.querySelector(
                            '.tree-toggle i'
                        );


                    if (icon) {

                        icon.className =
                            hasMatchedChild
                                ? 'bi bi-chevron-down'
                                : 'bi bi-chevron-left';
                    }
                }
            }
        );


        applyPagination();


    } catch (error) {

        console.error(
            'خطأ في تحميل الحسابات:',
            error
        );


        if (
            typeof showSystemToast ===
            'function'
        ) {

            showSystemToast(
                error.message ||
                'حدث خطأ أثناء تحميل الحسابات',
                'danger'
            );
        }
    }
}

    // =====================================================
    // أحداث الجدول
    // =====================================================

    accountsTbody.addEventListener(
        'click',
        function (event) {

            // -----------------------------------------------
            // فتح / إغلاق الشجرة
            // -----------------------------------------------

            const toggle =
                event.target.closest(
                    '.tree-toggle'
                );


            if (toggle) {

                event.preventDefault();


                // في وضع البحث نمنع التوسيع/الطي للحفاظ على النتائج
                if (isSearchActive) {
                    return;
                }


                toggleAccountTree(
                    toggle.dataset.id
                );

                return;
            }


            // -----------------------------------------------
            // تعديل
            // -----------------------------------------------

            const editButton =
                event.target.closest(
                    '.edit-account'
                );


            if (editButton) {

                event.preventDefault();

                editAccount(
                    editButton.dataset.id
                );

                return;
            }


            // -----------------------------------------------
            // حذف
            // -----------------------------------------------

            const deleteButton =
                event.target.closest(
                    '.delete-account'
                );


            if (deleteButton) {

                event.preventDefault();

                deleteAccount(
                    deleteButton.dataset.id
                );

                return;
            }
        }
    );


    // =====================================================
    // إعادة ضبط النموذج
    // =====================================================

    function resetAccountForm() {

        formMode = 'add';

        editingAccountId = null;


        accountForm.reset();


        if (accountID) {
            accountID.value = '';
        }


        setFormMethod('POST');


        enableAllParentOptions();


        // -------------------------------------------------
        // [هنا تم دمج التعديل الثاني]
        // إعادة تفعيل جميع العناصر في نافذة البحث وتفريغ حقل العرض
        // -------------------------------------------------
        if (typeof window.updateParentSearchDisabledState === 'function') {
            window.updateParentSearchDisabledState(null);
        }

        const displayField = document.getElementById('accParentDisplay');
        if (displayField) {
            displayField.value = '';
        }


        if (accountModalTitle) {

            accountModalTitle.textContent =
                'إضافة حساب';
        }


        if (saveAccountBtn) {

            saveAccountBtn.textContent =
                'حفظ';

            saveAccountBtn.disabled =
                false;
        }


        if (accLevel) {
            accLevel.value = 1;
        }
    }


    // =====================================================
    // عند إغلاق مودال الحساب
    // =====================================================

    if (accountModalElement) {

        accountModalElement.addEventListener(
            'hidden.bs.modal',
            function () {

                resetAccountForm();
            }
        );
    }


    // =====================================================
    // حماية النصوص HTML
    // =====================================================

    window.escapeHtml = function (text) {

        if (text === null || text === undefined) {
            return '';
        }


        const div =
            document.createElement('div');


        div.textContent =
            String(text);


        return div.innerHTML;
    };


    // =====================================================
    // نافذة البحث عن الحساب الأب
    // =====================================================

    const accParentDisplay =
        document.getElementById('accParentDisplay');

    const parentSearchModalElement =
        document.getElementById('parentAccountSearchModal');

    const parentSearchInput =
        document.getElementById('parentAccountSearchInput');

    const parentSearchList =
        document.getElementById('parentAccountSearchList');


    let parentSearchModal = null;

    if (parentSearchModalElement) {

        parentSearchModal =
            bootstrap.Modal.getOrCreateInstance(
                parentSearchModalElement
            );
    }


    // -----------------------------------------------------
    // فتح النافذة عند الضغط على حقل العرض
    // -----------------------------------------------------

    if (accParentDisplay) {

        accParentDisplay.addEventListener(
            'click',
            function () {

                if (parentSearchModal) {

                    parentSearchModal.show();

                    // تصفير حقل البحث وإظهار جميع النتائج
                    if (parentSearchInput) {

                        parentSearchInput.value = '';

                        filterParentSearchList('');
                    }
                }
            }
        );
    }


    // -----------------------------------------------------
    // التركيز على حقل البحث عند فتح النافذة
    // -----------------------------------------------------

    if (parentSearchModalElement) {

        parentSearchModalElement.addEventListener(
            'shown.bs.modal',
            function () {

                if (parentSearchInput) {

                    parentSearchInput.focus();
                }
            }
        );
    }


    // -----------------------------------------------------
    // فلترة النتائج أثناء البحث
    // -----------------------------------------------------

    function filterParentSearchList(query) {

        if (!parentSearchList) {
            return;
        }

        const term =
            (query || '')
                .trim()
                .toLowerCase();

        const items =
            parentSearchList.querySelectorAll(
                '.parent-search-item'
            );

        items.forEach(function (item) {

            const code =
                (item.dataset.code || '').toLowerCase();

            const name =
                (item.dataset.name || '').toLowerCase();

            const isMatch =
                !term ||
                code.includes(term) ||
                name.includes(term);

            item.style.display =
                isMatch ? '' : 'none';
        });
    }


    if (parentSearchInput) {

        parentSearchInput.addEventListener(
            'input',
            function () {

                filterParentSearchList(
                    parentSearchInput.value
                );
            }
        );
    }


    // -----------------------------------------------------
    // اختيار حساب من قائمة النتائج
    // -----------------------------------------------------

    if (parentSearchList) {

        parentSearchList.addEventListener(
            'click',
            function (event) {

                const item =
                    event.target.closest(
                        '.parent-search-item'
                    );

                if (!item) {
                    return;
                }

                // تجاهل العناصر المعطلة
                if (item.classList.contains('disabled')) {
                    return;
                }

                const selectedId =
                    item.dataset.id || '';

                const selectedCode =
                    item.dataset.code || '';

                const selectedName =
                    item.dataset.name || '';


                // تعبئة حقل العرض
                if (accParentDisplay) {

                    if (selectedId) {

                        accParentDisplay.value =
                            selectedCode + ' - ' + selectedName;

                    } else {

                        accParentDisplay.value = '';
                    }
                }


                // تعبئة الحقل المخفي وإطلاق حدث change
                if (accParent) {

                    accParent.value = selectedId;

                    accParent.dispatchEvent(
                        new Event('change', {
                            bubbles: true
                        })
                    );
                }


                // إغلاق النافذة
                if (parentSearchModal) {

                    parentSearchModal.hide();
                }
            }
        );
    }


    // -----------------------------------------------------
    // مزامنة حقل العرض عند تعديل الحساب أو إعادة الضبط
    // -----------------------------------------------------

    function syncParentDisplay() {

        if (!accParentDisplay || !accParent) {
            return;
        }

        const selectedOption =
            accParent.options[accParent.selectedIndex];

        if (
            !accParent.value ||
            !selectedOption ||
            !selectedOption.value
        ) {

            accParentDisplay.value = '';

            return;
        }

        const code =
            selectedOption.dataset.code || '';

        const name =
            selectedOption.dataset.name || selectedOption.textContent.trim();

        accParentDisplay.value =
            code
                ? (code + ' - ' + name)
                : name;
    }


    // مراقبة تغييرات الحقل المخفي لتحديث حقل العرض تلقائياً
    if (accParent) {

        accParent.addEventListener(
            'change',
            function () {

                syncParentDisplay();
            }
        );
    }


    // مزامنة عند تحميل الصفحة أول مرة
    syncParentDisplay();


    // -----------------------------------------------------
    // تعطيل الحساب الحالي في نافذة البحث (وضع التعديل)
    // -----------------------------------------------------

    window.updateParentSearchDisabledState = function (currentId) {

        if (!parentSearchList) {
            return;
        }

        const items =
            parentSearchList.querySelectorAll(
                '.parent-search-item'
            );

        items.forEach(function (item) {

            if (
                currentId &&
                String(item.dataset.id) === String(currentId)
            ) {

                item.classList.add('disabled', 'text-muted');
                item.style.pointerEvents = 'none';
                item.style.opacity = '0.5';

            } else {

                item.classList.remove('disabled', 'text-muted');
                item.style.pointerEvents = '';
                item.style.opacity = '';
            }
        });
    };


    // =====================================================
    // تهيئة الشجرة
    // =====================================================

    function initializeAccountTree() {

        const rows =
            Array.from(
                accountsTbody.querySelectorAll(
                    'tr.account-row'
                )
            );


        rows.forEach(
            function (row) {

                row.dataset.open =
                    'false';

                // إزالة أي تعيين سابق للرؤية
                row.dataset.treeVisible =
                    '';
            }
        );


        currentPage = 1;

        // حساب الرؤية: سيتم إظهار الجذور فقط
        refreshAccountTree();
    }


    // =====================================================
    // تشغيل التهيئة
    // =====================================================

    initializeAccountTree();

});