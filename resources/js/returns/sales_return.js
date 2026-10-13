/* =========================================================================
   مرتجع المبيعات — إدارة مرتجعات البيع
   متوافق 100% مع header / details / index
   طرق الدفع: credit (1), cash (2), bank (3)
   ========================================================================= */

/* =========================================================
   ثوابت
   ========================================================= */

const SALES_RETURN_PAYMENT_MAP = {
    'credit': 1,
    'cash':   2,
    'bank':   3,
};

const SALES_RETURN_PAYMENT_REVERSE = {
    1: 'credit',
    2: 'cash',
    3: 'bank',
};

const SALES_RETURN_PAYMENT_LABELS = {
    1: 'أجل',
    2: 'نقد',
    3: 'تحويل بنكي',
};

/* =========================================================
   حالة عامة
   ========================================================= */

let salesReturnMode = 'view';
let currentSalesReturnId = null;
let currentSalesReturnIsPosted = false;
let isSavingSalesReturn = false;

let originalInvoiceSearchTimer = null;
let salesReturnSearchTimer = null;

const SALES_RETURN_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* =========================================================
   Helpers — API
   ========================================================= */

function salesReturnApiHeaders(json = false) {
    const h = {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': SALES_RETURN_CSRF,
    };
    if (json) h['Content-Type'] = 'application/json';
    return h;
}

async function salesReturnApiGet(url) {
    const r = await fetch(url, { headers: salesReturnApiHeaders() });
    if (!r.ok) throw new Error(`فشل الطلب: ${r.status}`);
    return r.json();
}

async function salesReturnApiSend(url, method, body) {
    const r = await fetch(url, {
        method,
        headers: salesReturnApiHeaders(true),
        body: JSON.stringify(body),
    });

    const json = await r.json().catch(() => ({}));

    if (!r.ok) {
        let message = json.message || 'فشل الطلب';
        if (json.errors) {
            message = Object.values(json.errors).flat().join(' | ');
        } else if (json.error) {
            message = json.error;
        }

        const e = new Error(message);
        e.errors = json.errors;
        e.raw = json;
        console.error('API Error:', json);
        throw e;
    }
    return json;
}

/* =========================================================
   Helpers — UI
   ========================================================= */

function salesReturnNotify(message, type = 'info') {
    if (typeof window.showSystemToast === 'function') {
        window.showSystemToast(message, type);
    } else {
        console.warn(`[${type}] ${message}`);
        alert(message);
    }
}

function salesReturnGetValue(id) {
    const el = document.getElementById(id);
    return el ? (el.value || '') : '';
}

function salesReturnSetValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value ?? '';
}

function salesReturnExtractPayload(res) {
    if (res && res.header) return { header: res.header, details: res.details || [] };
    if (res && res.data && res.data.header) return { header: res.data.header, details: res.data.details || [] };
    return { header: {}, details: [] };
}

function salesReturnGetPaymentMethodInt() {
    const el = document.getElementById('SalesReturnPaymentMethod');
    if (!el) return 0;
    return SALES_RETURN_PAYMENT_MAP[el.value] || 0;
}

function salesReturnSetPaymentMethodInt(intValue) {
    const el = document.getElementById('SalesReturnPaymentMethod');
    if (!el) return;
    el.value = SALES_RETURN_PAYMENT_REVERSE[intValue] || '';
}

/* =========================================================
   Helper — ضبط قيمة عنصر داخل الصف (حماية من null)
   ========================================================= */

function setRowValue(row, selector, value) {
    const el = row.querySelector(selector);
    if (el) el.value = value ?? '';
}

/* =========================================================
   Cleanup Modals
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
   التهيئة
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {
    cleanupStuckModals();
    setSalesReturnMode('view');
    clearSalesReturnForm();
    bindSalesReturnEvents();

    setTimeout(cleanupStuckModals, 100);
    setTimeout(cleanupStuckModals, 500);
});

window.addEventListener('load', () => {
    setTimeout(cleanupStuckModals, 200);
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') cleanupStuckModals();
});

/* =========================================================
   ربط الأحداث (Delegation)
   ========================================================= */

