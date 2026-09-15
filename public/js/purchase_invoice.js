/* =========================================================
   فاتورة الشراء — بدون HTML + Cache + Focus Flow + Toast
   ========================================================= */

/* =========================================================
   الخرائط الثابتة
   ========================================================= */

const PAYMENT_METHOD_TO_INT = { credit: 1, cash: 2, bank: 3, network: 4 };
const PAYMENT_METHOD_TO_STR = { 1: 'credit', 2: 'cash', 3: 'bank', 4: 'network' };

/* =========================================================
   الحالة العامة
   ========================================================= */

let invoiceMode = 'view';
let currentInvoiceId = null;

let supplierModal, currencyModal, invoiceSearchModal,
    warehouseModal, accountModal, purchaseItemModal, typeModal;

let activePurchaseRow = null;
let currentTypeInput = null;
let accountSearchType = '';
let isSavingInvoice = false;
let editSnapshot = null;

const cache = {
    units: [], coins: [], warehouses: [], items: [],
    types: [], boxes: [], banks: [],
};

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* =========================================================
   Helpers
   ========================================================= */

function apiHeaders(json = false) {
    const h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN };
    if (json) h['Content-Type'] = 'application/json';
    return h;
}

async function apiGet(url) {
    const r = await fetch(url, { headers: apiHeaders() });
    if (!r.ok) throw new Error(`فشل الطلب: ${r.status}`);
    return r.json();
}

async function apiSend(url, method, body) {
    const r = await fetch(url, {
        method, headers: apiHeaders(true), body: JSON.stringify(body),
    });
    const json = await r.json().catch(() => ({}));
    if (!r.ok) {
        const e = new Error(json.message || 'فشل الطلب');
        e.errors = json.errors;
        throw e;
    }
    return json;
}

function debounce(fn, ms = 50) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function cloneTemplate(templateId) {
    const tpl = document.getElementById(templateId);
    if (!tpl) throw new Error(`Template not found: ${templateId}`);
    return tpl.content.firstElementChild.cloneNode(true);
}

function unwrap(data) {
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.data)) return data.data;
    return [];
}

/* =========================================================
   إشعار موحّد — يستخدم showSystemToast إن وُجد
   ========================================================= */

function notify(message, type = 'info') {
    if (typeof window.showSystemToast === 'function') {
        window.showSystemToast(message, type);
    } else {
        console.warn(`[${type}] ${message}`);
        alert(message);
    }
}

/* =========================================================
   Snapshot — لكشف عدم وجود تعديلات
   ========================================================= */

function takeSnapshot() {
    const data = {
        invoice_date: document.getElementById('PurchaseInvoicesDate2')?.value || '',
        account_id: document.getElementById('suplierID')?.value || '',
        coin_id: document.getElementById('coinsID')?.value || '',
        exchange_rate: document.getElementById('PuInExchangeRate2')?.value || '',
        warehouse_id: document.getElementById('warehouseID')?.value || '',
        payment_method: document.getElementById('PuInPaymentMethod2')?.value || '',
        payment_account_id: document.getElementById('paymentAccountId')?.value || '',
        statement: document.getElementById('PuInStatement2')?.value || '',
        reference: document.getElementById('invoiceReference')?.value || '',
        expenses: document.getElementById('PuInExpenses')?.value || '',
        tax_cost: document.getElementById('PuInTaxCost')?.value || '',
        transportation: document.getElementById('PuInTransportation')?.value || '',
        other_cost: document.getElementById('PuInOtherCost')?.value || '',
        other_cost_desc: document.getElementById('otherCostDescription')?.value || '',

        details: Array.from(
            document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row')
        ).map(r => ({
            item: r.querySelector('.row-item')?.dataset.itemId || '',
            type: r.querySelector('.row-type')?.dataset.typeId || '',
            code: r.querySelector('.row-code')?.value || '',
            unit: r.querySelector('.row-unit')?.value || '',
            qty: r.querySelector('.row-weight')?.value || '',
            price: r.querySelector('.row-price')?.value || '',
            discount: r.querySelector('.row-discount')?.value || '',
        })),
    };

    return JSON.stringify(data);
}

/* =========================================================
   تهيئة
   ========================================================= */

document.addEventListener('DOMContentLoaded', async () => {

    const init = (id) => {
        const el = document.getElementById(id);
        if (!el) { console.warn(`⚠️ Modal غير موجود: #${id}`); return null; }
        return new bootstrap.Modal(el);
    };

    supplierModal = init('supplierModal');
    currencyModal = init('currencyModal');
    invoiceSearchModal = init('invoiceSearchModal');
    warehouseModal = init('warehouseModal');
    accountModal = init('accountModal');
    purchaseItemModal = init('purchaseItemModal');
    typeModal = init('typeModal');

    await preloadAll();

    setInvoiceMode('view');
    clearInvoiceForm();
});

