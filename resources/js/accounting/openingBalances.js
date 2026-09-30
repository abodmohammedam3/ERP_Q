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
    activePickerType:   'CUSTOMER',
    pickerAccounts:     [],
    searchTimer:        null,
    pickerTimer:        null,
    listAbort:          null,
    systemCurrencyCode: '',
    systemCurrencyId:   null,

    currentPage:        1,
    perPage:            10,
    totalRows:          0,
    lastPage:           1,
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
//  أدوات عامة
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
//  أدوات القوالب
// ─────────────────────────────────────────────────────────────
function cloneTemplate(template) {
    if (!template || !template.content) return null;
    const first = template.content.firstElementChild;
    return first ? first.cloneNode(true) : null;
}

// ─────────────────────────────────────────────────────────────
//  أدوات العملة
// ─────────────────────────────────────────────────────────────

function isCurrencyLockedToAccount(type) {
    return type === 'CASH' || type === 'BANK';
}

function findSystemCurrencyOption(select) {
    if (!select) return null;

    const byData = Array.from(select.options).find(
        (o) => o.dataset.system === '1'
    );

    if (byData) return byData;

    const sysId = State.systemCurrencyId;
    if (sysId) {
        const byId = Array.from(select.options).find(
            (o) => String(o.value) === String(sysId)
        );
        if (byId) return byId;
    }

    return null;
}

function syncRateFromCurrency(currencySelect, rateInput) {
    if (!currencySelect || !rateInput) return;

    const opt = currencySelect.options[currencySelect.selectedIndex];
    const rate = opt?.dataset?.rate || 1;

    rateInput.value = Number(rate).toFixed(2);
}

function setCurrencyLock(currencySelect, locked) {
    if (!currencySelect) return;

    if (locked) {
        currencySelect.style.pointerEvents = 'none';
        currencySelect.tabIndex = -1;
        currencySelect.setAttribute('aria-disabled', 'true');
        currencySelect.classList.add('bg-body-secondary');
    } else {
        currencySelect.style.pointerEvents = '';
        currencySelect.tabIndex = 0;
        currencySelect.removeAttribute('aria-disabled');
        currencySelect.classList.remove('bg-body-secondary');
    }
}

// ─────────────────────────────────────────────────────────────
//  تحميل الجدول
// ─────────────────────────────────────────────────────────────
async function loadTable(search = '', page = 1) {
    abortPendingRequest();

    State.listAbort = new AbortController();

    try {
        const params = new URLSearchParams();
        params.set('type', El.currentType.value);
        params.set('search', search);
        params.set('page', page);
        params.set('per_page', State.perPage);

        const response = await fetch(
            `${API.list}?${params.toString()}`,
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
        State.currentPage  = json.pagination?.current_page || 1;
        State.lastPage     = json.pagination?.last_page || 1;
        State.totalRows    = json.pagination?.total || 0;

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
    const startIndex = (State.currentPage - 1) * State.perPage;

    renderTable(State.cachedRows, startIndex);
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

    const totalPages = State.lastPage;
    const current    = State.currentPage;
    const total      = State.totalRows;
    const perPage    = State.perPage;

    if (totalPages <= 1) {
        container.replaceChildren();
        return;
    }

    const start = (current - 1) * perPage + 1;
    const end   = Math.min(current * perPage, total);

    // ✅ استنساخ الغلاف
    const wrapper = cloneTemplate(El.paginationWrapperTemplate);
    if (!wrapper) return;

    const infoEl = wrapper.querySelector('.pagination-info');
    const listEl = wrapper.querySelector('.pagination-list');

    if (infoEl) {
        infoEl.textContent = `عرض ${start} - ${end} من ${total}`;
    }

    // ─── زر السابق ───
    const prevLi = cloneTemplate(El.paginationPrevTemplate);

    if (prevLi) {
        const prevBtn = prevLi.querySelector('.pagination-prev');
        prevBtn.dataset.page = current - 1;

        if (current === 1) {
            prevLi.classList.add('disabled');
        } else {
            prevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                loadTable(El.searchInput?.value || '', current - 1);
                document.getElementById('obTable')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        }

        listEl.appendChild(prevLi);
    }

    // ─── أرقام الصفحات ───
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= current - 2 && i <= current + 2)) {

            const pageLi = cloneTemplate(El.paginationPageTemplate);

            if (pageLi) {
                const pageBtn = pageLi.querySelector('.pagination-page');
                pageBtn.textContent = i;
                pageBtn.dataset.page = i;

                if (i === current) {
                    pageLi.classList.add('active');
                } else {
                    pageBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        loadTable(El.searchInput?.value || '', i);
                        document.getElementById('obTable')?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start',
                        });
                    });
                }

                listEl.appendChild(pageLi);
            }

        } else if (i === current - 3 || i === current + 3) {

            const ellipsis = cloneTemplate(El.paginationEllipsisTemplate);
            if (ellipsis) listEl.appendChild(ellipsis);
        }
    }

    // ─── زر التالي ───
    const nextLi = cloneTemplate(El.paginationNextTemplate);

    if (nextLi) {
        const nextBtn = nextLi.querySelector('.pagination-next');
        nextBtn.dataset.page = current + 1;

        if (current === totalPages) {
            nextLi.classList.add('disabled');
        } else {
            nextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                loadTable(El.searchInput?.value || '', current + 1);
                document.getElementById('obTable')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        }

        listEl.appendChild(nextLi);
    }

    // ─── استبدال المحتوى ───
    container.replaceChildren(wrapper);
}

