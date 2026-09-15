/**
 * chartOfAccounts.js
 * إدارة دليل الحسابات
 */

(function () {

    'use strict';


    // =====================================================
    // تشغيل النظام
    // =====================================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }


    // =====================================================
    // Initialization
    // =====================================================

    function init() {

        // =================================================
        // عناصر الصفحة
        // =================================================

        const addAccountBtn = document.getElementById('addAccountBtn');
        const accountForm = document.getElementById('accountForm');
        const accountModalElement = document.getElementById('addAccountModal');
        const saveAccountBtn = document.getElementById('saveAccountBtn');
        const accountModalTitle = document.getElementById('accountModalTitle');
        const accountsTreeBody = document.getElementById('accountsTreeBody');

        const searchCode = document.getElementById('searchCode');
        const searchName = document.getElementById('searchName');

        const accountID = document.getElementById('accountID');
        const accTypeID = document.getElementById('accTypeID');
        const accCode = document.getElementById('accCode');
        const accParent = document.getElementById('accParent');
        const accName = document.getElementById('accName');
        const nature = document.getElementById('nature');
        const accLevel = document.getElementById('accLevel');
        const IsActive = document.getElementById('IsActive');
        const isPostable = document.getElementById('isPostable');
        const accParentDisplay = document.getElementById('accParentDisplay');

        const accountRowTemplate = document.getElementById('accountRowTemplate');
        const accountsLoadingTemplate = document.getElementById('accountsLoadingTemplate');
        const accountsEmptyTemplate = document.getElementById('accountsEmptyTemplate');
        const accountsErrorTemplate = document.getElementById('accountsErrorTemplate');

        const parentAccountItemTemplate = document.getElementById('parentAccountItemTemplate');
        const parentRootAccountItemTemplate = document.getElementById('parentRootAccountItemTemplate');

        const paginationPageTemplate = document.getElementById('paginationPageTemplate');
        const paginationPreviousTemplate = document.getElementById('paginationPreviousTemplate');
        const paginationNextTemplate = document.getElementById('paginationNextTemplate');
        const paginationEllipsisTemplate = document.getElementById('paginationEllipsisTemplate');

        const accountsPaginationInfo = document.getElementById('accountsPaginationInfo');
        const accountsPaginationList = document.getElementById('accountsPaginationList');

        const analyticalAccountsModalElement = document.getElementById('analyticalAccountsModal');
        const analyticalAccountsParent = document.getElementById('analyticalAccountsParent');
        const analyticalAccountsBody = document.getElementById('analyticalAccountsBody');
        const analyticalSearchCode = document.getElementById('analyticalSearchCode');
        const analyticalSearchName = document.getElementById('analyticalSearchName');
        const analyticalPaginationInfo = document.getElementById('analyticalPaginationInfo');
        const analyticalPaginationList = document.getElementById('analyticalPaginationList');
        const analyticalAccountRowTemplate = document.getElementById('analyticalAccountRowTemplate');
        const analyticalLoadingTemplate = document.getElementById('analyticalLoadingTemplate');
        const analyticalEmptyTemplate = document.getElementById('analyticalEmptyTemplate');
        const analyticalErrorTemplate = document.getElementById('analyticalErrorTemplate');

        const deleteConfirmModalElement = document.getElementById('deleteConfirmModal');
        const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
        const deleteCancelBtn = document.getElementById('deleteCancelBtn');
        const deleteCancelBtnFooter = document.getElementById('deleteCancelBtnFooter');

        const openParentAccountSearchBtn = document.getElementById('openParentAccountSearchBtn');
        const parentAccountSearchModalElement = document.getElementById('parentAccountSearchModal');
        const parentAccountSearchInput = document.getElementById('parentAccountSearchInput');
        const parentAccountSearchList = document.getElementById('parentAccountSearchList');



        let analyticalParentSystemKey = '';
        // =================================================
        // التحقق الأساسي
        // =================================================

        if (!addAccountBtn || !accountForm || !accountModalElement) {
            console.error('[ChartOfAccounts] عناصر الصفحة الأساسية غير موجودة.');
            return;
        }

        if (typeof bootstrap === 'undefined') {
            console.error('[ChartOfAccounts] Bootstrap غير محمل.');
            return;
        }


        // =================================================
        // التحقق من Templates الأساسية
        // =================================================

        const requiredTemplates = [
            ['accountRowTemplate', accountRowTemplate],
            ['accountsLoadingTemplate', accountsLoadingTemplate],
            ['accountsEmptyTemplate', accountsEmptyTemplate],
            ['accountsErrorTemplate', accountsErrorTemplate],
            ['parentAccountItemTemplate', parentAccountItemTemplate],
            ['parentRootAccountItemTemplate', parentRootAccountItemTemplate],
            ['paginationPageTemplate', paginationPageTemplate],
            ['paginationPreviousTemplate', paginationPreviousTemplate],
            ['paginationNextTemplate', paginationNextTemplate],
            ['paginationEllipsisTemplate', paginationEllipsisTemplate]
        ];

        requiredTemplates.forEach(function ([name, template]) {
            if (!template) {
                console.error(`[ChartOfAccounts] Template غير موجود: ${name}`);
            }
        });


        // =================================================
        // Modals
        // =================================================

        const accountModal = bootstrap.Modal.getOrCreateInstance(accountModalElement);

        const deleteConfirmModal = deleteConfirmModalElement
            ? bootstrap.Modal.getOrCreateInstance(deleteConfirmModalElement)
            : null;

        const parentAccountSearchModal = parentAccountSearchModalElement
            ? bootstrap.Modal.getOrCreateInstance(parentAccountSearchModalElement)
            : null;

        const analyticalAccountsModal = analyticalAccountsModalElement
            ? bootstrap.Modal.getOrCreateInstance(analyticalAccountsModalElement)
            : null;


        // =================================================
        // State
        // =================================================

        let formMode = 'add';
        let editingAccountId = null;
        let deletingAccountId = null;
        let accounts = [];
        let currentPage = 1;
        const pageSize = 10;
        let searchTimer = null;
        let savedEditData = null;

        let selectedAnalyticalParentId = null;
        let analyticalCurrentPage = 1;
        const analyticalPageSize = 10;
        let analyticalSearchTimer = null;


        // =================================================
        // CSRF
        // =================================================

        function getCsrfToken() {
            const token = document.querySelector('meta[name="csrf-token"]');
            return token ? token.getAttribute('content') : '';
        }

        function jsonHeaders() {
            return {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            };
        }


        // =================================================
        // رسالة النظام
        // =================================================

        function showToast(message, type = 'success') {

            const toastType = type === 'error' ? 'danger' : type;

            if (typeof showSystemToast === 'function') {

                showSystemToast(message, toastType);

                return;
            }

            if (toastType === 'danger') {
                console.error(message);
            } else {
                console.log(message);
            }
        }


        // =================================================
        // رسالة Backend
        // =================================================

        async function getResponseMessage(response, fallback) {
            try {
                const data = await response.json();
                if (data?.message) {
                    return data.message;
                }
                if (data?.errors && typeof data.errors === 'object') {
                    const firstError = Object.values(data.errors)[0];
                    if (Array.isArray(firstError) && firstError.length) {
                        return firstError[0];
                    }
                }
            } catch (error) {
                // تجاهل
            }
            return fallback;
        }


        // =================================================
        // Template Helpers
        // =================================================

        function cloneTemplate(template) {
            if (!template || !template.content) {
                return null;
            }
            const element = template.content.firstElementChild;
            return element ? element.cloneNode(true) : null;
        }

        function showTemplateState(container, template) {
            if (!container || !template) {
                return;
            }
            const element = cloneTemplate(template);
            if (!element) {
                console.error('[ChartOfAccounts] تعذر استنساخ Template.');
                return;
            }
            container.replaceChildren(element);
        }


        // =================================================
        // حالات الشجرة
        // =================================================

        function showTreeState(state) {
            if (!accountsTreeBody) return;

            let template = null;

            switch (state) {
                case 'loading':
                    template = accountsLoadingTemplate;
                    break;
                case 'empty':
                    template = accountsEmptyTemplate;
                    break;
                case 'error':
                    template = accountsErrorTemplate;
                    break;
                default:
                    console.error('[ChartOfAccounts] حالة شجرة غير معروفة:', state);
                    return;
            }

            showTemplateState(accountsTreeBody, template);
        }


        // =================================================
        // حالات الحسابات التحليلية
        // =================================================

        function showAnalyticalState(state) {
            if (!analyticalAccountsBody) return;

            let template = null;

            switch (state) {
                case 'loading':
                    template = analyticalLoadingTemplate;
                    break;
                case 'empty':
                    template = analyticalEmptyTemplate;
                    break;
                case 'error':
                    template = analyticalErrorTemplate;
                    break;
                default:
                    console.error('[ChartOfAccounts] حالة تحليلية غير معروفة:', state);
                    return;
            }

            showTemplateState(analyticalAccountsBody, template);
        }


        // =================================================
        // تحديث مستوى الحساب
        // =================================================

        function updateAccountLevel() {
            if (!accLevel) {
                return;
            }

            const parentId = accParent?.value || '';

            if (!parentId) {
                accLevel.value = '1';
                accLevel.readOnly = true;
                return;
            }

            if (!accParent) {
                accLevel.value = '1';
                accLevel.readOnly = true;
                return;
            }

            const parentOption = Array.from(accParent.options).find(
                function (option) {
                    return String(option.value) === String(parentId);
                }
            );

            let parentLevel = 1;
            if (parentOption && parentOption.dataset && parentOption.dataset.level !== undefined) {
                const parsed = Number(parentOption.dataset.level);
                if (Number.isFinite(parsed)) {
                    parentLevel = parsed;
                }
            }

            accLevel.value = String(parentLevel + 1);
            accLevel.readOnly = true;
        }


        // =================================================
        // ✅ تحميل بيانات الحساب الأب الكاملة من السيرفر
        // يجلب accTypeID و nature الحقيقيين
        // =================================================

      async function syncParentAccountData(parentId) {

    if (!parentId) {
        return;
    }

    try {
        console.log('🔍 [Diagnostic] Fetching parent:', parentId);

        const response = await fetch(
            `/settings/accounting/chartOfAccounts/${parentId}`,
            { method: 'GET', headers: jsonHeaders() }
        );

        console.log('🔍 [Diagnostic] Response status:', response.status);

        if (!response.ok) {
            console.error('❌ [Diagnostic] Response NOT OK');
            return;
        }

        const data = await response.json();

        // ✅ طباعة كل الاستجابة
        console.log('🔍 [Diagnostic] Full response:', data);
        console.log('🔍 [Diagnostic] account object:', data.account);

        if (!data.success || !data.account) {
            console.error('❌ [Diagnostic] No account in response');
            return;
        }

        const parentAccount = data.account;

        console.log('🔍 [Diagnostic] accTypeID:', parentAccount.accTypeID);
        console.log('🔍 [Diagnostic] nature:', parentAccount.nature);
        console.log('🔍 [Diagnostic] All keys:', Object.keys(parentAccount));

        // ✅ مزامنة نوع الحساب
        if (accTypeID && parentAccount.accTypeID !== undefined && parentAccount.accTypeID !== null) {
            accTypeID.value = String(parentAccount.accTypeID);
            console.log('✅ [Diagnostic] Type set to:', accTypeID.value);
        }

        // ✅ مزامنة طبيعة الحساب
        if (nature && parentAccount.nature !== undefined && parentAccount.nature !== null) {
            nature.value = String(parentAccount.nature);
            console.log('✅ [Diagnostic] Nature set to:', nature.value);
        }

    } catch (error) {
        console.error('❌ [Diagnostic] Error:', error);
    }
}


        // =================================================
        // تحميل الرقم التالي
        // =================================================

        async function loadNextAccountCode(parentId) {

            if (!parentId) {
                return;
            }

            try {
                const response = await fetch(
                    `/settings/accounting/chartOfAccounts/next-code/${parentId}`,
                    { method: 'GET', headers: jsonHeaders() }
                );

                if (!response.ok) {
                    throw new Error(
                        await getResponseMessage(response, 'تعذر توليد رقم الحساب')
                    );
                }

                const data = await response.json();

                if (data.success && data.code && accCode) {
                    accCode.value = String(data.code);
                }
            } catch (error) {
                console.error(error);
                showToast(error.message || 'تعذر توليد رقم الحساب', 'error');
            }
        }


        // =================================================
        // مزامنة عرض الحساب الأب
        // =================================================

        function syncParentDisplay() {
            if (!accParent || !accParentDisplay) {
                return;
            }

            const option = accParent.options[accParent.selectedIndex];

            if (!option || !option.value) {
                accParentDisplay.value = 'لا يوجد (حساب رئيسي)';
                return;
            }

            accParentDisplay.value = option.textContent;
        }


        // =================================================
        // تغيير الحساب الأب
        // =================================================

        accParent?.addEventListener('change', async function () {
            updateAccountLevel();
            syncParentDisplay();

            if (!accParent.value) {
                if (accTypeID) accTypeID.value = '0';
                if (nature) nature.value = '0';

                if (formMode === 'add' && accCode) {
                    accCode.value = '';
                }

                if (accCode) accCode.readOnly = false;
                return;
            }

            const selectedOption = accParent.options[accParent.selectedIndex];
            if (!selectedOption) return;

            // مزامنة النوع من dataset (احتياطي)
            const parentType = selectedOption.dataset.type ?? '0';
            if (accTypeID) {
                accTypeID.value = String(parentType || '0');
            }

            const parentNature = selectedOption.dataset.nature || '';
            if (nature && parentNature !== '') {
                nature.value = String(parentNature);
            }

            // توليد الرقم
            if (accCode) accCode.readOnly = true;
            await loadNextAccountCode(accParent.value);

            // ✅ جلب البيانات الكاملة (لضمان النوع والطبيعة الصحيحين)
            await syncParentAccountData(accParent.value);
        });


        // =================================================
        // تحميل الحسابات الأب
        // =================================================

        async function loadParentAccounts() {

            if (!accParent) {
                return;
            }

            const currentParentId =
                String(accParent.value || '');

            try {

                const response =
                    await fetch(
                        '/settings/accounting/chartOfAccounts/tree',
                        {
                            method: 'GET',
                            headers: jsonHeaders()
                        }
                    );

                if (!response.ok) {

                    throw new Error(
                        await getResponseMessage(
                            response,
                            'تعذر تحميل الحسابات الأب'
                        )
                    );
                }

                const data =
                    await response.json();

                if (!data.success) {

                    throw new Error(
                        data.message ||
                        'تعذر تحميل الحسابات الأب'
                    );
                }

                const parentAccounts =
                    Array.isArray(data.accounts)
                        ? data.accounts
                        : [];


                const availableParentAccounts =
                    parentAccounts.filter(
                        function (account) {

                            if (
                                formMode === 'edit' &&
                                editingAccountId !== null &&
                                String(
                                    account.accountID
                                ) ===
                                String(
                                    editingAccountId
                                )
                            ) {
                                return false;
                            }

                            return true;
                        }
                    );


                accParent.replaceChildren();


                const rootOption =
                    document.createElement(
                        'option'
                    );

                rootOption.value = '';

                rootOption.textContent =
                    'لا يوجد (حساب رئيسي)';

                rootOption.dataset.type =
                    '0';

                rootOption.dataset.level =
                    '0';

                rootOption.dataset.nature =
                    '';

                rootOption.dataset.postable =
                    '0';

                rootOption.dataset.systemKey =
                    '';

                accParent.appendChild(
                    rootOption
                );


                if (parentAccountSearchList) {

                    parentAccountSearchList.replaceChildren();


                    const rootItem =
                        cloneTemplate(
                            parentRootAccountItemTemplate
                        );

                    if (rootItem) {

                        rootItem.dataset.id =
                            '';

                        rootItem.dataset.code =
                            '';

                        rootItem.dataset.name =
                            'لا يوجد (حساب رئيسي)';

                        rootItem.dataset.level =
                            '0';

                        rootItem.dataset.type =
                            '0';

                        rootItem.dataset.nature =
                            '';

                        rootItem.dataset.postable =
                            '0';

                        rootItem.dataset.systemKey =
                            '';

                        parentAccountSearchList.appendChild(
                            rootItem
                        );
                    }


                    availableParentAccounts.forEach(
                        function (account) {

                            const item =
                                cloneTemplate(
                                    parentAccountItemTemplate
                                );

                            if (!item) {
                                return;
                            }


                            item.dataset.id =
                                String(
                                    account.accountID
                                );

                            item.dataset.code =
                                String(
                                    account.accCode ?? ''
                                );

                            item.dataset.name =
                                String(
                                    account.accName ?? ''
                                );

                            item.dataset.level =
                                String(
                                    account.accLevel ?? 1
                                );

                            item.dataset.type =
                                String(
                                    account.accTypeID ?? ''
                                );

                            item.dataset.nature =
                                String(
                                    account.nature ?? ''
                                );

                            item.dataset.postable =
                                String(
                                    account.isPostable ?? 0
                                );

                            item.dataset.systemKey =
                                String(
                                    account.system_key ?? ''
                                );


                            const nameElement =
                                item.querySelector(
                                    '.parent-account-name'
                                );

                            if (nameElement) {

                                nameElement.textContent =
                                    account.accName ?? '';
                            }


                            const codeElement =
                                item.querySelector(
                                    '.parent-account-code'
                                );

                            if (codeElement) {

                                codeElement.textContent =
                                    account.accCode ?? '';
                            }


                            const levelElement =
                                item.querySelector(
                                    '.parent-account-level'
                                );

                            if (levelElement) {

                                levelElement.textContent =
                                    `المستوى ${
                                        account.accLevel ?? 1
                                    }`;
                            }


                            parentAccountSearchList.appendChild(
                                item
                            );
                        }
                    );
                }


                availableParentAccounts.forEach(
                    function (account) {

                        const option =
                            document.createElement(
                                'option'
                            );

                        option.value =
                            String(
                                account.accountID
                            );

                        option.textContent =
                            `${
                                account.accCode ?? ''
                            } - ${
                                account.accName ?? ''
                            }`;

                        option.dataset.code =
                            String(
                                account.accCode ?? ''
                            );

                        option.dataset.name =
                            String(
                                account.accName ?? ''
                            );

                        option.dataset.level =
                            String(
                                account.accLevel ?? 1
                            );

                        option.dataset.type =
                            String(
                                account.accTypeID ?? ''
                            );

                        option.dataset.nature =
                            String(
                                account.nature ?? ''
                            );

                        option.dataset.postable =
                            String(
                                account.isPostable ?? 0
                            );

                        option.dataset.systemKey =
                            String(
                                account.system_key ?? ''
                            );

                        accParent.appendChild(
                            option
                        );
                    }
                );


                if (currentParentId !== '') {

                    const restoredOption =
                        Array.from(
                            accParent.options
                        ).find(
                            function (option) {

                                return String(
                                    option.value
                                ) ===
                                currentParentId;
                            }
                        );

                    if (restoredOption) {

                        restoredOption.selected =
                            true;

                        if (accTypeID) {

                            const parentType =
                                restoredOption.dataset.type ||
                                '';

                            if (parentType !== '') {

                                accTypeID.value =
                                    String(
                                        parentType
                                    );
                            }
                        }


                        if (nature) {

                            const parentNature =
                                restoredOption.dataset.nature ||
                                '';

                            if (parentNature !== '') {

                                nature.value =
                                    String(
                                        parentNature
                                    );
                            }
                        }


                        updateAccountLevel();

                        syncParentDisplay();

                    } else {

                        accParent.selectedIndex = 0;

                        syncParentDisplay();
                    }

                } else {

                    accParent.selectedIndex = 0;

                    syncParentDisplay();
                }


            } catch (error) {

                console.error(error);

                showToast(
                    error.message ||
                    'تعذر تحميل الحسابات الأب',
                    'error'
                );
            }
        }


        // =================================================
        // تعطيل الحساب الحالي كأب
        // =================================================

        function disableCurrentAccountAsParent() {
            if (formMode !== 'edit' || !editingAccountId || !accParent) {
                return;
            }

            const option = accParent.querySelector(
                `option[value="${CSS.escape(String(editingAccountId))}"]`
            );

            if (option) {
                option.disabled = true;
            }
        }


        // =================================================
        // تعطيل الحسابات الفرعية
        // =================================================

        function disableInvalidParentOptions() {
            if (formMode !== 'edit' || !editingAccountId || !accParent) {
                return;
            }

            const descendants = new Set();

            function collectChildren(parentId) {
                accounts
                    .filter(function (account) {
                        return String(account.accParent ?? '') === String(parentId);
                    })
                    .forEach(function (child) {
                        descendants.add(String(child.accountID));
                        collectChildren(child.accountID);
                    });
            }

            collectChildren(editingAccountId);

            Array.from(accParent.options).forEach(function (option) {
                if (descendants.has(String(option.value))) {
                    option.disabled = true;
                }
            });

            disableCurrentAccountAsParent();
        }

        function enableAllParentOptions() {
            if (!accParent) return;
            Array.from(accParent.options).forEach(function (option) {
                option.disabled = false;
            });
        }


        // =================================================
        // وضع الإضافة مع إعادة ضبط كاملة
        // =================================================

        function setAddMode() {
            formMode = 'add';
            editingAccountId = null;
            savedEditData = null;

            accountForm.reset();

            if (accountID) accountID.value = '';
            if (accParent) accParent.value = '';
            if (accParentDisplay) accParentDisplay.value = 'لا يوجد (حساب رئيسي)';
            if (accLevel) {
                accLevel.value = '1';
                accLevel.readOnly = true;
            }
            if (IsActive) IsActive.value = '1';
            if (isPostable) isPostable.value = '0';

            if (accCode) {
                accCode.value = '';
                accCode.readOnly = false;
            }

            if (accTypeID) accTypeID.value = '0';
            if (nature) nature.value = '0';

            enableAllParentOptions();

            if (accountModalTitle) {
                accountModalTitle.textContent = 'إضافة حساب جديد';
            }

            if (saveAccountBtn) {
                saveAccountBtn.innerHTML =
                    '<i class="bi bi-check-lg me-1"></i> حفظ الحساب';
            }
        }


        // =================================================
        // فتح الإضافة
        // =================================================

        addAccountBtn.addEventListener('click', async function () {
            setAddMode();
            await loadParentAccounts();
            syncParentDisplay();
            accountModal.show();
        });


        // =================================================
        // البحث عن الحساب الأب
        // =================================================

        async function openParentSearch() {
            if (!parentAccountSearchModal) return;

            await loadParentAccounts();

            if (parentAccountSearchInput) {
                parentAccountSearchInput.value = '';
            }

            filterParentSearchList();
            parentAccountSearchModal.show();
        }

        accParentDisplay?.addEventListener('click', openParentSearch);
        openParentAccountSearchBtn?.addEventListener('click', openParentSearch);


        // =================================================
        // فلترة الحسابات الأب
        // =================================================

        function filterParentSearchList() {
            if (!parentAccountSearchList) return;

            const keyword = (parentAccountSearchInput?.value || '').trim().toLowerCase();

            parentAccountSearchList
                .querySelectorAll('.parent-search-item')
                .forEach(function (item) {
                    const code = (item.dataset.code || '').toLowerCase();
                    const name = (item.dataset.name || '').toLowerCase();

                    const visible =
                        keyword === '' ||
                        code.includes(keyword) ||
                        name.includes(keyword);

                    item.classList.toggle('d-none', !visible);
                });
        }

        parentAccountSearchInput?.addEventListener('input', filterParentSearchList);


        // =================================================
        // اختيار الحساب الأب
        // =================================================

        parentAccountSearchList?.addEventListener('click', async function (event) {
            const item = event.target.closest('.parent-search-item');
            if (!item) return;

            const selectedId = item.dataset.id || '';

            // =========================================
            // بدون أب
            // =========================================

            if (!selectedId) {
                if (accParent) {
                    accParent.selectedIndex = 0;
                    accParent.value = '';
                }

                syncParentDisplay();

                if (accLevel) {
                    accLevel.value = '1';
                    accLevel.readOnly = true;
                }

                if (accTypeID) accTypeID.value = '0';
                if (nature) nature.value = '0';

                if (formMode === 'add' && accCode) {
                    accCode.value = '';
                }

                if (accCode) accCode.readOnly = false;

                if (parentAccountSearchModal) {
                    parentAccountSearchModal.hide();
                }
                return;
            }

            // منع الحساب من أن يكون أباً لنفسه
            if (
                formMode === 'edit' &&
                String(selectedId) === String(editingAccountId)
            ) {
                showToast('لا يمكن أن يكون الحساب أباً لنفسه', 'error');
                return;
            }

            const selectedOption = Array.from(accParent.options).find(
                function (option) {
                    return String(option.value) === String(selectedId);
                }
            );

            if (!selectedOption) {
                showToast('تعذر تحديد الحساب الأب', 'error');
                return;
            }

            if (selectedOption.disabled) {
                showToast('لا يمكن اختيار هذا الحساب كحساب أب', 'error');
                return;
            }

            const systemKey =
                item.dataset.systemKey ||
                selectedOption.dataset.systemKey ||
                '';

            const isPostableValue = Number(
                item.dataset.postable ||
                selectedOption.dataset.postable ||
                0
            );

            if (isPostableValue === 1) {
                showToast('لا يمكن اختيار حساب تحليلي كحساب أب', 'error');
                return;
            }

            const specialSystemKeys = [
                'cash',
                'banks',
                'customers',
                'inventory',
                'suppliers'
            ];

            const normalizedSystemKey = String(systemKey).trim().toLowerCase();

            if (specialSystemKeys.includes(normalizedSystemKey)) {
                let message = 'لا يمكن إنشاء حساب فرعي تحت هذا الحساب من دليل الحسابات.';

                switch (normalizedSystemKey) {
                    case 'cash':
                        message = 'لا يمكن إنشاء حساب فرعي تحت حساب الصندوق من دليل الحسابات. استخدم شاشة الصندوق.';
                        break;
                    case 'banks':
                        message = 'لا يمكن إنشاء حساب فرعي تحت حساب البنوك من دليل الحسابات. استخدم شاشة البنوك.';
                        break;
                    case 'customers':
                        message = 'لا يمكن إنشاء حساب فرعي تحت حساب العملاء من دليل الحسابات. استخدم شاشة العملاء.';
                        break;
                    case 'inventory':
                        message = 'لا يمكن إنشاء حساب فرعي تحت حساب المخزون من دليل الحسابات. استخدم شاشة الأصناف والمخزون.';
                        break;
                    case 'suppliers':
                        message = 'لا يمكن إنشاء حساب فرعي تحت حساب الموردين من دليل الحسابات. استخدم شاشة الموردين.';
                        break;
                }

                showToast(message, 'error');
                return;
            }

            const parentIndex = Array.from(accParent.options).findIndex(
                function (option) {
                    return String(option.value) === String(selectedId);
                }
            );

            if (parentIndex === -1) {
                showToast('تعذر تحديد الحساب الأب', 'error');
                return;
            }

            accParent.selectedIndex = parentIndex;
            accParent.value = String(selectedId);

            syncParentDisplay();

            // مزامنة النوع من dataset (احتياطي)
            const parentType =
                selectedOption.dataset.type ??
                item.dataset.type ??
                '0';

            if (accTypeID) {
                accTypeID.value = String(parentType || '0');
            }

            const parentNature =
                selectedOption.dataset.nature ||
                item.dataset.nature ||
                '';

            if (nature && parentNature !== '') {
                nature.value = String(parentNature);
            }

            updateAccountLevel();

            // توليد الرقم
            if (accCode) accCode.readOnly = true;
            await loadNextAccountCode(selectedId);

            // ✅ جلب البيانات الكاملة (لضمان النوع والطبيعة الصحيحين)
            await syncParentAccountData(selectedId);

            if (parentAccountSearchModal) {
                parentAccountSearchModal.hide();
            }
        });


        // =================================================
        // تطبيع القيمة
        // =================================================

        function normalizeValue(value) {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value).trim();
        }


        // =================================================
        // بيانات النموذج للمقارنة
        // =================================================

        function getFormDataForComparison() {
            return {
                accTypeID: normalizeValue(accTypeID?.value),
                accCode: normalizeValue(accCode?.value),
                accParent: normalizeValue(accParent?.value),
                accName: normalizeValue(accName?.value),
                nature: normalizeValue(nature?.value),
                accLevel: normalizeValue(accLevel?.value),
                IsActive: normalizeValue(IsActive?.value),
                isPostable: normalizeValue(isPostable?.value)
            };
        }

        function hasFormChanges() {
            if (!savedEditData) {
                return true;
            }

            const currentData = getFormDataForComparison();

            return Object.keys(currentData).some(function (key) {
                return currentData[key] !== savedEditData[key];
            });
        }


        // =================================================
        // تعديل الحساب
        // =================================================

        async function editAccount(id) {
            try {
                formMode = 'edit';
                editingAccountId = id;

                const response = await fetch(
                    `/settings/accounting/chartOfAccounts/${id}`,
                    { method: 'GET', headers: jsonHeaders() }
                );

                if (!response.ok) {
                    throw new Error(
                        await getResponseMessage(response, 'تعذر جلب بيانات الحساب')
                    );
                }

                const data = await response.json();

                if (!data.success || !data.account) {
                    throw new Error(data.message || 'تعذر جلب بيانات الحساب');
                }

                const account = data.account;

                await loadParentAccounts();

                if (accountID) accountID.value = account.accountID ?? '';
                if (accTypeID) accTypeID.value = account.accTypeID ?? '';
                if (accCode) accCode.value = account.accCode ?? '';

                if (accParent) {
                    const parentValue = String(account.accParent ?? '');
                    const parentIndex = Array.from(accParent.options).findIndex(
                        function (option) {
                            return String(option.value) === parentValue;
                        }
                    );

                    if (parentIndex !== -1) {
                        accParent.selectedIndex = parentIndex;
                    } else {
                        accParent.selectedIndex = 0;
                    }
                }

                if (accName) accName.value = account.accName ?? '';
                if (nature) nature.value = account.nature ?? '';
                if (accLevel) accLevel.value = account.accLevel ?? '1';
                if (IsActive) IsActive.value = account.IsActive ?? '1';
                if (isPostable) isPostable.value = account.isPostable ?? '0';

                syncParentDisplay();

                disableInvalidParentOptions();

                savedEditData = getFormDataForComparison();

                if (accountModalTitle) {
                    accountModalTitle.textContent = 'تعديل الحساب';
                }

                if (saveAccountBtn) {
                    saveAccountBtn.innerHTML =
                        '<i class="bi bi-check-lg me-1"></i> حفظ التعديل';
                }

                accountModal.show();

            } catch (error) {
                console.error(error);

                formMode = 'add';
                editingAccountId = null;
                savedEditData = null;

                showToast(error.message || 'تعذر تحميل الحساب', 'error');
            }
        }


        // =================================================
        // التحقق من النموذج
        // =================================================

        function validateAccountForm() {
            const code = normalizeValue(accCode?.value);
            const name = normalizeValue(accName?.value);

            if (!code) {
                showToast('رقم الحساب مطلوب', 'error');
                accCode?.focus();
                return false;
            }

            if (!/^\d+$/.test(code)) {
                showToast('رقم الحساب يجب أن يحتوي على أرقام فقط', 'error');
                accCode?.focus();
                return false;
            }

            if (!name) {
                showToast('اسم الحساب مطلوب', 'error');
                accName?.focus();
                return false;
            }

            return true;
        }


        // =================================================
        // حفظ الحساب
        // =================================================

        accountForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (!validateAccountForm()) return;

            if (formMode === 'edit' && !hasFormChanges()) {
                showToast('لم يتم إجراء أي تعديل على بيانات الحساب', 'error');
                return;
            }

            updateAccountLevel();

            const formData = new FormData(accountForm);

            let url = '/settings/accounting/chartOfAccounts';
            const method = 'POST';

            if (formMode === 'edit' && editingAccountId) {
                url = `/settings/accounting/chartOfAccounts/${editingAccountId}`;
                formData.append('_method', 'PUT');
            }

            const originalText = saveAccountBtn?.innerHTML;

            try {
                if (saveAccountBtn) {
                    saveAccountBtn.disabled = true;
                    saveAccountBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1"></span> جاري الحفظ...';
                }

                const response = await fetch(url, {
                    method,
                    headers: jsonHeaders(),
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(
                        await getResponseMessage(response, 'تعذر حفظ الحساب')
                    );
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'تعذر حفظ الحساب');
                }

                showToast(data.message || 'تم حفظ الحساب بنجاح');
                accountModal.hide();

                await reloadAccountsTable();

            } catch (error) {
                console.error(error);
                showToast(
                    error.message || 'حدث خطأ أثناء حفظ الحساب',
                    'error'
                );
            } finally {
                if (saveAccountBtn) {
                    saveAccountBtn.disabled = false;
                    saveAccountBtn.innerHTML =
                        originalText ||
                        '<i class="bi bi-check-lg me-1"></i> حفظ الحساب';
                }
            }
        });


        // =================================================
        // فتح مودال الحذف
        // =================================================

        function openDeleteModal(id) {
            if (!id) return;

            deletingAccountId = id;

            if (deleteConfirmModal) {
                deleteConfirmModal.show();
            } else {
                performDeleteAccount();
            }
        }


        // =================================================
        // إغلاق مودال الحذف
        // =================================================

        function closeDeleteModal() {
            deletingAccountId = null;
            if (deleteConfirmModal) {
                deleteConfirmModal.hide();
            }
        }


        // =================================================
        // تنفيذ الحذف
        // =================================================

        async function performDeleteAccount() {
            if (!deletingAccountId) return;

            const id = deletingAccountId;
            const originalText = deleteConfirmBtn?.innerHTML;

            try {
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = true;
                    deleteConfirmBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1"></span> جاري الحذف...';
                }

                const response = await fetch(
                    `/settings/accounting/chartOfAccounts/${id}`,
                    { method: 'DELETE', headers: jsonHeaders() }
                );

                if (!response.ok) {
                    throw new Error(
                        await getResponseMessage(response, 'تعذر حذف الحساب')
                    );
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'تعذر حذف الحساب');
                }

                showToast(data.message || 'تم حذف الحساب بنجاح');

                closeDeleteModal();

                await reloadAccountsTable();

                if (selectedAnalyticalParentId) {
                    await loadAnalyticalAccounts(
                        selectedAnalyticalParentId,
                        analyticalCurrentPage
                    );
                }

            } catch (error) {
                console.error(error);
                showToast(error.message || 'تعذر حذف الحساب', 'error');
            } finally {
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = false;
                    deleteConfirmBtn.innerHTML =
                        originalText ||
                        '<i class="bi bi-trash me-1"></i> نعم، حذف الحساب';
                }
            }
        }

        deleteConfirmBtn?.addEventListener('click', performDeleteAccount);
        deleteCancelBtn?.addEventListener('click', closeDeleteModal);
        deleteCancelBtnFooter?.addEventListener('click', closeDeleteModal);


        // =================================================
        // خصائص الشجرة
        // =================================================

        function hasChildren(accountId) {
            return accounts.some(function (account) {
                return String(account.accParent ?? '') === String(accountId);
            });
        }// =================================================
