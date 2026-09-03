/* =========================================================
حالة الشاشة
========================================================= */

let invoiceMode = 'view';

let supplierModal = null;
let currencyModal = null;
let invoiceSearchModal = null;

/* =========================================================
تهيئة الشاشة
========================================================= */

document.addEventListener('DOMContentLoaded', function () {

supplierModal = new bootstrap.Modal(
    document.getElementById('supplierModal')
);

currencyModal = new bootstrap.Modal(
    document.getElementById('currencyModal')
);

invoiceSearchModal = new bootstrap.Modal(
    document.getElementById('invoiceSearchModal')
);

setInvoiceMode('view');

clearInvoiceForm();

});

/* =========================================================
تغيير وضع الشاشة
========================================================= */

function setInvoiceMode(mode) {

invoiceMode = mode;

const inputs = document.querySelectorAll(
    '#PurchaseInvoicesON2,' +
    '#PurchaseInvoicesDate2,' +
    '#PuInPaymentMethod2,' +
    '#cashAccount,' +
    '#bankAccount,' +
    '#walletAccount,' +
    '#supplierName,' +
    '#currencyName,' +
    '#PuInExchangeRate2,' +
    '#warehouseName,' +
    '#PuInStatement2,' +
    '#invoiceReference,' +
    '#PuInExpenses,' +
    '#PuInTaxCost,' +
    '#PuInTransportation,' +
    '#PuInOtherCost,' +
    '#otherCostDescription'
);

inputs.forEach(function (input) {

    input.disabled = mode === 'view';

});


const addRowButton =
    document.getElementById('btnAddInvoiceRow');

if (addRowButton) {
    addRowButton.disabled = mode === 'view';
}


const saveButton =
    document.getElementById('btnSaveInvoice');

const saveNewButton =
    document.getElementById('btnSaveAndNew');

const cancelButton =
    document.getElementById('btnCancelInvoice');

const editButton =
    document.getElementById('btnEditInvoice');

const printButton =
    document.getElementById('btnPrintInvoice');


if (mode === 'view') {

    saveButton.disabled = true;
    saveNewButton.disabled = true;
    cancelButton.classList.add('d-none');

    editButton.disabled = !hasInvoiceData();
    printButton.disabled = !hasInvoiceData();

}


else if (mode === 'add') {

    saveButton.disabled = false;
    saveNewButton.disabled = false;

    cancelButton.classList.remove('d-none');

    editButton.disabled = true;
    printButton.disabled = true;

}


else if (mode === 'edit') {

    saveButton.disabled = false;
    saveNewButton.disabled = false;

    cancelButton.classList.remove('d-none');

    editButton.disabled = true;
    printButton.disabled = false;

}

}

/* =========================================================
إضافة فاتورة جديدة
========================================================= */

function resetInvoice() {

clearInvoiceForm();

setInvoiceMode('add');

addInvoiceRow();

}

/* =========================================================
تفريغ الفاتورة
========================================================= */

function clearInvoiceForm() {

document.querySelectorAll(
    'input, textarea'
).forEach(function (element) {

    if (
        element.type !== 'button' &&
        element.type !== 'submit'
    ) {

        element.value = '';

    }

});


document.querySelectorAll('select')
    .forEach(function (select) {

        select.selectedIndex = 0;

    });


document.getElementById(
    'purchaseInvoiceDetails'
).innerHTML = `

    <tr>

        <td
            colspan="10"
            class="text-center text-muted py-4"
        >
            لا توجد أصناف مضافة إلى الفاتورة
        </td>

    </tr>

`;


document.getElementById(
    'totalDiscountDisplay'
).textContent = '0.00';


document.getElementById(
    'invoiceTotalDisplay'
).textContent = '0.00';


hidePaymentAccounts();


document
    .getElementById('otherCostDescriptionContainer')
    .classList.add('d-none');


document
    .getElementById('otherCostDescription')
    .value = '';


setInvoiceMode('view');

}

