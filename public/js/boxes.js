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
    const list = Array.isArray(data) ? data : boxesData;
    const tbody = document.getElementById('boxesTableBody');
    const badge = document.getElementById('boxesCountBadge');

    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-safe2 fs-2 d-block mb-2"></i>
                    لا توجد صناديق مسجلة
                </td>
            </tr>
        `;
        if (badge) badge.textContent = '0 صندوق';
        return;
    }

    tbody.innerHTML = list.map((box, i) => {
        const coinCode = box.coin?.coinsCode;
        const coinRate = box.coin?.coinsExchangeRate || 0;
        const accCode = box.account?.accCode || '—';
        const noCoin = !box.coinsID;

        const coinCell = coinCode
            ? `<span class="badge bg-secondary">${escapeHtml(coinCode)}</span>`
            : `<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill"></i> بلا عملة</span>`;

        return `
            <tr class="box-row text-center"
                data-id="${box.boxID}"
                data-name="${escapeHtml(box.boxName)}"
                data-coin="${box.coinsID || ''}"
                data-account-code="${accCode}"
                data-active="${box.is_active ? 1 : 0}"
                ${noCoin ? 'style="background-color: #fff3cd;"' : ''}>

                <td>${i + 1}</td>
                <td class="row-name text-start">${escapeHtml(box.boxName)}</td>
                <td class="row-coin">${coinCell}</td>
                <td class="row-rate">${noCoin ? '—' : formatNumber(coinRate)}</td>
                <td class="row-account">${accCode}</td>
                <td class="row-status">
                    <button type="button"
                            class="btn btn-sm ${box.is_active ? 'btn-success' : 'btn-secondary'} toggle-status-btn"
                            onclick="toggleBoxStatus(this)">
                        ${box.is_active ? 'نشط' : 'غير نشط'}
                    </button>
                </td>
                <td class="no-print">
                    <div class="btn-action-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBox(this)">
                            <i class="bi bi-pencil d-md-none"></i>
                            <span class="d-none d-md-inline">تعديل</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBox(this)">
                            <i class="bi bi-trash d-md-none"></i>
                            <span class="d-none d-md-inline">حذف</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    if (badge) badge.textContent = `${list.length} صندوق`;
}

/* =========================================================
   أدوات مساعدة
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

function filterBoxes() {
    const term = document.getElementById('searchBoxInput')?.value.trim().toLowerCase() || '';
    const coinId = document.getElementById('statusBoxFilter')?.value || '';

    const filtered = boxesData.filter(b => {
        const matchesSearch = (b.boxName || '').toLowerCase().includes(term);
        const matchesCoin = !coinId || String(b.coinsID) === String(coinId);
        return matchesSearch && matchesCoin;
    });

    renderBoxes(filtered);
}

/* =========================================================
   تحديث سعر الصرف عند اختيار العملة
========================================================= */

function updateExchangeRate() {
    const select = document.getElementById('coinsID');
    const rateInput = document.getElementById('exchangeRate');
    if (!select || !rateInput) return;

    const option = select.options[select.selectedIndex];
    const rate = option?.dataset?.rate || '';

    rateInput.value = rate ? Number(rate).toFixed(6) : '';
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

    const warning = document.getElementById('coinWarning');
    if (warning) warning.style.display = 'none';

    // جلب رقم الحساب التالي من الخادم
    try {
        const res = await fetch(boxesApi.nextCode, {
            headers: { 'Accept': 'application/json' },
        });
        const json = await res.json();
        if (json.success) {
            document.getElementById('accountCode').value = json.code;
        } else {
            showSystemToast(json.message || 'فشل جلب رقم الحساب', 'danger');
        }
    } catch (e) {
        console.error('فشل جلب رقم الحساب:', e);
    }

    const modalEl = document.getElementById('boxModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();
}

/* =========================================================
   تعديل
========================================================= */

function editBox(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    editingBoxId = row.dataset.id;

    document.getElementById('boxModalLabel').innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل الصندوق';

    document.getElementById('boxID').value = row.dataset.id;
    document.getElementById('boxName').value = row.dataset.name;
    document.getElementById('coinsID').value = row.dataset.coin || '';
    document.getElementById('isActive').value = row.dataset.active;
    document.getElementById('accountCode').value = row.dataset.accountCode || '';

    const warning = document.getElementById('coinWarning');
    if (warning) {
        warning.style.display = row.dataset.coin ? 'none' : 'block';
    }

    updateExchangeRate();

    const modalEl = document.getElementById('boxModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();
}

/* =========================================================
   حفظ (إضافة/تعديل)
========================================================= */

async function saveBox() {
    const form = document.getElementById('boxForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const coinValue = document.getElementById('coinsID').value;

    const payload = {
        boxName: document.getElementById('boxName').value.trim(),
        coinsID: coinValue ? Number(coinValue) : null,
        is_active: document.getElementById('isActive').value === '1',
    };

    const isEdit = editingBoxId !== null;
    const url = isEdit ? boxesApi.update(editingBoxId) : boxesApi.store;
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

        const modalEl = document.getElementById('boxModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();

        await loadBoxes();

        showSystemToast(json.message || 'تم الحفظ بنجاح', 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء الحفظ', 'danger');
    }
}

/* =========================================================
   حذف
========================================================= */

function deleteBox(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    deletingBoxId = row.dataset.id;
    document.getElementById('deleteBoxModal').classList.add('show');
}

document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('deleteBoxCancelBtn')?.addEventListener('click', () => {
        deletingBoxId = null;
        document.getElementById('deleteBoxModal').classList.remove('show');
    });

    document.getElementById('deleteBoxConfirmBtn')?.addEventListener('click', async function () {
        if (!deletingBoxId) return;

        this.disabled = true;

        try {
            const res = await fetch(boxesApi.destroy(deletingBoxId), {
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

            document.getElementById('deleteBoxModal').classList.remove('show');
            deletingBoxId = null;

            await loadBoxes();

            showSystemToast(json.message || 'تم الحذف بنجاح', 'success');

        } catch (e) {
            console.error(e);
            showSystemToast('حدث خطأ أثناء الحذف', 'danger');
        } finally {
            this.disabled = false;
        }
    });

    loadBoxes();
});

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleBoxStatus(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    const id = row.dataset.id;

    try {
        const res = await fetch(boxesApi.toggle(id), {
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

        await loadBoxes();
        showSystemToast(json.message, 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء تبديل الحالة', 'danger');
    }
}

/* =========================================================
   طباعة
========================================================= */

function printBoxes() {
    window.print();
}