async function preloadAll() {
    try {
        const [units, coins, warehouses, items, types, boxes, banks] = await Promise.all([
            apiGet('/setting/inventory/units/list'),
            apiGet('/setting/accounting/coins/list'),
            apiGet('/setting/inventory/warehouses/list'),
            apiGet('/setting/inventory/items/list'),
            apiGet('/setting/inventory/types/list'),
            apiGet('/setting/accounting/boxes/list'),
            apiGet('/setting/accounting/banks/list'),
        ]);

        cache.units = unwrap(units);
        cache.coins = unwrap(coins);
        cache.warehouses = unwrap(warehouses);
        cache.items = unwrap(items);
        cache.types = unwrap(types);
        cache.boxes = unwrap(boxes);
        cache.banks = unwrap(banks);

        console.log('✅ تم تحميل البيانات المرجعية');
    } catch (e) {
        console.error('فشل تحميل البيانات المرجعية', e);
        notify('تعذّر تحميل البيانات المرجعية', 'danger');
    }
}

/* =========================================================
   أوضاع الشاشة
   ========================================================= */

function setInvoiceMode(mode) {
    invoiceMode = mode;

    document.querySelectorAll(
        '#PurchaseInvoicesON2, #PurchaseInvoicesDate2, #PuInPaymentMethod2, ' +
        '#paymentAccount, #supplierName, #currencyName, #PuInExchangeRate2, ' +
        '#warehouseName, #PuInStatement2, #invoiceReference, #PuInExpenses, ' +
        '#PuInTaxCost, #PuInTransportation, #PuInOtherCost, #otherCostDescription'
    ).forEach(el => { el.disabled = (mode === 'view'); });

    document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row')
        .forEach(row => enableRow(row));

    const addBtn = document.getElementById('btnAddInvoiceRow');
    if (addBtn) addBtn.disabled = (mode === 'view');

    const saveBtn = document.getElementById('btnSaveInvoice');
    const saveNewBtn = document.getElementById('btnSaveAndNew');
    const cancelBtn = document.getElementById('btnCancelInvoice');
    const editBtn = document.getElementById('btnEditInvoice');
    const printBtn = document.getElementById('btnPrintInvoice');

    if (mode === 'view') {
        if (saveBtn) saveBtn.disabled = true;
        if (saveNewBtn) saveNewBtn.disabled = true;
        if (cancelBtn) cancelBtn.classList.add('d-none');
        if (editBtn) editBtn.disabled = !hasInvoiceData();
        if (printBtn) printBtn.disabled = !hasInvoiceData();

        const paymentInput = document.getElementById('paymentAccount');
        if (paymentInput) paymentInput.disabled = true;

        const paymentContainer = document.getElementById('paymentAccountContainer');
        if (paymentContainer) paymentContainer.classList.add('d-none');
    } else {
        if (saveBtn) saveBtn.disabled = false;
        if (saveNewBtn) saveNewBtn.disabled = false;
        if (cancelBtn) cancelBtn.classList.remove('d-none');
        if (editBtn) editBtn.disabled = true;
        if (printBtn) printBtn.disabled = true;
        paymentMethodChanged();
    }
}

/* =========================================================
   Reset / Clear
   ========================================================= */

async function resetInvoice() {
    clearInvoiceForm();
    setInvoiceMode('add');

    const numberEl = document.getElementById('PurchaseInvoicesON2');
    if (!numberEl) {
        notify('حقل رقم الفاتورة غير موجود — تحقق من تضمين رأس الفاتورة', 'danger');
        return;
    }

    try {
        const res = await apiGet('/operation/purchases/invoicesPurch/next-number');
        numberEl.value = res.next_number || '';
    } catch (e) {
        notify('تعذّر جلب رقم الفاتورة التالي', 'danger');
    }

    const dateEl = document.getElementById('PurchaseInvoicesDate2');
    if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];

    addInvoiceRow();

    const supplierEl = document.getElementById('supplierName');
    if (supplierEl) supplierEl.focus();
}

function clearInvoiceForm() {
    document.querySelectorAll(
        '#PurchaseInvoicesON2, #PurchaseInvoicesDate2, #PuInPaymentMethod2, ' +
        '#paymentAccount, #supplierName, #currencyName, #PuInExchangeRate2, ' +
        '#warehouseName, #PuInStatement2, #invoiceReference, #PuInExpenses, ' +
        '#PuInTaxCost, #PuInTransportation, #PuInOtherCost, #otherCostDescription'
    ).forEach(el => {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
    });

    ['suplierID', 'coinsID', 'warehouseID', 'paymentAccountId', 'AmountWords']
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

    setEmptyDetailsMessage();

    const discEl = document.getElementById('totalDiscountDisplay');
    if (discEl) discEl.textContent = '0.00';

    const totalEl = document.getElementById('invoiceTotalDisplay');
    if (totalEl) totalEl.textContent = '0.00';

    const amountEl = document.getElementById('AmountWords');
    if (amountEl) amountEl.value = '';

    hidePaymentAccounts();

    const otherCostDesc = document.getElementById('otherCostDescriptionContainer');
    if (otherCostDesc) otherCostDesc.classList.add('d-none');

    setInvoiceMode('view');
    currentInvoiceId = null;
    editSnapshot = null;
}

