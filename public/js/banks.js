/* =========================================================
   شاشة البنوك
========================================================= */

let banksData = [];
let editingBankId = null;
let deletingBankId = null;

const banksApi = {
    list: '/setting/accounting/banks/list',
    store: '/setting/accounting/banks',
    update: (id) => `/setting/accounting/banks/${id}`,
    destroy: (id) => `/setting/accounting/banks/${id}`,
    toggle: (id) => `/setting/accounting/banks/${id}/toggle-status`,
    nextCode: '/setting/accounting/banks/next-code',
};

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') || '';

/* =========================================================
   تحميل البيانات
========================================================= */

async function loadBanks() {
    try {
        const res = await fetch(banksApi.list, {
            headers: {
                'Accept': 'application/json',
            },
        });

        const json = await res.json();

        if (json.success) {
            banksData = json.data || [];
            renderBanks();
        }

    } catch (e) {
        console.error('خطأ في تحميل البنوك:', e);
    }
}

/* =========================================================
   عرض الجدول
========================================================= */

function renderBanks(data) {

    const list =
        Array.isArray(data)
            ? data
            : banksData;

    const tbody =
        document.getElementById('banksTableBody');

    const badge =
        document.getElementById('banksCountBadge');

    if (!tbody) return;

    /* ---------- حالة: لا توجد بيانات ---------- */
    if (!list.length) {

        const emptyTpl =
            document.getElementById('emptyBankRowTemplate');

        tbody.innerHTML = '';

        if (emptyTpl) {
            tbody.appendChild(
                emptyTpl.content.cloneNode(true)
            );
        }

        if (badge) {
            badge.textContent = '0 بنك';
        }

        return;
    }

    /* ---------- حالة: يوجد بيانات ---------- */
    const rowTpl =
        document.getElementById('bankRowTemplate');

    const coinBadgeTpl =
        document.getElementById('bankCoinBadgeTemplate');

    const noCoinBadgeTpl =
        document.getElementById('bankNoCoinBadgeTemplate');

    const dashTpl =
        document.getElementById('bankDashTemplate');

    const fragment =
        document.createDocumentFragment();

    list.forEach((bank, i) => {

        const rowFragment =
            rowTpl.content.cloneNode(true);

        const row =
            rowFragment.querySelector('tr');

        const coinCode = bank.coin?.coinsCode || '';
        const coinRate = bank.coin?.coinsExchangeRate || 0;
        const accCode = bank.account?.accCode || '—';
        const accountNumber = bank.accountNumber || '—';
        const noCoin = !bank.coinsID;
        const isAct = !!bank.is_active;

        /* بيانات الصف (data-*) */
        row.dataset.id = bank.bankID;
        row.dataset.name = bank.bankName;
        row.dataset.coin = bank.coinsID || '';
        row.dataset.accountNumber = accountNumber;
        row.dataset.accountCode = accCode;
        row.dataset.active = isAct ? 1 : 0;

        /* تمييز صف "بلا عملة" بلون تحذيري */
        if (noCoin) {
            row.style.backgroundColor = '#fff3cd';
        }

        /* الرقم التسلسلي واسم البنك */
        row.querySelector('.row-index').textContent = i + 1;
        row.querySelector('.row-name').textContent = bank.bankName;

        /* خلية العملة */
        const coinCell =
            row.querySelector('.row-coin');

        if (coinCode && coinBadgeTpl) {

            const badgeFragment =
                coinBadgeTpl.content.cloneNode(true);

            badgeFragment.querySelector('span')
                .textContent = coinCode;

            coinCell.appendChild(badgeFragment);

        } else if (noCoinBadgeTpl) {

            coinCell.appendChild(
                noCoinBadgeTpl.content.cloneNode(true)
            );
        }

        /* سعر الصرف */
        const rateCell =
            row.querySelector('.row-rate');

        if (noCoin) {
            if (dashTpl) {
                rateCell.appendChild(
                    dashTpl.content.cloneNode(true)
                );
            } else {
                rateCell.textContent = '—';
            }
        } else {
            rateCell.textContent = formatNumber(coinRate);
        }

        /* رقم الحساب البنكي */
        row.querySelector('.row-account-number').textContent =
            accountNumber;

        /* رقم الحساب المحاسبي */
        row.querySelector('.row-account').textContent =
            accCode;

        /* زر الحالة */
        const statusBtn =
            row.querySelector('.toggle-status-btn');

        statusBtn.classList.add(
            isAct ? 'btn-success' : 'btn-secondary'
        );

        statusBtn.textContent =
            isAct ? 'نشط' : 'غير نشط';

        statusBtn.title =
            isAct ? 'تعطيل' : 'تفعيل';

        fragment.appendChild(rowFragment);
    });

    tbody.innerHTML = '';
    tbody.appendChild(fragment);

    if (badge) {
        badge.textContent = `${list.length} بنك`;
    }
}

