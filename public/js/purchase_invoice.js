/* =========================================================
حالة الشاشة والمتغيرات العامة
========================================================= */

let invoiceMode = 'view';
let lastInvoiceNumber = 0;
let currentInvoiceId = null;

let supplierModal = null;
let currencyModal = null;
let invoiceSearchModal = null;
let warehouseModal = null;
let accountModal = null;
let purchaseItemModal = null;
let typeModal = null;

let activePurchaseRow = null;
let currentTypeInput = null;

// =========================================================
// البيانات التجريبية (سيتم استبدالها بقاعدة البيانات)
// =========================================================

let invoices = [
    {
        id: 1,
        number: '1',
        date: '2026-08-20',
        supplierId: 1,
        supplierName: 'مورد تجريبي 1',
        currencyId: 1,
        currencyName: 'ريال يمني',
        exchangeRate: 1,
        paymentMethod: 'cash',
        paymentAccountId: 1,
        paymentAccountName: 'الصندوق الرئيسي',
        warehouseId: 1,
        warehouseName: 'المخزن الرئيسي',
        statement: 'فاتورة شراء تجريبية',
        reference: 'REF-001',
        expenses: 0,
        tax: 0,
        transportation: 0,
        otherCost: 0,
        otherCostDesc: '',
        items: [
            { itemName: 'صنف ارحبي', type: 'عود', code: 'ITM-001', unit: 'كيلو', quantity: 10, price: 10000, discount: 0, total: 100000 }
        ]
    },
    {
        id: 2,
        number: '2',
        date: '2026-08-21',
        supplierId: 2,
        supplierName: 'مورد تجريبي 2',
        currencyId: 1,
        currencyName: 'ريال يمني',
        exchangeRate: 1,
        paymentMethod: 'bank',
        paymentAccountId: 3,
        paymentAccountName: 'الحساب البنكي الرئيسي',
        warehouseId: 1,
        warehouseName: 'المخزن الرئيسي',
        statement: 'فاتورة شراء ثانية',
        reference: 'REF-002',
        expenses: 5000,
        tax: 2000,
        transportation: 1000,
        otherCost: 0,
        otherCostDesc: '',
        items: [
            { itemName: 'ماوية', type: 'بزغه', code: 'ITM-002', unit: 'حبه', quantity: 5, price: 20000, discount: 1000, total: 99000 }
        ]
    }
];

let suppliers = [
    { id: 1, name: 'مورد تجريبي 1', account: '401001' },
    { id: 2, name: 'مورد تجريبي 2', account: '401002' },
    { id: 3, name: 'مؤسسة التوريدات', account: '401003' }
];

let currencies = [
    { id: 1, name: 'ريال يمني', symbol: 'YER', exchangeRate: 1 },
    { id: 2, name: 'ريال سعودي', symbol: 'SAR', exchangeRate: 140 },
    { id: 3, name: 'دولار أمريكي', symbol: 'USD', exchangeRate: 530 }
];

let warehouses = [
    { id: 1, name: 'المخزن الرئيسي' },
    { id: 2, name: 'مخزن الفرع الأول' }
];

let accounts = {
    cash: [
        { id: 1, name: 'الصندوق الرئيسي', type: 'نقد' },
        { id: 2, name: 'صندوق المبيعات', type: 'نقد' }
    ],
    bank: [
        { id: 3, name: 'الحساب البنكي الرئيسي', type: 'بنك' },
        { id: 4, name: 'حساب بنك الكريمي', type: 'بنك' }
    ],
    network: [
        { id: 5, name: 'محفظة MTN', type: 'شبكة' },
        { id: 6, name: 'محفظة يمن موبايل', type: 'شبكة' }
    ]
};

let purchaseItems = [
    { id: 1, name: 'صنف ارحبي', code: 'ITM-001' },
    { id: 2, name: 'ماوية', code: 'ITM-002' },
    { id: 3, name: 'همداني', code: 'ITM-003' },
    { id: 4, name: 'صعدي', code: 'ITM-004' }
];

let types = ['عود', 'بزغه', 'اميال', 'نقفه'];

// =========================================================
// تهيئة الشاشة
// =========================================================

document.addEventListener('DOMContentLoaded', function () {
    supplierModal = new bootstrap.Modal(document.getElementById('supplierModal'));
    currencyModal = new bootstrap.Modal(document.getElementById('currencyModal'));
    invoiceSearchModal = new bootstrap.Modal(document.getElementById('invoiceSearchModal'));
    warehouseModal = new bootstrap.Modal(document.getElementById('warehouseModal'));
    accountModal = new bootstrap.Modal(document.getElementById('accountModal'));
    purchaseItemModal = new bootstrap.Modal(document.getElementById('purchaseItemModal'));
    typeModal = new bootstrap.Modal(document.getElementById('typeModal'));

    if (invoices.length > 0) {
        const maxId = Math.max(...invoices.map(inv => parseInt(inv.number, 10)));
        lastInvoiceNumber = maxId;
    }

    setInvoiceMode('view');
    clearInvoiceForm();
});

