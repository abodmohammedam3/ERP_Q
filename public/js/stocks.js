console.log('===== stocks.js تم تحميله =====');
let stockModalInstance;
let currentStockId = null;

let allStocksData = [];
let filteredStocksData = [];

let currentPage = 1;

const rowsPerPage = 5;

let deletingStockId = null;
let nextAccountCode = null;

let stockRowTemplate = null;


/*
 * =========================================================
 * عند تحميل الصفحة
 * =========================================================
 */

document.addEventListener('DOMContentLoaded', function () {

    const modalElement =
        document.getElementById('stockModal');

    if (modalElement) {

        stockModalInstance =
            new bootstrap.Modal(modalElement);
    }


    /*
     * تحميل قالب الصف مرة واحدة
     */

    const template =
        document.getElementById('stockRowTemplate');

    if (template) {

        const row =
            template.content.firstElementChild;

        if (row) {

            stockRowTemplate =
                row.cloneNode(true);
        }
    }


    /*
     * تحميل البيانات من الخادم
     *
     * مهم:
     * لا نقرأ صفوف Blade القديمة.
     */

    reloadStocksTable();


    /*
     * أزرار الحذف
     */

    const cancelBtn =
        document.getElementById(
            'deleteCancelBtn'
        );

    if (cancelBtn) {

        cancelBtn.addEventListener(
            'click',
            closeDeleteModal
        );
    }


    const confirmBtn =
        document.getElementById(
            'deleteConfirmBtn'
        );

    if (confirmBtn) {

        confirmBtn.addEventListener(
            'click',
            confirmDeleteStock
        );
    }


    const overlay =
        document.getElementById(
            'deleteConfirmModal'
        );

    if (overlay) {

        overlay.addEventListener(
            'click',
            function (e) {

                if (e.target === this) {

                    closeDeleteModal();
                }
            }
        );
    }

});


/*
 * =========================================================
 * إعادة تحميل البيانات
 * =========================================================
 */

function reloadStocksTable() {
   

    fetch(
        '/setting/inventory/warehouses/list',
        {
            method: 'GET',

            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }
    )

    .then(response => {

        if (!response.ok) {

            throw new Error(
                'فشل تحميل بيانات المخازن'
            );
        }

        return response.json();
    })

    .then(data => {

    console.log('DATA FROM SERVER:', data.data);
    console.log('DATA LENGTH:', data.data?.length);
    console.table(data.data);

    if (!data.success) {

            throw new Error(
                data.message ||
                'حدث خطأ أثناء تحميل المخازن'
            );
        }


        /*
         * مصدر البيانات الوحيد
         */

        allStocksData =
            Array.isArray(data.data)
                ? data.data
                : [];


        filteredStocksData =
            [...allStocksData];


        /*
         * دائماً نبدأ من الصفحة الأولى
         */

        currentPage = 1;


        /*
         * دالة العرض المركزية
         */

        displayStocks();

    })

    .catch(error => {

        console.error(
            'reloadStocksTable:',
            error
        );

        showSystemToast(
            error.message ||
            'حدث خطأ في الاتصال بالخادم',
            'danger'
        );

    });

}


/*
 * =========================================================
 * دالة العرض المركزية
 *
 * تستدعى عند:
 *
 * 1. فتح الصفحة
 * 2. البحث
 * 3. الإضافة
 * 4. التعديل
 * 5. الحذف
 * 6. تغيير الحالة
 * 7. Pagination
 * =========================================================
 */