function setEmptyDetailsMessage() {
    const tbody = document.getElementById('purchaseInvoiceDetails');
    if (!tbody) return;

    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 10;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'لا توجد أصناف مضافة إلى الفاتورة';
    tr.appendChild(td);
    tbody.appendChild(tr);
}

/* =========================================================
   Row Template
   ========================================================= */

function addInvoiceRow() {
    if (invoiceMode === 'view') return;

    const tbody = document.getElementById('purchaseInvoiceDetails');
    if (!tbody) return;

    const emptyTd = tbody.querySelector('td[colspan="10"]');
    if (emptyTd) while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const row = cloneTemplate('invoiceRowTemplate');
    const unitSelect = row.querySelector('.row-unit');
    cache.units.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.UnitID;
        opt.textContent = u.UnitName;
        unitSelect.appendChild(opt);
    });

    tbody.appendChild(row);
    enableRow(row);
    renumberRows();
    calculateTotals();
    return row;
}

function enableRow(row) {
    if (!row) return;
    row.querySelectorAll('input, select').forEach(el => {
        if (!el.classList.contains('row-total')) {
            el.disabled = (invoiceMode === 'view');
        }
    });
    const btn = row.querySelector('button');
    if (btn) btn.disabled = (invoiceMode === 'view');
}

function removeRow(btn) {
    if (invoiceMode === 'view') return;
    const row = btn.closest('tr');
    if (row) row.remove();
    renumberRows();
    calculateTotals();

    const tbody = document.getElementById('purchaseInvoiceDetails');
    if (tbody && !tbody.querySelector('.purchase-detail-row')) setEmptyDetailsMessage();
}

function renumberRows() {
    document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row')
        .forEach((r, i) => {
            const numEl = r.querySelector('.row-num');
            if (numEl) numEl.textContent = i + 1;
        });
}

/* =========================================================
   حساب الإجماليات + المبلغ كتابة
   ========================================================= */

function calculateRow(input) {
    const row = input.closest('tr');
    if (!row) return;
    const qty = parseFloat(row.querySelector('.row-weight')?.value) || 0;
    const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
    const disc = parseFloat(row.querySelector('.row-discount')?.value) || 0;

    const totalEl = row.querySelector('.row-total');
    if (totalEl) totalEl.value = Math.max(0, qty * price - disc).toFixed(2);

    calculateTotals();
}

function calculateTotals() {
    let itemsTotal = 0, discountTotal = 0;
    document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row')
        .forEach(row => {
            const q = parseFloat(row.querySelector('.row-weight')?.value) || 0;
            const p = parseFloat(row.querySelector('.row-price')?.value) || 0;
            const d = parseFloat(row.querySelector('.row-discount')?.value) || 0;
            itemsTotal += q * p;
            discountTotal += d;
        });

    const expenses = parseFloat(document.getElementById('PuInExpenses')?.value) || 0;
    const tax = parseFloat(document.getElementById('PuInTaxCost')?.value) || 0;
    const trans = parseFloat(document.getElementById('PuInTransportation')?.value) || 0;
    const other = parseFloat(document.getElementById('PuInOtherCost')?.value) || 0;

    const net = Math.max(0, itemsTotal - discountTotal);
    const total = net + expenses + tax + trans + other;

    const discEl = document.getElementById('totalDiscountDisplay');
    if (discEl) discEl.textContent = discountTotal.toFixed(2);

    const totalEl = document.getElementById('invoiceTotalDisplay');
    if (totalEl) totalEl.textContent = total.toFixed(2);

    updateAmountWords(total);
}

function updateAmountWords(total) {
    const el = document.getElementById('AmountWords');
    if (!el) return;

    if (!total || total <= 0) { el.value = ''; return; }

    if (window.Utils && typeof window.Utils.numberToWords === 'function') {
        const currencyEl = document.getElementById('currencyName');
        const currencyName = currencyEl ? currencyEl.value : '';
        el.value = window.Utils.numberToWords(total, currencyName);
    } else {
        el.value = total.toFixed(2);
    }
}

function exchangeRateChanged() { calculateTotals(); }

/* =========================================================
   Payment method
   ========================================================= */

function paymentMethodChanged(clearPrevious = false) {
    if (clearPrevious) {
        const inp = document.getElementById('paymentAccount');
        const inpId = document.getElementById('paymentAccountId');
        if (inp) inp.value = '';
        if (inpId) inpId.value = '';
    }

    hidePaymentAccounts();

    const methodEl = document.getElementById('PuInPaymentMethod2');
    const container = document.getElementById('paymentAccountContainer');
    const input = document.getElementById('paymentAccount');

    if (!methodEl || !container || !input) return;

    const method = methodEl.value;

    if (!method || method === 'credit') {
        container.classList.add('d-none');
        input.disabled = true;
        input.value = '';
        const inpId = document.getElementById('paymentAccountId');
        if (inpId) inpId.value = '';
        return;
    }

    container.classList.remove('d-none');
    input.disabled = (invoiceMode === 'view');

    const labels = { cash: 'الصندوق', bank: 'الحساب البنكي', network: 'حساب المحفظة' };
    const lbl = container.querySelector('label');
    if (lbl) lbl.textContent = labels[method] || 'الحساب';
}