// =========================================================
// دالة مساعدة للانتقال إلى الحقل التالي
// =========================================================

function focusNextField(currentElement) {
    const allInputs = Array.from(document.querySelectorAll(
        'input:not([readonly]):not([disabled]), select:not([disabled])'
    ));
    const visibleInputs = allInputs.filter(el => el.offsetParent !== null);
    const currentIndex = visibleInputs.indexOf(currentElement);
    if (currentIndex !== -1 && currentIndex < visibleInputs.length - 1) {
        visibleInputs[currentIndex + 1].focus();
    }
}

// =========================================================
// تغيير وضع الشاشة
// =========================================================

function setInvoiceMode(mode) {
    invoiceMode = mode;

    const inputs = document.querySelectorAll(
        '#PurchaseInvoicesON2, #PurchaseInvoicesDate2, #PuInPaymentMethod2, ' +
        '#paymentAccount, #supplierName, #currencyName, #PuInExchangeRate2, ' +
        '#warehouseName, #PuInStatement2, #invoiceReference, #PuInExpenses, ' +
        '#PuInTaxCost, #PuInTransportation, #PuInOtherCost, #otherCostDescription'
    );

    inputs.forEach(input => {
        input.disabled = (mode === 'view');
    });

    const rows = document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row');
    rows.forEach(row => enableRow(row));

    const addRowButton = document.getElementById('btnAddInvoiceRow');
    if (addRowButton) addRowButton.disabled = (mode === 'view');

    const saveButton = document.getElementById('btnSaveInvoice');
    const saveNewButton = document.getElementById('btnSaveAndNew');
    const cancelButton = document.getElementById('btnCancelInvoice');
    const editButton = document.getElementById('btnEditInvoice');
    const printButton = document.getElementById('btnPrintInvoice');

    if (mode === 'view') {
        saveButton.disabled = true;
        saveNewButton.disabled = true;
        cancelButton.classList.add('d-none');
        editButton.disabled = !hasInvoiceData();
        printButton.disabled = !hasInvoiceData();
        document.getElementById('paymentAccount').disabled = true;
        document.getElementById('paymentAccountContainer').classList.add('d-none');
    } else {
        saveButton.disabled = false;
        saveNewButton.disabled = false;
        cancelButton.classList.remove('d-none');
        editButton.disabled = true;
        printButton.disabled = true;
        paymentMethodChanged();
    }
}

// =========================================================
// إضافة فاتورة جديدة
// =========================================================

function resetInvoice() {
    clearInvoiceForm();
    setInvoiceMode('add');

    lastInvoiceNumber++;
    document.getElementById('PurchaseInvoicesON2').value = lastInvoiceNumber;

    const today = new Date().toISOString().split('T')[0];
    document.getElementById('PurchaseInvoicesDate2').value = today;

    addInvoiceRow();

    document.getElementById('currencyName').disabled = false;
    document.getElementById('PuInExchangeRate2').disabled = false;
}

// =========================================================
// تفريغ الفاتورة
// =========================================================

function clearInvoiceForm() {
    document.querySelectorAll('#PurchaseInvoicesON2, #PurchaseInvoicesDate2, #PuInPaymentMethod2, ' +
        '#paymentAccount, #supplierName, #currencyName, #PuInExchangeRate2, ' +
        '#warehouseName, #PuInStatement2, #invoiceReference, #PuInExpenses, ' +
        '#PuInTaxCost, #PuInTransportation, #PuInOtherCost, #otherCostDescription')
        .forEach(el => {
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        });

    document.getElementById('suplierID').value = '';
    document.getElementById('coinsID').value = '';
    document.getElementById('warehouseID').value = '';
    document.getElementById('paymentAccountId').value = '';

    document.getElementById('purchaseInvoiceDetails').innerHTML = `
        <tr>
            <td colspan="10" class="text-center text-muted py-4">
                لا توجد أصناف مضافة إلى الفاتورة
            </td>
        </tr>
    `;

    document.getElementById('totalDiscountDisplay').textContent = '0.00';
    document.getElementById('invoiceTotalDisplay').textContent = '0.00';

    hidePaymentAccounts();
    document.getElementById('otherCostDescriptionContainer').classList.add('d-none');
    document.getElementById('otherCostDescription').value = '';

    setInvoiceMode('view');
    currentInvoiceId = null;
}

// =========================================================
// حساب الصف والإجماليات
// =========================================================

