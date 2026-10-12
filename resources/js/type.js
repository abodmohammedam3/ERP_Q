/* =========================================================
   شاشة أنواع الأصناف — نمط الوحدة 1 (بحث Prefix + ترقيم من الخادم)
========================================================= */

let typesData = [];
let editingTypeId = null;
let deletingTypeId = null;

/* Pagination (limit 100 + بحث prefix من الخادم) */
let typesPage = 1;
let typesLastPage = 1;
let typesSearchTerm = '';

const typeApi = {
    list: '/setting/inventory/types/list',
    store: '/setting/inventory/types',
    update: (id) => `/setting/inventory/types/${id}`,
    destroy: (id) => `/setting/inventory/types/${id}`,
    toggle: (id) => `/setting/inventory/types/${id}/toggle-status`,
};

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') || '';

/* =========================================================
   تحميل البيانات
========================================================= */

async function loadTypes() {
    try {
        const params = new URLSearchParams({
            page: typesPage,
        });

        if (typesSearchTerm) {
            params.set('search', typesSearchTerm);
        }

        const res = await fetch(`${typeApi.list}?${params}`, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();

        if (json.success) {
            typesData = json.data || [];
            typesLastPage = json.meta?.last_page || 1;

            renderTypes(typesData);
            renderTypesPagination();

            /* العدّاد = الإجمالي الحقيقي عبر الصفحات */
            const badge = document.getElementById('typesCountBadge');
            if (badge) {
                badge.textContent = json.meta?.total ?? typesData.length;
            }
        }
    } catch (e) {
        console.error('خطأ في تحميل الأنواع:', e);
    }
}

/* =========================================================
   عرض الجدول
========================================================= */

function renderTypes(data) {

    const list = Array.isArray(data)
        ? data
        : typesData;

    const tbody = document.getElementById('typesTableBody');
    if (!tbody) return;

    /* ---------- حالة: لا توجد بيانات ---------- */
    if (!list.length) {

        const emptyTpl = document.getElementById('emptyTypeRowTemplate');

        tbody.innerHTML = '';

        if (emptyTpl) {
            tbody.appendChild(emptyTpl.content.cloneNode(true));
        }

        return;
    }

    /* ---------- حالة: يوجد بيانات ---------- */
    const rowTpl = document.getElementById('typeRowTemplate');
    const codeBadgeTpl = document.getElementById('typeCodeBadgeTemplate');
    const codeDashTpl = document.getElementById('typeCodeDashTemplate');

    if (!rowTpl) return;

    const fragment = document.createDocumentFragment();

    list.forEach((type, i) => {

        const rowFragment = rowTpl.content.cloneNode(true);
        const row = rowFragment.querySelector('tr');
        const isAct = !!type.is_active;

        /* بيانات الصف (data-*) */
        row.dataset.id = type.id;
        row.dataset.name = type.name;
        row.dataset.code = type.code ?? '';
        row.dataset.active = isAct ? 1 : 0;

        /* الرقم التسلسلي واسم النوع */
        row.querySelector('.row-index').textContent = i + 1;
        row.querySelector('.row-name').textContent = type.name;

        /* خلية الرمز (شارة أو —) */
        const codeCell = row.querySelector('.row-code');
        if (codeCell) {
            codeCell.innerHTML = '';

            if (type.code && codeBadgeTpl) {
                const badgeFragment = codeBadgeTpl.content.cloneNode(true);
                badgeFragment.querySelector('span').textContent = type.code;
                codeCell.appendChild(badgeFragment);
            } else if (codeDashTpl) {
                codeCell.appendChild(codeDashTpl.content.cloneNode(true));
            }
        }

        /* زر الحالة */
        const statusBtn = row.querySelector('.toggle-status-btn');
        statusBtn.classList.add(isAct ? 'btn-success' : 'btn-secondary');
        statusBtn.textContent = isAct ? 'نشط' : 'غير نشط';
        statusBtn.title = isAct ? 'تعطيل' : 'تفعيل';

        fragment.appendChild(rowFragment);
    });

    tbody.innerHTML = '';
    tbody.appendChild(fragment);
}

/* =========================================================
   البحث (Prefix من الخادم — نمط الوحدة 1)
========================================================= */

function filterTypes() {

    typesSearchTerm =
        document
            .getElementById('searchTypeInput')
            ?.value
            .trim() || '';

    typesPage = 1;

    loadTypes();
}

/* =========================================================
   Pagination
========================================================= */

function renderTypesPagination() {

    const container = document.getElementById('typesPaginationList');
    if (!container) return;

    /* صفحة واحدة = لا نُظهر أزرار التنقل */
    if (typesLastPage <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    for (let p = 1; p <= typesLastPage; p++) {
        html += `
            <li class="page-item ${p === typesPage ? 'active' : ''}">
                <button type="button"
                        class="page-link"
                        onclick="goToTypePage(${p})">
                    ${p}
                </button>
            </li>
        `;
    }

    container.innerHTML = html;
}

async function goToTypePage(page) {
    typesPage = page;
    await loadTypes();
}

/* =========================================================
   فتح المودال (إضافة)
========================================================= */

function openTypeModal() {

    editingTypeId = null;

    document.getElementById('typeModalLabel').innerHTML =
        '<i class="bi bi-tags"></i> إضافة نوع';

    document.getElementById('typeForm').reset();
    document.getElementById('typeID').value = '';

    const modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById('typeModal'),
        { focus: false }
    );

    modal.show();
}

/* =========================================================
   تعديل
========================================================= */

function editType(btn) {

    const row = btn.closest('tr');
    if (!row) return;

    editingTypeId = row.dataset.id;

    document.getElementById('typeModalLabel').innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل النوع';

    document.getElementById('typeID').value = row.dataset.id;
    document.getElementById('typeName').value = row.dataset.name || '';
    document.getElementById('typeCode').value = row.dataset.code || '';

    const modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById('typeModal'),
        { focus: false }
    );

    modal.show();
}