/* =========================================================
   أدوات
========================================================= */

function formatNumber(v) {

    return Number(v || 0)
        .toLocaleString('en-US', {
            maximumFractionDigits: 6,
        });
}

/* =========================================================
   البحث
========================================================= */

function filterBanks() {

    const term =
        document
            .getElementById('searchBankInput')
            ?.value
            .trim()
            .toLowerCase() || '';

    const coinId =
        document
            .getElementById('statusBankFilter')
            ?.value || '';

    const filtered =
        banksData.filter(b => {

            const matchesSearch =
                (b.bankName || '')
                    .toLowerCase()
                    .includes(term);

            const matchesCoin =
                !coinId ||
                String(b.coinsID) === String(coinId);

            return matchesSearch && matchesCoin;
        });

    renderBanks(filtered);
}

/* =========================================================
   تحديث سعر الصرف
========================================================= */

function updateExchangeRate() {

    const select =
        document.getElementById('coinsID');

    const rateInput =
        document.getElementById('exchangeRate');

    if (!select || !rateInput) return;

    const option =
        select.options[select.selectedIndex];

    const rate =
        option?.dataset?.rate || '';

    rateInput.value =
        rate
            ? Number(rate).toFixed(6)
            : '';
}

/* =========================================================
   فتح المودال
========================================================= */

async function openBankModal() {

    editingBankId = null;

    document.getElementById('bankModalLabel').innerHTML =
        '<i class="bi bi-bank"></i> إضافة بنك';

    document.getElementById('bankForm').reset();

    document.getElementById('bankID').value = '';

    document.getElementById('isActive').value = '1';

    document.getElementById('exchangeRate').value = '';

    document.getElementById('accountCode').value = '';

    document.getElementById('accountNumber').value = '';

    const warning =
        document.getElementById('coinWarning');

    if (warning) {
        warning.style.display = 'none';
    }

    try {

        const res =
            await fetch(banksApi.nextCode, {
                headers: {
                    'Accept': 'application/json',
                },
            });

        const json =
            await res.json();

        if (json.success) {

            document.getElementById(
                'accountCode'
            ).value = json.code;

        } else {

            showSystemToast(
                json.message || 'فشل جلب رقم الحساب',
                'danger'
            );
        }

    } catch (e) {

        console.error('فشل جلب رقم الحساب:', e);
    }

    const modalEl =
        document.getElementById('bankModal');

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalEl,
            { focus: false }
        );

    modal.show();
}

/* =========================================================
   تعديل
========================================================= */

function editBank(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    editingBankId =
        row.dataset.id;

    document.getElementById('bankModalLabel').innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل البنك';

    document.getElementById('bankID').value = row.dataset.id;
    document.getElementById('bankName').value = row.dataset.name;
    document.getElementById('coinsID').value = row.dataset.coin || '';
    document.getElementById('accountNumber').value = row.dataset.accountNumber || '';
    document.getElementById('accountCode').value = row.dataset.accountCode || '';
    document.getElementById('isActive').value = row.dataset.active;

    const warning =
        document.getElementById('coinWarning');

    if (warning) {
        warning.style.display =
            row.dataset.coin ? 'none' : 'block';
    }

    updateExchangeRate();

    const modalEl =
        document.getElementById('bankModal');

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalEl,
            { focus: false }
        );

    modal.show();
}

/* =========================================================
   حفظ
========================================================= */