// ─────────────────────────────────────────────────────────────
//  بناء صف الجدول
// ─────────────────────────────────────────────────────────────
function buildTableRow(row, index) {
    const tr = cloneTemplate(El.rowTemplate);
    if (!tr) return null;

    setText(tr, '.row-index',        index + 1);
    setText(tr, '.row-account-code', row.entity_code || row.account_code);
    setText(tr, '.row-account-name', row.entity_name || row.account_name);
    setText(tr, '.row-currency',     row.currency_code || '—');
    setText(tr, '.row-rate',         row.currency_code ? formatMoney(row.exchange_rate) : '—');

    renderAmountCell(tr.querySelector('.row-debit'),  row.debit,  row.local_debit,  row.currency_code);
    renderAmountCell(tr.querySelector('.row-credit'), row.credit, row.local_credit, row.currency_code);
    renderAmountCell(tr.querySelector('.row-net'),    row.net,    row.local_net,    row.currency_code, row.balance_label);

    const editBtn = tr.querySelector('.btn-edit-row');
    if (editBtn) editBtn.dataset.id = row.id;

    const deleteBtn = tr.querySelector('.btn-delete-row');
    if (deleteBtn) {
        deleteBtn.dataset.id   = row.id;
        deleteBtn.dataset.name = row.entity_name || row.account_name || '';
    }

    return tr;
}

function renderAmountCell(cell, foreign, local, currencyCode, label = null) {
    if (!cell) return;

    const foreignDiv = cell.querySelector('.amount-foreign');
    const localDiv   = cell.querySelector('.amount-local');
    const labelDiv   = cell.querySelector('.amount-label-small');

    if (!foreignDiv || !localDiv) return;

    const isSystemCurrency = !currencyCode || currencyCode === State.systemCurrencyCode;

    if (isSystemCurrency) {
        foreignDiv.style.display = 'none';
        foreignDiv.textContent   = '';
        localDiv.textContent     = formatMoney(local);
        localDiv.className       = 'amount-single';
    } else {
        foreignDiv.style.display = '';
        foreignDiv.textContent   = `${formatMoney(foreign)} ${currencyCode}`;
        localDiv.textContent     = `${formatMoney(local)} ${State.systemCurrencyCode}`;
        localDiv.className       = 'amount-local';
    }

    if (labelDiv) {
        labelDiv.textContent = label || '';
    }
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
    const tr = cloneTemplate(El.lineTemplate);
    if (!tr) return;

    if (State.isEditMode) {
        const btn = tr.querySelector('.btn-remove-line');
        if (btn) btn.style.display = 'none';
    }

    tr.querySelector('.line-number').textContent = n;

    bindLineFields(tr, n);
    fillLineValues(tr, data);
    bindLineEvents(tr);

    El.linesBody.appendChild(tr);
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
}

