/* =========================================================================
   فاتورة البيع — بنفس نمط فاتورة الشراء
   ========================================================================= */

/* =========================================================
   الثوابت
   ========================================================= */
const SALES_SYSTEM_CURRENCY_ID   = window.SALES_SYSTEM_CURRENCY_ID || null;
const SALES_SYSTEM_CURRENCY_CODE = window.SALES_SYSTEM_CURRENCY_CODE || '';

const SALES_PAYMENT_TO_INT = { credit: 1, cash: 2, bank: 3 };
const SALES_PAYMENT_TO_STR = { 1: 'credit', 2: 'cash', 3: 'bank' };
const SALES_PAYMENT_LABELS = { 1: 'أجل', 2: 'نقدي', 3: 'بنك' };

const PIECE_UNIT_NAMES = ['الحبة', 'حبة', 'حبه', 'حب', 'قطعة', 'قطعه'];

/* =========================================================
   الحالة العامة
   ========================================================= */
let salesMode = 'view';
let currentSalesInvoiceId = null;
let currentSalesInvoiceIsPosted = false;
let currentSalesInvoiceHasReturns = false;
let isSavingSales = false;
let salesEditSnapshot = null;
let salesInvoiceSearchTimer = null;

let stockBalancesModalInstance = null;
let activeStockBalanceInput = null;
let activeStockBalanceRow = null;
let stockBalancesCache = [];

const SALES_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* =========================================================
   Helpers
   ========================================================= */

function salesApiHeaders(json = false) {
    const h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': SALES_CSRF };
    if (json) h['Content-Type'] = 'application/json';
    return h;
}

async function salesApiGet(url) {
    const r = await fetch(url, { headers: salesApiHeaders() });
    if (!r.ok) throw new Error(`فشل الطلب: ${r.status}`);
    return r.json();
}

async function salesApiSend(url, method, body) {
    const r = await fetch(url, {
        method, headers: salesApiHeaders(true), body: JSON.stringify(body),
    });
    const json = await r.json().catch(() => ({}));
    if (!r.ok) {
        let message = json.message || 'فشل الطلب';
        if (json.errors) message = Object.values(json.errors).flat().join(' | ');
        else if (json.error) message = json.error;

        const e = new Error(message);
        e.errors = json.errors;
        e.raw = json;
        console.error('API Error:', json);
        throw e;
    }
    return json;
}

function salesNotify(message, type = 'info') {
    if (typeof window.showSystemToast === 'function') {
        window.showSystemToast(message, type);
    } else {
        console.warn(`[${type}] ${message}`);
        alert(message);
    }
}

function salesGetValue(id) {
    const el = document.getElementById(id);
    return el ? (el.value || '') : '';
}

function salesSetValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value ?? '';
}