// ✅ هل الحساب مرتبط بشاشة خارجية؟
// (عملاء / بنوك / موردون / صناديق / مخازن)
// =================================================

const EXTERNAL_SYSTEM_KEYS = [
    'cash',
    'banks',
    'customers',
    'suppliers',
    'inventory'
];


function isExternalAccount(account) {

    // ✅ حساب نظامي
    if (Number(account.is_system) === 1) {
        return true;
    }

    // ✅ حساب مرتبط بشاشة خارجية
    const systemKey = String(
        account.system_key ?? ''
    ).trim().toLowerCase();

    if (systemKey === '') {
        return false;
    }

    return EXTERNAL_SYSTEM_KEYS.includes(systemKey);
}



        // =================================================
        // إنشاء صف الحساب
        // =================================================

   function createAccountRow(account) {
    const row = cloneTemplate(accountRowTemplate);
    if (!row) return null;

    row.dataset.id = String(account.accountID);
    row.dataset.parentId = String(account.accParent ?? '');
    row.dataset.level = String(account.accLevel ?? 1);

    const numberElement = row.querySelector('.account-row-number');
    const toggle = row.querySelector('.account-tree-toggle');
    const codeElement = row.querySelector('.account-code');
    const nameElement = row.querySelector('.account-name');
    const natureElement = row.querySelector('.account-nature');
    const parentElement = row.querySelector('.account-parent');
    const statusElement = row.querySelector('.account-status');
    const analyticalButton = row.querySelector('.show-analytical');
    const editButton = row.querySelector('.edit-account');
    const deleteButton = row.querySelector('.delete-account');
    const systemBadge = row.querySelector('.account-system-badge');

    if (codeElement) {
        codeElement.textContent = account.accCode ?? '';
    }

    if (nameElement) {
        nameElement.textContent = account.accName ?? '';
    }

    if (natureElement) {
        natureElement.textContent =
            Number(account.nature) === 1 ? 'دائن' : 'مدين';
    }

    if (parentElement) {
        const parent = accounts.find(function (item) {
            return String(item.accountID) === String(account.accParent);
        });

        parentElement.textContent = parent ? parent.accName : '—';
    }

    if (statusElement) {
        const active = Number(account.IsActive) === 1;

        statusElement.textContent = active ? 'نشط' : 'غير نشط';

        statusElement.classList.toggle('bg-success', active);
        statusElement.classList.toggle('bg-danger', !active);
        statusElement.classList.toggle('text-white', true);
    }

    const level = Math.max(0, Number(account.accLevel || 1) - 1);

    const accountCodeContainer = codeElement ? codeElement.parentElement : null;

    if (accountCodeContainer) {
        accountCodeContainer.style.marginRight = `${level * 30}px`;
    }

    if (toggle && hasChildren(account.accountID)) {
        toggle.classList.remove('d-none');
        toggle.dataset.id = String(account.accountID);
        toggle.setAttribute(
            'aria-expanded',
            account.__open === true ? 'true' : 'false'
        );
    } else if (toggle) {
        toggle.classList.add('d-none');
    }

    if (analyticalButton && Number(account.hasAnalytical) === 1) {
        analyticalButton.classList.remove('d-none');
        analyticalButton.dataset.id = String(account.accountID);
    } else if (analyticalButton) {
        analyticalButton.classList.add('d-none');
    }


    // =================================================
    // ✅ الحسابات النظامية والخارجية
    // =================================================

    if (isExternalAccount(account)) {

        editButton?.classList.add('d-none');
        deleteButton?.classList.add('d-none');

        // ✅ إعداد الـ Badge حسب نوع الحساب
        if (systemBadge) {

            const systemKey = String(
                account.system_key ?? ''
            ).trim().toLowerCase();

            // ✅ قائمة التسميات
            const badgeLabels = {
                'cash':      'صندوق',
                'banks':     'بنك',
                'customers': 'عميل',
                'suppliers': 'مورد',
                'inventory': 'مخزون'
            };

            // ✅ قائمة الألوان
            const badgeColors = {
                'cash':      'bg-success-subtle text-success-emphasis',
                'banks':     'bg-primary-subtle text-primary-emphasis',
                'customers': 'bg-info-subtle text-info-emphasis',
                'suppliers': 'bg-warning-subtle text-warning-emphasis',
                'inventory': 'bg-secondary-subtle text-secondary-emphasis'
            };


            // ✅ إعادة تعيين الكلاسات القديمة
            systemBadge.classList.remove(
                'bg-success',
                'bg-danger',
                'bg-primary',
                'bg-secondary',
                'bg-info',
                'bg-warning',
                'bg-success-subtle',
                'bg-primary-subtle',
                'bg-secondary-subtle',
                'bg-info-subtle',
                'bg-warning-subtle',
                'text-success-emphasis',
                'text-primary-emphasis',
                'text-secondary-emphasis',
                'text-info-emphasis',
                'text-warning-emphasis',
                'text-white'
            );


            // ✅ تحديد النص
            systemBadge.textContent =
                badgeLabels[systemKey] || 'نظامي';


            // ✅ تحديد اللون
            const colorClass =
                badgeColors[systemKey] ||
                'bg-secondary-subtle text-secondary-emphasis';

            colorClass.split(' ').forEach(function (cls) {
                systemBadge.classList.add(cls);
            });


            // ✅ إظهار الـ Badge
            systemBadge.classList.remove('d-none');
        }

    } else {

        if (editButton) {
            editButton.dataset.id = String(account.accountID);
        }
        if (deleteButton) {
            deleteButton.dataset.id = String(account.accountID);
        }
    }

    return row;
}


        // =================================================
        // هل الصف ظاهر؟
        // =================================================

        function isRowVisible(account) {
            let parentId = account.accParent;

            while (parentId) {
                const parent = accounts.find(function (item) {
                    return String(item.accountID) === String(parentId);
                });

                if (!parent) break;

                if (parent.__open !== true) {
                    return false;
                }

                parentId = parent.accParent;
            }

            return true;
        }


        // =================================================
        // الحسابات الظاهرة
        // =================================================

        function getVisibleAccounts() {
            return accounts.filter(isRowVisible);
        }


        // =================================================
        // تحديث أيقونات الشجرة
        // =================================================

        function updateTreeIcons() {
            if (!accountsTreeBody) return;

            accountsTreeBody
                .querySelectorAll('.account-tree-toggle')
                .forEach(function (button) {
                    const id = button.dataset.id;

                    const account = accounts.find(function (item) {
                        return String(item.accountID) === String(id);
                    });

                    if (!account) return;

                    const icon = button.querySelector('i');
                    if (!icon) return;

                    icon.className = account.__open
                        ? 'bi bi-chevron-down'
                        : 'bi bi-chevron-left';

                    button.setAttribute(
                        'aria-expanded',
                        account.__open === true ? 'true' : 'false'
                    );
                });
        }


        // =================================================
        // فتح / إغلاق الحساب
        // =================================================

        function toggleAccountTree(id) {
            const account = accounts.find(function (item) {
                return String(item.accountID) === String(id);
            });

            if (!account) return;

            account.__open = account.__open !== true;

            currentPage = 1;

            renderTree();
        }


        // =================================================
        // رسالة فارغة
        // =================================================

        function renderEmptyTreeMessage() {
            showTreeState('empty');
        }


        // =================================================
        // Pagination Helpers
        // =================================================

        function appendPaginationEllipsis(container) {
            if (!container || !paginationEllipsisTemplate) return;

            const element = cloneTemplate(paginationEllipsisTemplate);
            if (element) container.appendChild(element);
        }

        function appendPaginationPage(container, page, active, onClick) {
            if (!container || !paginationPageTemplate) return;

            const element = cloneTemplate(paginationPageTemplate);
            if (!element) return;

            const button = element.querySelector('.pagination-page');
            if (!button) return;

            button.textContent = String(page);

            if (active) {
                element.classList.add('active');
                button.setAttribute('aria-current', 'page');
            }

            button.addEventListener('click', onClick);
            container.appendChild(element);
        }

        function appendPaginationPrevious(container, disabled, onClick) {
            if (!container || !paginationPreviousTemplate) return;

            const element = cloneTemplate(paginationPreviousTemplate);
            if (!element) return;

            const button = element.querySelector('.pagination-previous');
            if (!button) return;

            if (disabled) {
                element.classList.add('disabled');
                button.disabled = true;
            } else {
                button.addEventListener('click', onClick);
            }

            container.appendChild(element);
        }

        function appendPaginationNext(container, disabled, onClick) {
            if (!container || !paginationNextTemplate) return;

            const element = cloneTemplate(paginationNextTemplate);
            if (!element) return;

            const button = element.querySelector('.pagination-next');
            if (!button) return;

            if (disabled) {
                element.classList.add('disabled');
                button.disabled = true;
            } else {
                button.addEventListener('click', onClick);
            }

            container.appendChild(element);
        }

        function buildPaginationPages(current, total) {
            if (total <= 7) {
                return Array.from({ length: total }, function (_, index) {
                    return index + 1;
                });
            }

            const pages = [1];

            if (current > 4) pages.push('...');

            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);

            for (let page = start; page <= end; page++) {
                pages.push(page);
            }

            if (current < total - 3) pages.push('...');

            pages.push(total);

            return pages;
        }


        // =================================================
        // Pagination الشجرة
        // =================================================

        function renderPagination(total) {
            if (!accountsPaginationInfo || !accountsPaginationList) return;

            if (!total || total <= 0) {
                accountsPaginationInfo.textContent = 'عرض 0-0 من 0 حساب';
                accountsPaginationList.replaceChildren();
                return;
            }

            const totalPages = Math.max(1, Math.ceil(total / pageSize));

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            if (currentPage < 1) {
                currentPage = 1;
            }

            const start = ((currentPage - 1) * pageSize) + 1;
            const end = Math.min(currentPage * pageSize, total);

            accountsPaginationInfo.textContent =
                `عرض ${start}-${end} من ${total} حساب`;

            accountsPaginationList.replaceChildren();

            appendPaginationPrevious(
                accountsPaginationList,
                currentPage === 1,
                function () {
                    currentPage--;
                    renderTree();
                }
            );

            buildPaginationPages(currentPage, totalPages).forEach(function (page) {
                if (page === '...') {
                    appendPaginationEllipsis(accountsPaginationList);
                } else {
                    appendPaginationPage(
                        accountsPaginationList,
                        page,
                        page === currentPage,
                        function () {
                            currentPage = page;
                            renderTree();
                        }
                    );
                }
            });

            appendPaginationNext(
                accountsPaginationList,
                currentPage === totalPages,
                function () {
                    currentPage++;
                    renderTree();
                }
            );
        }


        // =================================================
        // Render Tree
        // =================================================

        function renderTree() {
            if (!accountsTreeBody) return;

            const visibleAccounts = getVisibleAccounts();

            if (!visibleAccounts.length) {
                renderEmptyTreeMessage();
                renderPagination(0);
                return;
            }

            const total = visibleAccounts.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const start = (currentPage - 1) * pageSize;

            const pageAccounts = visibleAccounts.slice(start, start + pageSize);

            const fragment = document.createDocumentFragment();

            pageAccounts.forEach(function (account, index) {
                const row = createAccountRow(account);
                if (!row) return;

                const numberElement = row.querySelector('.account-row-number');
                if (numberElement) {
                    numberElement.textContent = String(start + index + 1);
                }

                fragment.appendChild(row);
            });

            accountsTreeBody.replaceChildren(fragment);

            updateTreeIcons();
            renderPagination(total);
        }


        // =================================================
        // أحداث الشجرة
        // =================================================

        accountsTreeBody?.addEventListener('click', function (event) {
            const toggle = event.target.closest('.account-tree-toggle');
            if (toggle) {
                toggleAccountTree(toggle.dataset.id);
                return;
            }

            const analyticalButton = event.target.closest('.show-analytical');
            if (analyticalButton) {
                loadAnalyticalAccounts(analyticalButton.dataset.id, 1);
                return;
            }

            const editButton = event.target.closest('.edit-account');
            if (editButton) {
                editAccount(editButton.dataset.id);
                return;
            }

            const deleteButton = event.target.closest('.delete-account');
            if (deleteButton) {
                openDeleteModal(deleteButton.dataset.id);
            }
        });


        // =================================================
        // تحميل الحسابات التحليلية
        // =================================================

        async function loadAnalyticalAccounts(parentId, page = 1) {
            if (!analyticalAccountsModal || !analyticalAccountsBody) return;

            selectedAnalyticalParentId = parentId;
            analyticalCurrentPage = page;

            analyticalAccountsModal.show();
            showAnalyticalState('loading');

            if (analyticalPaginationList) {
                analyticalPaginationList.replaceChildren();
            }

            if (analyticalPaginationInfo) {
                analyticalPaginationInfo.textContent = '';
            }

            try {
                const params = new URLSearchParams();
                params.set('page', String(page));
                params.set('per_page', String(analyticalPageSize));

                if (analyticalSearchCode?.value.trim()) {
                    params.set('search_code', analyticalSearchCode.value.trim());
                }

                if (analyticalSearchName?.value.trim()) {
                    params.set('search_name', analyticalSearchName.value.trim());
                }

                const response = await fetch(
                    `/settings/accounting/chartOfAccounts/${parentId}/analytical?${params.toString()}`,
                    { method: 'GET', headers: jsonHeaders() }
                );

                if (!response.ok) {
                    throw new Error(
                        await getResponseMessage(response, 'تعذر تحميل الحسابات التحليلية')
                    );
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'تعذر تحميل الحسابات التحليلية');
                }

                if (data.parent && analyticalAccountsParent) {

                    analyticalAccountsParent.textContent =
                        `${data.parent.accCode} - ${data.parent.accName}`;

                    //  خزّن system_key الخاص بالأب
                    analyticalParentSystemKey = String(
                        data.parent.system_key ?? ''
                    ).trim().toLowerCase();
                }

                const paginator = data.accounts;
                const items = Array.isArray(paginator?.data) ? paginator.data : [];

                if (!items.length) {
                    showAnalyticalState('empty');
                    renderAnalyticalPagination(paginator);
                    return;
                }

                const fragment = document.createDocumentFragment();

                const paginatorCurrentPage = Number(
                    paginator.current_page || page
                );

                const paginatorPerPage = Number(
                    paginator.per_page || analyticalPageSize
                );

                items.forEach(function (account, index) {
                    const row = cloneTemplate(analyticalAccountRowTemplate);
                    if (!row) return;

                    const numberElement = row.querySelector('.analytical-row-number');
                    const codeElement = row.querySelector('.analytical-account-code');
                    const nameElement = row.querySelector('.analytical-account-name');
                    const natureElement = row.querySelector('.analytical-account-nature');
                    const statusElement = row.querySelector('.analytical-account-status');
                    const editButton = row.querySelector('.edit-analytical-account');
                    const deleteButton = row.querySelector('.delete-analytical-account');

                    if (numberElement) {
                        numberElement.textContent = String(
                            ((paginatorCurrentPage - 1) * paginatorPerPage) + index + 1
                        );
                    }

                    if (codeElement) {
                        codeElement.textContent = account.accCode ?? '';
                    }

                    if (nameElement) {
                        nameElement.textContent = account.accName ?? '';
                    }

                    if (natureElement) {
                        natureElement.textContent =
                            Number(account.nature) === 1 ? 'دائن' : 'مدين';
                    }

                    if (statusElement) {
                        const active = Number(account.IsActive) === 1;

                        statusElement.textContent = active ? 'نشط' : 'غير نشط';

                        statusElement.classList.remove(
                            'bg-success',
                            'bg-danger'
                        );

                        statusElement.classList.add(
                            active ? 'bg-success' : 'bg-danger',
                            'text-white'
                        );
                    }

                    if (editButton) {
                        editButton.dataset.id = String(account.accountID);
                    }

                    if (deleteButton) {
                        deleteButton.dataset.id = String(account.accountID);
                    }

                   //  إخفاء الأزرار للحسابات النظامية والخارجية
                    const isSystemAccount =
                        Number(account.is_system) === 1;

                    const isExternalChild =
                        EXTERNAL_SYSTEM_KEYS.includes(
                            analyticalParentSystemKey
                        );

                    if (isSystemAccount || isExternalChild) {
                        editButton?.classList.add('d-none');
                        deleteButton?.classList.add('d-none');
                    }
                    fragment.appendChild(row);
                });

                analyticalAccountsBody.replaceChildren(fragment);

                renderAnalyticalPagination(paginator);

            } catch (error) {
                console.error(error);

                showAnalyticalState('error');

                if (analyticalPaginationList) {
                    analyticalPaginationList.replaceChildren();
                }

                if (analyticalPaginationInfo) {
                    analyticalPaginationInfo.textContent = 'تعذر تحميل البيانات';
                }

                showToast(
                    error.message || 'تعذر تحميل الحسابات التحليلية',
                    'error'
                );
            }
        }


        // =================================================
        // Pagination الحسابات التحليلية
        // =================================================

        function renderAnalyticalPagination(paginator) {
            if (!analyticalPaginationList || !analyticalPaginationInfo) return;

            const total = Number(paginator?.total || 0);

            const current = Number(
                paginator?.current_page || analyticalCurrentPage || 1
            );

            const perPage = Number(
                paginator?.per_page || analyticalPageSize
            );

            const lastPage = Math.max(
                1,
                Number(
                    paginator?.last_page || Math.ceil(total / perPage)
                )
            );

            analyticalCurrentPage = current;

            if (!total) {
                analyticalPaginationInfo.textContent = 'عرض 0-0 من 0 حساب';
                analyticalPaginationList.replaceChildren();
                return;
            }

            const start = ((current - 1) * perPage) + 1;
            const end = Math.min(current * perPage, total);

            analyticalPaginationInfo.textContent =
                `عرض ${start}-${end} من ${total} حساب`;

            analyticalPaginationList.replaceChildren();

            appendPaginationPrevious(
                analyticalPaginationList,
                current === 1,
                function () {
                    loadAnalyticalAccounts(
                        selectedAnalyticalParentId,
                        current - 1
                    );
                }
            );

            buildPaginationPages(current, lastPage).forEach(function (page) {
                if (page === '...') {
                    appendPaginationEllipsis(analyticalPaginationList);
                } else {
                    appendPaginationPage(
                        analyticalPaginationList,
                        page,
                        page === current,
                        function () {
                            loadAnalyticalAccounts(
                                selectedAnalyticalParentId,
                                page
                            );
                        }
                    );
                }
            });

            appendPaginationNext(
                analyticalPaginationList,
                current === lastPage,
                function () {
                    loadAnalyticalAccounts(
                        selectedAnalyticalParentId,
                        current + 1
                    );
                }
            );
        }


        // =================================================
        // أحداث الحسابات التحليلية
        // =================================================

        analyticalAccountsBody?.addEventListener('click', function (event) {
            const editButton = event.target.closest('.edit-analytical-account');

            if (editButton) {
                if (analyticalAccountsModal) {
                    analyticalAccountsModal.hide();
                }
                editAccount(editButton.dataset.id);
                return;
            }

            const deleteButton = event.target.closest('.delete-analytical-account');

            if (deleteButton) {
                if (analyticalAccountsModal) {
                    analyticalAccountsModal.hide();
                }
                openDeleteModal(deleteButton.dataset.id);
            }
        });


        // =================================================
        // البحث في الحسابات التحليلية
        // =================================================

        function scheduleAnalyticalSearch() {
            clearTimeout(analyticalSearchTimer);

            analyticalSearchTimer = setTimeout(function () {
                if (selectedAnalyticalParentId) {
                    loadAnalyticalAccounts(selectedAnalyticalParentId, 1);
                }
            }, 350);
        }

        analyticalSearchCode?.addEventListener('input', scheduleAnalyticalSearch);
        analyticalSearchName?.addEventListener('input', scheduleAnalyticalSearch);


        // =================================================
        // البحث الرئيسي
        // =================================================

        function scheduleMainSearch() {
            clearTimeout(searchTimer);

            searchTimer = setTimeout(function () {
                currentPage = 1;
                reloadAccountsTable();
            }, 350);
        }

        searchCode?.addEventListener('input', scheduleMainSearch);
        searchName?.addEventListener('input', scheduleMainSearch);


        // =================================================
        // إعادة تحميل الشجرة
        // =================================================

        async function reloadAccountsTable() {
            if (!accountsTreeBody) return;

            const openIds = new Set(
                accounts
                    .filter(function (account) {
                        return account.__open === true;
                    })
                    .map(function (account) {
                        return String(account.accountID);
                    })
            );

            showTreeState('loading');

            try {
                const params = new URLSearchParams();

                if (searchCode?.value.trim()) {
                    params.set('search_code', searchCode.value.trim());
                }

                if (searchName?.value.trim()) {
                    params.set('search_name', searchName.value.trim());
                }

                const query = params.toString();

                const url = query
                    ? `/settings/accounting/chartOfAccounts/tree?${query}`
                    : '/settings/accounting/chartOfAccounts/tree';

                const response = await fetch(url, {
                    method: 'GET',
                    headers: jsonHeaders()
                });

                if (!response.ok) {
                    throw new Error(
                        await getResponseMessage(response, 'تعذر تحميل الحسابات')
                    );
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'تعذر تحميل الحسابات');
                }

                accounts = Array.isArray(data.accounts) ? data.accounts : [];

                accounts.forEach(function (account) {
                    account.__open = openIds.has(String(account.accountID));
                });

                currentPage = 1;

                if (!accounts.length) {
                    renderEmptyTreeMessage();
                    renderPagination(0);
                    return;
                }

                renderTree();

            } catch (error) {
                console.error(error);

                accounts = [];

                showTreeState('error');
                renderPagination(0);

                showToast(
                    error.message || 'تعذر تحميل الحسابات',
                    'error'
                );
            }
        }


        // =================================================
        // إعادة ضبط مودال الحساب
        // =================================================

        accountModalElement.addEventListener('hidden.bs.modal', function (event) {

            if (event.target !== accountModalElement) {
                return;
            }

            accountForm.reset();

            formMode = 'add';
            editingAccountId = null;
            savedEditData = null;

            if (accountID) accountID.value = '';
            if (accLevel) {
                accLevel.value = '1';
                accLevel.readOnly = true;
            }
            if (accParent) accParent.value = '';
            if (accParentDisplay) {
                accParentDisplay.value = 'لا يوجد (حساب رئيسي)';
            }
            if (IsActive) IsActive.value = '1';
            if (isPostable) isPostable.value = '0';
            if (accCode) {
                accCode.value = '';
                accCode.readOnly = false;
            }
            if (accTypeID) accTypeID.value = '0';
            if (nature) nature.value = '0';

            enableAllParentOptions();

            if (accountModalTitle) {
                accountModalTitle.textContent = 'إضافة حساب جديد';
            }

            if (saveAccountBtn) {
                saveAccountBtn.innerHTML =
                    '<i class="bi bi-check-lg me-1"></i> حفظ الحساب';
            }
        });


        // =================================================
        // تنظيف بحث الحسابات التحليلية بالكامل عند الإغلاق
        // =================================================

        analyticalAccountsModalElement?.addEventListener(
                'hidden.bs.modal',
                function (event) {

                    if (event.target !== analyticalAccountsModalElement) {
                        return;
                    }

                    selectedAnalyticalParentId = null;
                    analyticalCurrentPage = 1;
                    analyticalParentSystemKey = '';  // ✅ تصفير

                clearTimeout(analyticalSearchTimer);

                if (analyticalSearchCode) analyticalSearchCode.value = '';
                if (analyticalSearchName) analyticalSearchName.value = '';

                if (analyticalPaginationInfo) {
                    analyticalPaginationInfo.textContent = '';
                }

                if (analyticalPaginationList) {
                    analyticalPaginationList.replaceChildren();
                }

                if (analyticalAccountsParent) {
                    analyticalAccountsParent.textContent = '';
                }
            }
        );


        // =================================================
        // API خارجي
        // =================================================

        window.loadAnalyticalAccounts = loadAnalyticalAccounts;
        window.editAccount = editAccount;
        window.deleteAccount = openDeleteModal;
        window.reloadAccountsTable = reloadAccountsTable;


        // =================================================
        // التشغيل الأول
        // =================================================

        reloadAccountsTable();

    }

})();