/* =========================================================
حساب تفاصيل الصف
========================================================= */

function calculateRow(input) {

const row = input.closest('tr');

if (!row) {
    return;
}


const unit =
    row.querySelector('.row-unit')?.value || '';


let quantity = 0;


if (unit === 'كيلو') {

    quantity =
        parseFloat(
            row.querySelector('.row-weight')?.value
        ) || 0;

} else {

    quantity =
        parseFloat(
            row.querySelector('.row-quantity')?.value
        ) || 0;

}


const price =
    parseFloat(
        row.querySelector('.row-price')?.value
    ) || 0;


const discount =
    parseFloat(
        row.querySelector('.row-discount')?.value
    ) || 0;


const subtotal =
    quantity * price;


const total =
    Math.max(0, subtotal - discount);


const totalInput =
    row.querySelector('.row-total');


if (totalInput) {

    totalInput.value =
        total.toFixed(2);

}


calculateTotals();

}

/* =========================================================
حساب إجماليات الفاتورة
========================================================= */

function calculateTotals() {

let itemsTotal = 0;

let totalDiscount = 0;


document
    .querySelectorAll('.purchase-detail-row')
    .forEach(function (row) {

        const total =
            parseFloat(
                row.querySelector('.row-total')?.value
            ) || 0;


        const discount =
            parseFloat(
                row.querySelector('.row-discount')?.value
            ) || 0;


        itemsTotal += total;

        totalDiscount += discount;

    });


const expenses =
    parseFloat(
        document.getElementById('PuInExpenses')?.value
    ) || 0;


const tax =
    parseFloat(
        document.getElementById('PuInTaxCost')?.value
    ) || 0;


const transportation =
    parseFloat(
        document.getElementById(
            'PuInTransportation'
        )?.value
    ) || 0;


const otherCost =
    parseFloat(
        document.getElementById('PuInOtherCost')?.value
    ) || 0;


const invoiceTotal =
    itemsTotal +
    expenses +
    tax +
    transportation +
    otherCost;


document.getElementById(
    'totalDiscountDisplay'
).textContent =
    totalDiscount.toFixed(2);


document.getElementById(
    'invoiceTotalDisplay'
).textContent =
    invoiceTotal.toFixed(2);

}

/* =========================================================
إضافة صف
========================================================= */

function addInvoiceRow() {

if (invoiceMode === 'view') {
    return;
}


const tbody =
    document.getElementById(
        'purchaseInvoiceDetails'
    );


const emptyRow =
    tbody.querySelector(
        'td[colspan="10"]'
    );


if (emptyRow) {

    tbody.innerHTML = '';

}


const rowCount =
    tbody.querySelectorAll(
        '.purchase-detail-row'
    ).length + 1;


const row =
    document.createElement('tr');


row.className =
    'purchase-detail-row';


row.innerHTML = `

    <td class="row-num">
        ${rowCount}
    </td>


    <td>

        <input
            type="text"
            class="form-control form-control-sm row-item"
            placeholder="الصنف"
            disabled
            onclick="openItemModal(this)"
            onkeydown="itemKeyDown(event)"
            onblur="itemBlur()"
        >

    </td>


    <td>

        <input
            type="text"
            class="form-control form-control-sm row-type"
            placeholder="النوع"
            disabled
            onclick="openTypeModal(this)"
            onkeydown="typeKeyDown(event)"
            onblur="typeBlur()"
        >

    </td>


    <td>

        <input
            type="text"
            class="form-control form-control-sm row-code"
            placeholder="الرمز"
            disabled
        >

    </td>


    <td>

        <select
            class="form-select form-select-sm row-unit"
            disabled
            onchange="unitChanged(this)"
        >

            <option value="">
                الوحدة
            </option>

            <option value="كيلو">
                كيلو
            </option>

            <option value="حبه">
                حبه
            </option>

        </select>

    </td>


    <td>

        <input
            type="number"
            class="form-control form-control-sm row-weight row-quantity"
            placeholder="الوزن / العدد"
            min="0"
            step="0.001"
            disabled
            oninput="calculateRow(this)"
        >

    </td>


    <td>

        <input
            type="number"
            class="form-control form-control-sm row-price"
            value="0"
            min="0"
            step="0.01"
            disabled
            oninput="calculateRow(this)"
        >

    </td>


    <td>

        <input
            type="number"
            class="form-control form-control-sm row-discount"
            value="0"
            min="0"
            step="0.01"
            disabled
            oninput="calculateRow(this)"
        >

    </td>


    <td>

        <input
            type="number"
            class="form-control form-control-sm row-total"
            value="0.00"
            readonly
        >

    </td>


    <td>

        <button
            type="button"
            class="btn btn-sm btn-outline-danger"
            onclick="removeRow(this)"
            disabled
        >

            <i class="bi bi-trash3"></i>

        </button>

    </td>

`;


tbody.appendChild(row);


enableRow(row);


calculateTotals();

}