function fillLineValues(tr, data) {
    const currencySelect = tr.querySelector('.line-currency');
    const rateInput      = tr.querySelector('.line-rate');

    if (data.type) {
        tr.querySelector('.line-type').value = data.type;
    }

    const type = data.type || '';

    if (data.account_id) {
        tr.querySelector('.line-account-id').value   = data.account_id;
        tr.querySelector('.line-account-code').value = data.account_code || '';
        tr.querySelector('.line-entity-id').value    = data.entity_id || '';

        const display = tr.querySelector('.line-account-display');
        display.value = data.entity_code || data.entity_name
            ? `${data.entity_code || ''} - ${data.entity_name || ''}`.trim()
            : `${data.account_code || ''} - ${data.account_name || ''}`.trim();
    }

    if (isCurrencyLockedToAccount(type)) {
        setCurrencyLock(currencySelect, true);

        if (data.currency_id) {
            currencySelect.value = data.currency_id;
        }
    } else {
        setCurrencyLock(currencySelect, false);

        if (data.currency_id) {
            currencySelect.value = data.currency_id;
        } else {
            const sysOpt = findSystemCurrencyOption(currencySelect);
            if (sysOpt) currencySelect.value = sysOpt.value;
        }
    }

    if (data.exchange_rate) {
        rateInput.value = Number(data.exchange_rate).toFixed(2);
    } else {
        syncRateFromCurrency(currencySelect, rateInput);
    }

    if (data.debit)  tr.querySelector('.line-debit').value  = data.debit;
    if (data.credit) tr.querySelector('.line-credit').value = data.credit;
}

