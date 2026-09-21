/* ============================================================
   شاشة الأرصدة الافتتاحية
   ============================================================ */

'use strict';

// ─────────────────────────────────────────────────────────────
//  الحالة العامة
// ─────────────────────────────────────────────────────────────
const State = {
    linesCounter:       0,
    isEditMode:         false,
    originalSnapshot:   null,
    activeDisplayInput: null,
    activePickerType:   'CASH',
    pickerAccounts:     [],
    searchTimer:        null,
    pickerTimer:        null,
    listAbort:          null,
    systemCurrencyCode: '',

    // الترقيم
    currentPage:        1,
    perPage:            10,
    totalRows:          0,
    cachedRows:         [],
    cachedTotals:       null,
};

// ─────────────────────────────────────────────────────────────
//  المسارات
// ─────────────────────────────────────────────────────────────
const API = {
    list:    '/setting/accounting/openingBalances/list',
    picker:  '/setting/accounting/openingBalances/picker',
    edit:    (id) => `/setting/accounting/openingBalances/${id}/edit`,
    store:   '/setting/accounting/openingBalances',
    update:  (id) => `/setting/accounting/openingBalances/${id}`,
    destroy: (id) => `/setting/accounting/openingBalances/${id}`,
};

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

// ─────────────────────────────────────────────────────────────
//  عناصر الصفحة
// ─────────────────────────────────────────────────────────────
const El = {};

// ─────────────────────────────────────────────────────────────
//  أدوات
// ─────────────────────────────────────────────────────────────
const formatMoney = (v) => Number(v || 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function toast(message, type = 'info') {
    if (typeof showSystemToast === 'function') {
        showSystemToast(message, type);
    } else {
        console.log(`[${type}] ${message}`);
    }
}

// ─────────────────────────────────────────────────────────────
//  تحميل الجدول
// ─────────────────────────────────────────────────────────────
async function loadTable(search = '') {
    abortPendingRequest();

    State.listAbort = new AbortController();

    try {
        const response = await fetch(
            `${API.list}?type=${El.currentType.value}&search=${encodeURIComponent(search)}`,
            {
                headers: { 'Accept': 'application/json' },
                signal:  State.listAbort.signal,
            }
        );

        const json = await response.json();

        if (!json.success) {
            toast(json.message || 'فشل تحميل البيانات', 'danger');
            return;
        }

        State.cachedRows   = json.rows || [];
        State.cachedTotals = json.totals || {};
        State.totalRows    = State.cachedRows.length;
        State.currentPage  = 1;

        renderCurrentPage();
        renderTotals(State.cachedTotals);
        renderPagination();

    } catch (error) {
        if (error.name === 'AbortError') return;

        console.error('فشل تحميل الأرصدة:', error);
        toast('تعذّر تحميل البيانات', 'danger');
    }
}

function abortPendingRequest() {
    if (State.listAbort) {
        State.listAbort.abort();
    }
}

// ─────────────────────────────────────────────────────────────
//  الترقيم
// ─────────────────────────────────────────────────────────────
function renderCurrentPage() {
    const start = (State.currentPage - 1) * State.perPage;
    const end   = start + State.perPage;
    const page  = State.cachedRows.slice(start, end);

    renderTable(page, start);
}

function renderTable(rows, startIndex = 0) {
    El.tableBody.querySelectorAll('tr:not(#noDataRow)').forEach((r) => r.remove());

    if (!rows.length) {
        El.noDataRow.style.display = '';
        return;
    }

    El.noDataRow.style.display = 'none';

    const fragment = document.createDocumentFragment();

    rows.forEach((row, i) => {
        fragment.appendChild(buildTableRow(row, startIndex + i));
    });

    El.tableBody.appendChild(fragment);
}

function renderPagination() {
    const container = document.getElementById('obPagination');
    if (!container) return;

    const totalPages = Math.ceil(State.totalRows / State.perPage);

    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    const start = (State.currentPage - 1) * State.perPage + 1;
    const end   = Math.min(State.currentPage * State.perPage, State.totalRows);

    let buttons = '';

    buttons += `
        <li class="page-item ${State.currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${State.currentPage - 1}">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;

    for (let i = 1; i <= totalPages; i++) {
        if (
            i === 1 ||
            i === totalPages ||
            (i >= State.currentPage - 2 && i <= State.currentPage + 2)
        ) {
            buttons += `
                <li class="page-item ${i === State.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        } else if (
            i === State.currentPage - 3 ||
            i === State.currentPage + 3
        ) {
            buttons += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    buttons += `
        <li class="page-item ${State.currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${State.currentPage + 1}">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;

    container.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">
                عرض ${start} - ${end} من ${State.totalRows}
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    ${buttons}
                </ul>
            </nav>
        </div>
    `;

    container.querySelectorAll('.page-link[data-page]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const page = parseInt(btn.dataset.page);

            if (page < 1 || page > totalPages) return;
            if (page === State.currentPage) return;

            State.currentPage = page;
            renderCurrentPage();
            renderPagination();

            document.getElementById('obTable')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });
    });
}

