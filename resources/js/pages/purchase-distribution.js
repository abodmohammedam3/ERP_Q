// resources/js/pages/purchase-distribution/index.js

let BootstrapModal = null;
async function ensureBootstrap() {
    if (BootstrapModal) return BootstrapModal;
    if (window.bootstrap?.Modal) { BootstrapModal = window.bootstrap.Modal; return BootstrapModal; }
    try { const m = await import('bootstrap'); BootstrapModal = m.Modal; return BootstrapModal; }
    catch (e) { console.error('[dist] Bootstrap غير متاح:', e); return null; }
}

document.addEventListener('DOMContentLoaded', () => {

    // ============================================================
    // المسارات
    // ============================================================
    const ROUTES = {
        invoices:      '/operation/purchases/distribution/invoices',
        items:         (id) => `/operation/purchases/distribution/invoices/${id}/items`,
        customers:     '/operation/purchases/distribution/customers',
        save:          '/operation/purchases/distribution/save',
        show:          (id) => `/operation/purchases/distribution/${id}`,
        destroy:       (id) => `/operation/purchases/distribution/${id}`,
        issueInvoice:  '/operation/purchases/distribution/issue-invoice',
        customerPage:  '/operation/purchases/distribution/customer-invoices',
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ============================================================
    // الحالة
    // ============================================================
    const state = {
        distributionId: null,
        invoices:       {},
        customers:      [],
        allocation:     {},
        selectedItemId: null,
    };

    // ============================================================
    // أدوات
    // ============================================================
    const $   = (id) => document.getElementById(id);
    const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const fmt = (v, d = 2) => num(v).toFixed(d);
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

    async function api(url, opts = {}) {
        const res = await fetch(url, {
            ...opts,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                ...(opts.headers || {}),
            },
        });
        const text = await res.text();
        let data;
        try { data = text ? JSON.parse(text) : {}; }
        catch { data = { message: text || `HTTP ${res.status}` }; }
        if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
        return data;
    }

    function setAlert(type, msg) {
        const icons = { success:'check-circle', error:'exclamation-triangle', info:'info-circle' };
        const cls = { success:'alert-success', error:'alert-danger', info:'alert-info' }[type] || 'alert-info';
        const area = $('alertArea');
        if (!area) return;
        area.innerHTML = `
            <div class="alert ${cls} border-0 alert-dismissible fade show py-2 d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-${icons[type] || 'info-circle'}"></i>
                <span>${escapeHtml(msg)}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>`;
    }

    // ============================================================
    // مودال الفواتير
    // ============================================================
    let invoiceModalInstance = null;

    async function openInvoiceModal() {
        const modalEl = $('invoicePickerModal');
        if (!modalEl) return;
        const Modal = await ensureBootstrap();
        if (Modal) {
            if (!invoiceModalInstance) invoiceModalInstance = new Modal(modalEl);
            invoiceModalInstance.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }
        loadInvoices();
    }

    function hideInvoiceModal() {
        const modalEl = $('invoicePickerModal');
        if (invoiceModalInstance) invoiceModalInstance.hide();
        else if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    }

    $('btnPickInvoice')?.addEventListener('click', openInvoiceModal);
    $('btnAddInvoice')?.addEventListener('click', openInvoiceModal);
    document.querySelectorAll('#invoicePickerModal [data-bs-dismiss="modal"]').forEach(btn =>
        btn.addEventListener('click', hideInvoiceModal));

    // ============================================================
    // فلترة الفواتير
    // ============================================================
    if ($('invoiceFilterDate')) {
        $('invoiceFilterDate').value = new Date().toISOString().slice(0, 10);
    }
    $('invoiceFilterDate')?.addEventListener('change', loadInvoices);
    $('invoiceFilterSupplier')?.addEventListener('input', debounce(loadInvoices, 400));
    $('invoiceClearBtn')?.addEventListener('click', () => {
        if ($('invoiceFilterDate'))     $('invoiceFilterDate').value = '';
        if ($('invoiceFilterSupplier')) $('invoiceFilterSupplier').value = '';
        loadInvoices();
    });

    async function loadInvoices() {
        $('invoiceLoading')?.classList.remove('d-none');
        $('invoiceEmpty')?.classList.add('d-none');
        $('invoiceError')?.classList.add('d-none');
        const wrap = $('invoiceTableWrapper');
        if (wrap) wrap.style.opacity = '0.4';

        try {
            const params = new URLSearchParams({
                date:     $('invoiceFilterDate')?.value || '',
                supplier: $('invoiceFilterSupplier')?.value.trim() || '',
            });
            const res = await api(`${ROUTES.invoices}?${params}`);
            renderInvoiceRows(res.data || []);
        } catch (e) {
            const errEl = $('invoiceError');
            if (errEl) {
                errEl.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${escapeHtml('فشل تحميل الفواتير: ' + e.message)}`;
                errEl.classList.remove('d-none');
            }
        } finally {
            $('invoiceLoading')?.classList.add('d-none');
            if (wrap) wrap.style.opacity = '1';
        }
    }

    function renderInvoiceRows(rows) {
        const body = $('invoiceTableBody');
        if (!body) return;
        body.innerHTML = '';

        if (!rows.length) {
            $('invoiceEmpty')?.classList.remove('d-none');
            return;
        }

        rows.forEach((inv, i) => {
            const already = !!state.invoices[inv.id];
            const tr = document.createElement('tr');
            tr.style.cursor = already ? 'default' : 'pointer';
            tr.title = already ? 'مضاف بالفعل' : 'اضغط للاختيار';
            if (already) tr.classList.add('table-secondary');

            tr.innerHTML = `
                <td class="text-muted">${i + 1}</td>
                <td class="fw-semibold">${escapeHtml(inv.invoice_number ?? inv.id)}</td>
                <td class="text-muted">${escapeHtml(inv.invoice_date ?? '—')}</td>
                <td>${escapeHtml(inv.supplier ?? '—')}</td>
                <td class="text-end fw-semibold">${fmt(inv.total)}</td>
            `;
            if (!already) tr.addEventListener('click', () => selectInvoice(inv));
            body.appendChild(tr);
        });
    }

    // ============================================================
    // اختيار فاتورة → تحميل أصنافها
    // ============================================================
    async function selectInvoice(inv) {
        if (!inv || state.invoices[inv.id]) return;
        hideInvoiceModal();
        setAlert('info', `جارٍ تحميل أصناف الفاتورة ${inv.invoice_number}...`);

        try {
            const res = await api(ROUTES.items(inv.id));
            if (!res.success) {
                setAlert('error', res.message || 'تعذر تحميل الأصناف.');
                return;
            }

            state.invoices[inv.id] = {
                id:       inv.id,
                number:   inv.invoice_number,
                date:     inv.invoice_date,
                supplier: inv.supplier,
                items:    res.data || [],
            };

            renderInvoicesList();
            renderItems();
            renderSummary();
            setAlert('success', `تمت إضافة الفاتورة ${inv.invoice_number}.`);
        } catch (e) {
            setAlert('error', 'فشل تحميل الأصناف: ' + e.message);
        }
    }

    function removeInvoice(invoiceId) {
        if (hasAllocationForInvoice(invoiceId) &&
            !confirm('توجد توزيعات على هذه الفاتورة. حذفها سيفقدك هذه التوزيعات. متابعة؟')) return;

        delete state.invoices[invoiceId];
        Object.keys(state.allocation).forEach(mdId => {
            const item = findItem(mdId);
            if (!item || item.purchase_invoice_id == invoiceId) delete state.allocation[mdId];
        });

        if (state.selectedItemId && !findItem(state.selectedItemId)) {
            state.selectedItemId = null;
        }

        renderInvoicesList();
        renderItems();
        renderAllocation();
        renderSummary();
    }

    function hasAllocationForInvoice(invoiceId) {
        return Object.keys(state.allocation).some(mdId => {
            const item = findItem(mdId);
            return item && item.purchase_invoice_id == invoiceId && getAllocatedQty(mdId) > 0;
        });
    }

    function findItem(movementDetailId) {
        for (const invId in state.invoices) {
            const found = state.invoices[invId].items.find(it => it.id == movementDetailId);
            if (found) return found;
        }
        return null;
    }

    // ============================================================
    // قائمة الفواتير المضافة
    // ============================================================
    function renderInvoicesList() {
        const box = $('invoicesListBox');
        if (!box) return;
        box.innerHTML = '';

        const invoices = Object.values(state.invoices);
        if (!invoices.length) {
            box.innerHTML = `
                <div class="text-muted small text-center w-100 py-2">
                    <i class="bi bi-inbox fs-4 d-block mb-1 opacity-50"></i>
                    لم تُضف فواتير بعد.
                </div>`;
            return;
        }

        invoices.forEach(inv => {
            const div = document.createElement('div');
            div.className = 'border rounded-3 p-2 d-flex justify-content-between align-items-center bg-white';
            div.style.minWidth = '220px';
            div.style.flex = '1 1 auto';
            div.style.maxWidth = '300px';
            div.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text text-primary"></i>
                    <div>
                        <div class="fw-semibold small">${escapeHtml(inv.number)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">
                            ${escapeHtml(inv.supplier)} · ${escapeHtml(inv.date)}
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-inv="${inv.id}">
                    <i class="bi bi-x-lg"></i>
                </button>
            `;
            box.appendChild(div);
        });

        box.querySelectorAll('button[data-remove-inv]').forEach(btn => {
            btn.addEventListener('click', () => removeInvoice(btn.dataset.removeInv));
        });
    }

    // ============================================================
    // جدول الأصناف
    // ============================================================
    $('itemsSearch')?.addEventListener('input', renderItems);

    function renderItems() {
        const body = $('itemsTableBody');
        if (!body) return;
        body.innerHTML = '';

        const allItems = [];
        Object.values(state.invoices).forEach(inv => {
            inv.items.forEach(it => allItems.push({ ...it, invoice_number: inv.number }));
        });

        if (!allItems.length) {
            $('itemsEmpty')?.classList.remove('d-none');
            if ($('itemsTableWrapper')) $('itemsTableWrapper').style.display = 'none';
            return;
        }

        $('itemsEmpty')?.classList.add('d-none');
        if ($('itemsTableWrapper')) $('itemsTableWrapper').style.display = '';

        const filter = ($('itemsSearch')?.value || '').trim().toLowerCase();

        allItems.forEach((item) => {
            const searchStr = `${item.item_name} ${item.code} ${item.type_name} ${item.unit_name} ${item.invoice_number}`.toLowerCase();
            if (filter && !searchStr.includes(filter)) return;

            const alloc  = getAllocatedQty(item.id);
            const remaining = Math.max(0, num(item.available) - alloc);
            const isSelected = state.selectedItemId == item.id;

            const tr = document.createElement('tr');
            if (isSelected) tr.classList.add('table-primary');

            tr.innerHTML = `
                <td style="width:36px">
                    <button type="button"
                            class="btn btn-sm ${isSelected ? 'btn-primary' : 'btn-outline-primary'}"
                            data-select="${item.id}" title="توزيع">
                        <i class="bi bi-sliders"></i>
                    </button>
                </td>
                <td class="small text-muted">${escapeHtml(item.invoice_number)}</td>
                <td class="fw-semibold">${escapeHtml(item.item_name)}</td>
                <td>${escapeHtml(item.type_name || '—')}</td>
                <td>${escapeHtml(item.unit_name || '—')}</td>
                <td class="small text-muted">${escapeHtml(item.code || '—')}</td>
                <td class="text-end">${fmt(item.quantity, 3)}</td>
                <td class="text-end fw-semibold ${remaining <= 0 ? 'text-danger' : 'text-success'}">
                    ${fmt(remaining, 3)}
                </td>
                <td class="text-end text-muted">${fmt(item.unit_cost)}</td>
                <td class="text-end text-muted">${fmt(item.sale_price)}</td>
            `;
            body.appendChild(tr);
        });

        body.querySelectorAll('button[data-select]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.selectedItemId = btn.dataset.select;
                renderItems();
                renderAllocation();
            });
        });
    }

    $('btnClearAllocation')?.addEventListener('click', () => {
        state.selectedItemId = null;
        renderItems();
        renderAllocation();
    });

    // ============================================================
    // لوحة التوزيع
    // ============================================================
    function getAllocatedQty(movementDetailId) {
        const row = state.allocation[movementDetailId] || {};
        return Object.values(row).reduce((s, q) => s + num(q), 0);
    }

    function renderAllocation() {
        const item = state.selectedItemId ? findItem(state.selectedItemId) : null;

        if (!item) {
            $('allocationEmpty')?.classList.remove('d-none');
            $('allocationBody')?.classList.add('d-none');
            if ($('allocationItemHint')) $('allocationItemHint').textContent = 'لم يُحدد صنف';
            return;
        }

        $('allocationEmpty')?.classList.add('d-none');
        $('allocationBody')?.classList.remove('d-none');

        const alloc = getAllocatedQty(item.id);
        const remaining = Math.max(0, num(item.available) - alloc);

        if ($('allocItemName'))  $('allocItemName').textContent  = item.item_name;
        if ($('allocAvailable')) $('allocAvailable').textContent = fmt(item.available, 3);
        if ($('allocAllocated')) $('allocAllocated').textContent = fmt(alloc, 3);
        if ($('allocRemaining')) $('allocRemaining').textContent = fmt(remaining, 3);
        if ($('allocationItemHint')) $('allocationItemHint').textContent = item.item_name;

        const body = $('allocationTableBody');
        if (!body) return;
        body.innerHTML = '';

        if (!state.customers.length) {
            body.innerHTML = `
                <tr><td colspan="3" class="text-center text-muted py-3 small">
                    <i class="bi bi-person-plus fs-4 d-block mb-1 opacity-50"></i>
                    أضف عملاء أولًا.
                </td></tr>`;
            return;
        }

        const row = state.allocation[item.id] || {};
        state.customers.forEach(c => {
            const qty = row[c.id] ?? '';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="fw-semibold small">${escapeHtml(c.name)}</div>
                    ${c.phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(c.phone)}</div>` : ''}
                </td>
                <td>
                    <input type="number" min="0" step="0.001"
                           class="form-control form-control-sm text-center alloc-input"
                           data-item="${item.id}" data-customer="${c.id}" value="${qty}">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 alloc-remove"
                            data-item="${item.id}" data-customer="${c.id}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });

        body.querySelectorAll('.alloc-input').forEach(inp =>
            inp.addEventListener('blur', () => handleAllocInput(inp)));
        body.querySelectorAll('.alloc-remove').forEach(btn =>
            btn.addEventListener('click', () => {
                const iId = btn.dataset.item, cId = btn.dataset.customer;
                if (state.allocation[iId]) delete state.allocation[iId][cId];
                renderAllocation(); renderItems(); renderSummary();
            }));
    }

    function handleAllocInput(input) {
        const itemId = input.dataset.item;
        const custId = input.dataset.customer;
        const item   = findItem(itemId);
        if (!item) return;

        let qty = num(input.value);
        if (qty < 0) qty = 0;

        const others = Object.entries(state.allocation[itemId] || {})
            .filter(([cid]) => cid != custId)
            .reduce((s, [, q]) => s + num(q), 0);

        const maxForField = num(item.available) - others;
        if (qty > maxForField) {
            qty = Math.max(0, maxForField);
            setAlert('error', `الكمية المتاحة فقط ${fmt(maxForField, 3)}.`);
        }

        state.allocation[itemId] = state.allocation[itemId] || {};
        if (qty === 0) delete state.allocation[itemId][custId];
        else           state.allocation[itemId][custId] = qty;

        input.value = qty === 0 ? '' : qty;
        renderAllocation(); renderItems(); renderSummary();
    }

    // ============================================================
    // Account Picker (اختيار العملاء)
    // ============================================================
    let accountPickerInstance = null;

    async function openAccountPicker() {
        const modalEl = $('AccountPickerModal');
        if (!modalEl) {
            setAlert('error', 'مودال اختيار الحساب غير محمّل.');
            return;
        }

        const Modal = await ensureBootstrap();
        if (Modal) {
            if (!accountPickerInstance) accountPickerInstance = new Modal(modalEl);
            accountPickerInstance.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }

        // إخفاء التبويبات غير المتعلقة بالعملاء
        document.querySelectorAll('#AccountPickerTabs .nav-link').forEach(tab => {
            if (tab.dataset.type === 'customer') {
                tab.classList.remove('d-none');
                tab.classList.add('active');
            } else {
                tab.classList.add('d-none');
                tab.classList.remove('active');
            }
        });

        // إخفاء عمود الرصيد في هذه الشاشة
        const balanceCol = $('AccountPickerColBalance');
        if (balanceCol) balanceCol.classList.add('d-none');

        // تفريغ الحقول
        if ($('AccountPickerSearch')) $('AccountPickerSearch').value = '';
        if ($('AccountPickerResults')) {
            $('AccountPickerResults').innerHTML = `
                <tr><td colspan="5" class="text-center text-muted py-4">ابدأ البحث</td></tr>`;
        }
        if ($('AccountPickerTitle')) $('AccountPickerTitle').textContent = 'اختيار عميل';

        if ($('AccountPickerSearch')) $('AccountPickerSearch').focus();

        loadAccounts();
    }

    function closeAccountPicker() {
        const modalEl = $('AccountPickerModal');
        if (accountPickerInstance) accountPickerInstance.hide();
        else if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    }

    $('btnAddCustomer')?.addEventListener('click', openAccountPicker);
    $('customerPickerTrigger')?.addEventListener('click', openAccountPicker);

    document.querySelectorAll('#AccountPickerModal [data-bs-dismiss="modal"]').forEach(btn =>
        btn.addEventListener('click', closeAccountPicker));

    $('AccountPickerSearch')?.addEventListener('input', debounce(loadAccounts, 400));
    $('AccountPickerClearBtn')?.addEventListener('click', () => {
        if ($('AccountPickerSearch')) $('AccountPickerSearch').value = '';
        loadAccounts();
    });

    async function loadAccounts() {
        const term = ($('AccountPickerSearch')?.value || '').trim();
        const results = $('AccountPickerResults');
        if (!results) return;

        if (term.length > 0 && term.length < 2) {
            results.innerHTML = `
                <tr><td colspan="5" class="text-center text-muted py-4">
                    اكتب حرفين على الأقل للبحث.
                </td></tr>`;
            return;
        }

        results.innerHTML = `
            <tr><td colspan="5" class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2">جارٍ التحميل...</span>
            </td></tr>`;

        try {
            const res = await api(`${ROUTES.customers}?q=${encodeURIComponent(term)}`);
            const rows = res.data || [];

            if (!rows.length) {
                results.innerHTML = `
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        لا نتائج مطابقة.
                    </td></tr>`;
                return;
            }

            results.innerHTML = '';
            rows.forEach(c => {
                const alreadyAdded = state.customers.some(x => x.id == c.id);

                const tr = document.createElement('tr');
                tr.style.cursor = alreadyAdded ? 'default' : 'pointer';
                if (alreadyAdded) tr.classList.add('table-secondary');

                tr.innerHTML = `
                    <td class="text-center text-muted">${escapeHtml(c.code ?? c.id)}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle text-success"></i>
                            <span class="fw-semibold">${escapeHtml(c.name)}</span>
                            ${alreadyAdded
                                ? '<span class="badge bg-secondary-subtle text-secondary ms-1">مضاف</span>'
                                : ''}
                        </div>
                    </td>
                    <td class="d-none"></td>
                    <td>${escapeHtml(c.phone || '—')}</td>
                `;

                if (!alreadyAdded) {
                    tr.addEventListener('click', () => {
                        addCustomer(c);
                        closeAccountPicker();
                    });
                }

                results.appendChild(tr);
            });
        } catch (e) {
            results.innerHTML = `
                <tr><td colspan="5" class="text-center text-danger py-4">
                    فشل البحث: ${escapeHtml(e.message)}
                </td></tr>`;
        }
    }

    function addCustomer(c) {
        if (state.customers.some(x => x.id == c.id)) {
            setAlert('error', 'العميل مضاف بالفعل.');
            return;
        }
        state.customers.push({
            id:    c.id,
            name:  c.name,
            code:  c.code ?? '',
            phone: c.phone || '',
        });
        renderCustomers();
        renderAllocation();
        renderSummary();
    }

    function renderCustomers() {
        const ul = $('selectedCustomersList');
        if (!ul) return;
        ul.innerHTML = '';

        if (!state.customers.length) {
            ul.innerHTML = `
                <li class="list-group-item text-muted text-center small py-3 border-0" id="customersEmpty">
                    <i class="bi bi-person-plus fs-3 d-block mb-1 opacity-50"></i>
                    لم يُضف عملاء بعد.
                </li>`;
            return;
        }

        state.customers.forEach(c => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center py-2 border-0';
            li.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle text-success"></i>
                    <div>
                        <div class="fw-semibold small">${escapeHtml(c.name)}</div>
                        ${c.phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(c.phone)}</div>` : ''}
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove="${c.id}">
                    <i class="bi bi-x-lg"></i>
                </button>
            `;
            ul.appendChild(li);
        });

        ul.querySelectorAll('button[data-remove]').forEach(btn =>
            btn.addEventListener('click', () => removeCustomer(btn.dataset.remove)));
    }

    function removeCustomer(id) {
        const hasQty = Object.values(state.allocation).some(row => num(row[id]) > 0);
        if (hasQty && !confirm('لدى العميل كميات موزعة. حذفها سيحذف هذه الكميات. متابعة؟')) return;

        state.customers = state.customers.filter(c => c.id != id);
        Object.values(state.allocation).forEach(row => { delete row[id]; });
        renderCustomers();
        renderAllocation();
        renderItems();
        renderSummary();
    }

    // ============================================================
    // الملخص
    // ============================================================
    function renderSummary() {
        let itemsCount = 0, qtyAlloc = 0, qtyRemaining = 0, cost = 0, sale = 0;
        const customersSet = new Set();

        Object.values(state.invoices).forEach(inv => {
            inv.items.forEach(item => {
                const alloc = getAllocatedQty(item.id);
                if (alloc > 0) {
                    itemsCount++;
                    qtyAlloc += alloc;
                    cost += alloc * num(item.unit_cost);
                    sale += alloc * num(item.sale_price);
                    Object.keys(state.allocation[item.id] || {}).forEach(cid => customersSet.add(cid));
                }
                qtyRemaining += Math.max(0, num(item.available) - alloc);
            });
        });

        if ($('sumItemsCount'))     $('sumItemsCount').textContent     = itemsCount;
        if ($('sumCustomersCount')) $('sumCustomersCount').textContent = customersSet.size;
        if ($('sumQtyAllocated'))   $('sumQtyAllocated').textContent   = fmt(qtyAlloc, 3);
        if ($('sumQtyRemaining'))   $('sumQtyRemaining').textContent   = fmt(qtyRemaining, 3);
        if ($('sumCost'))           $('sumCost').textContent           = fmt(cost);
        if ($('sumSale'))           $('sumSale').textContent           = fmt(sale);
    }

    // ============================================================
    // الحفظ
    // ============================================================
    $('btnSaveDraft')?.addEventListener('click', () => save());

    async function save() {
        if (!Object.keys(state.invoices).length) { setAlert('error', 'اختر فاتورة واحدة على الأقل.'); return; }
        if (!state.customers.length)             { setAlert('error', 'أضف عميلًا واحدًا على الأقل.'); return; }

        const details = [];
        Object.entries(state.allocation).forEach(([mdId, row]) => {
            const item = findItem(mdId);
            if (!item) return;
            Object.entries(row).forEach(([custId, qty]) => {
                if (num(qty) > 0) {
                    details.push({
                        movement_detail_id:  parseInt(mdId),
                        purchase_invoice_id: parseInt(item.purchase_invoice_id),
                        movement_id:         parseInt(item.movement_id),
                        customer_id:         parseInt(custId),
                        quantity:            num(qty),
                    });
                }
            });
        });

        if (!details.length) { setAlert('error', 'لا توجد كميات موزعة للحفظ.'); return; }

        const payload = {
            id:                state.distributionId,
            distribution_date: $('distributionDate')?.value,
            notes:             $('distributionNotes')?.value || null,
            details,
        };

        if ($('btnSaveDraft')) $('btnSaveDraft').disabled = true;
        try {
            const res = await api(ROUTES.save, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            setAlert('success', res.message + ` (${res.code})`);
            state.distributionId = res.id;
            const url = new URL(window.location);
            url.searchParams.set('id', res.id);
            window.history.replaceState({}, '', url);
        } catch (e) {
            setAlert('error', 'فشل الحفظ: ' + e.message);
        } finally {
            if ($('btnSaveDraft')) $('btnSaveDraft').disabled = false;
        }
    }

    // ============================================================
    // تحميل توزيع للتعديل (عبر ?id=X)
    // ============================================================
    async function loadExisting(id) {
        try {
            const res = await api(ROUTES.show(id));
            if (!res.success) return;

            state.distributionId = res.header.id;
            if ($('distributionDate'))  $('distributionDate').value  = res.header.distribution_date || '';
            if ($('distributionNotes')) $('distributionNotes').value = res.header.notes || '';

            const invoiceIds = [...new Set(res.details.map(d => d.purchase_invoice_id))];
            for (const invId of invoiceIds) {
                try {
                    const invRes = await api(ROUTES.items(invId));
                    if (invRes.success) {
                        state.invoices[invId] = {
                            id:       invId,
                            number:   invRes.invoice.invoice_number,
                            date:     invRes.invoice.invoice_date,
                            supplier: invRes.invoice.supplier,
                            items:    invRes.data || [],
                        };
                    }
                } catch (e) { /* تخطي */ }
            }

            const custMap = {};
            res.details.forEach(d => {
                custMap[d.customer_id] = { id: d.customer_id, name: d.customer_name, phone: '' };
            });
            state.customers = Object.values(custMap);

            res.details.forEach(d => {
                state.allocation[d.movement_detail_id] = state.allocation[d.movement_detail_id] || {};
                state.allocation[d.movement_detail_id][d.customer_id] = d.quantity;
            });

            renderInvoicesList();
            renderCustomers();
            renderItems();
            renderAllocation();
            renderSummary();
            setAlert('info', `تم تحميل التوزيع ${res.header.code} للتعديل.`);
        } catch (e) {
            setAlert('error', 'فشل تحميل التوزيع: ' + e.message);
        }
    }

    // ============================================================
    // شاشة الفوترة
    // ============================================================
    $('btnIssueInvoice')?.addEventListener('click', () => {
        if (!state.distributionId) {
            setAlert('error', 'احفظ التوزيع أولًا قبل الانتقال لشاشة الفوترة.');
            return;
        }
        window.open(ROUTES.customerPage, '_blank');
    });

    // ============================================================
    // إلغاء / جديد / سجل
    // ============================================================
    $('btnCancelAll')?.addEventListener('click', resetScreen);
    $('btnNew')?.addEventListener('click', () => {
        if (!confirm('بدء توزيع جديد؟ سيتم فقدان البيانات الحالية إن لم تُحفظ.')) return;
        window.location.href = window.location.pathname;
    });
    $('btnHistory')?.addEventListener('click', () => {
        window.open('/operation/purchases/distribution/list/all', '_blank');
    });

    function resetScreen() {
        if (!confirm('تفريغ الشاشة من كل البيانات؟')) return;
        state.distributionId = null;
        state.invoices = {};
        state.customers = [];
        state.allocation = {};
        state.selectedItemId = null;
        if ($('distributionNotes')) $('distributionNotes').value = '';
        if ($('alertArea'))         $('alertArea').innerHTML     = '';
        if ($('itemsSearch'))       $('itemsSearch').value       = '';
        renderInvoicesList();
        renderCustomers();
        renderItems();
        renderAllocation();
        renderSummary();
    }

    // ============================================================
    // التهيئة
    // ============================================================
    if ($('distributionDate') && !$('distributionDate').value) {
        $('distributionDate').value = new Date().toISOString().slice(0, 10);
    }

    const urlId = new URLSearchParams(window.location.search).get('id');
    if (urlId) loadExisting(urlId);

    renderInvoicesList();
    renderCustomers();
    renderItems();
    renderAllocation();
    renderSummary();
});