function salesFormatMoney(v) {
    if (window.Utils && typeof Utils.formatAmount === 'function') {
        return Utils.formatAmount(v);
    }
    return Number(v || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/* تنسيق سعر الصرف بدون أصفار زائدة */
function salesFormatRate(v) {
    if (window.Utils && typeof Utils.formatRate === 'function') {
        return Utils.formatRate(v);
    }
    return String(parseFloat(Number(v || 0).toFixed(6)));
}

function findDefaultPieceUnit() {
    const units = (typeof lookupCache !== 'undefined' && lookupCache.units) || [];
    for (const name of PIECE_UNIT_NAMES) {
        const found = units.find(u => u.UnitName === name);
        if (found) return found;
    }
    return null;
}

function salesExtractPayload(res) {
    if (res && res.header) {
        return { header: res.header, details: res.details || [] };
    }
    if (res && res.data && res.data.header) {
        return { header: res.data.header, details: res.data.details || [] };
    }
    return { header: {}, details: [] };
}

/**
 * ✅ هل العملة الحالية هي عملة النظام؟
 */
function isSalesInSystemCurrency() {
    const coinId = salesGetValue('salesCoinsID');
    if (!SALES_SYSTEM_CURRENCY_ID || !coinId) return false;
    return String(coinId) === String(SALES_SYSTEM_CURRENCY_ID);
}

/* =========================================================
   ✅ إدارة حالة سعر الصرف والعملة
   ========================================================= */
function applySalesExchangeRateState() {
    const rateEl = document.getElementById('SalesExchangeRate');
    if (!rateEl) return;

    /* ✅ سعر الصرف خاص بالفاتورة:
       - عملة النظام → readonly بقيمة 1 (مرجع التحويل)
       - عملة أجنبية → قابل للتعديل (القيمة الافتراضية من العملة، وتُحفظ مع الفاتورة) */
    if (isSalesInSystemCurrency()) {
        rateEl.readOnly = true;
        rateEl.tabIndex = -1;
        rateEl.classList.add('bg-light');
        rateEl.value = '1.000000';
        rateEl.title = 'سعر صرف عملة النظام ثابت = 1';
    } else {
        rateEl.readOnly = false;
        rateEl.tabIndex = 0;
        rateEl.classList.remove('bg-light');
        rateEl.title = 'سعر صرف هذه الفاتورة (يمكن تجاوز السعر اليومي)';
    }

    /* في وضع العرض فقط تكون كل الحقول مقروءة */
    if (typeof salesMode !== 'undefined' && salesMode === 'view') {
        rateEl.readOnly = true;
    }

    const currencyInput = document.getElementById('salesCurrencyName');
    const paymentAccountId = salesGetValue('salesPaymentAccountId');

    if (currencyInput) {
        if (paymentAccountId) {
            currencyInput.readOnly = true;
            currencyInput.classList.add('bg-light');
            currencyInput.style.pointerEvents = 'none';
            currencyInput.title = 'العملة محددة من حساب الدفع';
        } else {
            currencyInput.readOnly = false;
            currencyInput.classList.remove('bg-light');
            currencyInput.style.pointerEvents = 'auto';
            currencyInput.title = '';
        }
    }
}

/* =========================================================
   ✅ تنظيف الـ modals العالقة
   ========================================================= */
function cleanupStuckModals() {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');

    document.querySelectorAll('.modal.show').forEach(modalEl => {
        const instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) {
            try { instance.hide(); } catch (e) { /* ignore */ }
        }
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
    });

    document.querySelectorAll('.offcanvas-backdrop').forEach(el => el.remove());
}

/* =========================================================
   Lookup Hooks
   ========================================================= */

function registerSalesLookupHooks() {
    if (typeof LookupConfigs === 'undefined') return;

    LookupConfigs.currency.onSelect = (row) => {
        const isSys = String(row.coinsID) === String(SALES_SYSTEM_CURRENCY_ID);
        const rate = isSys ? 1 : (parseFloat(row.coinsExchangeRate) || 1);

        salesSetValue('SalesExchangeRate', salesFormatRate(rate));

        salesSetValue('salesPaymentAccount', '');
        salesSetValue('salesPaymentAccountId', '');

        applySalesExchangeRateState();
        calculateSalesTotals();
    };

    LookupConfigs.box.onSelect = (row) => {
        if (row.coinsID) {
            salesSetValue('salesCoinsID', row.coinsID);
            salesSetValue('salesCurrencyName', row.coinsName || '');

            const isSys = String(row.coinsID) === String(SALES_SYSTEM_CURRENCY_ID);
            const rate = isSys ? 1 : (parseFloat(row.coinsExchangeRate) || 1);
            salesSetValue('SalesExchangeRate', salesFormatRate(rate));
        }

        applySalesExchangeRateState();
        calculateSalesTotals();
    };

    LookupConfigs.bank.onSelect = (row) => {
        if (row.coinsID) {
            salesSetValue('salesCoinsID', row.coinsID);
            salesSetValue('salesCurrencyName', row.coinsName || '');

            const isSys = String(row.coinsID) === String(SALES_SYSTEM_CURRENCY_ID);
            const rate = isSys ? 1 : (parseFloat(row.coinsExchangeRate) || 1);
            salesSetValue('SalesExchangeRate', salesFormatRate(rate));
        }

        applySalesExchangeRateState();
        calculateSalesTotals();
    };
}

/* =========================================================
   التهيئة
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {
    cleanupStuckModals();

    window.lookupGetActiveCoinId = () => salesGetValue('salesCoinsID');

    registerSalesLookupHooks();

    const balancesEl = document.getElementById('stockBalancesModal');
    if (balancesEl) {
        stockBalancesModalInstance = new bootstrap.Modal(balancesEl);
    }

    setSalesMode('view');
    clearSalesForm();

    setTimeout(cleanupStuckModals, 100);
    setTimeout(cleanupStuckModals, 500);
});

window.addEventListener('load', () => {
    setTimeout(cleanupStuckModals, 200);
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        cleanupStuckModals();
    }
});

/* =========================================================
   أوضاع الشاشة
   ========================================================= */

function setSalesMode(mode) {
    salesMode = mode;

    document.querySelectorAll(
        '#SalesInvoiceDate, #SalesPaymentMethod, #salesPaymentAccount, ' +
        '#customerName, #salesCurrencyName, #SalesStatement, #SalesReference'
    ).forEach(el => { el.disabled = (mode === 'view'); });

    document.querySelectorAll('#salesInvoiceDetails .sales-detail-row')
        .forEach(row => enableSalesRow(row));

    const addBtn = document.getElementById('btnAddSalesRow');
    if (addBtn) addBtn.disabled = (mode === 'view');

    const newBtn = document.getElementById('btnNewSalesInvoice');
    if (newBtn) newBtn.disabled = (mode !== 'view');

    const saveBtn = document.getElementById('btnSaveSalesInvoice');
    const saveNewBtn = document.getElementById('btnSaveAndNewSalesInvoice');
    const cancelBtn = document.getElementById('btnCancelSalesInvoice');

    if (mode === 'view') {
        if (saveBtn) saveBtn.disabled = true;
        if (saveNewBtn) saveNewBtn.disabled = true;
        if (cancelBtn) cancelBtn.classList.add('d-none');

        const payInput = document.getElementById('salesPaymentAccount');
        if (payInput) payInput.disabled = true;

        const payContainer = document.getElementById('salesPaymentAccountContainer');
        if (payContainer) payContainer.classList.add('d-none');
    } else {
        if (saveBtn) saveBtn.disabled = false;
        if (saveNewBtn) saveNewBtn.disabled = false;
        if (cancelBtn) cancelBtn.classList.remove('d-none');
        salesPaymentMethodChanged();
    }

    updateSalesSearchButton();
    updateSalesPostingButtons();
    applySalesExchangeRateState();
}

/* =========================================================
   ✅ إدارة زر البحث
   ========================================================= */

function updateSalesSearchButton() {
    const searchBtn = document.getElementById('btnSearchSalesInvoice');
    if (!searchBtn) return;

    const hasInvoice = !!currentSalesInvoiceId;
    const isViewMode = salesMode === 'view';

    searchBtn.disabled = !isViewMode || hasInvoice;
}

/* =========================================================
   ✅ إدارة أزرار الترحيل / إلغاء الترحيل
   ========================================================= */

function updateSalesPostingButtons() {
    const editBtn   = document.getElementById('btnEditSalesInvoice');
    const postBtn   = document.getElementById('btnPostSalesInvoice');
    const unpostBtn = document.getElementById('btnUnpostSalesInvoice');
    const printBtn  = document.getElementById('btnPrintSalesInvoice');

    const hasData  = hasSalesInvoiceData();
    const isPosted = currentSalesInvoiceIsPosted;
    const isView   = salesMode === 'view';

    if (editBtn) {
        editBtn.disabled = !hasData || isPosted || !isView;
        editBtn.classList.toggle('d-none', isPosted);

        if (currentSalesInvoiceHasReturns && hasData && isView) {
            editBtn.title = 'لا يمكن تعديل فاتورة لها مرتجعات';
        } else {
            editBtn.title = 'تعديل الفاتورة';
        }
    }

    if (postBtn) {
        const show = hasData && !isPosted;
        postBtn.classList.toggle('d-none', !show);
        postBtn.disabled = !hasData || isPosted || !isView;
    }

    if (unpostBtn) {
        const show = hasData && isPosted;
        unpostBtn.classList.toggle('d-none', !show);
        unpostBtn.disabled = !hasData || !isPosted || !isView;
    }

    if (printBtn) {
        printBtn.disabled = !hasData;
    }
}

/* =========================================================
   Reset / Clear
   ========================================================= */

async function resetSalesInvoice() {
    cleanupStuckModals();

    clearSalesForm();
    setSalesMode('add');

    try {
        const res = await salesApiGet('/operation/sales/invoices/next-number');
        salesSetValue('SalesInvoiceNo', res.next_number || '');
    } catch (e) {
        salesNotify('تعذّر جلب رقم الفاتورة التالي', 'danger');
    }

    salesSetValue('SalesInvoiceDate', new Date().toISOString().split('T')[0]);

    addSalesRow();

    const customerEl = document.getElementById('customerName');
    if (customerEl) customerEl.focus();
}

function clearSalesForm() {
    document.querySelectorAll(
        '#SalesInvoiceNo, #SalesInvoiceDate, #SalesPaymentMethod, ' +
        '#salesPaymentAccount, #customerName, #salesCurrencyName, ' +
        '#SalesStatement, #SalesReference'
    ).forEach(el => {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
    });

    /* ✅ سعر الصرف تُعيد حالته دالة applySalesExchangeRateState (قابل للتعديل/مقروء حسب العملة) */
    const rateEl = document.getElementById('SalesExchangeRate');
    if (rateEl) rateEl.value = '';

    ['customerID', 'salesCoinsID', 'salesPaymentAccountId']
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

    setEmptySalesMessage();

    const discEl = document.getElementById('totalSalesDiscountDisplay');
    if (discEl) discEl.textContent = '0.00';

    const totalEl = document.getElementById('salesInvoiceTotalDisplay');
    if (totalEl) totalEl.textContent = '0.00';

    const baseResetEl = document.getElementById('salesInvoiceTotalBaseDisplay');
    if (baseResetEl) baseResetEl.textContent = '0.00';

    const exchangeRateResetEl = document.getElementById('SalesExchangeRate');
    if (exchangeRateResetEl) exchangeRateResetEl.value = '1.000000';

    hideSalesPaymentAccounts();

    currentSalesInvoiceId = null;
    currentSalesInvoiceIsPosted = false;
    currentSalesInvoiceHasReturns = false;
    salesEditSnapshot = null;

    setSalesMode('view');
    applySalesExchangeRateState();
}

function setEmptySalesMessage() {
    const tbody = document.getElementById('salesInvoiceDetails');
    if (!tbody) return;

    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 11; /* جدول أصناف الفاتورة (#salesInvoiceDetails) = 11 عمودًا */
    td.className = 'text-center text-muted py-4';
    td.textContent = 'لا توجد أصناف مضافة إلى الفاتورة';
    tr.appendChild(td);
    tbody.appendChild(tr);
}

/* =========================================================
   إدارة الصفوف
   ========================================================= */

function addSalesRow() {
    if (salesMode === 'view') return;

    const tbody = document.getElementById('salesInvoiceDetails');
    if (!tbody) return;

    /* صف «لا توجد أصناف» يُحذف بأي colspan (11) — للحماية من تغيّر عدد الأعمدة */
    const emptyRow = tbody.querySelector('tr:only-child > td[colspan]');
    if (emptyRow) while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const tpl = document.getElementById('salesRowTemplate');
    if (!tpl) {
        salesNotify('قالب الصف غير موجود', 'danger');
        return;
    }

    const row = tpl.content.firstElementChild.cloneNode(true);

    const defaultUnit = findDefaultPieceUnit();
    if (defaultUnit) {
        row.querySelector('.row-unit-id').value = defaultUnit.UnitID;
        row.querySelector('.row-unit').value = defaultUnit.UnitName;
    }

    tbody.appendChild(row);
    enableSalesRow(row);
    renumberSalesRows();
    calculateSalesTotals();

    return row;
}

function enableSalesRow(row) {
    if (!row) return;

    row.querySelectorAll('input, select, button').forEach(el => {
        if (el.classList.contains('row-total')) return;
        if (el.classList.contains('row-item')) return;
        if (el.classList.contains('row-type')) return;
        if (el.classList.contains('row-code')) return;
        if (el.classList.contains('row-unit')) return;
        if (el.classList.contains('row-warehouse')) return;
        if (el.classList.contains('row-cost-price')) return;
        if (el.type === 'hidden') return;
        el.disabled = (salesMode === 'view');
    });
}

function removeSalesRow(btn) {
    if (salesMode === 'view') return;

    const row = btn.closest('tr');
    if (row) row.remove();

    renumberSalesRows();
    calculateSalesTotals();

    const tbody = document.getElementById('salesInvoiceDetails');
    if (tbody && !tbody.querySelector('.sales-detail-row')) setEmptySalesMessage();
}

function renumberSalesRows() {
    document.querySelectorAll('#salesInvoiceDetails .sales-detail-row')
        .forEach((r, i) => {
            const numEl = r.querySelector('.row-num');
            if (numEl) numEl.textContent = i + 1;
        });
}

/* =========================================================
   الحسابات
   ========================================================= */

function calculateSalesRow(input) {
    const row = input.closest('tr');
    if (!row) return;

    /* ✅ أي تعديل يدوي على سعر الوحدة/الخصم في الصف يمنع إعادة التحويل التلقائي عند تغيير العملة */
    const target = input || null;
    if (target && target.classList.contains('row-price')) {
        target.dataset.manualPrice = '1';
    }

    const qty = parseFloat(row.querySelector('.row-measure')?.value) || 0;
    const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
    const disc = parseFloat(row.querySelector('.row-discount')?.value) || 0;

    const totalEl = row.querySelector('.row-total');
    if (totalEl) totalEl.value = Math.max(0, qty * price - disc).toFixed(2);

    calculateSalesTotals();
}

function calculateSalesTotals() {
    let itemsTotal = 0;
    let discountTotal = 0;

    document.querySelectorAll('#salesInvoiceDetails .sales-detail-row').forEach(row => {
        const q = parseFloat(row.querySelector('.row-measure')?.value) || 0;
        const p = parseFloat(String(row.querySelector('.row-price')?.value).replace(/,/g, '')) || 0;
        const d = parseFloat(String(row.querySelector('.row-discount')?.value).replace(/,/g, '')) || 0;
        itemsTotal += q * p;
        discountTotal += d;
    });

    const exchangeRate = parseFloat(String(document.getElementById('SalesExchangeRate')?.value).replace(/,/g, '')) || 1;

    const net = Math.max(0, itemsTotal - discountTotal);
    const total = net * exchangeRate;

    const discEl = document.getElementById('totalSalesDiscountDisplay');
    if (discEl) discEl.textContent = salesFormatMoney(discountTotal);

    const totalEl = document.getElementById('salesInvoiceTotalDisplay');
    if (totalEl) totalEl.textContent = salesFormatMoney(total);

    /* ✅ تسمية عملة الإجمالي — تمنع الخلط بين إجمالي الإيراد وإجمالي التكلفة */
    const currencyLabelEl = document.getElementById('salesInvoiceTotalCurrencyLabel');
    if (currencyLabelEl) {
        const coinName = (document.getElementById('salesCurrencyName')?.value || '').trim();
        currencyLabelEl.textContent = coinName || 'بعملة الفاتورة';
    }

    /* ✅ الإجمالي بالعملة الأساسية = الإجمالي × سعر الصرف */
    const baseEl = document.getElementById('salesInvoiceTotalBaseDisplay');
    if (baseEl) baseEl.textContent = salesFormatMoney(total);

    /* ملاحظة: total أعلاه = net × exchangeRate، لذا فهو نفسه بالعملة الأساسية */
}

function salesExchangeRateChanged() {
    /* ✅ إعادة احتساب الإجمالي بالعملة المحلية عند تغيير سعر الصرف */
    const rateEl = document.getElementById('SalesExchangeRate');
    if (rateEl) {
        const rate = parseFloat(rateEl.value);
        if (!isNaN(rate) && rate <= 0) {
            rateEl.value = '1.000000';
        }
    }
    calculateSalesTotals();
}

/* =========================================================
   طرق الدفع — 3 طرق
   ========================================================= */

function salesPaymentMethodChanged(clearPrevious = false) {
    if (clearPrevious) {
        salesSetValue('salesPaymentAccount', '');
        salesSetValue('salesPaymentAccountId', '');
    }

    hideSalesPaymentAccounts();

    const methodEl = document.getElementById('SalesPaymentMethod');
    const container = document.getElementById('salesPaymentAccountContainer');
    const input = document.getElementById('salesPaymentAccount');
    const label = document.getElementById('salesPaymentAccountLabel');

    if (!methodEl || !container || !input) return;

    const method = methodEl.value;

    if (!method || method === 'credit') {
        container.classList.add('d-none');
        input.disabled = true;
        input.value = '';
        salesSetValue('salesPaymentAccountId', '');
        applySalesExchangeRateState();
        return;
    }

    container.classList.remove('d-none');
    input.disabled = (salesMode === 'view');

    if (method === 'bank') {
        input.dataset.lookup = 'bank';
        input.dataset.lookupDisplayField = 'bankName';
        if (label) label.textContent = 'الحساب البنكي';
    } else {
        input.dataset.lookup = 'box';
        input.dataset.lookupDisplayField = 'boxName';
        if (label) label.textContent = 'الصندوق';
    }

    applySalesExchangeRateState();
}

function hideSalesPaymentAccounts() {
    const el = document.getElementById('salesPaymentAccountContainer');
    if (el) el.classList.add('d-none');
}

/* =========================================================
   البحث عن فاتورة — النقر على الصف
   ========================================================= */

function searchSalesInvoice() {
    const modalEl = document.getElementById('salesInvoiceSearchModal');
    if (!modalEl) return;

    salesSetValue('salesInvoiceSearchInput', '');
    const tb = document.getElementById('salesInvoiceSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 9;
    td.className = 'text-center text-muted py-4';
    td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
    tr.appendChild(td);
    tb.appendChild(tr);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    setTimeout(() => {
        document.getElementById('salesInvoiceSearchInput')?.focus();
        performSalesInvoiceSearch();
    }, 250);
}

function debouncedSalesInvoiceSearch() {
    clearTimeout(salesInvoiceSearchTimer);
    salesInvoiceSearchTimer = setTimeout(performSalesInvoiceSearch, 350);
}

/**
 * ✅ تفريغ البحث — إعادة عرض كل الفواتير
 */
function clearSalesInvoiceSearch() {
    salesSetValue('salesInvoiceSearchInput', '');
    performSalesInvoiceSearch();

    setTimeout(() => {
        document.getElementById('salesInvoiceSearchInput')?.focus();
    }, 100);
}

async function performSalesInvoiceSearch() {
    const search = salesGetValue('salesInvoiceSearchInput').trim();
    const tbody = document.getElementById('salesInvoiceSearchResults');
    tbody.replaceChildren();

    try {
        const raw = await salesApiGet(`/operation/sales/invoices/list?search=${encodeURIComponent(search)}`);
        const rows = Array.isArray(raw) ? raw : (raw.data || []);

        if (rows.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 9;
            td.className = 'text-center text-muted py-4';
            td.textContent = 'لا توجد نتائج مطابقة';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        rows.forEach((inv, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'text-center';
            tr.style.cursor = 'pointer';
            tr.title = 'اضغط لتحميل الفاتورة';

            tr.addEventListener('click', () => loadSalesInvoice(inv.sales_invoice_id));

            tr.addEventListener('mouseenter', () => {
                tr.classList.add('table-primary');
            });
            tr.addEventListener('mouseleave', () => {
                tr.classList.remove('table-primary');
            });

            /* الرقم التسلسلي (الأحدث في الأعلى = 1) */
            const tdSerial = document.createElement('td');
            tdSerial.textContent = idx + 1;
            tr.appendChild(tdSerial);

            [
                inv.invoice_number,
                inv.invoice_date,
                inv.customer_name,
                inv.coin_name,
                SALES_PAYMENT_LABELS[inv.payment_method] || '',
                salesFormatMoney(inv.total)
            ].forEach(v => {
                const td = document.createElement('td');
                td.textContent = v;
                tr.appendChild(td);
            });

            const tdStatus = document.createElement('td');
            const badge = document.createElement('span');
            if (inv.is_posted) {
                badge.className = 'badge bg-success';
                badge.textContent = 'مرحّلة';
            } else {
                badge.className = 'badge bg-secondary';
                badge.textContent = 'مسودة';
            }
            tdStatus.appendChild(badge);
            tr.appendChild(tdStatus);

            const tdActions = document.createElement('td');
            tdActions.className = 'text-nowrap';
            tdActions.addEventListener('click', (e) => e.stopPropagation());

            tdActions.appendChild(buildSalesPostToggleBtn(inv));
            tdActions.appendChild(buildSalesDeleteBtn(inv));
            tr.appendChild(tdActions);

            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        salesNotify('فشل البحث عن الفواتير', 'danger');
    }
}

/* =========================================================
   أزرار الإجراءات
   ========================================================= */

function buildSalesPostToggleBtn(inv) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm me-1';

    if (inv.is_posted) {
        btn.classList.add('btn-outline-warning');
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> إلغاء الترحيل';
        btn.title = 'فتح الفاتورة للتعديل';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            unpostSalesInvoice(inv.sales_invoice_id);
        });
    } else {
        btn.classList.add('btn-outline-success');
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> ترحيل';
        btn.title = 'ترحيل الفاتورة وإنشاء القيود';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            postSalesInvoice(inv.sales_invoice_id);
        });
    }

    return btn;
}

