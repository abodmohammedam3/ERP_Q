/* =========================================================
   شاشة أنواع الأصناف
========================================================= */

let typeModalInstance;
let currentTypeId = null;
let allTypesData = [];
let filteredTypesData = [];
let currentPage = 1;
const rowsPerPage = 5;

const typeApi = {
    list: '/setting/inventory/types/list',
    store: '/setting/inventory/types',
    update: (id) => `/setting/inventory/types/${id}`,
    destroy: (id) => `/setting/inventory/types/${id}`,
    toggle: (id) => `/setting/inventory/types/${id}/toggle-status`,
};

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') || '';

/* =========================================================
   التهيئة الأولية
========================================================= */

document.addEventListener('DOMContentLoaded', function () {

    const modalElement = document.getElementById('typeModal');
    if (modalElement) {
        typeModalInstance = new bootstrap.Modal(modalElement);
    }

    /*
     * قراءة البيانات الأولية من الجدول
     * (يعتمد على data-* الموجودة في Blade)
     */
    const rows =
        document.querySelectorAll('#typesTableBody tr.type-row');

    if (rows.length > 0) {

        rows.forEach(row => {

            allTypesData.push({
                id: parseInt(row.dataset.id, 10),
                name: row.dataset.name || '',
                code: row.dataset.code || null,
                is_active: row.dataset.active === '1' ? 1 : 0,
            });
        });

    } else {

        reloadTypesTable();
        return;
    }

    filteredTypesData = [...allTypesData];
    applyFiltersAndRender();
});

/* =========================================================
   عرض الجدول
========================================================= */

function renderTypes(types) {

    const tbody =
        document.getElementById('typesTableBody');

    if (!tbody) return;

    tbody.innerHTML = '';

    const start = (currentPage - 1) * rowsPerPage;
    const end = Math.min(start + rowsPerPage, types.length);
    const pageTypes = types.slice(start, end);

    /* ---------- حالة: لا توجد بيانات ---------- */
    if (pageTypes.length === 0) {

        const emptyTpl =
            document.getElementById('emptyTypeRowTemplate');

        if (emptyTpl) {
            tbody.appendChild(
                emptyTpl.content.cloneNode(true)
            );
        }

        return;
    }

    /* ---------- حالة: يوجد بيانات ---------- */
    const rowTpl =
        document.getElementById('typeRowTemplate');

    const codeBadgeTpl =
        document.getElementById('typeCodeBadgeTemplate');

    const codeDashTpl =
        document.getElementById('typeCodeDashTemplate');

    if (!rowTpl) return;

    const fragment =
        document.createDocumentFragment();

    pageTypes.forEach((type, index) => {

        const serial = start + index + 1;
        const isActive = type.is_active == 1;

        const rowFragment =
            rowTpl.content.cloneNode(true);

        const row =
            rowFragment.querySelector('tr');

        /* بيانات الصف (data-*) */
        row.dataset.id = type.id;
        row.dataset.name = type.name;
        row.dataset.code = type.code ?? '';
        row.dataset.active = isActive ? 1 : 0;

        /* الرقم التسلسلي واسم النوع */
        row.querySelector('.row-index').textContent = serial;
        row.querySelector('.row-name').textContent = type.name;

        /* خلية الرمز */
        const codeCell =
            row.querySelector('.row-code');

        if (type.code && codeBadgeTpl) {

            const badgeFragment =
                codeBadgeTpl.content.cloneNode(true);

            badgeFragment.querySelector('span')
                .textContent = type.code;

            codeCell.appendChild(badgeFragment);

        } else if (codeDashTpl) {

            codeCell.appendChild(
                codeDashTpl.content.cloneNode(true)
            );
        }

        /* زر الحالة */
        const statusBtn =
            row.querySelector('.toggle-status-btn');

        statusBtn.classList.add(
            isActive ? 'btn-success' : 'btn-secondary'
        );

        statusBtn.textContent =
            isActive ? 'نشط' : 'غير نشط';

        statusBtn.title =
            isActive ? 'تعطيل' : 'تفعيل';

        fragment.appendChild(rowFragment);
    });

    tbody.appendChild(fragment);
}

/* =========================================================
   تحديث عدد الأنواع
========================================================= */

