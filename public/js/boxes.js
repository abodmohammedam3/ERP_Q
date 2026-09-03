document.addEventListener('DOMContentLoaded', function () {

    // =========================================================
    // المتغيرات الرئيسية
    // =========================================================

    let boxes = [
        {
            id: 1,
            name: 'الصندوق الرئيسي',
            coinId: 1,
            coinCode: 'YER',
            exchangeRate: 1,
            accountName: 'حساب الصندوق الرئيسي'
        },
        {
            id: 2,
            name: 'صندوق المبيعات',
            coinId: 1,
            coinCode: 'YER',
            exchangeRate: 1,
            accountName: 'حساب مبيعات الصندوق'
        }
    ];

    let editingId = null;


    // =========================================================
    // عناصر الصفحة
    // =========================================================

    const searchInput = document.querySelector(
        'input[placeholder="ابحث باسم الصندوق..."]'
    );

    const coinFilter = document.querySelector(
        'select.form-select'
    );

    const searchButton = document.querySelector(
        '.btn-secondary'
    );

    const addButton = document.querySelector(
        '.btn-primary'
    );

    const tableBody = document.querySelector(
        'table tbody'
    );


    // =========================================================
    // إنشاء Modal الإضافة والتعديل
    // =========================================================

    createBoxModal();


    // =========================================================
    // عرض الصناديق
    // =========================================================

    function renderBoxes(data = boxes) {

        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';


        // لا توجد بيانات
        if (data.length === 0) {

            tableBody.innerHTML = `
                <tr>

                    <td
                        colspan="5"
                        class="text-center text-muted py-5"
                    >

                        <i class="bi bi-safe2 fs-2 d-block mb-2"></i>

                        لا توجد صناديق مسجلة

                    </td>

                </tr>
            `;

            return;
        }


        // عرض البيانات
        data.forEach(function (box) {

            const row = document.createElement('tr');

            row.classList.add('text-center');

            row.innerHTML = `

                <td class="text-center">
                    ${box.id}
                </td>

                <td>
                    ${escapeHtml(box.name)}
                </td>

                <td>
                    ${escapeHtml(box.coinCode || 'غير محددة')}
                </td>

                <td>
                    ${escapeHtml(box.accountName || 'غير مرتبط')}
                </td>

                <td class="text-center">

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary edit-box"
                        data-id="${box.id}"
                    >

                        <i class="bi bi-pencil"></i>
                        تعديل

                    </button>


                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger delete-box"
                        data-id="${box.id}"
                    >

                        <i class="bi bi-trash"></i>
                        حذف

                    </button>

                </td>

            `;

            tableBody.appendChild(row);

        });

    }


    // =========================================================
    // إنشاء نافذة إضافة / تعديل الصندوق
    // =========================================================

    function createBoxModal() {

        const modalHTML = `

            <div
                class="modal fade"
                id="boxModal"
                tabindex="-1"
                aria-labelledby="boxModalLabel"
                aria-hidden="true"
            >

                <div class="modal-dialog modal-lg modal-dialog-centered">

                    <div class="modal-content">

                        <div class="modal-header">

                            <h5
                                class="modal-title"
                                id="boxModalLabel"
                            >

                                <i class="bi bi-safe2"></i>
                                إضافة صندوق

                            </h5>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="إغلاق"
                            ></button>

                        </div>


                        <div class="modal-body">

                            <form id="boxForm">

                                <div class="row g-3">

                                    <div class="col-md-6">

                                        <label class="form-label">
                                            اسم الصندوق
                                            <span class="text-danger">*</span>
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            name="boxName"
                                            placeholder="أدخل اسم الصندوق"
                                            required
                                        >

                                    </div>


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            العملة
                                            <span class="text-danger">*</span>
                                        </label>

                                        <select
                                            class="form-select"
                                            name="coinId"
                                            required
                                        >

                                            <option value="">
                                                اختر العملة
                                            </option>

                                            ${getCoinsOptions()}

                                        </select>

                                    </div>


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            سعر الصرف
                                            <span class="text-danger">*</span>
                                        </label>

                                        <input
                                            type="number"
                                            class="form-control"
                                            name="exchangeRate"
                                            min="0"
                                            step="0.000001"
                                            placeholder="أدخل سعر الصرف"
                                            required
                                        >

                                    </div>


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            الحساب المحاسبي
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            name="accountName"
                                            placeholder="اسم الحساب المرتبط بالصندوق"
                                        >

                                    </div>

                                </div>

                            </form>

                        </div>


                        <div class="modal-footer">

                            <button
                                type="button"
                                class="btn btn-secondary"
                                data-bs-dismiss="modal"
                            >

                                <i class="bi bi-x-lg"></i>
                                إلغاء

                            </button>


                            <button
                                type="button"
                                class="btn btn-success"
                                id="saveBoxButton"
                            >

                                <i class="bi bi-check-lg"></i>
                                حفظ

                            </button>

                        </div>

                    </div>

                </div>

            </div>
        `;


        document.body.insertAdjacentHTML(
            'beforeend',
            modalHTML
        );


        attachModalEvents();

    }


    // =========================================================
    // خيارات العملات
    // =========================================================

    function getCoinsOptions() {

        /*
         * سيتم لاحقًا استبدال هذه البيانات
         * بالعملات القادمة من Laravel.
         */

        return `
            <option value="1">
                YER - الريال اليمني
            </option>

            <option value="2">
                SAR - الريال السعودي
            </option>

            <option value="3">
                USD - الدولار الأمريكي
            </option>
        `;

    }


    // =========================================================
    // أحداث Modal
    // =========================================================

    function attachModalEvents() {

        const saveButton =
            document.getElementById('saveBoxButton');

        if (saveButton) {

            saveButton.addEventListener(
                'click',
                saveBox
            );

        }

    }


    // =========================================================
    // فتح نافذة الإضافة
    // =========================================================

    if (addButton) {

        addButton.addEventListener(
            'click',
            function () {

                editingId = null;

                resetBoxForm();

                setModalTitle(
                    'إضافة صندوق'
                );

                openBoxModal();

            }
        );

    }


    // =========================================================
    // فتح نافذة التعديل
    // =========================================================

    document.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest('.edit-box');

            if (!button) {
                return;
            }


            const id =
                Number(button.dataset.id);


            const box =
                boxes.find(function (item) {

                    return item.id === id;

                });


            if (!box) {
                return;
            }


            editingId = id;

            fillBoxForm(box);

            setModalTitle(
                'تعديل الصندوق'
            );

            openBoxModal();

        }
    );


    // =========================================================
    // حفظ الصندوق
    // =========================================================

    function saveBox() {

        const form =
            document.getElementById('boxForm');


        if (!form) {
            return;
        }


        if (!form.checkValidity()) {

            form.reportValidity();

            return;

        }


        const name =
            form.elements['boxName']
                .value
                .trim();


        const coinId =
            Number(
                form.elements['coinId'].value
            );


        const exchangeRate =
            Number(
                form.elements['exchangeRate'].value
            );


        const accountName =
            form.elements['accountName']
                .value
                .trim();


        if (!name) {

            alert(
                'يرجى إدخال اسم الصندوق.'
            );

            return;

        }


        if (!coinId) {

            alert(
                'يرجى اختيار العملة.'
            );

            return;

        }


        if (!exchangeRate || exchangeRate <= 0) {

            alert(
                'يرجى إدخال سعر صرف صحيح.'
            );

            return;

        }


        const coinCode =
            getCoinCode(coinId);


        // =====================================================
        // تعديل
        // =====================================================

        if (editingId !== null) {

            const index =
                boxes.findIndex(function (item) {

                    return item.id === editingId;

                });


            if (index !== -1) {

                boxes[index] = {

                    ...boxes[index],

                    name: name,

                    coinId: coinId,

                    coinCode: coinCode,

                    exchangeRate: exchangeRate,

                    accountName: accountName

                };

            }

        }


        // =====================================================
        // إضافة
        // =====================================================

        else {

            boxes.push({

                id: getNextId(),

                name: name,

                coinId: coinId,

                coinCode: coinCode,

                exchangeRate: exchangeRate,

                accountName: accountName

            });

        }


        renderBoxes();

        closeBoxModal();

        resetBoxForm();

        editingId = null;


        alert(
            'تم حفظ الصندوق بنجاح.'
        );

    }


    // =========================================================
    // حذف الصندوق
    // =========================================================

    document.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest('.delete-box');


            if (!button) {
                return;
            }


            const id =
                Number(button.dataset.id);


            const box =
                boxes.find(function (item) {

                    return item.id === id;

                });


            if (!box) {
                return;
            }


            const confirmed =
                confirm(
                    `هل أنت متأكد من حذف الصندوق "${box.name}"؟`
                );


            if (!confirmed) {
                return;
            }


            boxes =
                boxes.filter(function (item) {

                    return item.id !== id;

                });


            renderBoxes();


            alert(
                'تم حذف الصندوق بنجاح.'
            );

        }
    );


    // =========================================================
    // البحث
    // =========================================================

    function filterBoxes() {

        const search =
            searchInput
                ? searchInput.value
                    .trim()
                    .toLowerCase()
                : '';


        const selectedCoin =
            coinFilter
                ? coinFilter.value
                : '';


        const filtered =
            boxes.filter(function (box) {


                const matchesSearch =
                    box.name
                        .toLowerCase()
                        .includes(search);


                const matchesCoin =
                    !selectedCoin ||
                    String(box.coinId) === selectedCoin;


                return (
                    matchesSearch &&
                    matchesCoin
                );

            });


        renderBoxes(filtered);

    }


    // =========================================================
    // زر البحث
    // =========================================================

    if (searchButton) {

        searchButton.addEventListener(
            'click',
            filterBoxes
        );

    }


    // =========================================================
    // البحث أثناء الكتابة
    // =========================================================

    if (searchInput) {

        searchInput.addEventListener(
            'input',
            filterBoxes
        );

    }


    // =========================================================
    // فلترة حسب العملة
    // =========================================================

    if (coinFilter) {

        coinFilter.addEventListener(
            'change',
            filterBoxes
        );

    }


    // =========================================================
    // تعبئة نموذج التعديل
    // =========================================================

    function fillBoxForm(box) {

        const form =
            document.getElementById('boxForm');


        if (!form) {
            return;
        }


        form.elements['boxName'].value =
            box.name;


        form.elements['coinId'].value =
            box.coinId;


        form.elements['exchangeRate'].value =
            box.exchangeRate;


        form.elements['accountName'].value =
            box.accountName || '';

    }


    // =========================================================
    // إعادة ضبط النموذج
    // =========================================================

    function resetBoxForm() {

        const form =
            document.getElementById('boxForm');


        if (!form) {
            return;
        }


        form.reset();

    }


    // =========================================================
    // عنوان Modal
    // =========================================================

    function setModalTitle(title) {

        const titleElement =
            document.getElementById(
                'boxModalLabel'
            );


        if (titleElement) {

            titleElement.innerHTML = `

                <i class="bi bi-safe2"></i>
                ${title}

            `;

        }

    }


    // =========================================================
    // فتح Modal
    // =========================================================

    function openBoxModal() {

        const modalElement =
            document.getElementById(
                'boxModal'
            );


        if (!modalElement) {
            return;
        }


        const modal =
            bootstrap.Modal.getOrCreateInstance(
                modalElement
            );


        modal.show();

    }


    // =========================================================
    // إغلاق Modal
    // =========================================================

    function closeBoxModal() {

        const modalElement =
            document.getElementById(
                'boxModal'
            );


        if (!modalElement) {
            return;
        }


        const modal =
            bootstrap.Modal.getInstance(
                modalElement
            );


        if (modal) {

            modal.hide();

        }

    }


    // =========================================================
    // الحصول على كود العملة
    // =========================================================

    function getCoinCode(coinId) {

        const coins = {

            1: 'YER',

            2: 'SAR',

            3: 'USD'

        };


        return coins[coinId] || '';

    }


    // =========================================================
    // إنشاء ID جديد
    // =========================================================

    function getNextId() {

        if (boxes.length === 0) {
            return 1;
        }


        return Math.max(
            ...boxes.map(function (box) {

                return box.id;

            })
        ) + 1;

    }


    // =========================================================
    // حماية النصوص
    // =========================================================

    function escapeHtml(value) {

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    }


    // =========================================================
    // تشغيل الشاشة
    // =========================================================

    renderBoxes();

});