function bindLineEvents(tr) {
    tr.querySelector('.line-account-display')
        .addEventListener('click', (e) => openPicker(e.target));

    tr.querySelector('.line-currency')
        .addEventListener('change', (e) => {
            const select = e.target;
            if (select.getAttribute('aria-disabled') === 'true') return;
            syncRateFromCurrency(select, tr.querySelector('.line-rate'));
        });

    tr.querySelector('.line-debit')
        .addEventListener('input', (e) => zeroOther(e.target, tr.querySelector('.line-credit')));

    tr.querySelector('.line-credit')
        .addEventListener('input', (e) => zeroOther(e.target, tr.querySelector('.line-debit')));

    tr.querySelector('.btn-remove-line')
        .addEventListener('click', () => {
            tr.remove();
            renumberLines();
        });
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
//  فتح / إغلاق مودال الإضافة والتعديل
// ─────────────────────────────────────────────────────────────
function openAddModal() {
    State.isEditMode = false;
    State.originalSnapshot = null;

    El.addEditTitle.textContent = 'إضافة رصيد افتتاحي';
    El.editId.value = '';
    El.obForm.reset();
    El.linesBody.replaceChildren();
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
        El.linesBody.replaceChildren();
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
//  Picker (مع تبويبات)
// ─────────────────────────────────────────────────────────────
async function openPicker(displayInput) {
    const row = displayInput.closest('tr');
    const rowType = row.querySelector('.line-type')?.value || '';
    const initialType = rowType || 'CUSTOMER';

    State.activeDisplayInput = displayInput;

    const modal = bootstrap.Modal.getOrCreateInstance(El.pickerModal, { focus: false });
    modal.show();

    await activatePickerTab(initialType);

    setTimeout(() => El.pickerSearch.focus(), 250);
}

async function activatePickerTab(type) {
    State.activePickerType = type;

    El.pickerTabs.forEach((tab) => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });

    const titles = {
        CASH:      'اختيار صندوق',
        BANK:      'اختيار بنك',
        CUSTOMER:  'اختيار عميل',
        SUPPLIER:  'اختيار مورد',
        INVENTORY: 'اختيار مخزن',
    };
    El.pickerTitle.textContent = titles[type] || 'اختيار حساب';

    updatePickerColumns(type);

    await loadPickerAccounts(El.pickerSearch.value);
}

function updatePickerColumns(type) {
    const showPhone    = type === 'CUSTOMER' || type === 'SUPPLIER';
    const showCurrency = type === 'CASH' || type === 'BANK';

    El.pickerModal.querySelectorAll('.picker-phone-col').forEach((el) => {
        el.style.display = showPhone ? '' : 'none';
    });

    El.pickerModal.querySelectorAll('.picker-currency-col').forEach((el) => {
        el.style.display = showCurrency ? '' : 'none';
    });
}

async function loadPickerAccounts(search = '') {
    showPickerLoading();

    El.pickerEmpty.style.display = 'none';

    try {
        const response = await fetch(
            `${API.picker}?type=${State.activePickerType}&search=${encodeURIComponent(search)}`,
            { headers: { 'Accept': 'application/json' } }
        );
        const json = await response.json();

        if (!json.success) {
            El.pickerBody.replaceChildren();
            El.pickerEmpty.style.display = '';
            return;
        }
            State.pickerAccounts = json.data || [];
            renderPickerList();

            //  أعد تطبيق إخفاء/إظهار الأعمدة على الصفوف الجديدة
            updatePickerColumns(State.activePickerType);

        } catch (error) {
        console.error(error);
        El.pickerBody.replaceChildren();
        El.pickerEmpty.style.display = '';
    }
}

function showPickerLoading() {
    const row = cloneTemplate(El.pickerLoadingTemplate);
    if (!row) return;
    El.pickerBody.replaceChildren(row);
}

function renderPickerList() {
    El.pickerBody.replaceChildren();

    if (!State.pickerAccounts.length) {
        El.pickerEmpty.style.display = '';
        return;
    }

    El.pickerEmpty.style.display = 'none';

    const fragment = document.createDocumentFragment();

    State.pickerAccounts.forEach((acc) => {
        const tr = cloneTemplate(El.pickerTemplate);
        if (!tr) return;

        setText(tr, '.picker-code',     acc.account_code || acc.code || '—');
        setText(tr, '.picker-name',     acc.name || '—');
        setText(tr, '.picker-phone',    acc.phone || '—');
        setText(tr, '.picker-currency', acc.currency_code || '—');

        tr.addEventListener('click', () => selectPickerRow(acc));

        fragment.appendChild(tr);
    });

    El.pickerBody.appendChild(fragment);
}

function selectPickerRow(acc) {
    if (!State.activeDisplayInput) return;

    if (isAccountDuplicate(acc.account_id, State.activeDisplayInput)) {
        toast('هذا الحساب مضاف بالفعل في سطر آخر', 'warning');
        return;
    }

    const row  = State.activeDisplayInput.closest('tr');
    const type = State.activePickerType;

    row.querySelector('.line-type').value = type;

    row.querySelector('.line-account-id').value   = acc.account_id;
    row.querySelector('.line-account-code').value = acc.account_code;
    row.querySelector('.line-entity-id').value    = acc.id;

    State.activeDisplayInput.value = `${acc.account_code || ''} - ${acc.name || ''}`.trim();

    const currencySelect = row.querySelector('.line-currency');
    const rateInput      = row.querySelector('.line-rate');

    if (isCurrencyLockedToAccount(type)) {
        setCurrencyLock(currencySelect, true);

        if (acc.currency_id && currencySelect) {
            currencySelect.value = acc.currency_id;
        }

        if (acc.exchange_rate && rateInput) {
            rateInput.value = Number(acc.exchange_rate).toFixed(2);
        } else {
            syncRateFromCurrency(currencySelect, rateInput);
        }
    } else {
        setCurrencyLock(currencySelect, false);

        const sysOpt = findSystemCurrencyOption(currencySelect);
        if (sysOpt) currencySelect.value = sysOpt.value;

        syncRateFromCurrency(currencySelect, rateInput);
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
    const accountId = row.querySelector('.line-account-id')?.value;
    const rate      = parseFloat(row.querySelector('.line-rate')?.value) || 0;
    const debit     = parseFloat(row.querySelector('.line-debit')?.value) || 0;
    const credit    = parseFloat(row.querySelector('.line-credit')?.value) || 0;

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

        const targetPage = isEdit ? State.currentPage : 1;

        await loadTable(El.searchInput?.value || '', targetPage);
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

        const targetPage = State.cachedRows.length === 1 && State.currentPage > 1
            ? State.currentPage - 1
            : State.currentPage;

        await loadTable(El.searchInput?.value || '', targetPage);
        toast(json.message || 'تم حذف الرصيد بنجاح', 'success');

    } catch (error) {
        console.error(error);
        toast('حدث خطأ أثناء الحذف', 'danger');

    } finally {
        El.btnDeleteOk.disabled = false;
    }
}

// ═════════════════════════════════════════════════════════════
//  الطباعة
// ═════════════════════════════════════════════════════════════
function printCurrentTab() {
    if (!State.totalRows) {
        toast('لا توجد بيانات للطباعة', 'warning');
        return;
    }

    const params = new URLSearchParams();
    params.set('type', El.currentType.value);

    const search = (El.searchInput?.value || '').trim();
    if (search !== '') {
        params.set('search', search);
    }

    window.open(
        `/setting/accounting/openingBalances/print?${params.toString()}`,
        '_blank',
        'width=1000,height=800'
    );
}

// ─────────────────────────────────────────────────────────────
//  تهيئة الصفحة
// ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cacheElements();
    readSystemSettings();
    bindGlobalEvents();

    loadTable('', 1);
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
    El.pickerSearch        = document.getElementById('accountPickerSearch');
    El.pickerClear         = document.getElementById('accountPickerClear');
    El.pickerBody          = document.getElementById('accountPickerBody');
    El.pickerEmpty         = document.getElementById('accountPickerEmpty');
    El.pickerTemplate      = document.getElementById('accountPickerRowTemplate');
    El.pickerLoadingTemplate = document.getElementById('accountPickerLoadingTemplate');

    El.pickerTabs          = El.pickerModal
        ? El.pickerModal.querySelectorAll('#pickerTabs .nav-link')
        : [];

    El.tabs                = document.querySelectorAll('#obTabs .nav-link');

    // قوالب الترقيم
    El.paginationWrapperTemplate  = document.getElementById('paginationWrapperTemplate');
    El.paginationPrevTemplate     = document.getElementById('paginationPrevTemplate');
    El.paginationNextTemplate     = document.getElementById('paginationNextTemplate');
    El.paginationPageTemplate     = document.getElementById('paginationPageTemplate');
    El.paginationEllipsisTemplate = document.getElementById('paginationEllipsisTemplate');
}

function readSystemSettings() {
    State.systemCurrencyCode = window.OB_SYSTEM_CURRENCY_CODE || '';
    State.systemCurrencyId   = window.OB_SYSTEM_CURRENCY_ID   || null;
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

    El.pickerTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activatePickerTab(tab.dataset.type);
        });
    });

    El.tabs.forEach((tab) => {
        tab.addEventListener('shown.bs.tab', function () {
            El.currentType.value = this.dataset.type;
            loadTable(El.searchInput?.value || '', 1);
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
    El.linesBody.replaceChildren();
    State.linesCounter = 0;
    State.isEditMode = false;
    State.originalSnapshot = null;
    El.btnAddLine.style.display = '';
}

function debounceSearch() {
    clearTimeout(State.searchTimer);
    State.searchTimer = setTimeout(
        () => loadTable(El.searchInput?.value || '', 1),
        500
    );
}

function debouncePicker() {
    clearTimeout(State.pickerTimer);
    State.pickerTimer = setTimeout(() => loadPickerAccounts(El.pickerSearch.value), 500);
}

function clearSearch() {
    El.searchInput.value = '';
    loadTable('', 1);
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