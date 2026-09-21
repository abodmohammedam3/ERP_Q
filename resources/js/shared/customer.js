/**
 * Customer Module - إدارة العملاء (بحث، اختيار، مودال)
 * يمكن استخدامها في صفحات المبيعات والفواتير
 */

window.Customer = {
    // الحقول المستهدفة
    targetFieldId: 'CustomerName',
    targetHiddenId: 'CustomerID',
    onSelect: null,

    setTargets(nameFieldId, hiddenFieldId) {
        this.targetFieldId = nameFieldId;
        this.targetHiddenId = hiddenFieldId;
        return this;
    },

    setOnSelect(callback) {
        this.onSelect = callback;
        return this;
    },

    openModal() {
        const modalId = 'CustomerModal';
        const modal = Modals.get(modalId);
        if (!modal) {
            alert('مودال العميل غير متوفر');
            return;
        }

        const currentValue = document.getElementById(this.targetFieldId)?.value || '';
        const searchInput = document.getElementById('CustomerSearchInput');
        if (searchInput) searchInput.value = currentValue;

        modal.show();

        setTimeout(() => {
            if (searchInput) searchInput.focus();
            this.search();
        }, 300);
    },

    search() {
        const searchValue = document.getElementById('CustomerSearchInput')?.value.trim() || '';
        const tbody = document.getElementById('CustomerResults');
        if (!tbody) return;

        // بيانات وهمية (استبدلها بـ AJAX)
        const customers = [
            { id: 1, name: 'عميل تجريبي 1', account: '201001' },
            { id: 2, name: 'عميل تجريبي 2', account: '201002' },
            { id: 3, name: 'مؤسسة التسويق', account: '201003' }
        ];

        const results = customers.filter(c =>
            c.name.includes(searchValue) || c.account.includes(searchValue)
        );

        tbody.innerHTML = '';
        if (results.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
            return;
        }

        results.forEach(c => {
            tbody.innerHTML += `
                <tr>
                    <td>${c.id}</td>
                    <td>${c.name}</td>
                    <td>${c.account}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-success"
                                onclick="Customer.select(${c.id}, '${c.name}', '${c.account}')">
                            اختيار
                        </button>
                    </td>
                </tr>
            `;
        });
    },

    select(id, name, account) {
        document.getElementById(this.targetHiddenId).value = id;
        document.getElementById(this.targetFieldId).value = name;

        Modals.hide('CustomerModal');

        if (this.onSelect) this.onSelect(id, name, account);
    },

    keyDown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.openModal();
        }
    },

    blur() {
        // منطق إضافي إن لزم
    }
};