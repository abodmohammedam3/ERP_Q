let typeModalInstance;
let currentTypeId = null;
let allTypesData = [];
let filteredTypesData = [];
let currentPage = 1;
const rowsPerPage = 5;

document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('typeModal');
    if (modalElement) {
        typeModalInstance = new bootstrap.Modal(modalElement);
    }

    // قراءة البيانات من الـ Blade
    const rows = document.querySelectorAll('#typesTableBody tr.type-row');
    if (rows.length > 0) {
        rows.forEach(row => {
            const type = {
                id: parseInt(row.dataset.id, 10),
                name: row.querySelector('.row-name').innerText.trim(),
                code: row.querySelector('.row-code') ? row.querySelector('.row-code').innerText.trim() : null,
                is_active: row.querySelector('.row-status .badge').classList.contains('bg-success') ? 1 : 0
            };
            allTypesData.push(type);
        });
    } else {
        reloadTypesTable();
    }

    filteredTypesData = [...allTypesData];
    applyFiltersAndRender();
});

function openTypeModal() {
    document.getElementById('typeForm').reset();
    document.getElementById('typeID').value = '';
    currentTypeId = null;
    document.getElementById('typeModalLabel').innerText = 'إضافة نوع جديد';
    typeModalInstance.show();
}

function editType(btn) {
    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);
    const name = row.querySelector('.row-name').innerText.trim();
    const code = row.querySelector('.row-code') ? row.querySelector('.row-code').innerText.trim() : '';

    document.getElementById('typeID').value = id;
    document.getElementById('typeName').value = name;
    document.getElementById('typeCode').value = code;
    currentTypeId = id;
    document.getElementById('typeModalLabel').innerText = 'تعديل بيانات النوع';
    typeModalInstance.show();
}

function saveType() {
    const form = document.getElementById('typeForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('typeID').value;
    const name = document.getElementById('typeName').value.trim();
    const code = document.getElementById('typeCode').value.trim();

    const url = id ? `/setting/inventory/types/${id}` : '/setting/inventory/types';
    const formData = new FormData();
    formData.append('name', name);
    if (code) formData.append('code', code);
    if (id) formData.append('_method', 'PUT');

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
            showSystemToast(data.message || 'تم حفظ النوع بنجاح', 'success');
            typeModalInstance.hide();
            reloadTypesTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function deleteType(btn) {
    if (!confirm('هل أنت متأكد من حذف هذا النوع نهائياً؟')) return;

    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);

    const formData = new FormData();
    formData.append('_method', 'DELETE');

    fetch(`/setting/inventory/types/${id}`, {
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
            showSystemToast(data.message || 'تم حذف النوع بنجاح', 'success');
            reloadTypesTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function toggleTypeStatus(btn) {
    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);

    const formData = new FormData();
    formData.append('_method', 'PATCH');

    fetch(`/setting/inventory/types/${id}/toggle-status`, {
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
            showSystemToast(data.message || 'تم تغيير حالة النوع بنجاح', 'success');
            reloadTypesTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function reloadTypesTable() {
    fetch('/setting/inventory/types/list', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allTypesData = data.data || [];
                filteredTypesData = [...allTypesData];
                applyFiltersAndRender();
            } else {
                showSystemToast('حدث خطأ أثناء تحميل الأنواع', 'danger');
            }
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast('حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function filterTypes() {
    const searchText = document.getElementById('searchTypeInput').value.toLowerCase().trim();
    if (!searchText) {
        filteredTypesData = [...allTypesData];
    } else {
        filteredTypesData = allTypesData.filter(type =>
            type.name.toLowerCase().includes(searchText)
        );
    }
    currentPage = 1;
    applyFiltersAndRender();
}

function applyFiltersAndRender() {
    renderTypes(filteredTypesData);
    updateTypesCount(filteredTypesData.length);
    renderPagination(filteredTypesData.length);
}

function renderTypes(types) {
    const tbody = document.getElementById('typesTableBody');
    if (!tbody) return;

    const start = (currentPage - 1) * rowsPerPage;
    const end = Math.min(start + rowsPerPage, types.length);
    const pageTypes = types.slice(start, end);

    if (pageTypes.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyTypeRow">
                <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-tags fs-2 d-block mb-2"></i>
                    لا توجد أنواع مسجلة
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    pageTypes.forEach((type, index) => {
        const serial = start + index + 1;
        const isActive = type.is_active == 1;
        const statusBadge = isActive
            ? '<span class="badge bg-success">نشط</span>'
            : '<span class="badge bg-danger">غير نشط</span>';

        html += `
            <tr class="type-row text-center" data-id="${type.id}">
                <td>${serial}</td>
                <td class="row-name">${escapeHtml(type.name)}</td>
                <td class="row-code">${type.code ?? '---'}</td>
                <td class="row-status">${statusBadge}</td>
                <td class="no-print">
                    <div class="btn-action-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editType(this)" title="تعديل">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ${isActive ? 'btn-toggle-on' : 'btn-toggle-off'}" onclick="toggleTypeStatus(this)" title="${isActive ? 'تعطيل' : 'تفعيل'}">
                            <i class="bi ${isActive ? 'bi-toggle-on' : 'bi-toggle-off'}"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteType(this)" title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function updateTypesCount(count) {
    const badge = document.getElementById('typesCountBadge');
    if (badge) {
        badge.innerText = count;
    }
}

function renderPagination(totalItems) {
    const paginationList = document.getElementById('typesPaginationList');
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

document.addEventListener('click', function (e) {
    const target = e.target.closest('#typesPaginationList .page-link');
    if (!target) return;

    const page = parseInt(target.dataset.page, 10);
    if (!page || page < 1) return;

    const totalPages = Math.ceil(filteredTypesData.length / rowsPerPage);
    if (page > totalPages) return;

    currentPage = page;
    renderTypes(filteredTypesData);
    renderPagination(filteredTypesData.length);
});

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

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
                th, td { border: 1px solid #000; padding: 8px; }
                th { background-color: #f8f9fa; }
                .no-print { display: none !important; }
                .btn { display: none; }
            </style>
        </head>
        <body>
            <h2>قائمة أنواع الأصناف</h2>
            <table>
                <thead>${table.querySelector('thead').innerHTML}</thead>
                <tbody>
    `;

    const allRows = document.querySelectorAll('#typesTableBody tr.type-row');
    allRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        let rowHtml = '<tr>';
        for (let i = 0; i < Math.min(cells.length - 1, 4); i++) {
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