/* =========================================================
   المخازن - فاتورة البيع
   ========================================================= */


/* =========================================================
   الضغط على Enter أو Tab
   ========================================================= */

window.salesWarehouseKeyDown = function (event) {

    const input = event.target;

    if (event.key === 'Enter') {

        event.preventDefault();

        SalesInvoiceState.activeRow =
            input.closest('tr');

        openSalesWarehouseModal();

    } else if (event.key === 'Tab') {

        if (input.value.trim() !== '') {

            event.preventDefault();

            SalesInvoiceState.activeRow =
                input.closest('tr');

            openSalesWarehouseModal();

        }

    }

};


/* =========================================================
   الكتابة في حقل المخزن
   ========================================================= */

window.salesWarehouseInput = function (event) {

    if (SalesInvoiceState.mode !== 'view') {

        clearTimeout(window.warehouseInputTimeout);

        window.warehouseInputTimeout = setTimeout(function() {

            const input = event.target;

            if (input.value.trim() !== '') {

                SalesInvoiceState.activeRow =
                    input.closest('tr');

                openSalesWarehouseModal();

            }

        }, 50);

    }

};


/* =========================================================
   فتح نافذة المخازن
   ========================================================= */

window.openSalesWarehouseModal = function () {

    if (SalesInvoiceState.mode === 'view') return;

    const value =
        SalesInvoiceState.activeRow
            ?.querySelector('.row-warehouse')?.value || '';

    const search =
        document.getElementById('salesWarehouseSearchInput');

    if (search) search.value = value;

    SalesInvoiceState.modals.warehouse.show();

    setTimeout(function() {

        if (search) search.focus();
        searchSalesWarehouses();

    }, 300);

};


/* =========================================================
   البحث عن المخازن
   ========================================================= */

window.searchSalesWarehouses = function () {

    const search =
        document.getElementById('salesWarehouseSearchInput')?.value.trim() || '';

    const tbody = document.getElementById('salesWarehouseResults');

    if (!tbody) return;

    const warehouses = [

        { id: 1, name: 'المخزن الرئيسي' },
        { id: 2, name: 'المخزن الفرعي' },
        { id: 3, name: 'مخزن المبيعات' }

    ];

    const results = warehouses.filter(function(warehouse) {

        return warehouse.name.includes(search);

    });

    tbody.innerHTML = '';

    if (results.length === 0) {

        tbody.innerHTML = `

            <tr>

                <td colspan="3" class="text-center text-muted py-3">
                    لا توجد نتائج
                </td>

            </tr>

        `;

        return;

    }

    results.forEach(function(warehouse) {

        tbody.innerHTML += `

            <tr>

                <td>${warehouse.id}</td>
                <td>${warehouse.name}</td>
                <td>

                    <button
                        type="button"
                        class="btn btn-sm btn-success"
                        onclick="selectSalesWarehouse(
                            '${warehouse.id}',
                            '${warehouse.name}'
                        )"
                    >
                        اختيار
                    </button>

                </td>

            </tr>

        `;

    });

};


/* =========================================================
   اختيار المخزن
   ========================================================= */

window.selectSalesWarehouse = function (id, name) {

    const row = SalesInvoiceState.activeRow;

    if (!row) return;

    const warehouse = row.querySelector('.row-warehouse');
    const warehouseID = row.querySelector('.row-warehouse-id');

    if (warehouse) warehouse.value = name;
    if (warehouseID) warehouseID.value = id;

    SalesInvoiceState.modals.warehouse.hide();

    // التركيز على حقل العدد (الحقل التالي)
    const measureInput = row.querySelector('.row-measure');
    if (measureInput) measureInput.focus();

};