function buildSalesDeleteBtn(inv) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-danger';
    btn.innerHTML = '<i class="bi bi-trash"></i> حذف';
    btn.title = 'حذف الفاتورة نهائيًا';
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteSalesInvoice(inv.sales_invoice_id, inv.invoice_number);
    });
    return btn;
}

/* =========================================================
   POST / UNPOST / DELETE (من نافذة البحث)
   ========================================================= */

/** ✅ تأكيد موحّد عبر appConfirm (shared/app-confirm.js) */
function salesConfirm(message, opts = {}) {
    if (typeof window.appConfirm === 'function') {
        return window.appConfirm(message, opts);
    }
    return Promise.resolve(window.confirm(message));
}

async function postSalesInvoice(id) {
    const ok = await salesConfirm('هل تريد ترحيل هذه الفاتورة؟\nسيتم إنشاء القيود وحركة المخزون.', {
        title: 'ترحيل فاتورة البيع',
        okText: 'ترحيل',
    });
    if (!ok) return;

    try {
        const r = await salesApiSend(`/operation/sales/invoices/${id}/post`, 'POST', {});
        salesNotify(r.message || 'تم ترحيل الفاتورة بنجاح', 'success');
        performSalesInvoiceSearch();
    } catch (e) {
        salesNotify(e.message, 'danger');
    }
}

