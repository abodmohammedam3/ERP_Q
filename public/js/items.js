/* =========================================================
   شاشة الأصناف
========================================================= */

let itemsData = [];
let editingItemId = null;
let deletingItemId = null;

const itemsApi = {
    list: '/setting/inventory/items/list',
    store: '/setting/inventory/items',
    update: (id) => `/setting/inventory/items/${id}`,
    destroy: (id) => `/setting/inventory/items/${id}`,
    toggle: (id) => `/setting/inventory/items/${id}/toggle-status`,
};

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') || '';

/* =========================================================
   تحميل البيانات
========================================================= */

async function loadItems() {
    try {
        const res = await fetch(itemsApi.list, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();

        if (json.success) {
            itemsData = json.data || [];
            renderItems();
        }
    } catch (e) {
        console.error('خطأ في تحميل الأصناف:', e);
    }
}

/* =========================================================
   عرض الجدول
========================================================= */

function renderItems(data) {
    const list = Array.isArray(data) ? data : itemsData;
    const tbody = document.getElementById('itemsTableBody');
    const badge = document.getElementById('itemsCountBadge');

    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-5">
                    <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                    لا توجد أصناف مسجلة
                </td>
            </tr>
        `;
        if (badge) badge.textContent = '0 صنف';
        return;
    }

    tbody.innerHTML = list.map((item, i) => `
        <tr class="item-row text-center"
            data-id="${item.itemID}"
            data-name="${escapeHtml(item.itemName2)}"
            data-active="${item.is_active ? 1 : 0}">

            <td>${i + 1}</td>
            <td class="row-name text-center">${escapeHtml(item.itemName2)}</td>
            <td class="row-status">
                <button type="button"
                        class="btn btn-sm ${item.is_active ? 'btn-success' : 'btn-secondary'} toggle-status-btn"
                        onclick="toggleItemStatus(this)">
                    ${item.is_active ? 'نشط' : 'غير نشط'}
                </button>
            </td>
            <td class="no-print">
                <div class="btn-action-group">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editItem(this)">
                        <i class="bi bi-pencil d-md-none"></i>
                        <span class="d-none d-md-inline">تعديل</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteItem(this)">
                        <i class="bi bi-trash d-md-none"></i>
                        <span class="d-none d-md-inline">حذف</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    if (badge) badge.textContent = `${list.length} صنف`;
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

function filterItems() {
    const term = document.getElementById('searchItemInput')?.value.trim().toLowerCase() || '';

    const filtered = itemsData.filter(it =>
        (it.itemName2 || '').toLowerCase().includes(term)
    );

    renderItems(filtered);
}

/* =========================================================
   فتح المودال (إضافة)
========================================================= */

function openItemModal() {
    editingItemId = null;

    document.getElementById('itemModalLabel').innerHTML =
        '<i class="bi bi-box-seam"></i> إضافة صنف';

    document.getElementById('itemForm').reset();
    document.getElementById('itemID').value = '';

    const modalEl = document.getElementById('itemModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();
}

/* =========================================================
   تعديل
========================================================= */

function editItem(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    editingItemId = row.dataset.id;

    document.getElementById('itemModalLabel').innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل الصنف';

    document.getElementById('itemID').value = row.dataset.id;
    document.getElementById('itemName').value = row.dataset.name;

    const modalEl = document.getElementById('itemModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();
}

/* =========================================================
   حفظ
========================================================= */

async function saveItem() {
    const form = document.getElementById('itemForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const payload = {
        itemName2: document.getElementById('itemName').value.trim(),
    };

    const isEdit = editingItemId !== null;
    const url = isEdit ? itemsApi.update(editingItemId) : itemsApi.store;
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

        const modalEl = document.getElementById('itemModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();

        await loadItems();

        showSystemToast(json.message || 'تم الحفظ بنجاح', 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء الحفظ', 'danger');
    }
}

/* =========================================================
   حذف
========================================================= */

function deleteItem(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    deletingItemId = row.dataset.id;
    document.getElementById('deleteItemModal').classList.add('show');
}

document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('deleteItemCancelBtn')?.addEventListener('click', () => {
        deletingItemId = null;
        document.getElementById('deleteItemModal').classList.remove('show');
    });

    document.getElementById('deleteItemConfirmBtn')?.addEventListener('click', async function () {
        if (!deletingItemId) return;

        this.disabled = true;

        try {
            const res = await fetch(itemsApi.destroy(deletingItemId), {
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

            document.getElementById('deleteItemModal').classList.remove('show');
            deletingItemId = null;

            await loadItems();

            showSystemToast(json.message || 'تم الحذف بنجاح', 'success');

        } catch (e) {
            console.error(e);
            showSystemToast('حدث خطأ أثناء الحذف', 'danger');
        } finally {
            this.disabled = false;
        }
    });

    loadItems();
});

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleItemStatus(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    const id = row.dataset.id;

    try {
        const res = await fetch(itemsApi.toggle(id), {
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

        await loadItems();
        showSystemToast(json.message, 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء تبديل الحالة', 'danger');
    }
}

/* =========================================================
   طباعة
========================================================= */

function printItems() {
    window.print();
}