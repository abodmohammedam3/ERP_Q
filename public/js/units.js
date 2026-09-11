/* =========================================================
   شاشة الوحدات
========================================================= */

let unitsData = [];
let editingUnitId = null;
let deletingUnitId = null;

const unitsApi = {
    list: '/setting/inventory/units/list',
    store: '/setting/inventory/units',
    update: (id) => `/setting/inventory/units/${id}`,
    destroy: (id) => `/setting/inventory/units/${id}`,
    toggle: (id) => `/setting/inventory/units/${id}/toggle-status`,
};

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') || '';

/* =========================================================
   تحميل البيانات
========================================================= */

async function loadUnits() {
    try {
        const res = await fetch(unitsApi.list, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();

        if (json.success) {
            unitsData = json.data || [];
            renderUnits();
        }
    } catch (e) {
        console.error('خطأ في تحميل الوحدات:', e);
    }
}

/* =========================================================
   عرض الجدول
========================================================= */

function renderUnits(data) {

    const list = Array.isArray(data)
        ? data
        : unitsData;

    const tbody =
        document.getElementById('unitsTableBody');

    const badge =
        document.getElementById('unitsCountBadge');

    if (!tbody) return;

    /* ---------- حالة: لا توجد بيانات ---------- */
    if (!list.length) {

        const emptyTpl =
            document.getElementById('emptyUnitRowTemplate');

        tbody.innerHTML = '';

        if (emptyTpl) {
            tbody.appendChild(
                emptyTpl.content.cloneNode(true)
            );
        }

        if (badge) {
            badge.textContent = '0 وحدة';
        }

        return;
    }

    /* ---------- حالة: يوجد بيانات ---------- */
    const rowTpl =
        document.getElementById('unitRowTemplate');

    const fragment =
        document.createDocumentFragment();

    list.forEach((unit, i) => {

        const rowFragment =
            rowTpl.content.cloneNode(true);

        const row =
            rowFragment.querySelector('tr');

        const isAct = !!unit.is_active;

        /* بيانات الصف (data-*) */
        row.dataset.id = unit.UnitID;
        row.dataset.name = unit.UnitName;
        row.dataset.active = isAct ? 1 : 0;

        /* الرقم التسلسلي واسم الوحدة */
        row.querySelector('.row-index').textContent = i + 1;
        row.querySelector('.row-name').textContent = unit.UnitName;

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
        badge.textContent = `${list.length} وحدة`;
    }
}

/* =========================================================
   البحث
========================================================= */

function filterUnits() {

    const term =
        document
            .getElementById('searchUnitInput')
            ?.value
            .trim()
            .toLowerCase() || '';

    const filtered =
        unitsData.filter(u =>
            (u.UnitName || '')
                .toLowerCase()
                .includes(term)
        );

    renderUnits(filtered);
}

/* =========================================================
   فتح المودال (إضافة)
========================================================= */

function openUnitModal() {

    editingUnitId = null;

    document.getElementById('unitModalLabel').innerHTML =
        '<i class="bi bi-rulers"></i> إضافة وحدة';

    document.getElementById('unitForm').reset();
    document.getElementById('unitID').value = '';

    const modalEl =
        document.getElementById('unitModal');

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

function editUnit(btn) {

    const row = btn.closest('tr');
    if (!row) return;

    editingUnitId = row.dataset.id;

    document.getElementById('unitModalLabel').innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل الوحدة';

    document.getElementById('unitID').value = row.dataset.id;
    document.getElementById('unitName').value = row.dataset.name;

    const modalEl =
        document.getElementById('unitModal');

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

async function saveUnit() {

    const form = document.getElementById('unitForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const unitName =
        document.getElementById('unitName')
            .value.trim();

    const isEdit =
        editingUnitId !== null;

    /* =====================================================
       التحقق من عدم وجود تعديل
    ===================================================== */
    if (isEdit) {

        const row =
            document.querySelector(
                `#unitsTableBody tr.unit-row[data-id="${editingUnitId}"]`
            );

        if (row) {

            const originalName =
                row.dataset.name?.trim() || '';

            if (unitName === originalName) {

                showSystemToast(
                    'لم يتم إجراء أي تعديل على بيانات الوحدة.',
                    'danger'
                );

                return;
            }
        }
    }

    const payload = {
        UnitName: unitName,
    };

    const url =
        isEdit
            ? unitsApi.update(editingUnitId)
            : unitsApi.store;

    const method =
        isEdit
            ? 'PUT'
            : 'POST';

    try {

        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });

        const json = await res.json();

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
            document.getElementById('unitModal');

        const modalInstance =
            bootstrap.Modal.getInstance(modalEl);

        if (modalInstance) {
            modalInstance.hide();
        }

        await loadUnits();

        showSystemToast(
            json.message || 'تم الحفظ بنجاح',
            'success'
        );

    } catch (e) {

        console.error(e);
        showSystemToast('حدث خطأ أثناء الحفظ', 'danger');
    }
}

/* =========================================================
   حذف
========================================================= */

function deleteUnit(btn) {

    const row = btn.closest('tr');
    if (!row) return;

    deletingUnitId = row.dataset.id;

    document
        .getElementById('deleteConfirmModal')
        .classList
        .add('show');
}

/* =========================================================
   أحداث الصفحة
========================================================= */

document.addEventListener('DOMContentLoaded', () => {

    /* ---------- إلغاء الحذف ---------- */
    document
        .getElementById('deleteCancelBtn')
        ?.addEventListener('click', () => {

            deletingUnitId = null;

            document
                .getElementById('deleteConfirmModal')
                .classList
                .remove('show');
        });

    /* ---------- تأكيد الحذف ---------- */
    document
        .getElementById('deleteConfirmBtn')
        ?.addEventListener('click', async function () {

            if (!deletingUnitId) return;

            this.disabled = true;

            try {

                const res =
                    await fetch(
                        unitsApi.destroy(deletingUnitId),
                        {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        }
                    );

                const json = await res.json();

                if (!json.success) {
                    showSystemToast(
                        json.message || 'حدث خطأ',
                        'danger'
                    );
                    return;
                }

                document
                    .getElementById('deleteConfirmModal')
                    .classList
                    .remove('show');

                deletingUnitId = null;

                await loadUnits();

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
        });

    /* ---------- تحميل الوحدات ---------- */
    loadUnits();
});

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleUnitStatus(btn) {

    const row = btn.closest('tr');
    if (!row) return;

    const id = row.dataset.id;

    try {

        const res = await fetch(unitsApi.toggle(id), {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const json = await res.json();

        if (!json.success) {
            showSystemToast(
                json.message || 'حدث خطأ',
                'danger'
            );
            return;
        }

        await loadUnits();
        showSystemToast(json.message, 'success');

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

function printUnits() {
    window.print();
}