async function unpostSalesInvoice(id) {
    const ok = await salesConfirm('هل تريد إلغاء ترحيل هذه الفاتورة؟\nسيتم حذف القيود وحركة المخزون.', {
        title: 'إلغاء ترحيل فاتورة البيع',
        okText: 'إلغاء الترحيل',
        danger: true,
    });
    if (!ok) return;

    try {
        const r = await salesApiSend(`/operation/sales/invoices/${id}/unpost`, 'POST', {});
        salesNotify(r.message || 'تم إلغاء ترحيل الفاتورة بنجاح', 'success');
        performSalesInvoiceSearch();

        if (currentSalesInvoiceId === id) {
            await loadSalesInvoice(id);
        }
    } catch (e) {
        salesNotify(e.message, 'danger');
    }
}

async function deleteSalesInvoice(id, number) {
    const label = number ? `رقم ${number}` : `#${id}`;
    const ok = await salesConfirm(`هل أنت متأكد من حذف الفاتورة ${label}؟\nلا يمكن التراجع عن هذه العملية.`, {
        title: 'حذف فاتورة البيع',
        okText: 'حذف',
        danger: true,
    });
    if (!ok) return;

    try {
        const r = await salesApiSend(`/operation/sales/invoices/${id}`, 'DELETE', {});
        salesNotify(r.message || 'تم حذف الفاتورة بنجاح', 'success');
        performSalesInvoiceSearch();

        if (currentSalesInvoiceId === id) {
            clearSalesForm();
        }
    } catch (e) {
        salesNotify(e.message, 'danger');
    }
}

