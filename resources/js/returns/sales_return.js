/* =========================================================================
   مرتجع المبيعات — إدارة مرتجعات البيع
   ========================================================================= */

/* =========================
   ثوابت
   ========================= */

/** تسميات طرق الدفع */
const SALES_RETURN_PAYMENT_LABELS = { 1: 'أجل', 2: 'نقدي', 3: 'بنك' };

/** خرائط التحويل (للتوافق مع أي كود خارجي) */
const SALES_RETURN_PAYMENT_TO_INT = { credit: 1, cash: 2, bank: 3 };
const SALES_RETURN_PAYMENT_TO_STR = { 1: 'credit', 2: 'cash', 3: 'bank' };

/** الوضعيات */
const SALES_RETURN_MODES = Object.freeze({ VIEW: 'view', ADD: 'add', EDIT: 'edit' });

/** تنسيق المبالغ بفواصل الآلاف (موحّد عبر Utils) */
function salesReturnFormatMoney(v) {
    if (window.Utils && typeof Utils.formatAmount === 'function') {
        return Utils.formatAmount(v);
    }
    return Number(v || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/** حقول الصف التي تبقى للقراءة فقط دائماً */
const SALES_RETURN_READONLY_ROW_CLASSES = [
    'row-num', 'row-total', 'row-available', 'row-discount',
    'row-price', 'row-item', 'row-type', 'row-code',
    'row-unit', 'row-warehouse',
];

/* =========================
   الحالة العامة
   ========================= */

let salesReturnMode = SALES_RETURN_MODES.VIEW;
let currentSalesReturnId = null;
let currentSalesReturnIsPosted = false;
let isSavingSalesReturn = false;

let originalInvoiceSearchTimer = null;
let salesReturnSearchTimer = null;

const SALES_RETURN_CSRF =
    document.querySelector('meta[name="csrf-token"]')?.content || '';

/* =========================================================================
   طبقة API
   ========================================================================= */

function salesReturnApiHeaders(json = false) {
    const h = { Accept: 'application/json', 'X-CSRF-TOKEN': SALES_RETURN_CSRF };
    if (json) h['Content-Type'] = 'application/json';
    return h;
}

/**
 * طلب GET موحّد.
 * @param {string} url
 * @returns {Promise<any>}
 */
async function salesReturnApiGet(url) {
    const r = await fetch(url, { headers: salesReturnApiHeaders() });
    if (!r.ok) throw new Error(`فشل الطلب: ${r.status}`);
    return r.json();
}

/**
 * طلب JSON موحّد (POST/PUT/DELETE).
 * @param {string} url
 * @param {string} method
 * @param {object} body
 */
async function salesReturnApiSend(url, method, body) {
    const r = await fetch(url, {
        method,
        headers: salesReturnApiHeaders(true),
        body: JSON.stringify(body ?? {}),
    });
    const json = await r.json().catch(() => ({}));
    if (!r.ok) {
        let message = json.message || 'فشل الطلب';
        if (json.errors) message = Object.values(json.errors).flat().join(' | ');
        else if (json.error) message = json.error;

        const err = new Error(message);
        err.errors = json.errors;
        err.raw = json;
        throw err;
    }
    return json;
}

/* =========================================================================
   أدوات مساعدة
   ========================================================================= */

/**
 * يعرض إشعاراً للمستخدم (Toast مخصص إن توفّر، وإلا console).
 * @param {string} message
 * @param {'info'|'success'|'warning'|'danger'} type
 */
function salesReturnNotify(message, type = 'info') {
    if (typeof window.showSystemToast === 'function') {
        window.showSystemToast(message, type);
        return;
    }
    const fn = type === 'danger' ? console.error : console.warn;
    fn(`[SalesReturn:${type}] ${message}`);
}

/** قراءة قيمة عنصر بأمان */
function salesReturnGetValue(id) {
    const el = document.getElementById(id);
    return el ? (el.value || '') : '';
}

/** تعيين قيمة عنصر بأمان */
function salesReturnSetValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value ?? '';
}

/** قراءة رقم بأمان مع حد أدنى اختياري */
function salesReturnReadNumber(id, fallback = 0, min = null) {
    const raw = salesReturnGetValue(id);
    const n = parseFloat(raw);
    if (!Number.isFinite(n)) return fallback;
    if (min !== null && n < min) return fallback;
    return n;
}

/** استخراج مصفوفة من استجابة API مهما كان شكلها */
function salesReturnExtractArray(raw) {
    if (Array.isArray(raw)) return raw;
    if (Array.isArray(raw?.data)) return raw.data;
    return [];
}

/** استخراج { header, details } من استجابة API */
function salesReturnExtractPayload(res) {
    if (res?.header) return { header: res.header, details: res.details || [] };
    if (res?.data?.header) return { header: res.data.header, details: res.data.details || [] };
    return { header: {}, details: [] };
}

/* =========================================================================
   غلاف مودال التأكيد العام
   ========================================================================= */

/**
 * غلاف بسيط لـ window.appConfirm مع fallback آمن.
 * @param {string} message
 * @param {{title?:string, okText?:string, cancelText?:string, danger?:boolean}} [opts]
 * @returns {Promise<boolean>}
 */
function salesReturnConfirm(message, opts = {}) {
    if (typeof window.appConfirm === 'function') {
        return window.appConfirm(message, opts);
    }
    return Promise.resolve(window.confirm(message));
}

/* =========================================================================
   تنظيف المودالات
   ========================================================================= */

/**
 * تنظيف آمن: لا يلمس أي مودال ظاهر حالياً حتى لا يكسر Bootstrap.
 */
function cleanupStuckModals() {
    if (document.querySelectorAll('.modal.show').length > 0) return;

    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');

    document.querySelectorAll('.modal.show').forEach(modalEl => {
        modalEl.classList.remove('show');
        modalEl.style.removeProperty('display');
        modalEl.setAttribute('aria-hidden', 'true');
    });

    document.querySelectorAll('.offcanvas-backdrop').forEach(el => el.remove());
}

/* =========================================================================
   التهيئة
   ========================================================================= */

document.addEventListener('DOMContentLoaded', () => {
    cleanupStuckModals();
    setSalesReturnMode(SALES_RETURN_MODES.VIEW);
    clearSalesReturnForm();
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        cleanupStuckModals();
    }
});