/* =========================================================
تفعيل صف
========================================================= */

function enableRow(row) {

if (!row) {
    return;
}


row.querySelectorAll('input, select')
    .forEach(function (element) {

        if (!element.classList.contains('row-total')) {

            element.disabled =
                invoiceMode === 'view';

        }

    });


const deleteButton =
    row.querySelector('button');


if (deleteButton) {

    deleteButton.disabled =
        invoiceMode === 'view';

}

}

/* =========================================================
حذف صف
========================================================= */

function removeRow(button) {

if (invoiceMode === 'view') {
    return;
}


const row =
    button.closest('tr');


if (row) {

    row.remove();

}


renumberRows();

calculateTotals();


const tbody =
    document.getElementById(
        'purchaseInvoiceDetails'
    );


if (
    tbody.querySelectorAll(
        '.purchase-detail-row'
    ).length === 0
) {

    tbody.innerHTML = `

        <tr>

            <td
                colspan="10"
                class="text-center text-muted py-4"
            >
                لا توجد أصناف مضافة إلى الفاتورة
            </td>

        </tr>

    `;

}

}

/* =========================================================
إعادة ترقيم الصفوف
========================================================= */

function renumberRows() {

document
    .querySelectorAll(
        '#purchaseInvoiceDetails .purchase-detail-row'
    )
    .forEach(function (row, index) {

        const number =
            row.querySelector('.row-num');

        if (number) {

            number.textContent =
                index + 1;

        }

    });

}

/* =========================================================
تغيير الوحدة
========================================================= */

function unitChanged(select) {

const row =
    select.closest('tr');


const quantityInput =
    row.querySelector('.row-weight');


if (!quantityInput) {
    return;
}


if (select.value === 'كيلو') {

    quantityInput.placeholder = 'الوزن';

    quantityInput.step = '0.001';


}

else if (select.value === 'حبه') {

    quantityInput.placeholder = 'العدد';

    quantityInput.step = '1';


}

else {

    quantityInput.placeholder =
        'الوزن / العدد';

    quantityInput.value = '';

}


calculateRow(select);

}

/* =========================================================
طريقة الدفع
========================================================= */

function paymentMethodChanged() {

hidePaymentAccounts();


const method =
    document.getElementById(
        'PuInPaymentMethod2'
    ).value;


if (method === 'cash') {

    document
        .getElementById(
            'cashAccountContainer'
        )
        .classList.remove('d-none');

}


else if (method === 'bank') {

    document
        .getElementById(
            'bankAccountContainer'
        )
        .classList.remove('d-none');

}


else if (method === 'network') {

    document
        .getElementById(
            'walletAccountContainer'
        )
        .classList.remove('d-none');

}

}

/* =========================================================
إخفاء حسابات الدفع
========================================================= */

