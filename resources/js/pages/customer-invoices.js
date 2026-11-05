// resources/js/pages/customer-invoices.js
import '../receipt/accountPicker.js';

let BootstrapModal = null;
async function ensureBootstrap() {
    if (BootstrapModal) return BootstrapModal;
    if (window.bootstrap?.Modal) { BootstrapModal = window.bootstrap.Modal; return BootstrapModal; }
    try { const m = await import('bootstrap'); BootstrapModal = m.Modal; return BootstrapModal; }
    catch (e) { console.error('[inv] Bootstrap غير متاح:', e); return null; }
}

document.addEventListener('DOMContentLoaded', () => {

    const ROUTES = {
        distributions:          '/operation/purchases/distribution/distributions-for-invoice',
        distCustomers:          (id) => `/operation/purchases/distribution/distribution/${id}/customers`,
        distCustomerDetails:    (id, cid) => `/operation/purchases/distribution/distribution/${id}/customer/${cid}/details`,
        issueInvoice:           '/operation/purchases/distribution/issue-invoice',
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const state = {
        distribution:     null,
        customer:         null,
        details:          [],
        paymentMethod:    2,   // نقدي افتراضيًا
        paymentAccountId: null,
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

        if (!res.ok) {
            let msg = data.message || `HTTP ${res.status}`;
            if (data.errors && typeof data.errors === 'object') {
                const details = Object.entries(data.errors)
                    .map(([field, msgs]) => `• ${field}: ${Array.isArray(msgs) ? msgs.join(' / ') : msgs}`)
                    .join('\n');
                msg += '\n\n' + details;
            }
            throw new Error(msg);
        }
        return data;
    }

    function showToast(msg, type = 'info') {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(msg, type);
            return;
        }
        console.log(`[issue:${type}] ${msg}`);
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
    // 1. اختيار التوزيع
    // ============================================================
    let distPickerInstance = null;
    let distListData = [];
    let distSearchTerm = '';

    $('btnPickDistribution')?.addEventListener('click', openDistPicker);

    async function openDistPicker() {
        const modalEl = $('distributionPickerModal');
        if (!modalEl) { toastError('مودال التوزيعات غير محمّل.'); return; }

        const Modal = await ensureBootstrap();
        if (Modal) {
            if (!distPickerInstance) distPickerInstance = new Modal(modalEl);
            distPickerInstance.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }

        if ($('distSearchInput')) $('distSearchInput').value = '';
        distSearchTerm = '';

        loadDistributions();
    }

    function closeDistPicker() {
        const modalEl = $('distributionPickerModal');
        if (distPickerInstance) distPickerInstance.hide();
        else if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    }

    document.querySelectorAll('#distributionPickerModal [data-bs-dismiss="modal"]').forEach(btn =>
        btn.addEventListener('click', closeDistPicker));

    $('distSearchInput')?.addEventListener('input', debounce((e) => {
        distSearchTerm = e.target.value.trim().toLowerCase();
        renderDistList();
    }, 300));

    $('distSearchClearBtn')?.addEventListener('click', () => {
        if ($('distSearchInput')) $('distSearchInput').value = '';
        distSearchTerm = '';
        renderDistList();
    });

    async function loadDistributions() {
        $('distLoading')?.classList.remove('d-none');
        $('distEmpty')?.classList.add('d-none');
        $('distError')?.classList.add('d-none');

        try {
            const res = await api(ROUTES.distributions);
            distListData = res.data || [];
            renderDistList();
        } catch (e) {
            if ($('distError')) {
                $('distError').textContent = 'فشل تحميل التوزيعات: ' + e.message;
                $('distError').classList.remove('d-none');
            }
        } finally {
            $('distLoading')?.classList.add('d-none');
        }
    }

    function renderDistList() {
        const tbody = $('distTbodyForInvoice');
        if (!tbody) {
            console.warn('[inv] لم أجد #distTbodyForInvoice');
            return;
        }

        tbody.innerHTML = '';

        let rows = distListData;
        if (distSearchTerm) {
            rows = distListData.filter(d =>
                (d.code || '').toLowerCase().includes(distSearchTerm) ||
                (d.date || '').toLowerCase().includes(distSearchTerm)
            );
        }

        if ($('distEmpty')) {
            if (!rows.length) {
                $('distEmpty').textContent = distSearchTerm
                    ? 'لا توجد نتائج مطابقة.'
                    : 'لا توجد توزيعات غير مفوترة.';
                $('distEmpty').classList.remove('d-none');
            } else {
                $('distEmpty').classList.add('d-none');
            }
        }

        rows.forEach((d, i) => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            // ✅ 6 خلايا تطابق رأس الجدول
            tr.innerHTML = `
                <td class="text-muted">${i + 1}</td>
                <td class="fw-semibold text-primary">${escapeHtml(d.code)}</td>
                <td class="text-muted small">${escapeHtml(d.date || '—')}</td>
                <td class="text-center">${d.details_count ?? 0}</td>
                <td class="text-center">${d.customers_count ?? 0}</td>
                <td class="text-end text-success fw-semibold">${fmt(d.total_sale)}</td>
            `;
            tr.addEventListener('click', () => {
                closeDistPicker();
                selectDistribution(d);
            });
            tbody.appendChild(tr);
        });
    }

    function selectDistribution(d) {
        state.distribution = { id: d.id, code: d.code, date: d.date };
        state.customer = null;
        state.details  = [];

        resetCustomerSection();
        if ($('btnPickCustomer')) $('btnPickCustomer').disabled = false;

        if ($('selectedDistributionLabel')) {
            $('selectedDistributionLabel').innerHTML = `
                <span class="text-primary">${escapeHtml(d.code)}</span>
                <span class="text-muted small"> · ${escapeHtml(d.date)}</span>
            `;
        }
        if ($('selectedCustomerLabel')) {
            $('selectedCustomerLabel').textContent = 'اختر عميلًا من هذا التوزيع';
        }

        toastSuccess(`تم اختيار التوزيع ${d.code}.`);
    }

    // ============================================================
    // 2. اختيار العميل
    // ============================================================
    let custPickerInstance = null;
    let custListData = [];
    let custSearchTerm = '';

    $('btnPickCustomer')?.addEventListener('click', openCustPicker);

    async function openCustPicker() {
        if (!state.distribution) {
            toastError('اختر توزيعًا أولًا.');
            return;
        }
        const modalEl = $('customerPickerModal');
        if (!modalEl) { toastError('مودال العملاء غير محمّل.'); return; }

        const Modal = await ensureBootstrap();
        if (Modal) {
            if (!custPickerInstance) custPickerInstance = new Modal(modalEl);
            custPickerInstance.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }

        if ($('custSearchInput')) $('custSearchInput').value = '';
        custSearchTerm = '';

        loadCustomers();
    }

    function closeCustPicker() {
        const modalEl = $('customerPickerModal');
        if (custPickerInstance) custPickerInstance.hide();
        else if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    }

    document.querySelectorAll('#customerPickerModal [data-bs-dismiss="modal"]').forEach(btn =>
        btn.addEventListener('click', closeCustPicker));

    $('custSearchInput')?.addEventListener('input', debounce((e) => {
        custSearchTerm = e.target.value.trim().toLowerCase();
        renderCustList();
    }, 300));

    $('custSearchClearBtn')?.addEventListener('click', () => {
        if ($('custSearchInput')) $('custSearchInput').value = '';
        custSearchTerm = '';
        renderCustList();
    });

    async function loadCustomers() {
        $('custLoading')?.classList.remove('d-none');
        $('custEmpty')?.classList.add('d-none');
        $('custError')?.classList.add('d-none');

        try {
            const res = await api(ROUTES.distCustomers(state.distribution.id));
            custListData = res.data || [];
            renderCustList();
        } catch (e) {
            if ($('custError')) {
                $('custError').textContent = 'فشل تحميل العملاء: ' + e.message;
                $('custError').classList.remove('d-none');
            }
        } finally {
            $('custLoading')?.classList.add('d-none');
        }
    }

    function renderCustList() {
        const tbody = $('custTbodyForInvoice');
        if (!tbody) return;
        tbody.innerHTML = '';

        let rows = custListData;
        if (custSearchTerm) {
            rows = custListData.filter(c =>
                (c.customer_name  || '').toLowerCase().includes(custSearchTerm) ||
                (c.customer_phone || '').toLowerCase().includes(custSearchTerm) ||
                (c.customer_code  || '').toLowerCase().includes(custSearchTerm)
            );
        }

        if (!rows.length) {
            if ($('custEmpty')) {
                $('custEmpty').textContent = custSearchTerm
                    ? 'لا توجد نتائج مطابقة.'
                    : 'لا يوجد عملاء في هذا التوزيع.';
                $('custEmpty').classList.remove('d-none');
            }
            return;
        }

        rows.forEach((c, i) => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.innerHTML = `
                <td class="text-muted">${i + 1}</td>
                <td>
                    <div class="fw-semibold">
                        ${escapeHtml(c.customer_name)}
                        ${c.customer_code ? `<span class="text-muted small">(${escapeHtml(c.customer_code)})</span>` : ''}
                    </div>
                    ${c.customer_phone ? `<div class="text-muted small">${escapeHtml(c.customer_phone)}</div>` : ''}
                </td>
                <td class="text-center">${c.details_count}</td>
                <td class="text-end text-success fw-semibold">${fmt(c.total_sale)}</td>
            `;
            tr.addEventListener('click', () => {
                closeCustPicker();
                selectCustomer(c);
            });
            tbody.appendChild(tr);
        });
    }

    async function selectCustomer(c) {
        state.customer = c;

        if ($('selectedCustomerLabel')) {
            $('selectedCustomerLabel').innerHTML = `
                <span class="text-success">${escapeHtml(c.customer_name)}</span>
                ${c.customer_phone ? `<span class="text-muted small">· ${escapeHtml(c.customer_phone)}</span>` : ''}
            `;
        }

        try {
            const res = await api(ROUTES.distCustomerDetails(state.distribution.id, c.customer_id));
            if (!res.success) {
                toastError(res.message || 'لا توجد تفاصيل.');
                return;
            }
            state.details = res.data || [];
            renderDetails(res.summary);
            updateSummaryAndIssueButton();
            toastSuccess(`تم اختيار العميل «${c.customer_name}».`);
        } catch (e) {
            toastError('فشل تحميل تفاصيل العميل: ' + e.message);
        }
    }

    // ============================================================
    // 3. رسم التفاصيل (13 عمود)
    // ============================================================
    function renderDetails(summary) {
        const tbody = $('invDetailsTbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!state.details.length) {
            tbody.innerHTML = `
                <tr><td colspan="13" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
                    لا توجد تفاصيل غير مفوترة.
                </td></tr>`;
            return;
        }

        state.details.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-semibold small">${escapeHtml(d.item_name)}</td>
                <td class="small text-muted">${escapeHtml(d.type_name || '—')}</td>
                <td class="text-muted small">${escapeHtml(d.invoice_number || '—')}</td>
                <td class="text-end fw-semibold text-success">${fmt(d.quantity, 3)}</td>
                <td class="text-center small text-muted">${escapeHtml(d.unit_name || '—')}</td>
                <td class="text-end fw-semibold text-primary">${fmt(d.batch_quantity, 3)}</td>
                <td class="text-end fw-semibold text-info">${fmt(d.converted_quantity, 3)}</td>
                <td class="text-end fw-semibold text-warning">${fmt(d.sorted_quantity, 3)}</td>
                <td class="text-end fw-semibold text-danger">${fmt(d.remaining_quantity, 3)}</td>
                <td class="text-end text-muted">${fmt(d.unit_cost)}</td>
                <td class="text-end text-muted">${fmt(d.sale_price)}</td>
                <td class="text-end text-muted">${fmt(d.total_cost)} ر.ي</td>
                <td class="text-end text-muted">${fmt(d.total_sale)} ر.ي</td>
            `;
            tbody.appendChild(tr);
        });

        if ($('invItemsCount')) $('invItemsCount').textContent = summary?.rows_count ?? state.details.length;

        const qtyParts = Object.entries(summary?.qty_by_unit || {})
            .map(([u, q]) => `${fmt(q, 3)} ${u}`).join(' + ');
        if ($('invQty')) $('invQty').textContent = qtyParts || '—';

        if ($('invTotalCost')) $('invTotalCost').textContent = `${fmt(summary?.total_cost || 0)} ر.ي`;
        if ($('invTotalSale')) $('invTotalSale').textContent = `${fmt(summary?.total_sale || 0)} ر.ي`;
    }

    function updateSummaryAndIssueButton() {
        const hasDetails = state.details.length > 0;
        if ($('btnIssue')) $('btnIssue').disabled = !hasDetails;
    }

    function resetCustomerSection() {
        state.customer = null;
        state.details  = [];

        if ($('selectedCustomerLabel')) $('selectedCustomerLabel').textContent = 'اختر عميلًا من هذا التوزيع';

        const tbody = $('invDetailsTbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="13" class="text-center text-muted py-4">
                        اختر عميلًا من التوزيع لعرض تفاصيله.
                    </td>
                </tr>`;
        }

        if ($('invItemsCount')) $('invItemsCount').textContent = 0;
        if ($('invQty'))        $('invQty').textContent = '—';
        if ($('invTotalCost'))  $('invTotalCost').textContent = '0.00';
        if ($('invTotalSale'))  $('invTotalSale').textContent = '0.00';
        if ($('btnIssue'))      $('btnIssue').disabled = true;
    }

    // ============================================================
    // 4. إلغاء العملية
    // ============================================================
    $('btnCancel')?.addEventListener('click', async () => {
        const ok = await confirmDialog('إلغاء العملية والعودة للبداية؟', {
            title: 'إلغاء', okText: 'متابعة', cancelText: 'تراجع', danger: true,
        });
        if (!ok) return;

        state.distribution = null;
        state.customer     = null;
        state.details      = [];
        state.paymentAccountId = null;

        if ($('selectedDistributionLabel')) $('selectedDistributionLabel').textContent = 'لم يتم اختيار توزيع';
        const accountInput = $('paymentAccountTrigger');
        if (accountInput) accountInput.value = '';

        resetCustomerSection();
        if ($('btnPickCustomer')) $('btnPickCustomer').disabled = true;

        toastInfo('تم إلغاء العملية.');
    });

    // ============================================================
    // 5. حساب الدفع
    // ============================================================
    $('paymentAccountTrigger')?.addEventListener('click', () => {
        if (state.paymentMethod === 1) {
            toastWarning('الآجل لا يحتاج حساب دفع.');
            return;
        }

        if (typeof window.AccountPicker === 'undefined') {
            toastError('مكوّن AccountPicker غير محمّل.');
            return;
        }

        window.__accountPickerOwner = 'payment';
        const type = state.paymentMethod === 3 ? 'bank' : 'cash';

        window.AccountPicker.open(type, (account) => {
            state.paymentAccountId = account.id;
            const input = $('paymentAccountTrigger');
            if (input) {
                input.value = account.code ? `${account.name} (${account.code})` : account.name;
            }
            toastSuccess(`تم اختيار: ${account.name}`);
        }, {
            allowedTypes: [type],
        });
    });

    document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            state.paymentMethod = parseInt(e.target.value);
            state.paymentAccountId = null;
            const input = $('paymentAccountTrigger');
            if (input) input.value = '';
            updatePaymentAccountVisibility();
        });
    });

    function updatePaymentAccountVisibility() {
        const isCredit = state.paymentMethod === 1;
        if ($('paymentAccountWrapper')) {
            $('paymentAccountWrapper').style.display = isCredit ? 'none' : '';
        }
    }

    // ============================================================
    // 6. إصدار الفاتورة
    // ============================================================
    $('btnIssue')?.addEventListener('click', issueInvoice);

    async function issueInvoice() {
        if (!state.distribution) { toastError('اختر توزيعًا أولًا.'); return; }
        if (!state.customer) { toastError('اختر عميلًا أولًا.'); return; }
        if (!state.details.length) { toastError('لا توجد تفاصيل قابلة للفوترة.'); return; }
        if (state.paymentMethod !== 1 && !state.paymentAccountId) {
            toastError('اختر حساب الدفع أولًا (نقدي/بنك).');
            return;
        }

        const ok = await confirmDialog(
            `سيتم إصدار فاتورة للعميل «${state.customer.customer_name}» من التوزيع «${state.distribution.code}» تشمل ${state.details.length} صف. متابعة؟`,
            { title: 'إصدار فاتورة', okText: 'إصدار', cancelText: 'إلغاء' }
        );
        if (!ok) return;

        if ($('btnIssue')) $('btnIssue').disabled = true;

        try {
            const payload = {
                customer_id:        state.customer.customer_id,
                distribution_ids:   [state.distribution.id],
                payment_method:     state.paymentMethod,
                payment_account_id: state.paymentMethod === 1 ? null : state.paymentAccountId,
            };

            const res = await api(ROUTES.issueInvoice, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            toastSuccess(res.message || 'تم إصدار الفاتورة.');
            if (res.invoice_number) {
                setTimeout(() => toastSuccess(`فاتورة البيع رقم: ${res.invoice_number}`), 500);
            }

            state.customer = null;
            state.details  = [];
            if ($('selectedCustomerLabel')) $('selectedCustomerLabel').textContent = 'اختر عميلًا آخر';
            if ($('invDetailsTbody')) {
                $('invDetailsTbody').innerHTML = `
                    <tr><td colspan="13" class="text-center text-muted py-4">
                        تمت الفوترة. اختر عميلًا جديدًا لعرض تفاصيله.
                    </td></tr>`;
            }
            if ($('invItemsCount')) $('invItemsCount').textContent = 0;
            if ($('invQty'))        $('invQty').textContent = '—';
            if ($('invTotalCost'))  $('invTotalCost').textContent = '0.00';
            if ($('invTotalSale'))  $('invTotalSale').textContent = '0.00';

        } catch (e) {
            toastError('فشل إصدار الفاتورة: ' + e.message);
        } finally {
            if ($('btnIssue')) $('btnIssue').disabled = !state.details.length;
        }
    }

    // ============================================================
    // التهيئة
    // ============================================================
    updatePaymentAccountVisibility();
});