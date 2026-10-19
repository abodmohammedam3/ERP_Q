/* =========================================================================
   مرتجع المشتريات — إدارة مرتجعات الشراء
   ========================================================================= */

const PURCHASE_RETURN_PAYMENT_TO_INT = { credit: 1, cash: 2, bank: 3 };
const PURCHASE_RETURN_PAYMENT_TO_STR = { 1: 'credit', 2: 'cash', 3: 'bank' };
const PURCHASE_RETURN_PAYMENT_LABELS = { 1: 'أجل', 2: 'نقدي', 3: 'بنك' };

/** تنسيق المبالغ بفواصل الآلاف (موحّد عبر Utils) */
function purchaseReturnFormatMoney(v) {
    if (window.Utils && typeof Utils.formatAmount === 'function') {
        return Utils.formatAmount(v);
    }
    return Number(v || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/** ✅ تأكيد موحّد عبر appConfirm (shared/app-confirm.js) — مثل sales_return.js */
function purchaseReturnConfirm(message, opts = {}) {
    if (typeof window.appConfirm === 'function') {
        return window.appConfirm(message, opts);
    }
    return Promise.resolve(window.confirm(message));
}

let purchaseReturnMode = 'view';
let currentPurchaseReturnId = null;
let currentPurchaseReturnIsPosted = false;
let isSavingPurchaseReturn = false;

/* ✅ مؤقتات البحث */
let originalInvoiceSearchTimer = null;
let purchaseReturnSearchTimer = null;

const PURCHASE_RETURN_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function purchaseReturnApiHeaders(json = false) {
    const h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': PURCHASE_RETURN_CSRF };
    if (json) h['Content-Type'] = 'application/json';
    return h;
}

async function purchaseReturnApiGet(url) {
    const r = await fetch(url, { headers: purchaseReturnApiHeaders() });
    if (!r.ok) throw new Error(`فشل الطلب: ${r.status}`);
    return r.json();
}

async function purchaseReturnApiSend(url, method, body) {
    const r = await fetch(url, {
        method, headers: purchaseReturnApiHeaders(true), body: JSON.stringify(body),
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

function purchaseReturnNotify(message, type = 'info') {
    if (typeof window.showSystemToast === 'function') {
        window.showSystemToast(message, type);
    } else {
        console.warn(`[${type}] ${message}`);
        alert(message);
    }
}

function purchaseReturnGetValue(id) {
    const el = document.getElementById(id);
    return el ? (el.value || '') : '';
}

function purchaseReturnSetValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value ?? '';
}

function purchaseReturnExtractPayload(res) {
    if (res && res.header) {
        return { header: res.header, details: res.details || [] };
    }
    if (res && res.data && res.data.header) {
        return { header: res.data.header, details: res.data.details || [] };
    }
    return { header: {}, details: [] };
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
   ✅ التهيئة عند التحميل
   ========================================================= */
document.addEventListener('DOMContentLoaded', () => {
    cleanupStuckModals();

    setPurchaseReturnMode('view');
    clearPurchaseReturnForm();

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
   ✅ عند الضغط على حقل الفاتورة الأصلية
   ========================================================= */

function onOriginalInvoiceFieldClick() {
    if (purchaseReturnMode === 'view') {
        return;
    }
    searchOriginalPurchaseInvoice();
}

/* =========================================================
   أوضاع الشاشة
   ========================================================= */

function setPurchaseReturnMode(mode) {
    purchaseReturnMode = mode;

    document.querySelectorAll('#purchaseReturnDetails .return-detail-row')
        .forEach(row => enablePurchaseReturnRow(row));

    const newBtn = document.getElementById('btnNewPurchaseReturn');
    if (newBtn) newBtn.disabled = (mode !== 'view');

    const saveBtn = document.getElementById('btnSavePurchaseReturn');
    const saveNewBtn = document.getElementById('btnSaveAndNewPurchaseReturn');
    const cancelBtn = document.getElementById('btnCancelPurchaseReturn');

    if (mode === 'view') {
        if (saveBtn) saveBtn.disabled = true;
        if (saveNewBtn) saveNewBtn.disabled = true;
        if (cancelBtn) cancelBtn.classList.add('d-none');
    } else {
        if (saveBtn) saveBtn.disabled = false;
        if (saveNewBtn) saveNewBtn.disabled = false;
        if (cancelBtn) cancelBtn.classList.remove('d-none');
    }

    const invoiceWrapper = document.getElementById('originalPurchaseInvoiceWrapper');
    if (invoiceWrapper) {
        invoiceWrapper.style.cursor = (mode === 'view') ? 'default' : 'pointer';
    }

    updatePurchaseReturnSearchButton();
    updatePurchaseReturnPostingButtons();
    applyPurchaseReturnExchangeRateState();
}

/**
 * ✅ سعر صرف المرتجع الخاص به — مثل فاتورة الشراء:
 *   - عملة النظام → readonly = 1
 *   - عملة أجنبية → قابل للتعديل (إلا في وضع العرض)
 */
function applyPurchaseReturnExchangeRateState() {
    const rateEl = document.getElementById('PurchaseReturnExchangeRate');
    if (!rateEl) return;

    const sysId = window.PURCHASE_SYSTEM_CURRENCY_ID ?? null;
    const coinId = purchaseReturnGetValue('purchaseReturnCoinsID');
    const isSys = sysId !== null && coinId !== '' && String(coinId) === String(sysId);
    const isView = purchaseReturnMode === 'view';

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

/* =========================================================
   ✅ إدارة زر البحث
   ========================================================= */

function updatePurchaseReturnSearchButton() {
    const searchBtn = document.getElementById('btnSearchPurchaseReturn');
    if (!searchBtn) return;

    const hasReturn = !!currentPurchaseReturnId;
    const isViewMode = purchaseReturnMode === 'view';

    searchBtn.disabled = !isViewMode || hasReturn;
}

/* =========================================================
   ✅ إدارة أزرار الترحيل / إلغاء الترحيل
   ========================================================= */

function updatePurchaseReturnPostingButtons() {
    const editBtn   = document.getElementById('btnEditPurchaseReturn');
    const postBtn   = document.getElementById('btnPostPurchaseReturn');
    const unpostBtn = document.getElementById('btnUnpostPurchaseReturn');
    const printBtn  = document.getElementById('btnPrintPurchaseReturn');

    const hasData  = hasPurchaseReturnData();
    const isPosted = currentPurchaseReturnIsPosted;
    const isView   = purchaseReturnMode === 'view';

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

    if (printBtn) {
        printBtn.disabled = !hasData;
    }
}

async function resetPurchaseReturn() {
    cleanupStuckModals();

    clearPurchaseReturnForm();
    setPurchaseReturnMode('add');

    try {
        const res = await purchaseReturnApiGet('/operation/purchases/returns/next-number');
        purchaseReturnSetValue('PurchaseReturnNo', res.next_number || '');
    } catch (e) {
        purchaseReturnNotify('تعذّر جلب رقم المرتجع التالي', 'danger');
    }

    purchaseReturnSetValue('PurchaseReturnDate', new Date().toISOString().split('T')[0]);

    searchOriginalPurchaseInvoice();
}

function clearPurchaseReturnForm() {
    [
        'PurchaseReturnNo', 'PurchaseReturnDate', 'originalPurchaseInvoiceNo',
        'PurchaseReturnPaymentMethod', 'PurchaseReturnPaymentMethodValue',
        'purchaseReturnPaymentAccount', 'purchaseReturnSupplierName',
        'purchaseReturnCurrencyName', 'purchaseReturnWarehouseName',
        'PurchaseReturnExchangeRate', 'PurchaseReturnStatement', 'PurchaseReturnReference'
    ].forEach(id => purchaseReturnSetValue(id, ''));

    [
        'originalPurchaseInvoiceId', 'purchaseReturnSupplierID', 'purchaseReturnCoinsID',
        'purchaseReturnWarehouseID', 'purchaseReturnPaymentAccountId'
    ].forEach(id => purchaseReturnSetValue(id, ''));

    setEmptyPurchaseReturnMessage();

    const discEl = document.getElementById('totalPurchaseReturnDiscountDisplay');
    if (discEl) discEl.textContent = '0.00';

    const totalEl = document.getElementById('purchaseReturnTotalDisplay');
    if (totalEl) totalEl.textContent = '0.00';

    hidePurchaseReturnPaymentAccounts();

    currentPurchaseReturnId = null;
    currentPurchaseReturnIsPosted = false;

    setPurchaseReturnMode('view');
}

function setEmptyPurchaseReturnMessage() {
    const tbody = document.getElementById('purchaseReturnDetails');
    if (!tbody) return;

    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 11;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'لا توجد أصناف. اضغط "مرتجع جديد" للبدء.';
    tr.appendChild(td);
    tbody.appendChild(tr);
}

function enablePurchaseReturnRow(row) {
    if (!row) return;

    row.querySelectorAll('input, select, button').forEach(el => {
        if (el.classList.contains('row-total')) return;
        if (el.classList.contains('row-available')) return;
        if (el.classList.contains('row-discount')) return;
        if (el.classList.contains('row-price')) return;
        if (el.classList.contains('row-item')) return;
        if (el.classList.contains('row-type')) return;
        if (el.classList.contains('row-code')) return;
        if (el.classList.contains('row-unit')) return;
        if (el.type === 'hidden') return;

        el.disabled = (purchaseReturnMode === 'view');
    });
}

function removePurchaseReturnRow(btn) {
    if (purchaseReturnMode === 'view') return;

    const row = btn.closest('tr');
    if (row) row.remove();

    renumberPurchaseReturnRows();
    calculatePurchaseReturnTotals();

    const tbody = document.getElementById('purchaseReturnDetails');
    if (tbody && !tbody.querySelector('.return-detail-row')) {
        setEmptyPurchaseReturnMessage();

        /* ✅ لم يبق أي سطر → العودة لحالة «مرتجع جديد»
           (نفرّغ اختيار الفاتورة الأصلية فقط، بلا مساس برقم المرتجع/تاريخه) */
        clearOriginalInvoiceSelection();
        setPurchaseReturnMode('add');
        updatePurchaseReturnSearchButton();
    }
}

/**
 * ✅ تفريغ اختيار الفاتورة الأصلية فقط:
 * الحقول المخفية للمعرّفات + أسماء المورد/العملة/المخزن + سعر الصرف،
 * ولا يلمس رقم المرتجع (PurchaseReturnNo) ولا تاريخه.
 */
function clearOriginalInvoiceSelection() {
    ['originalPurchaseInvoiceId', 'purchaseReturnSupplierID', 'purchaseReturnCoinsID',
     'purchaseReturnWarehouseID']
        .forEach(id => purchaseReturnSetValue(id, ''));

    ['originalPurchaseInvoiceNo', 'purchaseReturnSupplierName', 'purchaseReturnCurrencyName',
     'purchaseReturnWarehouseName', 'PurchaseReturnExchangeRate']
        .forEach(id => purchaseReturnSetValue(id, ''));

    applyPurchaseReturnExchangeRateState();

    const searchBtn = document.getElementById('btnSearchPurchaseReturn');
    if (searchBtn) searchBtn.disabled = false;
}

function renumberPurchaseReturnRows() {
    document.querySelectorAll('#purchaseReturnDetails .return-detail-row')
        .forEach((r, i) => {
            const numEl = r.querySelector('.row-num');
            if (numEl) numEl.textContent = i + 1;
        });
}

function calculatePurchaseReturnRow(input) {
    const row = input.closest('tr');
    if (!row) return;

    const available = parseFloat(row.querySelector('.row-available')?.value) || 0;
    let qty = parseFloat(row.querySelector('.row-measure')?.value) || 0;
    const price = parseFloat(row.querySelector('.row-price')?.value) || 0;

    const originalQty = parseFloat(row.querySelector('.row-original-qty')?.value) || 0;
    const originalDiscount = parseFloat(row.querySelector('.row-original-discount')?.value) || 0;

    if (qty > available) {
        purchaseReturnNotify(`الكمية المراد إرجاعها (${qty}) أكبر من الكمية المتاحة (${available})`, 'warning');
        row.querySelector('.row-measure').value = available;
        qty = available;
    }

    let discount = 0;
    if (originalQty > 0) {
        discount = (qty / originalQty) * originalDiscount;
    }

    const discountEl = row.querySelector('.row-discount');
    if (discountEl) discountEl.value = discount.toFixed(2);

    const totalEl = row.querySelector('.row-total');
    if (totalEl) totalEl.value = Math.max(0, qty * price - discount).toFixed(2);

    calculatePurchaseReturnTotals();
}

function calculatePurchaseReturnTotals() {
    let itemsTotal = 0;
    let discountTotal = 0;

    document.querySelectorAll('#purchaseReturnDetails .return-detail-row').forEach(row => {
        const q = parseFloat(row.querySelector('.row-measure')?.value) || 0;
        const p = parseFloat(row.querySelector('.row-price')?.value) || 0;
        const d = parseFloat(row.querySelector('.row-discount')?.value) || 0;
        itemsTotal += q * p;
        discountTotal += d;
    });

    const exchangeRate = parseFloat(document.getElementById('PurchaseReturnExchangeRate')?.value) || 1;
    const net = Math.max(0, itemsTotal - discountTotal);
    const total = net * exchangeRate;

    const discEl = document.getElementById('totalPurchaseReturnDiscountDisplay');
    if (discEl) discEl.textContent = purchaseReturnFormatMoney(discountTotal);

    const totalEl = document.getElementById('purchaseReturnTotalDisplay');
    if (totalEl) totalEl.textContent = purchaseReturnFormatMoney(total);
}

function purchaseReturnPaymentMethodChanged() {
    const methodValue = parseInt(purchaseReturnGetValue('PurchaseReturnPaymentMethodValue')) || 0;
    const displayEl = document.getElementById('PurchaseReturnPaymentMethod');
    const container = document.getElementById('purchaseReturnPaymentAccountContainer');
    const label = document.getElementById('purchaseReturnPaymentAccountLabel');

    if (displayEl) {
        displayEl.value = PURCHASE_RETURN_PAYMENT_LABELS[methodValue] || '';
    }

    if (container) {
        if (methodValue === 2 || methodValue === 3) {
            container.classList.remove('d-none');
            if (label) {
                label.textContent = methodValue === 3 ? 'الحساب البنكي' : 'الصندوق';
            }
        } else {
            container.classList.add('d-none');
            purchaseReturnSetValue('purchaseReturnPaymentAccount', '');
            purchaseReturnSetValue('purchaseReturnPaymentAccountId', '');
        }
    }
}

function hidePurchaseReturnPaymentAccounts() {
    const el = document.getElementById('purchaseReturnPaymentAccountContainer');
    if (el) el.classList.add('d-none');
}

/* =========================================================
   ✅ البحث عن فاتورة شراء أصلية
   ========================================================= */

function searchOriginalPurchaseInvoice() {
    const modalEl = document.getElementById('originalPurchaseInvoiceSearchModal');
    if (!modalEl) return;

    const searchInput = document.getElementById('originalPurchaseInvoiceSearchInput');
    if (searchInput) searchInput.value = '';

    const tb = document.getElementById('originalPurchaseInvoiceSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 7;
    td.className = 'text-center text-muted py-4';
    td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
    tr.appendChild(td);
    tb.appendChild(tr);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    setTimeout(() => {
        searchInput?.focus();
        performOriginalPurchaseInvoiceSearch();
    }, 250);
}

/**
 * ✅ بحث مؤجّل (Debounced) عن الفاتورة الأصلية
 */
function debouncedOriginalPurchaseInvoiceSearch() {
    clearTimeout(originalInvoiceSearchTimer);
    originalInvoiceSearchTimer = setTimeout(performOriginalPurchaseInvoiceSearch, 350);
}

/**
 * ✅ تفريغ البحث — إعادة عرض كل الفواتير
 */
function clearOriginalPurchaseInvoiceSearch() {
    const searchInput = document.getElementById('originalPurchaseInvoiceSearchInput');
    if (searchInput) searchInput.value = '';

    performOriginalPurchaseInvoiceSearch();

    setTimeout(() => {
        searchInput?.focus();
    }, 100);
}

async function performOriginalPurchaseInvoiceSearch() {
    const search = purchaseReturnGetValue('originalPurchaseInvoiceSearchInput').trim();
    const tbody = document.getElementById('originalPurchaseInvoiceSearchResults');
    tbody.replaceChildren();

    try {
        /* ✅ مسار مخصص: يُرجع فقط الفواتير التي ما زال بها رصيد قابل للإرجاع */
        const raw = await purchaseReturnApiGet(`/operation/purchases/returns/invoice-picker?search=${encodeURIComponent(search)}`);
        const rows = Array.isArray(raw) ? raw : (raw.data || []);

        if (rows.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 7;
            td.className = 'text-center text-muted py-4';
            td.textContent = 'لا توجد فواتير قابلة للإرجاع (ربما تم إرجاعها بالكامل)';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        rows.forEach(inv => {
            const tr = document.createElement('tr');
            tr.className = 'text-center';
            tr.style.cursor = 'pointer';
            tr.title = 'اضغط لاختيار هذه الفاتورة';

            // ✅ النقر على الصف = فحص الحالة ثم الاختيار
            tr.addEventListener('click', () => onInvoiceRowClick(inv));

            tr.addEventListener('mouseenter', () => {
                tr.classList.add('table-primary');
            });
            tr.addEventListener('mouseleave', () => {
                tr.classList.remove('table-primary');
            });

            [
                inv.invoice_number,
                inv.invoice_date,
                inv.supplier_name,
                inv.coin_name,
                PURCHASE_RETURN_PAYMENT_LABELS[inv.payment_method] || '',
                purchaseReturnFormatMoney(inv.total)
            ].forEach(v => {
                const td = document.createElement('td');
                td.textContent = v;
                tr.appendChild(td);
            });

            // ✅ عمود الحالة
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
        console.error(e);
        purchaseReturnNotify('فشل البحث عن الفواتير', 'danger');
    }
}

/* =========================================================
   ✅ عند النقر على صف فاتورة — فحص الحالة
   ========================================================= */

function onInvoiceRowClick(inv) {
    // ✅ فحص: هل الفاتورة مرحّلة؟
    if (!inv.is_posted) {
        purchaseReturnNotify(
            'لا يمكن اختيار فاتورة غير مرحّلة. قم بترحيلها أولًا من شاشة فواتير الشراء.',
            'warning'
        );
        return;  // ← لا تفعل شيئًا
    }

    // ✅ الفاتورة مرحّلة — اخترها
    selectOriginalPurchaseInvoice(inv.purchase_invoice_id);
}

/* =========================================================
   ✅ اختيار الفاتورة — تحميل التفاصيل
   ========================================================= */

async function selectOriginalPurchaseInvoice(invoiceId) {
    try {
        const res = await purchaseReturnApiGet(`/operation/purchases/invoicesPurch/${invoiceId}`);
        const { header: h, details } = purchaseReturnExtractPayload(res);

        // ✅ فحص إضافي على مستوى الـ API (حماية مزدوجة)
        if (!h.is_posted) {
            purchaseReturnNotify(
                'لا يمكن اختيار فاتورة غير مرحّلة.',
                'warning'
            );
            return;
        }

        purchaseReturnSetValue('originalPurchaseInvoiceId', h.purchase_invoice_id);
        purchaseReturnSetValue('originalPurchaseInvoiceNo', h.invoice_number);
        purchaseReturnSetValue('purchaseReturnSupplierID', h.account_id);
        purchaseReturnSetValue('purchaseReturnSupplierName', h.supplier_name);
        purchaseReturnSetValue('purchaseReturnCoinsID', h.coin_id);
        purchaseReturnSetValue('purchaseReturnCurrencyName', h.coin_name);
        purchaseReturnSetValue('purchaseReturnWarehouseID', h.warehouse_id || '');
        purchaseReturnSetValue('purchaseReturnWarehouseName', h.warehouse_name || '');
        purchaseReturnSetValue('PurchaseReturnExchangeRate', h.exchange_rate);

        purchaseReturnSetValue('PurchaseReturnPaymentMethodValue', h.payment_method);
        purchaseReturnSetValue('purchaseReturnPaymentAccountId', h.payment_account_id || '');
        purchaseReturnSetValue('purchaseReturnPaymentAccount', h.payment_account_name || '');

        purchaseReturnPaymentMethodChanged();

        const tbody = document.getElementById('purchaseReturnDetails');
        tbody.replaceChildren();

        const tpl = document.getElementById('purchaseReturnRowTemplate');
        let hasAvailableItems = false;

        for (let i = 0; i < details.length; i++) {
            const d = details[i];

            const availRes = await purchaseReturnApiGet(
                `/operation/purchases/returns/available-quantity?purchase_invoice_detail_id=${d.purchase_invoice_detail_id}`
            );
            const availableQty = parseFloat(availRes.available) || 0;

            if (availableQty <= 0) continue;
            hasAvailableItems = true;

            const row = tpl.content.firstElementChild.cloneNode(true);

            row.querySelector('.row-detail-id').value = d.purchase_invoice_detail_id;
            row.querySelector('.row-num').textContent = tbody.children.length + 1;

            row.querySelector('.row-item').value = d.item_name ?? '';
            row.querySelector('.row-item-id').value = d.item_id ?? '';

            row.querySelector('.row-type').value = d.type_name ?? '';
            row.querySelector('.row-type-id').value = d.type_id ?? '';

            row.querySelector('.row-code').value = d.code ?? '';

            row.querySelector('.row-unit').value = d.unit_name ?? '';
            row.querySelector('.row-unit-id').value = d.unit_id ?? '';

            row.querySelector('.row-price').value = d.price;
            row.querySelector('.row-unit-cost').value = d.unit_cost ?? d.price;
            row.querySelector('.row-available').value = availableQty;

            const originalQty = parseFloat(d.quantity) || 0;
            const originalDiscount = parseFloat(d.discount) || 0;
            row.querySelector('.row-original-qty').value = originalQty;
            row.querySelector('.row-original-discount').value = originalDiscount;

            row.querySelector('.row-measure').value = availableQty;

            let proportionalDiscount = 0;
            if (originalQty > 0) {
                proportionalDiscount = (availableQty / originalQty) * originalDiscount;
            }
            row.querySelector('.row-discount').value = proportionalDiscount.toFixed(2);

            row.querySelector('.row-total').value =
                Math.max(0, availableQty * d.price - proportionalDiscount).toFixed(2);

            tbody.appendChild(row);
            enablePurchaseReturnRow(row);
        }

        if (!hasAvailableItems) {
            setEmptyPurchaseReturnMessage();
            purchaseReturnNotify('جميع أصناف هذه الفاتورة تم إرجاعها مسبقاً', 'warning');
        } else {
            calculatePurchaseReturnTotals();
            purchaseReturnNotify('تم تحميل تفاصيل الفاتورة بنجاح', 'success');
        }

        const modalEl = document.getElementById('originalPurchaseInvoiceSearchModal');
        if (modalEl) {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }

        setTimeout(cleanupStuckModals, 300);

    } catch (e) {
        purchaseReturnNotify('فشل تحميل الفاتورة الأصلية: ' + e.message, 'danger');
    }
}

/* =========================================================
   البحث عن مرتجع شراء
   ========================================================= */

function searchPurchaseReturn() {
    const modalEl = document.getElementById('purchaseReturnSearchModal');
    if (!modalEl) return;

    const searchInput = document.getElementById('purchaseReturnSearchInput');
    if (searchInput) searchInput.value = '';

    const tb = document.getElementById('purchaseReturnSearchResults');
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
        performPurchaseReturnSearch();
    }, 250);
}

/**
 * ✅ بحث مؤجّل (Debounced) عن المرتجعات
 */
function debouncedPurchaseReturnSearch() {
    clearTimeout(purchaseReturnSearchTimer);
    purchaseReturnSearchTimer = setTimeout(performPurchaseReturnSearch, 350);
}

/**
 * ✅ تفريغ البحث — إعادة عرض كل المرتجعات
 */
function clearPurchaseReturnSearch() {
    const searchInput = document.getElementById('purchaseReturnSearchInput');
    if (searchInput) searchInput.value = '';

    performPurchaseReturnSearch();

    setTimeout(() => {
        searchInput?.focus();
    }, 100);
}

async function performPurchaseReturnSearch() {
    const search = purchaseReturnGetValue('purchaseReturnSearchInput').trim();
    const tbody = document.getElementById('purchaseReturnSearchResults');
    tbody.replaceChildren();

    try {
        const raw = await purchaseReturnApiGet(`/operation/purchases/returns/list?search=${encodeURIComponent(search)}`);
        const rows = Array.isArray(raw) ? raw : (raw.data || []);

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

            tr.addEventListener('click', () => loadPurchaseReturn(ret.purchase_return_id));

            tr.addEventListener('mouseenter', () => {
                tr.classList.add('table-primary');
            });
            tr.addEventListener('mouseleave', () => {
                tr.classList.remove('table-primary');
            });

            [
                ret.return_number,
                ret.return_date,
                ret.original_invoice_number,
                ret.supplier_name,
                PURCHASE_RETURN_PAYMENT_LABELS[ret.payment_method] || '',
                purchaseReturnFormatMoney(ret.total)
            ].forEach(v => {
                const td = document.createElement('td');
                td.textContent = v;
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
            tdActions.addEventListener('click', (e) => e.stopPropagation());

            tdActions.appendChild(buildPurchaseReturnPostToggleBtn(ret));
            tdActions.appendChild(buildPurchaseReturnDeleteBtn(ret));
            tr.appendChild(tdActions);

            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        purchaseReturnNotify('فشل البحث عن مرتجعات المشتريات', 'danger');
    }
}

function buildPurchaseReturnPostToggleBtn(ret) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm me-1';

    if (ret.is_posted) {
        btn.classList.add('btn-outline-warning');
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> إلغاء الترحيل';
        btn.title = 'فتح المرتجع للتعديل';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            unpostPurchaseReturn(ret.purchase_return_id);
        });
    } else {
        btn.classList.add('btn-outline-success');
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> ترحيل';
        btn.title = 'ترحيل المرتجع وإنشاء القيود';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            postPurchaseReturn(ret.purchase_return_id);
        });
    }

    return btn;
}

function buildPurchaseReturnDeleteBtn(ret) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-danger';
    btn.innerHTML = '<i class="bi bi-trash"></i> حذف';
    btn.title = 'حذف المرتجع نهائيًا';
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        deletePurchaseReturn(ret.purchase_return_id, ret.return_number);
    });
    return btn;
}

async function postPurchaseReturn(id) {
    const ok = await purchaseReturnConfirm('هل تريد ترحيل هذا المرتجع؟\nسيتم إنشاء القيود وحركة المخزون.', {
        title: 'ترحيل مرتجع الشراء',
        okText: 'ترحيل',
    });
    if (!ok) return;

    try {
        const r = await purchaseReturnApiSend(
            `/operation/purchases/returns/${id}/post`,
            'POST',
            {}
        );
        purchaseReturnNotify(r.message || 'تم ترحيل المرتجع بنجاح', 'success');
        performPurchaseReturnSearch();
    } catch (e) {
        purchaseReturnNotify(e.message, 'danger');
    }
}

async function unpostPurchaseReturn(id) {
    const ok = await purchaseReturnConfirm('هل تريد إلغاء ترحيل هذا المرتجع؟\nسيتم حذف القيود وحركة المخزون.', {
        title: 'إلغاء ترحيل مرتجع الشراء',
        okText: 'إلغاء الترحيل',
        danger: true,
    });
    if (!ok) return;

    try {
        const r = await purchaseReturnApiSend(
            `/operation/purchases/returns/${id}/unpost`,
            'POST',
            {}
        );
        purchaseReturnNotify(r.message || 'تم إلغاء ترحيل المرتجع بنجاح', 'success');
        performPurchaseReturnSearch();

        if (currentPurchaseReturnId === id) {
            await loadPurchaseReturn(id);
        }
    } catch (e) {
        purchaseReturnNotify(e.message, 'danger');
    }
}

async function deletePurchaseReturn(id, number) {
    const label = number ? `رقم ${number}` : `#${id}`;
    const ok = await purchaseReturnConfirm(`هل أنت متأكد من حذف المرتجع ${label}؟\nلا يمكن التراجع عن هذه العملية.`, {
        title: 'حذف مرتجع الشراء',
        okText: 'حذف',
        danger: true,
    });
    if (!ok) return;

    try {
        const r = await purchaseReturnApiSend(
            `/operation/purchases/returns/${id}`,
            'DELETE',
            {}
        );
        purchaseReturnNotify(r.message || 'تم حذف المرتجع بنجاح', 'success');
        performPurchaseReturnSearch();

        if (currentPurchaseReturnId === id) {
            clearPurchaseReturnForm();
        }
    } catch (e) {
        purchaseReturnNotify(e.message, 'danger');
    }
}

async function postCurrentPurchaseReturn() {
    if (!currentPurchaseReturnId) {
        purchaseReturnNotify('لا يوجد مرتجع للترحيل', 'warning');
        return;
    }

    const ok = await purchaseReturnConfirm('هل تريد ترحيل هذا المرتجع؟\nسيتم إنشاء القيود وحركة المخزون.', {
        title: 'ترحيل مرتجع الشراء',
        okText: 'ترحيل',
    });
    if (!ok) return;

    try {
        const r = await purchaseReturnApiSend(
            `/operation/purchases/returns/${currentPurchaseReturnId}/post`,
            'POST',
            {}
        );
        purchaseReturnNotify(r.message || 'تم ترحيل المرتجع بنجاح', 'success');
        await loadPurchaseReturn(currentPurchaseReturnId);
    } catch (e) {
        purchaseReturnNotify(e.message, 'danger');
    }
}

async function unpostCurrentPurchaseReturn() {
    if (!currentPurchaseReturnId) {
        purchaseReturnNotify('لا يوجد مرتجع', 'warning');
        return;
    }

    const ok = await purchaseReturnConfirm('هل تريد إلغاء ترحيل هذا المرتجع؟\nسيتم حذف القيود وحركة المخزون.', {
        title: 'إلغاء ترحيل مرتجع الشراء',
        okText: 'إلغاء الترحيل',
        danger: true,
    });
    if (!ok) return;

    try {
        const r = await purchaseReturnApiSend(
            `/operation/purchases/returns/${currentPurchaseReturnId}/unpost`,
            'POST',
            {}
        );
        purchaseReturnNotify(r.message || 'تم إلغاء ترحيل المرتجع بنجاح', 'success');
        await loadPurchaseReturn(currentPurchaseReturnId);
    } catch (e) {
        purchaseReturnNotify(e.message, 'danger');
    }
}

async function loadPurchaseReturn(id) {
    try {
        const res = await purchaseReturnApiGet(`/operation/purchases/returns/${id}`);
        const { header: h, details } = purchaseReturnExtractPayload(res);

        clearPurchaseReturnForm();

        purchaseReturnSetValue('PurchaseReturnNo', h.return_number ?? '');
        purchaseReturnSetValue('PurchaseReturnDate', h.return_date ?? '');
        purchaseReturnSetValue('originalPurchaseInvoiceId', h.original_purchase_invoice_id ?? '');
        purchaseReturnSetValue('originalPurchaseInvoiceNo', h.original_invoice_number ?? '');
        purchaseReturnSetValue('purchaseReturnSupplierID', h.account_id ?? '');
        purchaseReturnSetValue('purchaseReturnSupplierName', h.supplier_name ?? '');
        purchaseReturnSetValue('purchaseReturnCoinsID', h.coin_id ?? '');
        purchaseReturnSetValue('purchaseReturnCurrencyName', h.coin_name ?? '');
        purchaseReturnSetValue('purchaseReturnWarehouseID', h.warehouse_id ?? '');
        purchaseReturnSetValue('purchaseReturnWarehouseName', h.warehouse_name ?? '');
        purchaseReturnSetValue('PurchaseReturnExchangeRate', h.exchange_rate ?? 1);

        purchaseReturnSetValue('PurchaseReturnPaymentMethodValue', h.payment_method);
        purchaseReturnSetValue('purchaseReturnPaymentAccountId', h.payment_account_id ?? '');
        purchaseReturnSetValue('purchaseReturnPaymentAccount', h.payment_account_name ?? '');

        purchaseReturnPaymentMethodChanged();

        const tbody = document.getElementById('purchaseReturnDetails');
        tbody.replaceChildren();

        const tpl = document.getElementById('purchaseReturnRowTemplate');

        details.forEach((d, i) => {
            const row = tpl.content.firstElementChild.cloneNode(true);

            row.querySelector('.row-detail-id').value = d.purchase_invoice_detail_id;
            row.querySelector('.row-num').textContent = i + 1;

            row.querySelector('.row-item').value = d.item_name ?? '';
            row.querySelector('.row-item-id').value = d.item_id ?? '';

            row.querySelector('.row-type').value = d.type_name ?? '';
            row.querySelector('.row-type-id').value = d.type_id ?? '';

            row.querySelector('.row-code').value = d.code ?? '';

            row.querySelector('.row-unit').value = d.unit_name ?? '';
            row.querySelector('.row-unit-id').value = d.unit_id ?? '';

            row.querySelector('.row-price').value = d.price;
            row.querySelector('.row-unit-cost').value = d.unit_cost ?? d.price;
            row.querySelector('.row-available').value = d.available_quantity;
            row.querySelector('.row-measure').value = d.quantity;

            const originalQty = parseFloat(d.quantity) || 0;
            const originalDiscount = parseFloat(d.discount) || 0;
            row.querySelector('.row-original-qty').value = originalQty;
            row.querySelector('.row-original-discount').value = originalDiscount;
            row.querySelector('.row-discount').value = originalDiscount.toFixed(2);

            row.querySelector('.row-total').value = Number(d.total).toFixed(2);

            tbody.appendChild(row);
        });

        if (!details.length) setEmptyPurchaseReturnMessage();

        calculatePurchaseReturnTotals();

        const modalEl = document.getElementById('purchaseReturnSearchModal');
        if (modalEl) {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }

        setTimeout(cleanupStuckModals, 300);

        currentPurchaseReturnId = h.purchase_return_id;
        currentPurchaseReturnIsPosted = !!h.is_posted;

        setPurchaseReturnMode('view');

        purchaseReturnNotify('تم تحميل مرتجع المشتريات بنجاح', 'success');
    } catch (e) {
        purchaseReturnNotify('فشل تحميل مرتجع المشتريات: ' + e.message, 'danger');
    }
}

function hasPurchaseReturnData() {
    const el = document.getElementById('PurchaseReturnNo');
    return el ? el.value.trim() !== '' : false;
}

function editPurchaseReturn() {
    if (!hasPurchaseReturnData() || !currentPurchaseReturnId) {
        purchaseReturnNotify('لا يوجد مرتجع للتعديل', 'warning');
        return;
    }

    setPurchaseReturnMode('edit');
    document.querySelectorAll('#purchaseReturnDetails .return-detail-row')
        .forEach(r => enablePurchaseReturnRow(r));
}

async function cancelPurchaseReturn() {
    const ok = await purchaseReturnConfirm('هل أنت متأكد من إلغاء العملية؟', {
        title: 'إلغاء العملية',
        okText: 'تأكيد الإلغاء',
        danger: true,
    });
    if (!ok) return;
    clearPurchaseReturnForm();
    purchaseReturnNotify('تم إلغاء العملية', 'info');
}

async function savePurchaseReturn() {
    if (isSavingPurchaseReturn) return;

    const number = purchaseReturnGetValue('PurchaseReturnNo').trim();
    if (!number) return purchaseReturnNotify('رقم المرتجع مطلوب', 'warning');

    const invoiceId = purchaseReturnGetValue('originalPurchaseInvoiceId');
    if (!invoiceId) return purchaseReturnNotify('يجب اختيار الفاتورة الأصلية', 'warning');

    const supplierId = purchaseReturnGetValue('purchaseReturnSupplierID');
    if (!supplierId) return purchaseReturnNotify('يجب اختيار المورد', 'warning');

    const coinId = purchaseReturnGetValue('purchaseReturnCoinsID');
    if (!coinId) return purchaseReturnNotify('يجب اختيار العملة', 'warning');

    const methodInt = parseInt(purchaseReturnGetValue('PurchaseReturnPaymentMethodValue')) || 0;
    if (!methodInt) return purchaseReturnNotify('طريقة الدفع غير محددة', 'warning');

    if (methodInt !== 1 && !purchaseReturnGetValue('purchaseReturnPaymentAccountId')) {
        return purchaseReturnNotify('يجب اختيار حساب الدفع', 'warning');
    }

    const rows = document.querySelectorAll('#purchaseReturnDetails .return-detail-row');
    if (!rows.length) return purchaseReturnNotify('أضف صنفًا واحدًا على الأقل للمرتجع', 'warning');

    const details = [];
    for (const row of rows) {
        const detailId = row.querySelector('.row-detail-id')?.value;
        if (!detailId) continue;

        const qty = parseFloat(row.querySelector('.row-measure')?.value) || 0;
        if (qty <= 0) continue;

        const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
        const discount = parseFloat(row.querySelector('.row-discount')?.value) || 0;

        details.push({
            purchase_invoice_detail_id: Number(detailId),
            quantity: qty,
            price: price,
            discount: discount,
        });
    }

    if (!details.length) {
        return purchaseReturnNotify('يجب إدخال كمية أكبر من صفر لصنف واحد على الأقل', 'warning');
    }

    const payload = {
        return_number: number,
        return_date: purchaseReturnGetValue('PurchaseReturnDate'),
        original_purchase_invoice_id: Number(invoiceId),
        account_id: Number(supplierId),
        payment_method: methodInt,
        payment_account_id: purchaseReturnGetValue('purchaseReturnPaymentAccountId') || null,
        coin_id: Number(coinId),
        warehouse_id: purchaseReturnGetValue('purchaseReturnWarehouseID')
            ? Number(purchaseReturnGetValue('purchaseReturnWarehouseID'))
            : null,
        exchange_rate: parseFloat(purchaseReturnGetValue('PurchaseReturnExchangeRate')) || 1,
        statement: purchaseReturnGetValue('PurchaseReturnStatement') || null,
        reference: purchaseReturnGetValue('PurchaseReturnReference') || null,
        details,
    };

    isSavingPurchaseReturn = true;
    const saveBtn = document.getElementById('btnSavePurchaseReturn');
    const saveNewBtn = document.getElementById('btnSaveAndNewPurchaseReturn');
    if (saveBtn) saveBtn.disabled = true;
    if (saveNewBtn) saveNewBtn.disabled = true;

    try {
        let r;
        if (purchaseReturnMode === 'edit' && currentPurchaseReturnId) {
            r = await purchaseReturnApiSend(`/operation/purchases/returns/${currentPurchaseReturnId}`, 'PUT', payload);
            currentPurchaseReturnIsPosted = false;
        } else {
            r = await purchaseReturnApiSend('/operation/purchases/returns', 'POST', payload);
            currentPurchaseReturnId = r.purchase_return_id;
            currentPurchaseReturnIsPosted = !!r.is_posted;
            if (r.return_number) purchaseReturnSetValue('PurchaseReturnNo', r.return_number);
        }
        purchaseReturnNotify(r.message || 'تم الحفظ بنجاح', 'success');
        setPurchaseReturnMode('view');
    } catch (e) {
        let m = e.message;
        if (e.errors) m += ' — ' + Object.values(e.errors).flat().join(' | ');
        purchaseReturnNotify(m, 'danger');
    } finally {
        isSavingPurchaseReturn = false;
        if (purchaseReturnMode !== 'view') {
            if (saveBtn) saveBtn.disabled = false;
            if (saveNewBtn) saveNewBtn.disabled = false;
        }
    }
}

async function saveAndNewPurchaseReturn() {
    await savePurchaseReturn();
    if (purchaseReturnMode === 'view') await resetPurchaseReturn();
}

function printPurchaseReturn() {
    if (!hasPurchaseReturnData() || !currentPurchaseReturnId) {
        purchaseReturnNotify('يجب حفظ المرتجع أولاً قبل الطباعة', 'warning');
        return;
    }

    window.open(
        `/operation/purchases/returns/${currentPurchaseReturnId}/print`,
        '_blank',
        'width=900,height=700'
    );
}

/* =========================================================
   تصدير للـ Window
   ========================================================= */

Object.assign(window, {
    resetPurchaseReturn,
    searchPurchaseReturn,
    performPurchaseReturnSearch,
    debouncedPurchaseReturnSearch,
    clearPurchaseReturnSearch,
    searchOriginalPurchaseInvoice,
    performOriginalPurchaseInvoiceSearch,
    debouncedOriginalPurchaseInvoiceSearch,
    clearOriginalPurchaseInvoiceSearch,
    onInvoiceRowClick,
    selectOriginalPurchaseInvoice,
    calculatePurchaseReturnRow,
    removePurchaseReturnRow,
    clearOriginalInvoiceSelection,
    purchaseReturnPaymentMethodChanged,
    cancelPurchaseReturn,
    savePurchaseReturn,
    saveAndNewPurchaseReturn,
    editPurchaseReturn,
    printPurchaseReturn,
    loadPurchaseReturn,

    postPurchaseReturn,
    unpostPurchaseReturn,
    deletePurchaseReturn,
    postCurrentPurchaseReturn,
    unpostCurrentPurchaseReturn,
    updatePurchaseReturnPostingButtons,
    updatePurchaseReturnSearchButton,
    onOriginalInvoiceFieldClick,
    cleanupStuckModals,
});