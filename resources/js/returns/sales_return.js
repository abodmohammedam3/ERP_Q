/* =========================================================================
   مرتجع المبيعات — إدارة مرتجعات البيع
   ========================================================================= */

const RETURN_PAYMENT_TO_INT = { credit: 1, cash: 2, bank: 3, network: 4 };
const RETURN_PAYMENT_TO_STR = { 1: 'credit', 2: 'cash', 3: 'bank', 4: 'network' };

let salesReturnMode = 'view';
let currentSalesReturnId = null;
let isSavingSalesReturn = false;
let salesReturnEditSnapshot = null;

const RETURN_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function returnApiHeaders(json = false) {
    const h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': RETURN_CSRF };
    if (json) h['Content-Type'] = 'application/json';
    return h;
}

async function returnApiGet(url) {
    const r = await fetch(url, { headers: returnApiHeaders() });
    if (!r.ok) throw new Error(`فشل الطلب: ${r.status}`);
    return r.json();
}

async function returnApiSend(url, method, body) {
    const r = await fetch(url, {
        method, headers: returnApiHeaders(true), body: JSON.stringify(body),
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

function returnNotify(message, type = 'info') {
    if (typeof window.showSystemToast === 'function') {
        window.showSystemToast(message, type);
    } else {
        console.warn(`[${type}] ${message}`);
        alert(message);
    }
}

function returnGetValue(id) {
    const el = document.getElementById(id);
    return el ? (el.value || '') : '';
}

function returnSetValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value ?? '';
}

document.addEventListener('DOMContentLoaded', () => {
    setSalesReturnMode('view');
    clearSalesReturnForm();
});

function setSalesReturnMode(mode) {
    salesReturnMode = mode;

    document.querySelectorAll(
        '#SalesReturnDate, #SalesReturnPaymentMethod, #salesReturnPaymentAccount, ' +
        '#SalesReturnStatement, #SalesReturnReference'
    ).forEach(el => { el.disabled = (mode === 'view'); });

    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach(row => enableSalesReturnRow(row));

    const newBtn = document.getElementById('btnNewSalesReturn');
    if (newBtn) newBtn.disabled = (mode !== 'view');

    const searchBtn = document.getElementById('btnSearchSalesReturn');
    if (searchBtn) searchBtn.disabled = (mode !== 'view');

    const selectInvoiceBtn = document.getElementById('btnSelectOriginalInvoice');
    if (selectInvoiceBtn) selectInvoiceBtn.disabled = (mode === 'view');

    const saveBtn = document.getElementById('btnSaveSalesReturn');
    const saveNewBtn = document.getElementById('btnSaveAndNewSalesReturn');
    const cancelBtn = document.getElementById('btnCancelSalesReturn');
    const editBtn = document.getElementById('btnEditSalesReturn');
    const printBtn = document.getElementById('btnPrintSalesReturn');

    if (mode === 'view') {
        if (saveBtn) saveBtn.disabled = true;
        if (saveNewBtn) saveNewBtn.disabled = true;
        if (cancelBtn) cancelBtn.classList.add('d-none');
        if (editBtn) editBtn.disabled = !hasSalesReturnData();
        if (printBtn) printBtn.disabled = !hasSalesReturnData();

        const payContainer = document.getElementById('salesReturnPaymentAccountContainer');
        if (payContainer) payContainer.classList.add('d-none');
    } else {
        if (saveBtn) saveBtn.disabled = false;
        if (saveNewBtn) saveNewBtn.disabled = false;
        if (cancelBtn) cancelBtn.classList.remove('d-none');
        if (editBtn) editBtn.disabled = true;
        if (printBtn) printBtn.disabled = true;
        salesReturnPaymentMethodChanged();
    }
}

async function resetSalesReturn() {
    clearSalesReturnForm();
    setSalesReturnMode('add');

    try {
        const res = await returnApiGet('/operation/sales/returns/next-number');
        returnSetValue('SalesReturnNo', res.next_number || '');
    } catch (e) {
        returnNotify('تعذّر جلب رقم المرتجع التالي', 'danger');
    }

    returnSetValue('SalesReturnDate', new Date().toISOString().split('T')[0]);

    // فتح نافذة اختيار الفاتورة الأصلية تلقائيًا عند الضغط على جديد
    searchOriginalInvoice();
}

function clearSalesReturnForm() {
    document.querySelectorAll(
        '#SalesReturnNo, #SalesReturnDate, #originalSalesInvoiceNo, ' +
        '#SalesReturnPaymentMethod, #salesReturnPaymentAccount, ' +
        '#salesReturnCustomerName, #salesReturnCurrencyName, ' +
        '#SalesReturnExchangeRate, #SalesReturnStatement, #SalesReturnReference'
    ).forEach(el => {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
    });

    ['originalSalesInvoiceId', 'salesReturnCustomerID', 'salesReturnCoinsID', 'salesReturnPaymentAccountId']
        .forEach(id => returnSetValue(id, ''));

    setEmptySalesReturnMessage();

    const discEl = document.getElementById('totalSalesReturnDiscountDisplay');
    if (discEl) discEl.textContent = '0.00';

    const totalEl = document.getElementById('salesReturnTotalDisplay');
    if (totalEl) totalEl.textContent = '0.00';

    hideSalesReturnPaymentAccounts();
    setSalesReturnMode('view');
    currentSalesReturnId = null;
    salesReturnEditSnapshot = null;
}

function setEmptySalesReturnMessage() {
    const tbody = document.getElementById('salesReturnDetails');
    if (!tbody) return;

    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 12;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'اختر الفاتورة الأصلية لإدراج الأصناف المتاحة للإرجاع';
    tr.appendChild(td);
    tbody.appendChild(tr);
}

function enableSalesReturnRow(row) {
    if (!row) return;

    row.querySelectorAll('input, select, button').forEach(el => {
        if (el.classList.contains('row-total') || el.classList.contains('row-available')) return;
        if (el.type === 'hidden') return;
        el.disabled = (salesReturnMode === 'view');
    });
}

function removeSalesReturnRow(btn) {
    if (salesReturnMode === 'view') return;

    const row = btn.closest('tr');
    if (row) row.remove();

    renumberSalesReturnRows();
    calculateSalesReturnTotals();

    const tbody = document.getElementById('salesReturnDetails');
    if (tbody && !tbody.querySelector('.return-detail-row')) setEmptySalesReturnMessage();
}

function renumberSalesReturnRows() {
    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach((r, i) => {
            const numEl = r.querySelector('.row-num');
            if (numEl) numEl.textContent = i + 1;
        });
}

function calculateSalesReturnRow(input) {
    const row = input.closest('tr');
    if (!row) return;

    const available = parseFloat(row.querySelector('.row-available')?.value) || 0;
    let qty = parseFloat(row.querySelector('.row-measure')?.value) || 0;
    const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
    const disc = parseFloat(row.querySelector('.row-discount')?.value) || 0;

    if (qty > available) {
        returnNotify(`الكمية المراد إرجاعها (${qty}) أكبر من الكمية المتاحة (${available})`, 'warning');
        row.querySelector('.row-measure').value = available;
        qty = available;
    }

    const totalEl = row.querySelector('.row-total');
    if (totalEl) totalEl.value = Math.max(0, qty * price - disc).toFixed(2);

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

function salesReturnPaymentMethodChanged() {
    hideSalesReturnPaymentAccounts();

    const methodEl = document.getElementById('SalesReturnPaymentMethod');
    const container = document.getElementById('salesReturnPaymentAccountContainer');
    const input = document.getElementById('salesReturnPaymentAccount');

    if (!methodEl || !container || !input) return;

    const method = methodEl.value;

    if (!method || method === 'credit') {
        container.classList.add('d-none');
        input.disabled = true;
        input.value = '';
        returnSetValue('salesReturnPaymentAccountId', '');
        return;
    }

    container.classList.remove('d-none');
    input.disabled = (salesReturnMode === 'view');

    if (method === 'bank') {
        input.dataset.lookup = 'bank';
        input.dataset.lookupDisplayField = 'bankName';
    } else {
        input.dataset.lookup = 'box';
        input.dataset.lookupDisplayField = 'boxName';
    }

    const labels = { cash: 'الصندوق', bank: 'الحساب البنكي', network: 'حساب المحفظة' };
    const lbl = container.querySelector('label');
    if (lbl) lbl.textContent = labels[method] || 'الحساب';
}

function hideSalesReturnPaymentAccounts() {
    const el = document.getElementById('salesReturnPaymentAccountContainer');
    if (el) el.classList.add('d-none');
}

/* =========================================================
   البحث عن فاتورة مبيعات أصلية
   ========================================================= */

function searchOriginalInvoice() {
    const modalEl = document.getElementById('originalInvoiceSearchModal');
    if (!modalEl) return;

    returnSetValue('originalInvoiceSearchInput', '');
    const tb = document.getElementById('originalInvoiceSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 6;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'أدخل بيانات البحث ثم اضغط بحث';
    tr.appendChild(td);
    tb.appendChild(tr);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => document.getElementById('originalInvoiceSearchInput')?.focus(), 200);
}

async function performOriginalInvoiceSearch() {
    const search = returnGetValue('originalInvoiceSearchInput').trim();
    const tbody = document.getElementById('originalInvoiceSearchResults');
    tbody.replaceChildren();

    try {
        const raw = await returnApiGet(`/operation/sales/invoices/list?search=${encodeURIComponent(search)}`);
        const rows = Array.isArray(raw) ? raw : (raw.data || []);
        const labels = { 1: 'أجل', 2: 'نقد', 3: 'بنك', 4: 'شبكة' };

        rows.forEach(inv => {
            const tr = document.createElement('tr');
            [
                inv.invoice_number,
                inv.invoice_date,
                inv.customer_name,
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
            btn.textContent = 'اختيار الفاتورة';
            btn.addEventListener('click', () => selectOriginalInvoice(inv.sales_invoice_id));
            tdBtn.appendChild(btn);
            tr.appendChild(tdBtn);
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        returnNotify('فشل البحث عن الفواتير', 'danger');
    }
}

async function selectOriginalInvoice(invoiceId) {
    try {
        const res = await returnApiGet(`/operation/sales/invoices/${invoiceId}`);
        const h = res.header;
        const details = res.details || [];

        returnSetValue('originalSalesInvoiceId', h.sales_invoice_id);
        returnSetValue('originalSalesInvoiceNo', h.invoice_number);
        returnSetValue('salesReturnCustomerID', h.account_id);
        returnSetValue('salesReturnCustomerName', h.customer_name);
        returnSetValue('salesReturnCoinsID', h.coin_id);
        returnSetValue('salesReturnCurrencyName', h.coin_name);
        returnSetValue('SalesReturnExchangeRate', h.exchange_rate);
        returnSetValue('SalesReturnPaymentMethod', RETURN_PAYMENT_TO_STR[h.payment_method] || 'credit');
        returnSetValue('salesReturnPaymentAccountId', h.payment_account_id || '');
        returnSetValue('salesReturnPaymentAccount', h.payment_account_name || '');

        salesReturnPaymentMethodChanged();

        const tbody = document.getElementById('salesReturnDetails');
        tbody.replaceChildren();

        const tpl = document.getElementById('salesReturnRowTemplate');
        let hasAvailableItems = false;

        for (let i = 0; i < details.length; i++) {
            const d = details[i];

            // جلب الكمية المتاحة للإرجاع
            const availRes = await returnApiGet(
                `/operation/sales/returns/available-quantity?sales_invoice_detail_id=${d.sales_invoice_detail_id}`
            );
            const availableQty = parseFloat(availRes.available) || 0;

            if (availableQty <= 0) continue; // تخطي الأصناف المرجعة بالكامل
            hasAvailableItems = true;

            const row = tpl.content.firstElementChild.cloneNode(true);

            row.querySelector('.row-detail-id').value = d.sales_invoice_detail_id;
            row.querySelector('.row-num').textContent = tbody.children.length + 1;

            row.querySelector('.row-item').value = d.item_name ?? '';
            row.querySelector('.row-item-id').value = d.item_id ?? '';

            row.querySelector('.row-type').value = d.type_name ?? '';
            row.querySelector('.row-type-id').value = d.type_id ?? '';

            row.querySelector('.row-code').value = d.code ?? '';

            row.querySelector('.row-unit').value = d.unit_name ?? '';
            row.querySelector('.row-unit-id').value = d.unit_id ?? '';

            row.querySelector('.row-warehouse').value = d.warehouse_name ?? '';
            row.querySelector('.row-warehouse-id').value = d.warehouse_id ?? '';

            row.querySelector('.row-price').value = d.price;
            row.querySelector('.row-cost-price').value = d.cost_price ?? 0;
            row.querySelector('.row-available').value = availableQty;

            // الكمية الافتراضية للإرجاع = 0 أو المتاحة
            row.querySelector('.row-measure').value = availableQty;
            row.querySelector('.row-discount').value = 0;
            row.querySelector('.row-total').value = Number(availableQty * d.price).toFixed(2);

            tbody.appendChild(row);
            enableSalesReturnRow(row);
        }

        if (!hasAvailableItems) {
            setEmptySalesReturnMessage();
            returnNotify('جميع أصناف هذه الفاتورة تم إرجاعها مسبقاً', 'warning');
        } else {
            calculateSalesReturnTotals();
            returnNotify('تم تحميل تفاصيل الفاتورة بنجاح', 'success');
        }

        const modalEl = document.getElementById('originalInvoiceSearchModal');
        if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

    } catch (e) {
        returnNotify('فشل تحميل الفاتورة الأصلية: ' + e.message, 'danger');
    }
}

/* =========================================================
   البحث عن مرتجع بيع
   ========================================================= */

function searchSalesReturn() {
    const modalEl = document.getElementById('salesReturnSearchModal');
    if (!modalEl) return;

    returnSetValue('salesReturnSearchInput', '');
    const tb = document.getElementById('salesReturnSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 7;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'أدخل بيانات البحث ثم اضغط بحث';
    tr.appendChild(td);
    tb.appendChild(tr);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => document.getElementById('salesReturnSearchInput')?.focus(), 200);
}

async function performSalesReturnSearch() {
    const search = returnGetValue('salesReturnSearchInput').trim();
    const tbody = document.getElementById('salesReturnSearchResults');
    tbody.replaceChildren();

    try {
        const raw = await returnApiGet(`/operation/sales/returns/list?search=${encodeURIComponent(search)}`);
        const rows = Array.isArray(raw) ? raw : (raw.data || []);
        const labels = { 1: 'أجل', 2: 'نقد', 3: 'بنك', 4: 'شبكة' };

        rows.forEach(ret => {
            const tr = document.createElement('tr');
            [
                ret.return_number,
                ret.return_date,
                ret.original_invoice_number,
                ret.customer_name,
                labels[ret.payment_method] || '',
                Number(ret.total).toFixed(2)
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
            btn.addEventListener('click', () => loadSalesReturn(ret.sales_return_id));
            tdBtn.appendChild(btn);
            tr.appendChild(tdBtn);
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        returnNotify('فشل البحث عن مرتجعات المبيعات', 'danger');
    }
}

async function loadSalesReturn(id) {
    try {
        const res = await returnApiGet(`/operation/sales/returns/${id}`);
        const h = res.header;
        const details = res.details || [];

        clearSalesReturnForm();

        returnSetValue('SalesReturnNo', h.return_number ?? '');
        returnSetValue('SalesReturnDate', h.return_date ?? '');
        returnSetValue('originalSalesInvoiceId', h.original_sales_invoice_id ?? '');
        returnSetValue('originalSalesInvoiceNo', h.original_invoice_number ?? '');
        returnSetValue('salesReturnCustomerID', h.account_id ?? '');
        returnSetValue('salesReturnCustomerName', h.customer_name ?? '');
        returnSetValue('salesReturnCoinsID', h.coin_id ?? '');
        returnSetValue('salesReturnCurrencyName', h.coin_name ?? '');
        returnSetValue('SalesReturnExchangeRate', h.exchange_rate ?? 1);
        returnSetValue('SalesReturnPaymentMethod', RETURN_PAYMENT_TO_STR[h.payment_method] || '');
        returnSetValue('salesReturnPaymentAccountId', h.payment_account_id ?? '');
        returnSetValue('salesReturnPaymentAccount', h.payment_account_name ?? '');
        returnSetValue('SalesReturnStatement', h.statement ?? '');
        returnSetValue('SalesReturnReference', h.reference ?? '');

        salesReturnPaymentMethodChanged();

        const tbody = document.getElementById('salesReturnDetails');
        tbody.replaceChildren();

        const tpl = document.getElementById('salesReturnRowTemplate');

        details.forEach((d, i) => {
            const row = tpl.content.firstElementChild.cloneNode(true);

            row.querySelector('.row-detail-id').value = d.sales_invoice_detail_id;
            row.querySelector('.row-num').textContent = i + 1;

            row.querySelector('.row-item').value = d.item_name ?? '';
            row.querySelector('.row-item-id').value = d.item_id ?? '';

            row.querySelector('.row-type').value = d.type_name ?? '';
            row.querySelector('.row-type-id').value = d.type_id ?? '';

            row.querySelector('.row-code').value = d.code ?? '';

            row.querySelector('.row-unit').value = d.unit_name ?? '';
            row.querySelector('.row-unit-id').value = d.unit_id ?? '';

            row.querySelector('.row-warehouse').value = d.warehouse_name ?? '';
            row.querySelector('.row-warehouse-id').value = d.warehouse_id ?? '';

            row.querySelector('.row-price').value = d.price;
            row.querySelector('.row-cost-price').value = d.cost_price ?? 0;
            row.querySelector('.row-available').value = d.available_quantity;
            row.querySelector('.row-measure').value = d.quantity;
            row.querySelector('.row-discount').value = d.discount;
            row.querySelector('.row-total').value = Number(d.total).toFixed(2);

            tbody.appendChild(row);
        });

        if (!details.length) setEmptySalesReturnMessage();

        calculateSalesReturnTotals();

        const modalEl = document.getElementById('salesReturnSearchModal');
        if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

        currentSalesReturnId = h.sales_return_id;
        setSalesReturnMode('view');
        salesReturnEditSnapshot = null;

        returnNotify('تم تحميل مرتجع المبيعات بنجاح', 'success');
    } catch (e) {
        returnNotify('فشل تحميل مرتجع المبيعات: ' + e.message, 'danger');
    }
}

function hasSalesReturnData() {
    const el = document.getElementById('SalesReturnNo');
    return el ? el.value.trim() !== '' : false;
}

function editSalesReturn() {
    if (!hasSalesReturnData() || !currentSalesReturnId) {
        returnNotify('لا يوجد مرتجع للتعديل', 'warning');
        return;
    }

    setSalesReturnMode('edit');
    document.querySelectorAll('#salesReturnDetails .return-detail-row')
        .forEach(r => enableSalesReturnRow(r));
}

function cancelSalesReturn() {
    if (!confirm('هل أنت متأكد من إلغاء العملية؟')) return;
    clearSalesReturnForm();
    returnNotify('تم إلغاء العملية', 'info');
}

async function saveSalesReturn() {
    if (isSavingSalesReturn) return;

    const number = returnGetValue('SalesReturnNo').trim();
    if (!number) return returnNotify('رقم المرتجع مطلوب', 'warning');

    const invoiceId = returnGetValue('originalSalesInvoiceId');
    if (!invoiceId) return returnNotify('يجب اختيار الفاتورة الأصلية', 'warning');

    const customerId = returnGetValue('salesReturnCustomerID');
    if (!customerId) return returnNotify('يجب اختيار العميل', 'warning');

    const coinId = returnGetValue('salesReturnCoinsID');
    if (!coinId) return returnNotify('يجب اختيار العملة', 'warning');

    const methodStr = returnGetValue('SalesReturnPaymentMethod');
    if (!methodStr) return returnNotify('يجب اختيار طريقة الدفع', 'warning');

    const methodInt = RETURN_PAYMENT_TO_INT[methodStr];
    if (!methodInt) return returnNotify('طريقة الدفع غير صالحة', 'danger');

    if (methodInt !== 1 && !returnGetValue('salesReturnPaymentAccountId')) {
        return returnNotify('يجب اختيار حساب الدفع', 'warning');
    }

    const rows = document.querySelectorAll('#salesReturnDetails .return-detail-row');
    if (!rows.length) return returnNotify('أضف صنفًا واحدًا على الأقل للمرتجع', 'warning');

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
            price: price,
            discount: discount,
        });
    }

    if (!details.length) {
        return returnNotify('يجب إدخال كمية أكبر من صفر لصنف واحد على الأقل', 'warning');
    }

    const payload = {
        return_number: number,
        return_date: returnGetValue('SalesReturnDate'),
        original_sales_invoice_id: Number(invoiceId),
        account_id: Number(customerId),
        payment_method: methodInt,
        payment_account_id: returnGetValue('salesReturnPaymentAccountId') || null,
        coin_id: Number(coinId),
        exchange_rate: parseFloat(returnGetValue('SalesReturnExchangeRate')) || 1,
        statement: returnGetValue('SalesReturnStatement') || null,
        reference: returnGetValue('SalesReturnReference') || null,
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
            r = await returnApiSend(`/operation/sales/returns/${currentSalesReturnId}`, 'PUT', payload);
        } else {
            r = await returnApiSend('/operation/sales/returns', 'POST', payload);
            currentSalesReturnId = r.sales_return_id;
            if (r.return_number) returnSetValue('SalesReturnNo', r.return_number);
        }
        returnNotify(r.message || 'تم الحفظ بنجاح', 'success');
        setSalesReturnMode('view');
    } catch (e) {
        let m = e.message;
        if (e.errors) m += ' — ' + Object.values(e.errors).flat().join(' | ');
        returnNotify(m, 'danger');
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

function printSalesReturn() {
    if (!hasSalesReturnData() || !currentSalesReturnId) {
        returnNotify('يجب حفظ المرتجع أولاً قبل الطباعة', 'warning');
        return;
    }

    window.open(
        `/operation/sales/returns/${currentSalesReturnId}/print`,
        '_blank',
        'width=900,height=700'
    );
}

// تصدير الدوال للـ Window لاستخدامها في onClick في HTML
window.resetSalesReturn = resetSalesReturn;
window.searchSalesReturn = searchSalesReturn;
window.performSalesReturnSearch = performSalesReturnSearch;
window.searchOriginalInvoice = searchOriginalInvoice;
window.performOriginalInvoiceSearch = performOriginalInvoiceSearch;
window.selectOriginalInvoice = selectOriginalInvoice;
window.calculateSalesReturnRow = calculateSalesReturnRow;
window.removeSalesReturnRow = removeSalesReturnRow;
window.salesReturnPaymentMethodChanged = salesReturnPaymentMethodChanged;
window.cancelSalesReturn = cancelSalesReturn;
window.saveSalesReturn = saveSalesReturn;
window.saveAndNewSalesReturn = saveAndNewSalesReturn;
window.editSalesReturn = editSalesReturn;
window.printSalesReturn = printSalesReturn;
