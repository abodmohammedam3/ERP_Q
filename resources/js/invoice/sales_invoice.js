/* =========================================================================
   فاتورة البيع — ربط كامل بـ Laravel API
   ========================================================================= */

/* =========================================================
   الثوابت
   ========================================================= */

const SALES_PAYMENT_TO_INT = { credit: 1, cash: 2, bank: 3, network: 4 };
const SALES_PAYMENT_TO_STR = { 1: 'credit', 2: 'cash', 3: 'bank', 4: 'network' };

/* =========================================================
   الحالة العامة
   ========================================================= */

let salesMode = 'view';
let currentSalesInvoiceId = null;
let isSavingSales = false;
let salesEditSnapshot = null;

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

/* =========================================================
   Lookup Hooks
   ========================================================= */

function registerSalesLookupHooks() {
    if (typeof LookupConfigs === 'undefined') return;

    // العميل → التركيز على العملة (عبر data-lookup-next)

    // العملة → تحديث سعر الصرف
    LookupConfigs.currency.onSelect = (row) => {
        salesSetValue('SalesExchangeRate', row.coinsExchangeRate ?? 1);
        calculateSalesTotals();
    };

    // الصنف → تخزين ID + جلب التكلفة
    LookupConfigs.item.onSelect = async (row, target) => {
        const tr = target.closest('tr');
        if (!tr || !tr.classList.contains('sales-detail-row')) return;

        tr.querySelector('.row-item-id').value = row.itemID ?? '';
        await fetchSalesCost(tr);
    };

    // النوع → تخزين ID
    LookupConfigs.type.onSelect = (row, target) => {
        const tr = target.closest('tr');
        if (!tr) return;
        tr.querySelector('.row-type-id').value = row.id ?? '';
    };

    // المخزن → تخزين ID + جلب التكلفة
    LookupConfigs.warehouse.onSelect = async (row, target) => {
        const tr = target.closest('tr');
        if (!tr) return;

        tr.querySelector('.row-warehouse-id').value = row.StockID ?? '';
        await fetchSalesCost(tr);
    };
}

/* =========================================================
   جلب التكلفة
   ========================================================= */

async function fetchSalesCost(tr) {
    if (!tr) return;

    const itemId = tr.querySelector('.row-item-id')?.value;
    const warehouseId = tr.querySelector('.row-warehouse-id')?.value;

    if (!itemId || !warehouseId) return;

    try {
        const res = await salesApiGet(
            `/operation/sales/invoices/helpers/last-cost?item_id=${itemId}&warehouse_id=${warehouseId}`
        );
        const costInput = tr.querySelector('.row-cost-price');
        if (costInput) costInput.value = res.cost || 0;
    } catch (e) {
        console.warn('Failed to fetch cost', e);
    }
}

/* =========================================================
   التهيئة
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {
    registerSalesLookupHooks();
    setSalesMode('view');
    clearSalesForm();
});

/* =========================================================
   أوضاع الشاشة
   ========================================================= */

function setSalesMode(mode) {
    salesMode = mode;

    // حقول الرأس
    document.querySelectorAll(
        '#SalesInvoiceDate, #SalesPaymentMethod, #salesPaymentAccount, ' +
        '#customerName, #salesCurrencyName, #SalesExchangeRate, ' +
        '#SalesStatement, #SalesReference'
    ).forEach(el => { el.disabled = (mode === 'view'); });

    // صفوف التفاصيل
    document.querySelectorAll('#salesInvoiceDetails .sales-detail-row')
        .forEach(row => enableSalesRow(row));

    const addBtn = document.getElementById('btnAddSalesRow');
    if (addBtn) addBtn.disabled = (mode === 'view');

    const saveBtn = document.getElementById('btnSaveSalesInvoice');
    const saveNewBtn = document.getElementById('btnSaveAndNewSalesInvoice');
    const cancelBtn = document.getElementById('btnCancelSalesInvoice');
    const editBtn = document.getElementById('btnEditSalesInvoice');
    const printBtn = document.getElementById('btnPrintSalesInvoice');

    if (mode === 'view') {
        if (saveBtn) saveBtn.disabled = true;
        if (saveNewBtn) saveNewBtn.disabled = true;
        if (cancelBtn) cancelBtn.classList.add('d-none');
        if (editBtn) editBtn.disabled = !hasSalesInvoiceData();
        if (printBtn) printBtn.disabled = !hasSalesInvoiceData();

        const payInput = document.getElementById('salesPaymentAccount');
        if (payInput) payInput.disabled = true;

        const payContainer = document.getElementById('salesPaymentAccountContainer');
        if (payContainer) payContainer.classList.add('d-none');
    } else {
        if (saveBtn) saveBtn.disabled = false;
        if (saveNewBtn) saveNewBtn.disabled = false;
        if (cancelBtn) cancelBtn.classList.remove('d-none');
        if (editBtn) editBtn.disabled = true;
        if (printBtn) printBtn.disabled = true;
        salesPaymentMethodChanged();
    }
}