function hidePaymentAccounts() {
    const el = document.getElementById('paymentAccountContainer');
    if (el) el.classList.add('d-none');
}

/* =========================================================
   Input handlers
   ========================================================= */

function supplierKeyDown(e) {
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault(); openSupplierModal();
    }
}
function currencyKeyDown(e) {
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault(); openCurrencyModal();
    }
}
function warehouseKeyDown(e) {
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault(); openWarehouseModal();
    }
}
function accountKeyDown(e) {
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault(); openAccountModal();
    }
}

function supplierInput(e) { debounce(() => { if (e.target.value.trim()) openSupplierModal(); })(); }
function currencyInput(e) { debounce(() => { if (e.target.value.trim()) openCurrencyModal(); })(); }
function warehouseInput(e) { debounce(() => { if (e.target.value.trim()) openWarehouseModal(); })(); }
function accountInput(e) { debounce(() => { if (e.target.value.trim()) openAccountModal(); })(); }
function purchaseItemInput(e) { debounce(() => { if (e.target.value.trim()) { activePurchaseRow = e.target.closest('tr'); openPurchaseItemModal(); } })(); }
function typeInput(e) { debounce(() => { if (e.target.value.trim()) { currentTypeInput = e.target; openTypeModal(); } })(); }

function supplierBlur() { }
function currencyBlur() { }
function warehouseBlur() { }
function accountBlur() { }

/* =========================================================
   Supplier Modal
   ========================================================= */

function openSupplierModal() {
    if (invoiceMode === 'view' || !supplierModal) return;
    document.getElementById('supplierSearchInput').value =
        document.getElementById('supplierName').value;
    supplierModal.show();
    setTimeout(() => {
        document.getElementById('supplierSearchInput').focus();
        searchSuppliers();
    }, 200);
}

async function searchSuppliers() {
    const search = document.getElementById('supplierSearchInput').value.trim();
    const tbody = document.getElementById('supplierResults');
    tbody.replaceChildren();

    try {
        const res = await apiGet(`/setting/suppliers/search?search=${encodeURIComponent(search)}`);
        const rows = unwrap(res);

        rows.forEach(s => {
            const tr = cloneTemplate('supplierRowTemplate');
            tr.querySelector('.c-id').textContent = s.suplierID;
            tr.querySelector('.c-name').textContent = s.supName ?? '';
            tr.querySelector('.c-code').textContent = s.accountCode ?? '';
            tr.querySelector('.select-btn').addEventListener('click', () => {
                selectSupplier(s.accountID ?? '', s.supName ?? '');
            });
            tbody.appendChild(tr);
        });
    } catch (e) { console.error(e); }
}

function selectSupplier(accountId, name) {
    document.getElementById('suplierID').value = accountId;
    document.getElementById('supplierName').value = name;
    if (supplierModal) supplierModal.hide();
    setTimeout(() => document.getElementById('currencyName').focus(), 250);
}

/* =========================================================
   Currency Modal
   ========================================================= */

function openCurrencyModal() {
    if (invoiceMode === 'view' || !currencyModal) return;
    document.getElementById('currencySearchInput').value =
        document.getElementById('currencyName').value;
    currencyModal.show();
    setTimeout(() => {
        document.getElementById('currencySearchInput').focus();
        searchCurrencies();
    }, 200);
}

async function searchCurrencies() {
    const search = document.getElementById('currencySearchInput').value.trim();
    const tbody = document.getElementById('currencyResults');
    tbody.replaceChildren();

    const filtered = cache.coins.filter(c =>
        !search ||
        (c.coinsName || '').includes(search) ||
        (c.coinsCode || '').includes(search)
    );

    filtered.forEach(c => {
        const tr = cloneTemplate('currencyRowTemplate');
        tr.querySelector('.c-id').textContent = c.coinsID;
        tr.querySelector('.c-name').textContent = c.coinsName ?? '';
        tr.querySelector('.c-code').textContent = c.coinsCode ?? '';
        tr.querySelector('.c-rate').textContent = c.coinsExchangeRate ?? '';
        tr.querySelector('.select-btn').addEventListener('click', () => {
            selectCurrency(c.coinsID, c.coinsName, c.coinsExchangeRate);
        });
        tbody.appendChild(tr);
    });
}

function selectCurrency(id, name, rate) {
    document.getElementById('coinsID').value = id;
    document.getElementById('currencyName').value = name;
    document.getElementById('PuInExchangeRate2').value = rate;
    if (currencyModal) currencyModal.hide();
    calculateTotals();
    setTimeout(() => document.getElementById('warehouseName').focus(), 250);
}