function hidePaymentAccounts() {

document
    .getElementById('cashAccountContainer')
    .classList.add('d-none');


document
    .getElementById('bankAccountContainer')
    .classList.add('d-none');


document
    .getElementById('walletAccountContainer')
    .classList.add('d-none');

}

/* =========================================================
المورد
========================================================= */

function supplierKeyDown(event) {

if (event.key === 'Enter') {

    event.preventDefault();

    openSupplierModal();

}

}

function supplierBlur() {

if (
    invoiceMode !== 'view' &&
    document.activeElement.id !== 'supplierSearchInput'
) {

    setTimeout(function () {

        openSupplierModal();

    }, 200);

}

}

/* فتح نافذة المورد عند الضغط على الحقل */

function openSupplierModal() {

if (invoiceMode === 'view') {
    return;
}


const value =
    document.getElementById(
        'supplierName'
    ).value;


document.getElementById(
    'supplierSearchInput'
).value = value;


supplierModal.show();


setTimeout(function () {

    document
        .getElementById(
            'supplierSearchInput'
        )
        .focus();

    searchSuppliers();

}, 300);

}

function searchSuppliers() {

const search =
    document.getElementById(
        'supplierSearchInput'
    ).value.trim();


const tbody =
    document.getElementById(
        'supplierResults'
    );


const suppliers = [

    {
        id: 1,
        name: 'مورد تجريبي 1',
        account: '401001'
    },

    {
        id: 2,
        name: 'مورد تجريبي 2',
        account: '401002'
    },

    {
        id: 3,
        name: 'مؤسسة التوريدات',
        account: '401003'
    }

];


const results =
    suppliers.filter(function (supplier) {

        return (
            supplier.name.includes(search) ||
            supplier.account.includes(search)
        );

    });


tbody.innerHTML = '';


if (results.length === 0) {

    tbody.innerHTML = `

        <tr>

            <td
                colspan="4"
                class="text-center text-muted py-3"
            >
                لا توجد نتائج
            </td>

        </tr>

    `;

    return;

}


results.forEach(function (supplier) {

    tbody.innerHTML += `

        <tr>

            <td>${supplier.id}</td>

            <td>${supplier.name}</td>

            <td>${supplier.account}</td>

            <td>

                <button
                    type="button"
                    class="btn btn-sm btn-success"
                    onclick="selectSupplier(
                        '${supplier.id}',
                        '${supplier.name}',
                        '${supplier.account}'
                    )"
                >
                    اختيار
                </button>

            </td>

        </tr>

    `;

});

}

function selectSupplier(id, name, account) {

document.getElementById(
    'suplierID'
).value = id;


document.getElementById(
    'supplierName'
).value = name;


supplierModal.hide();

}

/* =========================================================
العملة
========================================================= */

function currencyKeyDown(event) {

if (event.key === 'Enter') {

    event.preventDefault();

    openCurrencyModal();

}

}

function currencyBlur() {

if (
    invoiceMode !== 'view' &&
    document.activeElement.id !== 'currencySearchInput'
) {

    setTimeout(function () {

        openCurrencyModal();

    }, 200);

}

}

function openCurrencyModal() {

if (invoiceMode === 'view') {
    return;
}


const value =
    document.getElementById(
        'currencyName'
    ).value;


document.getElementById(
    'currencySearchInput'
).value = value;


currencyModal.show();


setTimeout(function () {

    document
        .getElementById(
            'currencySearchInput'
        )
        .focus();

    searchCurrencies();

}, 300);

}