function displayStocks() {

    const tbody =
        document.getElementById(
            'stocksTableBody'
        );

    if (!tbody) {
        return;
    }


    /*
     * -----------------------------------------------------
     * حذف كل الصفوف المعروضة سابقاً
     * -----------------------------------------------------
     */

    tbody
        .querySelectorAll(
            'tr.stock-row'
        )
        .forEach(row => {

            row.remove();

        });


    /*
     * -----------------------------------------------------
     * حذف رسالة البيانات الفارغة القديمة
     * -----------------------------------------------------
     */

    const oldEmptyRow =
        document.getElementById(
            'emptyStockRow'
        );

    if (oldEmptyRow) {

        oldEmptyRow.remove();
    }


    /*
     * -----------------------------------------------------
     * حساب Pagination
     * -----------------------------------------------------
     */

    const totalItems =
        filteredStocksData.length;

    const totalPages =
        Math.ceil(
            totalItems /
            rowsPerPage
        );


    if (totalPages > 0 &&
        currentPage > totalPages) {

        currentPage =
            totalPages;
    }


    if (currentPage < 1) {

        currentPage = 1;
    }


    const start =
        (currentPage - 1) *
        rowsPerPage;


    const end =
        start + rowsPerPage;


    const pageStocks =
        filteredStocksData.slice(
            start,
            end
        );


    /*
     * -----------------------------------------------------
     * لا توجد بيانات
     * -----------------------------------------------------
     */

    if (pageStocks.length === 0) {

        const emptyRow =
            document.createElement('tr');

        emptyRow.id =
            'emptyStockRow';


        const emptyCell =
            document.createElement('td');


        const columnCount =
            document.querySelectorAll(
                '#stocksTable thead th'
            ).length;


        emptyCell.colSpan =
            columnCount;


        emptyCell.className =
            'text-center text-muted py-5';


        const icon =
            document.createElement('i');

        icon.className =
            'bi bi-building fs-2 d-block mb-2';


        emptyCell.appendChild(icon);


        emptyCell.appendChild(
            document.createTextNode(
                'لا توجد مخازن مسجلة'
            )
        );


        emptyRow.appendChild(
            emptyCell
        );


        tbody.appendChild(
            emptyRow
        );


        updateStocksCount(
            totalItems
        );


        renderPagination(
            totalItems
        );


        return;
    }


    /*
     * -----------------------------------------------------
     * التأكد من وجود Template
     * -----------------------------------------------------
     */

    if (!stockRowTemplate) {

        const template =
            document.getElementById(
                'stockRowTemplate'
            );

        if (template) {

            const row =
                template.content
                    .firstElementChild;

            if (row) {

                stockRowTemplate =
                    row.cloneNode(true);
            }
        }
    }


    if (!stockRowTemplate) {

        console.error(
            'stockRowTemplate غير موجود'
        );

        return;
    }


    /*
     * -----------------------------------------------------
     * إنشاء الصفوف
     * -----------------------------------------------------
     */

    pageStocks.forEach(
        (stock, index) => {

            const row =
                stockRowTemplate
                    .cloneNode(true);


            /*
             * ID
             */

            row.dataset.id =
                stock.StockID;


            /*
             * الرقم
             */

            const numberCell =
                row.querySelector(
                    '.row-number'
                );

            if (numberCell) {

                numberCell.textContent =
                    start + index + 1;
            }


            /*
             * الاسم
             */

            const nameCell =
                row.querySelector(
                    '.row-name'
                );

            if (nameCell) {

                nameCell.textContent =
                    stock.StockName || '';
            }


            /*
             * الحساب
             */

            const accountCell =
                row.querySelector(
                    '.row-account'
                );

            if (accountCell) {

                accountCell.textContent =
                    stock.accountDisplay ||
                    '---';
            }


            /*
             * الحالة
             */

            const statusButton =
                row.querySelector(
                    '.toggle-status-btn'
                );


            if (statusButton) {

                const active =
                    Number(
                        stock.is_active
                    ) === 1;


                statusButton.classList.remove(
                    'btn-success',
                    'btn-secondary'
                );


                if (active) {

                    statusButton.classList.add(
                        'btn-success'
                    );

                    statusButton.textContent =
                        'نشط';

                    statusButton.title =
                        'تعطيل';

                } else {

                    statusButton.classList.add(
                        'btn-secondary'
                    );

                    statusButton.textContent =
                        'غير نشط';

                    statusButton.title =
                        'تفعيل';
                }
            }


            /*
             * إضافة الصف
             */

            tbody.appendChild(
                row
            );

        }
    );


    /*
     * -----------------------------------------------------
     * تحديث العدد
     * -----------------------------------------------------
     */

    updateStocksCount(
        totalItems
    );


    /*
     * -----------------------------------------------------
     * تحديث Pagination
     * -----------------------------------------------------
 */

    renderPagination(
        totalItems
    );

}


/*
 * =========================================================
 * البحث
 * =========================================================
 */

function filterStocks() {

    const searchInput =
        document.getElementById(
            'searchStockInput'
        );

    if (!searchInput) {
        return;
    }


    const searchText =
        searchInput.value
            .toLowerCase()
            .trim();


    if (!searchText) {

        filteredStocksData =
            [...allStocksData];

    } else {

        filteredStocksData =
            allStocksData.filter(
                stock => {

                    const stockName =
                        String(
                            stock.StockName || ''
                        ).toLowerCase();


                    const accountCode =
                        String(
                            stock.accountDisplay || ''
                        ).toLowerCase();


                    return (
                        stockName.includes(
                            searchText
                        ) ||
                        accountCode.includes(
                            searchText
                        )
                    );

                }
            );
    }


    currentPage = 1;


    /*
     * العرض المركزي
     */

    displayStocks();

}