/* =========================================================
   Warehouse Modal
   ========================================================= */

function openWarehouseModal() {
    if (invoiceMode === 'view' || !warehouseModal) return;
    document.getElementById('warehouseSearchInput').value =
        document.getElementById('warehouseName').value;
    warehouseModal.show();
    setTimeout(() => {
        document.getElementById('warehouseSearchInput').focus();
        searchWarehouses();
    }, 200);
}

async function searchWarehouses() {
    const search = document.getElementById('warehouseSearchInput').value.trim();
    const tbody = document.getElementById('warehouseResults');
    tbody.replaceChildren();

    const filtered = cache.warehouses.filter(w =>
        !search || (w.StockName || '').includes(search)
    );

    filtered.forEach(w => {
        const tr = cloneTemplate('warehouseRowTemplate');
        tr.querySelector('.c-id').textContent = w.StockID;
        tr.querySelector('.c-name').textContent = w.StockName ?? '';
        tr.querySelector('.select-btn').addEventListener('click', () => {
            selectWarehouse(w.StockID, w.StockName);
        });
        tbody.appendChild(tr);
    });
}

function selectWarehouse(id, name) {
    document.getElementById('warehouseID').value = id;
    document.getElementById('warehouseName').value = name;
    if (warehouseModal) warehouseModal.hide();

    setTimeout(() => {
        const first = document.querySelector('#purchaseInvoiceDetails .purchase-detail-row');
        if (first) {
            first.querySelector('.row-item')?.focus();
        } else {
            addInvoiceRow();
            setTimeout(() => {
                document.querySelector('#purchaseInvoiceDetails .purchase-detail-row .row-item')?.focus();
            }, 100);
        }
    }, 250);
}

/* =========================================================
   Account Modal
   ========================================================= */

function openAccountModal() {
    if (invoiceMode === 'view' || !accountModal) return;

    const method = document.getElementById('PuInPaymentMethod2').value;
    if (!method || method === 'credit') {
        notify('يرجى اختيار طريقة دفع أولًا', 'warning');
        return;
    }
    accountSearchType = method;

    document.getElementById('accountSearchInput').value =
        document.getElementById('paymentAccount').value;

    const titles = { cash: 'اختيار الصندوق', bank: 'اختيار البنك', network: 'اختيار المحفظة' };
    document.getElementById('accountModalTitle').textContent =
        titles[method] || 'اختيار الحساب';

    accountModal.show();
    setTimeout(() => {
        document.getElementById('accountSearchInput').focus();
        searchAccounts();
    }, 200);
}

async function searchAccounts() {
    const search = document.getElementById('accountSearchInput').value.trim();
    const tbody = document.getElementById('accountResults');
    tbody.replaceChildren();

    let source = [];
    if (accountSearchType === 'cash') source = cache.boxes;
    else if (accountSearchType === 'bank') source = cache.banks;
    else if (accountSearchType === 'network') source = cache.boxes;
    else return;

    const filtered = source.filter(r => {
        const n = r.boxName || r.bankName || '';
        return !search || n.includes(search);
    });

    filtered.forEach(r => {
        const id = r.accountID;
        const name = r.boxName || r.bankName || '';
        const tr = cloneTemplate('accountRowTemplate');
        tr.querySelector('.c-id').textContent = id;
        tr.querySelector('.c-name').textContent = name;
        tr.querySelector('.select-btn').addEventListener('click', () => {
            selectAccount(id, name);
        });
        tbody.appendChild(tr);
    });
}

function selectAccount(id, name) {
    document.getElementById('paymentAccountId').value = id;
    document.getElementById('paymentAccount').value = name;
    if (accountModal) accountModal.hide();
    setTimeout(() => document.getElementById('supplierName').focus(), 250);
}

/* =========================================================
   Item Modal
   ========================================================= */

function purchaseItemKeyDown(e) {
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault();
        activePurchaseRow = e.target.closest('tr');
        openPurchaseItemModal();
    }
}

function openPurchaseItemModal(input) {
    if (invoiceMode === 'view' || !purchaseItemModal) return;
    if (input) activePurchaseRow = input.closest('tr');
    if (!activePurchaseRow) return;

    document.getElementById('purchaseItemSearchInput').value =
        activePurchaseRow.querySelector('.row-item')?.value || '';
    purchaseItemModal.show();
    setTimeout(() => {
        document.getElementById('purchaseItemSearchInput').focus();
        searchPurchaseItems();
    }, 200);
}

async function searchPurchaseItems() {
    const search = document.getElementById('purchaseItemSearchInput').value.trim();
    const tbody = document.getElementById('purchaseItemResults');
    tbody.replaceChildren();

    const filtered = cache.items.filter(i =>
        !search || (i.itemName2 || '').includes(search)
    );

    filtered.forEach(item => {
        const tr = cloneTemplate('itemRowTemplate');
        tr.querySelector('.c-id').textContent = item.itemID;
        tr.querySelector('.c-name').textContent = item.itemName2 ?? '';
        tr.querySelector('.select-btn').addEventListener('click', () => {
            selectPurchaseItem(item.itemID, item.itemName2);
        });
        tbody.appendChild(tr);
    });
}