function searchCurrencies() {

const search =
    document.getElementById(
        'currencySearchInput'
    ).value.trim();


const tbody =
    document.getElementById(
        'currencyResults'
    );


const currencies = [

    {
        id: 1,
        name: 'ريال يمني',
        symbol: 'YER',
        exchangeRate: 1
    },

    {
        id: 2,
        name: 'ريال سعودي',
        symbol: 'SAR',
        exchangeRate: 140
    },

    {
        id: 3,
        name: 'دولار أمريكي',
        symbol: 'USD',
        exchangeRate: 530
    }

];


const results =
    currencies.filter(function (currency) {

        return (
            currency.name.includes(search) ||
            currency.symbol
                .toLowerCase()
                .includes(search.toLowerCase())
        );

    });


tbody.innerHTML = '';


if (results.length === 0) {

    tbody.innerHTML = `

        <tr>

            <td
                colspan="5"
                class="text-center text-muted py-3"
            >
                لا توجد نتائج
            </td>

        </tr>

    `;

    return;

}


results.forEach(function (currency) {

    tbody.innerHTML += `

        <tr>

            <td>
                ${currency.id}
            </td>

            <td>
                ${currency.name}
            </td>

            <td>
                ${currency.symbol}
            </td>

            <td>
                ${currency.exchangeRate}
            </td>

            <td>

                <button
                    type="button"
                    class="btn btn-sm btn-success"
                    onclick="selectCurrency(
                        '${currency.id}',
                        '${currency.name}',
                        '${currency.exchangeRate}'
                    )"
                >
                    اختيار
                </button>

            </td>

        </tr>

    `;

});

}

function selectCurrency(id, name, exchangeRate) {

document.getElementById(
    'coinsID'
).value = id;


document.getElementById(
    'currencyName'
).value = name;


document.getElementById(
    'PuInExchangeRate2'
).value = exchangeRate;


currencyModal.hide();

}

/* =========================================================
المخزن
========================================================= */

function warehouseKeyDown(event) {

if (event.key === 'Enter') {

    event.preventDefault();

    alert(
        'سيتم فتح نافذة اختيار المخزن هنا عند ربط قاعدة البيانات.'
    );

}

}

function warehouseBlur() {

/*
 * مخصص لاحقاً لفتح نافذة المخازن تلقائياً.
 */

}

/* =========================================================
الأصناف
========================================================= */

function salesItemKeyDown(event) {

    if (event.key === 'Enter') {

        event.preventDefault();

        activeSalesRow =
            event.target.closest('tr');

        openSalesItemModal();

    }

}


function salesItemBlur(input) {

    if (
        salesInvoiceMode !== 'view' &&
        document.activeElement.id !==
        'salesItemSearchInput'
    ) {

        activeSalesRow =
            input.closest('tr');


        setTimeout(function () {

            openSalesItemModal();

        }, 200);

    }

}


function openSalesItemModal() {

    if (salesInvoiceMode === 'view') {

        return;

    }


    const value =
        activeSalesRow
            ?.querySelector(
                '.row-item'
            )
            ?.value || '';


    document.getElementById(
        'salesItemSearchInput'
    ).value = value;


    salesItemModal.show();


    setTimeout(function () {

        document
            .getElementById(
                'salesItemSearchInput'
            )
            .focus();

        searchSalesItems();

    }, 300);

}


function searchSalesItems() {

    const search =
        document.getElementById(
            'salesItemSearchInput'
        ).value.trim();


    const tbody =
        document.getElementById(
            'salesItemResults'
        );


    const items = [

        {
            id: 1,
            name: 'صنف ارحبي',
            code: 'ITM-001'
        },

        {
            id: 2,
            name: 'ماوية',
            code: 'ITM-002'
        },

        {
            id: 3,
            name: 'همداني',
            code: 'ITM-003'
        },

        {
            id: 4,
            name: 'صعدي',
            code: 'ITM-004'
        }

    ];


    const results =
        items.filter(function (item) {

            return item.name.includes(search);

        });


    tbody.innerHTML = '';


    results.forEach(function (item) {

        tbody.innerHTML += `

            <tr>

                <td>
                    ${item.id}
                </td>

                <td>
                    ${item.name}
                </td>

                <td>
                    ${item.code}
                </td>

                <td>

                    <button
                        type="button"
                        class="btn btn-sm btn-success"
                        onclick="selectSalesItem(
                            '${item.id}',
                            '${item.name}',
                            '${item.code}'
                        )"
                    >
                        اختيار
                    </button>

                </td>

            </tr>

        `;

    });


    if (results.length === 0) {

        tbody.innerHTML = `

            <tr>

                <td
                    colspan="4"
                    class="text-center text-muted py-3"
                >
                    لا توجد نتائج
                </td>

            </tr>

        `;

    }

}


