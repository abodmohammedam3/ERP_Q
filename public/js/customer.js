let customerModalInstance;
let lastCustomerId = 0;

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('customerModal');
    if(modalElement) {
        customerModalInstance = new bootstrap.Modal(modalElement);
    }

    // تعيين آخر معرف مستخدم من الجدول
    const rows = document.querySelectorAll('tr.customer-row');
    let maxId = 0;
    rows.forEach(row => {
        const id = parseInt(row.dataset.id, 10);
        if (!isNaN(id) && id > maxId) {
            maxId = id;
        }
    });
    lastCustomerId = maxId;

    // إعادة ترقيم الصفوف عند التحميل
    renumberRows();
});

function openCustomerModal() {
    document.getElementById('customerForm').reset();
    document.getElementById('customerID').value = '';
    document.getElementById('customerModalLabel').innerText = 'إضافة عميل جديد';

    // توليد رقم تحليلي جديد بناءً على آخر رقم موجود
    const analyticalInput = document.getElementById('cusAnalytical');
    const rows = document.querySelectorAll('tr.customer-row');
    let maxAnalytical = 12310000;
    rows.forEach(row => {
        const val = row.querySelector('.row-analytical')?.innerText.trim();
        if (val) {
            const num = parseInt(val, 10);
            if (!isNaN(num) && num > maxAnalytical) {
                maxAnalytical = num;
            }
        }
    });
    analyticalInput.value = maxAnalytical + 1;

    customerModalInstance.show();
}

function editCustomer(btn) {
    const row = btn.closest('tr');
    const id = row.dataset.id;

    document.getElementById('customerID').value = id;
    document.getElementById('cusName').value = row.querySelector('.row-name').innerText.trim();
    document.getElementById('cusPhone').value = row.querySelector('.row-phone').innerText.trim();
    document.getElementById('cusAddress').value = row.querySelector('.row-address').innerText.trim();
    document.getElementById('cusAnalytical').value = row.querySelector('.row-analytical').innerText.trim();
    document.getElementById('cusStatus').value = row.querySelector('.row-status').getAttribute('data-status');

    document.getElementById('customerModalLabel').innerText = 'تعديل بيانات العميل';
    customerModalInstance.show();
}

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
    const analytical = document.getElementById('cusAnalytical').value.trim();
    const status = document.getElementById('cusStatus').value;
    const statusHtml = status === '1' ? '<span class="badge bg-danger">متوقف</span>' : '<span class="badge bg-success">نشط</span>';

    const tbody = document.getElementById('customersTableBody');
    const emptyRow = document.getElementById('emptyCustomerRow');
    if (emptyRow) emptyRow.remove();

    if (id) {
        // تعديل
        const rows = tbody.querySelectorAll('tr.customer-row');
        rows.forEach(row => {
            if (row.dataset.id === id) {
                row.querySelector('.row-name').innerText = name;
                row.querySelector('.row-phone').innerText = phone;
                row.querySelector('.row-address').innerText = address;
                row.querySelector('.row-analytical').innerText = analytical;
                const statusCell = row.querySelector('.row-status');
                statusCell.setAttribute('data-status', status);
                statusCell.innerHTML = statusHtml;
            }
        });
    } else {
        // إضافة
        lastCustomerId++;
        const newId = lastCustomerId;

        const newRow = document.createElement('tr');
        newRow.className = 'text-center customer-row';
        newRow.dataset.id = newId;
        newRow.innerHTML = `
            <td class="row-id">0</td>
            <td class="row-name">${name}</td>
            <td class="row-phone">${phone}</td>
            <td class="row-address">${address}</td>
            <td class="row-analytical">${analytical}</td>
            <td class="row-status" data-status="${status}">${statusHtml}</td>
            <td class="text-center no-print">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCustomer(this)"><i class="bi bi-pencil"></i> تعديل</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(this)"><i class="bi bi-trash"></i> حذف</button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    renumberRows();
    customerModalInstance.hide();
    alert('تم حفظ البيانات بنجاح!');
}

function deleteCustomer(btn) {
    if (confirm('هل أنت متأكد من حذف هذا العميل؟')) {
        const row = btn.closest('tr');
        row.remove();

        const tbody = document.getElementById('customersTableBody');
        if (tbody.querySelectorAll('tr.customer-row').length === 0) {
            tbody.innerHTML = `
                <tr id="emptyCustomerRow">
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-people fs-2 d-block mb-2"></i> لا يوجد عملاء مسجلون
                    </td>
                </tr>
            `;
        } else {
            renumberRows();
        }
    }
}

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

function renumberRows() {
    const rows = document.querySelectorAll('tr.customer-row');
    rows.forEach((row, index) => {
        const idCell = row.querySelector('.row-id');
        if (idCell) {
            idCell.innerText = index + 1;
        }
    });
}

function printCustomers() {
    const table = document.getElementById('customersTable');
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

    const headerHtml = `
        <tr>
            <th>الرقم</th>
            <th>اسم العميل</th>
            <th>الهاتف</th>
            <th>العنوان</th>
            <th>رقم الحساب التحليلي</th>
            <th>الحالة</th>
        </tr>
    `;
    printContents += `<thead>${headerHtml}</thead><tbody>`;

    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.style.display !== 'none' && !row.id.includes('emptyCustomerRow')) {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 6) {
                const id = cells[0].innerText;
                const name = cells[1].innerText;
                const phone = cells[2].innerText;
                const address = cells[3].innerText;
                const analytical = cells[4].innerText;
                const status = cells[5].innerText;
                printContents += `
                    <tr>
                        <td>${id}</td>
                        <td>${name}</td>
                        <td>${phone}</td>
                        <td>${address}</td>
                        <td>${analytical}</td>
                        <td>${status}</td>
                    </tr>
                `;
            }
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