function calculateRow(input) {
    const row = input.closest('tr');
    if (!row) return;

    const unit = row.querySelector('.row-unit')?.value || '';
    let quantity = 0;

    if (unit === 'كيلو') {
        quantity = parseFloat(row.querySelector('.row-weight')?.value) || 0;
    } else if (unit === 'حبه') {
        quantity = parseFloat(row.querySelector('.row-quantity')?.value) || 0;
    } else {
        quantity = parseFloat(row.querySelector('.row-quantity')?.value) || 0;
    }

    const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
    const discount = parseFloat(row.querySelector('.row-discount')?.value) || 0;

    const subtotal = quantity * price;
    const total = Math.max(0, subtotal - discount);

    const totalInput = row.querySelector('.row-total');
    if (totalInput) totalInput.value = total.toFixed(2);

    calculateTotals();
}

function calculateTotals() {
    let itemsTotal = 0;
    let totalDiscount = 0;

    document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row').forEach(row => {
        const total = parseFloat(row.querySelector('.row-total')?.value) || 0;
        const discount = parseFloat(row.querySelector('.row-discount')?.value) || 0;
        itemsTotal += total;
        totalDiscount += discount;
    });

    const exchangeRate = parseFloat(document.getElementById('PuInExchangeRate2')?.value) || 1;
    const adjustedItemsTotal = itemsTotal * exchangeRate;

    const expenses = parseFloat(document.getElementById('PuInExpenses')?.value) || 0;
    const tax = parseFloat(document.getElementById('PuInTaxCost')?.value) || 0;
    const transportation = parseFloat(document.getElementById('PuInTransportation')?.value) || 0;
    const otherCost = parseFloat(document.getElementById('PuInOtherCost')?.value) || 0;

    const invoiceTotal = adjustedItemsTotal + expenses + tax + transportation + otherCost;

    document.getElementById('totalDiscountDisplay').textContent = totalDiscount.toFixed(2);
    document.getElementById('invoiceTotalDisplay').textContent = invoiceTotal.toFixed(2);
}

function exchangeRateChanged() {
    calculateTotals();
}

// =========================================================
// إدارة الصفوف
// =========================================================

function addInvoiceRow() {
    if (invoiceMode === 'view') return;

    const tbody = document.getElementById('purchaseInvoiceDetails');
    const emptyRow = tbody.querySelector('td[colspan="10"]');
    if (emptyRow) tbody.innerHTML = '';

    const rowCount = tbody.querySelectorAll('.purchase-detail-row').length + 1;
    const row = document.createElement('tr');
    row.className = 'purchase-detail-row';

    row.innerHTML = `
        <td class="row-num">${rowCount}</td>
        <td>
            <input type="text" class="form-control form-control-sm row-item" placeholder="الصنف" disabled
                   onclick="openPurchaseItemModal(this)" onkeydown="purchaseItemKeyDown(event)" oninput="purchaseItemInput(event)">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm row-type" placeholder="النوع" disabled
                   onclick="openTypeModal(this)" onkeydown="typeKeyDown(event)" oninput="typeInput(event)">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm row-code" placeholder="الرمز" disabled>
        </td>
        <td>
            <select class="form-select form-select-sm row-unit" disabled onchange="unitChanged(this)">
                <option value="كيلو">كيلو</option>
                <option value="حبه">حبه</option>
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm row-weight row-quantity" placeholder="العدد" min="0" step="0.001" disabled oninput="calculateRow(this)">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm row-price" value="0" min="0" step="0.01" disabled oninput="calculateRow(this)">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm row-discount" value="0" min="0" step="0.01" disabled oninput="calculateRow(this)">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm row-total" value="0.00" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)" disabled>
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;

    tbody.appendChild(row);
    enableRow(row);
    calculateTotals();
}

function enableRow(row) {
    if (!row) return;
    row.querySelectorAll('input, select').forEach(el => {
        if (!el.classList.contains('row-total')) {
            el.disabled = (invoiceMode === 'view');
        }
    });
    const deleteBtn = row.querySelector('button');
    if (deleteBtn) deleteBtn.disabled = (invoiceMode === 'view');
}

function removeRow(button) {
    if (invoiceMode === 'view') return;
    const row = button.closest('tr');
    if (row) row.remove();
    renumberRows();
    calculateTotals();

    const tbody = document.getElementById('purchaseInvoiceDetails');
    if (tbody.querySelectorAll('.purchase-detail-row').length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center text-muted py-4">
                    لا توجد أصناف مضافة إلى الفاتورة
                </td>
            </tr>
        `;
    }
}

function renumberRows() {
    document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row').forEach((row, index) => {
        const num = row.querySelector('.row-num');
        if (num) num.textContent = index + 1;
    });
}

