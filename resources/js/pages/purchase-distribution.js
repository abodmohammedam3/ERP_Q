// resources/js/pages/purchase-distribution/index.js

let BootstrapModal = null;
async function ensureBootstrap() {
    if (BootstrapModal) return BootstrapModal;
    if (window.bootstrap?.Modal) { BootstrapModal = window.bootstrap.Modal; return BootstrapModal; }
    try { const m = await import('bootstrap'); BootstrapModal = m.Modal; return BootstrapModal; }
    catch (e) { console.error('[dist] Bootstrap غير متاح:', e); return null; }
}

document.addEventListener('DOMContentLoaded', () => {

    const ROUTES = {
        invoices:      '/operation/purchases/distribution/invoices',
        items:         (id) => `/operation/purchases/distribution/invoices/${id}/items`,
        customers:     '/operation/purchases/distribution/customers',
        save:          '/operation/purchases/distribution/save',
        show:          (id) => `/operation/purchases/distribution/${id}`,
        issueInvoice:  '/operation/purchases/distribution/issue-invoice',
        customerPage:  '/operation/purchases/distribution/customer-invoices',
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const state = {
        distributionId:  null,
        invoices:        {},
        activeInvoiceId: null,
        customerInfo:    {},
        allocation:      {},
        modalItemId:     null,
        modalInvoiceId:  null,
    };

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

    // ============================================================
    // Toast & Confirm
    // ============================================================
    function showToast(msg, type = 'info') {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(msg, type);
            return;
        }
        const icons = { success:'check-circle', danger:'exclamation-triangle', warning:'exclamation-circle', info:'info-circle' };
        const cls   = { success:'alert-success', danger:'alert-danger', warning:'alert-warning', info:'alert-info' };
        const area = $('alertArea');
        if (!area) { console.log(`[${type}] ${msg}`); return; }
        area.innerHTML = `
            <div class="alert ${cls[type] || 'alert-info'} border-0 alert-dismissible fade show py-2 d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-${icons[type] || 'info-circle'}"></i>
                <span>${escapeHtml(msg)}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>`;
    }
    const toastSuccess = (m) => showToast(m, 'success');
    const toastError   = (m) => showToast(m, 'danger');
    const toastWarning = (m) => showToast(m, 'warning');
    const toastInfo    = (m) => showToast(m, 'info');

    async function confirmDialog(msg, opts = {}) {
        if (typeof window.appConfirm === 'function') {
            try { return await window.appConfirm(msg, opts); }
            catch (e) { console.warn('[dist] appConfirm فشل، استخدام confirm الأصلي', e); }
        }
        return window.confirm(msg);
    }

    function updateModeBadge() {
        const badge = $('distributionModeBadge');
        if (!badge) return;
        if (state.distributionId) {
            badge.className = 'badge bg-warning-subtle text-warning';
            badge.innerHTML = `<i class="bi bi-pencil-square"></i> وضع التعديل`;
        } else {
            badge.className = 'badge bg-success-subtle text-success';
            badge.innerHTML = `<i class="bi bi-plus-circle"></i> توزيع جديد`;
        }
    }

    // ============================================================
    // الحقول الفارغة
    // ============================================================
    function findEmptyAllocations() {
        const empty = [];
        Object.entries(state.allocation).forEach(([itemId, row]) => {
            Object.entries(row).forEach(([custId, qty]) => {
                if (num(qty) <= 0) {
                    empty.push({ itemId: parseInt(itemId), custId: parseInt(custId) });
                }
            });
        });
        return empty;
    }

    function getEmptyCustomersForItem(itemId) {
        const row = state.allocation[itemId] || {};
        const names = [];
        Object.entries(row).forEach(([cid, qty]) => {
            if (num(qty) <= 0) {
                const info = state.customerInfo[cid];
                names.push(info?.name || `#${cid}`);
            }
        });
        return names;
    }

    function removeEmptyCustomersForItem(itemId) {
        const row = state.allocation[itemId] || {};
        const removedCount = Object.keys(row).filter(cid => num(row[cid]) <= 0).length;
        Object.keys(row).forEach(cid => {
            if (num(row[cid]) <= 0) delete row[cid];
        });
        if (Object.keys(row).length === 0) delete state.allocation[itemId];
        pruneOrphanCustomers();
        return removedCount;
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
    document.querySelectorAll('#invoicePickerModal [data-bs-dismiss="modal"]').forEach(btn =>
        btn.addEventListener('click', hideInvoiceModal));

    if ($('invoiceFilterDate')) $('invoiceFilterDate').value = new Date().toISOString().slice(0, 10);
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
                errEl.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${escapeHtml('فشل: ' + e.message)}`;
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
        if (!rows.length) { $('invoiceEmpty')?.classList.remove('d-none'); return; }
        rows.forEach((inv, i) => {
            const already = !!state.invoices[inv.id];
            const tr = document.createElement('tr');
            tr.style.cursor = already ? 'default' : 'pointer';
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

    async function selectInvoice(inv) {
        if (!inv || state.invoices[inv.id]) return;
        hideInvoiceModal();
        toastInfo(`جارٍ تحميل أصناف الفاتورة ${inv.invoice_number}...`);
        try {
            const res = await api(ROUTES.items(inv.id));
            if (!res.success) { toastError(res.message || 'تعذر التحميل.'); return; }
            state.invoices[inv.id] = {
                id: inv.id, number: inv.invoice_number, date: inv.invoice_date,
                supplier: inv.supplier, items: res.data || [],
            };
            state.activeInvoiceId = inv.id;
            renderTabs();
            renderItems();
            renderSummary();
            toastSuccess(`أُضيفت الفاتورة ${inv.invoice_number}.`);
        } catch (e) {
            toastError('فشل: ' + e.message);
        }
    }

    async function removeInvoice(invoiceId) {
        const inv = state.invoices[invoiceId];
        if (!inv) return;

        const hasAlloc = hasAllocationForInvoice(invoiceId);
        const msg = hasAlloc
            ? `الفاتورة ${inv.number} تحتوي على توزيعات. حذفها سيحذف كل توزيعاتها. متابعة؟`
            : `حذف الفاتورة ${inv.number}؟`;
        const ok = await confirmDialog(msg, {
            title: hasAlloc ? 'حذف فاتورة تحتوي توزيعات' : 'حذف فاتورة',
            okText: 'حذف', danger: true,
        });
        if (!ok) return;

        delete state.invoices[invoiceId];
        Object.keys(state.allocation).forEach(mdId => {
            if (inv.items.some(it => it.id == mdId)) delete state.allocation[mdId];
        });
        pruneOrphanCustomers();

        if (state.activeInvoiceId == invoiceId) {
            const remaining = Object.keys(state.invoices);
            state.activeInvoiceId = remaining[0] || null;
        }

        renderTabs();
        renderItems();
        renderSummary();
        toastInfo(`حُذفت الفاتورة ${inv.number}.`);
    }

    function hasAllocationForInvoice(invoiceId) {
        const inv = state.invoices[invoiceId];
        if (!inv) return false;
        return inv.items.some(it => getAllocatedQty(it.id) > 0);
    }

    function pruneOrphanCustomers() {
        const inUse = new Set();
        Object.values(state.allocation).forEach(row =>
            Object.keys(row).forEach(cid => inUse.add(String(cid))));
        Object.keys(state.customerInfo).forEach(cid => {
            if (!inUse.has(String(cid))) delete state.customerInfo[cid];
        });
    }

    function findItem(movementDetailId) {
        for (const invId in state.invoices) {
            const found = state.invoices[invId].items.find(it => it.id == movementDetailId);
            if (found) return found;
        }
        return null;
    }

    function findInvoiceOfItem(movementDetailId) {
        for (const invId in state.invoices) {
            if (state.invoices[invId].items.some(it => it.id == movementDetailId)) {
                return state.invoices[invId];
            }
        }
        return null;
    }

    // ============================================================
    // شريط التبويبات
    // ============================================================
    function renderTabs() {
        const box = $('invoicesTabsBox');
        if (!box) return;
        box.innerHTML = '';

        const invoices = Object.values(state.invoices);

        if (!invoices.length) {
            box.innerHTML = `
                <div class="text-muted small text-center w-100 py-2">
                    <i class="bi bi-inbox fs-4 d-block mb-1 opacity-50"></i>
                    لم تُضف فواتير بعد — اضغط «إضافة فاتورة» للبدء.
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="btnAddInvoice">
                    <i class="bi bi-plus-lg"></i> إضافة فاتورة
                </button>`;
            $('btnAddInvoice')?.addEventListener('click', openInvoiceModal);
            return;
        }

        invoices.forEach(inv => {
            const distCount  = inv.items.filter(it => getAllocatedQty(it.id) > 0).length;
            const totalCount = inv.items.length;

            let badgeClass = 'bg-secondary-subtle text-secondary';
            if (distCount === totalCount && totalCount > 0) badgeClass = 'bg-success-subtle text-success';
            else if (distCount > 0)                          badgeClass = 'bg-warning-subtle text-warning';

            const isActive = state.activeInvoiceId == inv.id;

            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex align-items-stretch';
            wrapper.innerHTML = `
                <button type="button"
                        class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} d-flex align-items-center gap-2 rounded-end-0"
                        data-tab-id="${inv.id}">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="fw-semibold">${escapeHtml(inv.number)}</span>
                    <span class="badge ${isActive ? 'bg-white text-primary' : badgeClass}">
                        ${distCount}/${totalCount}
                    </span>
                </button>
                <button type="button"
                        class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} rounded-start-0 border-start-0 px-2"
                        data-close-id="${inv.id}" title="حذف الفاتورة">
                    <i class="bi bi-x-lg"></i>
                </button>
            `;
            box.appendChild(wrapper);
        });

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'btn btn-sm btn-outline-primary';
        addBtn.innerHTML = `<i class="bi bi-plus-lg"></i> إضافة فاتورة`;
        addBtn.addEventListener('click', openInvoiceModal);
        box.appendChild(addBtn);

        box.querySelectorAll('button[data-tab-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.activeInvoiceId = parseInt(btn.dataset.tabId);
                renderTabs();
                renderItems();
            });
        });
        box.querySelectorAll('button[data-close-id]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeInvoice(btn.dataset.closeId);
            });
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

        const activeInv = state.activeInvoiceId ? state.invoices[state.activeInvoiceId] : null;

        if ($('activeInvoiceLabel')) {
            $('activeInvoiceLabel').textContent = activeInv
                ? `${activeInv.number} — ${activeInv.supplier}`
                : '—';
        }

        if (!activeInv || !activeInv.items.length) {
            $('itemsEmpty')?.classList.remove('d-none');
            if ($('itemsTableWrapper')) $('itemsTableWrapper').style.display = 'none';
            if ($('itemsEmpty')) {
                $('itemsEmpty').innerHTML = activeInv
                    ? '<i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i><div class="small">لا توجد أصناف في هذه الفاتورة.</div>'
                    : '<i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i><div class="small">اختر فاتورة أو أضف واحدة.</div>';
            }
            return;
        }

        $('itemsEmpty')?.classList.add('d-none');
        if ($('itemsTableWrapper')) $('itemsTableWrapper').style.display = '';

        const filter = ($('itemsSearch')?.value || '').trim().toLowerCase();

        activeInv.items.forEach((item) => {
            const searchStr = `${item.item_name} ${item.code} ${item.type_name} ${item.unit_name}`.toLowerCase();
            if (filter && !searchStr.includes(filter)) return;

            const alloc = getAllocatedQty(item.id);
            const remaining = Math.max(0, num(item.available) - alloc);
            const hasAlloc = alloc > 0;

            const tr = document.createElement('tr');
            if (hasAlloc) tr.classList.add('table-success');
            tr.innerHTML = `
                <td style="width:36px">
                    <button type="button"
                            class="btn btn-sm ${hasAlloc ? 'btn-primary' : 'btn-outline-primary'}"
                            data-open-modal="${item.id}" title="توزيع">
                        <i class="bi bi-sliders"></i>
                    </button>
                </td>
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

        body.querySelectorAll('button[data-open-modal]').forEach(btn =>
            btn.addEventListener('click', () => openAllocationModal(btn.dataset.openModal)));
    }

    // ============================================================
    // مودال التوزيع
    // ============================================================
    let allocationModalInstance = null;
    let allowAllocationModalClose = false;

    $('allocationModal')?.addEventListener('hide.bs.modal', async (e) => {
        if (allowAllocationModalClose) return;

        const itemId = state.modalItemId;
        if (!itemId) return;

        const emptyNames = getEmptyCustomersForItem(itemId);
        if (emptyNames.length === 0) return;

        e.preventDefault();
        e.stopPropagation();

        const preview = emptyNames.slice(0, 3).join('، ');
        const more    = emptyNames.length > 3 ? ` و${emptyNames.length - 3} آخرين` : '';

        const ok = await confirmDialog(
            `يوجد ${emptyNames.length} عميل بحقل فارغ: «${preview}»${more}.\n\n` +
            `هل تريد حذفهم وإغلاق المودال؟\n` +
            `إن رفضت، سيبقى المودال مفتوحًا لإكمال الكميات.`,
            { title: 'عملاء بدون كمية', okText: 'حذفهم وإغلاق', cancelText: 'إلغاء', danger: true }
        );

        if (!ok) {
            document.querySelectorAll('#allocationTableBody .alloc-input').forEach(inp => {
                inp.classList.remove('is-invalid');
                const row = state.allocation[itemId] || {};
                const qty = num(row[inp.dataset.customer]);
                if (qty <= 0 && !inp.readOnly) inp.classList.add('is-invalid');
            });
            return;
        }

        const removed = removeEmptyCustomersForItem(itemId);

        renderAllocationModal();
        renderItems();
        renderSummary();
        renderTabs();

        if (removed > 0) {
            toastInfo(`حُذف ${removed} عميل بدون كمية.`);
        }

        allowAllocationModalClose = true;
        if (allocationModalInstance) allocationModalInstance.hide();
        else if ($('allocationModal')) {
            $('allocationModal').style.display = 'none';
            $('allocationModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    });

    async function openAllocationModal(itemId) {
        const modalEl = $('allocationModal');
        if (!modalEl) return;

        const inv = findInvoiceOfItem(itemId);
        if (!inv) return;

        state.modalItemId = itemId;
        state.modalInvoiceId = inv.id;
        allowAllocationModalClose = false;
        renderAllocationModal();

        const Modal = await ensureBootstrap();
        if (Modal) {
            if (!allocationModalInstance) allocationModalInstance = new Modal(modalEl);
            allocationModalInstance.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }
    }

    function getAllocatedQty(movementDetailId) {
        const row = state.allocation[movementDetailId] || {};
        return Object.values(row).reduce((s, q) => s + num(q), 0);
    }

    function renderAllocationModal() {
        const itemId = state.modalItemId;
        const item   = findItem(itemId);
        const inv    = state.modalInvoiceId ? state.invoices[state.modalInvoiceId] : null;
        if (!item || !inv) return;

        const alloc       = getAllocatedQty(itemId);
        const remaining   = Math.max(0, num(item.available) - alloc);
        const noRemaining = remaining <= 0;

        if ($('allocItemName'))      $('allocItemName').textContent      = item.item_name;
        if ($('allocInvoiceNumber')) $('allocInvoiceNumber').textContent = inv.number;
        if ($('allocAvailable'))     $('allocAvailable').textContent     = fmt(item.available, 3);
        if ($('allocAllocated'))     $('allocAllocated').textContent     = fmt(alloc, 3);
        if ($('allocRemaining'))     $('allocRemaining').textContent     = fmt(remaining, 3);

        const idx   = inv.items.findIndex(it => it.id == itemId);
        const total = inv.items.length;
        if ($('allocItemCounter')) $('allocItemCounter').textContent = `${idx + 1} من ${total}`;
        if ($('btnPrevItem'))      $('btnPrevItem').disabled = idx <= 0;
        if ($('btnNextItem'))      $('btnNextItem').disabled = idx >= total - 1;

        const tbody = $('allocationTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        const row = state.allocation[itemId] || {};
        const customerIds = Object.keys(row);

        if (!customerIds.length) {
            tbody.innerHTML = `
                <tr><td colspan="3" class="text-center text-muted py-3 small">
                    <i class="bi bi-person-plus fs-4 d-block mb-1 opacity-50"></i>
                    أضف عميلًا لتوزيع الكميات.
                </td></tr>`;
            return;
        }

        customerIds.forEach(cid => {
            const info     = state.customerInfo[cid] || { name: '—', phone: '' };
            const curQty   = num(row[cid]);
            const isEmpty  = curQty <= 0;
            const editable = !isEmpty || !noRemaining;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="fw-semibold small">
                        ${escapeHtml(info.name)}
                        ${isEmpty ? '<i class="bi bi-exclamation-circle-fill text-danger ms-1" title="لم تُدخل كمية"></i>' : ''}
                    </div>
                    ${info.phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(info.phone)}</div>` : ''}
                </td>
                <td>
                        <input type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            dir="ltr"
                            class="form-control form-control-sm text-center alloc-input ${editable ? '' : 'bg-body-secondary'} ${isEmpty && editable ? 'border-danger' : ''}"
                            data-customer="${cid}"
                            value="${curQty || ''}"
                            ${editable ? '' : 'readonly'}
                            placeholder="${editable ? 'أدخل كمية' : 'لا توجد كمية متبقية'}">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 alloc-remove"
                            data-customer="${cid}" title="إزالة العميل">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.alloc-input').forEach(inp => {
            if (inp.readOnly) {
                inp.addEventListener('click', (e) => {
                    e.preventDefault();
                    toastWarning('لا توجد كمية متبقية من هذا الصنف');
                    inp.blur();
                });
                inp.addEventListener('focus', () => inp.blur());
            } else {
                // keydown — يدعم Backspace/Delete/Ctrl+A/C/V/X/Z
                inp.addEventListener('keydown', (e) => {
                    const ch = e.key;

                    if (ch === 'Enter') {
                        e.preventDefault();
                        inp.blur();
                        return;
                    }

                    if (['Backspace','Delete','Tab','Escape','ArrowLeft','ArrowRight','Home','End'].includes(ch)) {
                        return;
                    }

                    if ((e.ctrlKey || e.metaKey) && ['a','c','v','x','z','y'].includes(String(ch).toLowerCase())) {
                        return;
                    }

                    if (/[0-9]/.test(ch)) return;

                    if (ch === '.') {
                        if (inp.value.includes('.')) e.preventDefault();
                        return;
                    }

                    e.preventDefault();
                });

                inp.addEventListener('paste', (e) => {
                    const txt = (e.clipboardData || window.clipboardData).getData('text');
                    if (!/^\d*\.?\d*$/.test(txt.trim())) {
                        e.preventDefault();
                        toastWarning('يُسمح فقط بالأرقام والنقطة العشرية.');
                    }
                });

                inp.addEventListener('drop', (e) => {
                    e.preventDefault();
                    toastWarning('لا يمكن إفلات نص هنا.');
                });

                inp.addEventListener('blur', () => handleModalAllocInput(inp));
            }
        });

        tbody.querySelectorAll('.alloc-remove').forEach(btn =>
            btn.addEventListener('click', async () => {
                const cid  = btn.dataset.customer;
                const info = state.customerInfo[cid];
                const qty  = num(state.allocation[itemId]?.[cid] || 0);

                if (qty > 0) {
                    const ok = await confirmDialog(
                        `العميل «${info?.name || ''}» لديه كمية ${fmt(qty, 3)}. إزالة العميل ستحذف هذه الكمية. متابعة؟`,
                        { title: 'إزالة عميل', okText: 'إزالة', danger: true }
                    );
                    if (!ok) return;
                }

                if (state.allocation[itemId]) delete state.allocation[itemId][cid];
                if (Object.keys(state.allocation[itemId] || {}).length === 0) {
                    delete state.allocation[itemId];
                }

                pruneOrphanCustomers();
                renderAllocationModal();
                renderItems();
                renderSummary();
                renderTabs();
            }));
    }

    function handleModalAllocInput(input) {
        const itemId = state.modalItemId;
        const custId = input.dataset.customer;
        const item   = findItem(itemId);
        if (!item) return;

        const raw = String(input.value ?? '').trim();

        if (raw === '') {
            state.allocation[itemId] = state.allocation[itemId] || {};
            state.allocation[itemId][custId] = 0;
            renderAllocationModal();
            renderItems();
            renderSummary();
            renderTabs();
            return;
        }

        if (num(raw) === 0) {
            toastError('الكمية يجب أن تكون أكبر من صفر — أدخل قيمة موجبة أو امسح الحقل لترك العميل بدون كمية.');
            input.value = '';
            state.allocation[itemId] = state.allocation[itemId] || {};
            state.allocation[itemId][custId] = 0;
            renderAllocationModal();
            renderItems();
            renderSummary();
            renderTabs();
            return;
        }

        let qty = num(raw);
        if (qty < 0) {
            toastError('الكمية لا يمكن أن تكون سالبة.');
            input.value = '';
            state.allocation[itemId] = state.allocation[itemId] || {};
            state.allocation[itemId][custId] = 0;
            renderAllocationModal();
            renderItems();
            renderSummary();
            renderTabs();
            return;
        }

        const others = Object.entries(state.allocation[itemId] || {})
            .filter(([cid]) => cid != custId)
            .reduce((s, [, q]) => s + num(q), 0);

        const maxForField = num(item.available) - others;
        if (qty > maxForField) {
            qty = Math.max(0, maxForField);
            toastError(
                maxForField <= 0
                    ? 'لا توجد كمية متبقية من هذا الصنف'
                    : `الكمية المتاحة فقط ${fmt(maxForField, 3)} — تم تصحيح القيمة تلقائيًا.`
            );
        }

        state.allocation[itemId] = state.allocation[itemId] || {};
        state.allocation[itemId][custId] = qty;

        input.value = qty;
        renderAllocationModal();
        renderItems();
        renderSummary();
        renderTabs();
    }

    function navigateItem(direction) {
        if (!state.modalInvoiceId) return;

        const itemId = state.modalItemId;
        const emptyNames = getEmptyCustomersForItem(itemId);
        if (emptyNames.length > 0) {
            const preview = emptyNames.slice(0, 3).join('، ');
            const more    = emptyNames.length > 3 ? ` و${emptyNames.length - 3} آخرين` : '';
            toastError(
                `أكمل أو احذف العملاء ذوي الحقول الفارغة قبل التنقل: «${preview}»${more}.`
            );
            return;
        }

        const inv = state.invoices[state.modalInvoiceId];
        if (!inv) return;
        const idx = inv.items.findIndex(it => it.id == state.modalItemId);
        if (idx === -1) return;
        const newIdx = idx + direction;
        if (newIdx < 0 || newIdx >= inv.items.length) return;
        state.modalItemId = inv.items[newIdx].id;
        renderAllocationModal();
    }

    $('btnPrevItem')?.addEventListener('click', () => navigateItem(-1));
    $('btnNextItem')?.addEventListener('click', () => navigateItem(+1));

    // ============================================================
    // Account Picker (Multi-Select)
    // ============================================================
    let accountPickerInstance = null;

    function ensureMultiSelectUI() {
        const results = $('AccountPickerResults');
        const table   = results?.closest('table');
        if (!table) return;

        const headerRow = table.querySelector('thead tr');
        if (headerRow && !headerRow.querySelector('.multi-check-col')) {
            const th = document.createElement('th');
            th.className = 'multi-check-col';
            th.style.width = '40px';
            th.innerHTML = `<input type="checkbox" class="form-check-input" id="AccountPickerSelectAll" title="تحديد الكل">`;
            headerRow.insertBefore(th, headerRow.firstChild);

            th.querySelector('#AccountPickerSelectAll')?.addEventListener('change', (e) => {
                document.querySelectorAll('.account-checkbox:not(:disabled)').forEach(cb => {
                    cb.checked = e.target.checked;
                });
                updateSelectedCount();
            });
        }

        const body = document.querySelector('#AccountPickerModal .modal-body');
        if (body && !$('AccountPickerFooter')) {
            const footer = document.createElement('div');
            footer.id = 'AccountPickerFooter';
            footer.className = 'd-flex justify-content-between align-items-center mt-3 pt-3 border-top';
            footer.innerHTML = `
                <div class="text-muted small">
                    <i class="bi bi-check2-square"></i>
                    المحدد: <span class="fw-bold text-primary" id="AccountPickerSelectedCount">0</span>
                </div>
                <button type="button" class="btn btn-success" id="AccountPickerAddSelected" disabled>
                    <i class="bi bi-check2-circle"></i> إضافة المحددين
                </button>
            `;
            body.appendChild(footer);

            $('AccountPickerAddSelected')?.addEventListener('click', () => {
                const selected = [];
                document.querySelectorAll('#AccountPickerResults .account-checkbox:checked:not(:disabled)').forEach(cb => {
                    selected.push({
                        id:    cb.dataset.id,
                        name:  cb.dataset.name,
                        phone: cb.dataset.phone || '',
                        code:  cb.dataset.code  || '',
                    });
                });
                if (!selected.length) {
                    toastWarning('لم تُحدد أي عميل.');
                    return;
                }
                addCustomersToItem(selected);
                closeAccountPicker();
            });
        }
    }

    function updateSelectedCount() {
        const count = document.querySelectorAll('#AccountPickerResults .account-checkbox:checked:not(:disabled)').length;
        const el  = $('AccountPickerSelectedCount');
        const btn = $('AccountPickerAddSelected');
        if (el)  el.textContent  = count;
        if (btn) btn.disabled    = count === 0;
    }

    async function openAccountPicker() {
        if (!state.modalItemId) { toastError('افتح مودال صنف أولًا.'); return; }
        const modalEl = $('AccountPickerModal');
        if (!modalEl) { toastError('مودال الحسابات غير محمّل.'); return; }

        const Modal = await ensureBootstrap();
        if (Modal) {
            if (!accountPickerInstance) accountPickerInstance = new Modal(modalEl);
            accountPickerInstance.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }

        document.querySelectorAll('#AccountPickerTabs .nav-link').forEach(tab => {
            if (tab.dataset.type === 'customer') {
                tab.classList.remove('d-none'); tab.classList.add('active');
            } else {
                tab.classList.add('d-none'); tab.classList.remove('active');
            }
        });
        const balanceCol = $('AccountPickerColBalance');
        if (balanceCol) balanceCol.classList.add('d-none');

        ensureMultiSelectUI();

        if ($('AccountPickerSearch')) $('AccountPickerSearch').value = '';
        if ($('AccountPickerResults')) {
            $('AccountPickerResults').innerHTML = `
                <tr><td colspan="6" class="text-center text-muted py-4">ابدأ البحث</td></tr>`;
        }
        if ($('AccountPickerTitle')) $('AccountPickerTitle').textContent = 'اختيار عملاء (متعدد)';
        updateSelectedCount();
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

    $('btnAddCustomerToItem')?.addEventListener('click', openAccountPicker);
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
                <tr><td colspan="6" class="text-center text-muted py-4">
                    اكتب حرفين على الأقل للبحث.
                </td></tr>`;
            return;
        }

        results.innerHTML = `
            <tr><td colspan="6" class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2">جارٍ التحميل...</span>
            </td></tr>`;

        try {
            const res = await api(`${ROUTES.customers}?q=${encodeURIComponent(term)}`);
            const rows = res.data || [];
            if (!rows.length) {
                results.innerHTML = `
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        لا نتائج مطابقة.
                    </td></tr>`;
                return;
            }

            const currentAlloc = state.modalItemId
                ? state.allocation[state.modalItemId] || {}
                : {};

            results.innerHTML = '';
            rows.forEach(c => {
                const alreadyAdded = currentAlloc[c.id] !== undefined;

                const tr = document.createElement('tr');
                tr.style.cursor = alreadyAdded ? 'default' : 'pointer';
                if (alreadyAdded) tr.classList.add('table-secondary');

                tr.innerHTML = `
                    <td class="text-center">
                        <input type="checkbox"
                               class="form-check-input account-checkbox"
                               data-id="${c.id}"
                               data-name="${escapeHtml(c.name)}"
                               data-phone="${escapeHtml(c.phone || '')}"
                               data-code="${escapeHtml(c.code  || '')}"
                               ${alreadyAdded ? 'disabled checked' : ''}>
                    </td>
                    <td class="text-center text-muted">${escapeHtml(c.code ?? c.id)}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle text-success"></i>
                            <span class="fw-semibold">${escapeHtml(c.name)}</span>
                            ${alreadyAdded ? '<span class="badge bg-secondary-subtle text-secondary ms-1">مضاف</span>' : ''}
                        </div>
                    </td>
                    <td class="d-none"></td>
                    <td>${escapeHtml(c.phone || '—')}</td>
                `;

                if (!alreadyAdded) {
                    tr.addEventListener('click', (e) => {
                        if (e.target.tagName === 'INPUT') return;
                        const cb = tr.querySelector('.account-checkbox');
                        if (cb) {
                            cb.checked = !cb.checked;
                            updateSelectedCount();
                        }
                    });
                }

                const cb = tr.querySelector('.account-checkbox');
                cb?.addEventListener('change', updateSelectedCount);

                results.appendChild(tr);
            });

            updateSelectedCount();
        } catch (e) {
            results.innerHTML = `
                <tr><td colspan="6" class="text-center text-danger py-4">
                    فشل: ${escapeHtml(e.message)}
                </td></tr>`;
        }
    }

    function addCustomersToItem(customers) {
        const itemId = state.modalItemId;
        if (!itemId) return;

        const added   = [];
        const skipped = [];

        customers.forEach(c => {
            if (!state.customerInfo[c.id]) {
                state.customerInfo[c.id] = {
                    id: c.id, name: c.name, phone: c.phone || '', code: c.code || '',
                };
            }

            state.allocation[itemId] = state.allocation[itemId] || {};
            if (state.allocation[itemId][c.id] === undefined) {
                state.allocation[itemId][c.id] = 0;
                added.push(c);
            } else {
                skipped.push(c);
            }
        });

        renderAllocationModal();
        renderItems();
        renderSummary();
        renderTabs();

        const item = findItem(itemId);
        const itemName = item?.item_name || '';

        if (added.length === 1) {
            toastSuccess(`أضيف العميل «${added[0].name}» إلى الصنف «${itemName}». أدخل الكمية الآن.`);
        } else if (added.length > 1) {
            toastSuccess(`أضيف ${added.length} عملاء إلى الصنف «${itemName}». أدخل كمياتهم الآن.`);
        }
        if (skipped.length > 0) {
            toastInfo(`${skipped.length} عميل مضاف مسبقًا — تم تجاهلهم.`);
        }
    }

    // ============================================================
    // الملخص
    // ============================================================
    function renderSummary() {
        let itemsCount = 0, cost = 0, sale = 0;
        const customersSet = new Set();
        const breakdown = [];

        Object.values(state.invoices).forEach(inv => {
            inv.items.forEach(item => {
                const alloc = getAllocatedQty(item.id);
                const unit  = (item.unit_name || '—').trim();

                if (alloc > 0) {
                    itemsCount++;
                    const c = alloc * num(item.unit_cost);
                    const s = alloc * num(item.sale_price);
                    cost += c;
                    sale += s;
                    breakdown.push({
                        item_name:      item.item_name,
                        invoice_number: inv.number,
                        qty:            alloc,
                        unit_name:      unit,
                        unit_cost:      num(item.unit_cost),
                        unit_sale:      num(item.sale_price),
                        total_cost:     c,
                        total_sale:     s,
                    });
                    Object.entries(state.allocation[item.id] || {}).forEach(([cid, q]) => {
                        if (num(q) > 0) customersSet.add(cid);
                    });
                }
            });
        });

        if ($('sumItemsCount'))     $('sumItemsCount').textContent     = itemsCount;
        if ($('sumCustomersCount')) $('sumCustomersCount').textContent = customersSet.size;
        if ($('sumCost'))           $('sumCost').textContent           = fmt(cost) + ' ر.ي';
        if ($('sumSale'))           $('sumSale').textContent           = fmt(sale) + ' ر.ي';

        const body = $('itemsBreakdownBody');
        if (!body) return;
        body.innerHTML = '';

        if (!breakdown.length) {
            body.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-3 small">
                        <i class="bi bi-info-circle"></i>
                        لا توجد كميات موزعة بعد.
                    </td>
                </tr>`;
            return;
        }

        breakdown.forEach(b => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-semibold small">${escapeHtml(b.item_name)}</td>
                <td class="text-muted small">${escapeHtml(b.invoice_number)}</td>
                <td class="text-end fw-semibold text-success">${fmt(b.qty, 3)}</td>
                <td class="text-center small text-muted">${escapeHtml(b.unit_name)}</td>
                <td class="text-end text-muted">${fmt(b.unit_cost)}</td>
                <td class="text-end text-muted">${fmt(b.unit_sale)}</td>
                <td class="text-end">${fmt(b.total_cost)} ر.ي</td>
                <td class="text-end">${fmt(b.total_sale)} ر.ي</td>
            `;
            body.appendChild(tr);
        });
    }

    // ============================================================
    // الحفظ
    // ============================================================
    $('btnSaveDraft')?.addEventListener('click', () => save());

    async function save() {
        if (!Object.keys(state.invoices).length) {
            toastError('اختر فاتورة واحدة على الأقل.');
            return;
        }

        const empties = findEmptyAllocations();
        if (empties.length > 0) {
            const first     = empties[0];
            const item      = findItem(first.itemId);
            const cust      = state.customerInfo[first.custId];
            const invOfItem = findInvoiceOfItem(first.itemId);

            toastError(
                `لا يمكن الحفظ — يوجد ${empties.length} عميل بحقل فارغ. ` +
                `أكمل الكميات أولًا: الصنف «${item?.item_name || ''}» — العميل «${cust?.name || ''}».`
            );

            if (invOfItem) {
                state.activeInvoiceId = invOfItem.id;
                renderTabs();
                renderItems();

                state.modalItemId    = first.itemId;
                state.modalInvoiceId = invOfItem.id;
                allowAllocationModalClose = false;
                renderAllocationModal();

                const Modal = await ensureBootstrap();
                const modalEl = $('allocationModal');
                if (Modal && modalEl) {
                    if (!allocationModalInstance) allocationModalInstance = new Modal(modalEl);
                    allocationModalInstance.show();
                }
            }
            return;
        }

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

        if (!details.length) {
            toastError('لا توجد كميات موزعة للحفظ.');
            return;
        }

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
            toastSuccess(res.message + ` (${res.code})`);
            state.distributionId = res.id;
            updateModeBadge();
            const url = new URL(window.location);
            url.searchParams.set('id', res.id);
            window.history.replaceState({}, '', url);
        } catch (e) {
            toastError('فشل الحفظ: ' + e.message);
        } finally {
            if ($('btnSaveDraft')) $('btnSaveDraft').disabled = false;
        }
    }

    // ============================================================
    // تحميل توزيع للتعديل
    // ============================================================
    async function loadExisting(id) {
        try {
            const res = await api(ROUTES.show(id));
            if (!res.success) return;

            state.distributionId = res.header.id;
            updateModeBadge();
            if ($('distributionDate'))  $('distributionDate').value  = res.header.distribution_date || '';
            if ($('distributionNotes')) $('distributionNotes').value = res.header.notes || '';

            const thisDistAlloc = {};
            res.details.forEach(d => {
                thisDistAlloc[d.movement_detail_id] =
                    (thisDistAlloc[d.movement_detail_id] || 0) + num(d.quantity);
            });

            const invoiceIds = [...new Set(res.details.map(d => d.purchase_invoice_id))];
            for (const invId of invoiceIds) {
                try {
                    const invRes = await api(ROUTES.items(invId));
                    if (invRes.success) {
                        state.invoices[invId] = {
                            id: invId,
                            number: invRes.invoice.invoice_number,
                            date: invRes.invoice.invoice_date,
                            supplier: invRes.invoice.supplier,
                            items: (invRes.data || []).map(it => {
                                const qty       = num(it.quantity);
                                const addBack   = thisDistAlloc[it.id] || 0;
                                const otherDist = qty - num(it.available) - addBack;
                                return {
                                    ...it,
                                    available: Math.max(0, qty - otherDist),
                                };
                            }),
                        };
                    }
                } catch (e) { /* تخطي */ }
            }

            res.details.forEach(d => {
                state.customerInfo[d.customer_id] = {
                    id: d.customer_id, name: d.customer_name, phone: '', code: '',
                };
                state.allocation[d.movement_detail_id] = state.allocation[d.movement_detail_id] || {};
                state.allocation[d.movement_detail_id][d.customer_id] = num(d.quantity);
            });

            const firstInvId = Object.keys(state.invoices)[0];
            state.activeInvoiceId = firstInvId ? parseInt(firstInvId) : null;

            renderTabs();
            renderItems();
            renderSummary();
            toastInfo(`تم تحميل التوزيع ${res.header.code} — وضع التعديل.`);
        } catch (e) {
            toastError('فشل تحميل التوزيع: ' + e.message);
        }
    }

    // ============================================================
    // إجراءات
    // ============================================================
    $('btnIssueInvoice')?.addEventListener('click', () => {
        if (!state.distributionId) {
            toastError('احفظ التوزيع أولًا قبل الانتقال لشاشة الفوترة.');
            return;
        }
        window.open(ROUTES.customerPage, '_blank');
    });

    $('btnCancelAll')?.addEventListener('click', resetScreen);
    $('btnNew')?.addEventListener('click', async () => {
        const ok = await confirmDialog('بدء توزيع جديد؟ سيتم فقدان البيانات الحالية إن لم تُحفظ.', {
            title: 'توزيع جديد', okText: 'بدء جديد', danger: true,
        });
        if (!ok) return;
        window.location.href = window.location.pathname;
    });
    $('btnHistory')?.addEventListener('click', () => {
    window.open('/operation/purchases/distribution/history', '_blank');
    });

    async function resetScreen() {
        const ok = await confirmDialog('تفريغ الشاشة من كل البيانات؟', {
            title: 'تفريغ الشاشة', okText: 'تفريغ', danger: true,
        });
        if (!ok) return;

        state.distributionId  = null;
        state.invoices        = {};
        state.activeInvoiceId = null;
        state.customerInfo    = {};
        state.allocation      = {};
        state.modalItemId     = null;
        state.modalInvoiceId  = null;
        updateModeBadge();
        if ($('distributionNotes')) $('distributionNotes').value = '';
        if ($('alertArea'))         $('alertArea').innerHTML     = '';
        if ($('itemsSearch'))       $('itemsSearch').value       = '';
        renderTabs();
        renderItems();
        renderSummary();
        toastInfo('تم تفريغ الشاشة.');
    }

    // ============================================================
    // التهيئة
    // ============================================================
    if ($('distributionDate') && !$('distributionDate').value) {
        $('distributionDate').value = new Date().toISOString().slice(0, 10);
    }

    const urlId = new URLSearchParams(window.location.search).get('id');
    if (urlId) loadExisting(urlId);

    updateModeBadge();
    renderTabs();
    renderItems();
    renderSummary();
});