/* =========================================================
   Reset / Clear
   ========================================================= */

async function resetSalesInvoice() {
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
        '#SalesExchangeRate, #SalesStatement, #SalesReference'
    ).forEach(el => {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
    });

    ['customerID', 'salesCoinsID', 'salesPaymentAccountId']
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

    setEmptySalesMessage();

    const discEl = document.getElementById('totalSalesDiscountDisplay');
    if (discEl) discEl.textContent = '0.00';

    const totalEl = document.getElementById('salesInvoiceTotalDisplay');
    if (totalEl) totalEl.textContent = '0.00';

    hideSalesPaymentAccounts();

    setSalesMode('view');
    currentSalesInvoiceId = null;
    salesEditSnapshot = null;
}

function setEmptySalesMessage() {
    const tbody = document.getElementById('salesInvoiceDetails');
    if (!tbody) return;

    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 11;
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

    const emptyTd = tbody.querySelector('td[colspan="11"]');
    if (emptyTd) while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    const tpl = document.getElementById('salesRowTemplate');
    if (!tpl) {
        salesNotify('قالب الصف غير موجود', 'danger');
        return;
    }

    const row = tpl.content.firstElementChild.cloneNode(true);

    // تعبئة الوحدات
    const unitSelect = row.querySelector('.row-unit');
    if (unitSelect && typeof lookupCache !== 'undefined') {
        const units = lookupCache.units || [];
        units.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.UnitID;
            opt.textContent = u.UnitName;
            unitSelect.appendChild(opt);
        });

        // الوحدة الافتراضية = "حبه"
        const defaultUnit = units.find(u => u.UnitName === 'حبه');
        if (defaultUnit) unitSelect.value = defaultUnit.UnitID;
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
        const p = parseFloat(row.querySelector('.row-price')?.value) || 0;
        const d = parseFloat(row.querySelector('.row-discount')?.value) || 0;
        itemsTotal += q * p;
        discountTotal += d;
    });

    const exchangeRate = parseFloat(document.getElementById('SalesExchangeRate')?.value) || 1;

    const net = Math.max(0, itemsTotal - discountTotal);
    const total = net * exchangeRate;

    const discEl = document.getElementById('totalSalesDiscountDisplay');
    if (discEl) discEl.textContent = discountTotal.toFixed(2);

    const totalEl = document.getElementById('salesInvoiceTotalDisplay');
    if (totalEl) totalEl.textContent = total.toFixed(2);
}

function salesExchangeRateChanged() { calculateSalesTotals(); }

/* =========================================================
   طرق الدفع
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

    if (!methodEl || !container || !input) return;

    const method = methodEl.value;

    if (!method || method === 'credit') {
        container.classList.add('d-none');
        input.disabled = true;
        input.value = '';
        salesSetValue('salesPaymentAccountId', '');
        return;
    }

    container.classList.remove('d-none');
    input.disabled = (salesMode === 'view');

    // تغيير نوع Lookup
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

function hideSalesPaymentAccounts() {
    const el = document.getElementById('salesPaymentAccountContainer');
    if (el) el.classList.add('d-none');
}

/* =========================================================
   البحث عن فاتورة
   ========================================================= */