function unitChanged(select) {
    const row = select.closest('tr');
    const qtyInput = row.querySelector('.row-weight');
    if (!qtyInput) return;

    if (select.value === 'كيلو') {
        qtyInput.placeholder = 'الوزن';
        qtyInput.step = '0.001';
    } else if (select.value === 'حبه') {
        qtyInput.placeholder = 'الكمية';
        qtyInput.step = '1';
    } else {
        qtyInput.placeholder = 'العدد';
        qtyInput.value = '';
    }
    calculateRow(select);
}

// =========================================================
// طريقة الدفع والحسابات
// =========================================================

function paymentMethodChanged() {
    hidePaymentAccounts();
    const method = document.getElementById('PuInPaymentMethod2').value;
    const container = document.getElementById('paymentAccountContainer');
    const input = document.getElementById('paymentAccount');

    if (!method || method === 'credit') {
        container.classList.add('d-none');
        input.disabled = true;
        input.value = '';
        document.getElementById('paymentAccountId').value = '';
        return;
    }

    container.classList.remove('d-none');
    input.disabled = (invoiceMode === 'view');

    const label = container.querySelector('label');
    if (label) {
        const labels = {
            cash: 'الصندوق',
            bank: 'الحساب البنكي',
            network: 'حساب المحفظة'
        };
        label.textContent = labels[method] || 'الحساب';
    }
}

function hidePaymentAccounts() {
    document.getElementById('paymentAccountContainer').classList.add('d-none');
}

let accountSearchType = '';

// ---- حساب الدفع ----

function accountKeyDown(event) {
    const input = event.target;
    if (event.key === 'Enter') {
        event.preventDefault();
        openAccountModal();
    } else if (event.key === 'Tab') {
        if (input.value.trim() !== '') {
            event.preventDefault();
            openAccountModal();
        }
    }
}

function accountInput(event) {
    if (invoiceMode !== 'view') {
        clearTimeout(window.accountInputTimeout);
        window.accountInputTimeout = setTimeout(() => {
            const input = event.target;
            if (input.value.trim() !== '') {
                openAccountModal();
            }
        }, 50);
    }
}

function openAccountModal() {
    if (invoiceMode === 'view') return;
    const method = document.getElementById('PuInPaymentMethod2').value;
    if (!method || method === 'credit') {
        alert('يرجى اختيار طريقة دفع مناسبة أولاً.');
        return;
    }

    accountSearchType = method;
    const input = document.getElementById('paymentAccount');
    document.getElementById('accountSearchInput').value = input.value;

    accountModal.show();
    setTimeout(() => {
        document.getElementById('accountSearchInput').focus();
        searchAccounts();
    }, 300);
}

function searchAccounts() {
    const search = document.getElementById('accountSearchInput').value.trim();
    const tbody = document.getElementById('accountResults');
    const list = accounts[accountSearchType] || [];
    const results = list.filter(acc => acc.name.includes(search));

    tbody.innerHTML = '';
    if (results.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
        return;
    }

    results.forEach(acc => {
        tbody.innerHTML += `
            <tr>
                <td>${acc.id}</td>
                <td>${acc.name}</td>
                <td>${acc.type}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-success" onclick="selectAccount('${acc.id}','${acc.name}')">
                        اختيار
                    </button>
                </td>
            </tr>
        `;
    });
}

function selectAccount(id, name) {
    document.getElementById('paymentAccountId').value = id;
    document.getElementById('paymentAccount').value = name;
    accountModal.hide();
    document.getElementById('supplierName').focus();
}

// ---- المورد ----

function supplierKeyDown(event) {
    const input = event.target;
    if (event.key === 'Enter') {
        event.preventDefault();
        openSupplierModal();
    } else if (event.key === 'Tab') {
        if (input.value.trim() !== '') {
            event.preventDefault();
            openSupplierModal();
        }
    }
}

function supplierInput(event) {
    if (invoiceMode !== 'view') {
        clearTimeout(window.supplierInputTimeout);
        window.supplierInputTimeout = setTimeout(() => {
            const input = event.target;
            if (input.value.trim() !== '') {
                openSupplierModal();
            }
        }, 50);
    }
}

function openSupplierModal() {
    if (invoiceMode === 'view') return;
    const val = document.getElementById('supplierName').value;
    document.getElementById('supplierSearchInput').value = val;
    supplierModal.show();
    setTimeout(() => {
        document.getElementById('supplierSearchInput').focus();
        searchSuppliers();
    }, 300);
}

function searchSuppliers() {
    const search = document.getElementById('supplierSearchInput').value.trim();
    const tbody = document.getElementById('supplierResults');
    const results = suppliers.filter(s => s.name.includes(search) || s.account.includes(search));

    tbody.innerHTML = '';
    if (results.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
        return;
    }

    results.forEach(s => {
        tbody.innerHTML += `
            <tr>
                <td>${s.id}</td>
                <td>${s.name}</td>
                <td>${s.account}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-success" onclick="selectSupplier('${s.id}','${s.name}')">
                        اختيار
                    </button>
                </td>
            </tr>
        `;
    });
}

