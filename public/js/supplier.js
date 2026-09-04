let supplierModalInstance;
let lastAnalyticalAccount = 12310000; // القيمة الأولية، سيتم تحديثها عند التحميل

// تهيئة النافذة المنبثقة وتحديد آخر رقم محاسبي من الجدول
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('supplierModal');
    if(modalElement) {
        supplierModalInstance = new bootstrap.Modal(modalElement);
    }

    // حساب آخر رقم محاسبي مستخدم من الجدول
    const rows = document.querySelectorAll('tr.supplier-row');
    let maxAccount = 12310000;
    rows.forEach(row => {
        const accText = row.querySelector('.row-analytical')?.innerText.trim();
        if (accText) {
            const num = parseInt(accText, 10);
            if (!isNaN(num) && num > maxAccount) {
                maxAccount = num;
            }
        }
    });
    lastAnalyticalAccount = maxAccount;
});

// الحصول على الرقم المحاسبي التالي
function getNextAnalyticalAccount() {
    return lastAnalyticalAccount + 1;
}

// فتح نافذة الإضافة (تفريغ الحقول أولاً)
function openSupplierModal() {
    document.getElementById('supplierForm').reset();
    document.getElementById('suplierID').value = '';
    document.getElementById('supplierModalLabel').innerText = 'إضافة مورد جديد';

    // تعيين الرقم المحاسبي التالي
    const nextAccount = getNextAnalyticalAccount();
    document.getElementById('analyticalAccount').value = nextAccount;
    
    supplierModalInstance.show();
}

// فتح نافذة التعديل (تعبئة الحقول ببيانات الصف المحدد)
function editSupplier(btn) {
    const row = btn.closest('tr');
    
    const id = row.querySelector('.row-id').innerText.trim();
    const name = row.querySelector('.row-name').innerText.trim();
    const phone = row.querySelector('.row-phone').innerText.trim();
    const area = row.querySelector('.row-area').innerText.trim();
    const analytical = row.querySelector('.row-analytical').innerText.trim();
    const status = row.querySelector('.row-status').getAttribute('data-status');

    document.getElementById('suplierID').value = id;
    document.getElementById('supName').value = name;
    document.getElementById('supPhone').value = phone;
    document.getElementById('supArea').value = area;
    document.getElementById('analyticalAccount').value = analytical;
    document.getElementById('supStatus').value = status;

    document.getElementById('supplierModalLabel').innerText = 'تعديل بيانات المورد';
    
    supplierModalInstance.show();
}

// حفظ بيانات المورد (إضافة أو تعديل)
function saveSupplier() {
    const form = document.getElementById('supplierForm');
    
    // تحقق بسيط من الحقول الإلزامية
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('suplierID').value;
    const name = document.getElementById('supName').value.trim();
    const phone = document.getElementById('supPhone').value.trim();
    const area = document.getElementById('supArea').value.trim();
    const analytical = document.getElementById('analyticalAccount').value.trim();
    const status = document.getElementById('supStatus').value;

    // كائن بيانات المورد
    const supplierData = {
        id: id ? parseInt(id) : null,
        name: name,
        phone: phone,
        area: area,
        analytical: analytical,
        status: status
    };

    if (id) {
        // تعديل موجود
        updateSupplierRow(id, supplierData);
        alert('تم تعديل بيانات المورد: ' + name + ' بنجاح!');
    } else {
        // إضافة جديد
        // نتحقق من أن الرقم المحاسبي لم يتغير (قد يكون تم تغييره يدوياً، لكنه readonly)
        // نضيف الصف
        addSupplierRow(supplierData);
        // تحديث آخر رقم محاسبي
        lastAnalyticalAccount = parseInt(analytical, 10);
        alert('تم إضافة المورد: ' + name + ' بنجاح!');
    }

    supplierModalInstance.hide();
}

// إضافة صف جديد إلى الجدول
function addSupplierRow(data) {
    const tbody = document.getElementById('suppliersTableBody');
    // إزالة رسالة "لا يوجد موردون" إن وجدت
    const emptyRow = document.getElementById('emptyRow');
    if (emptyRow) {
        emptyRow.remove();
    }

    // إنشاء صف جديد
    const row = document.createElement('tr');
    row.className = 'text-center supplier-row';
    row.setAttribute('data-id', data.id || 'new');

    // نعطي id مؤقت إذا كان جديداً (يمكن استخدام وقت)
    const tempId = data.id || Date.now();

    // تحديد نص الحالة
    const statusBadge = data.status == 1 
        ? '<span class="badge bg-danger">متوقف</span>' 
        : '<span class="badge bg-success">نشط</span>';

    row.innerHTML = `
        <td class="row-id">${tempId}</td>
        <td class="row-name">${escapeHtml(data.name)}</td>
        <td class="row-phone">${escapeHtml(data.phone)}</td>
        <td class="row-area">${escapeHtml(data.area)}</td>
        <td class="row-analytical">${escapeHtml(data.analytical)}</td>
        <td class="row-status" data-status="${data.status}">${statusBadge}</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editSupplier(this)">
                <i class="bi bi-pencil"></i> تعديل
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSupplier(this)">
                <i class="bi bi-trash"></i> حذف
            </button>
        </td>
    `;

    tbody.appendChild(row);
}