// ─────────────────────────────────────────────────────────────
//  بناء صف الجدول
// ─────────────────────────────────────────────────────────────
function buildTableRow(row, index) {
    const clone = El.rowTemplate.content.cloneNode(true);
    const tr = clone.querySelector('tr');

    setText(tr, '.row-index',        index + 1);
    setText(tr, '.row-account-code', row.entity_code || row.account_code);
    setText(tr, '.row-account-name', row.entity_name || row.account_name);
    setText(tr, '.row-currency',     row.currency_code || '—');
    setText(tr, '.row-rate',         row.currency_code ? formatMoney(row.exchange_rate) : '—');

    const notesCell = tr.querySelector('.row-notes');
    notesCell.textContent = row.notes || '';
    notesCell.title       = row.notes || '';

    renderAmountCell(tr.querySelector('.row-debit'),  row.debit,  row.local_debit,  row.currency_code);
    renderAmountCell(tr.querySelector('.row-credit'), row.credit, row.local_credit, row.currency_code);
    renderAmountCell(tr.querySelector('.row-net'),    row.net,    row.local_net,    row.currency_code);

    tr.querySelector('.btn-edit-row').dataset.id     = row.id;
    tr.querySelector('.btn-delete-row').dataset.id   = row.id;
    tr.querySelector('.btn-delete-row').dataset.name = row.entity_name || row.account_name || '';

    return tr;
}

function renderAmountCell(cell, foreign, local, currencyCode) {
    if (!cell) return;

    const foreignDiv = cell.querySelector('.amount-foreign');
    const localDiv   = cell.querySelector('.amount-local');

    if (!foreignDiv || !localDiv) return;

    const isSystemCurrency = !currencyCode || currencyCode === State.systemCurrencyCode;

    if (isSystemCurrency) {
        foreignDiv.style.display = 'none';
        foreignDiv.textContent   = '';
        localDiv.textContent     = formatMoney(local);
        localDiv.className       = 'amount-single';
        return;
    }

    foreignDiv.style.display = '';
    foreignDiv.textContent   = `${formatMoney(foreign)} ${currencyCode}`;
    localDiv.textContent     = `${formatMoney(local)} ${State.systemCurrencyCode}`;
    localDiv.className       = 'amount-local';
}

function renderTotals(totals) {
    setText(El.totalDebit,  null, formatMoney(totals.local_debit));
    setText(El.totalCredit, null, formatMoney(totals.local_credit));
    setText(El.totalNet,    null, formatMoney(totals.local_net));
}

function setText(parent, selector, value) {
    const el = selector ? parent.querySelector(selector) : parent;
    if (el) el.textContent = value ?? '';
}

// ─────────────────────────────────────────────────────────────
//  إدارة الأسطر داخل المودل
// ─────────────────────────────────────────────────────────────
function addLine(data = {}) {
    State.linesCounter++;

    const n = State.linesCounter;
    const clone = El.lineTemplate.content.cloneNode(true);
    const tr = clone.querySelector('tr');

    if (State.isEditMode) {
        const btn = tr.querySelector('.btn-remove-line');
        if (btn) btn.style.display = 'none';
    }

    tr.querySelector('.line-number').textContent = n;

    bindLineFields(tr, n);
    fillLineValues(tr, data);
    bindLineEvents(tr);

    El.linesBody.appendChild(clone);
    scrollToLastLine();
}

function bindLineFields(tr, n) {
    tr.querySelector('.line-type').name       = `lines[${n}][type]`;
    tr.querySelector('.line-account-id').name = `lines[${n}][account_id]`;
    tr.querySelector('.line-entity-id').name  = `lines[${n}][entity_id]`;
    tr.querySelector('.line-currency').name   = `lines[${n}][currency_id]`;
    tr.querySelector('.line-rate').name       = `lines[${n}][exchange_rate]`;
    tr.querySelector('.line-debit').name      = `lines[${n}][debit]`;
    tr.querySelector('.line-credit').name     = `lines[${n}][credit]`;
    tr.querySelector('.line-notes').name      = `lines[${n}][notes]`;
}

function fillLineValues(tr, data) {
    const type = tr.querySelector('.line-type');
    type.value = data.type || El.currentType.value || 'CASH';

    if (data.account_id) {
        tr.querySelector('.line-account-id').value   = data.account_id;
        tr.querySelector('.line-account-code').value = data.account_code || '';
        tr.querySelector('.line-entity-id').value    = data.entity_id || '';

        const display = tr.querySelector('.line-account-display');
        display.value = data.entity_code || data.entity_name
            ? `${data.entity_code || ''} - ${data.entity_name || ''}`.trim()
            : `${data.account_code || ''} - ${data.account_name || ''}`.trim();
    }

    if (data.currency_id)   tr.querySelector('.line-currency').value = data.currency_id;
    if (data.exchange_rate) tr.querySelector('.line-rate').value = Number(data.exchange_rate).toFixed(2);
    if (data.debit)         tr.querySelector('.line-debit').value  = data.debit;
    if (data.credit)        tr.querySelector('.line-credit').value = data.credit;
    if (data.notes)         tr.querySelector('.line-notes').value  = data.notes;
}