function selectSupplier(id, name) {
    document.getElementById('suplierID').value = id;
    document.getElementById('supplierName').value = name;
    supplierModal.hide();
    document.getElementById('currencyName').focus();
}

// ---- العملة ----

function currencyKeyDown(event) {
    const input = event.target;
    if (event.key === 'Enter') {
        event.preventDefault();
        openCurrencyModal();
    } else if (event.key === 'Tab') {
        if (input.value.trim() !== '') {
            event.preventDefault();
            openCurrencyModal();
        }
    }
}

function currencyInput(event) {
    if (invoiceMode !== 'view') {
        clearTimeout(window.currencyInputTimeout);
        window.currencyInputTimeout = setTimeout(() => {
            const input = event.target;
            if (input.value.trim() !== '') {
                openCurrencyModal();
            }
        }, 50);
    }
}

function openCurrencyModal() {
    if (invoiceMode === 'view') return;
    const val = document.getElementById('currencyName').value;
    document.getElementById('currencySearchInput').value = val;
    currencyModal.show();
    setTimeout(() => {
        document.getElementById('currencySearchInput').focus();
        searchCurrencies();
    }, 300);
}

function searchCurrencies() {
    const search = document.getElementById('currencySearchInput').value.trim();
    const tbody = document.getElementById('currencyResults');
    const results = currencies.filter(c => c.name.includes(search) || c.symbol.includes(search));

    tbody.innerHTML = '';
    if (results.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
        return;
    }

    results.forEach(c => {
        tbody.innerHTML += `
            <tr>
                <td>${c.id}</td>
                <td>${c.name}</td>
                <td>${c.symbol}</td>
                <td>${c.exchangeRate}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-success" onclick="selectCurrency('${c.id}','${c.name}','${c.exchangeRate}')">
                        اختيار
                    </button>
                </td>
            </tr>
        `;
    });
}

function selectCurrency(id, name, exchangeRate) {
    document.getElementById('coinsID').value = id;
    document.getElementById('currencyName').value = name;
    document.getElementById('PuInExchangeRate2').value = exchangeRate;
    currencyModal.hide();
    document.getElementById('warehouseName').focus();
    calculateTotals();
}

// ---- المخزن ----

function warehouseKeyDown(event) {
    const input = event.target;
    if (event.key === 'Enter') {
        event.preventDefault();
        openWarehouseModal();
    } else if (event.key === 'Tab') {
        if (input.value.trim() !== '') {
            event.preventDefault();
            openWarehouseModal();
        }
    }
}

function warehouseInput(event) {
    if (invoiceMode !== 'view') {
        clearTimeout(window.warehouseInputTimeout);
        window.warehouseInputTimeout = setTimeout(() => {
            const input = event.target;
            if (input.value.trim() !== '') {
                openWarehouseModal();
            }
        }, 50);
    }
}

function openWarehouseModal() {
    if (invoiceMode === 'view') return;
    const val = document.getElementById('warehouseName').value;
    document.getElementById('warehouseSearchInput').value = val;
    warehouseModal.show();
    setTimeout(() => {
        document.getElementById('warehouseSearchInput').focus();
        searchWarehouses();
    }, 300);
}

function searchWarehouses() {
    const search = document.getElementById('warehouseSearchInput').value.trim();
    const tbody = document.getElementById('warehouseResults');
    const results = warehouses.filter(w => w.name.includes(search));

    tbody.innerHTML = '';
    if (results.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
        return;
    }

    results.forEach(w => {
        tbody.innerHTML += `
            <tr>
                <td>${w.id}</td>
                <td>${w.name}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-success" onclick="selectWarehouse('${w.id}','${w.name}')">
                        اختيار
                    </button>
                </td>
            </tr>
        `;
    });
}

function selectWarehouse(id, name) {
    document.getElementById('warehouseID').value = id;
    document.getElementById('warehouseName').value = name;
    warehouseModal.hide();

    // التركيز على حقل "الصنف" في أول صف من الجدول
    const firstRow = document.querySelector('#purchaseInvoiceDetails .purchase-detail-row');
    if (firstRow) {
        const itemInput = firstRow.querySelector('.row-item');
        if (itemInput) itemInput.focus();
    } else {
        // إذا لم يوجد صف، نضيف صفاً ثم نركز
        addInvoiceRow();
        setTimeout(() => {
            const newRow = document.querySelector('#purchaseInvoiceDetails .purchase-detail-row');
            if (newRow) {
                const itemInput = newRow.querySelector('.row-item');
                if (itemInput) itemInput.focus();
            }
        }, 100);
    }
}

// ---- الأصناف (في جدول التفاصيل) ----

