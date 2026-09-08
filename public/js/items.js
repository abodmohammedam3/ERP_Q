let itemModalInstance;
let currentItemId = null;
let allItemsData = [];          // جميع الأصناف من الخادم
let filteredItemsData = [];     // الأصناف بعد تطبيق البحث
let currentPage = 1;
const rowsPerPage = 5;

document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('itemModal');
    if (modalElement) {
        itemModalInstance = new bootstrap.Modal(modalElement);
    }

    // قراءة البيانات من الـ Blade (المعروضة في الجدول)
    const rows = document.querySelectorAll('#itemsTableBody tr.item-row');
    if (rows.length > 0) {
        rows.forEach(row => {
            const item = {
                itemID: parseInt(row.dataset.id, 10),
                itemName2: row.querySelector('.row-name').innerText.trim(),
                is_active: row.querySelector('.row-status .badge').classList.contains('bg-success') ? 1 : 0
            };
            allItemsData.push(item);
        });
    } else {
        // إذا لم تكن هناك بيانات، نطلبها من الخادم
        reloadItemsTable();
    }

    // تهيئة البيانات المصفاة وعرضها
    filteredItemsData = [...allItemsData];
    applyFiltersAndRender();
});

// ============================================================
// دوال العرض
// ============================================================

function openItemModal() {
    document.getElementById('itemForm').reset();
    document.getElementById('itemID').value = '';
    currentItemId = null;
    document.getElementById('itemModalLabel').innerText = 'إضافة صنف جديد';
    itemModalInstance.show();
}

function editItem(btn) {
    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);
    const name = row.querySelector('.row-name').innerText.trim();

    document.getElementById('itemID').value = id;
    document.getElementById('itemName').value = name;
    currentItemId = id;
    document.getElementById('itemModalLabel').innerText = 'تعديل بيانات الصنف';
    itemModalInstance.show();
}

// ============================================================
// حفظ / تحديث / حذف / تبديل الحالة (AJAX)
// ============================================================