function bindSalesReturnEvents() {
    document.addEventListener('input', (e) => {
        if (e.target.classList.contains('row-measure') ||
            e.target.classList.contains('row-discount')) {
            calculateSalesReturnRow(e.target);
        }
    });

    const invInput = document.getElementById('originalInvoiceSearchInput');
    if (invInput) {
        invInput.addEventListener('input', () => {
            clearTimeout(originalInvoiceSearchTimer);
            originalInvoiceSearchTimer = setTimeout(performOriginalInvoiceSearch, 350);
        });
    }

    const retInput = document.getElementById('salesReturnSearchInput');
    if (retInput) {
        retInput.addEventListener('input', () => {
            clearTimeout(salesReturnSearchTimer);
            salesReturnSearchTimer = setTimeout(performSalesReturnSearch, 350);
        });
    }
}

/* =========================================================
   Mode Management
   ========================================================= */

function setSalesReturnMode(mode) {
    salesReturnMode = mode;

    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach(row => enableSalesReturnRow(row));

    const newBtn     = document.getElementById('btnNewSalesReturn');
    const saveBtn    = document.getElementById('btnSaveSalesReturn');
    const saveNewBtn = document.getElementById('btnSaveAndNewSalesReturn');
    const cancelBtn  = document.getElementById('btnCancelSalesReturn');

    if (newBtn) newBtn.disabled = (mode !== 'view');

    if (mode === 'view') {
        if (saveBtn)    saveBtn.disabled = true;
        if (saveNewBtn) saveNewBtn.disabled = true;
        if (cancelBtn)  cancelBtn.classList.add('d-none');
    } else {
        if (saveBtn)    saveBtn.disabled = false;
        if (saveNewBtn) saveNewBtn.disabled = false;
        if (cancelBtn)  cancelBtn.classList.remove('d-none');
    }

    const selectInvBtn = document.getElementById('btnSelectOriginalInvoice');
    if (selectInvBtn) selectInvBtn.disabled = (mode === 'view');

    const dateEl = document.getElementById('SalesReturnDate');
    if (dateEl) dateEl.readOnly = (mode === 'view');

    const statementEl = document.getElementById('SalesReturnStatement');
    const referenceEl = document.getElementById('SalesReturnReference');
    if (statementEl) statementEl.readOnly = (mode === 'view');
    if (referenceEl) referenceEl.readOnly = (mode === 'view');

    updateSalesReturnSearchButton();
    updateSalesReturnPostingButtons();
}

function updateSalesReturnSearchButton() {
    const searchBtn = document.getElementById('btnSearchSalesReturn');
    if (!searchBtn) return;

    const hasReturn = !!currentSalesReturnId;
    const isViewMode = salesReturnMode === 'view';

    searchBtn.disabled = !isViewMode || hasReturn;
}

function updateSalesReturnPostingButtons() {
    const editBtn   = document.getElementById('btnEditSalesReturn');
    const postBtn   = document.getElementById('btnPostSalesReturn');
    const unpostBtn = document.getElementById('btnUnpostSalesReturn');
    const printBtn  = document.getElementById('btnPrintSalesReturn');

    const hasData  = hasSalesReturnData();
    const isPosted = currentSalesReturnIsPosted;
    const isView   = salesReturnMode === 'view';

    if (editBtn) {
        editBtn.disabled = !hasData || isPosted || !isView;
        editBtn.classList.toggle('d-none', isPosted);
    }

    if (postBtn) {
        const show = hasData && !isPosted && isView;
        postBtn.classList.toggle('d-none', !show);
        postBtn.disabled = !hasData || isPosted || !isView;
    }

    if (unpostBtn) {
        const show = hasData && isPosted && isView;
        unpostBtn.classList.toggle('d-none', !show);
        unpostBtn.disabled = !hasData || !isPosted || !isView;
    }

    if (printBtn) printBtn.disabled = !hasData;
}