/* =========================================================================
   إدارة الوضعية
   ========================================================================= */

/**
 * يضبط وضعية الشاشة (view/add/edit) ويعطّل/يفعّل الأزرار والحقول.
 * @param {'view'|'add'|'edit'} mode
 */
function setSalesReturnMode(mode) {
    salesReturnMode = mode;

    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach(row => enableSalesReturnRow(row));

    const newBtn = document.getElementById('btnNewSalesReturn');
    const saveBtn = document.getElementById('btnSaveSalesReturn');
    const saveNewBtn = document.getElementById('btnSaveAndNewSalesReturn');
    const cancelBtn = document.getElementById('btnCancelSalesReturn');

    const isView = mode === SALES_RETURN_MODES.VIEW;

    if (newBtn) newBtn.disabled = !isView;
    if (saveBtn) saveBtn.disabled = isView;
    if (saveNewBtn) saveNewBtn.disabled = isView;
    if (cancelBtn) cancelBtn.classList.toggle('d-none', isView);

    const invoiceWrapper = document.getElementById('originalSalesInvoiceWrapper');
    if (invoiceWrapper) invoiceWrapper.style.cursor = isView ? 'default' : 'pointer';

    updateSalesReturnSearchButton();
    updateSalesReturnPostingButtons();
    applySalesReturnExchangeRateState();
}

/**
 * ✅ سعر صرف المرتجع الخاص به — مثل فاتورة البيع:
 *   - عملة النظام → readonly = 1
 *   - عملة أجنبية → قابل للتعديل (إلا في وضع العرض)
 */
function applySalesReturnExchangeRateState() {
    const rateEl = document.getElementById('SalesReturnExchangeRate');
    if (!rateEl) return;

    const sysId = window.SALES_SYSTEM_CURRENCY_ID ?? null;
    const coinId = salesReturnGetValue('salesReturnCoinsID');
    const isSys = sysId !== null && coinId !== '' && String(coinId) === String(sysId);
    const isView = salesReturnMode === SALES_RETURN_MODES.VIEW;

    if (isSys) {
        rateEl.value = '1';
    }

    if (isSys || isView) {
        rateEl.readOnly = true;
        rateEl.tabIndex = -1;
        rateEl.classList.add('bg-light');
        rateEl.title = isSys ? 'سعر صرف عملة النظام ثابت = 1' : 'وضع العرض — لا يمكن التعديل';
    } else {
        rateEl.readOnly = false;
        rateEl.tabIndex = 0;
        rateEl.classList.remove('bg-light');
        rateEl.title = 'سعر صرف هذا المرتجع (يمكن تجاوزه عن السعر اليومي)';
    }
}

function updateSalesReturnSearchButton() {
    const searchBtn = document.getElementById('btnSearchSalesReturn');
    if (!searchBtn) return;

    const hasReturn = !!currentSalesReturnId;
    const isViewMode = salesReturnMode === SALES_RETURN_MODES.VIEW;
    searchBtn.disabled = !isViewMode || hasReturn;
}