function purchaseItemKeyDown(event) {
    const input = event.target;
    if (event.key === 'Enter') {
        event.preventDefault();
        activePurchaseRow = input.closest('tr');
        openPurchaseItemModal();
    } else if (event.key === 'Tab') {
        if (input.value.trim() !== '') {
            event.preventDefault();
            activePurchaseRow = input.closest('tr');
            openPurchaseItemModal();
        }
    }
}

function purchaseItemInput(event) {
    if (invoiceMode !== 'view') {
        clearTimeout(window.itemInputTimeout);
        window.itemInputTimeout = setTimeout(() => {
            const input = event.target;
            if (input.value.trim() !== '') {
                activePurchaseRow = input.closest('tr');
                openPurchaseItemModal();
            }
        }, 50);
    }
}

function openPurchaseItemModal(input) {
    if (invoiceMode === 'view') return;
    if (input) activePurchaseRow = input.closest('tr');
    if (!activePurchaseRow) return;

    const val = activePurchaseRow.querySelector('.row-item')?.value || '';
    document.getElementById('purchaseItemSearchInput').value = val;
    purchaseItemModal.show();
    setTimeout(() => {
        document.getElementById('purchaseItemSearchInput').focus();
        searchPurchaseItems();
    }, 300);
}

function searchPurchaseItems() {
    const search = document.getElementById('purchaseItemSearchInput').value.trim();
    const tbody = document.getElementById('purchaseItemResults');
    const results = purchaseItems.filter(item => item.name.includes(search) || item.code.includes(search));

    tbody.innerHTML = '';
    if (results.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
        return;
    }

    results.forEach(item => {
        tbody.innerHTML += `
            <tr>
                <td>${item.id}</td>
                <td>${item.name}</td>
                <td>${item.code}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-success" onclick="selectPurchaseItem('${item.id}','${item.name}','${item.code}')">
                        اختيار
                    </button>
                </td>
            </tr>
        `;
    });
}

function selectPurchaseItem(id, name, code) {
    if (!activePurchaseRow) return;
    activePurchaseRow.querySelector('.row-item').value = name;
    activePurchaseRow.querySelector('.row-code').value = code;
    purchaseItemModal.hide();
    // التركيز على حقل النوع
    const typeInput = activePurchaseRow.querySelector('.row-type');
    if (typeInput) typeInput.focus();
}

// ---- الأنواع ----

function typeKeyDown(event) {
    const input = event.target;
    if (event.key === 'Enter') {
        event.preventDefault();
        currentTypeInput = input;
        openTypeModal();
    } else if (event.key === 'Tab') {
        if (input.value.trim() !== '') {
            event.preventDefault();
            currentTypeInput = input;
            openTypeModal();
        }
    }
}

function typeInput(event) {
    if (invoiceMode !== 'view') {
        clearTimeout(window.typeInputTimeout);
        window.typeInputTimeout = setTimeout(() => {
            const input = event.target;
            if (input.value.trim() !== '') {
                currentTypeInput = input;
                openTypeModal();
            }
        }, 50);
    }
}

function openTypeModal(input) {
    if (invoiceMode === 'view') return;
    currentTypeInput = input || window.currentTypeInput || document.activeElement;
    if (!currentTypeInput || !currentTypeInput.classList.contains('row-type')) return;

    const val = currentTypeInput.value || '';
    document.getElementById('typeSearchInput').value = val;
    typeModal.show();
    setTimeout(() => {
        document.getElementById('typeSearchInput').focus();
        searchTypes();
    }, 300);
}

function searchTypes() {
    const search = document.getElementById('typeSearchInput').value.trim();
    const tbody = document.getElementById('typeResults');
    const results = types.filter(t => t.includes(search));

    tbody.innerHTML = '';
    if (results.length === 0) {
        tbody.innerHTML = `<tr><td colspan="2" class="text-center text-muted py-3">لا توجد نتائج</td></tr>`;
        return;
    }

    results.forEach(type => {
        tbody.innerHTML += `
            <tr>
                <td>${type}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-success" onclick="selectType('${type}')">
                        اختيار
                    </button>
                </td>
            </tr>
        `;
    });
}

function selectType(name) {
    if (currentTypeInput) {
        currentTypeInput.value = name;
    }
    typeModal.hide();
    // التركيز على حقل الرمز (بدلاً من الوحدة)
    if (currentTypeInput) {
        const row = currentTypeInput.closest('tr');
        const codeInput = row.querySelector('.row-code');
        if (codeInput) codeInput.focus();
    }
}

// =========================================================
// التكاليف الأخرى
// =========================================================

function otherCostChanged() {
    const value = parseFloat(document.getElementById('PuInOtherCost').value) || 0;
    const container = document.getElementById('otherCostDescriptionContainer');
    const desc = document.getElementById('otherCostDescription');

    if (value > 0) {
        container.classList.remove('d-none');
        desc.disabled = (invoiceMode === 'view');
    } else {
        container.classList.add('d-none');
        desc.value = '';
    }
    calculateTotals();
}