function bindLineEvents(tr) {
    tr.querySelector('.line-type').addEventListener('change', () => resetLineAccount(tr));
    tr.querySelector('.line-account-display').addEventListener('click', (e) => openPicker(e.target));
    tr.querySelector('.line-currency').addEventListener('change', (e) => applyCurrencyRate(e.target, tr));
    tr.querySelector('.line-debit').addEventListener('input', (e) => zeroOther(e.target, tr.querySelector('.line-credit')));
    tr.querySelector('.line-credit').addEventListener('input', (e) => zeroOther(e.target, tr.querySelector('.line-debit')));
    tr.querySelector('.btn-remove-line').addEventListener('click', () => {
        tr.remove();
        renumberLines();
    });
}

function resetLineAccount(tr) {
    tr.querySelector('.line-account-display').value = '';
    tr.querySelector('.line-account-id').value      = '';
    tr.querySelector('.line-account-code').value    = '';
    tr.querySelector('.line-entity-id').value       = '';
}

function applyCurrencyRate(select, tr) {
    const rate = select.options[select.selectedIndex]?.dataset?.rate || 1;
    tr.querySelector('.line-rate').value = Number(rate).toFixed(2);
}

function zeroOther(input, other) {
    if (parseFloat(input.value) > 0) other.value = 0;
}

function renumberLines() {
    const rows = El.linesBody.querySelectorAll('tr');
    rows.forEach((row, i) => {
        row.querySelector('.line-number').textContent = i + 1;
    });
    State.linesCounter = rows.length;
}

function scrollToLastLine() {
    const lastRow = El.linesBody.querySelector('tr:last-child');
    if (!lastRow) return;

    const container = El.linesBody.closest('.table-responsive');
    if (!container) return;

    requestAnimationFrame(() => {
        const bottom  = lastRow.offsetTop + lastRow.offsetHeight;
        const visible = container.scrollTop + container.clientHeight;

        if (bottom > visible) {
            container.scrollTo({ top: bottom - container.clientHeight, behavior: 'smooth' });
        }
    });
}

// ─────────────────────────────────────────────────────────────
//  فتح / إغلاق المودلات
// ─────────────────────────────────────────────────────────────
function openAddModal() {
    State.isEditMode = false;
    State.originalSnapshot = null;

    El.addEditTitle.textContent = 'إضافة رصيد افتتاحي';
    El.editId.value = '';
    El.obForm.reset();
    El.linesBody.innerHTML = '';
    State.linesCounter = 0;

    El.btnAddLine.style.display = '';
    addLine();

    bootstrap.Modal.getOrCreateInstance(El.addEditModal, { focus: false }).show();
}

async function openEditModal(id) {
    try {
        const response = await fetch(API.edit(id), {
            headers: { 'Accept': 'application/json' },
        });
        const json = await response.json();

        if (!json.success) {
            toast(json.message || 'تعذّر جلب البيانات', 'danger');
            return;
        }

        State.isEditMode = true;
        El.addEditTitle.textContent = 'تعديل رصيد افتتاحي';
        El.editId.value = json.id;

        El.btnAddLine.style.display = 'none';
        El.linesBody.innerHTML = '';
        State.linesCounter = 0;

        json.lines.forEach(addLine);

        setTimeout(() => {
            State.originalSnapshot = captureFormSnapshot();
        }, 50);

        bootstrap.Modal.getOrCreateInstance(El.addEditModal, { focus: false }).show();

    } catch (error) {
        console.error(error);
        toast('حدث خطأ أثناء جلب البيانات', 'danger');
    }
}

// ─────────────────────────────────────────────────────────────
//  Picker
// ─────────────────────────────────────────────────────────────
async function openPicker(displayInput) {
    const row  = displayInput.closest('tr');
    const type = row.querySelector('.line-type')?.value || 'CASH';

    State.activePickerType   = type;
    State.activeDisplayInput = displayInput;

    configurePicker(type);
    await loadPickerAccounts('');

    const modal = bootstrap.Modal.getOrCreateInstance(El.pickerModal, { focus: false });
    modal.show();

    El.pickerModal.addEventListener('shown.bs.modal', function handler() {
        El.pickerSearch.focus();
        El.pickerModal.removeEventListener('shown.bs.modal', handler);
    }, { once: true });
}