/* =========================================================
   POST / UNPOST من النموذج
   ========================================================= */

async function postCurrentSalesInvoice() {
    if (!currentSalesInvoiceId) {
        salesNotify('لا توجد فاتورة للترحيل', 'warning');
        return;
    }

    const ok = await salesConfirm('هل تريد ترحيل هذه الفاتورة؟\nسيتم إنشاء القيود وحركة المخزون.', {
        title: 'ترحيل فاتورة البيع',
        okText: 'ترحيل',
    });
    if (!ok) return;

    try {
        const r = await salesApiSend(`/operation/sales/invoices/${currentSalesInvoiceId}/post`, 'POST', {});
        salesNotify(r.message || 'تم ترحيل الفاتورة بنجاح', 'success');
        await loadSalesInvoice(currentSalesInvoiceId);
    } catch (e) {
        salesNotify(e.message, 'danger');
    }
}

async function unpostCurrentSalesInvoice() {
    if (!currentSalesInvoiceId) {
        salesNotify('لا توجد فاتورة', 'warning');
        return;
    }

    const ok = await salesConfirm('هل تريد إلغاء ترحيل هذه الفاتورة؟\nسيتم حذف القيود وحركة المخزون.', {
        title: 'إلغاء ترحيل فاتورة البيع',
        okText: 'إلغاء الترحيل',
        danger: true,
    });
    if (!ok) return;

    try {
        const r = await salesApiSend(`/operation/sales/invoices/${currentSalesInvoiceId}/unpost`, 'POST', {});
        salesNotify(r.message || 'تم إلغاء ترحيل الفاتورة بنجاح', 'success');
        await loadSalesInvoice(currentSalesInvoiceId);
    } catch (e) {
        salesNotify(e.message, 'danger');
    }
}

/* =========================================================
   تحميل فاتورة
   ========================================================= */

