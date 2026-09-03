document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | البيانات التجريبية
    |--------------------------------------------------------------------------
    | سيتم استبدالها لاحقاً ببيانات Laravel / Database
    |--------------------------------------------------------------------------
    */

    let warehouses = [
        {
            id: 1,
            name: 'المخزن الرئيسي',
            account: 'حساب المخزن الرئيسي'
        },
        {
            id: 2,
            name: 'مخزن الفرع الأول',
            account: 'حساب مخزن الفرع الأول'
        },
        {
            id: 3,
            name: 'مخزن التبريد',
            account: 'حساب مخزن التبريد'
        }
    ];


    const tableBody =
        document.querySelector('#warehousesTable tbody');

    const searchInput =
        document.getElementById('warehouseSearch');

    const searchButton =
        document.getElementById('searchWarehouseBtn');

    const addButton =
        document.getElementById('addWarehouseBtn');

    const printButton =
        document.getElementById('printWarehousesBtn');


    /*
    |--------------------------------------------------------------------------
    | عرض المخازن
    |--------------------------------------------------------------------------
    */

    function renderWarehouses(data = warehouses) {

        if (!tableBody) {
            return;
        }


        tableBody.innerHTML = '';


        if (data.length === 0) {

            tableBody.innerHTML = `
                <tr>

                    <td
                        colspan="4"
                        class="text-center text-muted py-5"
                    >

                        <i class="bi bi-building fs-2 d-block mb-2"></i>

                        لا توجد مخازن مسجلة

                    </td>

                </tr>
            `;

            return;
        }


        data.forEach(function (warehouse) {

            const row = document.createElement('tr');

            row.innerHTML = `

                <td class="text-center">
                    ${warehouse.id}
                </td>

                <td>
                    ${escapeHtml(warehouse.name)}
                </td>

                <td>
                    ${escapeHtml(
                warehouse.account || 'غير مرتبط'
            )}
                </td>

                <td class="text-center">

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary edit-warehouse"
                        data-id="${warehouse.id}"
                        title="تعديل"
                    >
                        <i class="bi bi-pencil"></i>
                        تعديل
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger delete-warehouse"
                        data-id="${warehouse.id}"
                        title="حذف"
                    >
                        <i class="bi bi-trash"></i>
                        حذف
                    </button>

                </td>

            `;

            tableBody.appendChild(row);

        });

    }


    /*
    |--------------------------------------------------------------------------
    | البحث
    |--------------------------------------------------------------------------
    */

    function searchWarehouses() {

        const value = searchInput
            ? searchInput.value.trim().toLowerCase()
            : '';


        if (value === '') {

            renderWarehouses();

            return;
        }


        const filtered = warehouses.filter(function (warehouse) {

            return warehouse.name
                .toLowerCase()
                .includes(value);

        });


        renderWarehouses(filtered);

    }


    /*
    |--------------------------------------------------------------------------
    | إضافة مخزن
    |--------------------------------------------------------------------------
    */

    function addWarehouse() {

        const warehouseName = prompt(
            'أدخل اسم المخزن:'
        );


        if (warehouseName === null) {
            return;
        }


        const name = warehouseName.trim();


        if (name === '') {

            alert('يرجى إدخال اسم المخزن.');

            return;
        }


        /*
        | منع تكرار اسم المخزن
        */

        const exists = warehouses.some(function (warehouse) {

            return warehouse.name.toLowerCase() ===
                name.toLowerCase();

        });


        if (exists) {

            alert('هذا المخزن مسجل مسبقاً.');

            return;
        }


        /*
        | رقم مؤقت
        */

        const newId = warehouses.length > 0
            ? Math.max(
                ...warehouses.map(
                    warehouse => warehouse.id
                )
            ) + 1
            : 1;


        warehouses.push({

            id: newId,

            name: name,

            account: 'غير مرتبط'

        });


        renderWarehouses();

        alert('تمت إضافة المخزن بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | تعديل مخزن
    |--------------------------------------------------------------------------
    */

    function editWarehouse(id) {

        const warehouse = warehouses.find(
            function (item) {
                return item.id === id;
            }
        );


        if (!warehouse) {
            return;
        }


        const newName = prompt(
            'تعديل اسم المخزن:',
            warehouse.name
        );


        if (newName === null) {
            return;
        }


        const name = newName.trim();


        if (name === '') {

            alert('اسم المخزن لا يمكن أن يكون فارغاً.');

            return;
        }


        /*
        | منع التكرار
        */

        const exists = warehouses.some(function (item) {

            return item.id !== id &&
                item.name.toLowerCase() ===
                name.toLowerCase();

        });


        if (exists) {

            alert('يوجد مخزن آخر بهذا الاسم.');

            return;
        }


        warehouse.name = name;


        renderWarehouses();

        alert('تم تعديل المخزن بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | حذف مخزن
    |--------------------------------------------------------------------------
    */

    function deleteWarehouse(id) {

        const warehouse = warehouses.find(
            function (item) {
                return item.id === id;
            }
        );


        if (!warehouse) {
            return;
        }


        const confirmed = confirm(
            `هل أنت متأكد من حذف المخزن "${warehouse.name}"؟`
        );


        if (!confirmed) {
            return;
        }


        warehouses = warehouses.filter(
            function (item) {
                return item.id !== id;
            }
        );


        renderWarehouses();

        alert('تم حذف المخزن بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | طباعة المخازن
    |--------------------------------------------------------------------------
    */

    function printWarehouses() {

        const searchValue = searchInput
            ? searchInput.value.trim().toLowerCase()
            : '';


        let dataToPrint = warehouses;


        /*
        | إذا كان هناك بحث،
        | تتم طباعة نتائج البحث فقط
        */

        if (searchValue !== '') {

            dataToPrint = warehouses.filter(
                function (warehouse) {

                    return warehouse.name
                        .toLowerCase()
                        .includes(searchValue);

                }
            );

        }


        if (dataToPrint.length === 0) {

            alert('لا توجد بيانات لطباعة.');

            return;
        }


        let rows = '';


        dataToPrint.forEach(function (warehouse, index) {

            rows += `
                <tr>

                    <td>
                        ${index + 1}
                    </td>

                    <td>
                        ${escapeHtml(warehouse.name)}
                    </td>

                    <td>
                        ${escapeHtml(
                warehouse.account || 'غير مرتبط'
            )}
                    </td>

                </tr>
            `;

        });


        const printWindow = window.open(
            '',
            '_blank',
            'width=900,height=700'
        );


        if (!printWindow) {

            alert(
                'تعذر فتح نافذة الطباعة. يرجى السماح بالنوافذ المنبثقة.'
            );

            return;
        }


        printWindow.document.write(`

            <!DOCTYPE html>

            <html lang="ar" dir="rtl">

            <head>

                <meta charset="UTF-8">

                <title>
                    طباعة المخازن
                </title>

                <style>

                    body {
                        font-family: Arial, sans-serif;
                        padding: 30px;
                        direction: rtl;
                    }

                    h2 {
                        text-align: center;
                        margin-bottom: 25px;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                    }

                    th,
                    td {
                        border: 1px solid #000;
                        padding: 10px;
                        text-align: center;
                    }

                    th {
                        background: #eee;
                    }

                    .date {
                        text-align: left;
                        margin-bottom: 15px;
                    }

                </style>

            </head>

            <body>

                <h2>
                    قائمة المخازن
                </h2>

                <div class="date">

                    تاريخ الطباعة:
                    ${new Date().toLocaleDateString('ar-YE')}

                </div>

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                اسم المخزن
                            </th>

                            <th>
                                الحساب المرتبط
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        ${rows}

                    </tbody>

                </table>

                <script>

                    window.onload = function () {
                        window.print();
                    };

                <\/script>

            </body>

            </html>

        `);


        printWindow.document.close();

    }


    /*
    |--------------------------------------------------------------------------
    | حماية النصوص
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return String(value)

            .replace(/&/g, '&amp;')

            .replace(/</g, '&lt;')

            .replace(/>/g, '&gt;')

            .replace(/"/g, '&quot;')

            .replace(/'/g, '&#039;');

    }


    /*
    |--------------------------------------------------------------------------
    | أحداث البحث
    |--------------------------------------------------------------------------
    */

    if (searchButton) {

        searchButton.addEventListener(
            'click',
            searchWarehouses
        );

    }


    if (searchInput) {

        searchInput.addEventListener(
            'keyup',
            function (event) {

                if (event.key === 'Enter') {

                    searchWarehouses();

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | إضافة
    |--------------------------------------------------------------------------
    */

    if (addButton) {

        addButton.addEventListener(
            'click',
            addWarehouse
        );

    }


    /*
    |--------------------------------------------------------------------------
    | طباعة
    |--------------------------------------------------------------------------
    */

    if (printButton) {

        printButton.addEventListener(
            'click',
            printWarehouses
        );

    }


    /*
    |--------------------------------------------------------------------------
    | تعديل وحذف
    |--------------------------------------------------------------------------
    */

    if (tableBody) {

        tableBody.addEventListener(
            'click',
            function (event) {

                const editButton =
                    event.target.closest('.edit-warehouse');

                const deleteButton =
                    event.target.closest('.delete-warehouse');


                if (editButton) {

                    const id = Number(
                        editButton.dataset.id
                    );

                    editWarehouse(id);

                    return;
                }


                if (deleteButton) {

                    const id = Number(
                        deleteButton.dataset.id
                    );

                    deleteWarehouse(id);

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | التشغيل الأول
    |--------------------------------------------------------------------------
    */

    renderWarehouses();

});