/*
 * =========================================================
 * تحديث العدد
 * =========================================================
 */

function updateStocksCount(count) {

    const badge =
        document.getElementById(
            'stocksCountBadge'
        );

    if (badge) {

        badge.innerText =
            count;
    }

}


/*
 * =========================================================
 * Pagination
 * =========================================================
 */

function renderPagination(totalItems) {

    const paginationList =
        document.getElementById(
            'stocksPaginationList'
        );

    if (!paginationList) {
        return;
    }


    const totalPages =
        Math.ceil(
            totalItems /
            rowsPerPage
        );


    if (totalPages <= 1) {

        paginationList.innerHTML =
            '';

        return;
    }


    let html = '';


    /*
     * السابق
     */

    html += `
        <li class="page-item ${
            currentPage === 1
                ? 'disabled'
                : ''
        }">

            <button
                type="button"
                class="page-link"
                data-page="${currentPage - 1}"
                ${
                    currentPage === 1
                        ? 'disabled'
                        : ''
                }
            >

                <i class="bi bi-chevron-right"></i>

            </button>

        </li>
    `;


    /*
     * الصفحات
     */

    for (
        let page = 1;
        page <= totalPages;
        page++
    ) {

        html += `
            <li class="page-item ${
                page === currentPage
                    ? 'active'
                    : ''
            }">

                <button
                    type="button"
                    class="page-link"
                    data-page="${page}"
                >
                    ${page}
                </button>

            </li>
        `;
    }


    /*
     * التالي
     */

    html += `
        <li class="page-item ${
            currentPage === totalPages
                ? 'disabled'
                : ''
        }">

            <button
                type="button"
                class="page-link"
                data-page="${currentPage + 1}"
                ${
                    currentPage === totalPages
                        ? 'disabled'
                        : ''
                }
            >

                <i class="bi bi-chevron-left"></i>

            </button>

        </li>
    `;


    paginationList.innerHTML =
        html;

}


/*
 * =========================================================
 * حدث Pagination
 * =========================================================
 */

document.addEventListener(
    'click',
    function (e) {

        const button =
            e.target.closest(
                '#stocksPaginationList .page-link'
            );


        if (!button) {
            return;
        }


        const page =
            parseInt(
                button.dataset.page,
                10
            );


        if (!page || page < 1) {
            return;
        }


        const totalPages =
            Math.ceil(
                filteredStocksData.length /
                rowsPerPage
            );


        if (page > totalPages) {
            return;
        }


        currentPage =
            page;


        /*
         * العرض المركزي
         */

        displayStocks();

    }
);


/*
 * =========================================================
 * فتح الإضافة
 * =========================================================
 */

function openStockModal() {

    const form =
        document.getElementById(
            'stockForm'
        );


    if (form) {

        form.reset();
    }


    document.getElementById(
        'stockID'
    ).value = '';


    document.getElementById(
        'accountDisplay'
    ).value = '';


    currentStockId = null;


    document.getElementById(
        'stockModalLabel'
    ).innerText =
        'إضافة مخزن جديد';


    fetch(
        '/setting/inventory/warehouses/next-code',
        {
            method: 'GET',

            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }
    )

    .then(response => {

        if (!response.ok) {

            throw new Error(
                'تعذر الحصول على رقم الحساب التالي'
            );
        }

        return response.json();

    })

    .then(data => {

        if (data.success) {

            document.getElementById(
                'accountDisplay'
            ).value =
                data.code;

            nextAccountCode =
                data.code;

        } else {

            showSystemToast(
                data.message ||
                'تعذر الحصول على رقم الحساب التالي',
                'danger'
            );
        }

    })

    .catch(error => {

        console.error(
            'خطأ:',
            error
        );

        showSystemToast(
            error.message ||
            'حدث خطأ في الاتصال بالخادم',
            'danger'
        );

    });


    if (stockModalInstance) {

        stockModalInstance.show();
    }

}


/*
 * =========================================================
 * تعديل
 * =========================================================
 */

