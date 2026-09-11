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
    const list = Array.isArray(data) ? data : coinsData;
    const tbody = document.getElementById('coinsTableBody');
    const badge = document.getElementById('coinsCountBadge');

    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-currency-exchange fs-2 d-block mb-2"></i>
                    لا توجد عملات مسجلة
                </td>
            </tr>
        `;
        if (badge) badge.textContent = '0 عملة';
        return;
    }

    tbody.innerHTML = list.map((coin, i) => `
        <tr class="coin-row text-center"
            data-id="${coin.coinsID}"
            data-name="${escapeHtml(coin.coinsName)}"
            data-code="${escapeHtml(coin.coinsCode)}"
            data-rate="${coin.coinsExchangeRate}"
            data-system="${coin.coinsSystem ? 1 : 0}"
            data-active="${coin.is_active ? 1 : 0}">

            <td>${i + 1}</td>
            <td class="row-name text-start">${escapeHtml(coin.coinsName)}</td>
            <td class="row-code">
                <span class="badge bg-secondary">${escapeHtml(coin.coinsCode)}</span>
            </td>
            <td class="row-rate">${formatNumber(coin.coinsExchangeRate)}</td>
            <td class="row-system">
                ${coin.coinsSystem
            ? '<span class="badge bg-success">نعم</span>'
            : '<span class="badge bg-light text-dark border">لا</span>'}
            </td>
            <td class="row-status">
                <button type="button"
                        class="btn btn-sm ${coin.is_active ? 'btn-success' : 'btn-secondary'} toggle-status-btn"
                        onclick="toggleCoinStatus(this)">
                    ${coin.is_active ? 'نشط' : 'غير نشط'}
                </button>
            </td>
            <td class="no-print">
                <div class="btn-action-group">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCoin(this)">
                        <i class="bi bi-pencil d-md-none"></i>
                        <span class="d-none d-md-inline">تعديل</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCoin(this)">
                        <i class="bi bi-trash d-md-none"></i>
                        <span class="d-none d-md-inline">حذف</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    if (badge) badge.textContent = `${list.length} عملة`;
}

/* =========================================================
   أدوات
========================================================= */

function formatNumber(v) {
    return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 6 });
}

function escapeHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* =========================================================
   البحث والتصفية
========================================================= */

function filterCoins() {
    const term = document.getElementById('searchCoinInput')?.value.trim().toLowerCase() || '';
    const status = document.getElementById('statusCoinFilter')?.value || '';

    const filtered = coinsData.filter(c => {
        const matchesSearch =
            (c.coinsName || '').toLowerCase().includes(term) ||
            (c.coinsCode || '').toLowerCase().includes(term);

        let matchesStatus = true;
        if (status === 'active') matchesStatus = c.is_active === true || c.is_active === 1;
        if (status === 'inactive') matchesStatus = !(c.is_active === true || c.is_active === 1);

        return matchesSearch && matchesStatus;
    });

    renderCoins(filtered);
}

function resetCoinFilters() {
    const s = document.getElementById('searchCoinInput');
    const f = document.getElementById('statusCoinFilter');
    if (s) s.value = '';
    if (f) f.value = '';
    renderCoins();
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

    const modalEl = document.getElementById('coinModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();
}

/* =========================================================
   تعديل
========================================================= */

function editCoin(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    editingCoinId = row.dataset.id;

    document.getElementById('coinModalLabel').innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل العملة';

    document.getElementById('coinID').value = row.dataset.id;
    document.getElementById('coinsName').value = row.dataset.name;
    document.getElementById('coinsCode').value = row.dataset.code;
    document.getElementById('coinsExchangeRate').value = row.dataset.rate;
    document.getElementById('coinsSystem').value = row.dataset.system;
    document.getElementById('isActive').value = row.dataset.active;

    const modalEl = document.getElementById('coinModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();
}

/* =========================================================
   حفظ (إضافة/تعديل)
========================================================= */

async function saveCoin() {
    const form = document.getElementById('coinForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const payload = {
        coinsName: document.getElementById('coinsName').value.trim(),
        coinsCode: document.getElementById('coinsCode').value.trim().toUpperCase(),
        coinsExchangeRate: Number(document.getElementById('coinsExchangeRate').value),
        coinsSystem: document.getElementById('coinsSystem').value === '1',
        is_active: document.getElementById('isActive').value === '1',
    };

    const isEdit = editingCoinId !== null;
    const url = isEdit ? coinsApi.update(editingCoinId) : coinsApi.store;
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

        // إزالة التركيز قبل الإغلاق (تفادي تحذير aria-hidden)
        if (document.activeElement && document.activeElement.blur) {
            document.activeElement.blur();
        }

        const modalEl = document.getElementById('coinModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) {
            modalInstance.hide();
        }

        await loadCoins();

        showSystemToast(json.message || 'تم الحفظ بنجاح', 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء الحفظ', 'danger');
    }
}

/* =========================================================
   حذف
========================================================= */

function deleteCoin(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    if (row.dataset.system === '1') {
        showSystemToast('لا يمكن حذف العملة الأساسية للنظام', 'warning');
        return;
    }

    deletingCoinId = row.dataset.id;
    document.getElementById('deleteCoinModal').classList.add('show');
}

document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('deleteCoinCancelBtn')?.addEventListener('click', () => {
        deletingCoinId = null;
        document.getElementById('deleteCoinModal').classList.remove('show');
    });

    document.getElementById('deleteCoinConfirmBtn')?.addEventListener('click', async function () {
        if (!deletingCoinId) return;

        this.disabled = true;

        try {
            const res = await fetch(coinsApi.destroy(deletingCoinId), {
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

            document.getElementById('deleteCoinModal').classList.remove('show');
            deletingCoinId = null;

            await loadCoins();

            showSystemToast(json.message || 'تم الحذف بنجاح', 'success');

        } catch (e) {
            console.error(e);
            showSystemToast('حدث خطأ أثناء الحذف', 'danger');
        } finally {
            this.disabled = false;
        }
    });

    loadCoins();
});

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleCoinStatus(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    const id = row.dataset.id;

    if (row.dataset.system === '1' && row.dataset.active === '1') {
        showSystemToast('لا يمكن تعطيل العملة الأساسية للنظام', 'warning');
        return;
    }

    try {
        const res = await fetch(coinsApi.toggle(id), {
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

        await loadCoins();

        showSystemToast(json.message, 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء تبديل الحالة', 'danger');
    }
}

/* =========================================================
   طباعة
========================================================= */

function printCoins() {
    window.print();
}