/* =========================================================
   حفظ
========================================================= */

async function saveType() {

    const form = document.getElementById('typeForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const name = document.getElementById('typeName').value.trim();
    const code = document.getElementById('typeCode').value.trim();

    const isEdit = editingTypeId !== null;

    const payload = {
        name: name,
        code: code || null,
    };

    const url = isEdit ? typeApi.update(editingTypeId) : typeApi.store;
    const method = isEdit ? 'PUT' : 'POST';

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

        const modalInstance = bootstrap.Modal.getInstance(
            document.getElementById('typeModal')
        );
        if (modalInstance) {
            modalInstance.hide();
        }

        await loadTypes();

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
   حذف (نافذة تأكيد موحّدة — نمط الإعدادات)
========================================================= */

function deleteType(btn) {

    const row = btn.closest('tr');
    if (!row) return;

    deletingTypeId = row.dataset.id;

    document
        .getElementById('deleteConfirmModal')
        ?.classList
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

            deletingTypeId = null;

            document
                .getElementById('deleteConfirmModal')
                ?.classList
                .remove('show');
        });

    /* ---------- تأكيد الحذف ---------- */
    document
        .getElementById('deleteConfirmBtn')
        ?.addEventListener('click', async function () {

            if (!deletingTypeId) return;

            this.disabled = true;

            try {

                const res = await fetch(
                    typeApi.destroy(deletingTypeId),
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
                    ?.classList
                    .remove('show');

                deletingTypeId = null;

                await loadTypes();

                showSystemToast(
                    json.message || 'تم الحذف بنجاح',
                    'success'
                );

            } catch (e) {
                console.error(e);
                showSystemToast('حدث خطأ أثناء الحذف', 'danger');
            } finally {
                this.disabled = false;
            }
        });

    /* ---------- تحميل الأنواع ---------- */
    loadTypes();
});

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleTypeStatus(btn) {

    const row = btn.closest('tr');
    if (!row) return;

    const id = row.dataset.id;

    try {

        const res = await fetch(typeApi.toggle(id), {
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

        await loadTypes();

        showSystemToast(json.message, 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء تبديل الحالة', 'danger');
    }
}

/* =========================================================
   طباعة — القائمة الكاملة عبر full=1
========================================================= */

async function printTypes() {

    try {

        const res = await fetch(`${typeApi.list}?full=1`, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();

        const rows = json.success && Array.isArray(json.data)
            ? json.data
            : [];

        const printWindow = window.open('', '_blank');
        if (!printWindow) return;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        let rowsHtml = '';

        rows.forEach((type, i) => {
            rowsHtml += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${escapeHtml(type.name)}</td>
                    <td>${type.code ? escapeHtml(type.code) : '—'}</td>
                    <td>${type.is_active ? 'نشط' : 'غير نشط'}</td>
                </tr>
            `;
        });

        printWindow.document.write(`
            <html dir="rtl" lang="ar">
            <head>
                <title>طباعة قائمة الأنواع</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h2 { text-align: center; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; text-align: center; }
                    th, td { border: 1px solid #000; padding: 8px; }
                    th { background-color: #f8f9fa; }
                </style>
            </head>
            <body>
                <h2>قائمة أنواع الأصناف</h2>
                <table>
                    <thead>
                        <tr>
                            <th>الرقم</th>
                            <th>اسم النوع</th>
                            <th>الرمز</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            </body>
            </html>
        `);
        printWindow.document.close();

        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);

    } catch (e) {
        console.error(e);
        showSystemToast('تعذّرت طباعة القائمة', 'danger');
    }
}

/* ═══════════════════════════════════════════════════════════
   تصدير الدوال للنطاق العام
   ═══════════════════════════════════════════════════════════ */
Object.assign(window, {
    openTypeModal,
    editType,
    deleteType,
    saveType,
    toggleTypeStatus,
    printTypes,
    filterTypes,
    loadTypes,
    goToTypePage,
});



