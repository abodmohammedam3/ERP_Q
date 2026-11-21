// resources/js/pages/purchase-distribution.js

let BootstrapModal = null;
async function ensureBootstrap() {
    if (BootstrapModal) return BootstrapModal;
    if (window.bootstrap?.Modal) { BootstrapModal = window.bootstrap.Modal; return BootstrapModal; }
    try { const m = await import('bootstrap'); BootstrapModal = m.Modal; return BootstrapModal; }
    catch (e) { console.error('[dist] Bootstrap غير متاح:', e); return null; }
}

document.addEventListener('DOMContentLoaded', () => {

    const ROUTES = {
        invoices: '/operation/purchases/distribution/invoices',
        items: (id) => `/operation/purchases/distribution/invoices/${id}/items`,
        customers: '/operation/purchases/distribution/customers',
        save: '/operation/purchases/distribution/save',
        show: (id) => `/operation/purchases/distribution/${id}`,
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const state = {
        distributionId: null,
        invoices: {},
        activeInvoiceId: null,
        customerInfo: {},
        allocation: {},
        prices: {},
        units: {},   // الوحدة المختارة لكل (دفعة، عميل) — القيمة = UnitID وحدة الدفعة افتراضيًا
        pieceMeta: {},   // بيانات حقل الوزن (عدد الحبات × وزن الحبة) لكل (دفعة، عميل) — للعرض فقط
        unitsCache: null, // كاش وحدات القياس من /setting/inventory/units/list?full=1
        modalItemId: null,
        modalInvoiceId: null,
    };

    // ============================================================
    // الوحدات: التعرف النصي (نفس منهج movements.js)
    // ============================================================
    const isKiloUnitName = (n) => {
        const s = String(n || '').trim().toLowerCase();
        return !!s && (s.includes('كيلو') || s.includes('كجم') || s === 'kg' || s.includes('kilogram'));
    };
    const isGramUnitName = (n) => {
        const s = String(n || '').trim().toLowerCase();
        return !!s && (s.includes('جرام') || s.includes('جم') || s === 'g' || s.includes('gram'));
    };
    const isPieceUnitName = (n) =>
        ['الحبة', 'حبة', 'حبه', 'حب', 'قطعة', 'قطعه'].includes(String(n || '').trim());

    const unitNameById = (unitId) => {
        const u = (state.unitsCache || []).find(x => String(x.UnitID) === String(unitId));
        return u ? u.UnitName : '';
    };

    /**
     * معامل التحويل من الوحدة "from" إلى الوحدة "to" (بالكمية).
     * كيلو↔جرام فقط.
     */
    function unitFactor(fromId, toId) {
        if (String(fromId) === String(toId)) return 1;
        const fn = unitNameById(fromId);
        const tn = unitNameById(toId);
        if (isKiloUnitName(fn) && isGramUnitName(tn)) return 1000;   // كيلو → جرام
        if (isGramUnitName(fn) && isKiloUnitName(tn)) return 0.001;  // جرام → كيلو
        return null;
    }

    const isPieceUnitId = (unitId) => isPieceUnitName(unitNameById(unitId));

    /** جلب وحدات القياس مرة واحدة */
    async function ensureUnitsCache() {
        if (state.unitsCache) return state.unitsCache;
        try {
            const res = await fetch('/setting/inventory/units/list?full=1', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json();
            state.unitsCache = Array.isArray(json.data) ? json.data : [];
        } catch (e) {
            state.unitsCache = [];
        }
        return state.unitsCache;
    }

    /** الوحدة الافتراضية (المرجعية) للدفعة = وحدة الصنف في فاتورة الشراء */
    function batchUnitId(item) {
        return item?.unit_id ?? null;
    }

    /** الوحدة المختارة حاليًا لصف (دفعة، عميل) — الافتراضي وحدة الدفعة */
    function chosenUnitId(mdId, cid, item) {
        return state.units[mdId]?.[cid] ?? batchUnitId(item) ?? null;
    }

    /** الوحدات المتاحة للعرض في القائمة */
    function availableUnitOptions(item) {
        const base = batchUnitId(item);
        const all = state.unitsCache || [];
        const out = [];
        (all || []).forEach(u => {
            const uid = u.UnitID;
            if (String(uid) === String(base)) { out.push(u); return; }
            const f = unitFactor(uid, base);
            if (f !== null || isPieceUnitName(u.UnitName)) out.push(u);
        });
        if (!out.length && base !== null) {
            out.push({ UnitID: base, UnitName: item.unit_name || '' });
        }
        return out;
    }

    const $ = (id) => document.getElementById(id);
    const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const fmt = (v, d = 2) => num(v).toFixed(d);
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
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

    function showToast(msg, type = 'info') {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(msg, type);
            return;
        }
        console.log(`[dist:${type}] ${msg}`);
    }
    const toastSuccess = (m) => showToast(m, 'success');
    const toastError = (m) => showToast(m, 'danger');
    const toastWarning = (m) => showToast(m, 'warning');
    const toastInfo = (m) => showToast(m, 'info');

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

    const mainTabsEl = document.getElementById('mainTabs');
    const mainTabsPanes = mainTabsEl ? {
        distribute: document.getElementById('tab-distribute'),
        issue: document.getElementById('tab-issue'),
        history: document.getElementById('tab-history'),
    } : null;

    function activateMainTab(name) {
        if (!mainTabsEl || !mainTabsPanes?.[name]) return;
        mainTabsEl.querySelectorAll('.nav-link').forEach(t => {
            t.classList.toggle('active', t.dataset.tab === name);
        });
        Object.entries(mainTabsPanes).forEach(([k, el]) => {
            el?.classList.toggle('d-none', k !== name);
        });
        try {
            const url = new URL(window.location);
            url.searchParams.set('tab', name);
            window.history.replaceState({}, '', url);
        } catch (e) { }
    }

    if (mainTabsEl) {
        mainTabsEl.querySelectorAll('.nav-link').forEach(btn => {
            btn.addEventListener('click', () => activateMainTab(btn.dataset.tab));
        });
        const urlTab = new URLSearchParams(location.search).get('tab');
        if (urlTab && mainTabsPanes[urlTab]) activateMainTab(urlTab);
    }

    function findEmptyAllocations() {
        const empty = [];
        Object.entries(state.allocation).forEach(([itemId, row]) => {
            Object.entries(row || {}).forEach(([custId, qty]) => {
                if (num(qty) <= 0) {
                    empty.push({ itemId: parseInt(itemId, 10), custId: parseInt(custId, 10) });
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
        const priceRow = state.prices[itemId] || {};
        const removedCount = Object.keys(row).filter(cid => num(row[cid]) <= 0).length;
        Object.keys(row).forEach(cid => {
            if (num(row[cid]) <= 0) {
                delete row[cid];
                if (priceRow[cid] !== undefined) delete priceRow[cid];
            }
        });
        if (Object.keys(row).length === 0) {
            delete state.allocation[itemId];
            delete state.prices[itemId];
            delete state.units[itemId];
            delete state.pieceMeta[itemId];
        }
        pruneOrphanCustomers();
        return removedCount;
    }

    function pruneOrphanCustomers() {
        const inUse = new Set();
        Object.values(state.allocation).forEach(row =>
            Object.keys(row || {}).forEach(cid => inUse.add(String(cid))));
        Object.keys(state.customerInfo).forEach(cid => {
            if (!inUse.has(String(cid))) delete state.customerInfo[cid];
        });
    }

    function findItem(movementDetailId) {
        for (const invId in state.invoices) {
            const found = (state.invoices[invId].items || []).find(it => it.id == movementDetailId);
            if (found) return found;
        }
        return null;
    }

    function findInvoiceOfItem(movementDetailId) {
        for (const invId in state.invoices) {
            if ((state.invoices[invId].items || []).some(it => it.id == movementDetailId)) {
                return state.invoices[invId];
            }
        }
        return null;
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

    if ($('invoiceFilterDate')) $('invoiceFilterDate').value = new Date().toISOString().slice(0, 10);
    $('invoiceFilterDate')?.addEventListener('change', loadInvoices);
    $('invoiceFilterSupplier')?.addEventListener('input', debounce(loadInvoices, 400));
    $('invoiceClearBtn')?.addEventListener('click', () => {
        if ($('invoiceFilterDate')) $('invoiceFilterDate').value = '';
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
                date: $('invoiceFilterDate')?.value || '',
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
            const url = state.distributionId
                ? `${ROUTES.items(inv.id)}?exclude_distribution_id=${encodeURIComponent(state.distributionId)}`
                : ROUTES.items(inv.id);
            const res = await api(url);
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
            if ((inv.items || []).some(it => it.id == mdId)) {
                delete state.allocation[mdId];
                delete state.prices[mdId];
                delete state.units[mdId];
                delete state.pieceMeta[mdId];
            }
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
        return (inv.items || []).some(it => getAllocatedQty(it.id) > 0);
    }

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
                </div>`;
            return;
        }

        invoices.forEach(inv => {
            const distCount = (inv.items || []).filter(it => getAllocatedQty(it.id) > 0).length;
            const totalCount = (inv.items || []).length;

            let badgeClass = 'bg-secondary-subtle text-secondary';
            if (distCount === totalCount && totalCount > 0) badgeClass = 'bg-success-subtle text-success';
            else if (distCount > 0) badgeClass = 'bg-warning-subtle text-warning';

            const isActive = state.activeInvoiceId == inv.id;
            const supplier = inv.supplier || '—';

            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex align-items-stretch';
            wrapper.innerHTML = `
                <button type="button"
                        class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} d-flex align-items-center gap-2 rounded-end-0"
                        data-tab-id="${inv.id}">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="fw-semibold">${escapeHtml(inv.number)}</span>
                    <span class="badge ${isActive ? 'bg-white text-primary' : badgeClass}">
                        ${escapeHtml(supplier)}
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

        box.querySelectorAll('button[data-tab-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.activeInvoiceId = parseInt(btn.dataset.tabId, 10);
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

    $('itemsSearch')?.addEventListener('input', renderItems);
    $('itemsClearBtn')?.addEventListener('click', () => {
        if ($('itemsSearch')) {
            $('itemsSearch').value = '';
            renderItems();
        }
    });

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

    let allocationModalInstance = null;
    let allowAllocationModalClose = false;

    $('allocationModal')?.addEventListener('hide.bs.modal', async (e) => {
        if (allowAllocationModalClose) return;

        const itemId = state.modalItemId;
        if (!itemId) return;

        const item = findItem(itemId);
        if (!item) return;

        const row = state.allocation[itemId] || {};
        const priceRow = state.prices[itemId] || {};

        let invalidPrices = [];
        Object.entries(row).forEach(([cid, qty]) => {
            if (num(qty) > 0) {
                const price = priceRow[cid];
                if (price === undefined || num(price) <= 0) {
                    invalidPrices.push(state.customerInfo[cid]?.name || `#${cid}`);
                }
            }
        });

        if (invalidPrices.length > 0) {
            e.preventDefault();
            e.stopPropagation();

            document.querySelectorAll('#allocationTableBody .alloc-price-input').forEach(inp => {
                const cid = inp.dataset.customer;
                const qty = num(row[cid]);
                const price = priceRow[cid];
                if (qty > 0 && (price === undefined || num(price) <= 0)) {
                    inp.classList.add('border-danger');
                } else {
                    inp.classList.remove('border-danger');
                }
            });

            const preview = invalidPrices.slice(0, 3).join('، ');
            const more = invalidPrices.length > 3 ? ` و${invalidPrices.length - 3} آخرين` : '';
            toastError(`لا يمكن إغلاق المودال — يوجد عميل بسعر فارغ أو صفر: «${preview}»${more}. يرجى إدخال السعر أو مسح الكمية.`);
            return;
        }

        const emptyNames = getEmptyCustomersForItem(itemId);
        if (emptyNames.length === 0) return;

        e.preventDefault();
        e.stopPropagation();

        const preview = emptyNames.slice(0, 3).join('، ');
        const more = emptyNames.length > 3 ? ` و${emptyNames.length - 3} آخرين` : '';

        const ok = await confirmDialog(
            `يوجد ${emptyNames.length} عميل بحقل فارغ: «${preview}»${more}.\n\n` +
            `هل تريد حذفهم وإغلاق المودال؟\n` +
            `إن رفضت، سيبقى المودال مفتوحًا لإكمال الكميات.`,
            { title: 'عملاء بدون كمية', okText: 'حذفهم وإغلاق', cancelText: 'إلغاء', danger: true }
        );

        if (!ok) {
            document.querySelectorAll('#allocationTableBody .alloc-input').forEach(inp => {
                inp.classList.remove('is-invalid');
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

        await ensureUnitsCache();

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
        const item = findItem(itemId);
        const inv = state.modalInvoiceId ? state.invoices[state.modalInvoiceId] : null;
        if (!item || !inv) return;

        const alloc = getAllocatedQty(itemId);
        const remaining = Math.max(0, num(item.available) - alloc);
        const noRemaining = remaining <= 0;

        if ($('allocItemName')) $('allocItemName').textContent = item.item_name;
        if ($('allocInvoiceNumber')) $('allocInvoiceNumber').textContent = inv.number;
        if ($('allocAvailable')) $('allocAvailable').textContent = fmt(item.available, 3);
        if ($('allocAllocated')) $('allocAllocated').textContent = fmt(alloc, 3);
        if ($('allocRemaining')) $('allocRemaining').textContent = fmt(remaining, 3);
        if ($('allocUnitCost')) $('allocUnitCost').textContent = fmt(item.unit_cost);

        const idx = inv.items.findIndex(it => it.id == itemId);
        const total = inv.items.length;
        if ($('allocItemCounter')) $('allocItemCounter').textContent = `${idx + 1} من ${total}`;
        if ($('btnPrevItem')) $('btnPrevItem').disabled = idx <= 0;
        if ($('btnNextItem')) $('btnNextItem').disabled = idx >= total - 1;

        const tbody = $('allocationTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        const row = state.allocation[itemId] || {};
        const priceRow = state.prices[itemId] || {};
        const pieceRow = state.pieceMeta[itemId] || {};
        const customerIds = Object.keys(row);

        if (!customerIds.length) {
            tbody.innerHTML = `
                <tr><td colspan="7" class="text-center text-muted py-3 small">
                    <i class="bi bi-person-plus fs-4 d-block mb-1 opacity-50"></i>
                    أضف عميلًا لتوزيع الكميات.
                </td></tr>`;
            return;
        }

        customerIds.forEach(cid => {
            const info = state.customerInfo[cid] || { name: '—', phone: '' };
            const storedQty = num(row[cid]);   // بوحدة الدفعة دائمًا
            const storedPrice = priceRow[cid] !== undefined ? num(priceRow[cid]) : num(item.sale_price);

            const unitId = chosenUnitId(itemId, cid, item);
            const batch = batchUnitId(item);

            let curQty = storedQty;
            if (unitId !== null && batch !== null && String(unitId) !== String(batch)) {
                if (isPieceUnitId(unitId)) {
                    const meta = state.pieceMeta[itemId]?.[cid] || {};
                    curQty = num(meta.count) || 0;
                } else {
                    const f = unitFactor(batch, unitId);
                    if (f !== null && f > 0) { curQty = storedQty * f; }
                }
            }

            const curPrice = displayPriceFor(itemId, cid, item);
            const curTotal = curQty * curPrice;
            const curUnit = chosenUnitId(itemId, cid, item);
            const curPiece = pieceRow[cid] || null;

            const isEmpty = curQty <= 0;
            const editable = !isEmpty || !noRemaining;

            const isPriceInvalid = curPrice <= 0;
            const priceWarningClass = (curPrice > 0 && storedPrice < num(item.unit_cost)) ? 'border-warning bg-warning-subtle' : '';
            const priceInvalidClass = isPriceInvalid ? 'border-danger' : '';

            const showPrice = curQty > 0;

            const unitOptions = availableUnitOptions(item)
                .map(u => `<option value="${escapeHtml(String(u.UnitID))}" ${String(u.UnitID) === String(curUnit) ? 'selected' : ''}>${escapeHtml(u.UnitName || '')}</option>`)
                .join('');

            const pieceUnitSelected = curUnit !== null && isPieceUnitId(curUnit);

            // إصلاح التصميم المضغوط: استخدام flex-column
            const pieceBlock = pieceUnitSelected ? `
                <div class="piece-weight-block mt-1" data-piece-for="${escapeHtml(String(cid))}">
                    <div class="d-flex flex-column gap-1">
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off"
                               class="form-control form-control-sm text-center piece-count-input ${editable ? '' : 'bg-body-secondary'}"
                               data-customer="${cid}" placeholder="عدد الحبات"
                               value="${curPiece ? (curPiece.count || '') : ''}" ${editable ? '' : 'readonly'}>
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off"
                               class="form-control form-control-sm text-center piece-weight-input ${editable ? '' : 'bg-body-secondary'}"
                               data-customer="${cid}" placeholder="وزن الحبة (كجم)"
                               value="${curPiece ? (curPiece.weight || '') : ''}" ${editable ? '' : 'readonly'}>
                    </div>
                    <div class="small text-primary mt-1 piece-weight-total">${curPiece && num(curPiece.count) > 0 && num(curPiece.weight) > 0 ? `${fmt(num(curPiece.count) * num(curPiece.weight), 3)} كجم إجمالي` : ''}</div>
                </div>` : '';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="fw-semibold small">
                        ${escapeHtml(info.name)}
                        ${isEmpty ? '<i class="bi bi-exclamation-circle-fill text-danger ms-1" title="لم تُدخل كمية"></i>' : ''}
                    </div>
                    ${info.phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(info.phone)}</div>` : ''}
                </td>
                <td class="text-center">
                    <select class="form-select form-select-sm alloc-unit-input" data-customer="${cid}" ${editable ? '' : 'disabled'}>
                        ${unitOptions}
                    </select>
                    ${pieceBlock}
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
                           placeholder="${editable ? 'الكمية' : 'لا يوجد'}">
                </td>
                <td class="text-center">
                    <span class="price-placeholder text-muted small ${showPrice ? 'd-none' : ''}">أدخل الكمية</span>
                    <input type="text"
                           inputmode="decimal"
                           autocomplete="off"
                           dir="ltr"
                           class="form-control form-control-sm text-center alloc-price-input ${showPrice ? '' : 'd-none'} ${editable ? '' : 'bg-body-secondary'} ${priceWarningClass} ${priceInvalidClass}"
                           data-customer="${cid}"
                           value="${curPrice || ''}"
                           ${editable ? '' : 'readonly'}
                           placeholder="">
                </td>
                <td class="text-center">
                    <input type="text"
                           class="form-control form-control-sm text-center bg-light fw-bold text-success alloc-total-input ${showPrice ? '' : 'd-none'}"
                           value="${curTotal > 0 ? fmt(curTotal) : ''}"
                           readonly
                           tabindex="-1"
                           dir="ltr">
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
                inp.addEventListener('keydown', (e) => {
                    const ch = e.key;
                    if (ch === 'Enter') { e.preventDefault(); inp.blur(); return; }
                    if (['Backspace', 'Delete', 'Tab', 'Escape', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(ch)) return;
                    if ((e.ctrlKey || e.metaKey) && ['a', 'c', 'v', 'x', 'z', 'y'].includes(String(ch).toLowerCase())) return;
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

                inp.addEventListener('input', () => handleModalAllocLiveInput(inp));
                inp.addEventListener('blur', () => handleModalAllocInput(inp));
            }
        });

        tbody.querySelectorAll('.alloc-price-input').forEach(inp => {
            if (inp.readOnly) return;

            inp.addEventListener('keydown', (e) => {
                const ch = e.key;
                if (ch === 'Enter') { e.preventDefault(); inp.blur(); return; }
                if (['Backspace', 'Delete', 'Tab', 'Escape', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(ch)) return;
                if ((e.ctrlKey || e.metaKey) && ['a', 'c', 'v', 'x', 'z', 'y'].includes(String(ch).toLowerCase())) return;
                if (/[0-9]/.test(ch)) return;
                if (ch === '.') {
                    if (inp.value.includes('.')) e.preventDefault();
                    return;
                }
                e.preventDefault();
            });

            inp.addEventListener('input', () => handleModalPriceInput(inp));

            inp.addEventListener('blur', () => {
                const currentItemId = state.modalItemId;
                const currentItem = findItem(currentItemId);
                if (!currentItem) return;

                const custId = inp.dataset.customer;
                const storedPrice = state.prices[currentItemId]?.[custId];
                const price = num(storedPrice !== undefined ? storedPrice : inp.value);
                if (price > 0 && price < num(currentItem.unit_cost)) {
                    toastWarning(`السعر (${fmt(price)}) أقل من سعر التكلفة (${fmt(currentItem.unit_cost)})`);
                }
            });
        });

        tbody.querySelectorAll('.alloc-unit-input').forEach(sel => {
            if (sel.disabled) return;
            sel.addEventListener('change', () => handleUnitChange(sel));
        });

        tbody.querySelectorAll('.piece-count-input').forEach(inp => {
            if (inp.readOnly) return;
            inp.addEventListener('input', () => handlePieceInput(inp, 'count'));
        });
        tbody.querySelectorAll('.piece-weight-input').forEach(inp => {
            if (inp.readOnly) return;
            inp.addEventListener('input', () => handlePieceInput(inp, 'weight'));
        });

        tbody.querySelectorAll('.alloc-remove').forEach(btn =>
            btn.addEventListener('click', async () => {
                const cid = btn.dataset.customer;
                const info = state.customerInfo[cid];
                const qty = num(state.allocation[itemId]?.[cid] || 0);

                if (qty > 0) {
                    const ok = await confirmDialog(
                        `العميل «${info?.name || ''}» لديه كمية ${fmt(qty, 3)}. إزالة العميل ستحذف هذه الكمية. متابعة؟`,
                        { title: 'إزالة عميل', okText: 'إزالة', danger: true }
                    );
                    if (!ok) return;
                }

                if (state.allocation[itemId]) delete state.allocation[itemId][cid];
                if (state.prices[itemId]) delete state.prices[itemId][cid];
                if (state.units[itemId]) delete state.units[itemId][cid];
                if (state.pieceMeta[itemId]) delete state.pieceMeta[itemId][cid];

                if (Object.keys(state.allocation[itemId] || {}).length === 0) {
                    delete state.allocation[itemId];
                    delete state.prices[itemId];
                    delete state.units[itemId];
                    delete state.pieceMeta[itemId];
                }

                pruneOrphanCustomers();
                renderAllocationModal();
                renderItems();
                renderSummary();
                renderTabs();
            }));
    }

    function handleModalAllocLiveInput(input) {
        const itemId = state.modalItemId;
        const custId = input.dataset.customer;
        const item = findItem(itemId);
        if (!item) return;

        const qtyEntered = Math.max(0, num(input.value));
        const qty = qtyToBatchUnit(itemId, custId, qtyEntered, item);

        const others = Object.entries(state.allocation[itemId] || {})
            .filter(([cid]) => cid != custId)
            .reduce((s, [, q]) => s + num(q), 0);

        const maxForField = num(item.available) - others;

        if (qty > maxForField) {
            const capped = Math.max(0, maxForField);
            state.allocation[itemId] = state.allocation[itemId] || {};
            state.allocation[itemId][custId] = capped;
            setTimeout(() => {
                toastError(`المكافئ بالوحدة المرجعية (${fmt(capped, 3)} ${item.unit_name || ''}) — تم تصحيح القيمة تلقائيًا.`);
            }, 0);
        } else {
            state.allocation[itemId] = state.allocation[itemId] || {};
            state.allocation[itemId][custId] = qty;
        }

        const totalAlloc = getAllocatedQty(itemId);
        const remaining = Math.max(0, num(item.available) - totalAlloc);
        if ($('allocAllocated')) $('allocAllocated').textContent = fmt(totalAlloc, 3);
        if ($('allocRemaining')) $('allocRemaining').textContent = fmt(remaining, 3);

        const tr = input.closest('tr');
        if (tr) {
            const priceInput = tr.querySelector('.alloc-price-input');
            const totalInput = tr.querySelector('.alloc-total-input');
            const placeholder = tr.querySelector('.price-placeholder');

            if (qty > 0) {
                if (priceInput) priceInput.classList.remove('d-none');
                if (totalInput) totalInput.classList.remove('d-none');
                if (placeholder) placeholder.classList.add('d-none');

                let currentPrice = state.prices[itemId]?.[custId];
                if (currentPrice === undefined || currentPrice <= 0) {
                    state.prices[itemId] = state.prices[itemId] || {};
                    state.prices[itemId][custId] = num(item.sale_price);
                    if (priceInput) {
                        priceInput.value = priceFromBatchUnit(itemId, custId, item) || num(item.sale_price);
                        priceInput.classList.remove('border-warning', 'bg-warning-subtle', 'border-danger');
                    }
                }

                const priceShown = num(priceInput?.value);
                if (totalInput) totalInput.value = (qtyEntered * priceShown) > 0 ? fmt(qtyEntered * priceShown) : '';
            } else {
                if (priceInput) priceInput.classList.add('d-none');
                if (totalInput) totalInput.classList.add('d-none');
                if (placeholder) placeholder.classList.remove('d-none');

                state.prices[itemId] = state.prices[itemId] || {};
                state.prices[itemId][custId] = num(item.sale_price);
                if (priceInput) {
                    priceInput.value = priceFromBatchUnit(itemId, custId, item) || num(item.sale_price);
                    priceInput.classList.remove('border-warning', 'bg-warning-subtle', 'border-danger');
                }
            }
        }

        renderSummary();
        renderTabs();
    }

    function handleModalAllocInput(input) {
        const itemId = state.modalItemId;
        const custId = input.dataset.customer;
        const item = findItem(itemId);
        if (!item) return;

        const raw = String(input.value ?? '').trim();

        if (raw === '' || num(raw) === 0) {
            state.allocation[itemId] = state.allocation[itemId] || {};
            state.allocation[itemId][custId] = 0;

            state.prices[itemId] = state.prices[itemId] || {};
            state.prices[itemId][custId] = num(item.sale_price);

            renderAllocationModal();
            renderItems();
            renderSummary();
            renderTabs();
            return;
        }

        let qty = num(raw);
        if (qty < 0) {
            input.value = '';
            state.allocation[itemId] = state.allocation[itemId] || {};
            state.allocation[itemId][custId] = 0;

            state.prices[itemId] = state.prices[itemId] || {};
            state.prices[itemId][custId] = num(item.sale_price);

            renderAllocationModal();
            renderItems();
            renderSummary();
            renderTabs();
            return;
        }

        qty = qtyToBatchUnit(itemId, custId, qty, item);

        const others = Object.entries(state.allocation[itemId] || {})
            .filter(([cid]) => cid != custId)
            .reduce((s, [, q]) => s + num(q), 0);

        const maxForField = num(item.available) - others;
        if (qty > maxForField) {
            qty = Math.max(0, maxForField);
            toastError(
                maxForField <= 0
                    ? 'لا توجد كمية متبقية من هذا الصنف'
                    : `المكافئ بالوحدة المرجعية فقط ${fmt(maxForField, 3)} — تم تصحيح القيمة تلقائيًا.`
            );
        }

        state.allocation[itemId] = state.allocation[itemId] || {};
        state.allocation[itemId][custId] = qty;

        renderAllocationModal();
        renderItems();
        renderSummary();
        renderTabs();
    }

    function handleModalPriceInput(input) {
        const itemId = state.modalItemId;
        const custId = input.dataset.customer;
        const item = findItem(itemId);
        if (!item) return;

        let price = num(input.value);
        if (price < 0) price = 0;

        // السعر المعروض بالوحدة المختارة — يُحوّل إلى وحدة الدفعة قبل التخزين
        const unitId = chosenUnitId(itemId, custId, item);
        const batch = batchUnitId(item);
        if (unitId !== null && batch !== null && String(unitId) !== String(batch)) {
            if (isPieceUnitId(unitId)) {
                const meta = state.pieceMeta[itemId]?.[custId] || {};
                const wKg = num(meta.weight);
                if (wKg > 0) {
                    const batchName = item.unit_name || '';
                    let w = wKg;
                    if (isGramUnitName(batchName)) w = wKg * 1000;
                    else if (!isKiloUnitName(batchName)) w = 0;
                    price = w > 0 ? price / w : 0;
                } else {
                    price = 0;
                }
            } else {
                const f = unitFactor(unitId, batch);
                if (f !== null && f > 0) price = price / f;
            }
        }

        state.prices[itemId] = state.prices[itemId] || {};
        state.prices[itemId][custId] = price;

        input.classList.remove('border-warning', 'bg-warning-subtle', 'border-danger');

        if (price <= 0) {
            input.classList.add('border-danger');
        } else if (price < num(item.unit_cost)) {
            input.classList.add('border-warning', 'bg-warning-subtle');
        }

        const tr = input.closest('tr');
        if (tr) {
            const qtyInput = tr.querySelector('.alloc-input');
            const totalInput = tr.querySelector('.alloc-total-input');
            const qtyEntered = num(qtyInput?.value);
            const priceShown = num(input.value);
            if (totalInput) totalInput.value = (qtyEntered * priceShown) > 0 ? fmt(qtyEntered * priceShown) : '';
        }

        renderSummary();
    }

    /**
     * تحويل الكمية المدخلة (بالوحدة المختارة) إلى وحدة الدفعة.
     */
    function qtyToBatchUnit(itemId, custId, qtyEntered, item) {
        const unitId = chosenUnitId(itemId, custId, item);
        const batch = batchUnitId(item);

        if (unitId === null || batch === null || String(unitId) === String(batch)) return qtyEntered;

        if (isPieceUnitId(unitId)) {
            const meta = state.pieceMeta[itemId]?.[custId] || {};
            const kg = num(qtyEntered) * num(meta.weight);
            return kgToBatchUnit(kg, batch, item);
        }

        const f = unitFactor(unitId, batch);
        return f === null ? qtyEntered : qtyEntered * f;
    }

    /** تحويل وزن بالكيلو إلى وحدة الدفعة (كيلو → كيلو = نفسه، كيلو → جرام = ×1000) */
    function kgToBatchUnit(kg, batch, item) {
        const batchName = item.unit_name || '';
        if (isKiloUnitName(batchName)) return kg;
        if (isGramUnitName(batchName)) return kg * 1000;
        return kg;
    }

    /** تحويل السعر المخزن (بوحدة الدفعة) إلى السعر المعروض بالوحدة المختارة */
    function priceFromBatchUnit(itemId, custId, item) {
        const batchPrice = num(item.sale_price);
        const unitId = chosenUnitId(itemId, custId, item);
        const batch = batchUnitId(item);
        if (unitId === null || batch === null || String(unitId) === String(batch)) return batchPrice;

        if (isPieceUnitId(unitId)) {
            const meta = state.pieceMeta[itemId]?.[custId] || {};
            const wKg = num(meta.weight);
            if (wKg > 0) {
                const batchName = item.unit_name || '';
                let weightInBatch = wKg;
                if (isGramUnitName(batchName)) weightInBatch = wKg * 1000;
                else if (!isKiloUnitName(batchName)) return batchPrice;
                return batchPrice * weightInBatch;
            }
            return 0;
        }

        const f = unitFactor(batch, unitId);
        return f === null || f <= 0 ? batchPrice : batchPrice / f;
    }

    /** السعر المعروض بالوحدة المختارة (يُحوّل من السعر المخزن بوحدة الدفعة) */
    function displayPriceFor(itemId, custId, item) {
        const stored = state.prices[itemId]?.[custId];
        const batchPrice = stored !== undefined ? num(stored) : num(item.sale_price);
        const unitId = chosenUnitId(itemId, custId, item);
        const batch = batchUnitId(item);

        if (unitId === null || batch === null || String(unitId) === String(batch)) return batchPrice;

        if (isPieceUnitId(unitId)) {
            const meta = state.pieceMeta[itemId]?.[custId] || {};
            const wKg = num(meta.weight);
            if (wKg <= 0) return 0;
            const batchName = item.unit_name || '';
            let w = wKg;
            if (isGramUnitName(batchName)) w = wKg * 1000;
            else if (!isKiloUnitName(batchName)) return batchPrice;
            return batchPrice * w;
        }
        const f = unitFactor(batch, unitId);
        return f === null || f <= 0 ? batchPrice : batchPrice / f;
    }

    function isPieceMode(itemId, custId, item) {
        const unitId = chosenUnitId(itemId, custId, item);
        return unitId !== null && isPieceUnitId(unitId);
    }

    function handleUnitChange(select) {
        const itemId = state.modalItemId;
        const custId = select.dataset.customer;
        const item = findItem(itemId);
        if (!item) return;

        const newUnit = select.value;
        const batch = batchUnitId(item);
        const qtyInput = select.closest('tr')?.querySelector('.alloc-input');
        const priceInput = select.closest('tr')?.querySelector('.alloc-price-input');

        const curQtyBatch = num(state.allocation[itemId]?.[custId] || 0);
        const curPriceBatch = state.prices[itemId]?.[custId] !== undefined
            ? num(state.prices[itemId][custId]) : num(item.sale_price);

        state.units[itemId] = state.units[itemId] || {};
        state.units[itemId][custId] = newUnit;

        if (!isPieceUnitId(newUnit) && state.pieceMeta[itemId]) {
            delete state.pieceMeta[itemId][custId];
        }

        if (qtyInput) {
            if (isPieceUnitId(newUnit)) {
                qtyInput.value = '';
            } else {
                const f = unitFactor(batch, newUnit);
                qtyInput.value = (f !== null && f > 0) ? fmt(curQtyBatch * f, 3) : (curQtyBatch ? fmt(curQtyBatch, 3) : '');
            }
        }

        if (priceInput) {
            const batchName = item.unit_name || '';
            let shown = curPriceBatch;
            if (isPieceUnitId(newUnit)) {
                const meta = state.pieceMeta[itemId]?.[custId] || {};
                const wKg = num(meta.weight);
                if (wKg > 0) {
                    let w = wKg;
                    if (isGramUnitName(batchName)) w = wKg * 1000;
                    shown = curPriceBatch * w;
                } else shown = 0;
            } else {
                const f = unitFactor(batch, newUnit);
                if (f !== null && f > 0) shown = curPriceBatch / f;
            }
            priceInput.value = shown > 0 ? fmt(shown) : '';
        }

        renderAllocationModal();
    }

    function handlePieceInput(input, kind) {
        const itemId = state.modalItemId;
        const custId = input.dataset.customer;
        const item = findItem(itemId);
        if (!item) return;

        state.pieceMeta[itemId] = state.pieceMeta[itemId] || {};
        const meta = state.pieceMeta[itemId][custId] || { count: '', weight: '' };

        if (kind === 'count') meta.count = input.value;
        if (kind === 'weight') meta.weight = input.value;

        state.pieceMeta[itemId][custId] = meta;

        const count = num(meta.count);
        const wKg = num(meta.weight);
        const kgTotal = count * wKg;

        const qtyBatch = kgToBatchUnit(kgTotal, batchUnitId(item), item);

        const others = Object.entries(state.allocation[itemId] || {})
            .filter(([cid]) => cid != custId)
            .reduce((s, [, q]) => s + num(q), 0);
        const maxForField = num(item.available) - others;

        if (qtyBatch > maxForField && maxForField >= 0) {
            toastWarning(`المكافئ الوزني (${fmt(qtyBatch, 3)} ${item.unit_name || ''}) يتجاوز المتاح (${fmt(maxForField, 3)})`);
        }

        state.allocation[itemId] = state.allocation[itemId] || {};
        state.allocation[itemId][custId] = Math.min(qtyBatch, Math.max(0, maxForField));

        const tr = input.closest('tr');
        if (tr) {
            const priceInput = tr.querySelector('.alloc-price-input');
            const totalInput = tr.querySelector('.alloc-total-input');

            if (priceInput) {
                if (document.activeElement !== priceInput) {
                    const batchName = item.unit_name || '';
                    let w = wKg;
                    if (isGramUnitName(batchName)) w = wKg * 1000;
                    const storedPrice = state.prices[itemId]?.[custId] !== undefined
                        ? num(state.prices[itemId][custId]) : num(item.sale_price);
                    const piecePrice = storedPrice * w;
                    priceInput.value = piecePrice > 0 ? fmt(piecePrice) : '';
                }
            }
            if (totalInput && kgTotal > 0) {
                const piecePrice = num(priceInput?.value) || 0;
                totalInput.value = (count * piecePrice) > 0 ? fmt(count * piecePrice) : '';
            }
            const block = tr.querySelector('.piece-weight-block');
            if (block) {
                const info = block.querySelector('.piece-weight-total');
                if (info) info.textContent = kgTotal > 0 ? `${fmt(kgTotal, 3)} كجم إجمالي` : '';
            }
        }

        const totalAlloc = getAllocatedQty(itemId);
        const remaining = Math.max(0, num(item.available) - totalAlloc);
        if ($('allocAllocated')) $('allocAllocated').textContent = fmt(totalAlloc, 3);
        if ($('allocRemaining')) $('allocRemaining').textContent = fmt(remaining, 3);

        renderSummary();
        renderTabs();
    }

    function navigateItem(direction) {
        if (!state.modalInvoiceId) return;

        const itemId = state.modalItemId;
        const emptyNames = getEmptyCustomersForItem(itemId);
        if (emptyNames.length > 0) {
            const preview = emptyNames.slice(0, 3).join('، ');
            const more = emptyNames.length > 3 ? ` و${emptyNames.length - 3} آخرين` : '';
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

    let accountPickerInstance = null;

    function ensureMultiSelectUI() {
        const results = $('AccountPickerResults');
        const table = results?.closest('table');
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
                        id: cb.dataset.id,
                        name: cb.dataset.name,
                        phone: cb.dataset.phone || '',
                        code: cb.dataset.code || '',
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
        const el = $('AccountPickerSelectedCount');
        const btn = $('AccountPickerAddSelected');
        if (el) el.textContent = count;
        if (btn) btn.disabled = count === 0;
    }

    async function openAccountPicker() {
        if (!state.modalItemId) { toastError('افتح مودال صنف أولًا.'); return; }
        const modalEl = $('AccountPickerModal');
        if (!modalEl) { toastError('مودال الحسابات غير محمّل.'); return; }

        window.__accountPickerOwner = 'allocation';

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

        window.__accountPickerOwner = null;
    }

    $('btnAddCustomerToItem')?.addEventListener('click', openAccountPicker);
    document.querySelectorAll('#AccountPickerModal [data-bs-dismiss="modal"]').forEach(btn =>
        btn.addEventListener('click', closeAccountPicker));

    $('AccountPickerSearch')?.addEventListener('input', debounce(() => {
        if (window.__accountPickerOwner !== 'allocation') return;
        loadAccounts();
    }, 400));

    $('AccountPickerClearBtn')?.addEventListener('click', () => {
        if (window.__accountPickerOwner !== 'allocation') return;
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
                               data-code="${escapeHtml(c.code || '')}"
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

        const item = findItem(itemId);
        if (!item) return;

        const added = [];
        const skipped = [];

        customers.forEach(c => {
            if (!state.customerInfo[c.id]) {
                state.customerInfo[c.id] = {
                    id: c.id, name: c.name, phone: c.phone || '', code: c.code || '',
                };
            }

            state.allocation[itemId] = state.allocation[itemId] || {};
            state.prices[itemId] = state.prices[itemId] || {};
            state.units[itemId] = state.units[itemId] || {};

            if (state.allocation[itemId][c.id] === undefined) {
                state.allocation[itemId][c.id] = 0;
                state.prices[itemId][c.id] = num(item.sale_price);
                state.units[itemId][c.id] = batchUnitId(item);
                added.push(c);
            } else {
                skipped.push(c);
            }
        });

        renderAllocationModal();
        renderItems();
        renderSummary();
        renderTabs();

        const itemName = item.item_name || '';

        if (added.length === 1) {
            toastSuccess(`أضيف العميل «${added[0].name}» إلى الصنف «${itemName}». أدخل الكمية الآن.`);
        } else if (added.length > 1) {
            toastSuccess(`أضيف ${added.length} عملاء إلى الصنف «${itemName}». أدخل كمياتهم الآن.`);
        }
        if (skipped.length > 0) {
            toastInfo(`${skipped.length} عميل مضاف مسبقًا — تم تجاهلهم.`);
        }
    }

    function renderSummary() {
        let itemsCount = 0, cost = 0, sale = 0;
        const customersSet = new Set();

        Object.values(state.invoices).forEach(inv => {
            (inv.items || []).forEach(item => {
                const alloc = getAllocatedQty(item.id);

                if (alloc > 0) {
                    itemsCount++;
                    cost += alloc * num(item.unit_cost);

                    Object.entries(state.allocation[item.id] || {}).forEach(([cid, q]) => {
                        if (num(q) > 0) {
                            customersSet.add(cid);
                            const price = state.prices[item.id]?.[cid] ?? num(item.sale_price);
                            sale += num(q) * price;
                        }
                    });
                }
            });
        });

        if ($('sumItemsCount')) $('sumItemsCount').textContent = itemsCount;
        if ($('sumCustomersCount')) $('sumCustomersCount').textContent = customersSet.size;
        if ($('sumCost')) $('sumCost').textContent = fmt(cost);
        if ($('sumSale')) $('sumSale').textContent = fmt(sale);
    }

    $('btnSaveDraft')?.addEventListener('click', () => save());

    async function save() {
        if (!Object.keys(state.invoices).length) {
            toastError('اختر فاتورة واحدة على الأقل.');
            return;
        }

        const empties = findEmptyAllocations();
        if (empties.length > 0) {
            const first = empties[0];
            const item = findItem(first.itemId);
            const cust = state.customerInfo[first.custId];
            const invOfItem = findInvoiceOfItem(first.itemId);

            toastError(
                `لا يمكن الحفظ — يوجد ${empties.length} عميل بحقل فارغ. ` +
                `أكمل الكميات أولًا: الصنف «${item?.item_name || ''}» — العميل «${cust?.name || ''}».`
            );

            if (invOfItem) {
                state.activeInvoiceId = invOfItem.id;
                renderTabs();
                renderItems();

                state.modalItemId = first.itemId;
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

        let invalidPrice = null;
        for (const [mdId, row] of Object.entries(state.allocation)) {
            const item = findItem(mdId);
            if (!item) continue;

            for (const [custId, qty] of Object.entries(row)) {
                if (num(qty) > 0) {
                    const price = state.prices[mdId]?.[custId];
                    if (price === undefined || num(price) <= 0) {
                        invalidPrice = { mdId, custId, item, cust: state.customerInfo[custId] };
                        break;
                    }
                }
            }
            if (invalidPrice) break;
        }

        if (invalidPrice) {
            toastError(`لا يمكن الحفظ — السعر فارغ أو صفر للعميل «${invalidPrice.cust?.name || ''}» على الصنف «${invalidPrice.item.item_name}».`);

            const invOfItem = findInvoiceOfItem(invalidPrice.mdId);
            if (invOfItem) {
                state.activeInvoiceId = invOfItem.id;
                renderTabs();
                renderItems();

                state.modalItemId = invalidPrice.mdId;
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
                    const price = state.prices[mdId]?.[custId] ?? num(item.sale_price);
                    details.push({
                        movement_detail_id: parseInt(mdId, 10),
                        purchase_invoice_id: parseInt(item.purchase_invoice_id, 10),
                        movement_id: parseInt(item.movement_id, 10),
                        customer_id: parseInt(custId, 10),
                        quantity: num(qty),
                        sale_price: num(price),
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
            units:             state.units,
            piece_meta:        state.pieceMeta,
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

            await loadExisting(res.id);
        } catch (e) {
            toastError('فشل الحفظ: ' + e.message);
        } finally {
            if ($('btnSaveDraft')) $('btnSaveDraft').disabled = false;
        }
    }

    async function loadExisting(id) {
        try {
            const res = await api(ROUTES.show(id));
            if (!res.success) return;

            state.invoices = {};
            state.activeInvoiceId = null;
            state.customerInfo = {};
            state.allocation = {};
            state.prices = {};
            state.units = {};
            state.pieceMeta = {};
            state.modalItemId = null;
            state.modalInvoiceId = null;

            state.distributionId = res.header.id;
            updateModeBadge();
            if ($('distributionDate')) $('distributionDate').value = res.header.distribution_date || '';
            if ($('distributionNotes')) $('distributionNotes').value = res.header.notes || '';

            if (res.header.unit_meta?.units) {
                state.units = res.header.unit_meta.units;
            }
            if (res.header.unit_meta?.piece_meta) {
                state.pieceMeta = res.header.unit_meta.piece_meta;
            }

            const invoiceIds = [...new Set((res.details || []).map(d => d.purchase_invoice_id).filter(Boolean))];
            for (const invId of invoiceIds) {
                try {
                    const invRes = await api(ROUTES.items(invId) + `?exclude_distribution_id=${encodeURIComponent(id)}`);
                    if (invRes.success) {
                        state.invoices[invId] = {
                            id: invId,
                            number: invRes.invoice.invoice_number,
                            date: invRes.invoice.invoice_date,
                            supplier: invRes.invoice.supplier,
                            items: invRes.data || [],
                        };
                    }
                } catch (e) { /* تخطي */ }
            }

            (res.details || []).forEach(d => {
                if (num(d.quantity) <= 0) return;

                state.customerInfo[d.customer_id] = {
                    id: d.customer_id,
                    name: d.customer_name || `#${d.customer_id}`,
                    phone: d.customer_phone || '',
                    code: d.customer_code || '',
                };
                state.allocation[d.movement_detail_id] = state.allocation[d.movement_detail_id] || {};
                state.allocation[d.movement_detail_id][d.customer_id] = num(d.quantity);

                state.prices[d.movement_detail_id] = state.prices[d.movement_detail_id] || {};
                state.prices[d.movement_detail_id][d.customer_id] = num(d.sale_price);

                const item = findItem(d.movement_detail_id);
                if (item && item.unit_id != null) {
                    state.units[d.movement_detail_id] = state.units[d.movement_detail_id] || {};
                    if (state.units[d.movement_detail_id][d.customer_id] === undefined) {
                        state.units[d.movement_detail_id][d.customer_id] = item.unit_id;
                    }
                }
            });

            await ensureUnitsCache();

            pruneOrphanCustomers();

            const firstInvId = Object.keys(state.invoices)[0];
            state.activeInvoiceId = firstInvId ? parseInt(firstInvId, 10) : null;

            renderTabs();
            renderItems();
            renderSummary();
            toastInfo(`تم تحميل التوزيع ${res.header.code} — وضع التعديل.`);
        } catch (e) {
            toastError('فشل تحميل التوزيع: ' + e.message);
        }
    }

    $('btnCancelAll')?.addEventListener('click', resetScreen);
    $('btnNew')?.addEventListener('click', async () => {
        const ok = await confirmDialog('بدء توزيع جديد؟ سيتم فقدان البيانات الحالية إن لم تُحفظ.', {
            title: 'توزيع جديد', okText: 'بدء جديد', danger: true,
        });
        if (!ok) return;
        window.location.href = window.location.pathname;
    });

    async function resetScreen() {
        const ok = await confirmDialog('تفريغ الشاشة من كل البيانات؟', {
            title: 'تفريغ الشاشة', okText: 'تفريغ', danger: true,
        });
        if (!ok) return;

        state.distributionId = null;
        state.invoices = {};
        state.activeInvoiceId = null;
        state.customerInfo = {};
        state.allocation = {};
        state.prices = {};
        state.units = {};
        state.pieceMeta = {};
        state.modalItemId = null;
        state.modalInvoiceId = null;
        updateModeBadge();
        if ($('distributionNotes')) $('distributionNotes').value = '';
        if ($('itemsSearch')) $('itemsSearch').value = '';
        renderTabs();
        renderItems();
        renderSummary();
        toastInfo('تم تفريغ الشاشة.');
    }

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