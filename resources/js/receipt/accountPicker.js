/**
 * ═══════════════════════════════════════════════════════════
 *  مكون اختيار الحساب الموحد
 *  - يدعم 3 أنواع: customer (العملاء)، supplier (الموردين)،
 *    cash (الصناديق)، bank (البنوك)
 *  - المسار (URL) قابل للتخصيص
 * ═══════════════════════════════════════════════════════════
 */

window.AccountPicker = {
    currentType: 'customer',
    onSelect:    null,
    bsModal:     null,
    pickerUrl:   '/operation/accounting/receiptVouchers/picker', // ⭐ مسار افتراضي

    /**
     * ⭐ تعيين مسار الـ picker
     * @param {string} url
     */
    setUrl(url) {
        this.pickerUrl = url;
    },

    open(type, onSelect) {
        this.currentType = type;
        this.onSelect    = onSelect;

        const config = {
            customer: {
                title:    'اختيار الحساب الدائن (العميل)',
                colCode:  'الكود المحاسبي',
                colName:  'اسم العميل',
                colExtra: 'رقم الهاتف',
            },
            supplier: {
                title:    'اختيار الحساب المدين (المورد)',
                colCode:  'الكود المحاسبي',
                colName:  'اسم المورد',
                colExtra: 'رقم الهاتف',
            },
            cash: {
                title:    'اختيار حساب الصندوق',
                colCode:  'الكود المحاسبي',
                colName:  'اسم الصندوق',
                colExtra: 'العملة',
            },
            bank: {
                title:    'اختيار الحساب البنكي',
                colCode:  'الكود المحاسبي',
                colName:  'اسم البنك',
                colExtra: 'العملة',
            },
        };

        const cfg = config[type] || config.customer;

        document.getElementById('AccountPickerTitle').textContent    = cfg.title;
        document.getElementById('AccountPickerColCode').textContent  = cfg.colCode;
        document.getElementById('AccountPickerColName').textContent  = cfg.colName;
        document.getElementById('AccountPickerColExtra').textContent = cfg.colExtra;

        document.getElementById('AccountPickerSearch').value = '';
        document.getElementById('AccountPickerResults').innerHTML =
            '<tr><td colspan="3" class="text-center text-muted py-4">جاري التحميل...</td></tr>';

        if (!this.bsModal) {
            this.bsModal = new bootstrap.Modal(document.getElementById('AccountPickerModal'));
        }
        this.bsModal.show();

        setTimeout(() => document.getElementById('AccountPickerSearch')?.focus(), 300);

        this.search();
    },

    async search() {
        const search = document.getElementById('AccountPickerSearch').value.trim();
        const tbody  = document.getElementById('AccountPickerResults');

        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">جاري البحث...</td></tr>';

        try {
            const response = await fetch(
                `${this.pickerUrl}?type=${this.currentType}&search=${encodeURIComponent(search)}`,
                { headers: { 'Accept': 'application/json' } }
            );

            const data = await response.json();
            tbody.innerHTML = '';

            if (!data.success || !data.rows || data.rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">لا توجد نتائج</td></tr>';
                return;
            }

            data.rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                tr.innerHTML = `
                    <td class="text-center fw-bold">${row.code}</td>
                    <td>${row.name}</td>
                    <td class="text-center text-muted">${row.extra || '—'}</td>
                `;

                tr.addEventListener('click', () => {
                    if (this.onSelect) {
                        this.onSelect({
                            id:    row.id,
                            code:  row.code,
                            name:  row.name,
                            extra: row.extra,
                        });
                    }
                    this.bsModal.hide();
                });

                tbody.appendChild(tr);
            });

        } catch (error) {
            console.error('❌ خطأ في البحث:', error);
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">فشل الاتصال بالخادم</td></tr>';
        }
    },
};

// ══════════════════════════════════════════════════════════
//  ربط الأحداث (زر التفريغ + البحث الفوري)
// ══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {
    // ⭐ زر التفريغ
    document.getElementById('AccountPickerClearBtn')
        ?.addEventListener('click', function () {
            document.getElementById('AccountPickerSearch').value = '';
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
});