async function saveBank() {

    const form =
        document.getElementById('bankForm');

    if (!form.checkValidity()) {

        form.reportValidity();

        return;
    }

    const bankName =
        document.getElementById('bankName')
            .value.trim();

    const coinValue =
        document.getElementById('coinsID')
            .value;

    const accNum =
        document.getElementById('accountNumber')
            .value.trim();

    const isActive =
        document.getElementById('isActive')
            .value === '1';

    const isEdit =
        editingBankId !== null;

    /* =====================================================
       التحقق من عدم وجود تعديل
    ===================================================== */

    if (isEdit) {

        const row =
            document.querySelector(
                `#banksTableBody tr.bank-row[data-id="${editingBankId}"]`
            );

        if (row) {

            const originalName = row.dataset.name || '';
            const originalCoin = row.dataset.coin || '';
            const originalAccountNumber = row.dataset.accountNumber || '';
            const originalActive = row.dataset.active === '1';

            const currentCoin = coinValue || '';
            const currentAccountNumber = accNum || '';

            const noChanges =
                bankName === originalName &&
                currentCoin === originalCoin &&
                currentAccountNumber === originalAccountNumber &&
                isActive === originalActive;

            if (noChanges) {

                showSystemToast(
                    'لم يتم إجراء أي تعديل على بيانات البنك.',
                    'danger'
                );

                return;
            }
        }
    }

    const payload = {

        bankName: bankName,

        coinsID:
            coinValue
                ? Number(coinValue)
                : null,

        accountNumber:
            accNum || null,

        is_active: isActive,
    };

    const url =
        isEdit
            ? banksApi.update(editingBankId)
            : banksApi.store;

    const method =
        isEdit
            ? 'PUT'
            : 'POST';

    try {

        const res =
            await fetch(url, {

                method,

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },

                body: JSON.stringify(payload),
            });

        const json =
            await res.json();

        if (!json.success) {

            showSystemToast(
                json.message || 'حدث خطأ',
                'danger'
            );

            return;
        }

        if (
            document.activeElement &&
            document.activeElement.blur
        ) {
            document.activeElement.blur();
        }

        const modalEl =
            document.getElementById('bankModal');

        const modalInstance =
            bootstrap.Modal.getInstance(modalEl);

        if (modalInstance) {
            modalInstance.hide();
        }

        await loadBanks();

        showSystemToast(
            json.message || 'تم الحفظ بنجاح',
            'success'
        );

    } catch (e) {

        console.error(e);

        showSystemToast(
            'حدث خطأ أثناء الحفظ',
            'danger'
        );
    }
}

/* =========================================================
   حذف البنك
========================================================= */

function deleteBank(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    deletingBankId =
        row.dataset.id;

    const modal =
        document.getElementById('deleteConfirmModal');

    if (modal) {
        modal.classList.add('show');
    }
}

/* =========================================================
   أحداث الصفحة
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    () => {

        const deleteModal =
            document.getElementById('deleteConfirmModal');

        const deleteCancelBtn =
            document.getElementById('deleteCancelBtn');

        const deleteConfirmBtn =
            document.getElementById('deleteConfirmBtn');

        /* =================================================
           إلغاء الحذف
        ================================================= */

        deleteCancelBtn?.addEventListener(
            'click',
            () => {

                deletingBankId = null;

                deleteModal?.classList.remove('show');
            }
        );

        /* =================================================
           تأكيد الحذف
        ================================================= */

        deleteConfirmBtn?.addEventListener(
            'click',
            async function () {

                if (!deletingBankId) {
                    return;
                }

                this.disabled = true;

                try {

                    const res =
                        await fetch(
                            banksApi.destroy(deletingBankId),
                            {
                                method: 'DELETE',

                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                            }
                        );

                    const json =
                        await res.json();

                    if (!json.success) {

                        showSystemToast(
                            json.message ||
                            'حدث خطأ أثناء الحذف',
                            'danger'
                        );

                        return;
                    }

                    /* إغلاق النافذة */
                    deleteModal?.classList.remove('show');

                    deletingBankId = null;

                    /* تحديث الجدول */
                    await loadBanks();

                    showSystemToast(
                        json.message ||
                        'تم حذف البنك بنجاح',
                        'success'
                    );

                } catch (e) {

                    console.error(e);

                    showSystemToast(
                        'حدث خطأ أثناء الحذف',
                        'danger'
                    );

                } finally {

                    this.disabled = false;
                }
            }
        );

        /* تحميل البنوك */
        loadBanks();
    }
);

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleBankStatus(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    const id =
        row.dataset.id;

    try {

        const res =
            await fetch(
                banksApi.toggle(id),
                {
                    method: 'PATCH',

                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                }
            );

        const json =
            await res.json();

        if (!json.success) {

            showSystemToast(
                json.message || 'حدث خطأ',
                'danger'
            );

            return;
        }

        await loadBanks();

        showSystemToast(
            json.message,
            'success'
        );

    } catch (e) {

        console.error(e);

        showSystemToast(
            'حدث خطأ أثناء تبديل الحالة',
            'danger'
        );
    }
}

/* =========================================================
   طباعة
========================================================= */

function printBanks() {

    window.print();
}