function editStock(btn) {

    const row =
        btn.closest(
            'tr.stock-row'
        );


    if (!row) {
        return;
    }


    const id =
        parseInt(
            row.dataset.id,
            10
        );


    const name =
        row.querySelector(
            '.row-name'
        )?.innerText.trim() || '';


    const account =
        row.querySelector(
            '.row-account'
        )?.innerText.trim() || '';


    document.getElementById(
        'stockID'
    ).value =
        id;


    document.getElementById(
        'stockName'
    ).value =
        name;


    document.getElementById(
        'accountDisplay'
    ).value =
        account;


    currentStockId =
        id;


    document.getElementById(
        'stockModalLabel'
    ).innerText =
        'تعديل بيانات المخزن';


    if (stockModalInstance) {

        stockModalInstance.show();
    }

}


/*
 * =========================================================
 * الحفظ
 * =========================================================
 */

function saveStock() {

    const form =
        document.getElementById(
            'stockForm'
        );


    if (!form) {
        return;
    }


    if (!form.checkValidity()) {

        form.reportValidity();

        return;
    }


    const id =
        document.getElementById(
            'stockID'
        ).value;


    const name =
        document.getElementById(
            'stockName'
        ).value.trim();


    /*
     * منع الحفظ إذا لم يتغير الاسم
     */

    if (id) {

        const row =
            document.querySelector(
                `#stocksTableBody tr.stock-row[data-id="${id}"]`
            );


        if (row) {

            const originalName =
                row.querySelector(
                    '.row-name'
                )?.innerText.trim() || '';


            if (
                name === originalName
            ) {

                showSystemToast(
                    'لم يتم إجراء أي تعديل على بيانات المخزن.',
                    'danger'
                );

                return;
            }
        }
    }


    const url =
        id
            ? `/setting/inventory/warehouses/${id}`
            : '/setting/inventory/warehouses';


    const formData =
        new FormData();


    formData.append(
        'StockName',
        name
    );


    if (id) {

        formData.append(
            '_method',
            'PUT'
        );
    }


    fetch(
        url,
        {
            method: 'POST',

            headers: {

                'X-CSRF-TOKEN':
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ).getAttribute('content'),

                'X-Requested-With':
                    'XMLHttpRequest',

                'Accept':
                    'application/json'
            },

            body: formData
        }
    )

    .then(response =>
        response.json()
            .then(data => ({
                status: response.status,
                data
            }))
    )

    .then(({status, data}) => {

        if (
            status !== 200 ||
            !data.success
        ) {

            throw new Error(
                data.message ||
                'حدث خطأ غير معروف'
            );
        }


        showSystemToast(
            data.message ||
            'تم حفظ المخزن بنجاح',
            'success'
        );


        if (stockModalInstance) {

            stockModalInstance.hide();
        }


        /*
         * reload
         * ↓
         * displayStocks
         */

        reloadStocksTable();

    })

    .catch(error => {

        console.error(
            'خطأ:',
            error
        );

        showSystemToast(
            error.message ||
            'حدث خطأ في الاتصال بالخادم',
            'danger'
        );

    });

}


/*
 * =========================================================
 * حذف
 * =========================================================
 */

function deleteStock(btn) {

    const row =
        btn.closest(
            'tr.stock-row'
        );


    if (!row) {
        return;
    }


    deletingStockId =
        parseInt(
            row.dataset.id,
            10
        );


    const modal =
        document.getElementById(
            'deleteConfirmModal'
        );


    if (modal) {

        modal.classList.add(
            'show'
        );
    }

}


/*
 * =========================================================
 * تأكيد الحذف
 * =========================================================
 */

function confirmDeleteStock() {

    if (!deletingStockId) {
        return;
    }


    const id =
        deletingStockId;


    const formData =
        new FormData();


    formData.append(
        '_method',
        'DELETE'
    );


    fetch(
        `/setting/inventory/warehouses/${id}`,
        {
            method: 'POST',

            headers: {

                'X-CSRF-TOKEN':
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ).getAttribute('content'),

                'X-Requested-With':
                    'XMLHttpRequest',

                'Accept':
                    'application/json'
            },

            body: formData
        }
    )

    .then(response =>
        response.json()
            .then(data => ({
                status: response.status,
                data
            }))
    )

    .then(({status, data}) => {

        if (
            status !== 200 ||
            !data.success
        ) {

            throw new Error(
                data.message ||
                'حدث خطأ غير معروف'
            );
        }


        showSystemToast(
            data.message ||
            'تم حذف المخزن بنجاح',
            'success'
        );


        closeDeleteModal();


        /*
         * reload
         * ↓
         * displayStocks
         */

        reloadStocksTable();

    })

    .catch(error => {

        console.error(
            'خطأ:',
            error
        );

        showSystemToast(
            error.message ||
            'حدث خطأ في الاتصال بالخادم',
            'danger'
        );

        closeDeleteModal();

    });

}