async function loadSalesInvoice(id) {
    try {
        const res = await salesApiGet(`/operation/sales/invoices/${id}`);
        const { header: h, details } = salesExtractPayload(res);

        clearSalesForm();

        salesSetValue('SalesInvoiceNo', h.invoice_number ?? '');
        salesSetValue('SalesInvoiceDate', h.invoice_date ?? '');
        salesSetValue('customerID', h.account_id ?? '');
        salesSetValue('customerName', h.customer_name ?? '');
        salesSetValue('salesCoinsID', h.coin_id ?? '');
        salesSetValue('salesCurrencyName', h.coin_name ?? '');
        salesSetValue('SalesExchangeRate', h.exchange_rate ?? 1);
        salesSetValue('SalesPaymentMethod', SALES_PAYMENT_TO_STR[h.payment_method] || '');
        salesSetValue('salesPaymentAccountId', h.payment_account_id ?? '');
        salesSetValue('salesPaymentAccount', h.payment_account_name ?? '');
        salesSetValue('SalesStatement', h.statement ?? '');
        salesSetValue('SalesReference', h.reference ?? '');

        salesPaymentMethodChanged();

        const tbody = document.getElementById('salesInvoiceDetails');
        tbody.replaceChildren();

        const tpl = document.getElementById('salesRowTemplate');

        details.forEach((d, i) => {
            const row = tpl.content.firstElementChild.cloneNode(true);

            row.querySelector('.row-unit-id').value = d.unit_id ?? '';
            row.querySelector('.row-unit').value = d.unit_name ?? '';

            row.querySelector('.row-num').textContent = i + 1;

            const itemInput = row.querySelector('.row-item');
            itemInput.value = d.item_name ?? '';
            row.querySelector('.row-item-id').value = d.item_id ?? '';

            const typeInput = row.querySelector('.row-type');
            typeInput.value = d.type_name ?? '';
            row.querySelector('.row-type-id').value = d.type_id ?? '';

            row.querySelector('.row-code').value = d.code ?? '';

            const whInput = row.querySelector('.row-warehouse');
            whInput.value = d.warehouse_name ?? '';
            row.querySelector('.row-warehouse-id').value = d.warehouse_id ?? '';

            row.querySelector('.row-price').value = d.price;
            row.querySelector('.row-measure').value = d.quantity;
            row.querySelector('.row-discount').value = d.discount;
            row.querySelector('.row-total').value = Number(d.total).toFixed(2);
            row.querySelector('.row-cost-price').value = d.cost_price ?? 0;

            tbody.appendChild(row);
        });

        if (!details.length) setEmptySalesMessage();

        calculateSalesTotals();

        const modalEl = document.getElementById('salesInvoiceSearchModal');
        if (modalEl) {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }

        setTimeout(cleanupStuckModals, 300);

        currentSalesInvoiceId = h.sales_invoice_id;
        currentSalesInvoiceIsPosted = !!h.is_posted;
        currentSalesInvoiceHasReturns = !!h.has_returns;
        setSalesMode('view');
        salesEditSnapshot = null;

        applySalesExchangeRateState();

        salesNotify('تم تحميل الفاتورة بنجاح', 'success');
    } catch (e) {
        salesNotify('فشل تحميل الفاتورة: ' + e.message, 'danger');
    }
}

/* =========================================================
   Snapshot
   ========================================================= */

function takeSalesSnapshot() {
    const data = {
        invoice_date: salesGetValue('SalesInvoiceDate'),
        account_id: salesGetValue('customerID'),
        coin_id: salesGetValue('salesCoinsID'),
        exchange_rate: salesGetValue('SalesExchangeRate'),
        payment_method: salesGetValue('SalesPaymentMethod'),
        payment_account_id: salesGetValue('salesPaymentAccountId'),
        statement: salesGetValue('SalesStatement'),
        reference: salesGetValue('SalesReference'),
        details: Array.from(
            document.querySelectorAll('#salesInvoiceDetails .sales-detail-row')
        ).map(r => ({
            item: r.querySelector('.row-item-id')?.value || '',
            type: r.querySelector('.row-type-id')?.value || '',
            code: r.querySelector('.row-code')?.value || '',
            unit: r.querySelector('.row-unit-id')?.value || '',
            warehouse: r.querySelector('.row-warehouse-id')?.value || '',
            price: r.querySelector('.row-price')?.value || '',
            measure: r.querySelector('.row-measure')?.value || '',
            discount: r.querySelector('.row-discount')?.value || '',
        })),
    };
    return JSON.stringify(data);
}

/* =========================================================
   Save / Edit / Cancel
   ========================================================= */

function hasSalesInvoiceData() {
    const el = document.getElementById('SalesInvoiceNo');
    return el ? el.value.trim() !== '' : false;
}

function editSalesInvoice() {
    if (!hasSalesInvoiceData() || !currentSalesInvoiceId) {
        salesNotify('لا توجد فاتورة للتعديل', 'warning');
        return;
    }

    if (currentSalesInvoiceHasReturns) {
        salesNotify('لا يمكن تعديل فاتورة لها مرتجعات. احذف المرتجعات أولًا.', 'warning');
        return;
    }

    setSalesMode('edit');
    document.querySelectorAll('#salesInvoiceDetails .sales-detail-row')
        .forEach(r => enableSalesRow(r));
    document.querySelectorAll(
        '#SalesInvoiceDate, #SalesPaymentMethod, #salesPaymentAccount, #customerName, ' +
        '#SalesStatement, #SalesReference'
    ).forEach(el => el.disabled = false);

    salesPaymentMethodChanged();
    applySalesExchangeRateState();
    salesEditSnapshot = takeSalesSnapshot();
}

async function cancelSalesInvoice() {
    const ok = await salesConfirm('هل أنت متأكد من إلغاء العملية؟', {
        title: 'إلغاء العملية',
        okText: 'تأكيد الإلغاء',
        danger: true,
    });
    if (!ok) return;
    clearSalesForm();
    salesNotify('تم إلغاء العملية', 'info');
}

