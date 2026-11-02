// resources/js/pages/history.js

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
            window.showSystemToast(msg, type);
            return;
        }
        console.log(`[history:${type}] ${msg}`);
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
    // الإحصائيات
    // ============================================================
    const loaded = { stats: false, distributions: false, invoiceTab: false };

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
    // التبويبات الداخلية للسجل
    // ============================================================
    document.querySelectorAll('#historyTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#historyTabs .nav-link').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const tab = btn.dataset.tab;
            ['distributions','invoices'].forEach(t => {
                $('tab-' + t)?.classList.toggle('d-none', t !== tab);
            });

            if (tab === 'distributions' && !loaded.distributions) {
                loadDistributions();
            }
            if (tab === 'invoices' && !loaded.invoiceTab) {
                loaded.invoiceTab = true;
            }
        });
    });

    // ============================================================
    // تبويب التوزيعات
    // ============================================================
    let distModalInstance = null;

    async function loadDistributions() {
        const tbody = $('distTbody');
        if (!tbody) return;

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

            const res = await api(`${ROUTES.list}?${params}`);
            renderDistRows(res.data || []);
            loaded.distributions = true;
        } catch (e) {
            tbody.innerHTML = `
                <tr><td colspan="9" class="text-center text-danger py-4">
                    فشل التحميل: ${escapeHtml(e.message)}
                </td></tr>`;
        }
    }

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
                ? '<span class="badge bg-success-subtle text-success"><i class="bi bi-check2-circle"></i> مفوتر</span>'
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
    // مودال تفاصيل التوزيع
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
                    ? '<span class="badge bg-success-subtle text-success">مفوتر</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary">غير مفوتر</span>';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="fw-semibold small">${escapeHtml(d.item_name)}</td>
                    <td class="small text-muted">${escapeHtml(d.type_name || '—')}</td>
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
    // تبويب الفواتير
    // ============================================================
    let invoicePickerInstance = null;
    let selectedInvoice = null;
    let invoiceData = [];
    let invoiceSearchTerm  = '';
    let invoiceSearchScope = 'all';

    // ✅ معرّفات فريدة لتبويب السجل
    $('btnPickHistoryInvoice')?.addEventListener('click', openInvoicePicker);

    async function openInvoicePicker() {
        const modalEl = $('historyInvoicePickerModal');
        if (!modalEl) return;

        const Modal = await ensureBootstrap();
        if (Modal) {
            if (!invoicePickerInstance) invoicePickerInstance = new Modal(modalEl);
            invoicePickerInstance.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }

        if ($('hpFilterDate') && !$('hpFilterDate').value) {
            $('hpFilterDate').value = new Date().toISOString().slice(0, 10);
        }
        if ($('hpFilterSupplier')) $('hpFilterSupplier').value = '';

        loadInvoiceList();
    }

    function closeInvoicePicker() {
        const modalEl = $('historyInvoicePickerModal');
        if (invoicePickerInstance) invoicePickerInstance.hide();
        else if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    }

    document.querySelectorAll('#historyInvoicePickerModal [data-bs-dismiss="modal"]').forEach(btn =>
        btn.addEventListener('click', closeInvoicePicker));

    $('hpFilterDate')?.addEventListener('change', loadInvoiceList);
    $('hpFilterSupplier')?.addEventListener('input', debounce(loadInvoiceList, 400));
    $('hpClearBtn')?.addEventListener('click', () => {
        if ($('hpFilterDate'))     $('hpFilterDate').value = '';
        if ($('hpFilterSupplier')) $('hpFilterSupplier').value = '';
        loadInvoiceList();
    });

    async function loadInvoiceList() {
        if ($('hpLoading')) $('hpLoading').classList.remove('d-none');
        if ($('hpEmpty'))   $('hpEmpty').classList.add('d-none');
        if ($('hpError'))   $('hpError').classList.add('d-none');

        try {
            const params = new URLSearchParams({
                date:     $('hpFilterDate')?.value || '',
                supplier: $('hpFilterSupplier')?.value.trim() || '',
            });
            const res = await api(`${ROUTES.searchInvoices}?${params}`);
            renderInvoiceList(res.data || []);
        } catch (e) {
            if ($('hpError')) {
                $('hpError').textContent = 'فشل تحميل الفواتير: ' + e.message;
                $('hpError').classList.remove('d-none');
            }
        } finally {
            if ($('hpLoading')) $('hpLoading').classList.add('d-none');
        }
    }

    function renderInvoiceList(rows) {
        const tbody = $('hpTbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!rows.length) {
            if ($('hpEmpty')) $('hpEmpty').classList.remove('d-none');
            return;
        }

        rows.forEach((inv, i) => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.innerHTML = `
                <td class="text-muted">${i + 1}</td>
                <td class="fw-semibold">${escapeHtml(inv.number)}</td>
                <td class="text-muted">${escapeHtml(inv.date)}</td>
                <td>${escapeHtml(inv.supplier)}</td>
                <td class="text-end fw-semibold">${fmt(inv.total)}</td>
            `;
            tr.addEventListener('click', () => {
                closeInvoicePicker();
                selectInvoice(inv);
            });
            tbody.appendChild(tr);
        });
    }

    async function selectInvoice(inv) {
        selectedInvoice = inv;

        // ✅ مسح البحث عند اختيار فاتورة جديدة
        invoiceSearchTerm  = '';
        invoiceSearchScope = 'all';
        if ($('invoiceSearchInput'))  $('invoiceSearchInput').value  = '';
        if ($('invoiceSearchScope'))  $('invoiceSearchScope').value  = 'all';
        if ($('invoiceSearchCount'))  $('invoiceSearchCount').textContent = '';

        if ($('selectedInvoiceLabel')) {
            $('selectedInvoiceLabel').innerHTML = `
                <span class="text-primary">${escapeHtml(inv.number)}</span>
                <span class="text-muted small"> · ${escapeHtml(inv.supplier)}</span>
            `;
        }
        if ($('selectedInvoiceMeta')) {
            $('selectedInvoiceMeta').textContent = `التاريخ: ${inv.date} · الإجمالي: ${fmt(inv.total)} ر.ي`;
            $('selectedInvoiceMeta').style.display = '';
        }

        if ($('invoiceContent')) $('invoiceContent').classList.remove('d-none');
        if ($('invoiceEmpty'))   $('invoiceEmpty').classList.add('d-none');

        try {
            const res = await api(`${ROUTES.invoiceSummary}?invoice_id=${inv.id}`);
            invoiceData = res.data || [];

            if ($('invItemsCount'))       $('invItemsCount').textContent       = res.summary.items_count;
            if ($('invCustomersCount'))   $('invCustomersCount').textContent   = res.summary.customers_count;
            if ($('invNotInvoicedCount')) $('invNotInvoicedCount').textContent = res.summary.not_invoiced_count;
            if ($('invTotalCost'))        $('invTotalCost').textContent        = `${fmt(res.summary.total_cost)} ر.ي`;
            if ($('invTotalSale'))        $('invTotalSale').textContent        = `${fmt(res.summary.total_sale)} ر.ي`;

            const qtyParts = Object.entries(res.summary.qty_by_unit || {})
                .map(([u, q]) => `${fmt(q, 3)} ${u}`).join(' + ');
            if ($('invQty')) $('invQty').textContent = qtyParts || '—';

            renderInvoiceTable();
        } catch (e) {
            toastError('فشل تحميل توزيعات الفاتورة: ' + e.message);
        }
    }

    $('invoiceSearchInput')?.addEventListener('input', (e) => {
        invoiceSearchTerm = e.target.value.trim().toLowerCase();
        renderInvoiceTable();
    });

    $('invoiceSearchScope')?.addEventListener('change', (e) => {
        invoiceSearchScope = e.target.value;
        renderInvoiceTable();
    });

    $('invoiceSearchClearBtn')?.addEventListener('click', () => {
        if ($('invoiceSearchInput')) $('invoiceSearchInput').value = '';
        invoiceSearchTerm = '';
        renderInvoiceTable();
    });

    function renderInvoiceTable() {
        const thead = $('invoiceTableHead');
        const tbody = $('invoiceTbody');
        if (!thead || !tbody) return;

        // ✅ فلترة البيانات حسب البحث
        let filtered = invoiceData;
        if (invoiceSearchTerm) {
            const term = invoiceSearchTerm;
            filtered = invoiceData.filter(d => {
                const itemName  = (d.item_name      || '').toLowerCase();
                const itemCode  = (d.code           || '').toLowerCase();
                const typeName  = (d.type_name      || '').toLowerCase();
                const custName  = (d.customer_name  || '').toLowerCase();
                const custPhone = (d.customer_phone || '').toLowerCase();

                if (invoiceSearchScope === 'item') {
                    return itemName.includes(term) || itemCode.includes(term) || typeName.includes(term);
                }
                if (invoiceSearchScope === 'customer') {
                    return custName.includes(term) || custPhone.includes(term);
                }
                return itemName.includes(term)
                    || itemCode.includes(term)
                    || typeName.includes(term)
                    || custName.includes(term)
                    || custPhone.includes(term);
            });
        }

        // ✅ العدّاد
        if ($('invoiceSearchCount')) {
            $('invoiceSearchCount').textContent = invoiceSearchTerm
                ? `${filtered.length} من ${invoiceData.length}`
                : '';
        }

        // ✅ ترتيب ثابت: حسب الصنف ثم العميل
        const sorted = [...filtered].sort((a, b) => {
            const ia = (a.item_name || '').localeCompare(b.item_name || '');
            if (ia !== 0) return ia;
            return (a.customer_name || '').localeCompare(b.customer_name || '');
        });

        thead.innerHTML = `
            <tr class="small text-muted">
                <th class="fw-semibold">الصنف</th>
                <th class="fw-semibold">النوع</th>
                <th class="fw-semibold">العميل</th>
                <th class="text-end fw-semibold">الكمية</th>
                <th class="text-center fw-semibold">الوحدة</th>
                <th class="text-end fw-semibold">تكلفة الوحدة</th>
                <th class="text-end fw-semibold">إجمالي التكلفة</th>
                <th class="text-center fw-semibold">الحالة</th>
            </tr>
        `;

        tbody.innerHTML = '';

        if (!sorted.length) {
            const msg = invoiceSearchTerm
                ? `لا توجد نتائج مطابقة لـ «${escapeHtml(invoiceSearchTerm)}».`
                : 'لم يتم توزيع أي صنف من هذه الفاتورة بعد.';
            tbody.innerHTML = `
                <tr><td colspan="8" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
                    ${msg}
                </td></tr>`;
            return;
        }

        sorted.forEach(d => {
            const badge = d.is_invoiced
                ? '<span class="badge bg-success-subtle text-success">مفوتر</span>'
                : '<span class="badge bg-warning-subtle text-warning">غير مفوتر</span>';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-semibold small">${escapeHtml(d.item_name)}</td>
                <td class="small text-muted">${escapeHtml(d.type_name || '—')}</td>
                <td>
                    ${escapeHtml(d.customer_name)}
                    ${d.customer_phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(d.customer_phone)}</div>` : ''}
                </td>
                <td class="text-end fw-semibold text-success">${fmt(d.quantity, 3)}</td>
                <td class="text-center small text-muted">${escapeHtml(d.unit_name || '—')}</td>
                <td class="text-end text-muted">${fmt(d.unit_cost)}</td>
                <td class="text-end text-muted">${fmt(d.total_cost)} ر.ي</td>
                <td class="text-center">${badge}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // ============================================================
    // التهيئة
    // ============================================================
    loadStats();
    loadDistributions();
});