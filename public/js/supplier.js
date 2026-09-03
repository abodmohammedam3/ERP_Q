let supplierModalInstance;

// تهيئة النافذة المنبثقة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('supplierModal');
    if(modalElement) {
        supplierModalInstance = new bootstrap.Modal(modalElement);
    }
});

// فتح نافذة الإضافة (تفريغ الحقول أولاً)
function openSupplierModal() {
    document.getElementById('supplierForm').reset();
    document.getElementById('suplierID').value = '';
    document.getElementById('supplierModalLabel').innerText = 'إضافة مورد جديد';
    
    supplierModalInstance.show();
}

// فتح نافذة التعديل (تعبئة الحقول ببيانات الصف المحدد)
function editSupplier(btn) {
    const row = btn.closest('tr');
    
    const id = row.querySelector('.row-id').innerText.trim();
    const name = row.querySelector('.row-name').innerText.trim();
    const phone = row.querySelector('.row-phone').innerText.trim();
    const area = row.querySelector('.row-area').innerText.trim();
    const status = row.querySelector('.row-status').getAttribute('data-status');

    document.getElementById('suplierID').value = id;
    document.getElementById('supName').value = name;
    document.getElementById('supPhone').value = phone;
    document.getElementById('supArea').value = area;
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
    const name = document.getElementById('supName').value;
    
    if (id) {
        // هنا يتم إرسال طلب Ajax للتعديل (محاكاة)
        alert('تم تعديل بيانات المورد: ' + name + ' بنجاح!');
    } else {
        // هنا يتم إرسال طلب Ajax للإضافة (محاكاة)
        alert('تم إضافة المورد: ' + name + ' بنجاح!');
    }

    supplierModalInstance.hide();
    
    // يمكن هنا إعادة تحميل الصفحة أو تحديث الجدول برمجياً
    // location.reload();
}

// حذف مورد
function deleteSupplier(btn) {
    const row = btn.closest('tr');
    const name = row.querySelector('.row-name').innerText.trim();

    if (confirm('هل أنت متأكد من حذف المورد: ' + name + '؟')) {
        // محاكاة عملية الحذف
        row.remove();
        alert('تم الحذف بنجاح');
        
        // التحقق مما إذا كان الجدول فارغاً لإظهار رسالة "لا يوجد موردون"
        const tbody = document.getElementById('suppliersTableBody');
        if (tbody.querySelectorAll('tr.supplier-row').length === 0) {
            tbody.innerHTML = `
                <tr id="emptyRow">
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-truck fs-2 d-block mb-2"></i>
                        لا يوجد موردون مسجلون
                    </td>
                </tr>
            `;
        }
    }
}

// فلترة وبحث في الجدول محلياً (محاكاة)
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