async function saveSalesInvoice() {
    if (isSavingSales) return;

    const number = salesGetValue('SalesInvoiceNo').trim();
    if (!number) return salesNotify('رقم الفاتورة مطلوب', 'warning');

    const customerId = salesGetValue('customerID');
    if (!customerId) return salesNotify('يجب اختيار العميل', 'warning');

    const coinId = salesGetValue('salesCoinsID');
    if (!coinId) return salesNotify('يجب اختيار العملة', 'warning');

    const methodStr = salesGetValue('SalesPaymentMethod');
    if (!methodStr) return salesNotify('يجب اختيار طريقة الدفع', 'warning');

    const methodInt = SALES_PAYMENT_TO_INT[methodStr];
    if (!methodInt) return salesNotify('طريقة الدفع غير صالحة', 'danger');

    if (methodInt !== 1 && !salesGetValue('salesPaymentAccountId')) {
        return salesNotify('يجب اختيار حساب الدفع', 'warning');
    }

    const rows = document.querySelectorAll('#salesInvoiceDetails .sales-detail-row');
    if (!rows.length) return salesNotify('أضف صنفًا واحدًا على الأقل', 'warning');

    const details = [];
    for (const row of rows) {
        const itemId = row.querySelector('.row-item-id')?.value;
        if (!itemId) return salesNotify('يجب اختيار الصنف في كل الصفوف', 'warning');

        const warehouseId = row.querySelector('.row-warehouse-id')?.value;
        if (!warehouseId) return salesNotify('يجب اختيار المخزن في كل الصفوف', 'warning');

        const unitId = row.querySelector('.row-unit-id')?.value || null;

        const qty = parseFloat(row.querySelector('.row-measure')?.value) || 0;
        if (qty <= 0) return salesNotify('الكمية يجب أن تكون أكبر من صفر', 'warning');

        const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
        if (price <= 0) return salesNotify('سعر الوحدة يجب أن يكون أكبر من صفر', 'warning');

        const discount = parseFloat(row.querySelector('.row-discount')?.value) || 0;
        if (discount > qty * price) {
            return salesNotify('الخصم لا يمكن أن يتجاوز قيمة الصف', 'warning');
        }

        details.push({
            item_id: Number(itemId),
            type_id: row.querySelector('.row-type-id')?.value
                ? Number(row.querySelector('.row-type-id').value) : null,
            unit_id: unitId ? Number(unitId) : null,
            warehouse_id: Number(warehouseId),
            code: row.querySelector('.row-code')?.value || null,
            quantity: qty,
            price: price,
            cost_price: parseFloat(row.querySelector('.row-cost-price')?.value) || 0,
            discount: discount,
        });
    }

    if (salesMode === 'edit' && salesEditSnapshot) {
        if (takeSalesSnapshot() === salesEditSnapshot) {
            salesNotify('لم يتم إجراء أي تعديل على الفاتورة', 'info');
            return;
        }
    }

    const payload = {
        invoice_number: number,
        invoice_date: salesGetValue('SalesInvoiceDate'),
        account_id: Number(customerId),
        payment_method: methodInt,
        payment_account_id: salesGetValue('salesPaymentAccountId') || null,
        coin_id: Number(coinId),
        exchange_rate: parseFloat(salesGetValue('SalesExchangeRate')) || 1,
        statement: salesGetValue('SalesStatement') || null,
        reference: salesGetValue('SalesReference') || null,
        details,
    };

    isSavingSales = true;
    const saveBtn = document.getElementById('btnSaveSalesInvoice');
    const saveNewBtn = document.getElementById('btnSaveAndNewSalesInvoice');
    if (saveBtn) saveBtn.disabled = true;
    if (saveNewBtn) saveNewBtn.disabled = true;

    try {
        let r;
        if (salesMode === 'edit' && currentSalesInvoiceId) {
            r = await salesApiSend(`/operation/sales/invoices/${currentSalesInvoiceId}`, 'PUT', payload);
            currentSalesInvoiceIsPosted = false;
        } else {
            r = await salesApiSend('/operation/sales/invoices', 'POST', payload);
            currentSalesInvoiceId = r.sales_invoice_id;
            currentSalesInvoiceIsPosted = !!r.is_posted;
            currentSalesInvoiceHasReturns = false;
            if (r.invoice_number) salesSetValue('SalesInvoiceNo', r.invoice_number);
        }
        salesNotify(r.message || 'تم الحفظ بنجاح', 'success');
        setSalesMode('view');
        salesEditSnapshot = null;
    } catch (e) {
        let m = e.message;
        if (e.errors) m += ' — ' + Object.values(e.errors).flat().join(' | ');
        salesNotify(m, 'danger');
    } finally {
        isSavingSales = false;
        if (salesMode !== 'view') {
            if (saveBtn) saveBtn.disabled = false;
            if (saveNewBtn) saveNewBtn.disabled = false;
        }
    }
}

async function saveAndNewSalesInvoice() {
    await saveSalesInvoice();
    if (salesMode === 'view') await resetSalesInvoice();
}

function printSalesInvoice() {
    if (!hasSalesInvoiceData() || !currentSalesInvoiceId) {
        salesNotify('يجب حفظ الفاتورة أولاً قبل الطباعة', 'warning');
        return;
    }

    window.open(
        `/operation/sales/invoices/${currentSalesInvoiceId}/print`,
        '_blank',
        'width=900,height=700'
    );
}

/* =========================================================================
   Modal: الأرصدة والتسعير
   ========================================================================= */

function openStockBalancesModal(input) {
    if (salesMode === 'view') return;
    if (!stockBalancesModalInstance) {
        salesNotify('نافذة الأرصدة غير جاهزة', 'danger');
        return;
    }

    activeStockBalanceInput = input;
    activeStockBalanceRow = input.closest('tr');

    const itemName = activeStockBalanceRow?.querySelector('.row-item')?.value || '';
    const searchInput = document.getElementById('stockBalancesSearch');
    if (searchInput) searchInput.value = itemName;

    stockBalancesModalInstance.show();

    if (stockBalancesCache.length === 0) {
        fetchStockBalances();
    } else {
        filterStockBalances();
    }
}

