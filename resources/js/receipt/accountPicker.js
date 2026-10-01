/**
 * ═══════════════════════════════════════════════════════════
 *  مكون اختيار الحساب الموحد (مع تبويبات)
 *  يدعم 5 أنواع: customer, supplier, other, bank, cash
 * ═══════════════════════════════════════════════════════════
 */

window.AccountPicker = {
    currentType:  'customer',
    onSelect:     null,
    bsModal:      null,
    pickerUrl:    '/operation/accounting/receiptVouchers/picker',
    coinsID:      null,
    allowedTypes: null,

    setUrl(url) {
        this.pickerUrl = url;
    },

    open(type, onSelect, options = {}) {
        this.currentType  = type;
        this.onSelect     = onSelect;
        this.coinsID      = options.coinsID || null;
        this.allowedTypes = options.allowedTypes || null;

        this.applyTabsVisibility();
        this.activateTab(type);

        const searchInput = document.getElementById('AccountPickerSearch');
        if (searchInput) searchInput.value = '';

        const results = document.getElementById('AccountPickerResults');
        if (results) {
            results.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">جاري التحميل...</td></tr>';
        }

        if (!this.bsModal) {
            const modalEl = document.getElementById('AccountPickerModal');
            if (!modalEl) {
                console.error('❌ AccountPickerModal غير موجود في الصفحة');
                return;
            }
            this.bsModal = new bootstrap.Modal(modalEl);
        }
        this.bsModal.show();

        setTimeout(() => document.getElementById('AccountPickerSearch')?.focus(), 300);

        this.search();
    },

    applyTabsVisibility() {
        const tabs = document.querySelectorAll('#AccountPickerTabs .nav-link');

        tabs.forEach(tab => {
            const type = tab.dataset.type;

            if (!this.allowedTypes) {
                tab.parentElement.style.display = '';
                return;
            }

            if (this.allowedTypes.includes(type)) {
                tab.parentElement.style.display = '';
            } else {
                tab.parentElement.style.display = 'none';
            }
        });
    },

    activateTab(type) {
        const tabs = document.querySelectorAll('#AccountPickerTabs .nav-link');

        tabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.type === type);
        });

        this.currentType = type;
        this.updateColumnHeaders(type);
    },

    updateColumnHeaders(type) {
        const extraCol   = document.getElementById('AccountPickerColExtra');
        const balanceCol = document.getElementById('AccountPickerColBalance');

        const config = {
            customer: { extra: 'رقم الهاتف' },
            supplier: { extra: 'رقم الهاتف' },
            other:    { extra: 'معلومة إضافية' },
            bank:     { extra: 'العملة' },
            cash:     { extra: 'العملة' },
        };

        const cfg = config[type] || { extra: 'معلومة إضافية' };

        if (extraCol) extraCol.textContent = cfg.extra;

        const showBalance = ['customer', 'supplier', 'other'].includes(type);
        if (balanceCol) balanceCol.style.display = showBalance ? '' : 'none';
    },

    async search() {
        const searchInput = document.getElementById('AccountPickerSearch');
        const tbody       = document.getElementById('AccountPickerResults');

        if (!tbody) return;

        const search = searchInput?.value.trim() || '';

        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">جاري البحث...</td></tr>';

        try {
            const params = new URLSearchParams({
                type: this.currentType,
                search: search,
            });

            if (this.coinsID) {
                params.set('coinsID', this.coinsID);
            }

            const response = await fetch(
                `${this.pickerUrl}?${params.toString()}`,
                { headers: { 'Accept': 'application/json' } }
            );

            const data = await response.json();
            tbody.innerHTML = '';

            if (!data.success || !data.rows || data.rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">لا توجد نتائج</td></tr>';
                return;
            }

            const showBalance = ['customer', 'supplier', 'other'].includes(this.currentType);

            data.rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';

                const balanceText = (row.balance !== null && row.balance !== undefined)
                    ? this.formatMoney(row.balance)
                    : '—';

                tr.innerHTML = `
                    <td class="text-center fw-bold">${row.code}</td>
                    <td>${row.name}</td>
                    <td class="text-center text-muted">${row.extra || '—'}</td>
                    <td class="text-center fw-bold text-primary" style="${showBalance ? '' : 'display:none'}">
                        ${balanceText}
                    </td>
                `;

                tr.addEventListener('click', () => this.selectRow(row));

                tbody.appendChild(tr);
            });

        } catch (error) {
            console.error('❌ خطأ في البحث:', error);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">فشل الاتصال بالخادم</td></tr>';
        }
    },

    formatMoney(v) {
        return Number(v || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    },

    selectRow(row) {
        if (this.onSelect) {
            this.onSelect({
                id:            row.id,
                code:          row.code,
                name:          row.name,
                extra:         row.extra,
                balance:       row.balance,
                currency_id:   row.currency_id   || null,
                currency_code: row.currency_code || null,
                exchange_rate: row.exchange_rate || 1,
            });
        }

        this.bsModal.hide();
    },
};

// ══════════════════════════════════════════════════════════
//  ربط الأحداث
// ══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {

    document.getElementById('AccountPickerClearBtn')
        ?.addEventListener('click', function () {
            const inp = document.getElementById('AccountPickerSearch');
            if (inp) inp.value = '';
            window.AccountPicker.search();
        });

    let searchTimeout;
    document.getElementById('AccountPickerSearch')
        ?.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => window.AccountPicker.search(), 300);
        });

    document.getElementById('AccountPickerSearch')
        ?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                window.AccountPicker.search();
            }
        });

    document.querySelectorAll('#AccountPickerTabs .nav-link')
        .forEach(tab => {
            tab.addEventListener('click', function () {
                window.AccountPicker.activateTab(this.dataset.type);
                window.AccountPicker.search();
            });
        });
});