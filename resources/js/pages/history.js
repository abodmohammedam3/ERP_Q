// resources/js/pages/purchase-distribution/history.js

let BootstrapModal = null;
async function ensureBootstrap() {
    if (BootstrapModal) return BootstrapModal;
    if (window.bootstrap?.Modal) { BootstrapModal = window.bootstrap.Modal; return BootstrapModal; }
    try { const m = await import('bootstrap'); BootstrapModal = m.Modal; return BootstrapModal; }
    catch (e) { console.error('[history] Bootstrap غير متاح:', e); return null; }
}

document.addEventListener('DOMContentLoaded', () => {

    const ROUTES = {
        stats:           '/operation/purchases/distribution/stats',
        list:            '/operation/purchases/distribution/list/all',
        show:            (id) => `/operation/purchases/distribution/${id}`,
        customers:       '/operation/purchases/distribution/customers',
        customerSummary: '/operation/purchases/distribution/customer-summary',
        searchInvoices:  '/operation/purchases/distribution/invoices-search',
        invoiceSummary:  '/operation/purchases/distribution/invoice-summary',
        distributionPage:'/operation/purchases/distribution',
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

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

    function showToast(msg, type = 'info') {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(msg, type); return;
        }
        const icons = { success:'check-circle', danger:'exclamation-triangle', warning:'exclamation-circle', info:'info-circle' };
        const cls = { success:'alert-success', danger:'alert-danger', warning:'alert-warning', info:'alert-info' };
        const area = $('alertArea');
        if (!area) { console.log(`[${type}] ${msg}`); return; }
        area.innerHTML = `
            <div class="alert ${cls[type] || 'alert-info'} border-0 alert-dismissible fade show py-2 d-flex align-items-center gap-2">
                <i class="bi bi-${icons[type] || 'info-circle'}"></i>
                <span>${escapeHtml(msg)}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>`;
    }
    const toastSuccess = (m) => showToast(m, 'success');
    const toastError   = (m) => showToast(m, 'danger');
    const toastInfo    = (m) => showToast(m, 'info');

    async function confirmDialog(msg, opts = {}) {
        if (typeof window.appConfirm === 'function') {
            try { return await window.appConfirm(msg, opts); }
            catch (e) { /* fallback */ }
        }
        return window.confirm(msg);
    }

    // ============================================================
    // حالة التحميل لكل جزء — يمنع إعادة التحميل غير الضروري
    // ============================================================
    const loaded = {
        stats:            false,
        distributions:    false,
        customersTab:     false,
        invoicesTab:      false,
        customerSelected: false,
        invoiceSelected:  false,
    };

    // ============================================================
    // 1) الإحصائيات — تُحمَّل مرة واحدة فقط
    // ============================================================
    async function loadStats() {
        if (loaded.stats) return;
        try {
            const res = await api(ROUTES.stats);
            const d = res.data || {};
            if ($('statTotal'))    $('statTotal').textContent    = d.total    ?? 0;
            if ($('statDraft'))    $('statDraft').textContent    = d.draft    ?? 0;
            if ($('statInvoiced')) $('statInvoiced').textContent = d.invoiced ?? 0;
            loaded.stats = true;
        } catch (e) {
            console.warn('[history] فشل تحميل الإحصائيات:', e.message);
        }
    }

    // ============================================================
    // التبويبات — Lazy Loading
    // ============================================================
    document.querySelectorAll('#historyTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#historyTabs .nav-link').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const tab = btn.dataset.tab;
            ['distributions','customers','invoices'].forEach(t => {
                $('tab-' + t)?.classList.toggle('d-none', t !== tab);
            });

            if (tab === 'distributions' && !loaded.distributions) {
                loadDistributions();
            }
            if (tab === 'customers' && !loaded.customersTab) {
                loaded.customersTab = true;
            }
            if (tab === 'invoices' && !loaded.invoicesTab) {
                loaded.invoicesTab = true;
            }
        });
    });

    // ============================================================
    // 2) تبويب التوزيعات — مستقل (مع Pagination)
    // ============================================================
    let distModalInstance = null;
    const distPager = { page: 1, perPage: 25, total: 0, lastPage: 1 };

    async function loadDistributions(page = 1) {
        const tbody = $('distTbody');
        if (!tbody) return;
        distPager.page = page;

        tbody.innerHTML = `
            <tr><td colspan="9" class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div> جارٍ التحميل...
            </td></tr>`;

        try {
            const params = new URLSearchParams();
            if ($('fSearch')?.value.trim())  params.set('search',    $('fSearch').value.trim());
            if ($('fStatus')?.value)         params.set('status',    $('fStatus').value);
            if ($('fDateFrom')?.value)       params.set('date_from', $('fDateFrom').value);
            if ($('fDateTo')?.value)         params.set('date_to',   $('fDateTo').value);
            params.set('page', String(distPager.page));
            params.set('per_page', String(distPager.perPage));

            const res = await api(`${ROUTES.list}?${params}`);
            renderDistRows(res.data || []);
            if (res.meta) {
                distPager.total    = res.meta.total;
                distPager.lastPage = res.meta.last_page;
                renderDistPager(res.meta);
            }
            loaded.distributions = true;
        } catch (e) {
            tbody.innerHTML = `
                <tr><td colspan="9" class="text-center text-danger py-4">
                    فشل التحميل: ${escapeHtml(e.message)}
                </td></tr>`;
        }
    }

    function renderDistPager(meta) {
        const pager = $('distPager');
        const info  = $('distPagerInfo');
        if (!pager || !info) return;

        if (!meta || meta.total === 0) {
            pager.style.display = 'none';
            info.textContent = '';
            return;
        }

        pager.style.display = 'flex';
        const from = (meta.page - 1) * meta.per_page + 1;
        const to   = Math.min(meta.page * meta.per_page, meta.total);
        info.textContent = `عرض ${from}–${to} من ${meta.total} توزيع`;

        $('distPageLabel').textContent = `صفحة ${meta.page} من ${meta.last_page}`;
        $('distPrevBtn').disabled = meta.page <= 1;
        $('distNextBtn').disabled = meta.page >= meta.last_page;
    }

    $('distPrevBtn')?.addEventListener('click', () => loadDistributions(distPager.page - 1));
    $('distNextBtn')?.addEventListener('click', () => loadDistributions(distPager.page + 1));

    function renderDistRows(rows) {
        const tbody = $('distTbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!rows.length) {
            tbody.innerHTML = `
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
                    لا توجد توزيعات مطابقة.
                </td></tr>`;
            return;
        }

        rows.forEach((r, i) => {
            const statusBadge = r.status === 'invoiced'
                ? `<span class="badge bg-success-subtle text-success" title="فاتورات البيع: ${(r.sales_invoice_numbers || []).map(escapeHtml).join('، ')}"><i class="bi bi-check2-circle"></i> مفوتر${(r.sales_invoice_numbers || []).length ? ` · ${r.sales_invoice_numbers.length}` : ''}</span>`
                : '<span class="badge bg-warning-subtle text-warning"><i class="bi bi-pencil-square"></i> مسودة</span>';

            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.innerHTML = `
                <td class="text-muted">${i + 1}</td>
                <td class="fw-semibold">${escapeHtml(r.code)}</td>
                <td class="text-muted">${escapeHtml(r.distribution_date)}</td>
                <td>${statusBadge}</td>
                <td class="text-center">${r.details_count}</td>
                <td class="text-center">${r.customers_count}</td>
                <td class="text-end text-danger">${fmt(r.total_cost)} ر.ي</td>
                <td class="text-end text-success">${fmt(r.total_sale)} ر.ي</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            `;
            tr.addEventListener('click', () => openDistDetails(r.id));
            tbody.appendChild(tr);
        });
    }

    $('btnRefresh')?.addEventListener('click', loadDistributions);
    $('btnClear')?.addEventListener('click', () => {
        if ($('fSearch'))   $('fSearch').value   = '';
        if ($('fStatus'))   $('fStatus').value   = '';
        if ($('fDateFrom')) $('fDateFrom').value = '';
        if ($('fDateTo'))   $('fDateTo').value   = '';
        loadDistributions();
    });
    $('fSearch')?.addEventListener('input', debounce(loadDistributions, 400));
    $('fStatus')?.addEventListener('change', loadDistributions);
    $('fDateFrom')?.addEventListener('change', loadDistributions);
    $('fDateTo')?.addEventListener('change', loadDistributions);

    // ============================================================
    // 3) مودال تفاصيل التوزيع
    // ============================================================
    async function openDistDetails(id) {
        try {
            const res = await api(ROUTES.show(id));
            if (!res.success) { toastError('تعذر التحميل.'); return; }

            $('ddTitle').textContent    = `تفاصيل التوزيع ${res.header.code}`;
            $('ddSubtitle').textContent = `${res.header.distribution_date} · ${res.header.status_label}`;

            $('ddDetailsCount').textContent   = res.summary.details_count;
            $('ddCustomersCount').textContent = res.summary.customers_count;

            const qtyParts = Object.entries(res.summary.qty_by_unit || {})
                .map(([u, q]) => `${fmt(q, 3)} ${escapeHtml(u)}`);
            $('ddQty').textContent = qtyParts.length ? qtyParts.join(' + ') : '0';

            $('ddTotalCost').textContent = `${fmt(res.summary.total_cost)} ر.ي`;
            $('ddTotalSale').textContent = `${fmt(res.summary.total_sale)} ر.ي`;

            const tbody = $('ddTbody');
            tbody.innerHTML = '';
            (res.details || []).forEach(d => {
                const invBadge = d.is_invoiced
                    ? `<span class="badge bg-success-subtle text-success" title="فاتورة البيع رقم ${escapeHtml(d.sales_invoice_number || '—')}">مفوتر${d.sales_invoice_number ? ` · ${escapeHtml(d.sales_invoice_number)}` : ''}</span>`
                    : '<span class="badge bg-secondary-subtle text-secondary">غير مفوتر</span>';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="fw-semibold small">${escapeHtml(d.item_name)}</td>
                    <td class="text-muted small">${escapeHtml(d.invoice_number || '—')}</td>
                    <td>
                        ${escapeHtml(d.customer_name || '—')}
                        ${d.customer_phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(d.customer_phone)}</div>` : ''}
                    </td>
                    <td class="text-end fw-semibold text-success">${fmt(d.quantity, 3)}</td>
                    <td class="text-center small text-muted">${escapeHtml(d.unit_name || '—')}</td>
                    <td class="text-end text-muted">${fmt(d.unit_cost)}</td>
                    <td class="text-end text-muted">${fmt(d.sale_price)}</td>
                    <td class="text-end">${fmt(d.total_cost)} ر.ي</td>
                    <td class="text-end">${fmt(d.total_sale)} ر.ي</td>
                    <td class="text-center">${invBadge}</td>
                `;
                tbody.appendChild(tr);
            });

            const editBtn = $('ddEditBtn');
            if (res.header.is_draft) {
                editBtn.classList.remove('d-none');
                editBtn.href = `${ROUTES.distributionPage}?id=${res.header.id}`;
            } else {
                editBtn.classList.add('d-none');
            }

            // تحميل سجل التتبع (Audit Log)
            loadDistLogs(res.header.id);

            const modalEl = $('distDetailsModal');
            const Modal = await ensureBootstrap();
            if (Modal) {
                if (!distModalInstance) distModalInstance = new Modal(modalEl);
                distModalInstance.show();
            } else {
                modalEl.style.display = 'block';
                modalEl.classList.add('show');
                document.body.classList.add('modal-open');
            }
        } catch (e) {
            toastError('فشل التحميل: ' + e.message);
        }
    }

    // ============================================================
    // 3-أ) سجل تتبع التوزيع (Audit Log)
    // ============================================================
    const ACTION_ICONS = {
        created: 'plus-circle text-success',
        updated: 'pencil-square text-warning',
        issued:  'receipt text-primary',
        deleted: 'trash text-danger',
    };

    async function loadDistLogs(distributionId) {
        const box = $('ddLogsBody');
        if (!box) return;
        box.innerHTML = '<div class="py-2 text-center text-muted">جارٍ التحميل…</div>';

        try {
            const res = await api(`${ROUTES.show(distributionId)}/logs`);
            const rows = res.data || [];
            if (!rows.length) {
                box.innerHTML = '<div class="py-2 text-center text-muted">لا توجد فعاليات مسجّلة بعد.</div>';
                return;
            }
            box.innerHTML = '';
            rows.forEach(l => {
                const icon = ACTION_ICONS[l.action] || 'circle text-muted';
                const metaTxt = l.meta
                    ? Object.entries(l.meta).map(([k, v]) => `${escapeHtml(k)}: ${escapeHtml(String(v))}`).join('، ')
                    : '';
                const el = document.createElement('div');
                el.className = 'd-flex align-items-start gap-2 py-1 border-bottom';
                el.innerHTML = `
                    <i class="bi bi-${icon}"></i>
                    <div class="flex-fill">
                        <div class="fw-semibold">${escapeHtml(l.action_label)}</div>
                        <div class="text-muted">${escapeHtml(l.description || '')}</div>
                        ${metaTxt ? `<div class="text-muted" style="font-size:0.72rem;">${metaTxt}</div>` : ''}
                    </div>
                    <div class="text-end text-muted" style="font-size:0.72rem; min-width:110px;">
                        ${escapeHtml(l.created_at || '—')}
                    </div>
                `;
                box.appendChild(el);
            });
        } catch (e) {
            box.innerHTML = `<div class="py-2 text-center text-danger">فشل التحميل: ${escapeHtml(e.message)}</div>`;
        }
    }

    // ============================================================
    // 4) تبويب العملاء — مستقل
    // ============================================================
    let selectedCustomer = null;

    $('custSearch')?.addEventListener('input', debounce(searchCustomers, 400));
    $('custInvoicedFilter')?.addEventListener('change', () => {
        if (selectedCustomer) loadCustomerSummary(selectedCustomer.id);
    });

    async function searchCustomers() {
        const q = $('custSearch').value.trim();
        const box = $('custResults');
        if (!box) return;
        if (q.length < 2) { box.style.display = 'none'; box.innerHTML = ''; return; }

        try {
            const res = await api(`${ROUTES.customers}?q=${encodeURIComponent(q)}`);
            const rows = res.data || [];
            box.innerHTML = '';
            box.style.display = rows.length ? '' : 'none';

            rows.forEach(c => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action py-2 small border-0';
                btn.innerHTML = `
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-circle text-success"></i>
                        <div class="text-start">
                            <div class="fw-semibold">${escapeHtml(c.name)}</div>
                            ${c.phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(c.phone)}</div>` : ''}
                        </div>
                    </div>`;
                btn.addEventListener('click', () => {
                    box.style.display = 'none';
                    $('custSearch').value = c.name;
                    selectedCustomer = c;
                    loadCustomerSummary(c.id);
                });
                box.appendChild(btn);
            });

            if (!rows.length) {
                box.innerHTML = '<div class="list-group-item text-muted small text-center py-2 border-0">لا نتائج</div>';
            }
        } catch (e) { /* ignore */ }
    }

    async function loadCustomerSummary(customerId) {
        $('custEmpty')?.classList.add('d-none');
        $('custSummaryBox')?.classList.remove('d-none');
        const tbody = $('custTbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr><td colspan="8" class="text-center text-muted py-3">
                    <div class="spinner-border spinner-border-sm text-primary"></div> جارٍ التحميل...
                </td></tr>`;
        }

        try {
            const filter = $('custInvoicedFilter')?.value || 'all';
            const params = new URLSearchParams({ customer_id: customerId, invoiced_filter: filter });
            const res = await api(`${ROUTES.customerSummary}?${params}`);

            if ($('custName'))  $('custName').textContent  = res.customer.name;
            if ($('custPhone')) $('custPhone').textContent = res.customer.phone || '';

            $('custRowsCount').textContent        = res.summary.rows_count;
            $('custNotInvoicedCount').textContent = res.summary.not_invoiced_count;
            $('custTotalCost').textContent        = `${fmt(res.summary.total_cost)} ر.ي`;
            $('custTotalSale').textContent        = `${fmt(res.summary.total_sale)} ر.ي`;

            const notInvQty = Object.entries(res.summary.not_invoiced_qty || {})
                .map(([u, q]) => `${fmt(q, 3)} ${u}`).join(' + ');
            $('custNotInvoicedQty').textContent = notInvQty || '—';

            const issueBtn = $('btnIssueForCustomer');
            if (issueBtn) {
                issueBtn.disabled = res.summary.not_invoiced_count === 0;
                issueBtn.onclick = () => {
                    window.open('/operation/purchases/distribution/customer-invoices?customer_id=' + customerId, '_blank');
                };
            }

            if (tbody) {
                tbody.innerHTML = '';
                (res.data || []).forEach(d => {
                    const badge = d.is_invoiced
                        ? `<span class="badge bg-success-subtle text-success" title="فاتورة البيع رقم ${escapeHtml(d.sales_invoice_number || '—')}">مفوتر${d.sales_invoice_number ? ` · ${escapeHtml(d.sales_invoice_number)}` : ''}</span>`
                        : '<span class="badge bg-warning-subtle text-warning">غير مفوتر</span>';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="fw-semibold small">${escapeHtml(d.item_name)}</td>
                        <td class="text-muted small">${escapeHtml(d.invoice_number || '—')}</td>
                        <td class="text-muted small">${escapeHtml(d.distribution || '—')}</td>
                        <td class="text-end fw-semibold text-success">${fmt(d.quantity, 3)}</td>
                        <td class="text-center small text-muted">${escapeHtml(d.unit_name || '—')}</td>
                        <td class="text-end text-muted">${fmt(d.total_cost)}</td>
                        <td class="text-end text-muted">${fmt(d.total_sale)}</td>
                        <td class="text-center">${badge}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            loaded.customerSelected = true;
        } catch (e) {
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-3">فشل: ${escapeHtml(e.message)}</td></tr>`;
            }
        }
    }

    // ============================================================
    // 5) تبويب الفواتير — مستقل
    // ============================================================
    let selectedInvoice = null;

    $('invSearch')?.addEventListener('input', debounce(searchInvoices, 400));

    async function searchInvoices() {
        const q = $('invSearch').value.trim();
        const box = $('invResults');
        if (!box) return;
        if (q.length < 1) { box.style.display = 'none'; box.innerHTML = ''; return; }

        try {
            const res = await api(`${ROUTES.searchInvoices}?q=${encodeURIComponent(q)}`);
            const rows = res.data || [];
            box.innerHTML = '';
            box.style.display = rows.length ? '' : 'none';

            rows.forEach(inv => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action py-2 small border-0';
                btn.innerHTML = `
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-receipt text-info"></i>
                        <div class="text-start">
                            <div class="fw-semibold">${escapeHtml(inv.number)}</div>
                            <div class="text-muted" style="font-size:0.7rem;">
                                ${escapeHtml(inv.supplier)} · ${escapeHtml(inv.date)}
                            </div>
                        </div>
                    </div>`;
                btn.addEventListener('click', () => {
                    box.style.display = 'none';
                    $('invSearch').value = `${inv.number} — ${inv.supplier}`;
                    selectedInvoice = inv;
                    loadInvoiceSummary(inv.id);
                });
                box.appendChild(btn);
            });

            if (!rows.length) {
                box.innerHTML = '<div class="list-group-item text-muted small text-center py-2 border-0">لا نتائج</div>';
            }
        } catch (e) { /* ignore */ }
    }

    async function loadInvoiceSummary(invoiceId) {
        $('invEmpty')?.classList.add('d-none');
        $('invSummaryBox')?.classList.remove('d-none');

        const tbody = $('invTbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr><td colspan="9" class="text-center text-muted py-3">
                    <div class="spinner-border spinner-border-sm text-primary"></div> جارٍ التحميل...
                </td></tr>`;
        }

        try {
            const res = await api(`${ROUTES.invoiceSummary}?invoice_id=${invoiceId}`);

            if ($('invNumber'))   $('invNumber').textContent   = res.invoice.number;
            if ($('invSupplier')) $('invSupplier').textContent = res.invoice.supplier;
            if ($('invDate'))     $('invDate').textContent     = res.invoice.date;
            if ($('invTotal'))    $('invTotal').textContent    = `${fmt(res.invoice.total)} ر.ي`;

            if ($('invItemsCount'))       $('invItemsCount').textContent       = res.summary.items_count;
            if ($('invCustomersCount'))   $('invCustomersCount').textContent   = res.summary.customers_count;
            if ($('invTotalCost'))        $('invTotalCost').textContent        = `${fmt(res.summary.total_cost)} ر.ي`;
            if ($('invTotalSale'))        $('invTotalSale').textContent        = `${fmt(res.summary.total_sale)} ر.ي`;
            if ($('invNotInvoicedCount')) $('invNotInvoicedCount').textContent = res.summary.not_invoiced_count;

            const qtyParts = Object.entries(res.summary.qty_by_unit || {})
                .map(([u, q]) => `${fmt(q, 3)} ${u}`).join(' + ');
            if ($('invQty')) $('invQty').textContent = qtyParts || '—';

            if (tbody) {
                tbody.innerHTML = '';
                if (!res.data || !res.data.length) {
                    tbody.innerHTML = `
                        <tr><td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
                            لم يتم توزيع أي صنف من هذه الفاتورة بعد.
                        </td></tr>`;
                } else {
                    res.data.forEach(d => {
                        const badge = d.is_invoiced
                            ? `<span class="badge bg-success-subtle text-success" title="فاتورة البيع رقم ${escapeHtml(d.sales_invoice_number || '—')}">مفوتر${d.sales_invoice_number ? ` · ${escapeHtml(d.sales_invoice_number)}` : ''}</span>`
                            : '<span class="badge bg-warning-subtle text-warning">غير مفوتر</span>';
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="fw-semibold small">${escapeHtml(d.item_name)}</td>
                            <td class="text-muted small">${escapeHtml(d.distribution || '—')}</td>
                            <td class="text-muted small">${escapeHtml(d.dist_date || '—')}</td>
                            <td>
                                ${escapeHtml(d.customer_name)}
                                ${d.customer_phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(d.customer_phone)}</div>` : ''}
                            </td>
                            <td class="text-end fw-semibold text-success">${fmt(d.quantity, 3)}</td>
                            <td class="text-center small text-muted">${escapeHtml(d.unit_name || '—')}</td>
                            <td class="text-end text-muted">${fmt(d.total_cost)}</td>
                            <td class="text-end text-muted">${fmt(d.total_sale)}</td>
                            <td class="text-center">${badge}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            }

            loaded.invoiceSelected = true;
        } catch (e) {
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-3">فشل: ${escapeHtml(e.message)}</td></tr>`;
            }
        }
    }

    // ============================================================
    // التهيئة — تحميل مستقل
    // ============================================================
    loadStats();
    loadDistributions();
});