async function fetchStockBalances() {
    const tbody = document.getElementById('stockBalancesBody');
    if (tbody) {
        tbody.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 11;
        td.className = 'text-center text-muted py-4';
        td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    try {
        const res = await salesApiGet('/operation/movements/helpers/stock-balances');
        stockBalancesCache = Array.isArray(res.data) ? res.data : [];
        filterStockBalances();
    } catch (e) {
        console.error(e);
        salesNotify('فشل تحميل الأرصدة', 'danger');
    }
}

function filterStockBalances() {
    const search = (document.getElementById('stockBalancesSearch')?.value || '').trim().toLowerCase();

    if (!search) {
        renderStockBalances(stockBalancesCache);
        return;
    }

    const filtered = stockBalancesCache.filter(row => {
        return (
            (row.item_name || '').toLowerCase().includes(search) ||
            (row.type_name || '').toLowerCase().includes(search) ||
            (row.code || '').toLowerCase().includes(search) ||
            (row.warehouse_name || '').toLowerCase().includes(search) ||
            (row.unit_name || '').toLowerCase().includes(search)
        );
    });

    renderStockBalances(filtered);
}

function renderStockBalances(rows) {
    const tbody = document.getElementById('stockBalancesBody');
    if (!tbody) return;

    tbody.replaceChildren();

    if (!rows.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 11;
        td.className = 'text-center text-muted py-4';
        td.textContent = 'لا توجد أرصدة متاحة';
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    /* ✅ مفتاح مركّب: الصنف + النوع + المخزن + الوحدة
       (لا يُعطَّل كل صنف بنفس item_id — فقط الدفعة المختارة تحديداً) */
    const balanceKey = (r) => [
        r.item_id ?? '',
        r.type_id ?? '',
        r.warehouse_id ?? '',
        r.unit_id ?? '',
    ].join('|');

    const usedKeys = new Set();
    document.querySelectorAll('#salesInvoiceDetails .sales-detail-row').forEach(r => {
        if (r === activeStockBalanceRow) return;
        const id = r.querySelector('.row-item-id')?.value;
        if (!id) return;
        usedKeys.add([
            id,
            r.querySelector('.row-type-id')?.value ?? '',
            r.querySelector('.row-warehouse-id')?.value ?? '',
            r.querySelector('.row-unit-id')?.value ?? '',
        ].join('|'));
    });

    const fragment = document.createDocumentFragment();

    rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.className = 'text-center';

        const isUsed = usedKeys.has(balanceKey(row));

        if (isUsed) {
            tr.classList.add('opacity-50', 'bg-light');
        }

        const cells = [
            { text: row.item_name || '—', className: 'text-start' },
            { text: row.type_name || '—' },
            { text: row.code || '—' },
            { text: row.warehouse_name || '—' },
            { text: row.unit_name || '—' },
            { text: salesFormatMoney(row.quantity), className: 'fw-bold text-success' },
            { text: salesFormatMoney(row.unit_cost) },
            { text: salesFormatMoney(row.sale_price) },
            { text: salesFormatMoney(row.min_price) },
            { text: salesFormatMoney(row.max_price) },
        ];

        cells.forEach(c => {
            const td = document.createElement('td');
            td.textContent = c.text;
            if (c.className) td.className = c.className;
            tr.appendChild(td);
        });

        const tdActions = document.createElement('td');
        const btnSelect = document.createElement('button');
        btnSelect.type = 'button';
        btnSelect.className = 'btn btn-sm btn-primary';
        btnSelect.innerHTML = '<i class="bi bi-check-lg"></i> اختيار';

        if (isUsed) {
            btnSelect.disabled = true;
            btnSelect.classList.add('disabled');
            btnSelect.title = 'هذه الدفعة (الصنف/النوع/المخزن/الوحدة) مضافة بالفعل في صف آخر';
        } else {
            btnSelect.addEventListener('click', () => selectStockBalance(row));
        }

        tdActions.appendChild(btnSelect);
        tr.appendChild(tdActions);

        fragment.appendChild(tr);
    });

    tbody.appendChild(fragment);
}

function selectStockBalance(row) {
    if (!activeStockBalanceRow) return;

    activeStockBalanceRow.querySelector('.row-item-id').value = row.item_id ?? '';
    activeStockBalanceRow.querySelector('.row-item').value = row.item_name ?? '';

    activeStockBalanceRow.querySelector('.row-type-id').value = row.type_id ?? '';
    activeStockBalanceRow.querySelector('.row-type').value = row.type_name ?? '';

    activeStockBalanceRow.querySelector('.row-code').value = row.code ?? '';

    activeStockBalanceRow.querySelector('.row-unit-id').value = row.unit_id ?? '';
    activeStockBalanceRow.querySelector('.row-unit').value = row.unit_name ?? '';

    activeStockBalanceRow.querySelector('.row-warehouse-id').value = row.warehouse_id ?? '';
    activeStockBalanceRow.querySelector('.row-warehouse').value = row.warehouse_name ?? '';

    /* ✅ التحويل للعرض فقط: الأرصدة محفوظة بالعملة الأساسية، وإدخال الفاتورة بعملتها.
       price (المحفوظ في السطر) يجب أن يكون بعملة الفاتورة → sale_price ÷ rate.
       cost_price يبقى بالعملة الأساسية (يستخدمه الخادم لحساب التكلفة). */
    const rate = parseFloat(document.getElementById('SalesExchangeRate')?.value) || 1;
    const safeRate = rate > 0 ? rate : 1;

    const priceInInvoiceCurrency = (Number(row.sale_price) || 0) / safeRate;

    const priceEl = activeStockBalanceRow.querySelector('.row-price');
    priceEl.value = window.AppFormat.amount(priceInInvoiceCurrency);
    priceEl.dataset.rawValue = String(priceInInvoiceCurrency);

    /* ✅ وسم «معدّل يدويًا» يُلغى لأن السعر جاء من الأرصدة لا من المستخدم */
    delete priceEl.dataset.manualPrice;

    activeStockBalanceRow.querySelector('.row-cost-price').value = row.unit_cost || 0;
    activeStockBalanceRow.querySelector('.row-measure').value = 1;

    calculateSalesRow(activeStockBalanceRow.querySelector('.row-measure'));

    /* ✅ الإجمالي بالعملة الأساسية: net × سعر الصرف (المحفوظ بالعملة الأساسية) */
    const net = (Number(priceInInvoiceCurrency) || 0) * 1;
    const baseTotal = net * safeRate;
    const baseEl = document.getElementById('salesInvoiceTotalBaseDisplay');
    if (baseEl) baseEl.textContent = window.AppFormat.amount(baseTotal);

    stockBalancesModalInstance?.hide();

    setTimeout(() => {
        activeStockBalanceRow?.querySelector('.row-measure')?.focus();
    }, 300);
}

/* =========================================================
   تصدير للـ HTML
   ========================================================= */

Object.assign(window, {
    resetSalesInvoice,
    addSalesRow,
    removeSalesRow,
    calculateSalesRow,
    salesExchangeRateChanged,
    salesPaymentMethodChanged,
    searchSalesInvoice,
    performSalesInvoiceSearch,
    debouncedSalesInvoiceSearch,
    clearSalesInvoiceSearch,
    loadSalesInvoice,
    editSalesInvoice,
    cancelSalesInvoice,
    saveSalesInvoice,
    saveAndNewSalesInvoice,
    printSalesInvoice,

    postSalesInvoice,
    unpostSalesInvoice,
    deleteSalesInvoice,
    postCurrentSalesInvoice,
    unpostCurrentSalesInvoice,
    updateSalesPostingButtons,
    updateSalesSearchButton,
    applySalesExchangeRateState,
    cleanupStuckModals,

    openStockBalancesModal,
    fetchStockBalances,
    filterStockBalances,
    renderStockBalances,
    selectStockBalance,

    findDefaultPieceUnit,
});