// =========================================================
// البحث عن الفاتورة
// =========================================================

function searchInvoice() {
    document.getElementById('invoiceSearchInput').value = '';
    document.getElementById('invoiceSearchResults').innerHTML = `
        <tr>
            <td colspan="7" class="text-center text-muted py-4">
                أدخل بيانات البحث ثم اضغط بحث
            </td>
        </tr>
    `;
    invoiceSearchModal.show();
    setTimeout(() => {
        document.getElementById('invoiceSearchInput').focus();
    }, 300);
}

function performInvoiceSearch() {
    const search = document.getElementById('invoiceSearchInput').value.trim();
    const tbody = document.getElementById('invoiceSearchResults');
    const results = invoices.filter(inv =>
        inv.number.includes(search) ||
        inv.supplierName.includes(search)
    );

    tbody.innerHTML = '';
    if (results.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">لا توجد فواتير مطابقة للبحث</td></tr>`;
        return;
    }

    results.forEach(inv => {
        const total = inv.items.reduce((sum, item) => sum + item.total, 0);
        tbody.innerHTML += `
            <tr>
                <td>${inv.number}</td>
                <td>${inv.date}</td>
                <td>${inv.supplierName}</td>
                <td>${inv.currencyName}</td>
                <td>${inv.paymentMethod}</td>
                <td>${total.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-primary" onclick="loadInvoice(${inv.id})">
                        <i class="bi bi-eye"></i> عرض
                    </button>
                </td>
            </tr>
        `;
    });
}

function loadInvoice(invoiceId) {
    const invoice = invoices.find(inv => inv.id === invoiceId);
    if (!invoice) {
        alert('الفاتورة غير موجودة');
        return;
    }

    clearInvoiceForm();
    setInvoiceMode('view');

    // تعبئة البيانات
    document.getElementById('PurchaseInvoicesON2').value = invoice.number;
    document.getElementById('PurchaseInvoicesDate2').value = invoice.date;
    document.getElementById('suplierID').value = invoice.supplierId;
    document.getElementById('supplierName').value = invoice.supplierName;
    document.getElementById('coinsID').value = invoice.currencyId;
    document.getElementById('currencyName').value = invoice.currencyName;
    document.getElementById('PuInExchangeRate2').value = invoice.exchangeRate;
    document.getElementById('warehouseID').value = invoice.warehouseId;
    document.getElementById('warehouseName').value = invoice.warehouseName;
    document.getElementById('PuInPaymentMethod2').value = invoice.paymentMethod;
    document.getElementById('paymentAccountId').value = invoice.paymentAccountId || '';
    document.getElementById('paymentAccount').value = invoice.paymentAccountName || '';
    document.getElementById('PuInStatement2').value = invoice.statement || '';
    document.getElementById('invoiceReference').value = invoice.reference || '';
    document.getElementById('PuInExpenses').value = invoice.expenses || 0;
    document.getElementById('PuInTaxCost').value = invoice.tax || 0;
    document.getElementById('PuInTransportation').value = invoice.transportation || 0;
    document.getElementById('PuInOtherCost').value = invoice.otherCost || 0;
    document.getElementById('otherCostDescription').value = invoice.otherCostDesc || '';

    if (invoice.otherCost > 0) {
        document.getElementById('otherCostDescriptionContainer').classList.remove('d-none');
        document.getElementById('otherCostDescription').disabled = true;
    }

    paymentMethodChanged();

    const tbody = document.getElementById('purchaseInvoiceDetails');
    tbody.innerHTML = '';
    if (invoice.items && invoice.items.length > 0) {
        invoice.items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.className = 'purchase-detail-row';
            row.innerHTML = `
                <td class="row-num">${index + 1}</td>
                <td><input type="text" class="form-control form-control-sm row-item" value="${item.itemName}" disabled></td>
                <td><input type="text" class="form-control form-control-sm row-type" value="${item.type || ''}" disabled></td>
                <td><input type="text" class="form-control form-control-sm row-code" value="${item.code || ''}" disabled></td>
                <td>
                    <select class="form-select form-select-sm row-unit" disabled>
                        <option value="كيلو" ${item.unit === 'كيلو' ? 'selected' : ''}>كيلو</option>
                        <option value="حبه" ${item.unit === 'حبه' ? 'selected' : ''}>حبه</option>
                    </select>
                </td>
                <td><input type="number" class="form-control form-control-sm row-weight row-quantity" value="${item.quantity}" disabled></td>
                <td><input type="number" class="form-control form-control-sm row-price" value="${item.price}" disabled></td>
                <td><input type="number" class="form-control form-control-sm row-discount" value="${item.discount || 0}" disabled></td>
                <td><input type="number" class="form-control form-control-sm row-total" value="${item.total.toFixed(2)}" readonly></td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger" disabled>
                        <i class="bi bi-trash3"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">لا توجد أصناف مضافة إلى الفاتورة</td></tr>`;
    }

    calculateTotals();
    invoiceSearchModal.hide();
    currentInvoiceId = invoice.id;
}