function selectSalesItem(id, name, code) {

    if (!activeSalesRow) {

        return;

    }


    activeSalesRow.querySelector(
        '.row-item'
    ).value = name;


    activeSalesRow.querySelector(
        '.row-code'
    ).value = code;


    salesItemModal.hide();

}


/* =========================================================
الأنواع
========================================================= */

function typeKeyDown(event) {

if (event.key === 'Enter') {

    event.preventDefault();

    openTypeModal(event.target);

}

}

function typeBlur() {

/*
 * مخصص لاحقاً.
 */

}

function openTypeModal(input) {

if (invoiceMode === 'view') {
    return;
}


const types = [

    'عود',
    'بزغه',
    'اميال',
    'نقفه'

];


const search =
    input.value.trim();


const results =
    types.filter(function (type) {

        return type.includes(search);

    });


let html = `

    <div class="modal fade" id="typeSelectionModal" tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">
                        اختيار النوع
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <table class="table table-bordered table-hover">

                        <thead class="table-light">

                            <tr class="text-center">
                                <th>النوع</th>
                                <th>اختيار</th>
                            </tr>

                        </thead>

                        <tbody>
`;


if (results.length === 0) {

    html += `

        <tr>

            <td
                colspan="2"
                class="text-center text-muted"
            >
                لا توجد نتائج
            </td>

        </tr>

    `;

}


results.forEach(function (type) {

    html += `

        <tr>

            <td>
                ${type}
            </td>

            <td>

                <button
                    type="button"
                    class="btn btn-sm btn-success"
                    onclick="selectType('${type}')"
                >
                    اختيار
                </button>

            </td>

        </tr>

    `;

});


html += `

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

`;


const oldModal =
    document.getElementById(
        'typeSelectionModal'
    );


if (oldModal) {
    oldModal.remove();
}


document.body.insertAdjacentHTML(
    'beforeend',
    html
);


window.currentTypeInput = input;


const modal =
    new bootstrap.Modal(
        document.getElementById(
            'typeSelectionModal'
        )
    );


modal.show();


document
    .getElementById(
        'typeSelectionModal'
    )
    .addEventListener(
        'hidden.bs.modal',
        function () {

            this.remove();

        }
    );

}

function selectType(name) {

if (window.currentTypeInput) {

    window.currentTypeInput.value = name;

}


const modalElement =
    document.getElementById(
        'typeSelectionModal'
    );


if (modalElement) {

    const modal =
        bootstrap.Modal.getInstance(
            modalElement
        );

    if (modal) {
        modal.hide();
    }

}

}

/* =========================================================
التكاليف الأخرى
========================================================= */

function otherCostChanged() {

const value =
    parseFloat(
        document.getElementById(
            'PuInOtherCost'
        ).value
    ) || 0;


const container =
    document.getElementById(
        'otherCostDescriptionContainer'
    );


const description =
    document.getElementById(
        'otherCostDescription'
    );


if (value > 0) {

    container.classList.remove('d-none');

    description.disabled =
        invoiceMode === 'view';

}

else {

    container.classList.add('d-none');

    description.value = '';

}


calculateTotals();

}

/* =========================================================
تعديل الفاتورة
========================================================= */

function editInvoice() {

if (!hasInvoiceData()) {

    alert(
        'لا توجد فاتورة محددة للتعديل.'
    );

    return;

}


setInvoiceMode('edit');


document
    .querySelectorAll(
        '#purchaseInvoiceDetails .purchase-detail-row'
    )
    .forEach(function (row) {

        enableRow(row);

    });

}

