/* =========================================================
   شاشة الأنواع
========================================================= */

let typesData = [];
let editingTypeId = null;
let deletingTypeId = null;

const typesApi = {
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
        const res = await fetch(typesApi.list, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();

        if (json.success) {
            typesData = json.data || [];
            renderTypes();
        }
    } catch (e) {
        console.error('خطأ في تحميل الأنواع:', e);
    }
}

/* =========================================================
   عرض الجدول
========================================================= */

function renderTypes(data) {
    const list = Array.isArray(data) ? data : typesData;
    const tbody = document.getElementById('typesTableBody');
    const badge = document.getElementById('typesCountBadge');

    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-upc-scan fs-2 d-block mb-2"></i>
                    لا توجد أنواع مسجلة
                </td>
            </tr>
        `;
        if (badge) badge.textContent = '0 نوع';
        return;
    }

    tbody.innerHTML = list.map((type, i) => `
        <tr class="type-row text-center"
            data-id="${type.id}"
            data-name="${escapeHtml(type.name)}"
            data-code="${escapeHtml(type.code || '')}"
            data-active="${type.is_active ? 1 : 0}">

            <td>${i + 1}</td>
            <td class="row-name text-start">${escapeHtml(type.name)}</td>
            <td class="row-code">
                <span class="badge bg-secondary">${escapeHtml(type.code || '—')}</span>
            </td>
            <td class="row-status">
                <button type="button"
                        class="btn btn-sm ${type.is_active ? 'btn-success' : 'btn-secondary'} toggle-status-btn"
                        onclick="toggleTypeStatus(this)">
                    ${type.is_active ? 'نشط' : 'غير نشط'}
                </button>
            </td>
            <td class="no-print">
                <div class="btn-action-group">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editType(this)">
                        <i class="bi bi-pencil d-md-none"></i>
                        <span class="d-none d-md-inline">تعديل</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteType(this)">
                        <i class="bi bi-trash d-md-none"></i>
                        <span class="d-none d-md-inline">حذف</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    if (badge) badge.textContent = `${list.length} نوع`;
}

/* =========================================================
   أدوات
========================================================= */

function escapeHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* =========================================================
   البحث
========================================================= */

function filterTypes() {
    const term = document.getElementById('searchTypeInput')?.value.trim().toLowerCase() || '';

    const filtered = typesData.filter(t =>
        (t.name || '').toLowerCase().includes(term) ||
        (t.code || '').toLowerCase().includes(term)
    );

    renderTypes(filtered);
}

/* =========================================================
   فتح المودال (إضافة)
========================================================= */

function openTypeModal() {
    editingTypeId = null;

    document.getElementById('typeModalLabel').innerHTML =
        '<i class="bi bi-upc-scan"></i> إضافة نوع';

    document.getElementById('typeForm').reset();
    document.getElementById('typeID').value = '';

    const modalEl = document.getElementById('typeModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
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
    document.getElementById('typeName').value = row.dataset.name;
    document.getElementById('typeCode').value = row.dataset.code || '';

    const modalEl = document.getElementById('typeModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
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

    const payload = {
        name: document.getElementById('typeName').value.trim(),
        code: document.getElementById('typeCode').value.trim() || null,
    };

    const isEdit = editingTypeId !== null;
    const url = isEdit ? typesApi.update(editingTypeId) : typesApi.store;
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
            showSystemToast(json.message || 'حدث خطأ', 'danger');
            return;
        }

        if (document.activeElement && document.activeElement.blur) {
            document.activeElement.blur();
        }

        const modalEl = document.getElementById('typeModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();

        await loadTypes();

        showSystemToast(json.message || 'تم الحفظ بنجاح', 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء الحفظ', 'danger');
    }
}

/* =========================================================
   حذف
========================================================= */

function deleteType(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    deletingTypeId = row.dataset.id;
    document.getElementById('deleteTypeModal').classList.add('show');
}

document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('deleteTypeCancelBtn')?.addEventListener('click', () => {
        deletingTypeId = null;
        document.getElementById('deleteTypeModal').classList.remove('show');
    });

    document.getElementById('deleteTypeConfirmBtn')?.addEventListener('click', async function () {
        if (!deletingTypeId) return;

        this.disabled = true;

        try {
            const res = await fetch(typesApi.destroy(deletingTypeId), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            const json = await res.json();

            if (!json.success) {
                showSystemToast(json.message || 'حدث خطأ', 'danger');
                return;
            }

            document.getElementById('deleteTypeModal').classList.remove('show');
            deletingTypeId = null;

            await loadTypes();

            showSystemToast(json.message || 'تم الحذف بنجاح', 'success');

        } catch (e) {
            console.error(e);
            showSystemToast('حدث خطأ أثناء الحذف', 'danger');
        } finally {
            this.disabled = false;
        }
    });

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
        const res = await fetch(typesApi.toggle(id), {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const json = await res.json();

        if (!json.success) {
            showSystemToast(json.message || 'حدث خطأ', 'danger');
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
   طباعة
========================================================= */

function printTypes() {
    window.print();
}