// تحديث صف موجود
function updateSupplierRow(id, data) {
    const rows = document.querySelectorAll('tr.supplier-row');
    let targetRow = null;
    rows.forEach(row => {
        if (row.querySelector('.row-id').innerText.trim() == id) {
            targetRow = row;
        }
    });

    if (!targetRow) {
        alert('الصف غير موجود');
        return;
    }

    // تحديث البيانات
    targetRow.querySelector('.row-name').innerText = data.name;
    targetRow.querySelector('.row-phone').innerText = data.phone;
    targetRow.querySelector('.row-area').innerText = data.area;
    targetRow.querySelector('.row-analytical').innerText = data.analytical;
    const statusTd = targetRow.querySelector('.row-status');
    statusTd.setAttribute('data-status', data.status);
    statusTd.innerHTML = data.status == 1 
        ? '<span class="badge bg-danger">متوقف</span>' 
        : '<span class="badge bg-success">نشط</span>';
}

// حذف مورد
function deleteSupplier(btn) {
    const row = btn.closest('tr');
    const name = row.querySelector('.row-name').innerText.trim();

    if (confirm('هل أنت متأكد من حذف المورد: ' + name + '؟')) {
        row.remove();
        alert('تم الحذف بنجاح');
        
        // التحقق مما إذا كان الجدول فارغاً لإظهار رسالة "لا يوجد موردون"
        const tbody = document.getElementById('suppliersTableBody');
        if (tbody.querySelectorAll('tr.supplier-row').length === 0) {
            tbody.innerHTML = `
                <tr id="emptyRow">
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-truck fs-2 d-block mb-2"></i>
                        لا يوجد موردون مسجلون
                    </td>
                </tr>
            `;
        }
    }
}

// فلترة وبحث في الجدول محلياً
function filterSuppliers() {
    const searchText = document.getElementById('searchInput').value.toLowerCase();
    const statusValue = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('tr.supplier-row');

    rows.forEach(row => {
        const name = row.querySelector('.row-name').innerText.toLowerCase();
        const phone = row.querySelector('.row-phone').innerText.toLowerCase();
        const status = row.querySelector('.row-status').getAttribute('data-status');

        const matchSearch = name.includes(searchText) || phone.includes(searchText);
        const matchStatus = statusValue === "" || status === statusValue;

        if (matchSearch && matchStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// دالة الطباعة
function printSuppliers() {
    // نأخذ الصفوف المرئية فقط (حسب الفلترة)
    const rows = document.querySelectorAll('tr.supplier-row');
    let visibleRows = [];
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            visibleRows.push(row);
        }
    });

    if (visibleRows.length === 0) {
        alert('لا توجد بيانات للطباعة');
        return;
    }

    // بناء محتوى الطباعة
    let tableHtml = `
        <table border="1" cellpadding="5" style="width:100%; border-collapse:collapse; text-align:center;">
            <thead>
                <tr>
                    <th>الرقم</th>
                    <th>اسم المورد</th>
                    <th>الهاتف</th>
                    <th>المنطقة</th>
                    <th>رقم الحساب التحليلي</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
    `;

    visibleRows.forEach(row => {
        const id = row.querySelector('.row-id').innerText.trim();
        const name = row.querySelector('.row-name').innerText.trim();
        const phone = row.querySelector('.row-phone').innerText.trim();
        const area = row.querySelector('.row-area').innerText.trim();
        const analytical = row.querySelector('.row-analytical').innerText.trim();
        const statusText = row.querySelector('.row-status .badge').innerText.trim();

        tableHtml += `
            <tr>
                <td>${id}</td>
                <td>${name}</td>
                <td>${phone}</td>
                <td>${area}</td>
                <td>${analytical}</td>
                <td>${statusText}</td>
            </tr>
        `;
    });

    tableHtml += `</tbody></table>`;

    // فتح نافذة الطباعة
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <title>طباعة الموردين</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; direction: rtl; }
                h2 { text-align: center; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #000; padding: 8px; text-align: center; }
                th { background: #eee; }
                .date { text-align: left; margin-bottom: 15px; }
            </style>
        </head>
        <body>
            <h2>قائمة الموردين</h2>
            <div class="date">تاريخ الطباعة: ${new Date().toLocaleDateString('ar-YE')}</div>
            ${tableHtml}
            <script>
                window.onload = function() { window.print(); };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// دالة لتجنب injection (حماية)
function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}