function configurePicker(type) {
    const titles = {
        CASH:     'اختيار صندوق',
        BANK:     'اختيار بنك',
        CUSTOMER: 'اختيار عميل',
        SUPPLIER: 'اختيار مورد',
    };
    const headers = {
        CASH:     'رمز الصندوق',
        BANK:     'رمز البنك',
        CUSTOMER: 'رمز العميل',
        SUPPLIER: 'رمز المورد',
    };

    El.pickerTitle.textContent      = titles[type] || 'اختيار';
    El.pickerCodeHeader.textContent = headers[type] || 'الرمز';

    const showCurrency = type === 'CASH' || type === 'BANK';
    El.pickerModal.querySelectorAll('.picker-currency-col').forEach((col) => {
        col.style.display = showCurrency ? '' : 'none';
    });
}

async function loadPickerAccounts(search = '') {
    El.pickerBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">جارٍ التحميل...</td></tr>';
    El.pickerEmpty.style.display = 'none';

    try {
        const response = await fetch(
            `${API.picker}?type=${State.activePickerType}&search=${encodeURIComponent(search)}`,
            { headers: { 'Accept': 'application/json' } }
        );
        const json = await response.json();

        if (!json.success) {
            El.pickerBody.innerHTML = '';
            El.pickerEmpty.style.display = '';
            return;
        }

        State.pickerAccounts = json.data || [];
        renderPickerList();

    } catch (error) {
        console.error(error);
        El.pickerBody.innerHTML = '';
        El.pickerEmpty.style.display = '';
    }
}

function renderPickerList() {
    const showCurrency = State.activePickerType === 'CASH' || State.activePickerType === 'BANK';

    El.pickerBody.innerHTML = '';

    if (!State.pickerAccounts.length) {
        El.pickerEmpty.style.display = '';
        return;
    }

    El.pickerEmpty.style.display = 'none';

    const fragment = document.createDocumentFragment();

    State.pickerAccounts.forEach((acc) => {
        const clone = El.pickerTemplate.content.cloneNode(true);
        const tr = clone.querySelector('tr');

        setText(tr, '.picker-code',    acc.code);
        setText(tr, '.picker-name',    acc.name);
        setText(tr, '.picker-account', acc.account_code);

        const currencyCell = tr.querySelector('.picker-currency');
        if (showCurrency) {
            currencyCell.textContent = acc.currency_code || '—';
        } else {
            currencyCell.style.display = 'none';
        }

        tr.addEventListener('click', () => selectPickerRow(acc));

        fragment.appendChild(clone);
    });

    El.pickerBody.appendChild(fragment);
}

function selectPickerRow(acc) {
    if (!State.activeDisplayInput) return;

    // ⬇️ التحقق من التكرار
    if (isAccountDuplicate(acc.account_id, State.activeDisplayInput)) {
        toast('هذا الحساب مضاف بالفعل في سطر آخر', 'warning');
        return;
    }

    const row = State.activeDisplayInput.closest('tr');

    row.querySelector('.line-account-id').value   = acc.account_id;
    row.querySelector('.line-account-code').value = acc.account_code;
    row.querySelector('.line-entity-id').value    = acc.id;

    State.activeDisplayInput.value = `${acc.code || ''} - ${acc.name || ''}`.trim();

    if (State.activePickerType === 'CASH' || State.activePickerType === 'BANK') {
        const currencySelect = row.querySelector('.line-currency');
        const rateInput      = row.querySelector('.line-rate');

        if (currencySelect && acc.currency_id) {
            currencySelect.value = acc.currency_id;
        }
        if (rateInput && acc.exchange_rate) {
            rateInput.value = Number(acc.exchange_rate).toFixed(2);
        }
    }

    bootstrap.Modal.getInstance(El.pickerModal)?.hide();
}

function isAccountDuplicate(accountId, currentInput) {
    const currentRow = currentInput.closest('tr');

    let duplicate = false;

    El.linesBody.querySelectorAll('tr').forEach((tr) => {
        if (tr === currentRow) return;

        const existing = tr.querySelector('.line-account-id')?.value;

        if (existing && String(existing) === String(accountId)) {
            duplicate = true;
        }
    });

    return duplicate;
}

// ─────────────────────────────────────────────────────────────
//  التحقق من النموذج
// ─────────────────────────────────────────────────────────────
function validateForm() {
    const rows = El.linesBody.querySelectorAll('tr');

    if (!rows.length) {
        toast('يجب إضافة سطر واحد على الأقل', 'danger');
        return false;
    }

    // التحقق من التكرار
    const accountIds = [];

    for (let i = 0; i < rows.length; i++) {
        const accountId = rows[i].querySelector('.line-account-id')?.value;

        if (accountId) {
            if (accountIds.includes(String(accountId))) {
                toast(`السطر ${i + 1}: هذا الحساب مضاف بالفعل في سطر آخر.`, 'danger');
                return false;
            }
            accountIds.push(String(accountId));
        }
    }

    // التحقق من كل سطر
    for (let i = 0; i < rows.length; i++) {
        const error = validateRow(rows[i], i);
        if (error) {
            toast(error, 'danger');
            return false;
        }
    }

    if (State.isEditMode && State.originalSnapshot) {
        if (!hasChanged()) {
            toast('لم يتم إجراء أي تعديل على البيانات.', 'danger');
            return false;
        }
    }

    return true;
}

