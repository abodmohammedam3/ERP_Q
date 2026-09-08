let unitModalInstance;
let currentUnitId = null;
let allUnitsData = [];
let filteredUnitsData = [];
let currentPage = 1;
const rowsPerPage = 5;

document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('unitModal');
    if (modalElement) {
        unitModalInstance = new bootstrap.Modal(modalElement);
    }

    // قراءة البيانات من الـ Blade
    const rows = document.querySelectorAll('#unitsTableBody tr.unit-row');
    if (rows.length > 0) {
        rows.forEach(row => {
            const unit = {
                UnitID: parseInt(row.dataset.id, 10),
                UnitName: row.querySelector('.row-name').innerText.trim(),
                is_active: row.querySelector('.row-status .badge').classList.contains('bg-success') ? 1 : 0
            };
            allUnitsData.push(unit);
        });
    } else {
        reloadUnitsTable();
    }

    filteredUnitsData = [...allUnitsData];
    applyFiltersAndRender();

    // =========================================================
    // أحداث نافذة تأكيد الحذف
    // =========================================================
    const cancelBtn = document.getElementById('deleteCancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeDeleteModal);
    }

    const confirmBtn = document.getElementById('deleteConfirmBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', confirmDeleteUnit);
    }

    const overlay = document.getElementById('deleteConfirmModal');
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    }
});

// =========================================================
// فتح مودال الإضافة
// =========================================================
function openUnitModal() {
    document.getElementById('unitForm').reset();
    document.getElementById('unitID').value = '';
    currentUnitId = null;
    document.getElementById('unitModalLabel').innerText = 'إضافة وحدة جديدة';
    unitModalInstance.show();
}

// =========================================================
// تعديل وحدة
// =========================================================
function editUnit(btn) {
    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);
    const name = row.querySelector('.row-name').innerText.trim();

    document.getElementById('unitID').value = id;
    document.getElementById('unitName').value = name;
    currentUnitId = id;
    document.getElementById('unitModalLabel').innerText = 'تعديل بيانات الوحدة';
    unitModalInstance.show();
}

// =========================================================
// حفظ الوحدة (إضافة / تعديل)
// =========================================================
function saveUnit() {
    const form = document.getElementById('unitForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('unitID').value;
    const name = document.getElementById('unitName').value.trim();

    const url = id ? `/setting/inventory/units/${id}` : '/setting/inventory/units';
    const formData = new FormData();
    formData.append('UnitName', name);
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
            showSystemToast(data.message || 'تم حفظ الوحدة بنجاح', 'success');
            unitModalInstance.hide();
            reloadUnitsTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

// =========================================================
// حذف وحدة (فتح نافذة التأكيد)
// =========================================================
let deletingUnitId = null;

function deleteUnit(btn) {
    const row = btn.closest('tr');
    deletingUnitId = parseInt(row.dataset.id, 10);
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.classList.add('show');
    }
}

function confirmDeleteUnit() {
    if (!deletingUnitId) return;

    const id = deletingUnitId;
    const formData = new FormData();
    formData.append('_method', 'DELETE');

    fetch(`/setting/inventory/units/${id}`, {
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
            showSystemToast(data.message || 'تم حذف الوحدة بنجاح', 'success');
            closeDeleteModal();
            reloadUnitsTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
            closeDeleteModal();
        });
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.classList.remove('show');
    }
    deletingUnitId = null;
}

