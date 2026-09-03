let customerModalInstance;
let lastCustomerId = 1000; // رقم وهمي للعملاء الجدد كبداية

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('customerModal');
    if(modalElement) {
        customerModalInstance = new bootstrap.Modal(modalElement);
    }
});

// فتح نافذة الإضافة
function openCustomerModal() {
    document.getElementById('customerForm').reset();
    document.getElementById('customerID').value = '';
    document.getElementById('customerModalLabel').innerText = 'إضافة عميل جديد';
    
    customerModalInstance.show();
}

// فتح نافذة التعديل
function editCustomer(btn) {
    const row = btn.closest('tr');
    
    document.getElementById('customerID').value = row.querySelector('.row-id').innerText.trim();
    document.getElementById('cusName').value = row.querySelector('.row-name').innerText.trim();
    document.getElementById('cusPhone').value = row.querySelector('.row-phone').innerText.trim();
    document.getElementById('cusAddress').value = row.querySelector('.row-address').innerText.trim();
    document.getElementById('cusStatus').value = row.querySelector('.row-status').getAttribute('data-status');

    document.getElementById('customerModalLabel').innerText = 'تعديل بيانات العميل';
    customerModalInstance.show();
}

// حفظ (إضافة / تعديل) في الجدول ديناميكياً
function saveCustomer() {
    const form = document.getElementById('customerForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('customerID').value;
    const name = document.getElementById('cusName').value;
    const phone = document.getElementById('cusPhone').value;
    const address = document.getElementById('cusAddress').value;
    const status = document.getElementById('cusStatus').value;
    const statusHtml = status === '1' ? '<span class="badge bg-danger">متوقف</span>' : '<span class="badge bg-success">نشط</span>';

    const tbody = document.getElementById('customersTableBody');
    const emptyRow = document.getElementById('emptyCustomerRow');
    
    if (emptyRow) emptyRow.remove();

    if (id) {
        // حالة التعديل
        const rows = tbody.querySelectorAll('tr.customer-row');
        rows.forEach(row => {
            if (row.querySelector('.row-id').innerText.trim() === id) {
                row.querySelector('.row-name').innerText = name;
                row.querySelector('.row-phone').innerText = phone;
                row.querySelector('.row-address').innerText = address;
                const statusCell = row.querySelector('.row-status');
                statusCell.setAttribute('data-status', status);
                statusCell.innerHTML = statusHtml;
            }
        });
    } else {
        // حالة الإضافة
        lastCustomerId++;
        const newRow = document.createElement('tr');
        newRow.className = 'text-center customer-row';
        newRow.innerHTML = `
            <td class="row-id">${lastCustomerId}</td>
            <td class="row-name">${name}</td>
            <td class="row-phone">${phone}</td>
            <td class="row-address">${address}</td>
            <td class="row-status" data-status="${status}">${statusHtml}</td>
            <td class="text-center no-print">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCustomer(this)"><i class="bi bi-pencil"></i> تعديل</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(this)"><i class="bi bi-trash"></i> حذف</button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    customerModalInstance.hide();
    alert('تم حفظ البيانات بنجاح!');
}

// حذف العميل
function deleteCustomer(btn) {
    if (confirm('هل أنت متأكد من حذف هذا العميل؟')) {
        btn.closest('tr').remove();
        
        const tbody = document.getElementById('customersTableBody');
        if (tbody.querySelectorAll('tr.customer-row').length === 0) {
            tbody.innerHTML = `
                <tr id="emptyCustomerRow">
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-people fs-2 d-block mb-2"></i> لا يوجد عملاء مسجلون
                    </td>
                </tr>
            `;
        }
    }
}

// فلترة العملاء
function filterCustomers() {
    const searchText = document.getElementById('searchCustomerInput').value.toLowerCase();
    const statusValue = document.getElementById('filterCustomerStatus').value;
    const rows = document.querySelectorAll('tr.customer-row');

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

// طباعة الجدول حسب الفلترة الحالية
function printCustomers() {
    const table = document.getElementById('customersTable');
    // بناء نافذة طباعة جديدة تحتوي فقط على الصفوف الظاهرة (بدون عمود الإجراءات)
    let printContents = `
        <html dir="rtl" lang="ar">
        <head>
            <title>طباعة قائمة العملاء</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; text-align: center; }
                th, td { border: 1px solid #000; padding: 8px; }
                th { background-color: #f8f9fa; }
                .no-print { display: none !important; }
            </style>
        </head>
        <body>
            <h2>قائمة العملاء</h2>
            <table>
    `;

    // جلب الهيدر واستبعاد عمود الإجراءات
    const thead = table.querySelector('thead').innerHTML;
    printContents += `<thead>${thead}</thead><tbody>`;

    // جلب الصفوف الظاهرة فقط (غير المخفية بالفلتر)
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.style.display !== 'none' && !row.id.includes('emptyCustomerRow')) {
            printContents += `<tr>${row.innerHTML}</tr>`;
        }
    });

    printContents += `</tbody></table></body></html>`;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContents);
    printWindow.document.close();
    
    // الانتظار قليلاً لتطبيق الستايل ثم الطباعة
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}