// =========================================================
// عمليات الفاتورة (تعديل، حفظ، إلغاء، طباعة)
// =========================================================

function editInvoice() {
    if (!hasInvoiceData()) {
        alert('لا توجد فاتورة محددة للتعديل.');
        return;
    }
    setInvoiceMode('edit');
    document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row').forEach(row => enableRow(row));
    document.querySelectorAll('#PurchaseInvoicesDate2, #PuInPaymentMethod2, #paymentAccount, #supplierName, #currencyName, #PuInExchangeRate2, #warehouseName, #PuInStatement2, #invoiceReference, #PuInExpenses, #PuInTaxCost, #PuInTransportation, #PuInOtherCost, #otherCostDescription')
        .forEach(el => el.disabled = false);
    paymentMethodChanged();
}

function hasInvoiceData() {
    return document.getElementById('PurchaseInvoicesON2').value.trim() !== '';
}

function cancelInvoice() {
    if (!confirm('هل أنت متأكد من إلغاء العملية؟ سيتم مسح البيانات الحالية.')) return;
    clearInvoiceForm();
}

function saveInvoice() {
    const number = document.getElementById('PurchaseInvoicesON2').value.trim();
    if (!number) {
        alert('رقم الفاتورة مطلوب.');
        return;
    }

    const supplier = document.getElementById('supplierName').value.trim();
    if (!supplier) {
        alert('يرجى اختيار المورد.');
        return;
    }

    const currency = document.getElementById('currencyName').value.trim();
    if (!currency) {
        alert('يرجى اختيار العملة.');
        return;
    }

    const rows = document.querySelectorAll('#purchaseInvoiceDetails .purchase-detail-row');
    if (rows.length === 0) {
        alert('يرجى إضافة صنف واحد على الأقل.');
        return;
    }

    const invoiceData = {
        id: currentInvoiceId || Date.now(),
        number: number,
        date: document.getElementById('PurchaseInvoicesDate2').value,
        supplierId: document.getElementById('suplierID').value,
        supplierName: supplier,
        currencyId: document.getElementById('coinsID').value,
        currencyName: currency,
        exchangeRate: parseFloat(document.getElementById('PuInExchangeRate2').value) || 1,
        paymentMethod: document.getElementById('PuInPaymentMethod2').value,
        paymentAccountId: document.getElementById('paymentAccountId').value,
        paymentAccountName: document.getElementById('paymentAccount').value,
        warehouseId: document.getElementById('warehouseID').value,
        warehouseName: document.getElementById('warehouseName').value,
        statement: document.getElementById('PuInStatement2').value,
        reference: document.getElementById('invoiceReference').value,
        expenses: parseFloat(document.getElementById('PuInExpenses').value) || 0,
        tax: parseFloat(document.getElementById('PuInTaxCost').value) || 0,
        transportation: parseFloat(document.getElementById('PuInTransportation').value) || 0,
        otherCost: parseFloat(document.getElementById('PuInOtherCost').value) || 0,
        otherCostDesc: document.getElementById('otherCostDescription').value,
        items: []
    };

    rows.forEach(row => {
        invoiceData.items.push({
            itemName: row.querySelector('.row-item').value,
            type: row.querySelector('.row-type').value,
            code: row.querySelector('.row-code').value,
            unit: row.querySelector('.row-unit').value,
            quantity: parseFloat(row.querySelector('.row-weight').value) || 0,
            price: parseFloat(row.querySelector('.row-price').value) || 0,
            discount: parseFloat(row.querySelector('.row-discount').value) || 0,
            total: parseFloat(row.querySelector('.row-total').value) || 0
        });
    });

    const existingIndex = invoices.findIndex(inv => inv.id === invoiceData.id);
    if (existingIndex !== -1) {
        invoices[existingIndex] = invoiceData;
    } else {
        invoices.push(invoiceData);
        if (parseInt(invoiceData.number) > lastInvoiceNumber) {
            lastInvoiceNumber = parseInt(invoiceData.number);
        }
    }

    alert(invoiceMode === 'edit' ? 'تم تعديل الفاتورة بنجاح.' : 'تم حفظ الفاتورة بنجاح.');
    setInvoiceMode('view');
    currentInvoiceId = invoiceData.id;
}

function saveAndNewInvoice() {
    saveInvoice();
    resetInvoice();
}

function printInvoice() {
    if (!hasInvoiceData()) {
        alert('لا توجد فاتورة للطباعة.');
        return;
    }
    window.print();
}