// =========================================================
// تبديل حالة الوحدة
// =========================================================
function toggleUnitStatus(btn) {
    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);

    const formData = new FormData();
    formData.append('_method', 'PATCH');

    fetch(`/setting/inventory/units/${id}/toggle-status`, {
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
            showSystemToast(data.message || 'تم تغيير حالة الوحدة بنجاح', 'success');
            reloadUnitsTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

// =========================================================
// تحميل البيانات من الخادم
// =========================================================
function reloadUnitsTable() {
    fetch('/setting/inventory/units/list', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allUnitsData = data.data || [];
                filteredUnitsData = [...allUnitsData];
                applyFiltersAndRender();
            } else {
                showSystemToast('حدث خطأ أثناء تحميل الوحدات', 'danger');
            }
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast('حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

// =========================================================
// البحث (على العميل)
// =========================================================
function filterUnits() {
    const searchText = document.getElementById('searchUnitInput').value.toLowerCase().trim();
    if (!searchText) {
        filteredUnitsData = [...allUnitsData];
    } else {
        filteredUnitsData = allUnitsData.filter(unit =>
            unit.UnitName.toLowerCase().includes(searchText)
        );
    }
    currentPage = 1;
    applyFiltersAndRender();
}

function applyFiltersAndRender() {
    renderUnits(filteredUnitsData);
    updateUnitsCount(filteredUnitsData.length);
    renderPagination(filteredUnitsData.length);
}

// =========================================================
// عرض الوحدات
// =========================================================
function renderUnits(units) {
    const tbody = document.getElementById('unitsTableBody');
    if (!tbody) return;

    const start = (currentPage - 1) * rowsPerPage;
    const end = Math.min(start + rowsPerPage, units.length);
    const pageUnits = units.slice(start, end);

    if (pageUnits.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyUnitRow">
                <td colspan="4" class="text-center text-muted py-5">
                    <i class="bi bi-rulers fs-2 d-block mb-2"></i>
                    لا توجد وحدات مسجلة
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    pageUnits.forEach((unit, index) => {
        const serial = start + index + 1;
        const isActive = unit.is_active == 1;
        const statusBadge = isActive
            ? '<span class="badge bg-success">نشط</span>'
            : '<span class="badge bg-danger">غير نشط</span>';

        html += `
            <tr class="unit-row text-center" data-id="${unit.UnitID}">
                <td>${serial}</td>
                <td class="row-name">${escapeHtml(unit.UnitName)}</td>
                <td class="row-status">${statusBadge}</td>
                <td class="no-print">
                    <div class="btn-action-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editUnit(this)" title="تعديل">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ${isActive ? 'btn-toggle-on' : 'btn-toggle-off'}" onclick="toggleUnitStatus(this)" title="${isActive ? 'تعطيل' : 'تفعيل'}">
                            <i class="bi ${isActive ? 'bi-toggle-on' : 'bi-toggle-off'}"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteUnit(this)" title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// =========================================================
// تحديث العداد
// =========================================================
function updateUnitsCount(count) {
    const badge = document.getElementById('unitsCountBadge');
    if (badge) {
        badge.innerText = count;
    }
}

// =========================================================
// Pagination
// =========================================================
function renderPagination(totalItems) {
    const paginationList = document.getElementById('unitsPaginationList');
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
    const target = e.target.closest('#unitsPaginationList .page-link');
    if (!target) return;

    const page = parseInt(target.dataset.page, 10);
    if (!page || page < 1) return;

    const totalPages = Math.ceil(filteredUnitsData.length / rowsPerPage);
    if (page > totalPages) return;

    currentPage = page;
    renderUnits(filteredUnitsData);
    renderPagination(filteredUnitsData.length);
});

// =========================================================
// أدوات مساعدة
// =========================================================
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

// =========================================================
// طباعة
// =========================================================
function printUnits() {
    const table = document.getElementById('unitsTable');
    let printContents = `
        <html dir="rtl" lang="ar">
        <head>
            <title>طباعة قائمة الوحدات</title>
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
            <h2>قائمة وحدات القياس</h2>
            <table>
                <thead>${table.querySelector('thead').innerHTML}</thead>
                <tbody>
    `;

    const allRows = document.querySelectorAll('#unitsTableBody tr.unit-row');
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

// =========================================================
// دالة احتياطية لـ showSystemToast
// =========================================================
if (typeof showSystemToast !== 'function') {
    window.showSystemToast = function (message, type) {
        alert(message);
    };
}