/* =========================================================
التحقق من وجود فاتورة
========================================================= */

function hasInvoiceData() {

const invoiceNumber =
    document.getElementById(
        'PurchaseInvoicesON2'
    ).value.trim();


return invoiceNumber !== '';

}

/* =========================================================
إلغاء الفاتورة
========================================================= */

function cancelInvoice() {

if (
    !confirm(
        'هل أنت متأكد من إلغاء العملية؟ سيتم مسح البيانات الحالية.'
    )
) {

    return;

}


clearInvoiceForm();

}

/* =========================================================
حفظ الفاتورة
========================================================= */

function saveInvoice() {

const invoiceNumber =
    document.getElementById(
        'PurchaseInvoicesON2'
    ).value.trim();


if (invoiceNumber === '') {

    alert(
        'يرجى إدخال رقم الفاتورة.'
    );

    return;

}


const supplier =
    document.getElementById(
        'supplierName'
    ).value.trim();


if (supplier === '') {

    alert(
        'يرجى اختيار المورد.'
    );

    return;

}


const rows =
    document.querySelectorAll(
        '#purchaseInvoiceDetails .purchase-detail-row'
    );


if (rows.length === 0) {

    alert(
        'يرجى إضافة صنف واحد على الأقل.'
    );

    return;

}


alert(
    invoiceMode === 'edit'
        ? 'تم تعديل الفاتورة بنجاح.'
        : 'تم حفظ الفاتورة بنجاح.'
);


setInvoiceMode('view');

}

/* =========================================================
حفظ وإضافة فاتورة
========================================================= */

function saveAndNewInvoice() {

saveInvoice();

}

/* =========================================================
طباعة الفاتورة
========================================================= */

function printInvoice() {

if (!hasInvoiceData()) {

    alert(
        'لا توجد فاتورة للطباعة.'
    );

    return;

}


window.print();

}

/* =========================================================
البحث عن الفاتورة
========================================================= */

function searchInvoice() {

document.getElementById(
    'invoiceSearchInput'
).value = '';


document.getElementById(
    'invoiceSearchResults'
).innerHTML = `

    <tr>

        <td
            colspan="7"
            class="text-center text-muted py-4"
        >
            أدخل بيانات البحث ثم اضغط بحث
        </td>

    </tr>

`;


invoiceSearchModal.show();


setTimeout(function () {

    document
        .getElementById(
            'invoiceSearchInput'
        )
        .focus();

}, 300);

}

/* =========================================================
تنفيذ البحث
========================================================= */

function performInvoiceSearch() {

const search =
    document.getElementById(
        'invoiceSearchInput'
    ).value.trim();


const tbody =
    document.getElementById(
        'invoiceSearchResults'
    );


const invoices = [

    {
        id: 1,
        number: 'PUR-0001',
        date: '2026-08-20',
        supplier: 'مورد تجريبي 1',
        currency: 'ريال يمني',
        payment: 'نقد',
        total: '150000'
    },

    {
        id: 2,
        number: 'PUR-0002',
        date: '2026-08-21',
        supplier: 'مورد تجريبي 2',
        currency: 'ريال يمني',
        payment: 'أجل',
        total: '225000'
    },

    {
        id: 3,
        number: 'PUR-0003',
        date: '2026-08-22',
        supplier: 'مؤسسة التوريدات',
        currency: 'ريال سعودي',
        payment: 'تحويل بنكي',
        total: '320000'
    }

];


const results =
    invoices.filter(function (invoice) {

        return (
            invoice.number
                .toLowerCase()
                .includes(search.toLowerCase()) ||

            invoice.supplier
                .toLowerCase()
                .includes(search.toLowerCase())
        );

    });


tbody.innerHTML = '';


if (results.length === 0) {

    tbody.innerHTML = `

        <tr>

            <td
                colspan="7"
                class="text-center text-muted py-4"
            >
                لا توجد فواتير مطابقة للبحث
            </td>

        </tr>

    `;

    return;

}


results.forEach(function (invoice) {

    tbody.innerHTML += `

        <tr>

            <td>${invoice.number}</td>

            <td>${invoice.date}</td>

            <td>${invoice.supplier}</td>

            <td>${invoice.currency}</td>

            <td>${invoice.payment}</td>

            <td>${invoice.total}</td>

            <td>

                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    onclick="loadInvoice(${invoice.id})"
                >

                    <i class="bi bi-eye"></i>
                    عرض

                </button>

            </td>

        </tr>

    `;

});

}