function hasSalesReturnData() {
    const el = document.getElementById('SalesReturnNo');
    return el ? el.value.trim() !== '' : false;
}

/* =========================================================
   Reset / Clear
   ========================================================= */

async function resetSalesReturn() {
    cleanupStuckModals();

    clearSalesReturnForm();
    setSalesReturnMode('add');

    try {
        const res = await salesReturnApiGet('/operation/sales/returns/next-number');
        salesReturnSetValue('SalesReturnNo', res.next_number || '');
    } catch (e) {
        salesReturnNotify('تعذّر جلب رقم المرتجع التالي', 'danger');
    }

    salesReturnSetValue('SalesReturnDate', new Date().toISOString().split('T')[0]);

    searchOriginalInvoice();
}

function clearSalesReturnForm() {
    [
        'SalesReturnNo', 'SalesReturnDate',
        'originalSalesInvoiceId', 'originalSalesInvoiceNo',
        'SalesReturnPaymentMethod',
        'salesReturnPaymentAccount', 'salesReturnPaymentAccountId',
        'salesReturnCustomerID', 'salesReturnCustomerName',
        'salesReturnCoinsID', 'salesReturnCurrencyName',
        'SalesReturnExchangeRate',
        'SalesReturnStatement', 'SalesReturnReference',
    ].forEach(id => salesReturnSetValue(id, ''));

    setEmptySalesReturnMessage();

    const discEl = document.getElementById('totalSalesReturnDiscountDisplay');
    if (discEl) discEl.textContent = '0.00';
    const totalEl = document.getElementById('salesReturnTotalDisplay');
    if (totalEl) totalEl.textContent = '0.00';

    hideSalesReturnPaymentAccounts();

    currentSalesReturnId = null;
    currentSalesReturnIsPosted = false;

    setSalesReturnMode('view');
}

function setEmptySalesReturnMessage() {
    const tbody = document.getElementById('salesReturnDetails');
    if (!tbody) return;

    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 12;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'لا توجد أصناف. اضغط "مرتجع جديد" للبدء.';
    tr.appendChild(td);
    tbody.appendChild(tr);
}

/* =========================================================
   Row Management
   ========================================================= */

function enableSalesReturnRow(row) {
    if (!row) return;

    const measureEl  = row.querySelector('.row-measure');
    const discountEl = row.querySelector('.row-discount');
    const removeBtn  = row.querySelector('button.btn-outline-danger');

    const isView = (salesReturnMode === 'view');

    if (measureEl)  measureEl.disabled  = isView;
    if (discountEl) discountEl.disabled = isView;
    if (removeBtn)  removeBtn.disabled  = isView;
}

function removeSalesReturnRow(btn) {
    if (salesReturnMode === 'view') return;

    const row = btn.closest('tr');
    if (row) row.remove();

    renumberSalesReturnRows();
    calculateSalesReturnTotals();

    const tbody = document.getElementById('salesReturnDetails');
    if (tbody && !tbody.querySelector('.return-detail-row')) {
        setEmptySalesReturnMessage();
    }
}

function renumberSalesReturnRows() {
    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach((r, i) => {
            const numEl = r.querySelector('.row-num');
            if (numEl) numEl.textContent = i + 1;
        });
}

/* =========================================================
   Calculation
   ========================================================= */

