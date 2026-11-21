// resources/js/pages/history.js

let BootstrapModal = null;

/**
 * تحميل كلاس Modal من Bootstrap بأمان سواء كان متاحاً عالمياً أو عبر الاستيراد.
 */
async function ensureBootstrap() {
    if (BootstrapModal) return BootstrapModal;
    if (window.bootstrap?.Modal) {
        BootstrapModal = window.bootstrap.Modal;
        return BootstrapModal;
    }
    try {
        const m = await import('bootstrap');
        BootstrapModal = m.Modal;
        return BootstrapModal;
    } catch (e) {
        console.error('[history] Bootstrap غير متاح:', e);
        return null;
    }
}

document.addEventListener('DOMContentLoaded', () => {

    // مسارات الواجهة الخلفية الخاصة بسجل التوزيعات
    const ROUTES = {
        stats:            '/operation/purchases/distribution/stats',
        list:             '/operation/purchases/distribution/list/all',
        show:             (id) => `/operation/purchases/distribution/${id}`,
        deleteDist:       (id) => `/operation/purchases/distribution/${id}`,
        distCustomers:    (id) => `/operation/purchases/distribution/distribution/${id}/customers`,
        distCustDetails:  (id, cid) => `/operation/purchases/distribution/distribution/${id}/customer/${cid}/details`,
        updateCustInDist: (id, cid) => `/operation/purchases/distribution/distribution/${id}/customer/${cid}/update`,
        searchInvoices:   '/operation/purchases/distribution/invoices-search',
        invoiceSummary:   '/operation/purchases/distribution/invoice-summary',
        distributionPage: '/operation/purchases/distribution',
    };

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // دوال مساعدة للوصول للعناصر وتنسيق الأرقام والنصوص
    const $          = (id) => document.getElementById(id);
    const num        = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const fmt        = (v, d = 2) => num(v).toFixed(d);
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    const debounce   = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

    /**
     * تنفيذ طلبات الـ API وإرجاع البيانات أو رمي خطأ واضح.
     */
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
        try {
            data = text ? JSON.parse(text) : {};
        } catch {
            data = { message: text || `HTTP ${res.status}` };
        }
        if (!res.ok) {
            throw new Error(data.message || `HTTP ${res.status}`);
        }
        return data;
    }

    /**
     * عرض إشعارات النظام الموحدة.
     */
    function showToast(msg, type = 'info') {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(msg, type);
            return;
        }
        console.log(`[history:${type}] ${msg}`);
    }
    const toastSuccess = (m) => showToast(m, 'success');
    const toastError   = (m) => showToast(m, 'danger');
    const toastWarning = (m) => showToast(m, 'warning');
    const toastInfo    = (m) => showToast(m, 'info');

    /**
     * استدعاء المودال الموحّد للمشروع (window.appConfirm).
     */
    async function confirmAction(msg, opts = {}) {
        if (typeof window.appConfirm === 'function') {
            return await window.appConfirm(msg, opts);
        }
        return window.confirm(msg);
    }

    /**
     * إظهار مودال Bootstrap حسب المعرّف.
     */
    async function showModalById(modalId, currentInstance) {
        const modalEl = $(modalId);
        if (!modalEl) return currentInstance;
        const Modal = await ensureBootstrap();
        if (Modal) {
            const inst = currentInstance || new Modal(modalEl);
            inst.show();
            return inst;
        }
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
        document.body.classList.add('modal-open');
        return currentInstance;
    }

    /**
     * إخفاء مودال Bootstrap حسب المعرّف.
     */
    function hideModalById(modalId, instance) {
        const modalEl = $(modalId);
        if (instance) {
            instance.hide();
        } else if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    }

    /**
     * يخفّت أو يُعيد لون محتوى مودال تفاصيل التوزيع (#distDetailsModal .modal-content) نفسه
     * عند ظهور مودال التعديل، دون التأثير على كامل الشاشة.
     * المتغير القابل للتعديل: dimOpacity (مثلاً 0.45 لخفوت واضح على المودال فقط).
     */
    function setParentModalDimmed(isDimmed, dimOpacity = '0.45') {
        const parentModalEl = $('distDetailsModal');
        if (!parentModalEl) return;

        const modalContent = parentModalEl.querySelector('.modal-content');
        const targetEl = modalContent || parentModalEl;

        parentModalEl.classList.toggle('modal-dimmed-parent', Boolean(isDimmed));
        targetEl.style.opacity = isDimmed ? String(dimOpacity) : '';
        targetEl.style.transition = 'opacity 0.2s ease';
    }

    /**
     * ربط أحداث مودال تعديل العميل لإخفات مودال التفاصيل عند الظهور وإعادته عند الإغلاق.
     */
    function bindEditCustomerModalDimming() {
        const editModalEl = $('editDistCustomerModal');
        if (!editModalEl || editModalEl.dataset.dimBound === '1') return;

        editModalEl.dataset.dimBound = '1';
        editModalEl.addEventListener('show.bs.modal', () => setParentModalDimmed(true));
        editModalEl.addEventListener('shown.bs.modal', () => setParentModalDimmed(true));
        editModalEl.addEventListener('hide.bs.modal', () => setParentModalDimmed(false));
        editModalEl.addEventListener('hidden.bs.modal', () => setParentModalDimmed(false));
    }

    // ============================================================
    // 1. الإحصائيات
    // ============================================================
    const loaded = { stats: false, distributions: false, invoiceTab: false };

    async function loadStats(force = false) {
        if (loaded.stats && !force) return;
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
    // 2. التبويبات الداخلية للسجل
    // ============================================================
    document.querySelectorAll('#historyTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#historyTabs .nav-link').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const tab = btn.dataset.tab;
            ['distributions', 'invoices'].forEach(t => {
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
    // 3. تبويب التوزيعات + الحذف عبر المودال الموحّد
    // ============================================================
    let distModalInstance = null;
    let isDeletingDist = false;

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
                <td class="text-center" onclick="event.stopPropagation()">
                    <div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-view-dist" title="عرض">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-dist" title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tr.addEventListener('click', () => openDistDetails(r.id));
            tr.querySelector('.btn-view-dist')?.addEventListener('click', () => openDistDetails(r.id));
            tr.querySelector('.btn-delete-dist')?.addEventListener('click', () => deleteDistribution(r));
            tbody.appendChild(tr);
        });
    }

    function distributionHasInvoicedRows(distRow) {
        if (!distRow) return false;
        if (typeof distRow.has_invoiced === 'boolean') return distRow.has_invoiced;
        if (typeof distRow.invoiced_count === 'number') return distRow.invoiced_count > 0;
        if (typeof distRow.invoiced_details_count === 'number') return distRow.invoiced_details_count > 0;
        return distRow.status === 'invoiced';
    }

    async function executeDeleteDistribution(distRow, forceDelete = false) {
        if (isDeletingDist) return;
        isDeletingDist = true;
        try {
            const url = forceDelete
                ? `${ROUTES.deleteDist(distRow.id)}?force=1`
                : ROUTES.deleteDist(distRow.id);

            const res = await api(url, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ force: forceDelete ? 1 : 0 }),
            });

            toastSuccess(res.message || 'تم حذف التوزيع بنجاح.');
            await Promise.all([loadDistributions(), loadStats(true)]);
        } catch (e) {
            toastError('تعذر حذف التوزيع: ' + e.message);
        } finally {
            isDeletingDist = false;
        }
    }

    async function deleteDistribution(distRow) {
        if (isDeletingDist) return;
        const hasInvoiced = distributionHasInvoicedRows(distRow);

        const message = hasInvoiced
            ? `تنبيه: التوزيع «${distRow.code}» يحتوي على صفوف مفوترة. هل أنت متأكد من المتابعة وحذف التوزيع؟`
            : `هل أنت متأكد من حذف التوزيع «${distRow.code}»؟`;

        const ok = await confirmAction(message, {
            title: hasInvoiced ? 'تحذير: توزيع يحتوي على صفوف مفوترة' : 'حذف توزيع',
            okText: hasInvoiced ? 'نعم، احذف التوزيع' : 'حذف نهائي',
            danger: true,
        });

        if (!ok) return;

        await executeDeleteDistribution(distRow, hasInvoiced);
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
    // 4. مودال تفاصيل التوزيع + استعراض وتعديل عميل خاص حسب التوزيع
    // ============================================================
    let editCustModalInstance = null;
    let isSavingCustomer = false;
    let editCustState = {
        distributionId: null,
        distributionCode: '',
        customerId: null,
        customerName: '',
        rows: [],
        deletedIds: new Set(),
    };

    async function openDistDetails(id) {
        try {
            const res = await api(ROUTES.show(id));
            if (!res.success) {
                toastError('تعذر التحميل.');
                return;
            }

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

            // ترتيب الأعمدة: الفاتورة، الصنف، النوع، الرمز، العميل، الكمية، تكلفة الوحدة، سعر البيع، إجمالي، الفوترة
            (res.details || []).forEach(d => {
                const invBadge = d.is_invoiced
                    ? '<span class="badge bg-success-subtle text-success">مفوتر</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary">غير مفوتر</span>';

                const rowTotal = num(d.total_sale) || (num(d.quantity) * num(d.sale_price));

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-muted small">${escapeHtml(d.invoice_number || '—')}</td>
                    <td class="fw-semibold small">${escapeHtml(d.item_name || '—')}</td>
                    <td class="text-muted small">${escapeHtml(d.type_name || '—')}</td>
                    <td class="text-muted small">${escapeHtml(d.code || '—')}</td>
                    <td>
                        <button type="button"
                                class="btn btn-link p-0 text-decoration-none fw-semibold text-start btn-edit-dist-customer"
                                data-customer-id="${d.customer_id}"
                                data-customer-name="${escapeHtml(d.customer_name || '—')}"
                                title="استعراض وتعديل بيانات هذا العميل في التوزيع">
                            ${escapeHtml(d.customer_name || '—')}
                            <i class="bi bi-pencil-square text-primary small ms-1"></i>
                        </button>
                        ${d.customer_phone ? `<div class="text-muted" style="font-size:0.7rem;">${escapeHtml(d.customer_phone)}</div>` : ''}
                    </td>
                    <td class="text-end fw-semibold text-success">${fmt(d.quantity, 3)}</td>
                    <td class="text-end text-muted">${fmt(d.unit_cost)}</td>
                    <td class="text-end text-muted">${fmt(d.sale_price)}</td>
                    <td class="text-end fw-semibold">${fmt(rowTotal)} ر.ي</td>
                    <td class="text-center">${invBadge}</td>
                `;
                tr.querySelector('.btn-edit-dist-customer')?.addEventListener('click', () => {
                    openEditDistCustomer(id, res.header.code, d.customer_id, d.customer_name || '');
                });
                tbody.appendChild(tr);
            });

            const editBtn = $('ddEditBtn');
            if (editBtn) {
                if (res.header.is_draft) {
                    editBtn.classList.remove('d-none');
                    editBtn.href = `${ROUTES.distributionPage}?id=${res.header.id}`;
                } else {
                    editBtn.classList.add('d-none');
                }
            }

            loadDistLogs(id);

            setParentModalDimmed(false);
            distModalInstance = await showModalById('distDetailsModal', distModalInstance);
        } catch (e) {
            toastError('فشل التحميل: ' + e.message);
        }
    }

    async function loadDistLogs(id) {
        const body = $('ddLogsBody');
        if (!body) return;
        body.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div> <span class="ms-1 small">جارٍ تحميل السجل...</span>';
        try {
            const res = await api(`${ROUTES.distributionPage}/${id}/logs`);
            const logs = res?.data || [];
            if (!logs.length) {
                body.innerHTML = '<span class="text-muted small">لا توجد حركات مسجلة لهذا التوزيع حتى الآن.</span>';
                return;
            }
            body.innerHTML = logs.map(l => `
                <div class="d-flex justify-content-between align-items-center border-bottom py-1 text-start">
                    <div>
                        <span class="badge bg-secondary-subtle text-secondary me-1">${escapeHtml(l.action)}</span>
                        <span class="small">${escapeHtml(l.description || '—')}</span>
                        <span class="text-muted small">(${escapeHtml(l.user)})</span>
                    </div>
                    <div class="text-muted small" style="font-size:0.75rem;">${escapeHtml(l.created_at)}</div>
                </div>
            `).join('');
        } catch (e) {
            body.innerHTML = `<span class="text-danger small">تعذر تحميل السجل: ${escapeHtml(e.message)}</span>`;
        }
    }

    async function openEditDistCustomer(distributionId, distributionCode, customerId, customerName) {
        try {
            const res = await api(ROUTES.distCustDetails(distributionId, customerId));
            const rows = Array.isArray(res.data) ? res.data : [];

            editCustState = {
                distributionId,
                distributionCode,
                customerId,
                customerName,
                rows: rows.map(r => {
                    const maxAllowed = (r.max_allowed_row !== undefined && r.max_allowed_row !== null)
                        ? Math.max(0, num(r.max_allowed_row))
                        : (num(r.quantity) + Math.max(0, num(r.remaining_quantity)));

                    return {
                        ...r,
                        _qty: num(r.quantity),
                        _price: num(r.sale_price),
                        _maxAllowed: maxAllowed,
                    };
                }),
                deletedIds: new Set(),
            };

            if ($('edcTitle'))    $('edcTitle').textContent    = `بيانات العميل: ${customerName}`;
            if ($('edcSubtitle')) $('edcSubtitle').textContent = `التوزيع: ${distributionCode} · عدد الصفوف: ${editCustState.rows.length}`;

            renderEditCustomerRows();
            bindEditCustomerModalDimming();
            editCustModalInstance = await showModalById('editDistCustomerModal', editCustModalInstance);
            setParentModalDimmed(true);
        } catch (e) {
            toastError('فشل تحميل بيانات العميل في التوزيع: ' + e.message);
        }
    }

    /**
     * تنظيف المدخلات العشرية ليقبل الأرقام ونقطة عشرية واحدة فقط.
     */
    function sanitizeDecimalInput(rawValue) {
        let clean = String(rawValue ?? '').replace(/[^0-9.]/g, '');
        const firstDot = clean.indexOf('.');
        if (firstDot !== -1) {
            clean = clean.slice(0, firstDot + 1) + clean.slice(firstDot + 1).replace(/\./g, '');
        }
        return clean;
    }

      
    function renderEditCustomerRows() {
        const tbody = $('edcTbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        // استبعاد الصفوف التي تم تعليمها للحذف مؤقتاً قبل الحفظ
        const activeRows = editCustState.rows.filter(r => !editCustState.deletedIds.has(r.id));

        if (!activeRows.length) {
            tbody.innerHTML = `
                <tr><td colspan="9" class="text-center text-muted py-4">
                    لا توجد صفوف متبقية لهذا العميل في هذا التوزيع (سيتم حذف الصفوف المحذوفة عند الحفظ).
                </td></tr>`;
            return;
        }

        // ترتيب الأعمدة: الفاتورة، الرمز، الصنف والنوع، الكمية، الوحدة، سعر البيع، إجمالي البيع، الحالة، حذف
        activeRows.forEach(r => {
            const rowTotal = num(r._qty) * num(r._price);
            const isRowInvoiced = !!r.is_invoiced;
            const statusBadge = isRowInvoiced
                ? '<span class="badge bg-success-subtle text-success">مفوتر</span>'
                : '<span class="badge bg-warning-subtle text-warning">غير مفوتر</span>';

            const itemAndType = `${escapeHtml(r.item_name || '—')}${r.type_name && r.type_name !== '—' ? ` <span class="text-muted small">(${escapeHtml(r.type_name)})</span>` : ''}`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-muted small">${escapeHtml(r.invoice_number || '—')}</td>
                <td class="text-muted small">${escapeHtml(r.code || '—')}</td>
                <td class="fw-semibold small">${itemAndType}</td>
                <td>
                    <input type="text"
                           dir="ltr"
                           inputmode="decimal"
                           autocomplete="off"
                           class="form-control form-control-sm text-center edc-qty ${isRowInvoiced ? 'bg-body-secondary' : ''}"
                           data-row-id="${r.id}"
                           value="${escapeHtml(String(r._qty))}"
                           ${isRowInvoiced ? 'readonly disabled' : ''}
                           title="${isRowInvoiced ? 'بند مفوتر لا يمكن تعديل كميته' : `الحد الأقصى المتاح: ${fmt(r._maxAllowed, 3)}`}">
                </td>
                <td class="text-center small text-muted">${escapeHtml(r.unit_name || '—')}</td>
                <td>
                    <input type="text"
                           dir="ltr"
                           inputmode="decimal"
                           autocomplete="off"
                           class="form-control form-control-sm text-center edc-price ${isRowInvoiced ? 'bg-body-secondary' : ''}"
                           data-row-id="${r.id}"
                           value="${escapeHtml(String(r._price))}"
                           ${isRowInvoiced ? 'readonly disabled' : ''}
                           title="${isRowInvoiced ? 'بند مفوتر لا يمكن تعديل سعره' : 'سعر البيع للوحدة'}">
                </td>
                <td class="text-end fw-semibold text-success edc-row-total">${fmt(rowTotal)} ر.ي</td>
                <td class="text-center">${statusBadge}</td>
                <td class="text-center">
                    ${isRowInvoiced ? `
                        <button type="button" class="btn btn-sm btn-outline-secondary disabled" disabled title="لا يمكن حذف بند مفوتر">
                            <i class="bi bi-lock"></i>
                        </button>
                    ` : `
                        <button type="button" class="btn btn-sm btn-outline-danger edc-del-row"
                                data-row-id="${r.id}" title="حذف الصف">
                            <i class="bi bi-trash"></i>
                        </button>
                    `}
                </td>
            `;

            const qtyInp   = tr.querySelector('.edc-qty');
            const priceInp = tr.querySelector('.edc-price');
            const totalEl  = tr.querySelector('.edc-row-total');
            const delBtn   = tr.querySelector('.edc-del-row');

            // دالة داخلية لتحديث نص إجمالي البيع للصف
            const refreshRowTotal = () => {
                if (totalEl) {
                    totalEl.textContent = `${fmt(num(r._qty) * num(r._price))} ر.ي`;
                }
            };

            // تنظيف قيمة الكمية والتحقق من عدم تجاوز الحد الأقصى المتاح في الدفعة
            qtyInp?.addEventListener('input', () => {
                let clean = sanitizeDecimalInput(qtyInp.value);
                let q = num(clean);

                if (q > r._maxAllowed) {
                    q = r._maxAllowed;
                    clean = String(q);
                    toastWarning(`الحد الأقصى المتاح في الدفعة هو ${fmt(r._maxAllowed, 3)}`);
                }

                if (qtyInp.value !== clean) {
                    qtyInp.value = clean;
                }

                r._qty = q;
                refreshRowTotal();
            });

            // تنظيف قيمة سعر البيع وتحديث إجمالي الصف فوراً
            priceInp?.addEventListener('input', () => {
                const clean = sanitizeDecimalInput(priceInp.value);
                if (priceInp.value !== clean) {
                    priceInp.value = clean;
                }

                r._price = num(clean);
                refreshRowTotal();
            });

            // تعليم الصف للحذف وإعادة رسم الجدول
            delBtn?.addEventListener('click', () => {
                editCustState.deletedIds.add(r.id);
                renderEditCustomerRows();
            });

            tbody.appendChild(tr);
        });
    }


    $('edcSaveBtn')?.addEventListener('click', async () => {
        if (isSavingCustomer) return;

        // استبعاد البنود المفوترة من التعديل أو الحذف
        const activeRows = editCustState.rows.filter(r => !editCustState.deletedIds.has(r.id) && !r.is_invoiced);
        const safeDeletedIds = Array.from(editCustState.deletedIds).filter(id => {
            const row = editCustState.rows.find(r => r.id == id);
            return !row?.is_invoiced;
        });

        for (const r of activeRows) {
            if (num(r._qty) <= 0) {
                toastError(`الكمية مطلوبة وأكبر من صفر للصنف «${r.item_name}».`);
                return;
            }
            if (num(r._qty) > r._maxAllowed + 0.000001) {
                toastError(`الكمية للصنف «${r.item_name}» تتجاوز المتاح في الدفعة (${fmt(r._maxAllowed, 3)}).`);
                return;
            }
            if (num(r._price) <= 0) {
                toastError(`سعر البيع مطلوب وأكبر من صفر للصنف «${r.item_name}».`);
                return;
            }
        }

        const payload = {
            items: activeRows.map(r => ({
                id: r.id,
                quantity: num(r._qty),
                sale_price: num(r._price),
            })),
            deleted_ids: safeDeletedIds,
        };

        isSavingCustomer = true;
        const btn = $('edcSaveBtn');
        if (btn) btn.disabled = true;

        try {
            const res = await api(
                ROUTES.updateCustInDist(editCustState.distributionId, editCustState.customerId),
                {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                }
            );

            toastSuccess(res.message || 'تم حفظ التعديلات بنجاح.');
            setParentModalDimmed(false);
            hideModalById('editDistCustomerModal', editCustModalInstance);

            await Promise.all([
                openDistDetails(editCustState.distributionId),
                loadDistributions(),
                loadStats(true),
            ]);
        } catch (e) {
            toastError('فشل حفظ التعديلات: ' + e.message);
        } finally {
            isSavingCustomer = false;
            if (btn) btn.disabled = false;
        }
    });

    // ============================================================
    // 5. تبويب الفواتير
    // ============================================================
    let invoicePickerInstance = null;
    let selectedInvoice = null;
    let invoiceData = [];
    let invoiceSearchTerm  = '';
    let invoiceSearchScope = 'all';

    $('btnPickHistoryInvoice')?.addEventListener('click', openInvoicePicker);

    async function openInvoicePicker() {
        invoicePickerInstance = await showModalById('historyInvoicePickerModal', invoicePickerInstance);
        if ($('hpFilterDate') && !$('hpFilterDate').value) {
            $('hpFilterDate').value = new Date().toISOString().slice(0, 10);
        }
        if ($('hpFilterSupplier')) $('hpFilterSupplier').value = '';
        loadInvoiceList();
    }

    function closeInvoicePicker() {
        hideModalById('historyInvoicePickerModal', invoicePickerInstance);
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

        if ($('invoiceSearchCount')) {
            $('invoiceSearchCount').textContent = invoiceSearchTerm
                ? `${filtered.length} من ${invoiceData.length}`
                : '';
        }

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
    // 6. التهيئة الأولية
    // ============================================================
    loadStats();
    loadDistributions();
});
