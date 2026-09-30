/* =========================================================================
   مرتجع المشتريات — إدارة مرتجعات الشراء
   ========================================================================= */

const PURCHASE_RETURN_PAYMENT_TO_INT = { credit: 1, cash: 2, bank: 3, network: 4 };
const PURCHASE_RETURN_PAYMENT_TO_STR = { 1: 'credit', 2: 'cash', 3: 'bank', 4: 'network' };

let purchaseReturnMode = 'view';
let currentPurchaseReturnId = null;
let isSavingPurchaseReturn = false;

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

document.addEventListener('DOMContentLoaded', () => {
    setPurchaseReturnMode('view');
    clearPurchaseReturnForm();
});

function setPurchaseReturnMode(mode) {
    purchaseReturnMode = mode;

    document.querySelectorAll(
        '#PurchaseReturnDate, #PurchaseReturnPaymentMethod, #purchaseReturnPaymentAccount, ' +
        '#PurchaseReturnStatement, #PurchaseReturnReference'
    ).forEach(el => { el.disabled = (mode === 'view'); });

    document.querySelectorAll('#purchaseReturnDetails .return-detail-row')
        .forEach(row => enablePurchaseReturnRow(row));

    const newBtn = document.getElementById('btnNewPurchaseReturn');
    if (newBtn) newBtn.disabled = (mode !== 'view');

    const searchBtn = document.getElementById('btnSearchPurchaseReturn');
    if (searchBtn) searchBtn.disabled = (mode !== 'view');

    const selectInvoiceBtn = document.getElementById('btnSelectOriginalPurchaseInvoice');
    if (selectInvoiceBtn) selectInvoiceBtn.disabled = (mode === 'view');

    const saveBtn = document.getElementById('btnSavePurchaseReturn');
    const saveNewBtn = document.getElementById('btnSaveAndNewPurchaseReturn');
    const cancelBtn = document.getElementById('btnCancelPurchaseReturn');
    const editBtn = document.getElementById('btnEditPurchaseReturn');
    const printBtn = document.getElementById('btnPrintPurchaseReturn');

    if (mode === 'view') {
        if (saveBtn) saveBtn.disabled = true;
        if (saveNewBtn) saveNewBtn.disabled = true;
        if (cancelBtn) cancelBtn.classList.add('d-none');
        if (editBtn) editBtn.disabled = !hasPurchaseReturnData();
        if (printBtn) printBtn.disabled = !hasPurchaseReturnData();

        const payContainer = document.getElementById('purchaseReturnPaymentAccountContainer');
        if (payContainer) payContainer.classList.add('d-none');
    } else {
        if (saveBtn) saveBtn.disabled = false;
        if (saveNewBtn) saveNewBtn.disabled = false;
        if (cancelBtn) cancelBtn.classList.remove('d-none');
        if (editBtn) editBtn.disabled = true;
        if (printBtn) printBtn.disabled = true;
        purchaseReturnPaymentMethodChanged();
    }
}

async function resetPurchaseReturn() {
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
    document.querySelectorAll(
        '#PurchaseReturnNo, #PurchaseReturnDate, #originalPurchaseInvoiceNo, ' +
        '#PurchaseReturnPaymentMethod, #purchaseReturnPaymentAccount, ' +
        '#purchaseReturnSupplierName, #purchaseReturnCurrencyName, ' +
        '#purchaseReturnWarehouseName, #PurchaseReturnExchangeRate, ' +
        '#PurchaseReturnStatement, #PurchaseReturnReference'
    ).forEach(el => {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
    });

    ['originalPurchaseInvoiceId', 'purchaseReturnSupplierID', 'purchaseReturnCoinsID', 'purchaseReturnWarehouseID', 'purchaseReturnPaymentAccountId']
        .forEach(id => purchaseReturnSetValue(id, ''));

    setEmptyPurchaseReturnMessage();

    const discEl = document.getElementById('totalPurchaseReturnDiscountDisplay');
    if (discEl) discEl.textContent = '0.00';

    const totalEl = document.getElementById('purchaseReturnTotalDisplay');
    if (totalEl) totalEl.textContent = '0.00';

    hidePurchaseReturnPaymentAccounts();
    setPurchaseReturnMode('view');
    currentPurchaseReturnId = null;
}