function calculateSalesReturnRow(input) {
    const row = input.closest('tr');
    if (!row) return;

    const available = parseFloat(row.querySelector('.row-available')?.value) || 0;
    let qty = parseFloat(row.querySelector('.row-measure')?.value) || 0;
    const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
    let discount = parseFloat(row.querySelector('.row-discount')?.value) || 0;

    if (qty < 0) qty = 0;
    if (qty > available) {
        salesReturnNotify(
            `الكمية المراد إرجاعها (${qty}) أكبر من الكمية المتاحة (${available})`,
            'warning'
        );
        qty = available;
        row.querySelector('.row-measure').value = available;
    }

    const lineTotal = qty * price;
    if (discount < 0) discount = 0;
    if (discount > lineTotal) {
        discount = lineTotal;
        row.querySelector('.row-discount').value = discount.toFixed(2);
    }

    const totalEl = row.querySelector('.row-total');
    if (totalEl) totalEl.value = Math.max(0, lineTotal - discount).toFixed(2);

    calculateSalesReturnTotals();
}

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

    const exchangeRate = parseFloat(document.getElementById('SalesReturnExchangeRate')?.value) || 1;
    const net = Math.max(0, itemsTotal - discountTotal);
    const total = net * exchangeRate;

    const discEl = document.getElementById('totalSalesReturnDiscountDisplay');
    if (discEl) discEl.textContent = discountTotal.toFixed(2);

    const totalEl = document.getElementById('salesReturnTotalDisplay');
    if (totalEl) totalEl.textContent = total.toFixed(2);
}

/* =========================================================
   Payment Method
   ========================================================= */

function salesReturnPaymentMethodChanged() {
    const methodInt = salesReturnGetPaymentMethodInt();
    const container = document.getElementById('salesReturnPaymentAccountContainer');
    const label = document.getElementById('salesReturnPaymentAccountLabel');

    if (!container) return;

    if (methodInt === 2 || methodInt === 3) {
        container.classList.remove('d-none');

        if (label) {
            label.textContent = (methodInt === 3) ? 'الحساب البنكي' : 'الصندوق';
        }
    } else {
        container.classList.add('d-none');
        salesReturnSetValue('salesReturnPaymentAccount', '');
        salesReturnSetValue('salesReturnPaymentAccountId', '');
    }
}

function hideSalesReturnPaymentAccounts() {
    const el = document.getElementById('salesReturnPaymentAccountContainer');
    if (el) el.classList.add('d-none');
}

/* =========================================================
   Search Original Invoice (Modal)
   ========================================================= */

