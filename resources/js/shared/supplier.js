/**
 * Supplier Module - إدارة الموردين (بحث، اختيار، مودال)
 * يمكن استخدامها في أي صفحة تحتاج إلى اختيار مورد
 */

window.Supplier = {
    // الحقل الذي سيتم تعبئته بعد الاختيار
    targetFieldId: 'SupplierName',
    targetHiddenId: 'SupplierID',
    // دالة بعد الاختيار (اختياري)
    onSelect: null,

    // تعيين الحقول المستهدفة
    setTargets(nameFieldId, hiddenFieldId) {
        this.targetFieldId = nameFieldId;
        this.targetHiddenId = hiddenFieldId;
        return this;
    },

    // تعيين دالة بعد الاختيار
    setOnSelect(callback) {
        this.onSelect = callback;
        return this;
    },

    // فتح مودال المورد
    openModal() {
        const modalId = 'SupplierModal';
        const modal = Modals.get(modalId);
        if (!modal) {
            alert('مودال المورد غير متوفر');
            return;
        }

        // نقل القيمة الحالية إلى حقل البحث
        const currentValue = document.getElementById(this.targetFieldId)?.value || '';
        const searchInput = document.getElementById('SupplierSearchInput');
        if (searchInput) searchInput.value = currentValue;

        modal.show();

        // تركيز حقل البحث بعد فتح المودال
        setTimeout(() => {
            if (searchInput) searchInput.focus();
            this.search();
        }, 300);
    },

    // البحث عن الموردين
    search() {
        const searchValue = document.getElementById('SupplierSearchInput')?.value.trim() || '';
        const tbody = document.getElementById('SupplierResults');
        if (!tbody) return;

        // بيانات وهمية (يمكن استبدالها بـ AJAX)
        const suppliers = [
            { id: 1, name: 'مورد تجريبي 1', account: '401001' },
            { id: 2, name: 'مورد تجريبي 2', account: '401002' },
            { id: 3, name: 'مؤسسة التوريدات', account: '401003' }
        ];

        const results = suppliers.filter(s =>
            s.name.includes(searchValue) || s.account.includes(searchValue)
        );

        tbody.innerHTML = '';
        if (results.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
            return;
        }

        results.forEach(s => {
            tbody.innerHTML += `
                <tr>
                    <td>${s.id}</td>
                    <td>${s.name}</td>
                    <td>${s.account}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-success"
                                onclick="Supplier.select(${s.id}, '${s.name}', '${s.account}')">
                            اختيار
                        </button>
                    </td>
                </tr>
            `;
        });
    },

    // اختيار مورد
    select(id, name, account) {
        document.getElementById(this.targetHiddenId).value = id;
        document.getElementById(this.targetFieldId).value = name;

        // إغلاق المودال
        Modals.hide('SupplierModal');

        // استدعاء callback إن وجد
        if (this.onSelect) this.onSelect(id, name, account);
    },

    // أحداث لوحة المفاتيح والفأرة
    keyDown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.openModal();
        }
    },

    blur() {
        // يمكن إضافة منطق إضافي هنا
    }
};