function searchSalesInvoice() {
    const modalEl = document.getElementById('salesInvoiceSearchModal');
    if (!modalEl) return;

    salesSetValue('salesInvoiceSearchInput', '');
    const tb = document.getElementById('salesInvoiceSearchResults');
    tb.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 7;
    td.className = 'text-center text-muted py-4';
    td.textContent = 'أدخل بيانات البحث ثم اضغط بحث';
    tr.appendChild(td);
    tb.appendChild(tr);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => document.getElementById('salesInvoiceSearchInput')?.focus(), 200);
}

async function performSalesInvoiceSearch() {
    const search = salesGetValue('salesInvoiceSearchInput').trim();
    const tbody = document.getElementById('salesInvoiceSearchResults');
    tbody.replaceChildren();

    try {
        const raw = await salesApiGet(`/operation/sales/invoices/list?search=${encodeURIComponent(search)}`);
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
            btn.textContent = 'عرض';
            btn.addEventListener('click', () => loadSalesInvoice(inv.sales_invoice_id));
            tdBtn.appendChild(btn);
            tr.appendChild(tdBtn);
            tbody.appendChild(tr);
        });
    } catch (e) {
        console.error(e);
        salesNotify('فشل البحث عن الفواتير', 'danger');
    }
}

/* =========================================================
   تحميل فاتورة
   ========================================================= */

async function loadSalesInvoice(id) {
    try {
        const res = await salesApiGet(`/operation/sales/invoices/${id}`);
        const h = res.header;
        const details = res.details || [];

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

            // تعبئة الوحدات
            const unitSel = row.querySelector('.row-unit');
            if (unitSel && typeof lookupCache !== 'undefined') {
                (lookupCache.units || []).forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.UnitID;
                    opt.textContent = u.UnitName;
                    if (String(u.UnitID) === String(d.unit_id)) opt.selected = true;
                    unitSel.appendChild(opt);
                });
            }

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
        if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

        currentSalesInvoiceId = h.sales_invoice_id;
        setSalesMode('view');
        salesEditSnapshot = null;

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
            unit: r.querySelector('.row-unit')?.value || '',
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

    setSalesMode('edit');
    document.querySelectorAll('#salesInvoiceDetails .sales-detail-row')
        .forEach(r => enableSalesRow(r));
    document.querySelectorAll(
        '#SalesInvoiceDate, #SalesPaymentMethod, #salesPaymentAccount, #customerName, ' +
        '#salesCurrencyName, #SalesExchangeRate, #SalesStatement, #SalesReference'
    ).forEach(el => el.disabled = false);

    salesPaymentMethodChanged();
    salesEditSnapshot = takeSalesSnapshot();
}

function cancelSalesInvoice() {
    if (!confirm('هل أنت متأكد من إلغاء العملية؟')) return;
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
            unit_id: row.querySelector('.row-unit')?.value
                ? Number(row.querySelector('.row-unit').value) : null,
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
        } else {
            r = await salesApiSend('/operation/sales/invoices', 'POST', payload);
            currentSalesInvoiceId = r.sales_invoice_id;
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
        notify('يجب حفظ الفاتورة أولاً قبل الطباعة', 'warning');
        return;
    }

    window.open(
        `/operation/sales/invoices/${currentSalesInvoiceId}/print`,
        '_blank',
        'width=900,height=700'
    );
}
/* =========================================================
   تصدير للـ HTML
   ========================================================= */

window.resetSalesInvoice = resetSalesInvoice;
window.addSalesRow = addSalesRow;
window.removeSalesRow = removeSalesRow;
window.calculateSalesRow = calculateSalesRow;
window.salesExchangeRateChanged = salesExchangeRateChanged;
window.salesPaymentMethodChanged = salesPaymentMethodChanged;
window.searchSalesInvoice = searchSalesInvoice;
window.performSalesInvoiceSearch = performSalesInvoiceSearch;
window.loadSalesInvoice = loadSalesInvoice;
window.editSalesInvoice = editSalesInvoice;
window.cancelSalesInvoice = cancelSalesInvoice;
window.saveSalesInvoice = saveSalesInvoice;
window.saveAndNewSalesInvoice = saveAndNewSalesInvoice;
window.printSalesInvoice = printSalesInvoice;