/* =========================================================
تحميل الفاتورة المختارة
========================================================= */

function loadInvoice(invoiceId) {

clearInvoiceForm();


document.getElementById(
    'PurchaseInvoicesON2'
).value = 'PUR-0001';


document.getElementById(
    'PurchaseInvoicesDate2'
).value = '2026-08-20';


document.getElementById(
    'supplierName'
).value = 'مورد تجريبي 1';


document.getElementById(
    'suplierID'
).value = '1';


document.getElementById(
    'currencyName'
).value = 'ريال يمني';


document.getElementById(
    'coinsID'
).value = '1';


document.getElementById(
    'PuInExchangeRate2'
).value = '1';


document.getElementById(
    'warehouseName'
).value = 'المخزن الرئيسي';


document.getElementById(
    'warehouseID'
).value = '1';


document.getElementById(
    'PuInPaymentMethod2'
).value = 'cash';


paymentMethodChanged();


document.getElementById(
    'cashAccount'
).value = 'main';


document.getElementById(
    'PuInStatement2'
).value = 'فاتورة شراء تجريبية';


document.getElementById(
    'invoiceReference'
).value = 'REF-001';


document.getElementById(
    'PuInExpenses'
).value = '0';


document.getElementById(
    'PuInTaxCost'
).value = '0';


document.getElementById(
    'PuInTransportation'
).value = '0';


document.getElementById(
    'PuInOtherCost'
).value = '0';


const tbody =
    document.getElementById(
        'purchaseInvoiceDetails'
    );


tbody.innerHTML = '';


addReadOnlyInvoiceRow(
    tbody,
    1
);


calculateTotals();


invoiceSearchModal.hide();


setInvoiceMode('view');

}

/* =========================================================
إضافة صف للفاتورة المعروضة
========================================================= */

function addReadOnlyInvoiceRow(tbody, number) {

const row =
    document.createElement('tr');


row.className =
    'purchase-detail-row';


row.innerHTML = `

    <td>
        ${number}
    </td>

    <td>

        <input
            type="text"
            class="form-control form-control-sm row-item"
            value="صنف ارحبي"
            disabled
        >

    </td>

    <td>

        <input
            type="text"
            class="form-control form-control-sm row-type"
            value="عود"
            disabled
        >

    </td>

    <td>

        <input
            type="text"
            class="form-control form-control-sm row-code"
            value="ITM-001"
            disabled
        >

    </td>

    <td>

        <select
            class="form-select form-select-sm row-unit"
            disabled
        >

            <option selected>
                كيلو
            </option>

        </select>

    </td>

    <td>

        <input
            type="number"
            class="form-control form-control-sm row-weight"
            value="10"
            disabled
        >

    </td>

    <td>

        <input
            type="number"
            class="form-control form-control-sm row-price"
            value="10000"
            disabled
        >

    </td>

    <td>

        <input
            type="number"
            class="form-control form-control-sm row-discount"
            value="0"
            disabled
        >

    </td>

    <td>

        <input
            type="number"
            class="form-control form-control-sm row-total"
            value="100000"
            readonly
        >

    </td>

    <td>

        <button
            type="button"
            class="btn btn-sm btn-outline-danger"
            onclick="removeRow(this)"
            disabled
        >

            <i class="bi bi-trash3"></i>

        </button>

    </td>

`;


tbody.appendChild(row);

}