let typeModalInstance;
let lastTypeId = 100; // رقم تجريبي للأنواع الجديدة

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('typeModal');
    if(modalElement) {
        typeModalInstance = new bootstrap.Modal(modalElement);
    }
    updateTypesCount();
});

// فتح نافذة الإضافة
function openTypeModal() {
    document.getElementById('typeForm').reset();
    document.getElementById('typeID').value = '';
    document.getElementById('typeModalLabel').innerText = 'إضافة نوع جديد';
    
    typeModalInstance.show();
}

// فتح نافذة التعديل
function editType(btn) {
    const row = btn.closest('tr');
    
    document.getElementById('typeID').value = row.querySelector('.row-id').innerText.trim();
    document.getElementById('typeName').value = row.querySelector('.row-name').innerText.trim();

    document.getElementById('typeModalLabel').innerText = 'تعديل بيانات النوع';
    typeModalInstance.show();
}

// حفظ النوع (إضافة/تعديل)
function saveType() {
    const form = document.getElementById('typeForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('typeID').value;
    const name = document.getElementById('typeName').value;

    const tbody = document.getElementById('typesTableBody');
    const emptyRow = document.getElementById('emptyTypeRow');
    
    if (emptyRow) emptyRow.remove();

    if (id) {
        // تعديل
        const rows = tbody.querySelectorAll('tr.type-row');
        rows.forEach(row => {
            if (row.querySelector('.row-id').innerText.trim() === id) {
                row.querySelector('.row-name').innerText = name;
            }
        });
    } else {
        // إضافة
        lastTypeId++;
        const newRow = document.createElement('tr');
        newRow.className = 'type-row text-center';
        newRow.innerHTML = `
            <td class="row-id">${lastTypeId}</td>
            <td class="row-name">${name}</td>
            <td class="no-print">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editType(this)">
                    <i class="bi bi-pencil"></i> تعديل
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteType(this)">
                    <i class="bi bi-trash"></i> حذف
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    updateTypesCount();
    typeModalInstance.hide();
    alert('تم حفظ النوع بنجاح!');
}

// حذف النوع
function deleteType(btn) {
    if (confirm('هل أنت متأكد من حذف هذا النوع؟')) {
        btn.closest('tr').remove();
        
        const tbody = document.getElementById('typesTableBody');
        if (tbody.querySelectorAll('tr.type-row').length === 0) {
            tbody.innerHTML = `
                <tr id="emptyTypeRow">
                    <td colspan="3" class="text-center text-muted py-5">
                        <i class="bi bi-tags fs-2 d-block mb-2"></i>
                        لا توجد أنواع مسجلة
                    </td>
                </tr>
            `;
        }
        updateTypesCount();
    }
}

// تحديث العداد
function updateTypesCount() {
    const count = document.querySelectorAll('tr.type-row').length;
    const badge = document.getElementById('typesCountBadge');
    if(badge) badge.innerText = count;
}

// فلترة الأنواع (بحث)
function filterTypes() {
    const searchText = document.getElementById('searchTypeInput').value.toLowerCase();
    const rows = document.querySelectorAll('tr.type-row');

    rows.forEach(row => {
        const name = row.querySelector('.row-name').innerText.toLowerCase();
        
        if (name.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// طباعة قائمة الأنواع
function printTypes() {
    const table = document.getElementById('typesTable');
    
    let printContents = `
        <html dir="rtl" lang="ar">
        <head>
            <title>طباعة قائمة الأنواع</title>
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
            <h2>قائمة أنواع الأصناف</h2>
            <table>
    `;

    const thead = table.querySelector('thead').innerHTML;
    printContents += `<thead>${thead}</thead><tbody>`;

    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.style.display !== 'none' && !row.id.includes('emptyTypeRow')) {
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
