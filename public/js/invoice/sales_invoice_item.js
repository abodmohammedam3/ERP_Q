/* =========================================================
   الأصناف - فاتورة البيع
   ========================================================= */


/* =========================================================
   الضغط على Enter أو Tab
   ========================================================= */

window.salesItemKeyDown = function (event) {

    const input = event.target;

    if (event.key === 'Enter') {

        event.preventDefault();

        SalesInvoiceState.activeRow =
            input.closest('tr');

        openSalesItemModal();

    } else if (event.key === 'Tab') {

        if (input.value.trim() !== '') {

            event.preventDefault();

            SalesInvoiceState.activeRow =
                input.closest('tr');

            openSalesItemModal();

        }

    }

};


/* =========================================================
   الكتابة في حقل الصنف
   ========================================================= */

window.salesItemInput = function (event) {

    if (SalesInvoiceState.mode !== 'view') {

        clearTimeout(window.itemInputTimeout);

        window.itemInputTimeout = setTimeout(function() {

            const input = event.target;

            if (input.value.trim() !== '') {

                SalesInvoiceState.activeRow =
                    input.closest('tr');

                openSalesItemModal();

            }

        }, 50);

    }

};


/* =========================================================
   فتح نافذة الأصناف
   ========================================================= */

window.openSalesItemModal = function () {

    if (SalesInvoiceState.mode === 'view') return;

    const row = SalesInvoiceState.activeRow;
    if (!row) return;

    // تعبئة قيمة البحث من الصف الحالي
    const itemName = row.querySelector('.row-item')?.value || '';
    document.getElementById('salesItemSearchInput').value = itemName;

    SalesInvoiceState.modals.item.show();

    setTimeout(function() {

        document.getElementById('salesItemSearchInput').focus();
        searchSalesItems();

    }, 300);

};


/* =========================================================
   البحث عن الأصناف
   ========================================================= */

window.searchSalesItems = function () {

    const search =
        document.getElementById(
            'salesItemSearchInput'
        )?.value.trim() || '';

    const tbody =
        document.getElementById(
            'salesItemResults'
        );

    if (!tbody) return;

    const items = [

        {
            id: 1,
            name: 'صنف ارحبي',
            code: 'ITM-001',
            costPrice: 8000,
            sellPrice: 10000,
            type: 'عود'
        },

        {
            id: 2,
            name: 'ماوية',
            code: 'ITM-002',
            costPrice: 15000,
            sellPrice: 20000,
            type: 'بزغه'
        },

        {
            id: 3,
            name: 'همداني',
            code: 'ITM-003',
            costPrice: 12000,
            sellPrice: 18000,
            type: 'عود'
        },

        {
            id: 4,
            name: 'صعدي',
            code: 'ITM-004',
            costPrice: 9000,
            sellPrice: 14000,
            type: 'نقفه'
        }

    ];

    const results = items.filter(function(item) {

        return item.name.includes(search) ||
               item.code.toLowerCase().includes(search.toLowerCase());

    });

    tbody.innerHTML = '';

    if (results.length === 0) {

        tbody.innerHTML = `

            <tr>

                <td colspan="8" class="text-center text-muted py-3">
                    لا توجد نتائج
                </td>

            </tr>

        `;

        return;

    }

    results.forEach(function(item) {

        tbody.innerHTML += `

            <tr>

                <td>${item.id}</td>
                <td>${item.name}</td>
                <td>${item.type || ''}</td>
                <td>${item.code}</td>
                <td>${item.costPrice}</td>
                <td>${item.sellPrice}</td>
                <td>
                    <input type="number" class="form-control form-control-sm" id="qty_${item.id}" value="1" min="0" step="0.001" style="width:80px; display:inline-block;">
                </td>
                <td>

                    <button
                        type="button"
                        class="btn btn-sm btn-success"
                        onclick="selectSalesItem(
                            '${item.id}',
                            '${item.name}',
                            '${item.code}',
                            '${item.type}',
                            '${item.costPrice}',
                            '${item.sellPrice}'
                        )"
                    >
                        اختيار
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-warning mt-1"
                        onclick="editSalesItemPriceFromTable(
                            '${item.id}',
                            '${item.name}',
                            '${item.sellPrice}'
                        )"
                    >
                        تعديل السعر
                    </button>

                </td>

            </tr>

        `;

    });

};


/* =========================================================
   اختيار الصنف (مع تعبئة جميع الحقول)
   ========================================================= */

window.selectSalesItem = function (
    id,
    name,
    code,
    type,
    costPrice,
    sellPrice
) {

    const row = SalesInvoiceState.activeRow;
    if (!row) return;

    // جلب الكمية من حقل الإدخال في الجدول
    const qtyInput = document.getElementById('qty_' + id);
    const quantity = qtyInput ? qtyInput.value : '1';

    row.querySelector('.row-item').value = name;
    row.querySelector('.row-type').value = type || '';
    row.querySelector('.row-code').value = code;
    row.querySelector('.row-price').value = sellPrice;
    row.querySelector('.row-measure').value = quantity;

    // تعبئة سعر التكلفة في بيانات مخفية
    let costInput = row.querySelector('.row-cost-price');
    if (!costInput) {
        costInput = document.createElement('input');
        costInput.type = 'hidden';
        costInput.className = 'row-cost-price';
        row.appendChild(costInput);
    }
    costInput.value = costPrice;

    // حساب الإجمالي
    calculateSalesRow(row.querySelector('.row-price'));

    SalesInvoiceState.modals.item.hide();

    // التركيز على حقل الوحدة (الحقل التالي)
    const unitInput = row.querySelector('.row-unit');
    if (unitInput) unitInput.focus();

};


/* =========================================================
   تعديل سعر البيع من الجدول (زر في كل صف)
   ========================================================= */

window.editSalesItemPriceFromTable = function (id, name, currentPrice) {

    const newPrice = prompt(
        'تعديل سعر البيع للصنف: ' + name,
        currentPrice
    );

    if (newPrice !== null && !isNaN(newPrice) && parseFloat(newPrice) >= 0) {

        const priceValue = parseFloat(newPrice);

        // تحديث السعر في الصف الحالي
        const row = SalesInvoiceState.activeRow;
        if (row) {
            row.querySelector('.row-price').value = priceValue;
            calculateSalesRow(row.querySelector('.row-price'));
        }

        // تحديث السعر في نافذة الصنف
        document.getElementById('salesItemSellPrice').value = priceValue;

        alert('تم تحديث سعر البيع إلى: ' + priceValue);

    }

};


/* =========================================================
   تعديل سعر البيع من النافذة (الزر القديم)
   ========================================================= */

window.editSalesItemPrice = function () {

    const row = SalesInvoiceState.activeRow;
    if (!row) return;

    const currentPrice =
        row.querySelector('.row-price')?.value || '0';

    const newPrice = prompt('أدخل سعر البيع الجديد:', currentPrice);

    if (newPrice !== null && !isNaN(newPrice) && parseFloat(newPrice) >= 0) {

        const priceValue = parseFloat(newPrice);
        row.querySelector('.row-price').value = priceValue;
        document.getElementById('salesItemSellPrice').value = priceValue;
        calculateSalesRow(row.querySelector('.row-price'));

    }

};