function validateRow(row, index) {
    const n         = index + 1;
    const type      = row.querySelector('.line-type')?.value;
    const accountId = row.querySelector('.line-account-id')?.value;
    const rate      = parseFloat(row.querySelector('.line-rate')?.value) || 0;
    const debit     = parseFloat(row.querySelector('.line-debit')?.value) || 0;
    const credit    = parseFloat(row.querySelector('.line-credit')?.value) || 0;

    if (!type)                     return `السطر ${n}: يجب اختيار النوع`;
    if (!accountId)                return `السطر ${n}: يجب اختيار الحساب`;
    if (rate <= 0)                 return `السطر ${n}: سعر الصرف يجب أن يكون أكبر من صفر`;
    if (debit > 0 && credit > 0)   return `السطر ${n}: لا يمكن إدخال مدين ودائن معًا`;
    if (debit <= 0 && credit <= 0) return `السطر ${n}: يجب أن يكون المبلغ أكبر من صفر`;

    return null;
}

function hasChanged() {
    const original = JSON.parse(State.originalSnapshot);
    const current  = JSON.parse(captureFormSnapshot());

    if (current.lines.length !== original.lines.length) return false;

    return captureFormSnapshot() !== State.originalSnapshot;
}

function captureFormSnapshot() {
    const lines = [];

    El.linesBody.querySelectorAll('tr').forEach((row) => {
        lines.push({
            type:          row.querySelector('.line-type')?.value || '',
            account_id:    row.querySelector('.line-account-id')?.value || '',
            entity_id:     row.querySelector('.line-entity-id')?.value || '',
            currency_id:   row.querySelector('.line-currency')?.value || '',
            exchange_rate: row.querySelector('.line-rate')?.value || '',
            debit:         row.querySelector('.line-debit')?.value || '',
            credit:        row.querySelector('.line-credit')?.value || '',
            notes:         row.querySelector('.line-notes')?.value || '',
        });
    });

    return JSON.stringify({ lines });
}

// ─────────────────────────────────────────────────────────────
//  الحفظ
// ─────────────────────────────────────────────────────────────
async function saveForm() {
    if (!validateForm()) return;

    const isEdit = State.isEditMode && El.editId.value;
    const url    = isEdit ? API.update(El.editId.value) : API.store;

    const formData = new FormData(El.obForm);
    if (isEdit) formData.append('_method', 'PUT');

    try {
        const response = await fetch(url, {
            method: 'POST',
            body:   formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
        });

        const json = await response.json();

        if (!json.success) {
            toast(json.message || 'حدث خطأ', 'danger');
            return;
        }

        document.activeElement?.blur();
        bootstrap.Modal.getInstance(El.addEditModal)?.hide();

        await loadTable(El.searchInput.value);
        toast(json.message || 'تم الحفظ بنجاح', 'success');

    } catch (error) {
        console.error(error);
        toast('حدث خطأ أثناء الحفظ', 'danger');
    }
}

// ─────────────────────────────────────────────────────────────
//  الحذف
// ─────────────────────────────────────────────────────────────
function openDeleteModal(id, name = '') {
    if (!id) return;

    El.deleteId.value = id;
    El.deleteTarget.textContent = name || '';
    El.deleteModal?.classList.add('show');
}

function closeDeleteModal() {
    El.deleteModal?.classList.remove('show');
    El.deleteId.value = '';
    El.deleteTarget.textContent = '';
}

async function confirmDelete() {
    const id = El.deleteId.value;
    if (!id) return;

    El.btnDeleteOk.disabled = true;

    try {
        const response = await fetch(API.destroy(id), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
        });

        const json = await response.json();

        if (!json.success) {
            toast(json.message || 'حدث خطأ أثناء الحذف', 'danger');
            return;
        }

        closeDeleteModal();
        await loadTable(El.searchInput.value);
        toast(json.message || 'تم حذف الرصيد بنجاح', 'success');

    } catch (error) {
        console.error(error);
        toast('حدث خطأ أثناء الحذف', 'danger');

    } finally {
        El.btnDeleteOk.disabled = false;
    }
}

// ═════════════════════════════════════════════════════════════
//  الطباعة (محسّنة)
// ═════════════════════════════════════════════════════════════
function printCurrentTab() {
    const rows = State.cachedRows.length ? State.cachedRows : [];

    if (!rows.length) {
        toast('لا توجد بيانات للطباعة', 'warning');
        return;
    }

    const printData = {
        title:       getTabTitle(El.currentType.value),
        typeCode:    El.currentType.value,
        rows:        rows.map((r, i) => normalizePrintRow(r, i + 1)),
        totals:      State.cachedTotals || {},
        systemCode:  State.systemCurrencyCode || '',
        printedAt:   formatPrintDate(new Date()),
        printedBy:   getCurrentUserName(),
        companyName: getCompanyName(),
    };

    openPrintWindow(buildPrintHTML(printData));
}

