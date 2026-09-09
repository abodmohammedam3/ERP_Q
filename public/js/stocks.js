let stockModalInstance;
let currentStockId = null;
let allStocksData = [];
let filteredStocksData = [];
let currentPage = 1;
const rowsPerPage = 5;
let deletingStockId = null;
let nextAccountCode = null;

document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('stockModal');
    if (modalElement) {
        stockModalInstance = new bootstrap.Modal(modalElement);
    }

    const rows = document.querySelectorAll('#stocksTableBody tr.stock-row');
    if (rows.length > 0) {
        rows.forEach(row => {
            const stock = {
                StockID: parseInt(row.dataset.id, 10),
                StockName: row.querySelector('.row-name').innerText.trim(),
                accountDisplay: row.querySelector('.row-account') ? row.querySelector('.row-account').innerText.trim() : '',
                is_active: row.querySelector('.row-status .toggle-status-btn')?.classList.contains('btn-success') ? 1 : 0
            };
            allStocksData.push(stock);
        });
    } else {
        reloadStocksTable();
    }

    filteredStocksData = [...allStocksData];
    applyFiltersAndRender();

    const cancelBtn = document.getElementById('deleteCancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeDeleteModal);
    }
    const confirmBtn = document.getElementById('deleteConfirmBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', confirmDeleteStock);
    }
    const overlay = document.getElementById('deleteConfirmModal');
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });
    }
});

function openStockModal() {
    document.getElementById('stockForm').reset();
    document.getElementById('stockID').value = '';
    document.getElementById('accountDisplay').value = '';
    currentStockId = null;
    document.getElementById('stockModalLabel').innerText = 'إضافة مخزن جديد';

    // جلب رقم الحساب التالي من الخادم
    fetch('/setting/inventory/warehouses/next-code', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('accountDisplay').value = data.code;
                nextAccountCode = data.code;
            } else {
                showSystemToast(data.message || 'تعذر الحصول على رقم الحساب التالي', 'danger');
            }
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast('حدث خطأ في الاتصال بالخادم', 'danger');
        });

    stockModalInstance.show();
}

function editStock(btn) {
    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);
    const name = row.querySelector('.row-name').innerText.trim();
    const accountDisplay = row.querySelector('.row-account') ? row.querySelector('.row-account').innerText.trim() : '';

    document.getElementById('stockID').value = id;
    document.getElementById('stockName').value = name;
    document.getElementById('accountDisplay').value = accountDisplay;
    currentStockId = id;
    document.getElementById('stockModalLabel').innerText = 'تعديل بيانات المخزن';
    stockModalInstance.show();
}