function updateTypesCount(count) {

    const badge =
        document.getElementById('typesCountBadge');

    if (badge) {
        badge.innerText = count;
    }
}

/* =========================================================
   الترقيم
========================================================= */

function renderPagination(totalItems) {

    const paginationList =
        document.getElementById('typesPaginationList');

    if (!paginationList) return;

    paginationList.innerHTML = '';

    const totalPages =
        Math.ceil(totalItems / rowsPerPage);

    if (totalPages <= 1) return;

    const itemTpl =
        document.getElementById('paginationItemTemplate');

    const prevTpl =
        document.getElementById('paginationPrevTemplate');

    const nextTpl =
        document.getElementById('paginationNextTemplate');

    const fragment =
        document.createDocumentFragment();

    /* ---------- زر السابق ---------- */
    if (prevTpl) {

        const prevFragment =
            prevTpl.content.cloneNode(true);

        const prevLi =
            prevFragment.querySelector('.page-item');

        const prevBtn =
            prevFragment.querySelector('.page-link');

        prevBtn.dataset.page = currentPage - 1;

        if (currentPage === 1) {

            prevLi.classList.add('disabled');
            prevBtn.disabled = true;
        }

        fragment.appendChild(prevFragment);
    }

    /* ---------- أرقام الصفحات ---------- */
    if (itemTpl) {

        for (let page = 1; page <= totalPages; page++) {

            const pageFragment =
                itemTpl.content.cloneNode(true);

            const pageLi =
                pageFragment.querySelector('.page-item');

            const pageBtn =
                pageFragment.querySelector('.page-link');

            pageBtn.textContent = page;
            pageBtn.dataset.page = page;

            if (page === currentPage) {
                pageLi.classList.add('active');
            }

            fragment.appendChild(pageFragment);
        }
    }

    /* ---------- زر التالي ---------- */
    if (nextTpl) {

        const nextFragment =
            nextTpl.content.cloneNode(true);

        const nextLi =
            nextFragment.querySelector('.page-item');

        const nextBtn =
            nextFragment.querySelector('.page-link');

        nextBtn.dataset.page = currentPage + 1;

        if (currentPage === totalPages) {

            nextLi.classList.add('disabled');
            nextBtn.disabled = true;
        }

        fragment.appendChild(nextFragment);
    }

    paginationList.appendChild(fragment);
}

/* =========================================================
   البحث والتصفية
========================================================= */