function getTabTitle(type) {
    return {
        CASH:     'الصناديق',
        BANK:     'البنوك',
        CUSTOMER: 'العملاء',
        SUPPLIER: 'الموردين',
    }[type] || 'الأرصدة';
}

function normalizePrintRow(row, index) {
    const isForeign = row.currency_code && row.currency_code !== State.systemCurrencyCode;

    return {
        index,
        accountCode: row.entity_code || row.account_code || '—',
        accountName: row.entity_name || row.account_name || '—',
        currency:    row.currency_code || '—',
        rate:        isForeign ? formatMoney(row.exchange_rate) : '—',
        debit:       formatMoney(row.local_debit),
        credit:      formatMoney(row.local_credit),
        net:         formatMoney(row.local_net),
        notes:       row.notes || '',
    };
}

function formatPrintDate(date) {
    const pad = (n) => String(n).padStart(2, '0');

    return `${date.getFullYear()}/${pad(date.getMonth() + 1)}/${pad(date.getDate())} ` +
           `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function openPrintWindow(html) {
    const win = window.open('', '_blank', 'width=1000,height=800');

    if (!win) {
        toast('تم منع النافذة المنبثقة. الرجاء السماح بها.', 'danger');
        return;
    }

    win.document.open();
    win.document.write(html);
    win.document.close();
}

function buildPrintHTML(data) {
    const rowsPerPage = 20;
    const pages       = chunkArray(data.rows, rowsPerPage);

    const pagesHTML = pages.map((pageRows, pageIndex) => {
        const isLastPage = pageIndex === pages.length - 1;
        const startIndex = pageIndex * rowsPerPage;
        const isFirstPage = pageIndex === 0;

        return buildPrintPage({
            data,
            pageRows,
            pageIndex,
            startIndex,
            isFirstPage,
            isLastPage,
            totalPages: pages.length,
        });
    }).join('');

    return wrapPrintDocument({
        title:     data.title,
        pagesHTML,
    });
}

function buildPrintPage(ctx) {
    const { data, pageRows, pageIndex, startIndex, isFirstPage, isLastPage, totalPages } = ctx;

    const rowsHTML = pageRows.map((r, i) => `
        <tr>
            <td class="col-num">${startIndex + i + 1}</td>
            <td class="col-code">${escapeHtml(r.accountCode)}</td>
            <td class="col-name">${escapeHtml(r.accountName)}</td>
            <td class="col-currency">${escapeHtml(r.currency)}</td>
            <td class="col-rate">${r.rate}</td>
            <td class="col-amount">${r.debit}</td>
            <td class="col-amount">${r.credit}</td>
            <td class="col-amount net">${r.net}</td>
            <td class="col-notes">${escapeHtml(r.notes)}</td>
        </tr>
    `).join('');

    return `
        <div class="page">
            <header class="page-header">
                <div class="header-title">
                    <h1>${escapeHtml(data.companyName)}</h1>
                    <h2>الأرصدة الافتتاحية - ${escapeHtml(data.title)}</h2>
                </div>
                <div class="header-meta">
                    <div><strong>التاريخ:</strong> ${data.printedAt}</div>
                    <div><strong>الصفحة:</strong> ${pageIndex + 1} / ${totalPages}</div>
                    <div><strong>المستخدم:</strong> ${escapeHtml(data.printedBy)}</div>
                </div>
            </header>

            ${isFirstPage ? buildPrintSummary(data) : ''}

            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-code">رقم الحساب</th>
                        <th class="col-name">اسم الحساب</th>
                        <th class="col-currency">العملة</th>
                        <th class="col-rate">السعر</th>
                        <th class="col-amount">مدين</th>
                        <th class="col-amount">دائن</th>
                        <th class="col-amount">الرصيد</th>
                        <th class="col-notes">ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHTML}
                </tbody>
                ${isLastPage ? buildPrintTotals(data) : ''}
            </table>

            ${isLastPage ? buildPrintFooter(data) : ''}
        </div>
    `;
}

function buildPrintSummary(data) {
    const t = data.totals;

    return `
        <div class="summary-bar">
            <div class="summary-item">
                <span class="label">عدد السجلات</span>
                <span class="value">${data.rows.length}</span>
            </div>
            <div class="summary-item">
                <span class="label">إجمالي المدين</span>
                <span class="value debit">${formatMoney(t.local_debit)}</span>
            </div>
            <div class="summary-item">
                <span class="label">إجمالي الدائن</span>
                <span class="value credit">${formatMoney(t.local_credit)}</span>
            </div>
            <div class="summary-item">
                <span class="label">الصافي</span>
                <span class="value net">${formatMoney(t.local_net)}</span>
            </div>
        </div>
    `;
}

function buildPrintTotals(data) {
    const t = data.totals;

    return `
        <tfoot>
            <tr>
                <td colspan="5" class="total-label">الإجماليات:</td>
                <td class="col-amount">${formatMoney(t.local_debit)}</td>
                <td class="col-amount">${formatMoney(t.local_credit)}</td>
                <td class="col-amount net">${formatMoney(t.local_net)}</td>
                <td></td>
            </tr>
        </tfoot>
    `;
}

function buildPrintFooter(data) {
    return `
        <footer class="page-footer">
            <div class="signatures">
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-label">المحاسب</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-label">المراجع</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-label">المدير المالي</div>
                </div>
            </div>
            <div class="footer-info">
                <span>${escapeHtml(data.companyName)}</span>
                <span>طُبع في: ${data.printedAt}</span>
            </div>
        </footer>
    `;
}

function wrapPrintDocument({ title, pagesHTML }) {
    return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>${escapeHtml(title)}</title>
    <style>${getPrintStyles()}</style>
</head>
<body>
    ${pagesHTML}

    <script>
        window.onload = function () {
            setTimeout(function () { window.print(); }, 400);
        };
    <\/script>
</body>
</html>`;
}