function saveStock() {
    const form = document.getElementById('stockForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('stockID').value;
    const name = document.getElementById('stockName').value.trim();

    const url = id ? `/setting/inventory/warehouses/${id}` : '/setting/inventory/warehouses';
    const formData = new FormData();
    formData.append('StockName', name);
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
            showSystemToast(data.message || 'تم حفظ المخزن بنجاح', 'success');
            stockModalInstance.hide();
            reloadStocksTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function deleteStock(btn) {
    const row = btn.closest('tr');
    deletingStockId = parseInt(row.dataset.id, 10);
    document.getElementById('deleteConfirmModal').classList.add('show');
}

function confirmDeleteStock() {
    if (!deletingStockId) return;

    const id = deletingStockId;
    const formData = new FormData();
    formData.append('_method', 'DELETE');

    fetch(`/setting/inventory/warehouses/${id}`, {
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
            showSystemToast(data.message || 'تم حذف المخزن بنجاح', 'success');
            closeDeleteModal();
            reloadStocksTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
            closeDeleteModal();
        });
}

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').classList.remove('show');
    deletingStockId = null;
}

function toggleStockStatus(btn) {
    const row = btn.closest('tr');
    const id = parseInt(row.dataset.id, 10);

    const formData = new FormData();
    formData.append('_method', 'PATCH');

    fetch(`/setting/inventory/warehouses/${id}/toggle-status`, {
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
            showSystemToast(data.message || 'تم تغيير حالة المخزن بنجاح', 'success');
            reloadStocksTable();
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast(error.message || 'حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function reloadStocksTable() {
    fetch('/setting/inventory/warehouses/list', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allStocksData = data.data || [];
                filteredStocksData = [...allStocksData];
                applyFiltersAndRender();
            } else {
                showSystemToast('حدث خطأ أثناء تحميل المخازن', 'danger');
            }
        })
        .catch(error => {
            console.error('خطأ:', error);
            showSystemToast('حدث خطأ في الاتصال بالخادم', 'danger');
        });
}

function filterStocks() {
    const searchText = document.getElementById('searchStockInput').value.toLowerCase().trim();
    if (!searchText) {
        filteredStocksData = [...allStocksData];
    } else {
        filteredStocksData = allStocksData.filter(stock =>
            stock.StockName.toLowerCase().includes(searchText) ||
            (stock.accountDisplay && stock.accountDisplay.toString().includes(searchText))
        );
    }
    currentPage = 1;
    applyFiltersAndRender();
}

function applyFiltersAndRender() {
    renderStocks(filteredStocksData);
    updateStocksCount(filteredStocksData.length);
    renderPagination(filteredStocksData.length);
}

function renderStocks(stocks) {
    const tbody = document.getElementById('stocksTableBody');
    if (!tbody) return;

    const start = (currentPage - 1) * rowsPerPage;
    const end = Math.min(start + rowsPerPage, stocks.length);
    const pageStocks = stocks.slice(start, end);

    if (pageStocks.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyStockRow">
                <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-building fs-2 d-block mb-2"></i>
                    لا توجد مخازن مسجلة
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    pageStocks.forEach((stock, index) => {
        const serial = start + index + 1;
        const isActive = stock.is_active == 1;
        const statusBtnClass = isActive ? 'btn-success' : 'btn-secondary';
        const statusText = isActive ? 'نشط' : 'غير نشط';
        const statusTitle = isActive ? 'تعطيل' : 'تفعيل';

        html += `
            <tr class="stock-row text-center" data-id="${stock.StockID}">
                <td>${serial}</td>
                <td class="row-name">${escapeHtml(stock.StockName)}</td>
                <td class="row-account">${stock.accountDisplay || '---'}</td>
                <td class="row-status">
                    <button type="button" class="btn btn-sm ${statusBtnClass} toggle-status-btn" onclick="toggleStockStatus(this)" title="${statusTitle}">
                        ${statusText}
                    </button>
                </td>
                <td class="no-print">
                    <div class="btn-action-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editStock(this)" title="تعديل">
                            <i class="bi bi-pencil d-md-none"></i>
                            <span class="d-none d-md-inline">تعديل</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteStock(this)" title="حذف">
                            <i class="bi bi-trash d-md-none"></i>
                            <span class="d-none d-md-inline">حذف</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function updateStocksCount(count) {
    const badge = document.getElementById('stocksCountBadge');
    if (badge) badge.innerText = count;
}

function renderPagination(totalItems) {
    const paginationList = document.getElementById('stocksPaginationList');
    if (!paginationList) return;
    const totalPages = Math.ceil(totalItems / rowsPerPage);
    if (totalPages <= 1) { paginationList.innerHTML = ''; return; }

    let html = '';
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button></li>`;
    for (let page = 1; page <= totalPages; page++) {
        html += `<li class="page-item ${page === currentPage ? 'active' : ''}"><button type="button" class="page-link" data-page="${page}">${page}</button></li>`;
    }
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button></li>`;
    paginationList.innerHTML = html;
}

document.addEventListener('click', function (e) {
    const target = e.target.closest('#stocksPaginationList .page-link');
    if (!target) return;
    const page = parseInt(target.dataset.page, 10);
    if (!page || page < 1) return;
    const totalPages = Math.ceil(filteredStocksData.length / rowsPerPage);
    if (page > totalPages) return;
    currentPage = page;
    renderStocks(filteredStocksData);
    renderPagination(filteredStocksData.length);
});

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function printStocks() {
    const table = document.getElementById('stocksTable');
    let printContents = `
        <html dir="rtl" lang="ar">
        <head>
            <title>طباعة المخازن</title>
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
            <h2>قائمة المخازن</h2>
            <table>
                <thead>${table.querySelector('thead').innerHTML}</thead>
                <tbody>
    `;
    const allRows = document.querySelectorAll('#stocksTableBody tr.stock-row');
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
    if (!printWindow) {
        alert('تعذر فتح نافذة الطباعة. يرجى السماح بالنوافذ المنبثقة.');
        return;
    }
    printWindow.document.write(printContents);
    printWindow.document.close();
    setTimeout(() => { printWindow.print(); printWindow.close(); }, 250);
}

if (typeof showSystemToast !== 'function') {
    window.showSystemToast = function (message, type) {
        alert(message);
    };
}