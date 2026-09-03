document.addEventListener('DOMContentLoaded', function () {

    let units = [
        {
            id: 1,
            name: 'كيلو'
        },
        {
            id: 2,
            name: 'جرام'
        },
        {
            id: 3,
            name: 'حبة'
        },
        {
            id: 4,
            name: 'كيس'
        }
    ];


    const unitsTable = document.querySelector('#unitsTable tbody');
    const searchInput = document.getElementById('unitSearch');
    const searchButton = document.getElementById('searchUnitBtn');
    const addButton = document.getElementById('addUnitBtn');
    const printButton = document.getElementById('printUnitsBtn');


    /*
    |--------------------------------------------------------------------------
    | عرض الوحدات
    |--------------------------------------------------------------------------
    */

    function renderUnits(data = units) {

        if (!unitsTable) {
            return;
        }

        unitsTable.innerHTML = '';


        if (data.length === 0) {

            unitsTable.innerHTML = `
                <tr>
                    <td
                        colspan="3"
                        class="text-center text-muted py-5"
                    >
                        <i class="bi bi-rulers fs-2 d-block mb-2"></i>
                        لا توجد وحدات مسجلة
                    </td>
                </tr>
            `;

            return;
        }


        data.forEach(function (unit) {

            const row = document.createElement('tr');

            row.innerHTML = `
                <td class="text-center">
                    ${unit.id}
                </td>

                <td>
                    ${escapeHtml(unit.name)}
                </td>

                <td class="text-center">

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary edit-unit"
                        data-id="${unit.id}"
                    >
                        <i class="bi bi-pencil"></i>
                        تعديل
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger delete-unit"
                        data-id="${unit.id}"
                    >
                        <i class="bi bi-trash"></i>
                        حذف
                    </button>

                </td>
            `;

            unitsTable.appendChild(row);

        });

    }


    /*
    |--------------------------------------------------------------------------
    | البحث
    |--------------------------------------------------------------------------
    */

    function searchUnits() {

        const searchValue = searchInput.value
            .trim()
            .toLowerCase();


        if (searchValue === '') {

            renderUnits();

            return;
        }


        const filteredUnits = units.filter(function (unit) {

            return unit.name
                .toLowerCase()
                .includes(searchValue);

        });


        renderUnits(filteredUnits);

    }


    /*
    |--------------------------------------------------------------------------
    | إضافة وحدة
    |--------------------------------------------------------------------------
    */

    function addUnit() {

        const unitName = prompt('أدخل اسم الوحدة:');


        if (unitName === null) {
            return;
        }


        const name = unitName.trim();


        if (name === '') {

            alert('يرجى إدخال اسم الوحدة.');

            return;
        }


        /*
        | منع تكرار الوحدة
        */

        const exists = units.some(function (unit) {

            return unit.name.toLowerCase() === name.toLowerCase();

        });


        if (exists) {

            alert('هذه الوحدة مسجلة مسبقاً.');

            return;
        }


        /*
        | إنشاء رقم مؤقت
        */

        const newId = units.length > 0
            ? Math.max(...units.map(unit => unit.id)) + 1
            : 1;


        units.push({
            id: newId,
            name: name
        });


        renderUnits();

        alert('تمت إضافة الوحدة بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | تعديل وحدة
    |--------------------------------------------------------------------------
    */

    function editUnit(id) {

        const unit = units.find(function (item) {

            return item.id === id;

        });


        if (!unit) {
            return;
        }


        const newName = prompt(
            'تعديل اسم الوحدة:',
            unit.name
        );


        if (newName === null) {
            return;
        }


        const name = newName.trim();


        if (name === '') {

            alert('اسم الوحدة لا يمكن أن يكون فارغاً.');

            return;
        }


        /*
        | منع التكرار
        */

        const exists = units.some(function (item) {

            return item.id !== id &&
                   item.name.toLowerCase() === name.toLowerCase();

        });


        if (exists) {

            alert('يوجد وحدة أخرى بهذا الاسم.');

            return;
        }


        unit.name = name;


        renderUnits();

        alert('تم تعديل الوحدة بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | حذف وحدة
    |--------------------------------------------------------------------------
    */

    function deleteUnit(id) {

        const unit = units.find(function (item) {

            return item.id === id;

        });


        if (!unit) {
            return;
        }


        const confirmed = confirm(
            `هل أنت متأكد من حذف الوحدة "${unit.name}"؟`
        );


        if (!confirmed) {
            return;
        }


        units = units.filter(function (item) {

            return item.id !== id;

        });


        renderUnits();

        alert('تم حذف الوحدة بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | طباعة الوحدات
    |--------------------------------------------------------------------------
    */

    function printUnits() {

        const searchValue = searchInput
            ? searchInput.value.trim().toLowerCase()
            : '';


        let dataToPrint = units;


        /*
        | طباعة نتائج البحث فقط
        */

        if (searchValue !== '') {

            dataToPrint = units.filter(function (unit) {

                return unit.name
                    .toLowerCase()
                    .includes(searchValue);

            });

        }


        if (dataToPrint.length === 0) {

            alert('لا توجد بيانات لطباعة.');

            return;
        }


        let rows = '';


        dataToPrint.forEach(function (unit, index) {

            rows += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(unit.name)}</td>
                </tr>
            `;

        });


        const printWindow = window.open(
            '',
            '_blank',
            'width=900,height=700'
        );


        printWindow.document.write(`
            <!DOCTYPE html>

            <html lang="ar" dir="rtl">

            <head>

                <meta charset="UTF-8">

                <title>طباعة الوحدات</title>

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
                    قائمة الوحدات
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
                                اسم الوحدة
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
    | الأحداث
    |--------------------------------------------------------------------------
    */

    if (searchButton) {

        searchButton.addEventListener(
            'click',
            searchUnits
        );

    }


    if (searchInput) {

        searchInput.addEventListener(
            'keyup',
            function (event) {

                if (event.key === 'Enter') {

                    searchUnits();

                }

            }
        );

    }


    if (addButton) {

        addButton.addEventListener(
            'click',
            addUnit
        );

    }


    if (printButton) {

        printButton.addEventListener(
            'click',
            printUnits
        );

    }


    /*
    |--------------------------------------------------------------------------
    | تعديل وحذف
    |--------------------------------------------------------------------------
    */

    if (unitsTable) {

        unitsTable.addEventListener(
            'click',
            function (event) {

                const editButton =
                    event.target.closest('.edit-unit');

                const deleteButton =
                    event.target.closest('.delete-unit');


                if (editButton) {

                    const id = Number(
                        editButton.dataset.id
                    );

                    editUnit(id);

                    return;
                }


                if (deleteButton) {

                    const id = Number(
                        deleteButton.dataset.id
                    );

                    deleteUnit(id);

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | التشغيل الأول
    |--------------------------------------------------------------------------
    */

    renderUnits();

});