function searchOriginalInvoice() {
    if (salesReturnMode === 'view') return;

    const modalEl = document.getElementById('originalInvoiceSearchModal');
    if (!modalEl) return;

    const searchInput = document.getElementById('originalInvoiceSearchInput');
    if (searchInput) searchInput.value = '';

    const tbody = document.getElementById('originalInvoiceSearchResults');
    if (tbody) {
        tbody.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 7;
        td.className = 'text-center text-muted py-4';
        td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    setTimeout(() => {
        searchInput?.focus();
        performOriginalInvoiceSearch();
    }, 250);
}

async function performOriginalInvoiceSearch() {
    const search = salesReturnGetValue('originalInvoiceSearchInput').trim();
    const tbody = document.getElementById('originalInvoiceSearchResults');
    if (!tbody) return;

    tbody.replaceChildren();

    const loadingTr = document.createElement('tr');
    const loadingTd = document.createElement('td');
    loadingTd.colSpan = 7;
    loadingTd.className = 'text-center text-muted py-4';
    loadingTd.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
    loadingTr.appendChild(loadingTd);
    tbody.appendChild(loadingTr);

    try {
        const raw = await salesReturnApiGet(
            `/operation/sales/invoices/list?search=${encodeURIComponent(search)}`
        );
        const rows = Array.isArray(raw) ? raw : (raw.data || []);

        tbody.replaceChildren();

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
                Number(inv.total).toFixed(2),
            ].forEach(v => {
                const td = document.createElement('td');
                td.textContent = v;
                tr.appendChild(td);
            });

            const tdChoose = document.createElement('td');
            const chooseBtn = document.createElement('button');
            chooseBtn.type = 'button';
            chooseBtn.className = 'btn btn-sm btn-primary';
            chooseBtn.innerHTML = '<i class="bi bi-check2"></i> اختيار';
            chooseBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                onSalesInvoiceRowClick(inv);
            });
            tdChoose.appendChild(chooseBtn);
            tr.appendChild(tdChoose);

            if (!inv.is_posted) tr.classList.add('opacity-75');

            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        tbody.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 7;
        td.className = 'text-center text-danger py-4';
        td.textContent = 'فشل البحث عن الفواتير';
        tr.appendChild(td);
        tbody.appendChild(tr);
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
        salesReturnSetValue('SalesReturnExchangeRate', h.exchange_rate);

        salesReturnSetPaymentMethodInt(h.payment_method);
        salesReturnSetValue('salesReturnPaymentAccountId', h.payment_account_id || '');
        salesReturnSetValue('salesReturnPaymentAccount', h.payment_account_name || '');

        salesReturnPaymentMethodChanged();

        const tbody = document.getElementById('salesReturnDetails');
        tbody.replaceChildren();

        const tpl = document.getElementById('salesReturnRowTemplate');
        let hasAvailableItems = false;

        for (let i = 0; i < details.length; i++) {
            const d = details[i];

            const availRes = await salesReturnApiGet(
                `/operation/sales/returns/available-quantity?sales_invoice_detail_id=${d.sales_invoice_detail_id}`
            );
            const availableQty = parseFloat(availRes.available) || 0;

            if (availableQty <= 0) continue;
            hasAvailableItems = true;

            const row = tpl.content.firstElementChild.cloneNode(true);

            setRowValue(row, '.row-detail-id', d.sales_invoice_detail_id);

            const numEl = row.querySelector('.row-num');
            if (numEl) numEl.textContent = tbody.children.length + 1;

            setRowValue(row, '.row-item', d.item_name);
            setRowValue(row, '.row-item-id', d.item_id);
            setRowValue(row, '.row-type', d.type_name);
            setRowValue(row, '.row-type-id', d.type_id);
            setRowValue(row, '.row-code', d.code);
            setRowValue(row, '.row-unit', d.unit_name);
            setRowValue(row, '.row-unit-id', d.unit_id);
            setRowValue(row, '.row-warehouse', d.warehouse_name);
            setRowValue(row, '.row-warehouse-id', d.warehouse_id);

            setRowValue(row, '.row-price', d.price);
            setRowValue(row, '.row-cost-price', d.cost_price ?? d.price);
            setRowValue(row, '.row-available', availableQty);

            const originalQty = parseFloat(d.quantity) || 0;
            const originalDiscount = parseFloat(d.discount) || 0;
            setRowValue(row, '.row-original-qty', originalQty);
            setRowValue(row, '.row-original-discount', originalDiscount);

            setRowValue(row, '.row-measure', availableQty);

            let proportionalDiscount = 0;
            if (originalQty > 0) {
                proportionalDiscount = (availableQty / originalQty) * originalDiscount;
            }
            setRowValue(row, '.row-discount', proportionalDiscount.toFixed(2));
            setRowValue(
                row, '.row-total',
                Math.max(0, availableQty * d.price - proportionalDiscount).toFixed(2)
            );

            tbody.appendChild(row);
            enableSalesReturnRow(row);
        }

        if (!hasAvailableItems) {
            setEmptySalesReturnMessage();
            salesReturnNotify('جميع أصناف هذه الفاتورة تم إرجاعها مسبقاً', 'warning');
        } else {
            calculateSalesReturnTotals();
            salesReturnNotify('تم تحميل تفاصيل الفاتورة بنجاح', 'success');
        }

        const modalEl = document.getElementById('originalInvoiceSearchModal');
        if (modalEl) {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }

        setTimeout(cleanupStuckModals, 300);

    } catch (e) {
        salesReturnNotify('فشل تحميل الفاتورة الأصلية: ' + e.message, 'danger');
    }
}

/* =========================================================
   Search Sales Returns (Modal)
   ========================================================= */