/*
 * =========================================================
 * إغلاق الحذف
 * =========================================================
 */

function closeDeleteModal() {

    const modal =
        document.getElementById(
            'deleteConfirmModal'
        );


    if (modal) {

        modal.classList.remove(
            'show'
        );
    }


    deletingStockId =
        null;

}


/*
 * =========================================================
 * تغيير الحالة
 * =========================================================
 */

function toggleStockStatus(btn) {

    const row =
        btn.closest(
            'tr.stock-row'
        );


    if (!row) {
        return;
    }


    const id =
        parseInt(
            row.dataset.id,
            10
        );


    const formData =
        new FormData();


    formData.append(
        '_method',
        'PATCH'
    );


    fetch(
        `/setting/inventory/warehouses/${id}/toggle-status`,
        {
            method: 'POST',

            headers: {

                'X-CSRF-TOKEN':
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ).getAttribute('content'),

                'X-Requested-With':
                    'XMLHttpRequest',

                'Accept':
                    'application/json'
            },

            body: formData
        }
    )

    .then(response =>
        response.json()
            .then(data => ({
                status: response.status,
                data
            }))
    )

    .then(({status, data}) => {

        if (
            status !== 200 ||
            !data.success
        ) {

            throw new Error(
                data.message ||
                'حدث خطأ غير معروف'
            );
        }


        showSystemToast(
            data.message ||
            'تم تغيير حالة المخزن بنجاح',
            'success'
        );


        /*
         * reload
         * ↓
         * displayStocks
         */

        reloadStocksTable();

    })

    .catch(error => {

        console.error(
            'خطأ:',
            error
        );

        showSystemToast(
            error.message ||
            'حدث خطأ في الاتصال بالخادم',
            'danger'
        );

    });

}


/*
 * =========================================================
 * escapeHtml
 * =========================================================
 */

function escapeHtml(text) {

    if (
        text === null ||
        text === undefined
    ) {

        return '';
    }


    const div =
        document.createElement(
            'div'
        );


    div.textContent =
        String(text);


    return div.innerHTML;

}


/*
 * =========================================================
 * الطباعة
 * =========================================================
 */

function printStocks() {

    const table =
        document.getElementById(
            'stocksTable'
        );


    if (!table) {
        return;
    }


    let printContents = `
        <html dir="rtl" lang="ar">

        <head>

            <title>طباعة المخازن</title>

            <style>

                body {
                    font-family: Arial, sans-serif;
                    padding: 20px;
                }

                h2 {
                    text-align: center;
                    margin-bottom: 20px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    text-align: center;
                }

                th,
                td {
                    border: 1px solid #000;
                    padding: 8px;
                }

                th {
                    background-color: #f8f9fa;
                }

                .no-print {
                    display: none !important;
                }

                .btn {
                    display: none;
                }

            </style>

        </head>

        <body>

            <h2>قائمة المخازن</h2>

            <table>

                <thead>
                    ${table.querySelector('thead').innerHTML}
                </thead>

                <tbody>
    `;


    const rows =
        document.querySelectorAll(
            '#stocksTableBody tr.stock-row'
        );


    rows.forEach(row => {

        const cells =
            row.querySelectorAll('td');


        let rowHtml =
            '<tr>';


        for (
            let i = 0;
            i < Math.min(
                cells.length - 1,
                4
            );
            i++
        ) {

            rowHtml +=
                cells[i].outerHTML;
        }


        rowHtml +=
            '</tr>';


        printContents +=
            rowHtml;

    });


    printContents += `
                </tbody>

            </table>

        </body>

        </html>
    `;


    const printWindow =
        window.open(
            '',
            '_blank'
        );


    if (!printWindow) {

        alert(
            'تعذر فتح نافذة الطباعة. يرجى السماح بالنوافذ المنبثقة.'
        );

        return;
    }


    printWindow.document.write(
        printContents
    );


    printWindow.document.close();


    setTimeout(() => {

        printWindow.print();

        printWindow.close();

    }, 250);

}


/*
 * =========================================================
 * fallback
 * =========================================================
 */

if (
    typeof showSystemToast !==
    'function'
) {

    window.showSystemToast =
        function (
            message,
            type
        ) {

            alert(message);

        };

}