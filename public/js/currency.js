document.addEventListener('DOMContentLoaded', function () {

    // =========================================================
    // عناصر الشاشة
    // =========================================================

    const coinModal = document.getElementById('coinModal');
    const coinModalLabel = document.getElementById('coinModalLabel');
    const coinForm = document.getElementById('coinForm');

    const searchInput = document.querySelector('input[name="search"]');
    const statusFilter = document.querySelector('select[name="status"]');

    const searchButton = document.querySelector('.btn-secondary');
    const resetButton = document.querySelector('.btn-outline-secondary');

    // زر الحفظ - نختاره بواسطة المعرف الجديد
    const saveButton = document.getElementById('saveCoinBtn');


    // =========================================================
    // بيانات تجريبية مؤقتة
    // =========================================================

    let coins = [
        {
            id: 1,
            name: 'الريال اليمني',
            symbol: 'YER',
            exchangeRate: 1,
            baseCurrency: true,
            status: true,
        },
        {
            id: 2,
            name: 'الريال السعودي',
            symbol: 'SAR',
            exchangeRate: 0.0097,
            baseCurrency: false,
            status: true,
        }
    ];

    let editingId = null;


    // =========================================================
    // عناصر جدول العملات
    // =========================================================

    const tableBody = document.querySelector('table tbody');
    const countBadge = document.querySelector('.card-header .badge');


    // =========================================================
    // عرض العملات
    // =========================================================

    function renderCoins(data = coins) {

        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';

        if (data.length === 0) {

            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">

                        <i class="bi bi-currency-exchange fs-2 d-block mb-2"></i>

                        لا توجد عملات لعرضها

                    </td>
                </tr>
            `;

            updateCount(0);

            return;
        }


        data.forEach(function (coin, index) {

            const row = document.createElement('tr');

            row.innerHTML = `

                <td class="text-center">
                    ${index + 1}
                </td>

                <td>
                    ${escapeHtml(coin.name)}
                </td>

                <td>
                    <span class="badge bg-secondary">
                        ${escapeHtml(coin.symbol)}
                    </span>
                </td>

                <td>
                    ${formatNumber(coin.exchangeRate)}
                </td>

                <td>

                    ${
                        coin.baseCurrency
                            ? '<span class="badge bg-success">نعم</span>'
                            : '<span class="badge bg-light text-dark border">لا</span>'
                    }

                </td>

                <td>

                    ${
                        coin.status
                            ? '<span class="badge bg-success">نشطة</span>'
                            : '<span class="badge bg-danger">غير نشطة</span>'
                    }

                </td>

                <td class="text-center">

                    <div class="btn-group" role="group">

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary edit-coin"
                            data-id="${coin.id}"
                            title="تعديل">

                            <i class="bi bi-pencil"></i>

                        </button>


                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger delete-coin"
                            data-id="${coin.id}"
                            title="حذف">

                            <i class="bi bi-trash"></i>

                        </button>

                    </div>

                </td>
            `;

            tableBody.appendChild(row);

        });


        updateCount(data.length);
    }


    // =========================================================
    // تحديث عدد العملات
    // =========================================================

    function updateCount(count) {

        if (countBadge) {
            countBadge.textContent = `${count} عملة`;
        }

    }


    // =========================================================
    // تنسيق الأرقام
    // =========================================================

    function formatNumber(value) {

        return Number(value).toLocaleString('ar-YE', {
            maximumFractionDigits: 6
        });

    }


    // =========================================================
    // حماية النصوص المعروضة داخل HTML
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
    // البحث والتصفية
    // =========================================================

    function filterCoins() {

        const search = searchInput
            ? searchInput.value.trim().toLowerCase()
            : '';

        const status = statusFilter
            ? statusFilter.value
            : '';


        const filtered = coins.filter(function (coin) {

            const matchesSearch =
                coin.name.toLowerCase().includes(search) ||
                coin.symbol.toLowerCase().includes(search);


            let matchesStatus = true;

            if (status === 'active') {
                matchesStatus = coin.status === true;
            }

            if (status === 'inactive') {
                matchesStatus = coin.status === false;
            }


            return matchesSearch && matchesStatus;

        });


        renderCoins(filtered);
    }


    // =========================================================
    // زر البحث
    // =========================================================

    if (searchButton) {

        searchButton.addEventListener('click', function () {

            filterCoins();

        });

    }


    // =========================================================
    // البحث أثناء الكتابة
    // =========================================================

    if (searchInput) {

        searchInput.addEventListener('input', function () {

            filterCoins();

        });

    }


    // =========================================================
    // التصفية حسب الحالة
    // =========================================================

    if (statusFilter) {

        statusFilter.addEventListener('change', function () {

            filterCoins();

        });

    }


    // =========================================================
    // إعادة تعيين البحث
    // =========================================================

    if (resetButton) {

        resetButton.addEventListener('click', function () {

            if (searchInput) {
                searchInput.value = '';
            }

            if (statusFilter) {
                statusFilter.value = '';
            }

            renderCoins();

        });

    }


    // =========================================================
    // فتح نافذة إضافة عملة
    // =========================================================

    document.addEventListener('click', function (event) {

        const addButton = event.target.closest(
            '[data-bs-target="#coinModal"]'
        );

        if (!addButton) {
            return;
        }

        editingId = null;

        if (coinModalLabel) {
            coinModalLabel.innerHTML = `
                <i class="bi bi-currency-exchange"></i>
                إضافة عملة
            `;
        }

        resetForm();

    });


    // =========================================================
    // تعديل العملة
    // =========================================================

    document.addEventListener('click', function (event) {

        const editButton = event.target.closest('.edit-coin');

        if (!editButton) {
            return;
        }

        const id = Number(editButton.dataset.id);

        const coin = coins.find(function (item) {
            return item.id === id;
        });

        if (!coin) {
            return;
        }

        editingId = id;

        fillForm(coin);

        if (coinModalLabel) {

            coinModalLabel.innerHTML = `
                <i class="bi bi-pencil-square"></i>
                تعديل العملة
            `;

        }

        openModal();

    });


    // =========================================================
    // تعبئة النموذج
    // =========================================================

    function fillForm(coin) {

        if (!coinForm) {
            return;
        }

        coinForm.elements['coin_name'].value =
            coin.name;

        coinForm.elements['coin_symbol'].value =
            coin.symbol;

        coinForm.elements['exchange_rate'].value =
            coin.exchangeRate;

        coinForm.elements['is_base_currency'].value =
            coin.baseCurrency ? '1' : '0';

        coinForm.elements['status'].value =
            coin.status ? '1' : '0';

    }


    // =========================================================
    // إعادة ضبط النموذج
    // =========================================================

    function resetForm() {

        if (!coinForm) {
            return;
        }

        coinForm.reset();

        coinForm.elements['is_base_currency'].value = '0';

        coinForm.elements['status'].value = '1';

    }


    // =========================================================
    // فتح Modal
    // =========================================================

    function openModal() {

        if (!coinModal) {
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(coinModal);

        modal.show();

    }


    // =========================================================
    // حفظ العملة (تم إصلاحه)
    // =========================================================

    if (saveButton) {

        saveButton.addEventListener('click', function (event) {

            // منع أي سلوك افتراضي
            event.preventDefault();

            // التحقق من صحة النموذج
            if (!coinForm.checkValidity()) {

                coinForm.reportValidity();

                return;

            }


            // قراءة البيانات من النموذج
            const name =
                coinForm.elements['coin_name'].value.trim();

            const symbol =
                coinForm.elements['coin_symbol'].value.trim();

            const exchangeRate =
                Number(
                    coinForm.elements['exchange_rate'].value
                );

            const baseCurrency =
                coinForm.elements['is_base_currency'].value === '1';

            const status =
                coinForm.elements['status'].value === '1';


            // ---------------------------------------------
            // التحقق من البيانات
            // ---------------------------------------------

            if (!name) {

                alert('يرجى إدخال اسم العملة.');

                return;

            }

            if (!symbol) {

                alert('يرجى إدخال رمز العملة.');

                return;

            }

            if (!exchangeRate || exchangeRate <= 0) {

                alert('يرجى إدخال سعر صرف صحيح.');

                return;

            }


            // ---------------------------------------------
            // تعديل
            // ---------------------------------------------

            if (editingId !== null) {

                const index = coins.findIndex(function (item) {

                    return item.id === editingId;

                });


                if (index !== -1) {

                    coins[index] = {

                        ...coins[index],

                        name: name,
                        symbol: symbol,
                        exchangeRate: exchangeRate,
                        baseCurrency: baseCurrency,
                        status: status

                    };

                }

            }

            // ---------------------------------------------
            // إضافة
            // ---------------------------------------------

            else {

                const newCoin = {

                    id: Date.now(),

                    name: name,

                    symbol: symbol,

                    exchangeRate: exchangeRate,

                    baseCurrency: baseCurrency,

                    status: status

                };


                coins.push(newCoin);

            }


            // ---------------------------------------------
            // منع وجود أكثر من عملة أساسية
            // ---------------------------------------------

            if (baseCurrency) {

                coins.forEach(function (coin) {

                    // استثناء العملة التي يتم تعديلها أو إضافتها
                    const isCurrent = coin.id === editingId || coin.id === (coins.at(-1)?.id);

                    if (!isCurrent) {

                        coin.baseCurrency = false;

                    }

                });

            }


            // إعادة عرض الجدول
            renderCoins();

            // إعادة ضبط النموذج
            resetForm();

            // إلغاء التعديل
            editingId = null;


            // إغلاق المودال
            if (coinModal) {

                const modal =
                    bootstrap.Modal.getInstance(coinModal);

                if (modal) {
                    modal.hide();
                }

            }


            alert('تم حفظ العملة بنجاح.');

        });

    }


    // =========================================================
    // حذف العملة
    // =========================================================

    document.addEventListener('click', function (event) {

        const deleteButton =
            event.target.closest('.delete-coin');

        if (!deleteButton) {
            return;
        }

        const id = Number(deleteButton.dataset.id);

        const coin =
            coins.find(function (item) {
                return item.id === id;
            });


        if (!coin) {
            return;
        }


        if (coin.baseCurrency) {

            alert(
                'لا يمكن حذف العملة الأساسية للنظام.'
            );

            return;

        }


        const confirmed = confirm(
            `هل أنت متأكد من حذف العملة "${coin.name}"؟`
        );


        if (!confirmed) {
            return;
        }


        coins = coins.filter(function (item) {

            return item.id !== id;

        });


        renderCoins();

        alert('تم حذف العملة بنجاح.');

    });


    // =========================================================
    // تشغيل الشاشة أول مرة
    // =========================================================

    renderCoins();

});