function searchSalesReturn() {
    const modalEl = document.getElementById('salesReturnSearchModal');
    if (!modalEl) return;

    const searchInput = document.getElementById('salesReturnSearchInput');
    if (searchInput) searchInput.value = '';

    const tbody = document.getElementById('salesReturnSearchResults');
    if (tbody) {
        tbody.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 7;
        td.className = 'text-center text-muted py-4';
        td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    setTimeout(() => {
        searchInput?.focus();
        performSalesReturnSearch();
    }, 250);
}

async function performSalesReturnSearch() {
    const search = salesReturnGetValue('salesReturnSearchInput').trim();
    const tbody = document.getElementById('salesReturnSearchResults');
    if (!tbody) return;

    tbody.replaceChildren();

    const loadingTr = document.createElement('tr');
    const loadingTd = document.createElement('td');
    loadingTd.colSpan = 7;
    loadingTd.className = 'text-center text-muted py-4';
    loadingTd.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
    loadingTr.appendChild(loadingTd);
    tbody.appendChild(loadingTr);

    try {
        const raw = await salesReturnApiGet(
            `/operation/sales/returns/list?search=${encodeURIComponent(search)}`
        );
        const rows = Array.isArray(raw) ? raw : (raw.data || []);

        tbody.replaceChildren();

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
                Number(ret.total).toFixed(2),
            ].forEach(v => {
                const td = document.createElement('td');
                td.textContent = v;
                tr.appendChild(td);
            });

            const tdActions = document.createElement('td');
            tdActions.className = 'text-nowrap';
            tdActions.addEventListener('click', (e) => e.stopPropagation());

            tdActions.appendChild(buildSalesReturnPostToggleBtn(ret));
            tdActions.appendChild(buildSalesReturnDeleteBtn(ret));

            tr.appendChild(tdActions);

            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        tbody.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 7;
        td.className = 'text-center text-danger py-4';
        td.textContent = 'فشل البحث عن المرتجعات';
        tr.appendChild(td);
        tbody.appendChild(tr);
        salesReturnNotify('فشل البحث عن المرتجعات', 'danger');
    }
}

/* =========================================================
   أزرار الإجراءات في نافذة البحث
   ========================================================= */

function buildSalesReturnPostToggleBtn(ret) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm me-1';

    if (ret.is_posted) {
        btn.classList.add('btn-outline-warning');
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> إلغاء الترحيل';
        btn.title = 'إلغاء ترحيل المرتجع (للتعديل)';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            unpostSalesReturn(ret.sales_return_id);
        });
    } else {
        btn.classList.add('btn-outline-success');
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> ترحيل';
        btn.title = 'ترحيل المرتجع وإنشاء القيود';
        btn.addEventListener('click', (e) => {
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
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteSalesReturn(ret.sales_return_id, ret.return_number);
    });
    return btn;
}

/* =========================================================
   Post / Unpost / Delete (من نافذة البحث)
   ========================================================= */

