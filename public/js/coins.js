/* =========================================================
   شاشة العملات
========================================================= */

let coinsData = [];
let editingCoinId = null;
let deletingCoinId = null;

const coinsApi = {
    list: '/setting/accounting/coins/list',
    store: '/setting/accounting/coins',
    update: (id) => `/setting/accounting/coins/${id}`,
    destroy: (id) => `/setting/accounting/coins/${id}`,
    toggle: (id) => `/setting/accounting/coins/${id}/toggle-status`,
};

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') || '';

/* =========================================================
   تحميل البيانات
========================================================= */

async function loadCoins() {
    try {
        const res = await fetch(coinsApi.list, {
            headers: { 'Accept': 'application/json' },
        });

        const json = await res.json();

        if (json.success) {
            coinsData = json.data || [];
            renderCoins();
        }

    } catch (e) {
        console.error('خطأ في تحميل العملات:', e);
    }
}

/* =========================================================
   عرض الجدول
========================================================= */

function renderCoins(data) {

    const list = Array.isArray(data)
        ? data
        : coinsData;

    const tbody =
        document.getElementById('coinsTableBody');

    const badge =
        document.getElementById('coinsCountBadge');

    if (!tbody) return;

    /* ---------- حالة: لا توجد بيانات ---------- */
    if (!list.length) {

        const emptyTpl =
            document.getElementById('emptyCoinRowTemplate');

        tbody.innerHTML = '';

        if (emptyTpl) {
            tbody.appendChild(
                emptyTpl.content.cloneNode(true)
            );
        }

        if (badge) {
            badge.textContent = '0 عملة';
        }

        return;
    }

    /* ---------- حالة: يوجد بيانات ---------- */
    const rowTpl =
        document.getElementById('coinRowTemplate');

    const codeBadgeTpl =
        document.getElementById('coinCodeBadgeTemplate');

    const systemYesTpl =
        document.getElementById('coinSystemYesTemplate');

    const systemNoTpl =
        document.getElementById('coinSystemNoTemplate');

    const fragment =
        document.createDocumentFragment();

    list.forEach((coin, i) => {

        const rowFragment =
            rowTpl.content.cloneNode(true);

        const row =
            rowFragment.querySelector('tr');

        const code = coin.coinsCode || '';
        const rate = coin.coinsExchangeRate || 0;
        const isSys = !!coin.coinsSystem;
        const isAct = !!coin.is_active;

        /* بيانات الصف (data-*) */
        row.dataset.id = coin.coinsID;
        row.dataset.name = coin.coinsName;
        row.dataset.code = code;
        row.dataset.rate = rate;
        row.dataset.system = isSys ? 1 : 0;
        row.dataset.active = isAct ? 1 : 0;

        /* الرقم التسلسلي واسم العملة */
        row.querySelector('.row-index').textContent = i + 1;
        row.querySelector('.row-name').textContent = coin.coinsName;

        /* خلية الرمز */
        const codeCell =
            row.querySelector('.row-code');

        if (code && codeBadgeTpl) {

            const badgeFragment =
                codeBadgeTpl.content.cloneNode(true);

            badgeFragment.querySelector('span')
                .textContent = code;

            codeCell.appendChild(badgeFragment);

        } else {

            const span = document.createElement('span');
            span.className = 'text-muted';
            span.textContent = '—';
            codeCell.appendChild(span);
        }

        /* سعر الصرف */
        row.querySelector('.row-rate').textContent =
            formatNumber(rate);

        /* خلية العملة الأساسية */
        const systemCell =
            row.querySelector('.row-system');

        const sysTpl =
            isSys ? systemYesTpl : systemNoTpl;

        if (sysTpl) {
            systemCell.appendChild(
                sysTpl.content.cloneNode(true)
            );
        }

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
        badge.textContent = `${list.length} عملة`;
    }
}

/* =========================================================
   أدوات مساعدة
========================================================= */

function formatNumber(v) {

    return Number(v || 0)
        .toLocaleString('en-US', {
            maximumFractionDigits: 6
        });
}

/* =========================================================
   البحث والتصفية
========================================================= */

function filterCoins() {

    const term =
        document
            .getElementById('searchCoinInput')
            ?.value
            .trim()
            .toLowerCase() || '';

    const status =
        document
            .getElementById('statusCoinFilter')
            ?.value || '';

    const filtered =
        coinsData.filter(c => {

            const matchesSearch =
                (c.coinsName || '')
                    .toLowerCase()
                    .includes(term) ||
                (c.coinsCode || '')
                    .toLowerCase()
                    .includes(term);

            const matchesStatus =
                !status ||
                (status === 'active' && c.is_active) ||
                (status === 'inactive' && !c.is_active);

            return matchesSearch && matchesStatus;
        });

    renderCoins(filtered);
}

/* =========================================================
   فتح المودال (إضافة)
========================================================= */

function openCoinModal() {

    editingCoinId = null;

    document.getElementById('coinModalLabel').innerHTML =
        '<i class="bi bi-currency-exchange"></i> إضافة عملة';

    document.getElementById('coinForm').reset();

    document.getElementById('coinID').value = '';

    document.getElementById('coinsSystem').value = '0';

    document.getElementById('isActive').value = '1';

    const modalEl =
        document.getElementById('coinModal');

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

function editCoin(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    editingCoinId =
        row.dataset.id;

    document.getElementById(
        'coinModalLabel'
    ).innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل العملة';

    document.getElementById('coinID').value = row.dataset.id;
    document.getElementById('coinsName').value = row.dataset.name;
    document.getElementById('coinsCode').value = row.dataset.code;
    document.getElementById('coinsExchangeRate').value = row.dataset.rate;
    document.getElementById('coinsSystem').value = row.dataset.system;
    document.getElementById('isActive').value = row.dataset.active;

    const modalEl =
        document.getElementById('coinModal');

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalEl,
            { focus: false }
        );

    modal.show();
}

/* =========================================================
   حفظ (إضافة/تعديل)
========================================================= */

async function saveCoin() {

    const form =
        document.getElementById('coinForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const coinsName =
        document.getElementById('coinsName')
            .value
            .trim();

    const coinsCode =
        document.getElementById('coinsCode')
            .value
            .trim()
            .toUpperCase();

    const coinsExchangeRate =
        document.getElementById('coinsExchangeRate')
            .value;

    const coinsSystem =
        document.getElementById('coinsSystem')
            .value === '1';

    const isActive =
        document.getElementById('isActive')
            .value === '1';

    /*
     * =====================================================
     * التحقق من التعديل
     * =====================================================
     */
    if (editingCoinId !== null) {

        const row =
            document.querySelector(
                `#coinsTableBody tr.coin-row[data-id="${editingCoinId}"]`
            );

        if (row) {

            const originalName = row.dataset.name?.trim() || '';
            const originalCode = row.dataset.code?.trim() || '';
            const originalRate = Number(row.dataset.rate || 0);
            const originalSystem = row.dataset.system === '1';
            const originalActive = row.dataset.active === '1';

            const nameChanged = coinsName !== originalName;
            const codeChanged = coinsCode !== originalCode;
            const rateChanged = Number(coinsExchangeRate) !== originalRate;
            const systemChanged = coinsSystem !== originalSystem;
            const statusChanged = isActive !== originalActive;

            if (
                !nameChanged &&
                !codeChanged &&
                !rateChanged &&
                !systemChanged &&
                !statusChanged
            ) {

                showSystemToast(
                    'لم يتم إجراء أي تعديل على بيانات العملة.',
                    'danger'
                );

                return;
            }
        }
    }

    const payload = {
        coinsName: coinsName,
        coinsCode: coinsCode,
        coinsExchangeRate: Number(coinsExchangeRate),
        coinsSystem: coinsSystem,
        is_active: isActive,
    };

    const isEdit =
        editingCoinId !== null;

    const url =
        isEdit
            ? coinsApi.update(editingCoinId)
            : coinsApi.store;

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
            document.getElementById('coinModal');

        const modalInstance =
            bootstrap.Modal.getInstance(modalEl);

        if (modalInstance) {
            modalInstance.hide();
        }

        await loadCoins();

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
   حذف
========================================================= */

function deleteCoin(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    deletingCoinId =
        row.dataset.id;

    document
        .getElementById('deleteCoinModal')
        .classList
        .add('show');
}

document.addEventListener(
    'DOMContentLoaded',
    () => {

        document
            .getElementById('deleteCoinCancelBtn')
            ?.addEventListener(
                'click',
                () => {

                    deletingCoinId = null;

                    document
                        .getElementById('deleteCoinModal')
                        .classList
                        .remove('show');
                }
            );

        document
            .getElementById('deleteCoinConfirmBtn')
            ?.addEventListener(
                'click',
                async function () {

                    if (!deletingCoinId) return;

                    this.disabled = true;

                    try {

                        const res =
                            await fetch(
                                coinsApi.destroy(deletingCoinId),
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
                                json.message || 'حدث خطأ',
                                'danger'
                            );

                            return;
                        }

                        document
                            .getElementById('deleteCoinModal')
                            .classList
                            .remove('show');

                        deletingCoinId = null;

                        await loadCoins();

                        showSystemToast(
                            json.message || 'تم الحذف بنجاح',
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

        loadCoins();
    }
);

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleCoinStatus(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    const id =
        row.dataset.id;

    try {

        const res =
            await fetch(
                coinsApi.toggle(id),
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

        await loadCoins();

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

function printCoins() {

    window.print();
}