function saveItem() {
    const form = document.getElementById('itemForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('itemID').value;
    const name = document.getElementById('itemName').value.trim();

    const url = id ? `/setting/inventory/items/${id}` : '/setting/inventory/items';
    const formData = new FormData();
    formData.append('itemName2', name);
    if (id) {
        formData.append('_method', 'PUT');
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
        .then(response => response.json().then(data => ({ status: response.status, data })))
        .then(({ status, data }) => {
            if (status !== 200 || !data.success) {
                throw new Error(data.message || 'حدث خطأ غير معروف');
            }
            showSystemToast(data.message || 'تم حفظ الصنف بنجاح', 'success');
            itemModalInstance.hide();
            reloadItemsTable(); // إعادة تحميل البيانات من الخادم
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function deleteItem(btn) {
    if (!confirm('هل أنت متأكد من حذف هذا الصنف نهائياً؟')) return;

    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);

    const formData = new FormData();
    formData.append('_method', 'DELETE');

    fetch(`/setting/inventory/items/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
        .then(response => response.json().then(data => ({ status: response.status, data })))
        .then(({ status, data }) => {
            if (status !== 200 || !data.success) {
                throw new Error(data.message || 'حدث خطأ غير معروف');
            }
            showSystemToast(data.message || 'تم حذف الصنف بنجاح', 'success');
            reloadItemsTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function toggleItemStatus(btn) {
    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);

    const formData = new FormData();
    formData.append('_method', 'PATCH');

    fetch(`/setting/inventory/items/${id}/toggle-status`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
        .then(response => response.json().then(data => ({ status: response.status, data })))
        .then(({ status, data }) => {
            if (status !== 200 || !data.success) {
                throw new Error(data.message || 'حدث خطأ غير معروف');
            }
            showSystemToast(data.message || 'تم تغيير حالة الصنف بنجاح', 'success');
            reloadItemsTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

// ============================================================
// تحميل البيانات من الخادم (للتحديث)
// ============================================================

function reloadItemsTable() {
    const url = '/setting/inventory/items/list';

    fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allItemsData = data.data || [];
                filteredItemsData = [...allItemsData];
                applyFiltersAndRender();
            } else {
                showSystemToast('حدث خطأ أثناء تحميل الأصناف', 'danger');
            }
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast('حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

// ============================================================
// التصفية والعرض (كل شيء على العميل)
// ============================================================

function filterItems() {
    const searchText = document.getElementById('searchItemInput').value.toLowerCase().trim();

    if (!searchText) {
        filteredItemsData = [...allItemsData];
    } else {
        filteredItemsData = allItemsData.filter(item =>
            item.itemName2.toLowerCase().includes(searchText)
        );
    }

    currentPage = 1; // إعادة ضبط الصفحة إلى الأولى عند البحث
    applyFiltersAndRender();
}

function applyFiltersAndRender() {
    renderItems(filteredItemsData);
    updateItemsCount(filteredItemsData.length);
    renderPagination(filteredItemsData.length);
}

function renderItems(items) {
    const tbody = document.getElementById('itemsTableBody');
    if (!tbody) return;

    const start = (currentPage - 1) * rowsPerPage;
    const end = Math.min(start + rowsPerPage, items.length);
    const pageItems = items.slice(start, end);

    if (pageItems.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyItemRow">
                <td colspan="4" class="text-center text-muted py-5">
                    <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                    لا توجد أصناف مسجلة
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    pageItems.forEach((item, index) => {
        const serial = start + index + 1;
        const isActive = item.is_active == 1;
        const statusBadge = isActive
            ? '<span class="badge bg-success">نشط</span>'
            : '<span class="badge bg-danger">غير نشط</span>';

        html += `
            <tr class="item-row text-center" data-id="${item.itemID}">
                <td>${serial}</td>
                <td class="row-name">${escapeHtml(item.itemName2)}</td>
                <td class="row-status">${statusBadge}</td>
                <td class="no-print">
                    <div class="btn-action-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editItem(this)" title="تعديل">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ${isActive ? 'btn-toggle-on' : 'btn-toggle-off'}" onclick="toggleItemStatus(this)" title="${isActive ? 'تعطيل' : 'تفعيل'}">
                            <i class="bi ${isActive ? 'bi-toggle-on' : 'bi-toggle-off'}"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteItem(this)" title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function updateItemsCount(count) {
    const badge = document.getElementById('itemsCountBadge');
    if (badge) {
        badge.innerText = count;
    }
}

function renderPagination(totalItems) {
    const paginationList = document.getElementById('itemsPaginationList');
    if (!paginationList) return;

    const totalPages = Math.ceil(totalItems / rowsPerPage);

    if (totalPages <= 1) {
        paginationList.innerHTML = '';
        return;
    }

    let html = '';

    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <button type="button" class="page-link" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>
                <i class="bi bi-chevron-right"></i>
            </button>
        </li>
    `;

    for (let page = 1; page <= totalPages; page++) {
        html += `
            <li class="page-item ${page === currentPage ? 'active' : ''}">
                <button type="button" class="page-link" data-page="${page}">${page}</button>
            </li>
        `;
    }

    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <button type="button" class="page-link" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>
                <i class="bi bi-chevron-left"></i>
            </button>
        </li>
    `;

    paginationList.innerHTML = html;
}

// أحداث التنقل بين الصفحات
document.addEventListener('click', function (e) {
    const target = e.target.closest('#itemsPaginationList .page-link');
    if (!target) return;

    const page = parseInt(target.dataset.page, 10);
    if (!page || page < 1) return;

    const totalPages = Math.ceil(filteredItemsData.length / rowsPerPage);
    if (page > totalPages) return;

    currentPage = page;
    renderItems(filteredItemsData);
    renderPagination(filteredItemsData.length);
});

// ============================================================
// أدوات مساعدة
// ============================================================

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

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
                th, td { border: 1px solid #000; padding: 8px; }
                th { background-color: #f8f9fa; }
                .no-print { display: none !important; }
                .btn { display: none; }
            </style>
        </head>
        <body>
            <h2>قائمة الأصناف</h2>
            <table>
                <thead>${table.querySelector('thead').innerHTML}</thead>
                <tbody>
    `;

    const allRows = document.querySelectorAll('#itemsTableBody tr.item-row');
    allRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        let rowHtml = '<tr>';
        for (let i = 0; i < Math.min(cells.length - 1, 3); i++) {
            rowHtml += cells[i].outerHTML;
        }
        rowHtml += '</tr>';
        printContents += rowHtml;
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

if (typeof showSystemToast !== 'function') {
    window.showSystemToast = function (message, type) {
        alert(message);
    };
}