function getPrintStyles() {
    return `
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            direction: rtl;
            margin: 0;
            padding: 0;
            color: #212529;
            font-size: 11px;
        }

        .page {
            padding: 10px;
            page-break-after: always;
            position: relative;
            min-height: 275mm;
        }

        .page:last-child { page-break-after: auto; }

        /* ─── الترويسة ─── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .header-title h1 {
            font-size: 14px;
            margin: 0 0 2px 0;
            color: #0d6efd;
        }

        .header-title h2 {
            font-size: 12px;
            margin: 0;
            color: #495057;
            font-weight: 500;
        }

        .header-meta {
            font-size: 10px;
            color: #6c757d;
            text-align: left;
            line-height: 1.5;
        }

        /* ─── شريط الملخص ─── */
        .summary-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            padding: 8px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }

        .summary-item {
            flex: 1;
            text-align: center;
            padding: 4px;
            border-left: 1px solid #dee2e6;
        }

        .summary-item:first-child { border-left: none; }

        .summary-item .label {
            display: block;
            font-size: 9px;
            color: #6c757d;
            margin-bottom: 3px;
        }

        .summary-item .value {
            font-size: 12px;
            font-weight: 700;
            direction: ltr;
            display: inline-block;
        }

        .summary-item .value.debit  { color: #198754; }
        .summary-item .value.credit { color: #dc3545; }
        .summary-item .value.net    { color: #0d6efd; }

        /* ─── الجدول ─── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            table-layout: fixed;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #dee2e6;
            padding: 4px 5px;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .data-table th {
            background: #e7f1ff;
            color: #0d6efd;
            font-weight: 700;
            font-size: 10px;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .data-table tfoot td {
            background: #e7f1ff;
            font-weight: 700;
            color: #0d6efd;
        }

        /* ─── أعمدة ─── */
        .col-num      { width: 4%; }
        .col-code     { width: 11%; }
        .col-name     { width: 23%; text-align: right; }
        .col-currency { width: 6%; }
        .col-rate     { width: 7%; }
        .col-amount   { width: 12%; text-align: right; direction: ltr; font-family: 'Consolas', monospace; font-weight: 600; }
        .col-notes    { width: 13%; text-align: right; font-size: 9px; }

        .col-amount.net { color: #0d6efd; }

        .total-label {
            text-align: right;
            background: #e7f1ff;
            color: #0d6efd;
            font-weight: 700;
        }

        /* ─── التذييل ─── */
        .page-footer {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px dashed #adb5bd;
        }

        .signatures {
            display: flex;
            justify-content: space-around;
            margin-bottom: 12px;
        }

        .sig-box {
            text-align: center;
            width: 28%;
        }

        .sig-line {
            border-bottom: 1px solid #495057;
            height: 35px;
            margin-bottom: 4px;
        }

        .sig-label {
            font-size: 10px;
            color: #495057;
            font-weight: 600;
        }

        .footer-info {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #6c757d;
            padding-top: 6px;
            border-top: 1px solid #e9ecef;
        }

        @media print {
            body { padding: 0; }
            .page { padding: 0; min-height: auto; }
        }
    `;
}

// ─────────────────────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────────────────────
function chunkArray(arr, size) {
    const chunks = [];

    for (let i = 0; i < arr.length; i += size) {
        chunks.push(arr.slice(i, i + size));
    }

    return chunks;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getCurrentUserName() {
    return document.querySelector('meta[name="user-name"]')?.content
        || document.querySelector('.user-name')?.textContent?.trim()
        || '—';
}

function getCompanyName() {
    return document.querySelector('meta[name="company-name"]')?.content
        || window.OB_COMPANY_NAME
        || 'نظام ERP';
}

// ─────────────────────────────────────────────────────────────
//  تهيئة الصفحة
// ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cacheElements();
    readSystemSettings();
    bindGlobalEvents();

    loadTable();
});

