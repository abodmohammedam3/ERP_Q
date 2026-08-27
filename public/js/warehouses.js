let stockModalInstance;
let lastStockId = 50; // رقم وهمي للبدء به عند الإضافة

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('stockModal');
    if(modalElement) {
        stockModalInstance = new bootstrap.Modal(modalElement);
    }
    updateStocksCount();
});

// فتح نافذة الإضافة
function openStockModal() {
    document.getElementById('stockForm').reset();
    document.getElementById('stockID').value = '';
    document.getElementById('stockModalLabel').innerText = 'إضافة مخزن جديد';
    
    stockModalInstance.show();
}

// فتح نافذة التعديل
function editStock(btn) {
    const row = btn.closest('tr');
    
    document.getElementById('stockID').value = row.querySelector('.row-id').innerText.trim();
    document.getElementById('stockName').value = row.querySelector('.row-name').innerText.trim();
    
    const accountName = row.querySelector('.row-account').innerText.trim();
    document.getElementById('stockAccount').value = accountName === 'غير مرتبط' ? '' : accountName;

    document.getElementById('stockModalLabel').innerText = 'تعديل بيانات المخزن';
    stockModalInstance.show();
}

// حفظ البيانات (إضافة / تعديل) في الجدول
function saveStock() {
    const form = document.getElementById('stockForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('stockID').value;
    const name = document.getElementById('stockName').value;
    const account = document.getElementById('stockAccount').value || 'غير مرتبط';

    const tbody = document.getElementById('stocksTableBody');
    const emptyRow = document.getElementById('emptyStockRow');
    
    if (emptyRow) emptyRow.remove();

    if (id) {
        // تحديث صف موجود
        const rows = tbody.querySelectorAll('tr.stock-row');
        rows.forEach(row => {
            if (row.querySelector('.row-id').innerText.trim() === id) {
                row.querySelector('.row-name').innerText = name;
                row.querySelector('.row-account').innerText = account;
            }
        });
    } else {
        // إضافة صف جديد
        lastStockId++;
        const newRow = document.createElement('tr');
        newRow.className = 'stock-row';
        newRow.innerHTML = `
            <td class="text-center row-id">${lastStockId}</td>
            <td class="row-name">${name}</td>
            <td class="row-account">${account}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editStock(this)" title="تعديل">
                    <i class="bi bi-pencil"></i> تعديل
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteStock(this)" title="حذف">
                    <i class="bi bi-trash"></i> حذف
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    updateStocksCount();
    stockModalInstance.hide();
    alert('تم حفظ بيانات المخزن بنجاح!');
}

// حذف مخزن
function deleteStock(btn) {
    if (confirm('هل أنت متأكد من حذف هذا المخزن؟')) {
        btn.closest('tr').remove();
        
        const tbody = document.getElementById('stocksTableBody');
        if (tbody.querySelectorAll('tr.stock-row').length === 0) {
            tbody.innerHTML = `
                <tr id="emptyStockRow">
                    <td colspan="4" class="text-center text-muted py-5">
                        <i class="bi bi-building fs-2 d-block mb-2"></i>
                        لا توجد مخازن مسجلة
                    </td>
                </tr>
            `;
        }
        updateStocksCount();
    }
}

// تحديث العداد
function updateStocksCount() {
    const count = document.querySelectorAll('tr.stock-row').length;
    const badge = document.getElementById('stocksCountBadge');
    if(badge) badge.innerText = count;
}

// فلترة المخازن
function filterStocks() {
    const searchText = document.getElementById('searchStockInput').value.toLowerCase();
    const rows = document.querySelectorAll('tr.stock-row');

    rows.forEach(row => {
        const name = row.querySelector('.row-name').innerText.toLowerCase();
        
        if (name.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
