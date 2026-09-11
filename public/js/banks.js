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
            headers: { 'Accept': 'application/json' },
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
    const list = Array.isArray(data) ? data : banksData;
    const tbody = document.getElementById('banksTableBody');
    const badge = document.getElementById('banksCountBadge');

    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="bi bi-bank fs-2 d-block mb-2"></i>
                    لا توجد بنوك مسجلة
                </td>
            </tr>
        `;
        if (badge) badge.textContent = '0 بنك';
        return;
    }

    tbody.innerHTML = list.map((bank, i) => {
        const coinCode = bank.coin?.coinsCode;
        const coinRate = bank.coin?.coinsExchangeRate || 0;
        const accCode = bank.account?.accCode || '—';
        const accountNumber = bank.accountNumber || '—';
        const noCoin = !bank.coinsID;

        const coinCell = coinCode
            ? `<span class="badge bg-secondary">${escapeHtml(coinCode)}</span>`
            : `<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill"></i> بلا عملة</span>`;

        return `
            <tr class="bank-row text-center"
                data-id="${bank.bankID}"
                data-name="${escapeHtml(bank.bankName)}"
                data-coin="${bank.coinsID || ''}"
                data-account-number="${escapeHtml(accountNumber)}"
                data-account-code="${accCode}"
                data-active="${bank.is_active ? 1 : 0}"
                ${noCoin ? 'style="background-color: #fff3cd;"' : ''}>

                <td>${i + 1}</td>
                <td class="row-name text-start">${escapeHtml(bank.bankName)}</td>
                <td class="row-coin">${coinCell}</td>
                <td class="row-rate">${noCoin ? '—' : formatNumber(coinRate)}</td>
                <td class="row-account-number">${escapeHtml(accountNumber)}</td>
                <td class="row-account">${accCode}</td>
                <td class="row-status">
                    <button type="button"
                            class="btn btn-sm ${bank.is_active ? 'btn-success' : 'btn-secondary'} toggle-status-btn"
                            onclick="toggleBankStatus(this)">
                        ${bank.is_active ? 'نشط' : 'غير نشط'}
                    </button>
                </td>
                <td class="no-print">
                    <div class="btn-action-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBank(this)">
                            <i class="bi bi-pencil d-md-none"></i>
                            <span class="d-none d-md-inline">تعديل</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBank(this)">
                            <i class="bi bi-trash d-md-none"></i>
                            <span class="d-none d-md-inline">حذف</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    if (badge) badge.textContent = `${list.length} بنك`;
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
   البحث
========================================================= */

function filterBanks() {
    const term = document.getElementById('searchBankInput')?.value.trim().toLowerCase() || '';
    const coinId = document.getElementById('statusBankFilter')?.value || '';

    const filtered = banksData.filter(b => {
        const matchesSearch = (b.bankName || '').toLowerCase().includes(term);
        const matchesCoin = !coinId || String(b.coinsID) === String(coinId);
        return matchesSearch && matchesCoin;
    });

    renderBanks(filtered);
}

/* =========================================================
   تحديث سعر الصرف
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

    const warning = document.getElementById('coinWarning');
    if (warning) warning.style.display = 'none';

    try {
        const res = await fetch(banksApi.nextCode, {
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

    const modalEl = document.getElementById('bankModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();
}

/* =========================================================
   تعديل
========================================================= */

function editBank(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    editingBankId = row.dataset.id;

    document.getElementById('bankModalLabel').innerHTML =
        '<i class="bi bi-pencil-square"></i> تعديل البنك';

    document.getElementById('bankID').value = row.dataset.id;
    document.getElementById('bankName').value = row.dataset.name;
    document.getElementById('coinsID').value = row.dataset.coin || '';
    document.getElementById('accountNumber').value = row.dataset.accountNumber || '';
    document.getElementById('accountCode').value = row.dataset.accountCode || '';
    document.getElementById('isActive').value = row.dataset.active;

    const warning = document.getElementById('coinWarning');
    if (warning) {
        warning.style.display = row.dataset.coin ? 'none' : 'block';
    }

    updateExchangeRate();

    const modalEl = document.getElementById('bankModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();
}

/* =========================================================
   حفظ
========================================================= */

async function saveBank() {
    const form = document.getElementById('bankForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const coinValue = document.getElementById('coinsID').value;
    const accNum = document.getElementById('accountNumber').value.trim();

    const payload = {
        bankName: document.getElementById('bankName').value.trim(),
        coinsID: coinValue ? Number(coinValue) : null,
        accountNumber: accNum || null,
        is_active: document.getElementById('isActive').value === '1',
    };

    const isEdit = editingBankId !== null;
    const url = isEdit ? banksApi.update(editingBankId) : banksApi.store;
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

        const modalEl = document.getElementById('bankModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();

        await loadBanks();

        showSystemToast(json.message || 'تم الحفظ بنجاح', 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء الحفظ', 'danger');
    }
}

/* =========================================================
   حذف
========================================================= */

function deleteBank(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    deletingBankId = row.dataset.id;
    document.getElementById('deleteBankModal').classList.add('show');
}

document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('deleteBankCancelBtn')?.addEventListener('click', () => {
        deletingBankId = null;
        document.getElementById('deleteBankModal').classList.remove('show');
    });

    document.getElementById('deleteBankConfirmBtn')?.addEventListener('click', async function () {
        if (!deletingBankId) return;

        this.disabled = true;

        try {
            const res = await fetch(banksApi.destroy(deletingBankId), {
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

            document.getElementById('deleteBankModal').classList.remove('show');
            deletingBankId = null;

            await loadBanks();

            showSystemToast(json.message || 'تم الحذف بنجاح', 'success');

        } catch (e) {
            console.error(e);
            showSystemToast('حدث خطأ أثناء الحذف', 'danger');
        } finally {
            this.disabled = false;
        }
    });

    loadBanks();
});

/* =========================================================
   تبديل الحالة
========================================================= */

async function toggleBankStatus(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    const id = row.dataset.id;

    try {
        const res = await fetch(banksApi.toggle(id), {
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

        await loadBanks();
        showSystemToast(json.message, 'success');

    } catch (e) {
        console.error(e);
        showSystemToast('حدث خطأ أثناء تبديل الحالة', 'danger');
    }
}

/* =========================================================
   طباعة
========================================================= */

function printBanks() {
    window.print();
}