function cacheElements() {
    El.obForm              = document.getElementById('obForm');
    El.addEditModal        = document.getElementById('addEditModal');
    El.addEditTitle        = document.getElementById('addEditTitle');
    El.editId              = document.getElementById('editId');
    El.linesBody           = document.getElementById('linesBody');
    El.lineTemplate        = document.getElementById('lineRowTemplate');
    El.btnAddLine          = document.getElementById('btnAddLine');

    El.tableBody           = document.getElementById('obTableBody');
    El.rowTemplate         = document.getElementById('obRowTemplate');
    El.noDataRow           = document.getElementById('noDataRow');

    El.btnAdd              = document.getElementById('btnAddOpeningBalance');
    El.searchInput         = document.getElementById('searchInput');
    El.btnClearSearch      = document.getElementById('btnClearSearch');
    El.currentType         = document.getElementById('currentType');

    El.deleteModal         = document.getElementById('deleteBoxModal');
    El.deleteId            = document.getElementById('deleteId');
    El.deleteTarget        = document.getElementById('deleteTargetName');
    El.btnDeleteOk         = document.getElementById('deleteBoxConfirmBtn');
    El.btnDeleteCancel     = document.getElementById('deleteBoxCancelBtn');

    El.totalDebit          = document.getElementById('totalDebit');
    El.totalCredit         = document.getElementById('totalCredit');
    El.totalNet            = document.getElementById('totalNet');

    El.pickerModal         = document.getElementById('accountPickerModal');
    El.pickerTitle         = document.getElementById('accountPickerTitle');
    El.pickerCodeHeader    = document.getElementById('accountPickerCodeHeader');
    El.pickerSearch        = document.getElementById('accountPickerSearch');
    El.pickerClear         = document.getElementById('accountPickerClear');
    El.pickerBody          = document.getElementById('accountPickerBody');
    El.pickerEmpty         = document.getElementById('accountPickerEmpty');
    El.pickerTemplate      = document.getElementById('accountPickerRowTemplate');

    El.tabs                = document.querySelectorAll('#obTabs .nav-link');
}

function readSystemSettings() {
    State.systemCurrencyCode = window.OB_SYSTEM_CURRENCY_CODE || '';
}

function bindGlobalEvents() {
    El.btnAdd?.addEventListener('click', openAddModal);
    El.btnAddLine?.addEventListener('click', () => addLine());

    document.getElementById('btnPrintOpeningBalances')
        ?.addEventListener('click', printCurrentTab);

    El.obForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        saveForm();
    });

    El.addEditModal?.addEventListener('hidden.bs.modal', resetModal);

    El.searchInput?.addEventListener('input', debounceSearch);
    El.btnClearSearch?.addEventListener('click', clearSearch);

    El.pickerSearch?.addEventListener('input', debouncePicker);
    El.pickerClear?.addEventListener('click', clearPicker);

    El.tabs.forEach((tab) => {
        tab.addEventListener('shown.bs.tab', function () {
            El.currentType.value = this.dataset.type;
            loadTable(El.searchInput.value);
        });
    });

    El.tableBody?.addEventListener('click', handleTableClick);

    El.btnDeleteCancel?.addEventListener('click', closeDeleteModal);
    El.btnDeleteOk?.addEventListener('click', confirmDelete);

    El.deleteModal?.addEventListener('click', (e) => {
        if (e.target === El.deleteModal) closeDeleteModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && El.deleteModal?.classList.contains('show')) {
            closeDeleteModal();
        }
    });
}

function resetModal() {
    El.obForm.reset();
    El.editId.value = '';
    El.linesBody.innerHTML = '';
    State.linesCounter = 0;
    State.isEditMode = false;
    State.originalSnapshot = null;
    El.btnAddLine.style.display = '';
}

function debounceSearch() {
    clearTimeout(State.searchTimer);
    State.searchTimer = setTimeout(() => loadTable(El.searchInput.value), 500);
}

function debouncePicker() {
    clearTimeout(State.pickerTimer);
    State.pickerTimer = setTimeout(() => loadPickerAccounts(El.pickerSearch.value), 500);
}

function clearSearch() {
    El.searchInput.value = '';
    loadTable('');
    El.searchInput.focus();
}

function clearPicker() {
    El.pickerSearch.value = '';
    loadPickerAccounts('');
    El.pickerSearch.focus();
}

function handleTableClick(e) {
    const editBtn = e.target.closest('.btn-edit-row');
    if (editBtn) {
        openEditModal(editBtn.dataset.id);
        return;
    }

    const deleteBtn = e.target.closest('.btn-delete-row');
    if (deleteBtn) {
        openDeleteModal(deleteBtn.dataset.id, deleteBtn.dataset.name);
    }
}
/* ═══════════════════════════════════════════════════════════
   تصدير للـ HTML (احتياطي)
   ═══════════════════════════════════════════════════════════ */
Object.assign(window, {
    openAddModal,
    openEditModal,
    printCurrentTab,
    loadTable,
    openDeleteModal,
    closeDeleteModal,
    confirmDelete,
    addLine,
    saveForm,
});