async function postSalesReturn(id) {
    if (!confirm('هل تريد ترحيل هذا المرتجع؟\nسيتم إنشاء القيود وحركة المخزون.')) return;

    try {
        const r = await salesReturnApiSend(
            `/operation/sales/returns/${id}/post`,
            'POST',
            {}
        );
        salesReturnNotify(r.message || 'تم ترحيل المرتجع بنجاح', 'success');
        performSalesReturnSearch();
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

async function unpostSalesReturn(id) {
    if (!confirm('هل تريد إلغاء ترحيل هذا المرتجع؟\nسيتم حذف القيود وحركة المخزون.')) return;

    try {
        const r = await salesReturnApiSend(
            `/operation/sales/returns/${id}/unpost`,
            'POST',
            {}
        );
        salesReturnNotify(r.message || 'تم إلغاء ترحيل المرتجع بنجاح', 'success');
        performSalesReturnSearch();

        if (currentSalesReturnId === id) {
            await loadSalesReturn(id);
        }
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

async function deleteSalesReturn(id, number) {
    const label = number ? `رقم ${number}` : `#${id}`;
    if (!confirm(`هل أنت متأكد من حذف المرتجع ${label}؟\nلا يمكن التراجع عن هذه العملية.`)) return;

    try {
        const r = await salesReturnApiSend(
            `/operation/sales/returns/${id}`,
            'DELETE',
            {}
        );
        salesReturnNotify(r.message || 'تم حذف المرتجع بنجاح', 'success');
        performSalesReturnSearch();

        if (currentSalesReturnId === id) {
            clearSalesReturnForm();
        }
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

/* =========================================================
   Load Sales Return
   ========================================================= */

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
        salesReturnSetValue('SalesReturnExchangeRate', h.exchange_rate ?? 1);

        salesReturnSetPaymentMethodInt(h.payment_method);
        salesReturnSetValue('salesReturnPaymentAccountId', h.payment_account_id ?? '');
        salesReturnSetValue('salesReturnPaymentAccount', h.payment_account_name ?? '');

        salesReturnPaymentMethodChanged();

        const tbody = document.getElementById('salesReturnDetails');
        tbody.replaceChildren();

        const tpl = document.getElementById('salesReturnRowTemplate');

        details.forEach((d, i) => {
            const row = tpl.content.firstElementChild.cloneNode(true);

            setRowValue(row, '.row-detail-id', d.sales_invoice_detail_id);

            const numEl = row.querySelector('.row-num');
            if (numEl) numEl.textContent = i + 1;

            setRowValue(row, '.row-item', d.item_name);
            setRowValue(row, '.row-item-id', d.item_id);
            setRowValue(row, '.row-type', d.type_name);
            setRowValue(row, '.row-type-id', d.type_id);
            setRowValue(row, '.row-code', d.code);
            setRowValue(row, '.row-unit', d.unit_name);
            setRowValue(row, '.row-unit-id', d.unit_id);
            setRowValue(row, '.row-warehouse', d.warehouse_name);
            setRowValue(row, '.row-warehouse-id', d.warehouse_id);

            setRowValue(row, '.row-price', d.price);
            setRowValue(row, '.row-cost-price', d.cost_price ?? d.price);
            setRowValue(row, '.row-available', d.available_quantity ?? 0);
            setRowValue(row, '.row-measure', d.quantity);

            const originalQty = parseFloat(d.quantity) || 0;
            const originalDiscount = parseFloat(d.discount) || 0;
            setRowValue(row, '.row-original-qty', originalQty);
            setRowValue(row, '.row-original-discount', originalDiscount);

            setRowValue(row, '.row-discount', originalDiscount.toFixed(2));
            setRowValue(row, '.row-total', Number(d.total).toFixed(2));

            tbody.appendChild(row);
        });

        if (!details.length) setEmptySalesReturnMessage();

        calculateSalesReturnTotals();

        const modalEl = document.getElementById('salesReturnSearchModal');
        if (modalEl) {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }
        setTimeout(cleanupStuckModals, 300);

        currentSalesReturnId = h.sales_return_id;
        currentSalesReturnIsPosted = !!h.is_posted;

        setSalesReturnMode('view');

        salesReturnNotify('تم تحميل مرتجع البيع بنجاح', 'success');

    } catch (e) {
        salesReturnNotify('فشل تحميل مرتجع البيع: ' + e.message, 'danger');
    }
}

/* =========================================================
   Save
   ========================================================= */

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

    const methodInt = salesReturnGetPaymentMethodInt();
    if (!methodInt) return salesReturnNotify('طريقة الدفع غير محددة', 'warning');

    if (methodInt !== 1 && !salesReturnGetValue('salesReturnPaymentAccountId')) {
        return salesReturnNotify('يجب اختيار حساب الدفع', 'warning');
    }

    const rows = document.querySelectorAll('#salesReturnDetails .return-detail-row');
    if (!rows.length) return salesReturnNotify('أضف صنفًا واحدًا على الأقل للمرتجع', 'warning');

    const details = [];
    let warehouseId = null;

    for (const row of rows) {
        const detailId = row.querySelector('.row-detail-id')?.value;
        if (!detailId) continue;

        const qty = parseFloat(row.querySelector('.row-measure')?.value) || 0;
        if (qty <= 0) continue;

        const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
        const discount = parseFloat(row.querySelector('.row-discount')?.value) || 0;

        if (!warehouseId) {
            warehouseId = row.querySelector('.row-warehouse-id')?.value || null;
        }

        details.push({
            sales_invoice_detail_id: Number(detailId),
            quantity: qty,
            price: price,
            discount: discount,
        });
    }

    if (!details.length) {
        return salesReturnNotify('يجب إدخال كمية أكبر من صفر لصنف واحد على الأقل', 'warning');
    }

    const payload = {
        return_number: number,
        return_date: salesReturnGetValue('SalesReturnDate'),
        original_sales_invoice_id: Number(invoiceId),
        account_id: Number(customerId),
        payment_method: methodInt,
        payment_account_id: salesReturnGetValue('salesReturnPaymentAccountId') || null,
        coin_id: Number(coinId),
        warehouse_id: warehouseId ? Number(warehouseId) : null,
        exchange_rate: parseFloat(salesReturnGetValue('SalesReturnExchangeRate')) || 1,
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
        if (salesReturnMode === 'edit' && currentSalesReturnId) {
            r = await salesReturnApiSend(
                `/operation/sales/returns/${currentSalesReturnId}`,
                'PUT',
                payload
            );
            currentSalesReturnIsPosted = false;
        } else {
            r = await salesReturnApiSend('/operation/sales/returns', 'POST', payload);
            currentSalesReturnId = r.sales_return_id;
            currentSalesReturnIsPosted = !!r.is_posted;
            if (r.return_number) salesReturnSetValue('SalesReturnNo', r.return_number);
        }

        salesReturnNotify(r.message || 'تم الحفظ بنجاح', 'success');
        setSalesReturnMode('view');

    } catch (e) {
        let m = e.message;
        if (e.errors) m += ' — ' + Object.values(e.errors).flat().join(' | ');
        salesReturnNotify(m, 'danger');
    } finally {
        isSavingSalesReturn = false;
        if (salesReturnMode !== 'view') {
            if (saveBtn) saveBtn.disabled = false;
            if (saveNewBtn) saveNewBtn.disabled = false;
        }
    }
}

async function saveAndNewSalesReturn() {
    await saveSalesReturn();
    if (salesReturnMode === 'view') await resetSalesReturn();
}

/* =========================================================
   Edit / Cancel
   ========================================================= */

function editSalesReturn() {
    if (!hasSalesReturnData() || !currentSalesReturnId) {
        salesReturnNotify('لا يوجد مرتجع للتعديل', 'warning');
        return;
    }

    setSalesReturnMode('edit');

    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach(r => enableSalesReturnRow(r));
}

function cancelSalesReturn() {
    if (!confirm('هل أنت متأكد من إلغاء العملية؟')) return;
    clearSalesReturnForm();
    salesReturnNotify('تم إلغاء العملية', 'info');
}

/* =========================================================
   Post / Unpost (من النموذج)
   ========================================================= */

async function postCurrentSalesReturn() {
    if (!currentSalesReturnId) {
        salesReturnNotify('لا يوجد مرتجع للترحيل', 'warning');
        return;
    }

    if (!confirm('هل تريد ترحيل هذا المرتجع؟\nسيتم إنشاء القيود وحركة المخزون.')) return;

    try {
        const r = await salesReturnApiSend(
            `/operation/sales/returns/${currentSalesReturnId}/post`,
            'POST',
            {}
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

    if (!confirm('هل تريد إلغاء ترحيل هذا المرتجع؟\nسيتم حذف القيود وحركة المخزون.')) return;

    try {
        const r = await salesReturnApiSend(
            `/operation/sales/returns/${currentSalesReturnId}/unpost`,
            'POST',
            {}
        );
        salesReturnNotify(r.message || 'تم إلغاء ترحيل المرتجع بنجاح', 'success');
        await loadSalesReturn(currentSalesReturnId);
    } catch (e) {
        salesReturnNotify(e.message, 'danger');
    }
}

/* =========================================================
   Print
   ========================================================= */

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

/* =========================================================
   تصدير للـ window
   ========================================================= */

Object.assign(window, {
    resetSalesReturn,
    searchSalesReturn,
    performOriginalInvoiceSearch,
    performSalesReturnSearch,
    searchOriginalInvoice,
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
    cleanupStuckModals,
});