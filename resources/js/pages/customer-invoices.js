// resources/js/pages/purchase-distribution/customer-invoices.js

let BootstrapModal = null;
async function ensureBootstrap() {
    if (BootstrapModal) return BootstrapModal;
    if (window.bootstrap?.Modal) { BootstrapModal = window.bootstrap.Modal; return BootstrapModal; }
    try { const m = await import('bootstrap'); BootstrapModal = m.Modal; return BootstrapModal; }
    catch (e) { console.error('[inv] Bootstrap غير متاح:', e); return null; }
}

document.addEventListener('DOMContentLoaded', () => {

    const ROUTES = {
        customers:             '/operation/purchases/distribution/customers',
        customerDistributions: (id) => `/operation/purchases/distribution/customer/${id}/distributions`,
        issueInvoice:          '/operation/purchases/distribution/issue-invoice',
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const state = {
        customer:      null,   // { id, name, phone, code }
        distributions: [],     // [{ id, code, date, details:[], selected:bool }]
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
    const toastWarning = (m) => showToast(m, 'warning');
    const toastInfo    = (m) => showToast(m, 'info');

    async function confirmDialog(msg, opts = {}) {
        if (typeof window.appConfirm === 'function') {
            try { return await window.appConfirm(msg, opts); }
            catch (e) { /* fallback */ }
        }
        return window.confirm(msg);
    }

    // ============================================================
    // Customer Picker
    // ============================================================
    let accountPickerInstance = null;

    async function openCustomerPicker() {
        const modalEl = $('AccountPickerModal');
        if (!modalEl) { toastError('مودال اختيار الحساب غير محمّل.'); return; }

        const Modal = await ensureBootstrap();
        if (Modal) {
            if (!accountPickerInstance) accountPickerInstance = new Modal(modalEl);
            accountPickerInstance.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }

        // تبويب العملاء فقط
        document.querySelectorAll('#AccountPickerTabs .nav-link').forEach(tab => {
            if (tab.dataset.type === 'customer') {
                tab.classList.remove('d-none'); tab.classList.add('active');
            } else {
                tab.classList.add('d-none'); tab.classList.remove('active');
            }
        });
        const balanceCol = $('AccountPickerColBalance');
        if (balanceCol) balanceCol.classList.add('d-none');

        if ($('AccountPickerSearch')) $('AccountPickerSearch').value = '';
        if ($('AccountPickerResults')) {
            $('AccountPickerResults').innerHTML = `
                <tr><td colspan="5" class="text-center text-muted py-4">ابدأ البحث</td></tr>`;
        }
        if ($('AccountPickerTitle')) $('AccountPickerTitle').textContent = 'اختيار عميل';
        if ($('AccountPickerSearch')) $('AccountPickerSearch').focus();

        loadAccounts();
    }

    function closeCustomerPicker() {
        const modalEl = $('AccountPickerModal');
        if (accountPickerInstance) accountPickerInstance.hide();
        else if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    }

    $('customerPickerTrigger')?.addEventListener('click', openCustomerPicker);
    document.querySelectorAll('#AccountPickerModal [data-bs-dismiss="modal"]').forEach(btn =>
        btn.addEventListener('click', closeCustomerPicker));

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
            results.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">اكتب حرفين على الأقل للبحث.</td></tr>`;
            return;
        }

        results.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary"></div> <span class="ms-2">جارٍ التحميل...</span></td></tr>`;

        try {
            const res = await api(`${ROUTES.customers}?q=${encodeURIComponent(term)}`);
            const rows = res.data || [];
            if (!rows.length) {
                results.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">لا نتائج مطابقة.</td></tr>`;
                return;
            }

            results.innerHTML = '';
            rows.forEach(c => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                tr.innerHTML = `
                    <td class="text-center text-muted">${escapeHtml(c.code ?? c.id)}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle text-success"></i>
                            <span class="fw-semibold">${escapeHtml(c.name)}</span>
                        </div>
                    </td>
                    <td class="d-none"></td>
                    <td>${escapeHtml(c.phone || '—')}</td>
                `;
                tr.addEventListener('click', () => {
                    selectCustomer(c);
                    closeCustomerPicker();
                });
                results.appendChild(tr);
            });
        } catch (e) {
            results.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">فشل: ${escapeHtml(e.message)}</td></tr>`;
        }
    }

    // ============================================================
    // Selection & Loading
    // ============================================================
    function selectCustomer(c) {
        state.customer = {
            id:    c.id,
            name:  c.name,
            phone: c.phone || '',
            code:  c.code || '',
        };
        updateCustomerLabel();
        loadCustomerDistributions(c.id);
    }

    function updateCustomerLabel() {
        if (!state.customer) {
            if ($('customerPickerLabel')) $('customerPickerLabel').textContent = 'اضغط لاختيار عميل...';
            if ($('btnClearCustomer'))    $('btnClearCustomer').disabled = true;
            if ($('contentArea'))         $('contentArea').classList.add('d-none');
            return;
        }
        if ($('customerPickerLabel')) {
            $('customerPickerLabel').innerHTML = `
                <i class="bi bi-person-circle text-success"></i>
                <span class="fw-bold text-dark">${escapeHtml(state.customer.name)}</span>
                ${state.customer.code ? `<span class="text-muted small">(${escapeHtml(state.customer.code)})</span>` : ''}
                ${state.customer.phone ? `<span class="text-muted small">· ${escapeHtml(state.customer.phone)}</span>` : ''}
            `;
        }
        if ($('btnClearCustomer')) $('btnClearCustomer').disabled = false;
        if ($('contentArea'))      $('contentArea').classList.remove('d-none');
    }

    async function loadCustomerDistributions(customerId) {
        const container = $('distributionsList');
        if (container) {
            container.innerHTML = `<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary"></div> جارٍ التحميل...</div>`;
        }

        try {
            const res = await api(ROUTES.customerDistributions(customerId));

            // ضبط بيانات العميل إن لم تكن موجودة (عند التحميل المباشر من URL)
            if (!state.customer && res.customer) {
                state.customer = {
                    id:    res.customer.id,
                    name:  res.customer.name,
                    phone: res.customer.phone || '',
                    code:  '',
                };
                updateCustomerLabel();
            }

            state.distributions = (res.data || []).map(d => ({
                id:       d.id,
                code:     d.code,
                date:     d.distribution_date,
                details:  d.details || [],
                selected: true,
            }));

            renderDistributions();
            updateSummary();
        } catch (e) {
            if (container) {
                container.innerHTML = `<div class="text-center text-danger py-4">فشل التحميل: ${escapeHtml(e.message)}</div>`;
            }
            toastError('فشل تحميل التوزيعات: ' + e.message);
        }
    }

    // ============================================================
    // Render
    // ============================================================
    function renderDistributions() {
        const container = $('distributionsList');
        if (!container) return;

        if (!state.distributions.length) {
            container.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success opacity-75"></i>
                    <div>لا توجد توزيعات غير مفوترة لهذا العميل.</div>
                </div>`;
            return;
        }

        container.innerHTML = '';

        state.distributions.forEach(dist => {
            const card = document.createElement('div');
            card.className = 'border-bottom p-3';

            let totalCost = 0, totalSale = 0;
            dist.details.forEach(d => {
                totalCost += num(d.quantity) * num(d.unit_cost);
                totalSale += num(d.quantity) * num(d.sale_price);
            });

            const detailsHtml = dist.details.map(d => `
                <tr>
                    <td class="fw-semibold small">${escapeHtml(d.item_name)}</td>
                    <td class="text-muted small">${escapeHtml(d.invoice_number || '—')}</td>
                    <td class="text-end fw-semibold text-success">${fmt(d.quantity, 3)}</td>
                    <td class="text-center small text-muted">${escapeHtml(d.unit_name || '—')}</td>
                    <td class="text-end text-muted">${fmt(d.unit_cost)}</td>
                    <td class="text-end text-muted">${fmt(d.sale_price)}</td>
                    <td class="text-end">${fmt(num(d.quantity) * num(d.unit_cost))} ر.ي</td>
                    <td class="text-end">${fmt(num(d.quantity) * num(d.sale_price))} ر.ي</td>
                </tr>
            `).join('');

            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input dist-checkbox" 
                               id="dist-check-${dist.id}" ${dist.selected ? 'checked' : ''}>
                        <label class="form-check-label fw-bold" for="dist-check-${dist.id}">
                            ${escapeHtml(dist.code)}
                            <span class="text-muted small fw-normal">· ${escapeHtml(dist.date)}</span>
                            <span class="badge bg-secondary-subtle text-secondary ms-1">${dist.details.length} صف</span>
                        </label>
                    </div>
                    <div class="text-end small">
                        <span class="text-muted">تكلفة:</span>
                        <span class="text-danger fw-bold">${fmt(totalCost)} ر.ي</span>
                        <span class="text-muted mx-1">|</span>
                        <span class="text-muted">بيع:</span>
                        <span class="text-success fw-bold">${fmt(totalSale)} ر.ي</span>
                    </div>
                </div>
                <div class="table-responsive border rounded-3" style="max-height:220px; overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="small text-muted">
                                <th>الصنف</th>
                                <th>الفاتورة</th>
                                <th class="text-end">الكمية</th>
                                <th class="text-center">الوحدة</th>
                                <th class="text-end">تكلفة الوحدة</th>
                                <th class="text-end">سعر البيع</th>
                                <th class="text-end">إجمالي التكلفة</th>
                                <th class="text-end">إجمالي البيع</th>
                            </tr>
                        </thead>
                        <tbody>${detailsHtml}</tbody>
                    </table>
                </div>
            `;

            card.querySelector('.dist-checkbox').addEventListener('change', (e) => {
                dist.selected = e.target.checked;
                updateSummary();
            });

            container.appendChild(card);
        });
    }

    // ============================================================
    // Summary
    // ============================================================
    function updateSummary() {
        const selected = state.distributions.filter(d => d.selected);
        let totalQty = 0, totalCost = 0, totalSale = 0;

        selected.forEach(dist => {
            dist.details.forEach(d => {
                totalQty  += num(d.quantity);
                totalCost += num(d.quantity) * num(d.unit_cost);
                totalSale += num(d.quantity) * num(d.sale_price);
            });
        });

        if ($('sumDistributions')) $('sumDistributions').textContent = selected.length;
        if ($('sumQty'))           $('sumQty').textContent           = fmt(totalQty, 3);
        if ($('sumCost'))          $('sumCost').textContent          = fmt(totalCost);
        if ($('sumSale'))          $('sumSale').textContent          = fmt(totalSale);

        if ($('btnIssue')) $('btnIssue').disabled = selected.length === 0;
    }

    // ============================================================
    // Actions
    // ============================================================
    $('btnSelectAll')?.addEventListener('click', () => {
        state.distributions.forEach(d => d.selected = true);
        renderDistributions();
        updateSummary();
    });

    $('btnDeselectAll')?.addEventListener('click', () => {
        state.distributions.forEach(d => d.selected = false);
        renderDistributions();
        updateSummary();
    });

    function resetScreen() {
        if (state.distributions.length && !confirm('سيتم إلغاء التحديد والعودة للبداية. متابعة؟')) return;
        state.customer = null;
        state.distributions = [];
        updateCustomerLabel();
        if ($('distributionsList')) $('distributionsList').innerHTML = '';
        if ($('alertArea'))         $('alertArea').innerHTML = '';
        updateSummary();
    }

    $('btnClearCustomer')?.addEventListener('click', resetScreen);
    $('btnCancel')?.addEventListener('click', resetScreen);

    $('btnIssue')?.addEventListener('click', issueInvoice);

    async function issueInvoice() {
        if (!state.customer) { toastError('اختر عميلًا أولًا.'); return; }
        const selected = state.distributions.filter(d => d.selected);
        if (!selected.length) { toastError('حدد توزيعًا واحدًا على الأقل.'); return; }

        const totalDetails = selected.reduce((s, d) => s + d.details.length, 0);
        const ok = await confirmDialog(
            `سيتم إصدار فاتورة للعميل «${state.customer.name}» تشمل ${selected.length} توزيع و ${totalDetails} صف. متابعة؟`,
            { title: 'إصدار فاتورة', okText: 'إصدار', cancelText: 'إلغاء' }
        );
        if (!ok) return;

        if ($('btnIssue')) $('btnIssue').disabled = true;

        try {
            const res = await api(ROUTES.issueInvoice, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_id:      state.customer.id,
                    distribution_ids: selected.map(d => d.id),
                }),
            });

            toastSuccess(res.message || 'تم تجهيز البيانات للفاتورة.');

            // إعادة تحميل التوزيعات (المفوترة ستختفي تلقائيًا)
            await loadCustomerDistributions(state.customer.id);
        } catch (e) {
            toastError('فشل إصدار الفاتورة: ' + e.message);
        } finally {
            if ($('btnIssue')) $('btnIssue').disabled = state.distributions.filter(d => d.selected).length === 0;
        }
    }

    // ============================================================
    // Init
    // ============================================================
    const urlParams = new URLSearchParams(window.location.search);
    const preCustomerId = urlParams.get('customer_id');
    if (preCustomerId) {
        loadCustomerDistributions(preCustomerId);
    }

    updateCustomerLabel();
    updateSummary();
});