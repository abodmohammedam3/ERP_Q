let itemModalInstance;
let currentItemId = null;

document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('itemModal');
    if (modalElement) {
        itemModalInstance = new bootstrap.Modal(modalElement);
    }
});

// فتح نافذة الإضافة
function openItemModal() {
    document.getElementById('itemForm').reset();
    document.getElementById('itemID').value = '';
    currentItemId = null;
    document.getElementById('itemModalLabel').innerText = 'إضافة صنف جديد';
    itemModalInstance.show();
}

// فتح نافذة التعديل
function editItem(btn) {
    const row = btn.closest('tr');
    const id = row.querySelector('.row-id').innerText.trim();
    const name = row.querySelector('.row-name').innerText.trim();

    document.getElementById('itemID').value = id;
    document.getElementById('itemName').value = name;
    currentItemId = id;
    document.getElementById('itemModalLabel').innerText = 'تعديل بيانات الصنف';
    itemModalInstance.show();
}

// حفظ الصنف (إضافة / تعديل)
function saveItem() {
    const form = document.getElementById('itemForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('itemID').value;
    const name = document.getElementById('itemName').value.trim();

    const url = id ? `/setting/inventory/items/${id}` : '/setting/inventory/items';
    const method = id ? 'PUT' : 'POST';

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ itemName2: name })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // إعادة تحميل الصفحة لتحديث الجدول
                location.reload();
            } else {
                alert('حدث خطأ: ' + (data.message || 'غير معروف'));
            }
        })
        .catch(error => {
            console.error(error);
            alert('حدث خطأ في الاتصال بالخادم');
        });
}

// حذف الصنف (تعطيل)
function deleteItem(btn) {
    if (!confirm('هل أنت متأكد من تعطيل هذا الصنف؟')) return;

    const row = btn.closest('tr');
    const id = row.querySelector('.row-id').innerText.trim();

    fetch(`/setting/inventory/items/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('حدث خطأ: ' + (data.message || 'غير معروف'));
            }
        })
        .catch(error => {
            console.error(error);
            alert('حدث خطأ في الاتصال بالخادم');
        });
}

// تبديل حالة التفعيل (تمكين / تعطيل)
function toggleItemStatus(btn) {
    const row = btn.closest('tr');
    const id = row.querySelector('.row-id').innerText.trim();

    fetch(`/setting/inventory/items/${id}/toggle-status`, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('حدث خطأ: ' + (data.message || 'غير معروف'));
            }
        })
        .catch(error => {
            console.error(error);
            alert('حدث خطأ في الاتصال بالخادم');
        });
}

// فلترة الأصناف (تعتمد على العميل)
function filterItems() {
    const searchText = document.getElementById('searchItemInput').value.toLowerCase();
    const rows = document.querySelectorAll('tr.item-row');

    rows.forEach(row => {
        const name = row.querySelector('.row-name').innerText.toLowerCase();
        row.style.display = name.includes(searchText) ? '' : 'none';
    });
}

// طباعة القائمة (مع تعديل عدد الأعمدة)
function printItems() {
    const table = document.getElementById('itemsTable');

    let printContents = `
        <html dir="rtl" lang="ar">
        <head>
            <title>طباعة قائمة الأصناف</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h2 { text-align: center; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; text-align: center; }
                th, td { border: 1px solid #000; padding: 10px; }
                th { background-color: #f8f9fa; }
                .no-print { display: none !important; }
            </style>
        </head>
        <body>
            <h2>قائمة الأصناف</h2>
            <table>
    `;

    const thead = table.querySelector('thead').innerHTML;
    printContents += `<thead>${thead}</thead><tbody>`;

    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.style.display !== 'none' && !row.id.includes('emptyItemRow')) {
            printContents += `<tr>${row.innerHTML}</tr>`;
        }
    });

    printContents += `</tbody></table></body></html>`;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContents);
    printWindow.document.close();

    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}