function setEmptyPurchaseReturnMessage() {
    const tbody = document.getElementById('purchaseReturnDetails');
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

function enablePurchaseReturnRow(row) {
    if (!row) return;

    row.querySelectorAll('input, select, button').forEach(el => {
        if (el.classList.contains('row-total') || el.classList.contains('row-available')) return;
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
    if (tbody && !tbody.querySelector('.return-detail-row')) setEmptyPurchaseReturnMessage();
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
    const disc = parseFloat(row.querySelector('.row-discount')?.value) || 0;

    if (qty > available) {
        purchaseReturnNotify(`الكمية المراد إرجاعها (${qty}) أكبر من الكمية المتاحة (${available})`, 'warning');
        row.querySelector('.row-measure').value = available;
        qty = available;
    }

    const totalEl = row.querySelector('.row-total');
    if (totalEl) totalEl.value = Math.max(0, qty * price - disc).toFixed(2);

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
    if (discEl) discEl.textContent = discountTotal.toFixed(2);

    const totalEl = document.getElementById('purchaseReturnTotalDisplay');
    if (totalEl) totalEl.textContent = total.toFixed(2);
}

function purchaseReturnPaymentMethodChanged() {
    hidePurchaseReturnPaymentAccounts();

    const methodEl = document.getElementById('PurchaseReturnPaymentMethod');
    const container = document.getElementById('purchaseReturnPaymentAccountContainer');
    const input = document.getElementById('purchaseReturnPaymentAccount');

    if (!methodEl || !container || !input) return;

    const method = methodEl.value;

    if (!method || method === 'credit') {
        container.classList.add('d-none');
        input.disabled = true;
        input.value = '';
        purchaseReturnSetValue('purchaseReturnPaymentAccountId', '');
        return;
    }

    container.classList.remove('d-none');
    input.disabled = (purchaseReturnMode === 'view');

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

function hidePurchaseReturnPaymentAccounts() {
    const el = document.getElementById('purchaseReturnPaymentAccountContainer');
    if (el) el.classList.add('d-none');
}

/* =========================================================
   البحث عن فاتورة شراء أصلية
   ========================================================= */

function searchOriginalPurchaseInvoice() {
    const modalEl = document.getElementById('originalPurchaseInvoiceSearchModal');
    if (!modalEl) return;

    purchaseReturnSetValue('originalPurchaseInvoiceSearchInput', '');
    const tb = document.getElementById('originalPurchaseInvoiceSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 6;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'أدخل بيانات البحث ثم اضغط بحث';
    tr.appendChild(td);
    tb.appendChild(tr);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => document.getElementById('originalPurchaseInvoiceSearchInput')?.focus(), 200);
}

async function performOriginalPurchaseInvoiceSearch() {
    const search = purchaseReturnGetValue('originalPurchaseInvoiceSearchInput').trim();
    const tbody = document.getElementById('originalPurchaseInvoiceSearchResults');
    tbody.replaceChildren();

    try {
        const raw = await purchaseReturnApiGet(`/operation/purchases/invoicesPurch/list?search=${encodeURIComponent(search)}`);
        const rows = Array.isArray(raw) ? raw : (raw.data || []);
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
            btn.textContent = 'اختيار الفاتورة';
            btn.addEventListener('click', () => selectOriginalPurchaseInvoice(inv.purchase_invoice_id));
            tdBtn.appendChild(btn);
            tr.appendChild(tdBtn);
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        purchaseReturnNotify('فشل البحث عن الفواتير', 'danger');
    }
}

async function selectOriginalPurchaseInvoice(invoiceId) {
    try {
        const res = await purchaseReturnApiGet(`/operation/purchases/invoicesPurch/${invoiceId}`);
        const h = res.header;
        const details = res.details || [];

        purchaseReturnSetValue('originalPurchaseInvoiceId', h.purchase_invoice_id);
        purchaseReturnSetValue('originalPurchaseInvoiceNo', h.invoice_number);
        purchaseReturnSetValue('purchaseReturnSupplierID', h.account_id);
        purchaseReturnSetValue('purchaseReturnSupplierName', h.supplier_name);
        purchaseReturnSetValue('purchaseReturnCoinsID', h.coin_id);
        purchaseReturnSetValue('purchaseReturnCurrencyName', h.coin_name);
        purchaseReturnSetValue('purchaseReturnWarehouseID', h.warehouse_id || '');
        purchaseReturnSetValue('purchaseReturnWarehouseName', h.warehouse_name || '');
        purchaseReturnSetValue('PurchaseReturnExchangeRate', h.exchange_rate);
        purchaseReturnSetValue('PurchaseReturnPaymentMethod', PURCHASE_RETURN_PAYMENT_TO_STR[h.payment_method] || 'credit');
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

            row.querySelector('.row-warehouse').value = d.warehouse_name ?? '';
            row.querySelector('.row-warehouse-id').value = d.warehouse_id ?? '';

            row.querySelector('.row-price').value = d.price;
            row.querySelector('.row-unit-cost').value = d.unit_cost ?? d.price;
            row.querySelector('.row-available').value = availableQty;

            row.querySelector('.row-measure').value = availableQty;
            row.querySelector('.row-discount').value = 0;
            row.querySelector('.row-total').value = Number(availableQty * d.price).toFixed(2);

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
        if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

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

    purchaseReturnSetValue('purchaseReturnSearchInput', '');
    const tb = document.getElementById('purchaseReturnSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 7;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'أدخل بيانات البحث ثم اضغط بحث';
    tr.appendChild(td);
    tb.appendChild(tr);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => document.getElementById('purchaseReturnSearchInput')?.focus(), 200);
}

async function performPurchaseReturnSearch() {
    const search = purchaseReturnGetValue('purchaseReturnSearchInput').trim();
    const tbody = document.getElementById('purchaseReturnSearchResults');
    tbody.replaceChildren();

    try {
        const raw = await purchaseReturnApiGet(`/operation/purchases/returns/list?search=${encodeURIComponent(search)}`);
        const rows = Array.isArray(raw) ? raw : (raw.data || []);
        const labels = { 1: 'أجل', 2: 'نقد', 3: 'بنك', 4: 'شبكة' };

        rows.forEach(ret => {
            const tr = document.createElement('tr');
            [
                ret.return_number,
                ret.return_date,
                ret.original_invoice_number,
                ret.supplier_name,
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
            btn.addEventListener('click', () => loadPurchaseReturn(ret.purchase_return_id));
            tdBtn.appendChild(btn);
            tr.appendChild(tdBtn);
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        purchaseReturnNotify('فشل البحث عن مرتجعات المشتريات', 'danger');
    }
}

async function loadPurchaseReturn(id) {
    try {
        const res = await purchaseReturnApiGet(`/operation/purchases/returns/${id}`);
        const h = res.header;
        const details = res.details || [];

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
        purchaseReturnSetValue('PurchaseReturnPaymentMethod', PURCHASE_RETURN_PAYMENT_TO_STR[h.payment_method] || '');
        purchaseReturnSetValue('purchaseReturnPaymentAccountId', h.payment_account_id ?? '');
        purchaseReturnSetValue('purchaseReturnPaymentAccount', h.payment_account_name ?? '');
        purchaseReturnSetValue('PurchaseReturnStatement', h.statement ?? '');
        purchaseReturnSetValue('PurchaseReturnReference', h.reference ?? '');

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

            row.querySelector('.row-warehouse').value = d.warehouse_name ?? '';
            row.querySelector('.row-warehouse-id').value = d.warehouse_id ?? '';

            row.querySelector('.row-price').value = d.price;
            row.querySelector('.row-unit-cost').value = d.unit_cost ?? d.price;
            row.querySelector('.row-available').value = d.available_quantity;
            row.querySelector('.row-measure').value = d.quantity;
            row.querySelector('.row-discount').value = d.discount;
            row.querySelector('.row-total').value = Number(d.total).toFixed(2);

            tbody.appendChild(row);
        });

        if (!details.length) setEmptyPurchaseReturnMessage();

        calculatePurchaseReturnTotals();

        const modalEl = document.getElementById('purchaseReturnSearchModal');
        if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

        currentPurchaseReturnId = h.purchase_return_id;
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

function cancelPurchaseReturn() {
    if (!confirm('هل أنت متأكد من إلغاء العملية؟')) return;
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

    const methodStr = purchaseReturnGetValue('PurchaseReturnPaymentMethod');
    if (!methodStr) return purchaseReturnNotify('يجب اختيار طريقة الدفع', 'warning');

    const methodInt = PURCHASE_RETURN_PAYMENT_TO_INT[methodStr];
    if (!methodInt) return purchaseReturnNotify('طريقة الدفع غير صالحة', 'danger');

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
        warehouse_id: purchaseReturnGetValue('purchaseReturnWarehouseID') ? Number(purchaseReturnGetValue('purchaseReturnWarehouseID')) : null,
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
        } else {
            r = await purchaseReturnApiSend('/operation/purchases/returns', 'POST', payload);
            currentPurchaseReturnId = r.purchase_return_id;
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

// تصدير الدوال للـ Window
window.resetPurchaseReturn = resetPurchaseReturn;
window.searchPurchaseReturn = searchPurchaseReturn;
window.performPurchaseReturnSearch = performPurchaseReturnSearch;
window.searchOriginalPurchaseInvoice = searchOriginalPurchaseInvoice;
window.performOriginalPurchaseInvoiceSearch = performOriginalPurchaseInvoiceSearch;
window.selectOriginalPurchaseInvoice = selectOriginalPurchaseInvoice;
window.calculatePurchaseReturnRow = calculatePurchaseReturnRow;
window.removePurchaseReturnRow = removePurchaseReturnRow;
window.purchaseReturnPaymentMethodChanged = purchaseReturnPaymentMethodChanged;
window.cancelPurchaseReturn = cancelPurchaseReturn;
window.savePurchaseReturn = savePurchaseReturn;
window.saveAndNewPurchaseReturn = saveAndNewPurchaseReturn;
window.editPurchaseReturn = editPurchaseReturn;
window.printPurchaseReturn = printPurchaseReturn;
