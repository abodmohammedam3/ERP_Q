/* =========================================================
   شاشة الصناديق
========================================================= */

let boxesData = [];
let editingBoxId = null;
let deletingBoxId = null;

const boxesApi = {
    list: '/setting/accounting/boxes/list',
    store: '/setting/accounting/boxes',
    update: (id) => `/setting/accounting/boxes/${id}`,
    destroy: (id) => `/setting/accounting/boxes/${id}`,
    toggle: (id) => `/setting/accounting/boxes/${id}/toggle-status`,
    nextCode: '/setting/accounting/boxes/next-code',
};

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') || '';

/* =========================================================
   تحميل البيانات
========================================================= */

async function loadBoxes() {
    try {
        const res = await fetch(boxesApi.list, {
            headers: { 'Accept': 'application/json' },
        });

        const json = await res.json();

        if (json.success) {
            boxesData = json.data || [];
            renderBoxes();
        }

    } catch (e) {
        console.error('خطأ في تحميل الصناديق:', e);
    }
}

/* =========================================================
   عرض الجدول
========================================================= */

function renderBoxes(data) {

    const list = Array.isArray(data)
        ? data
        : boxesData;

    const tbody =
        document.getElementById('boxesTableBody');

    const badge =
        document.getElementById('boxesCountBadge');

    if (!tbody) return;

    /* ---------- حالة: لا توجد بيانات ---------- */
    if (!list.length) {

        const emptyTpl =
            document.getElementById('emptyBoxRowTemplate');

        tbody.innerHTML = '';

        if (emptyTpl) {
            tbody.appendChild(
                emptyTpl.content.cloneNode(true)
            );
        }

        if (badge) {
            badge.textContent = '0 صندوق';
        }

        return;
    }

    /* ---------- حالة: يوجد بيانات ---------- */
    const rowTpl =
        document.getElementById('boxRowTemplate');

    const coinBadgeTpl =
        document.getElementById('boxCoinBadgeTemplate');

    const fragment =
        document.createDocumentFragment();

    list.forEach((box, i) => {

        const rowFragment =
            rowTpl.content.cloneNode(true);

        const row =
            rowFragment.querySelector('tr');

        const coinCode =
            box.coin?.coinsCode || '';

        const coinRate =
            box.coin?.coinsExchangeRate || 0;

        const accCode =
            box.account?.accCode || '—';

        /* بيانات الصف (data-*) */
        row.dataset.id = box.boxID;
        row.dataset.name = box.boxName;
        row.dataset.coin = box.coinsID || '';
        row.dataset.accountCode = accCode;
        row.dataset.active = box.is_active ? 1 : 0;

        /* الرقم التسلسلي واسم الصندوق */
        row.querySelector('.row-index').textContent = i + 1;
        row.querySelector('.row-name').textContent = box.boxName;

        /* خلية العملة */
        const coinCell =
            row.querySelector('.row-coin');

        if (coinCode && coinBadgeTpl) {

            const badgeFragment =
                coinBadgeTpl.content.cloneNode(true);

            badgeFragment.querySelector('span')
                .textContent = coinCode;

            coinCell.appendChild(badgeFragment);

        } else {

            const span = document.createElement('span');
            span.className = 'text-muted';
            span.textContent = '—';
            coinCell.appendChild(span);
        }

        /* سعر الصرف ورقم الحساب */
        row.querySelector('.row-rate').textContent =
            formatNumber(coinRate);

        row.querySelector('.row-account').textContent =
            accCode;

        /* زر الحالة */
        const statusBtn =
            row.querySelector('.toggle-status-btn');

        statusBtn.classList.add(
            box.is_active ? 'btn-success' : 'btn-secondary'
        );

        statusBtn.textContent =
            box.is_active ? 'نشط' : 'غير نشط';

        fragment.appendChild(rowFragment);
    });

    tbody.innerHTML = '';
    tbody.appendChild(fragment);

    if (badge) {
        badge.textContent = `${list.length} صندوق`;
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

function filterBoxes() {

    const term =
        document
            .getElementById('searchBoxInput')
            ?.value
            .trim()
            .toLowerCase() || '';

    const coinId =
        document
            .getElementById('statusBoxFilter')
            ?.value || '';

    const filtered =
        boxesData.filter(b => {

            const matchesSearch =
                (b.boxName || '')
                    .toLowerCase()
                    .includes(term);

            const matchesCoin =
                !coinId ||
                String(b.coinsID) === String(coinId);

            return matchesSearch && matchesCoin;
        });

    renderBoxes(filtered);
}

/* =========================================================
   تحديث سعر الصرف عند اختيار العملة
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
   فتح المودال (إضافة)
========================================================= */

async function openBoxModal() {

    editingBoxId = null;

    document.getElementById('boxModalLabel').innerHTML =
        '<i class="bi bi-safe2"></i> إضافة صندوق';

    document.getElementById('boxForm').reset();

    document.getElementById('boxID').value = '';

    document.getElementById('isActive').value = '1';

    document.getElementById('exchangeRate').value = '';

    document.getElementById('accountCode').value = '';

    /*
     * جلب رقم الحساب التالي من الخادم
     */
    try {

        const res =
            await fetch(boxesApi.nextCode, {
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
                json.message ||
                'فشل جلب رقم الحساب',
                'danger'
            );
        }

    } catch (e) {

        console.error(
            'فشل جلب رقم الحساب:',
            e
        );
    }

    const modalEl =
        document.getElementById('boxModal');

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

function editBox(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    editingBoxId =
        row.dataset.id;

    document.getElementById(
        'boxModalLabel'
    ).innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل الصندوق';

    document.getElementById(
        'boxID'
    ).value =
        row.dataset.id;

    document.getElementById(
        'boxName'
    ).value =
        row.dataset.name;

    document.getElementById(
        'coinsID'
    ).value =
        row.dataset.coin || '';

    document.getElementById(
        'isActive'
    ).value =
        row.dataset.active;

    document.getElementById(
        'accountCode'
    ).value =
        row.dataset.accountCode || '';

    updateExchangeRate();

    const modalEl =
        document.getElementById('boxModal');

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

async function saveBox() {

    const form =
        document.getElementById('boxForm');

    /*
     * التحقق من صحة النموذج
     *
     * العملة مطلوبة من خلال required
     * في select الخاص بالعملة.
     */
    if (!form.checkValidity()) {

        form.reportValidity();

        return;
    }

    const boxName =
        document
            .getElementById('boxName')
            .value
            .trim();

    const coinValue =
        document
            .getElementById('coinsID')
            .value;

    const isActive =
        document
            .getElementById('isActive')
            .value === '1';

    /*
     * =====================================================
     * التحقق من التعديل
     * =====================================================
     *
     * إذا كان تعديل صندوق موجود:
     * نقارن البيانات الحالية بالبيانات الأصلية الموجودة
     * في صف الصندوق.
     *
     * إذا لم يحدث أي تغيير:
     * لا نرسل الطلب إلى الخادم.
     */
    if (editingBoxId !== null) {

        const row =
            document.querySelector(
                `#boxesTableBody tr.box-row[data-id="${editingBoxId}"]`
            );

        if (row) {

            const originalName =
                row.dataset.name?.trim() || '';

            const originalCoin =
                row.dataset.coin || '';

            const originalActive =
                row.dataset.active === '1';

            const nameChanged =
                boxName !== originalName;

            const coinChanged =
                String(coinValue || '') !==
                String(originalCoin || '');

            const statusChanged =
                isActive !== originalActive;

            /*
             * لا يوجد أي تغيير
             */
            if (
                !nameChanged &&
                !coinChanged &&
                !statusChanged
            ) {

                showSystemToast(
                    'لم يتم إجراء أي تعديل على بيانات الصندوق.',
                    'danger'
                );

                return;
            }
        }
    }

    /*
     * العملة إجبارية،
     * لذلك نرسل قيمتها مباشرة.
     */
    const payload = {

        boxName: boxName,

        coinsID: Number(coinValue),

        is_active: isActive,
    };

    const isEdit =
        editingBoxId !== null;

    const url =
        isEdit
            ? boxesApi.update(editingBoxId)
            : boxesApi.store;

    const method =
        isEdit
            ? 'PUT'
            : 'POST';

    try {

        const res =
            await fetch(url, {

                method,

                headers: {

                    'Content-Type':
                        'application/json',

                    'Accept':
                        'application/json',

                    'X-CSRF-TOKEN':
                        csrfToken,
                },

                body:
                    JSON.stringify(payload),
            });

        const json =
            await res.json();

        if (!json.success) {

            showSystemToast(
                json.message ||
                'حدث خطأ',
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
            document.getElementById('boxModal');

        const modalInstance =
            bootstrap.Modal.getInstance(
                modalEl
            );

        if (modalInstance) {
            modalInstance.hide();
        }

        await loadBoxes();

        showSystemToast(
            json.message ||
            'تم الحفظ بنجاح',
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

function deleteBox(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    deletingBoxId =
        row.dataset.id;

    document
        .getElementById('deleteBoxModal')
        .classList
        .add('show');
}

document.addEventListener(
    'DOMContentLoaded',
    () => {

        document
            .getElementById(
                'deleteBoxCancelBtn'
            )
            ?.addEventListener(
                'click',
                () => {

                    deletingBoxId = null;

                    document
                        .getElementById(
                            'deleteBoxModal'
                        )
                        .classList
                        .remove('show');
                }
            );

        document
            .getElementById(
                'deleteBoxConfirmBtn'
            )
            ?.addEventListener(
                'click',
                async function () {

                    if (!deletingBoxId) return;

                    this.disabled = true;

                    try {

                        const res =
                            await fetch(
                                boxesApi.destroy(
                                    deletingBoxId
                                ),
                                {
                                    method: 'DELETE',

                                    headers: {

                                        'Accept':
                                            'application/json',

                                        'X-CSRF-TOKEN':
                                            csrfToken,
                                    },
                                }
                            );

                        const json =
                            await res.json();

                        if (!json.success) {

                            showSystemToast(
                                json.message ||
                                'حدث خطأ',
                                'danger'
                            );

                            return;
                        }

                        document
                            .getElementById(
                                'deleteBoxModal'
                            )
                            .classList
                            .remove('show');

                        deletingBoxId = null;

                        await loadBoxes();

                        showSystemToast(
                            json.message ||
                            'تم الحذف بنجاح',
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

        loadBoxes();
    }
);

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleBoxStatus(btn) {

    const row =
        btn.closest('tr');

    if (!row) return;

    const id =
        row.dataset.id;

    try {

        const res =
            await fetch(
                boxesApi.toggle(id),
                {
                    method: 'PATCH',

                    headers: {

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            csrfToken,
                    },
                }
            );

        const json =
            await res.json();

        if (!json.success) {

            showSystemToast(
                json.message ||
                'حدث خطأ',
                'danger'
            );

            return;
        }

        await loadBoxes();

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

function printBoxes() {

    window.print();
}