function selectPurchaseItem(id, name) {
    if (!activePurchaseRow) return;
    const inp = activePurchaseRow.querySelector('.row-item');
    inp.value = name;
    inp.dataset.itemId = id;
    if (purchaseItemModal) purchaseItemModal.hide();
    setTimeout(() => activePurchaseRow.querySelector('.row-type')?.focus(), 250);
}

/* =========================================================
   Type Modal
   ========================================================= */

function typeKeyDown(e) {
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault();
        currentTypeInput = e.target;
        openTypeModal();
    }
}

function openTypeModal(input) {
    if (invoiceMode === 'view' || !typeModal) return;

    currentTypeInput = input || currentTypeInput || document.activeElement;
    if (!currentTypeInput?.classList.contains('row-type')) return;

    document.getElementById('typeSearchInput').value = currentTypeInput.value || '';
    typeModal.show();
    setTimeout(() => {
        document.getElementById('typeSearchInput').focus();
        searchTypes();
    }, 200);
}

async function searchTypes() {
    const search = document.getElementById('typeSearchInput').value.trim();
    const tbody = document.getElementById('typeResults');
    tbody.replaceChildren();

    const filtered = cache.types.filter(t =>
        !search || (t.name || '').includes(search)
    );

    filtered.forEach(t => {
        const tr = cloneTemplate('typeRowTemplate');
        tr.querySelector('.c-id').textContent = t.id;
        tr.querySelector('.c-name').textContent = t.name ?? '';
        tr.querySelector('.select-btn').addEventListener('click', () => {
            selectType(t.id, t.name);
        });
        tbody.appendChild(tr);
    });
}

function selectType(id, name) {
    if (currentTypeInput) {
        currentTypeInput.value = name;
        currentTypeInput.dataset.typeId = id;
        const row = currentTypeInput.closest('tr');
        if (typeModal) typeModal.hide();
        setTimeout(() => row.querySelector('.row-weight')?.focus(), 250);
    } else if (typeModal) {
        typeModal.hide();
    }
}

/* =========================================================
   Other cost
   ========================================================= */

function otherCostChanged() {
    const v = parseFloat(document.getElementById('PuInOtherCost').value) || 0;
    const c = document.getElementById('otherCostDescriptionContainer');
    const d = document.getElementById('otherCostDescription');
    if (v > 0) { c.classList.remove('d-none'); d.disabled = (invoiceMode === 'view'); }
    else { c.classList.add('d-none'); d.value = ''; }
    calculateTotals();
}

/* =========================================================
   Search Invoice
   ========================================================= */

