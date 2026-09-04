document.addEventListener('DOMContentLoaded', function () {

    // =========================================================
    // البيانات التجريبية (مع إضافة الحقل التحليلي)
    // =========================================================

    let warehouses = [
        {
            id: 1,
            name: 'المخزن الرئيسي',
            analyticalAccount: '14110001'
        },
        {
            id: 2,
            name: 'مخزن الفرع الأول',
            analyticalAccount: '14110002'
        },
        {
            id: 3,
            name: 'مخزن التبريد',
            analyticalAccount: '14110003'
        }
    ];

    let editingId = null;
    let lastAnalyticalAccount = 14110000;

    // تحديث آخر رقم تحليلي مستخدم
    function updateLastAnalytical() {
        let max = 14110000;
        warehouses.forEach(w => {
            const num = parseInt(w.analyticalAccount, 10);
            if (!isNaN(num) && num > max) max = num;
        });
        lastAnalyticalAccount = max;
    }
    updateLastAnalytical();

    // =========================================================
    // عناصر الصفحة
    // =========================================================

    const tableBody = document.getElementById('warehousesTableBody');
    const searchInput = document.getElementById('warehouseSearch');
    const searchButton = document.getElementById('searchWarehouseBtn');
    const addButton = document.getElementById('addWarehouseBtn');
    const printButton = document.getElementById('printWarehousesBtn');

    // عناصر المودال
    const warehouseModal = document.getElementById('warehouseModal');
    const warehouseModalLabel = document.getElementById('warehouseModalLabel');
    const warehouseForm = document.getElementById('warehouseForm');
    const warehouseId = document.getElementById('warehouseId');
    const warehouseName = document.getElementById('warehouseName');
    const warehouseAnalytical = document.getElementById('warehouseAnalytical');
    const saveWarehouseBtn = document.getElementById('saveWarehouseBtn');

    let modalInstance = null;
    if (warehouseModal) {
        modalInstance = new bootstrap.Modal(warehouseModal);
    }

    // =========================================================
    // عرض المخازن
    // =========================================================

    function renderWarehouses(data = warehouses) {
        if (!tableBody) return;

        tableBody.innerHTML = '';

        if (data.length === 0) {
            tableBody.innerHTML = `
                <tr id="emptyWarehouseRow">
                    <td colspan="4" class="text-center text-muted py-5">
                        <i class="bi bi-building fs-2 d-block mb-2"></i>
                        لا توجد مخازن مسجلة
                    </td>
                </tr>
            `;
            updateCount(0);
            return;
        }

        data.forEach(function (warehouse, index) {
            const row = document.createElement('tr');
            row.className = 'warehouse-row';
            row.dataset.id = warehouse.id;

            row.innerHTML = `
                <td class="text-center row-number">${index + 1}</td>
                <td class="row-name">${escapeHtml(warehouse.name)}</td>
                <td class="row-analytical">${escapeHtml(warehouse.analyticalAccount || 'غير محدد')}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-primary edit-warehouse" data-id="${warehouse.id}" title="تعديل">
                        <i class="bi bi-pencil"></i> تعديل
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-warehouse" data-id="${warehouse.id}" title="حذف">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </td>
            `;

            tableBody.appendChild(row);
        });

        updateCount(data.length);
    }

    // =========================================================
    // تحديث عدد المخازن
    // =========================================================

    function updateCount(count) {
        const badge = document.getElementById('warehouseCount');
        if (badge) {
            badge.textContent = count;
        }
    }

    // =========================================================
    // إعادة ترقيم الصفوف (بعد الحذف)
    // =========================================================

    function renumberRows() {
        const rows = document.querySelectorAll('tr.warehouse-row');
        rows.forEach((row, index) => {
            const numCell = row.querySelector('.row-number');
            if (numCell) numCell.innerText = index + 1;
        });
    }

    // =========================================================
    // البحث (يعمل تلقائياً عند الكتابة)
    // =========================================================

    function filterWarehouses() {
        const search = searchInput.value.trim().toLowerCase();
        const filtered = warehouses.filter(function (w) {
            return w.name.toLowerCase().includes(search);
        });
        renderWarehouses(filtered);
    }

    // مستمعات البحث
    if (searchInput) {
        searchInput.addEventListener('input', filterWarehouses);
    }
    if (searchButton) {
        searchButton.addEventListener('click', filterWarehouses);
    }

    // =========================================================
    // فتح مودال الإضافة
    // =========================================================

    function openAddModal() {
        editingId = null;
        warehouseForm.reset();
        warehouseId.value = '';
        warehouseModalLabel.innerHTML = '<i class="bi bi-building"></i> إضافة مخزن';

        // توليد رقم تحليلي جديد
        const newAnalytical = lastAnalyticalAccount + 1;
        warehouseAnalytical.value = newAnalytical;

        if (modalInstance) modalInstance.show();
    }

    // =========================================================
    // فتح مودال التعديل
    // =========================================================

    function openEditModal(id) {
        const warehouse = warehouses.find(w => w.id === id);
        if (!warehouse) return;

        editingId = id;
        warehouseId.value = id;
        warehouseName.value = warehouse.name;
        warehouseAnalytical.value = warehouse.analyticalAccount || '';
        warehouseModalLabel.innerHTML = '<i class="bi bi-building"></i> تعديل المخزن';

        if (modalInstance) modalInstance.show();
    }

    // =========================================================
    // حفظ المخزن (إضافة أو تعديل)
    // =========================================================

    function saveWarehouse() {
        if (!warehouseForm.checkValidity()) {
            warehouseForm.reportValidity();
            return;
        }

        const name = warehouseName.value.trim();
        if (!name) {
            alert('يرجى إدخال اسم المخزن.');
            return;
        }

        const analytical = warehouseAnalytical.value.trim();

        if (editingId) {
            // تعديل
            const existing = warehouses.find(w => w.id === editingId);
            if (existing) {
                existing.name = name;
                existing.analyticalAccount = analytical;
            }
        } else {
            // إضافة
            const newId = warehouses.length > 0 ? Math.max(...warehouses.map(w => w.id)) + 1 : 1;
            warehouses.push({
                id: newId,
                name: name,
                analyticalAccount: analytical
            });
            // تحديث آخر رقم تحليلي
            const num = parseInt(analytical, 10);
            if (!isNaN(num) && num > lastAnalyticalAccount) {
                lastAnalyticalAccount = num;
            }
        }

        renderWarehouses();
        renumberRows();
        if (modalInstance) modalInstance.hide();
        alert('تم حفظ المخزن بنجاح.');
    }

    // =========================================================
    // حذف المخزن
    // =========================================================

    function deleteWarehouse(id) {
        const warehouse = warehouses.find(w => w.id === id);
        if (!warehouse) return;

        if (!confirm(`هل أنت متأكد من حذف المخزن "${warehouse.name}"؟`)) return;

        warehouses = warehouses.filter(w => w.id !== id);
        renderWarehouses();
        renumberRows();
        alert('تم حذف المخزن بنجاح.');
    }

    // =========================================================
    // طباعة المخازن
    // =========================================================

    function printWarehouses() {
        const search = searchInput.value.trim().toLowerCase();
        let dataToPrint = warehouses;
        if (search) {
            dataToPrint = warehouses.filter(w => w.name.toLowerCase().includes(search));
        }

        if (dataToPrint.length === 0) {
            alert('لا توجد بيانات للطباعة.');
            return;
        }

        let rows = '';
        dataToPrint.forEach((w, index) => {
            rows += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(w.name)}</td>
                    <td>${escapeHtml(w.analyticalAccount || 'غير محدد')}</td>
                </tr>
            `;
        });

        const printWindow = window.open('', '_blank', 'width=900,height=700');
        if (!printWindow) {
            alert('تعذر فتح نافذة الطباعة. يرجى السماح بالنوافذ المنبثقة.');
            return;
        }

        printWindow.document.write(`
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>طباعة المخازن</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 30px; direction: rtl; }
                    h2 { text-align: center; margin-bottom: 25px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #000; padding: 10px; text-align: center; }
                    th { background: #eee; }
                    .date { text-align: left; margin-bottom: 15px; }
                    @media print { button { display: none; } }
                </style>
            </head>
            <body>
                <h2>قائمة المخازن</h2>
                <div class="date">تاريخ الطباعة: ${new Date().toLocaleDateString('ar-YE')}</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المخزن</th>
                            <th>رقم الحساب التحليلي</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <script>
                    window.onload = function () { window.print(); };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // =========================================================
    // أحداث الأزرار
    // =========================================================

    if (addButton) {
        addButton.addEventListener('click', openAddModal);
    }

    if (saveWarehouseBtn) {
        saveWarehouseBtn.addEventListener('click', saveWarehouse);
    }

    if (printButton) {
        printButton.addEventListener('click', printWarehouses);
    }

    // التعديل والحذف عبر delegation على الجدول
    if (tableBody) {
        tableBody.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-warehouse');
            const deleteBtn = e.target.closest('.delete-warehouse');

            if (editBtn) {
                const id = Number(editBtn.dataset.id);
                openEditModal(id);
            }

            if (deleteBtn) {
                const id = Number(deleteBtn.dataset.id);
                deleteWarehouse(id);
            }
        });
    }

    // =========================================================
    // حماية النصوص
    // =========================================================

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =========================================================
    // التشغيل الأول
    // =========================================================

    renderWarehouses();
});