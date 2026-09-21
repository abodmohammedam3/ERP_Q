/**
 * Receipt Voucher Search Module - البحث عن سندات القبض
 */

window.ReceiptVoucherSearch = {
    targetFields: {},
    onSelect: null,

    setTargets(fields) {
        this.targetFields = { ...this.targetFields, ...fields };
        return this;
    },

    setOnSelect(callback) {
        this.onSelect = callback;
        return this;
    },

    openModal() {
        const modal = Modals.get('ReceiptVoucherSearchModal');
        if (!modal) {
            alert('مودال البحث عن سندات القبض غير متوفر');
            return;
        }

        const searchInput = document.getElementById('ReceiptVoucherSearchInput');
        if (searchInput) searchInput.value = '';

        const tbody = document.getElementById('ReceiptVoucherSearchResults');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">ابحث عن سند قبض</td></tr>`;
        }

        modal.show();

        setTimeout(() => {
            if (searchInput) searchInput.focus();
        }, 300);
    },

    search() {
        const searchValue = document.getElementById('ReceiptVoucherSearchInput')?.value.trim() || '';
        const tbody = document.getElementById('ReceiptVoucherSearchResults');
        if (!tbody) return;

        // بيانات وهمية (استبدلها بـ AJAX من الخادم)
        const vouchers = [
            {
                number: 'قبض-2026-001',
                date: '2026-09-01',
                customer: 'عميل تجريبي 1',
                customerId: 1,
                amount: '150000',
                currency: 'ريال يمني',
                currencyId: 1,
                exchangeRate: 1,
                payment: 'نقد',
                status: 'مفعل',
                notes: 'دفعة أولى'
            },
            {
                number: 'قبض-2026-002',
                date: '2026-09-02',
                customer: 'عميل تجريبي 2',
                customerId: 2,
                amount: '225000',
                currency: 'ريال سعودي',
                currencyId: 2,
                exchangeRate: 140,
                payment: 'تحويل بنكي',
                status: 'مفعل',
                notes: 'دفعة ثانية'
            },
            {
                number: 'قبض-2026-003',
                date: '2026-09-03',
                customer: 'مؤسسة التسويق',
                customerId: 3,
                amount: '50000',
                currency: 'ريال يمني',
                currencyId: 1,
                exchangeRate: 1,
                payment: 'شيك',
                status: 'معلق',
                notes: 'شيك مؤجل'
            },
            {
                number: 'قبض-2026-004',
                date: '2026-09-04',
                customer: 'عميل تجريبي 1',
                customerId: 1,
                amount: '75000',
                currency: 'دولار أمريكي',
                currencyId: 3,
                exchangeRate: 530,
                payment: 'عبر شبكة',
                status: 'مفعل',
                notes: 'دفعة نهائية'
            }
        ];

        const results = vouchers.filter(v =>
            v.number.toLowerCase().includes(searchValue.toLowerCase()) ||
            v.customer.toLowerCase().includes(searchValue.toLowerCase())
        );

        tbody.innerHTML = '';
        if (results.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">لا توجد نتائج مطابقة</td></tr>`;
            return;
        }

        results.forEach(v => {
            let statusColor = 'success';
            if (v.status === 'ملغي') statusColor = 'danger';
            else if (v.status === 'معلق') statusColor = 'warning';

            tbody.innerHTML += `
                <tr>
                    <td><strong>${v.number}</strong></td>
                    <td>${v.date}</td>
                    <td>${v.customer}</td>
                    <td>${v.amount}</td>
                    <td>${v.currency}</td>
                    <td>${v.payment}</td>
                    <td><span class="badge bg-${statusColor}">${v.status}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-success"
                                onclick="ReceiptVoucherSearch.select('${v.number}', '${v.date}', '${v.customer}', ${v.customerId}, '${v.amount}', '${v.currency}', ${v.currencyId}, ${v.exchangeRate}, '${v.payment}', '${v.notes}')">
                            <i class="bi bi-check-lg"></i> اختيار
                        </button>
                    </td>
                </tr>
            `;
        });
    },

    select(number, date, customer, customerId, amount, currency, currencyId, exchangeRate, paymentMethod, notes) {
        const fields = this.targetFields;

        if (fields.number) {
            const el = document.getElementById(fields.number);
            if (el) el.value = number;
        }
        if (fields.date) {
            const el = document.getElementById(fields.date);
            if (el) el.value = date;
        }
        if (fields.customer) {
            const el = document.getElementById(fields.customer);
            if (el) el.value = customer;
        }
        if (fields.customerId) {
            const el = document.getElementById(fields.customerId);
            if (el) el.value = customerId;
        }
        if (fields.amount) {
            const el = document.getElementById(fields.amount);
            if (el) el.value = amount;
        }
        if (fields.currency) {
            const el = document.getElementById(fields.currency);
            if (el) el.value = currency;
        }
        if (fields.currencyId) {
            const el = document.getElementById(fields.currencyId);
            if (el) el.value = currencyId;
        }
        if (fields.exchangeRate) {
            const el = document.getElementById(fields.exchangeRate);
            if (el) el.value = exchangeRate;
        }
        if (fields.paymentMethod) {
            const el = document.getElementById(fields.paymentMethod);
            if (el) el.value = paymentMethod;
        }
        if (fields.notes) {
            const el = document.getElementById(fields.notes);
            if (el) el.value = notes;
        }

        if (typeof PaymentMethod !== 'undefined' && PaymentMethod.change) {
            PaymentMethod.change();
        }

        Modals.hide('ReceiptVoucherSearchModal');

        if (this.onSelect) {
            this.onSelect({ number, date, customer, customerId, amount, currency, currencyId, exchangeRate, paymentMethod, notes });
        }

        if (typeof updateDisplay === 'function') updateDisplay();
        if (typeof updateSummary === 'function') updateSummary();
        if (typeof state !== 'undefined' && state.setMode) state.setMode('view');
    }
};

console.log('✅ ReceiptVoucherSearch تم تحميله بنجاح');