function updateSalesReturnPostingButtons() {
    const editBtn   = document.getElementById('btnEditSalesReturn');
    const postBtn   = document.getElementById('btnPostSalesReturn');
    const unpostBtn = document.getElementById('btnUnpostSalesReturn');
    const printBtn  = document.getElementById('btnPrintSalesReturn');

    const hasData  = hasSalesReturnData();
    const isPosted = currentSalesReturnIsPosted;
    const isView   = salesReturnMode === SALES_RETURN_MODES.VIEW;

    if (editBtn) {
        editBtn.disabled = !hasData || isPosted || !isView;
        editBtn.classList.toggle('d-none', isPosted);
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

    if (printBtn) printBtn.disabled = !hasData;
}

/* =========================================================================
   نموذج المرتجع
   ========================================================================= */

/**
 * بدء مرتجع جديد: يفتح نافذة اختيار الفاتورة فوراً، ثم يجلب الرقم بالخلفية.
 */
async function resetSalesReturn() {
    cleanupStuckModals();

    clearSalesReturnForm();
    setSalesReturnMode(SALES_RETURN_MODES.ADD);
    salesReturnSetValue('SalesReturnDate', new Date().toISOString().split('T')[0]);

    // الإجراء الأساسي: فتح نافذة اختيار الفاتورة
    searchOriginalSalesInvoice();

    // جلب الرقم التالي بالخلفية
    try {
        const res = await salesReturnApiGet('/operation/sales/returns/next-number');
        salesReturnSetValue('SalesReturnNo', res.next_number || '');
    } catch (e) {
        console.error('[SalesReturn] next-number failed', e);
        salesReturnNotify('تعذّر جلب رقم المرتجع التالي', 'danger');
    }
}

/** تفريغ كل حقول النموذج وإعادة الحالة */
function clearSalesReturnForm() {
    [
        'SalesReturnNo', 'SalesReturnDate', 'originalSalesInvoiceNo',
        'SalesReturnPaymentMethod', 'SalesReturnPaymentMethodValue',
        'salesReturnPaymentAccount', 'salesReturnCustomerName',
        'salesReturnCurrencyName', 'salesReturnWarehouseName',
        'SalesReturnExchangeRate', 'SalesReturnStatement', 'SalesReturnReference',
        'originalSalesInvoiceId', 'salesReturnCustomerID', 'salesReturnCoinsID',
        'salesReturnWarehouseID', 'salesReturnPaymentAccountId',
    ].forEach(id => salesReturnSetValue(id, ''));

    setEmptySalesReturnMessage();

    const discEl = document.getElementById('totalSalesReturnDiscountDisplay');
    if (discEl) discEl.textContent = '0.00';

    const totalEl = document.getElementById('salesReturnTotalDisplay');
    if (totalEl) totalEl.textContent = '0.00';

    hideSalesReturnPaymentAccounts();

    currentSalesReturnId = null;
    currentSalesReturnIsPosted = false;

    setSalesReturnMode(SALES_RETURN_MODES.VIEW);
}

/** يستنتج عدد الأعمدة من thead أو يستخدم قيمة افتراضية */
function salesReturnDetailsColSpan() {
    const thead = document.querySelector('#salesReturnDetailsTable thead tr');
    if (thead) return thead.children.length || 12;
    return 12;
}

/** رسالة "لا توجد أصناف" */
function setEmptySalesReturnMessage() {
    const tbody = document.getElementById('salesReturnDetails');
    if (!tbody) return;

    tbody.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = salesReturnDetailsColSpan();
    td.className = 'text-center text-muted py-4';
    td.textContent = 'لا توجد أصناف. اضغط "مرتجع جديد" للبدء.';
    tr.appendChild(td);
    tbody.appendChild(tr);
}

/* =========================================================================
   صفوف التفاصيل
   ========================================================================= */

/**
 * تفعيل/تعطيل حقول الصف حسب الوضعية.
 * @param {HTMLTableRowElement} row
 */
function enableSalesReturnRow(row) {
    if (!row) return;
    const isView = salesReturnMode === SALES_RETURN_MODES.VIEW;

    row.querySelectorAll('input, select, button').forEach(el => {
        if (el.type === 'hidden') return;
        if (SALES_RETURN_READONLY_ROW_CLASSES.some(c => el.classList.contains(c))) return;
        el.disabled = isView;
    });
}

/**
 * بناء صف تفاصيل موحّد (يُستخدم في التحميل من فاتورة أو من مرتجع محفوظ).
 * @param {object} args
 * @param {object} args.detail بيانات البند
 * @param {number} args.availableQty الكمية المتاحة للإرجاع
 * @param {number} args.index ترتيب الصف
 * @param {'invoice'|'return'} args.source مصدر البيانات
 * @returns {HTMLTableRowElement}
 */
function buildSalesReturnRow({ detail, availableQty, index, source }) {
    const tpl = document.getElementById('salesReturnRowTemplate');
    const row = tpl.content.firstElementChild.cloneNode(true);

    const setVal = (sel, v) => {
        const el = row.querySelector(sel);
        if (el) el.value = v ?? '';
    };

    setVal('.row-detail-id', detail.sales_invoice_detail_id);
    row.querySelector('.row-num').textContent = index + 1;

    setVal('.row-item', detail.item_name);
    setVal('.row-item-id', detail.item_id);
    setVal('.row-type', detail.type_name);
    setVal('.row-type-id', detail.type_id);
    setVal('.row-code', detail.code);
    setVal('.row-unit', detail.unit_name);
    setVal('.row-unit-id', detail.unit_id);
    setVal('.row-warehouse', detail.warehouse_name);
    setVal('.row-warehouse-id', detail.warehouse_id);

    const price = parseFloat(detail.price) || 0;
    setVal('.row-price', price);
    setVal('.row-cost-price', detail.cost_price ?? price);
    setVal('.row-available', availableQty);

    // الكميات الأصلية: من الفاتورة الأصلية أو من وسم واضح في بيانات المرتجع
    const originalQty =
        parseFloat(detail.original_quantity ?? detail.original_qty ?? detail.invoice_quantity) ||
        (source === 'invoice' ? parseFloat(detail.quantity) || 0 : 0);

    const originalDiscount =
        parseFloat(detail.original_discount ?? detail.invoice_discount) ||
        (source === 'invoice' ? parseFloat(detail.discount) || 0 : 0);

    setVal('.row-original-qty', originalQty);
    setVal('.row-original-discount', originalDiscount);

    // الكمية الافتراضية
    const defaultQty =
        source === 'invoice'
            ? availableQty
            : (parseFloat(detail.quantity) || 0);

    setVal('.row-measure', defaultQty);

    // الخصم النسبي
    let rowDiscount;
    if (source === 'invoice') {
        rowDiscount = originalQty > 0
            ? (defaultQty / originalQty) * originalDiscount
            : 0;
    } else {
        rowDiscount = parseFloat(detail.discount) || 0;
    }
    setVal('.row-discount', rowDiscount.toFixed(2));

    const rowTotal =
        source === 'invoice'
            ? Math.max(0, defaultQty * price - rowDiscount)
            : (parseFloat(detail.total) || Math.max(0, defaultQty * price - rowDiscount));

    setVal('.row-total', rowTotal.toFixed(2));

    return row;
}

function renumberSalesReturnRows() {
    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach((r, i) => {
            const numEl = r.querySelector('.row-num');
            if (numEl) numEl.textContent = i + 1;
        });
}

/**
 * حذف صف تفاصيل مع تأكيد.
 * @param {HTMLButtonElement} btn
 */
async function removeSalesReturnRow(btn) {
    if (salesReturnMode === SALES_RETURN_MODES.VIEW) return;

    const row = btn.closest('tr');
    if (!row) return;

    const ok = await salesReturnConfirm('هل تريد حذف هذا الصنف من المرتجع؟', {
        title: 'حذف صنف',
        okText: 'حذف',
        danger: true,
    });
    if (!ok) return;

    row.remove();
    renumberSalesReturnRows();
    calculateSalesReturnTotals();

    const tbody = document.getElementById('salesReturnDetails');
    if (tbody && !tbody.querySelector('.return-detail-row')) {
        setEmptySalesReturnMessage();
    }
}

/* =========================================================================
   الحسابات
   ========================================================================= */

/** إعادة حساب صف واحد بناءً على الكمية المُدخَلة */
function calculateSalesReturnRow(input) {
    const row = input.closest('tr');
    if (!row) return;

    const availableEl = row.querySelector('.row-available');
    const measureEl   = row.querySelector('.row-measure');
    const priceEl     = row.querySelector('.row-price');
    const origQtyEl   = row.querySelector('.row-original-qty');
    const origDiscEl  = row.querySelector('.row-original-discount');

    const available = parseFloat(availableEl?.value) || 0;
    let qty = parseFloat(measureEl?.value) || 0;
    const price = parseFloat(priceEl?.value) || 0;
    const originalQty = parseFloat(origQtyEl?.value) || 0;
    const originalDiscount = parseFloat(origDiscEl?.value) || 0;

    if (qty < 0) qty = 0;

    if (available > 0 && qty > available) {
        salesReturnNotify(
            `الكمية المراد إرجاعها (${qty}) أكبر من الكمية المتاحة (${available})`,
            'warning'
        );
        if (measureEl) measureEl.value = available;
        qty = available;
    }

    const discount = originalQty > 0
        ? (qty / originalQty) * originalDiscount
        : 0;

    const discountEl = row.querySelector('.row-discount');
    if (discountEl) discountEl.value = discount.toFixed(2);

    const totalEl = row.querySelector('.row-total');
    if (totalEl) totalEl.value = Math.max(0, qty * price - discount).toFixed(2);

    calculateSalesReturnTotals();
}

/** إعادة حساب الإجماليات */
function calculateSalesReturnTotals() {
    let itemsTotal = 0;
    let discountTotal = 0;

    document.querySelectorAll('#salesReturnDetails .return-detail-row').forEach(row => {
        const q = parseFloat(row.querySelector('.row-measure')?.value) || 0;
        const p = parseFloat(row.querySelector('.row-price')?.value) || 0;
        const d = parseFloat(row.querySelector('.row-discount')?.value) || 0;
        itemsTotal += q * p;
        discountTotal += d;
    });

    const exchangeRate = salesReturnReadNumber('SalesReturnExchangeRate', 1, 0) || 1;

    const net = Math.max(0, itemsTotal - discountTotal);
    const total = net * exchangeRate;

    const discEl = document.getElementById('totalSalesReturnDiscountDisplay');
    if (discEl) discEl.textContent = salesReturnFormatMoney(discountTotal);

    const totalEl = document.getElementById('salesReturnTotalDisplay');
    if (totalEl) totalEl.textContent = salesReturnFormatMoney(total);
}

/* =========================================================================
   طريقة الدفع
   ========================================================================= */

function salesReturnPaymentMethodChanged() {
    const methodValue = parseInt(salesReturnGetValue('SalesReturnPaymentMethodValue'), 10) || 0;
    const displayEl = document.getElementById('SalesReturnPaymentMethod');
    const container = document.getElementById('salesReturnPaymentAccountContainer');
    const label = document.getElementById('salesReturnPaymentAccountLabel');

    if (displayEl) displayEl.value = SALES_RETURN_PAYMENT_LABELS[methodValue] || '';

    if (!container) return;

    if (methodValue === 2 || methodValue === 3) {
        container.classList.remove('d-none');
        if (label) label.textContent = methodValue === 3 ? 'الحساب البنكي' : 'الصندوق';
    } else {
        container.classList.add('d-none');
        salesReturnSetValue('salesReturnPaymentAccount', '');
        salesReturnSetValue('salesReturnPaymentAccountId', '');
    }
}

function hideSalesReturnPaymentAccounts() {
    document.getElementById('salesReturnPaymentAccountContainer')?.classList.add('d-none');
}

/* =========================================================================
   البحث عن فاتورة البيع الأصلية
   ========================================================================= */

function onOriginalInvoiceFieldClick() {
    if (salesReturnMode === SALES_RETURN_MODES.VIEW) return;
    searchOriginalSalesInvoice();
}

function searchOriginalSalesInvoice() {
    const modalEl = document.getElementById('originalSalesInvoiceSearchModal');
    if (!modalEl) {
        console.error('[SalesReturn] modal #originalSalesInvoiceSearchModal missing');
        salesReturnNotify('لم يتم العثور على نافذة البحث عن الفاتورة', 'danger');
        return;
    }

    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        console.error('[SalesReturn] Bootstrap not loaded');
        salesReturnNotify('مكتبة Bootstrap غير محمّلة', 'danger');
        return;
    }

    const searchInput = document.getElementById('originalSalesInvoiceSearchInput');
    if (searchInput) searchInput.value = '';

    const tb = document.getElementById('originalSalesInvoiceSearchResults');
    if (tb) {
        tb.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 7;
        td.className = 'text-center text-muted py-4';
        td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
        tr.appendChild(td);
        tb.appendChild(tr);
    }

    try {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } catch (err) {
        console.error('[SalesReturn] modal show failed', err);
        salesReturnNotify('تعذّر فتح نافذة البحث', 'danger');
        return;
    }

    setTimeout(() => {
        searchInput?.focus();
        performOriginalSalesInvoiceSearch();
    }, 300);
}