function filterTypes() {

    const searchText =
        document
            .getElementById('searchTypeInput')
            ?.value
            .toLowerCase()
            .trim() || '';

    if (!searchText) {

        filteredTypesData = [...allTypesData];

    } else {

        filteredTypesData =
            allTypesData.filter(type =>
                (type.name || '')
                    .toLowerCase()
                    .includes(searchText)
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

/* =========================================================
   مودال الإضافة/التعديل
========================================================= */

function openTypeModal() {

    document.getElementById('typeForm').reset();
    document.getElementById('typeID').value = '';

    currentTypeId = null;

    document.getElementById('typeModalLabel').innerText =
        'إضافة نوع جديد';

    typeModalInstance?.show();
}

/* =========================================================
   تعديل
========================================================= */

function editType(btn) {

    const row = btn.closest('tr');
    if (!row) return;

    const id = parseInt(row.dataset.id, 10);
    const name = row.dataset.name || '';
    const code = row.dataset.code || '';

    document.getElementById('typeID').value = id;
    document.getElementById('typeName').value = name;
    document.getElementById('typeCode').value = code;

    currentTypeId = id;

    document.getElementById('typeModalLabel').innerText =
        'تعديل بيانات النوع';

    typeModalInstance?.show();
}

/* =========================================================
   حفظ
========================================================= */

function saveType() {

    const form = document.getElementById('typeForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const id = document.getElementById('typeID').value;
    const name = document.getElementById('typeName').value.trim();
    const code = document.getElementById('typeCode').value.trim();

    const url =
        id
            ? typeApi.update(id)
            : typeApi.store;

    const formData = new FormData();
    formData.append('name', name);
    if (code) formData.append('code', code);
    if (id) formData.append('_method', 'PUT');

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: formData,
    })
        .then(response =>
            response
                .json()
                .then(data => ({ status: response.status, data }))
        )
        .then(({ status, data }) => {

            if (status !== 200 || !data.success) {
                throw new Error(
                    data.message || 'حدث خطأ غير معروف'
                );
            }

            showSystemToast(
                data.message || 'تم حفظ النوع بنجاح',
                'success'
            );

            typeModalInstance?.hide();
            reloadTypesTable();
        })
        .catch(error => {

            console.error('خطأ:', error);

            showSystemToast(
                error.message || 'حدث خطأ في الاتصال بالخادم',
                'danger'
            );
        });
}

/* =========================================================
   حذف
========================================================= */

function deleteType(btn) {

    if (!confirm('هل أنت متأكد من حذف هذا النوع نهائياً؟')) {
        return;
    }

    const row = btn.closest('tr');
    if (!row) return;

    const id = parseInt(row.dataset.id, 10);

    const formData = new FormData();
    formData.append('_method', 'DELETE');

    fetch(typeApi.destroy(id), {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: formData,
    })
        .then(response =>
            response
                .json()
                .then(data => ({ status: response.status, data }))
        )
        .then(({ status, data }) => {

            if (status !== 200 || !data.success) {
                throw new Error(
                    data.message || 'حدث خطأ غير معروف'
                );
            }

            showSystemToast(
                data.message || 'تم حذف النوع بنجاح',
                'success'
            );

            reloadTypesTable();
        })
        .catch(error => {

            console.error('خطأ:', error);

            showSystemToast(
                error.message || 'حدث خطأ في الاتصال بالخادم',
                'danger'
            );
        });
}

/* =========================================================
   تبديل الحالة
========================================================= */

function toggleTypeStatus(btn) {

    const row = btn.closest('tr');
    if (!row) return;

    const id = parseInt(row.dataset.id, 10);

    const formData = new FormData();
    formData.append('_method', 'PATCH');

    fetch(typeApi.toggle(id), {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: formData,
    })
        .then(response =>
            response
                .json()
                .then(data => ({ status: response.status, data }))
        )
        .then(({ status, data }) => {

            if (status !== 200 || !data.success) {
                throw new Error(
                    data.message || 'حدث خطأ غير معروف'
                );
            }

            showSystemToast(
                data.message || 'تم تغيير حالة النوع بنجاح',
                'success'
            );

            reloadTypesTable();
        })
        .catch(error => {

            console.error('خطأ:', error);

            showSystemToast(
                error.message || 'حدث خطأ في الاتصال بالخادم',
                'danger'
            );
        });
}

/* =========================================================
   إعادة تحميل الجدول من الخادم
========================================================= */

function reloadTypesTable() {

    fetch(typeApi.list, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then(response => response.json())
        .then(data => {

            if (data.success) {

                allTypesData = data.data || [];
                filteredTypesData = [...allTypesData];

                currentPage = 1;
                applyFiltersAndRender();

            } else {

                showSystemToast(
                    'حدث خطأ أثناء تحميل الأنواع',
                    'danger'
                );
            }
        })
        .catch(error => {

            console.error('خطأ:', error);

            showSystemToast(
                'حدث خطأ في الاتصال بالخادم',
                'danger'
            );
        });
}

/* =========================================================
   أحداث الترقيم (Delegation)
========================================================= */

document.addEventListener('click', function (e) {

    const target =
        e.target.closest('#typesPaginationList .page-link');

    if (!target) return;

    const page = parseInt(target.dataset.page, 10);
    if (!page || page < 1) return;

    const totalPages =
        Math.ceil(filteredTypesData.length / rowsPerPage);

    if (page > totalPages) return;

    currentPage = page;

    renderTypes(filteredTypesData);
    renderPagination(filteredTypesData.length);
});

/* =========================================================
   طباعة
========================================================= */

function printTypes() {

    const table = document.getElementById('typesTable');
    if (!table) return;

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

    const allRows =
        document.querySelectorAll('#typesTableBody tr.type-row');

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

/* =========================================================
   Fallback للتنبيهات
========================================================= */

if (typeof showSystemToast !== 'function') {

    window.showSystemToast = function (message) {
        alert(message);
    };
}