function searchInvoice() {
    if (!invoiceSearchModal) return;

    document.getElementById('invoiceSearchInput').value = '';
    const tb = document.getElementById('invoiceSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 7;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'أدخل بيانات البحث ثم اضغط بحث';
    tr.appendChild(td);
    tb.appendChild(tr);

    invoiceSearchModal.show();
    setTimeout(() => document.getElementById('invoiceSearchInput').focus(), 200);
}

async function performInvoiceSearch() {
    const search = document.getElementById('invoiceSearchInput').value.trim();
    const tbody = document.getElementById('invoiceSearchResults');
    tbody.replaceChildren();

    try {
        const raw = await apiGet(`/operation/purchases/invoicesPurch/list?search=${encodeURIComponent(search)}`);
        const rows = unwrap(raw);
        const labels = { 1: 'أجل', 2: 'نقد', 3: 'بنك', 4: 'شبكة' };

        rows.forEach(inv => {
            const tr = document.createElement('tr');
            [
                inv.invoice_number,
                inv.invoice_date,
                inv.supplier_name,
                inv.coin_name,
                labels[inv.payment_method] || '',
                Number(inv.total).toFixed(2)
            ].forEach(v => {
                const td = document.createElement('td');
                td.textContent = v;
                tr.appendChild(td);
            });

            const tdBtn = document.createElement('td');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-primary';
            btn.textContent = 'عرض';
            btn.addEventListener('click', () => loadInvoice(inv.purchase_invoice_id));
            tdBtn.appendChild(btn);
            tr.appendChild(tdBtn);

            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        notify('فشل البحث عن الفواتير', 'danger');
    }
}

/* =========================================================
   Load Invoice
   ========================================================= */

async function loadInvoice(id) {
    try {
        const res = await apiGet(`/operation/purchases/invoicesPurch/${id}`);
        const h = res.header;
        const details = res.details || [];

        clearInvoiceForm();

        document.getElementById('PurchaseInvoicesON2').value = h.invoice_number ?? '';
        document.getElementById('PurchaseInvoicesDate2').value = h.invoice_date ?? '';
        document.getElementById('suplierID').value = h.account_id ?? '';
        document.getElementById('supplierName').value = h.supplier_name ?? '';
        document.getElementById('coinsID').value = h.coin_id ?? '';
        document.getElementById('currencyName').value = h.coin_name ?? '';
        document.getElementById('PuInExchangeRate2').value = h.exchange_rate ?? 1;
        document.getElementById('warehouseID').value = h.warehouse_id ?? '';
        document.getElementById('warehouseName').value = h.warehouse_name ?? '';
        document.getElementById('PuInPaymentMethod2').value =
            PAYMENT_METHOD_TO_STR[h.payment_method] || '';
        document.getElementById('paymentAccountId').value = h.payment_account_id ?? '';
        document.getElementById('paymentAccount').value = h.payment_account_name ?? '';
        document.getElementById('PuInStatement2').value = h.statement ?? '';
        document.getElementById('invoiceReference').value = h.reference ?? '';
        document.getElementById('PuInExpenses').value = h.expenses ?? 0;
        document.getElementById('PuInTaxCost').value = h.tax_cost ?? 0;
        document.getElementById('PuInTransportation').value = h.transportation ?? 0;
        document.getElementById('PuInOtherCost').value = h.other_cost ?? 0;
        document.getElementById('otherCostDescription').value = h.other_cost_description ?? '';

        if (Number(h.other_cost) > 0)
            document.getElementById('otherCostDescriptionContainer').classList.remove('d-none');

        paymentMethodChanged();

        const tbody = document.getElementById('purchaseInvoiceDetails');
        tbody.replaceChildren();

        details.forEach((d, i) => {
            const row = cloneTemplate('invoiceRowTemplate');
            const unitSel = row.querySelector('.row-unit');
            cache.units.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.UnitID;
                opt.textContent = u.UnitName;
                if (u.UnitID == d.unit_id) opt.selected = true;
                unitSel.appendChild(opt);
            });
            row.querySelector('.row-num').textContent = i + 1;
            row.querySelector('.row-item').value = d.item_name ?? '';
            row.querySelector('.row-item').dataset.itemId = d.item_id ?? '';
            row.querySelector('.row-type').value = d.type_name ?? '';
            row.querySelector('.row-type').dataset.typeId = d.type_id ?? '';
            row.querySelector('.row-code').value = d.code ?? '';
            row.querySelector('.row-weight').value = d.quantity;
            row.querySelector('.row-price').value = d.price;
            row.querySelector('.row-discount').value = d.discount;
            row.querySelector('.row-total').value = Number(d.total).toFixed(2);
            tbody.appendChild(row);
        });

        if (!details.length) setEmptyDetailsMessage();

        calculateTotals();
        if (invoiceSearchModal) invoiceSearchModal.hide();
        currentInvoiceId = h.purchase_invoice_id;
        setInvoiceMode('view');
        editSnapshot = null;

        notify('تم تحميل الفاتورة بنجاح', 'success');
    } catch (e) {
        notify('فشل تحميل الفاتورة: ' + e.message, 'danger');
    }
}

/* =========================================================
   Save / Edit / Cancel / Print
   ========================================================= */

function hasInvoiceData() {
    const el = document.getElementById('PurchaseInvoicesON2');
    if (!el) return false;
    return el.value.trim() !== '';
}

function editInvoice() {
    if (!hasInvoiceData() || !currentInvoiceId) {
        notify('لا توجد فاتورة للتعديل', 'warning');
        return;
    }
    setInvoiceMode('edit');
    document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row')
        .forEach(r => enableRow(r));
    document.querySelectorAll(
        '#PurchaseInvoicesDate2, #PuInPaymentMethod2, #paymentAccount, #supplierName, ' +
        '#currencyName, #PuInExchangeRate2, #warehouseName, #PuInStatement2, ' +
        '#invoiceReference, #PuInExpenses, #PuInTaxCost, #PuInTransportation, ' +
        '#PuInOtherCost, #otherCostDescription'
    ).forEach(el => el.disabled = false);
    paymentMethodChanged();

    // ✅ حفظ لقطة للفاتورة بعد الدخول في وضع التعديل
    editSnapshot = takeSnapshot();
}

function cancelInvoice() {
    if (!confirm('هل أنت متأكد من إلغاء العملية؟')) return;
    clearInvoiceForm();
    notify('تم إلغاء العملية', 'info');
}

async function saveInvoice() {
    // حماية من الحفظ المزدوج
    if (isSavingInvoice) return;

    const number = document.getElementById('PurchaseInvoicesON2').value.trim();
    if (!number) return notify('رقم الفاتورة مطلوب', 'warning');

    const accountId = document.getElementById('suplierID').value;
    if (!accountId) return notify('يجب اختيار المورد', 'warning');

    const coinId = document.getElementById('coinsID').value;
    if (!coinId) return notify('يجب اختيار العملة', 'warning');

    const wid = document.getElementById('warehouseID').value;
    if (!wid) return notify('يجب اختيار المخزن', 'warning');

    const methodStr = document.getElementById('PuInPaymentMethod2').value;
    if (!methodStr) return notify('يجب اختيار طريقة الدفع', 'warning');

    const methodInt = PAYMENT_METHOD_TO_INT[methodStr];
    if (!methodInt) return notify('طريقة الدفع غير صالحة', 'danger');

    // التحقق المنطقي: طرق الدفع الفوري تتطلب حسابًا
    if (methodInt !== 1 && !document.getElementById('paymentAccountId').value) {
        return notify('يجب اختيار حساب الدفع', 'warning');
    }

    const rows = document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row');
    if (!rows.length) return notify('أضف صنفًا واحدًا على الأقل', 'warning');

    const details = [];
    for (const row of rows) {
        const itemId = row.querySelector('.row-item').dataset.itemId;
        if (!itemId) return notify('يجب اختيار الصنف في كل الصفوف', 'warning');

        const qty = parseFloat(row.querySelector('.row-weight').value) || 0;
        if (qty <= 0) return notify('الكمية يجب أن تكون أكبر من صفر', 'warning');

        const price = parseFloat(row.querySelector('.row-price').value) || 0;
        if (price <= 0) return notify('سعر الوحدة يجب أن يكون أكبر من صفر', 'warning');

        const discount = parseFloat(row.querySelector('.row-discount').value) || 0;

        if (discount > qty * price) {
            return notify('الخصم لا يمكن أن يتجاوز قيمة الصف', 'warning');
        }

        const rowTotal = Math.max(0, qty * price - discount);
        if (rowTotal <= 0) {
            return notify('إجمالي الصف يجب أن يكون أكبر من صفر', 'warning');
        }

        details.push({
            item_id: Number(itemId),
            type_id: row.querySelector('.row-type').dataset.typeId
                ? Number(row.querySelector('.row-type').dataset.typeId) : null,
            unit_id: row.querySelector('.row-unit').value
                ? Number(row.querySelector('.row-unit').value) : null,
            code: row.querySelector('.row-code').value || null,
            quantity: qty,
            price: price,
            discount: discount,
        });
    }

    // ✅ كشف عدم وجود تعديلات في وضع التعديل
    if (invoiceMode === 'edit' && editSnapshot) {
        const currentSnapshot = takeSnapshot();
        if (currentSnapshot === editSnapshot) {
            notify('لم يتم إجراء أي تعديل على الفاتورة', 'info');
            return;
        }
    }

    const payload = {
        invoice_number: number,
        invoice_date: document.getElementById('PurchaseInvoicesDate2').value,
        account_id: Number(accountId),
        payment_method: methodInt,
        payment_account_id: document.getElementById('paymentAccountId').value || null,
        coin_id: Number(coinId),
        warehouse_id: Number(wid),
        exchange_rate: parseFloat(document.getElementById('PuInExchangeRate2').value) || 1,
        expenses: parseFloat(document.getElementById('PuInExpenses').value) || 0,
        tax_cost: parseFloat(document.getElementById('PuInTaxCost').value) || 0,
        transportation: parseFloat(document.getElementById('PuInTransportation').value) || 0,
        other_cost: parseFloat(document.getElementById('PuInOtherCost').value) || 0,
        other_cost_description: document.getElementById('otherCostDescription').value || null,
        statement: document.getElementById('PuInStatement2').value || null,
        reference: document.getElementById('invoiceReference').value || null,
        details,
    };

    // بدء الحماية من الحفظ المزدوج
    isSavingInvoice = true;

    const saveBtn = document.getElementById('btnSaveInvoice');
    const saveNewBtn = document.getElementById('btnSaveAndNew');
    if (saveBtn) saveBtn.disabled = true;
    if (saveNewBtn) saveNewBtn.disabled = true;

    try {
        let r;
        if (invoiceMode === 'edit' && currentInvoiceId) {
            r = await apiSend(`/operation/purchases/invoicesPurch/${currentInvoiceId}`, 'PUT', payload);
        } else {
            r = await apiSend('/operation/purchases/invoicesPurch', 'POST', payload);
            currentInvoiceId = r.purchase_invoice_id;

            if (r.invoice_number) {
                document.getElementById('PurchaseInvoicesON2').value = r.invoice_number;
            }
        }
        notify(r.message || 'تم الحفظ بنجاح', 'success');
        setInvoiceMode('view');
        editSnapshot = null;
    } catch (e) {
        let m = e.message;
        if (e.errors) m += ' — ' + Object.values(e.errors).flat().join(' | ');
        notify(m, 'danger');
    } finally {
        isSavingInvoice = false;
        if (invoiceMode !== 'view') {
            if (saveBtn) saveBtn.disabled = false;
            if (saveNewBtn) saveNewBtn.disabled = false;
        }
    }
}

async function saveAndNewInvoice() {
    await saveInvoice();
    if (invoiceMode === 'view') await resetInvoice();
}

function printInvoice() {
    if (!hasInvoiceData()) { notify('لا توجد فاتورة للطباعة', 'warning'); return; }
    window.print();
}