function debouncedOriginalSalesInvoiceSearch() {
    clearTimeout(originalInvoiceSearchTimer);
    originalInvoiceSearchTimer = setTimeout(performOriginalSalesInvoiceSearch, 350);
}

function clearOriginalSalesInvoiceSearch() {
    const searchInput = document.getElementById('originalSalesInvoiceSearchInput');
    if (searchInput) searchInput.value = '';

    performOriginalSalesInvoiceSearch();
    setTimeout(() => searchInput?.focus(), 100);
}

async function performOriginalSalesInvoiceSearch() {
    const search = salesReturnGetValue('originalSalesInvoiceSearchInput').trim();
    const tbody = document.getElementById('originalSalesInvoiceSearchResults');
    if (!tbody) return;
    tbody.replaceChildren();

    try {
        const raw = await salesReturnApiGet(
            `/operation/sales/invoices/list?search=${encodeURIComponent(search)}`
        );
        const rows = salesReturnExtractArray(raw);

        if (rows.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 7;
            td.className = 'text-center text-muted py-4';
            td.textContent = 'لا توجد نتائج مطابقة';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        rows.forEach(inv => {
            const tr = document.createElement('tr');
            tr.className = 'text-center';
            tr.style.cursor = 'pointer';
            tr.title = 'اضغط لاختيار هذه الفاتورة';

            tr.addEventListener('click', () => onSalesInvoiceRowClick(inv));
            tr.addEventListener('mouseenter', () => tr.classList.add('table-primary'));
            tr.addEventListener('mouseleave', () => tr.classList.remove('table-primary'));

            [
                inv.invoice_number,
                inv.invoice_date,
                inv.customer_name,
                inv.coin_name,
                SALES_RETURN_PAYMENT_LABELS[inv.payment_method] || '',
                salesReturnFormatMoney(inv.total),
            ].forEach(v => {
                const td = document.createElement('td');
                td.textContent = v ?? '';
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
                tr.classList.add('opacity-75');
            }
            tdStatus.appendChild(badge);
            tr.appendChild(tdStatus);

            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error('[SalesReturn] invoice search failed', e);
        salesReturnNotify('فشل البحث عن الفواتير', 'danger');
    }
}

function onSalesInvoiceRowClick(inv) {
    if (!inv.is_posted) {
        salesReturnNotify(
            'لا يمكن اختيار فاتورة غير مرحّلة. قم بترحيلها أولًا من شاشة فواتير البيع.',
            'warning'
        );
        return;
    }
    selectOriginalSalesInvoice(inv.sales_invoice_id);
}

/**
 * تحميل فاتورة بيع أصلية وتعبئة تفاصيلها في المرتجع.
 * يستخدم Promise.all لجلب الكميات المتاحة بالتوازي.
 * @param {number} invoiceId
 */
async function selectOriginalSalesInvoice(invoiceId) {
    try {
        const res = await salesReturnApiGet(`/operation/sales/invoices/${invoiceId}`);
        const { header: h, details } = salesReturnExtractPayload(res);

        if (!h.is_posted) {
            salesReturnNotify('لا يمكن اختيار فاتورة غير مرحّلة.', 'warning');
            return;
        }

        salesReturnSetValue('originalSalesInvoiceId', h.sales_invoice_id);
        salesReturnSetValue('originalSalesInvoiceNo', h.invoice_number);
        salesReturnSetValue('salesReturnCustomerID', h.account_id);
        salesReturnSetValue('salesReturnCustomerName', h.customer_name);
        salesReturnSetValue('salesReturnCoinsID', h.coin_id);
        salesReturnSetValue('salesReturnCurrencyName', h.coin_name);
        salesReturnSetValue('salesReturnWarehouseID', h.warehouse_id || '');
        salesReturnSetValue('salesReturnWarehouseName', h.warehouse_name || '');
        salesReturnSetValue('SalesReturnExchangeRate', h.exchange_rate);

        salesReturnSetValue('SalesReturnPaymentMethodValue', h.payment_method);
        salesReturnSetValue('salesReturnPaymentAccountId', h.payment_account_id || '');
        salesReturnSetValue('salesReturnPaymentAccount', h.payment_account_name || '');

        salesReturnPaymentMethodChanged();

        const tbody = document.getElementById('salesReturnDetails');
        tbody.replaceChildren();

        // جلب الكميات المتاحة بالتوازي (بدل N طلبات متتالية)
        const availabilities = await Promise.all(
            details.map(d =>
                salesReturnApiGet(
                    `/operation/sales/returns/available-quantity?sales_invoice_detail_id=${d.sales_invoice_detail_id}`
                )
                    .then(r => parseFloat(r.available) || 0)
                    .catch(err => {
                        console.error('[SalesReturn] available-qty failed', d.sales_invoice_detail_id, err);
                        return 0;
                    })
            )
        );

        let added = 0;
        for (let i = 0; i < details.length; i++) {
            const availableQty = availabilities[i];
            if (availableQty <= 0) continue;

            const row = buildSalesReturnRow({
                detail: details[i],
                availableQty,
                index: added,
                source: 'invoice',
            });

            tbody.appendChild(row);
            enableSalesReturnRow(row);
            added++;
        }

        if (added === 0) {
            setEmptySalesReturnMessage();
            salesReturnNotify('جميع أصناف هذه الفاتورة تم إرجاعها مسبقاً', 'warning');
        } else {
            calculateSalesReturnTotals();
            salesReturnNotify('تم تحميل تفاصيل الفاتورة بنجاح', 'success');
        }

        const modalEl = document.getElementById('originalSalesInvoiceSearchModal');
        bootstrap.Modal.getInstance(modalEl)?.hide();
        setTimeout(cleanupStuckModals, 400);
    } catch (e) {
        console.error('[SalesReturn] load invoice failed', e);
        salesReturnNotify('فشل تحميل الفاتورة الأصلية: ' + e.message, 'danger');
    }
}

/* =========================================================================
   البحث عن مرتجع
   ========================================================================= */

function searchSalesReturn() {
    const modalEl = document.getElementById('salesReturnSearchModal');
    if (!modalEl) return;

    const searchInput = document.getElementById('salesReturnSearchInput');
    if (searchInput) searchInput.value = '';

    const tb = document.getElementById('salesReturnSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 8;
    td.className = 'text-center text-muted py-4';
    td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
    tr.appendChild(td);
    tb.appendChild(tr);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    setTimeout(() => {
        searchInput?.focus();
        performSalesReturnSearch();
    }, 250);
}

function debouncedSalesReturnSearch() {
    clearTimeout(salesReturnSearchTimer);
    salesReturnSearchTimer = setTimeout(performSalesReturnSearch, 350);
}

function clearSalesReturnSearch() {
    const searchInput = document.getElementById('salesReturnSearchInput');
    if (searchInput) searchInput.value = '';

    performSalesReturnSearch();
    setTimeout(() => searchInput?.focus(), 100);
}

async function performSalesReturnSearch() {
    const search = salesReturnGetValue('salesReturnSearchInput').trim();
    const tbody = document.getElementById('salesReturnSearchResults');
    if (!tbody) return;
    tbody.replaceChildren();

    try {
        const raw = await salesReturnApiGet(
            `/operation/sales/returns/list?search=${encodeURIComponent(search)}`
        );
        const rows = salesReturnExtractArray(raw);

        if (rows.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 8;
            td.className = 'text-center text-muted py-4';
            td.textContent = 'لا توجد نتائج مطابقة';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        rows.forEach(ret => {
            const tr = document.createElement('tr');
            tr.className = 'text-center';
            tr.style.cursor = 'pointer';
            tr.title = 'اضغط لتحميل المرتجع';

            tr.addEventListener('click', () => loadSalesReturn(ret.sales_return_id));
            tr.addEventListener('mouseenter', () => tr.classList.add('table-primary'));
            tr.addEventListener('mouseleave', () => tr.classList.remove('table-primary'));

            [
                ret.return_number,
                ret.return_date,
                ret.original_invoice_number,
                ret.customer_name,
                SALES_RETURN_PAYMENT_LABELS[ret.payment_method] || '',
                salesReturnFormatMoney(ret.total),
            ].forEach(v => {
                const td = document.createElement('td');
                td.textContent = v ?? '';
                tr.appendChild(td);
            });

            const tdStatus = document.createElement('td');
            const badge = document.createElement('span');
            if (ret.is_posted) {
                badge.className = 'badge bg-success';
                badge.textContent = 'مرحّل';
            } else {
                badge.className = 'badge bg-secondary';
                badge.textContent = 'مسودة';
            }
            tdStatus.appendChild(badge);
            tr.appendChild(tdStatus);

            const tdActions = document.createElement('td');
            tdActions.className = 'text-nowrap';
            tdActions.addEventListener('click', e => e.stopPropagation());

            tdActions.appendChild(buildSalesReturnPostToggleBtn(ret));
            tdActions.appendChild(buildSalesReturnDeleteBtn(ret));
            tr.appendChild(tdActions);

            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error('[SalesReturn] search failed', e);
        salesReturnNotify('فشل البحث عن مرتجعات المبيعات', 'danger');
    }
}

function buildSalesReturnPostToggleBtn(ret) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm me-1';

    if (ret.is_posted) {
        btn.classList.add('btn-outline-warning');
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> إلغاء الترحيل';
        btn.title = 'فتح المرتجع للتعديل';
        btn.addEventListener('click', e => {
            e.stopPropagation();
            unpostSalesReturn(ret.sales_return_id);
        });
    } else {
        btn.classList.add('btn-outline-success');
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> ترحيل';
        btn.title = 'ترحيل المرتجع وإنشاء القيود';
        btn.addEventListener('click', e => {
            e.stopPropagation();
            postSalesReturn(ret.sales_return_id);
        });
    }

    return btn;
}

function buildSalesReturnDeleteBtn(ret) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-danger';
    btn.innerHTML = '<i class="bi bi-trash"></i> حذف';
    btn.title = 'حذف المرتجع نهائيًا';
    btn.addEventListener('click', e => {
        e.stopPropagation();
        deleteSalesReturn(ret.sales_return_id, ret.return_number);
    });
    return btn;
}

/* =========================================================================
   الترحيل / إلغاء الترحيل / الحذف
   ========================================================================= */

async function postSalesReturn(id) {
    const ok = await salesReturnConfirm(
        'هل تريد ترحيل هذا المرتجع؟ سيتم إنشاء القيود وحركة المخزون.',
        { title: 'ترحيل المرتجع', okText: 'ترحيل' }
    );
    if (!ok) return;

    try {
        const r = await salesReturnApiSend(`/operation/sales/returns/${id}/post`, 'POST', {});
        salesReturnNotify(r.message || 'تم ترحيل المرتجع بنجاح', 'success');
        performSalesReturnSearch();
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

async function unpostSalesReturn(id) {
    const ok = await salesReturnConfirm(
        'هل تريد إلغاء ترحيل هذا المرتجع؟ سيتم حذف القيود وحركة المخزون.',
        { title: 'إلغاء الترحيل', okText: 'إلغاء الترحيل', danger: true }
    );
    if (!ok) return;

    try {
        const r = await salesReturnApiSend(`/operation/sales/returns/${id}/unpost`, 'POST', {});
        salesReturnNotify(r.message || 'تم إلغاء ترحيل المرتجع بنجاح', 'success');
        performSalesReturnSearch();

        if (currentSalesReturnId === id) await loadSalesReturn(id);
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

async function deleteSalesReturn(id, number) {
    const label = number ? `رقم ${number}` : `#${id}`;
    const ok = await salesReturnConfirm(
        `هل أنت متأكد من حذف المرتجع ${label}؟ لا يمكن التراجع عن هذه العملية.`,
        { title: 'حذف مرتجع', okText: 'حذف نهائي', danger: true }
    );
    if (!ok) return;

    try {
        const r = await salesReturnApiSend(`/operation/sales/returns/${id}`, 'DELETE', {});
        salesReturnNotify(r.message || 'تم حذف المرتجع بنجاح', 'success');
        performSalesReturnSearch();

        if (currentSalesReturnId === id) clearSalesReturnForm();
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

async function postCurrentSalesReturn() {
    if (!currentSalesReturnId) {
        salesReturnNotify('لا يوجد مرتجع للترحيل', 'warning');
        return;
    }

    const ok = await salesReturnConfirm(
        'هل تريد ترحيل هذا المرتجع؟ سيتم إنشاء القيود وحركة المخزون.',
        { title: 'ترحيل المرتجع', okText: 'ترحيل' }
    );
    if (!ok) return;

    try {
        const r = await salesReturnApiSend(
            `/operation/sales/returns/${currentSalesReturnId}/post`, 'POST', {}
        );
        salesReturnNotify(r.message || 'تم ترحيل المرتجع بنجاح', 'success');
        await loadSalesReturn(currentSalesReturnId);
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

async function unpostCurrentSalesReturn() {
    if (!currentSalesReturnId) {
        salesReturnNotify('لا يوجد مرتجع', 'warning');
        return;
    }

    const ok = await salesReturnConfirm(
        'هل تريد إلغاء ترحيل هذا المرتجع؟ سيتم حذف القيود وحركة المخزون.',
        { title: 'إلغاء الترحيل', okText: 'إلغاء الترحيل', danger: true }
    );
    if (!ok) return;

    try {
        const r = await salesReturnApiSend(
            `/operation/sales/returns/${currentSalesReturnId}/unpost`, 'POST', {}
        );
        salesReturnNotify(r.message || 'تم إلغاء ترحيل المرتجع بنجاح', 'success');
        await loadSalesReturn(currentSalesReturnId);
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

/* =========================================================================
   تحميل مرتجع محفوظ
   ========================================================================= */

/**
 * تحميل مرتجع محفوظ بالكامل.
 * @param {number} id
 */
async function loadSalesReturn(id) {
    try {
        const res = await salesReturnApiGet(`/operation/sales/returns/${id}`);
        const { header: h, details } = salesReturnExtractPayload(res);

        clearSalesReturnForm();

        salesReturnSetValue('SalesReturnNo', h.return_number ?? '');
        salesReturnSetValue('SalesReturnDate', h.return_date ?? '');
        salesReturnSetValue('originalSalesInvoiceId', h.original_sales_invoice_id ?? '');
        salesReturnSetValue('originalSalesInvoiceNo', h.original_invoice_number ?? '');
        salesReturnSetValue('salesReturnCustomerID', h.account_id ?? '');
        salesReturnSetValue('salesReturnCustomerName', h.customer_name ?? '');
        salesReturnSetValue('salesReturnCoinsID', h.coin_id ?? '');
        salesReturnSetValue('salesReturnCurrencyName', h.coin_name ?? '');
        salesReturnSetValue('salesReturnWarehouseID', h.warehouse_id ?? '');
        salesReturnSetValue('salesReturnWarehouseName', h.warehouse_name ?? '');
        salesReturnSetValue('SalesReturnExchangeRate', h.exchange_rate ?? 1);

        salesReturnSetValue('SalesReturnPaymentMethodValue', h.payment_method);
        salesReturnSetValue('salesReturnPaymentAccountId', h.payment_account_id ?? '');
        salesReturnSetValue('salesReturnPaymentAccount', h.payment_account_name ?? '');

        salesReturnPaymentMethodChanged();

        const tbody = document.getElementById('salesReturnDetails');
        tbody.replaceChildren();

        details.forEach((d, i) => {
            const row = buildSalesReturnRow({
                detail: d,
                availableQty: parseFloat(d.available_quantity) || 0,
                index: i,
                source: 'return',
            });
            tbody.appendChild(row);
        });

        if (!details.length) setEmptySalesReturnMessage();

        calculateSalesReturnTotals();

        const modalEl = document.getElementById('salesReturnSearchModal');
        bootstrap.Modal.getInstance(modalEl)?.hide();
        setTimeout(cleanupStuckModals, 400);

        currentSalesReturnId = h.sales_return_id;
        currentSalesReturnIsPosted = !!h.is_posted;

        setSalesReturnMode(SALES_RETURN_MODES.VIEW);
        salesReturnNotify('تم تحميل مرتجع المبيعات بنجاح', 'success');
    } catch (e) {
        console.error('[SalesReturn] load failed', e);
        salesReturnNotify('فشل تحميل مرتجع المبيعات: ' + e.message, 'danger');
    }
}

function hasSalesReturnData() {
    const el = document.getElementById('SalesReturnNo');
    return el ? el.value.trim() !== '' : false;
}

function editSalesReturn() {
    if (!hasSalesReturnData() || !currentSalesReturnId) {
        salesReturnNotify('لا يوجد مرتجع للتعديل', 'warning');
        return;
    }

    setSalesReturnMode(SALES_RETURN_MODES.EDIT);
    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach(r => enableSalesReturnRow(r));
}

async function cancelSalesReturn() {
    const ok = await salesReturnConfirm('هل أنت متأكد من إلغاء العملية؟', {
        title: 'إلغاء',
        okText: 'نعم، إلغاء',
        danger: true,
    });
    if (!ok) return;

    clearSalesReturnForm();
    salesReturnNotify('تم إلغاء العملية', 'info');
}

/* =========================================================================
   الحفظ
   ========================================================================= */

async function saveSalesReturn() {
    if (isSavingSalesReturn) return;

    const number = salesReturnGetValue('SalesReturnNo').trim();
    if (!number) return salesReturnNotify('رقم المرتجع مطلوب', 'warning');

    const invoiceId = salesReturnGetValue('originalSalesInvoiceId');
    if (!invoiceId) return salesReturnNotify('يجب اختيار الفاتورة الأصلية', 'warning');

    const customerId = salesReturnGetValue('salesReturnCustomerID');
    if (!customerId) return salesReturnNotify('يجب اختيار العميل', 'warning');

    const coinId = salesReturnGetValue('salesReturnCoinsID');
    if (!coinId) return salesReturnNotify('يجب اختيار العملة', 'warning');

    const methodInt = parseInt(salesReturnGetValue('SalesReturnPaymentMethodValue'), 10) || 0;
    if (!methodInt) return salesReturnNotify('طريقة الدفع غير محددة', 'warning');

    if (methodInt !== 1 && !salesReturnGetValue('salesReturnPaymentAccountId')) {
        return salesReturnNotify('يجب اختيار حساب الدفع', 'warning');
    }

    const rows = document.querySelectorAll('#salesReturnDetails .return-detail-row');
    if (!rows.length) return salesReturnNotify('أضف صنفًا واحدًا على الأقل للمرتجع', 'warning');

    const details = [];
    for (const row of rows) {
        const detailId = row.querySelector('.row-detail-id')?.value;
        if (!detailId) continue;

        const qty = parseFloat(row.querySelector('.row-measure')?.value) || 0;
        if (qty <= 0) continue;

        const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
        const discount = parseFloat(row.querySelector('.row-discount')?.value) || 0;

        details.push({
            sales_invoice_detail_id: Number(detailId),
            quantity: qty,
            price,
            discount,
        });
    }

    if (!details.length) {
        return salesReturnNotify('يجب إدخال كمية أكبر من صفر لصنف واحد على الأقل', 'warning');
    }

    const exchangeRate = salesReturnReadNumber('SalesReturnExchangeRate', 1, 0);
    if (exchangeRate <= 0) {
        return salesReturnNotify('سعر الصرف يجب أن يكون أكبر من صفر', 'warning');
    }

    const payload = {
        return_number: number,
        return_date: salesReturnGetValue('SalesReturnDate'),
        original_sales_invoice_id: Number(invoiceId),
        account_id: Number(customerId),
        payment_method: methodInt,
        payment_account_id: salesReturnGetValue('salesReturnPaymentAccountId') || null,
        coin_id: Number(coinId),
        warehouse_id: salesReturnGetValue('salesReturnWarehouseID')
            ? Number(salesReturnGetValue('salesReturnWarehouseID'))
            : null,
        exchange_rate: exchangeRate,
        statement: salesReturnGetValue('SalesReturnStatement') || null,
        reference: salesReturnGetValue('SalesReturnReference') || null,
        details,
    };

    isSavingSalesReturn = true;
    const saveBtn = document.getElementById('btnSaveSalesReturn');
    const saveNewBtn = document.getElementById('btnSaveAndNewSalesReturn');
    if (saveBtn) saveBtn.disabled = true;
    if (saveNewBtn) saveNewBtn.disabled = true;

    try {
        let r;
        if (salesReturnMode === SALES_RETURN_MODES.EDIT && currentSalesReturnId) {
            r = await salesReturnApiSend(
                `/operation/sales/returns/${currentSalesReturnId}`, 'PUT', payload
            );
            currentSalesReturnIsPosted = false;
        } else {
            r = await salesReturnApiSend('/operation/sales/returns', 'POST', payload);
            currentSalesReturnId = r.sales_return_id;
            currentSalesReturnIsPosted = !!r.is_posted;
            if (r.return_number) salesReturnSetValue('SalesReturnNo', r.return_number);
        }

        salesReturnNotify(r.message || 'تم الحفظ بنجاح', 'success');
        setSalesReturnMode(SALES_RETURN_MODES.VIEW);
    } catch (e) {
        let m = e.message;
        if (e.errors) m += ' — ' + Object.values(e.errors).flat().join(' | ');
        salesReturnNotify(m, 'danger');
    } finally {
        isSavingSalesReturn = false;
        if (salesReturnMode !== SALES_RETURN_MODES.VIEW) {
            if (saveBtn) saveBtn.disabled = false;
            if (saveNewBtn) saveNewBtn.disabled = false;
        }
    }
}

async function saveAndNewSalesReturn() {
    await saveSalesReturn();
    if (salesReturnMode === SALES_RETURN_MODES.VIEW) await resetSalesReturn();
}

function printSalesReturn() {
    if (!hasSalesReturnData() || !currentSalesReturnId) {
        salesReturnNotify('يجب حفظ المرتجع أولاً قبل الطباعة', 'warning');
        return;
    }

    window.open(
        `/operation/sales/returns/${currentSalesReturnId}/print`,
        '_blank',
        'width=900,height=700'
    );
}

/* =========================================================================
   تصدير للـ Window
   ========================================================================= */

Object.assign(window, {
    // ثوابت (للتوافق)
    SALES_RETURN_PAYMENT_LABELS,
    SALES_RETURN_PAYMENT_TO_INT,
    SALES_RETURN_PAYMENT_TO_STR,

    // Actions
    resetSalesReturn,
    searchSalesReturn,
    performSalesReturnSearch,
    debouncedSalesReturnSearch,
    clearSalesReturnSearch,
    searchOriginalSalesInvoice,
    performOriginalSalesInvoiceSearch,
    debouncedOriginalSalesInvoiceSearch,
    clearOriginalSalesInvoiceSearch,
    onSalesInvoiceRowClick,
    selectOriginalSalesInvoice,
    calculateSalesReturnRow,
    removeSalesReturnRow,
    salesReturnPaymentMethodChanged,
    cancelSalesReturn,
    saveSalesReturn,
    saveAndNewSalesReturn,
    editSalesReturn,
    printSalesReturn,
    loadSalesReturn,
    postSalesReturn,
    unpostSalesReturn,
    deleteSalesReturn,
    postCurrentSalesReturn,
    unpostCurrentSalesReturn,
    updateSalesReturnPostingButtons,
    updateSalesReturnSearchButton,
    onOriginalInvoiceFieldClick,
    cleanupStuckModals,
    salesReturnConfirm,
});