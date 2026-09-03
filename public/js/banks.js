document.addEventListener('DOMContentLoaded', function () {

    let banks = [];

    const banksTable = document.querySelector('#banksTable tbody');
    const searchInput = document.getElementById('bankSearch');
    const searchButton = document.getElementById('searchBankBtn');
    const addButton = document.getElementById('addBankBtn');
    const printButton = document.getElementById('printBanksBtn');


    /*
    |--------------------------------------------------------------------------
    | بيانات تجريبية
    |--------------------------------------------------------------------------
    | سيتم استبدالها لاحقاً ببيانات Laravel / Database
    |--------------------------------------------------------------------------
    */

    banks = [
        {
            id: 1,
            name: 'بنك الكريمي'
        },
        {
            id: 2,
            name: 'بنك اليمن والكويت'
        },
        {
            id: 3,
            name: 'بنك التضامن'
        }
    ];


    /*
    |--------------------------------------------------------------------------
    | عرض البنوك
    |--------------------------------------------------------------------------
    */

    function renderBanks(data = banks) {

        if (!banksTable) {
            return;
        }

        banksTable.innerHTML = '';

        if (data.length === 0) {

            banksTable.innerHTML = `
                <tr>
                    <td
                        colspan="3"
                        class="text-center text-muted py-5"
                    >
                        <i class="bi bi-bank fs-2 d-block mb-2"></i>
                        لا توجد بنوك مسجلة
                    </td>
                </tr>
            `;

            return;
        }


        data.forEach(function (bank, index) {

            const row = document.createElement('tr');

            row.innerHTML = `
                <td class="text-center">
                    ${bank.id}
                </td>

                <td>
                    ${escapeHtml(bank.name)}
                </td>

                <td class="text-center">

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary edit-bank"
                        data-id="${bank.id}"
                    >
                        <i class="bi bi-pencil"></i>
                        تعديل
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger delete-bank"
                        data-id="${bank.id}"
                    >
                        <i class="bi bi-trash"></i>
                        حذف
                    </button>

                </td>
            `;

            banksTable.appendChild(row);

        });

    }


    /*
    |--------------------------------------------------------------------------
    | البحث
    |--------------------------------------------------------------------------
    */

    function searchBanks() {

        const searchValue = searchInput.value.trim().toLowerCase();

        if (searchValue === '') {

            renderBanks();

            return;
        }


        const filteredBanks = banks.filter(function (bank) {

            return bank.name
                .toLowerCase()
                .includes(searchValue);

        });


        renderBanks(filteredBanks);

    }


    /*
    |--------------------------------------------------------------------------
    | إضافة بنك
    |--------------------------------------------------------------------------
    */

    function addBank() {

        const bankName = prompt('أدخل اسم البنك:');


        if (bankName === null) {
            return;
        }


        const name = bankName.trim();


        if (name === '') {

            alert('يرجى إدخال اسم البنك.');

            return;
        }


        /*
        | منع تكرار اسم البنك
        */

        const exists = banks.some(function (bank) {

            return bank.name.toLowerCase() === name.toLowerCase();

        });


        if (exists) {

            alert('هذا البنك مسجل مسبقاً.');

            return;
        }


        /*
        | إنشاء رقم مؤقت
        */

        const newId = banks.length > 0
            ? Math.max(...banks.map(bank => bank.id)) + 1
            : 1;


        banks.push({
            id: newId,
            name: name
        });


        renderBanks();

        alert('تمت إضافة البنك بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | تعديل بنك
    |--------------------------------------------------------------------------
    */

    function editBank(id) {

        const bank = banks.find(function (item) {

            return item.id === id;

        });


        if (!bank) {
            return;
        }


        const newName = prompt(
            'تعديل اسم البنك:',
            bank.name
        );


        if (newName === null) {
            return;
        }


        const name = newName.trim();


        if (name === '') {

            alert('اسم البنك لا يمكن أن يكون فارغاً.');

            return;
        }


        /*
        | منع التكرار
        */

        const exists = banks.some(function (item) {

            return item.id !== id &&
                   item.name.toLowerCase() === name.toLowerCase();

        });


        if (exists) {

            alert('يوجد بنك آخر بهذا الاسم.');

            return;
        }


        bank.name = name;


        renderBanks();

        alert('تم تعديل البنك بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | حذف بنك
    |--------------------------------------------------------------------------
    */

    function deleteBank(id) {

        const bank = banks.find(function (item) {

            return item.id === id;

        });


        if (!bank) {
            return;
        }


        const confirmed = confirm(
            `هل أنت متأكد من حذف البنك "${bank.name}"؟`
        );


        if (!confirmed) {
            return;
        }


        banks = banks.filter(function (item) {

            return item.id !== id;

        });


        renderBanks();

        alert('تم حذف البنك بنجاح.');

    }


    /*
    |--------------------------------------------------------------------------
    | طباعة البنوك
    |--------------------------------------------------------------------------
    */

    function printBanks() {

        const searchValue = searchInput
            ? searchInput.value.trim().toLowerCase()
            : '';


        let dataToPrint = banks;


        /*
        | إذا كان المستخدم يبحث،
        | تتم طباعة النتائج الظاهرة فقط
        */

        if (searchValue !== '') {

            dataToPrint = banks.filter(function (bank) {

                return bank.name
                    .toLowerCase()
                    .includes(searchValue);

            });

        }


        if (dataToPrint.length === 0) {

            alert('لا توجد بيانات لطباعة.');

            return;
        }


        let rows = '';


        dataToPrint.forEach(function (bank, index) {

            rows += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(bank.name)}</td>
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

                <title>طباعة البنوك</title>

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

                    @media print {

                        button {
                            display: none;
                        }

                    }

                </style>

            </head>

            <body>

                <h2>
                    قائمة البنوك
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
                                اسم البنك
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
    | حماية النصوص من HTML
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
            searchBanks
        );

    }


    if (searchInput) {

        searchInput.addEventListener(
            'keyup',
            function (event) {

                if (event.key === 'Enter') {

                    searchBanks();

                }

            }
        );

    }


    if (addButton) {

        addButton.addEventListener(
            'click',
            addBank
        );

    }


    if (printButton) {

        printButton.addEventListener(
            'click',
            printBanks
        );

    }


    /*
    |--------------------------------------------------------------------------
    | أزرار التعديل والحذف
    |--------------------------------------------------------------------------
    */

    if (banksTable) {

        banksTable.addEventListener(
            'click',
            function (event) {

                const editButton =
                    event.target.closest('.edit-bank');

                const deleteButton =
                    event.target.closest('.delete-bank');


                if (editButton) {

                    const id = Number(
                        editButton.dataset.id
                    );

                    editBank(id);

                    return;
                }


                if (deleteButton) {

                    const id = Number(
                        deleteButton.dataset.id
                    );

                    deleteBank(id);

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | التشغيل الأول
    |--------------------------------------------------------------------------
    */

    renderBanks();

});