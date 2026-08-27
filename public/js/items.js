let itemModalInstance;
let lastItemId = 500; // رقم تجريبي للعناصر الجديدة

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('itemModal');
    if(modalElement) {
        itemModalInstance = new bootstrap.Modal(modalElement);
    }
    updateItemsCount();
});

// فتح نافذة الإضافة
function openItemModal() {
    document.getElementById('itemForm').reset();
    document.getElementById('itemID').value = '';
    document.getElementById('itemModalLabel').innerText = 'إضافة صنف جديد';
    
    itemModalInstance.show();
}

// فتح نافذة التعديل
function editItem(btn) {
    const row = btn.closest('tr');
    
    document.getElementById('itemID').value = row.querySelector('.row-id').innerText.trim();
    document.getElementById('itemName').value = row.querySelector('.row-name').innerText.trim();

    document.getElementById('itemModalLabel').innerText = 'تعديل بيانات الصنف';
    itemModalInstance.show();
}

// حفظ الصنف (إضافة/تعديل)
function saveItem() {
    const form = document.getElementById('itemForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('itemID').value;
    const name = document.getElementById('itemName').value;

    const tbody = document.getElementById('itemsTableBody');
    const emptyRow = document.getElementById('emptyItemRow');
    
    if (emptyRow) emptyRow.remove();

    if (id) {
        // تعديل
        const rows = tbody.querySelectorAll('tr.item-row');
        rows.forEach(row => {
            if (row.querySelector('.row-id').innerText.trim() === id) {
                row.querySelector('.row-name').innerText = name;
            }
        });
    } else {
        // إضافة
        lastItemId++;
        const newRow = document.createElement('tr');
        newRow.className = 'item-row text-center';
        newRow.innerHTML = `
            <td class="row-id">${lastItemId}</td>
            <td class="row-name">${name}</td>
            <td class="no-print">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editItem(this)">
                    <i class="bi bi-pencil"></i> تعديل
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteItem(this)">
                    <i class="bi bi-trash"></i> حذف
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    updateItemsCount();
    itemModalInstance.hide();
    alert('تم حفظ الصنف بنجاح!');
}

// حذف الصنف
function deleteItem(btn) {
    if (confirm('هل أنت متأكد من حذف هذا الصنف؟')) {
        btn.closest('tr').remove();
        
        const tbody = document.getElementById('itemsTableBody');
        if (tbody.querySelectorAll('tr.item-row').length === 0) {
            tbody.innerHTML = `
                <tr id="emptyItemRow">
                    <td colspan="3" class="text-center text-muted py-5">
                        <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                        لا توجد أصناف مسجلة
                    </td>
                </tr>
            `;
        }
        updateItemsCount();
    }
}

// تحديث عداد الأصناف
function updateItemsCount() {
    const count = document.querySelectorAll('tr.item-row').length;
    const badge = document.getElementById('itemsCountBadge');
    if(badge) badge.innerText = count;
}

// فلترة الأصناف (بحث)
function filterItems() {
    const searchText = document.getElementById('searchItemInput').value.toLowerCase();
    const rows = document.querySelectorAll('tr.item-row');

    rows.forEach(row => {
        const name = row.querySelector('.row-name').innerText.toLowerCase();
        
        if (name.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// طباعة القائمة
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
