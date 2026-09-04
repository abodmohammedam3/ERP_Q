/* =========================================================
   صفوف تفاصيل فاتورة البيع
   ========================================================= */


/* =========================================================
   إضافة صف
   ========================================================= */

window.addSalesRow = function () {

    if (SalesInvoiceState.mode === 'view') return;

    const tbody = document.getElementById('salesInvoiceDetails');
    if (!tbody) return;

    const emptyRow = tbody.querySelector('td[colspan="11"]');
    if (emptyRow) tbody.innerHTML = '';

    const rowCount = tbody.querySelectorAll('.sales-detail-row').length + 1;
    const row = document.createElement('tr');
    row.className = 'sales-detail-row';

    row.innerHTML = `

        <td class="row-num">${rowCount}</td>

        <td>

            <input
                type="text"
                class="form-control form-control-sm row-item"
                placeholder="الصنف"
                disabled
                onclick="openSalesItemModal()"
                onkeydown="salesItemKeyDown(event)"
                oninput="salesItemInput(event)"
            >

        </td>

        <td>

            <input
                type="text"
                class="form-control form-control-sm row-type"
                placeholder="النوع"
                disabled
                onclick="openSalesTypeModal()"
                onkeydown="salesTypeKeyDown(event)"
                oninput="salesTypeInput(event)"
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

            <input
                type="text"
                class="form-control form-control-sm row-unit"
                placeholder="الوحدة"
                readonly
                disabled
                onclick="openSalesUnitModal(this)"
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
                oninput="calculateSalesRow(this)"
            >

        </td>

        <td>

            <input
                type="text"
                class="form-control form-control-sm row-warehouse"
                placeholder="المخزن"
                disabled
                onclick="openSalesWarehouseModal()"
                onkeydown="salesWarehouseKeyDown(event)"
                oninput="salesWarehouseInput(event)"
            >

            <input
                type="hidden"
                class="row-warehouse-id"
            >

        </td>

        <td>

            <input
                type="number"
                class="form-control form-control-sm row-measure"
                value="0"
                min="0"
                step="0.001"
                disabled
                oninput="calculateSalesRow(this)"
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
                oninput="calculateSalesRow(this)"
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
                onclick="removeSalesRow(this)"
                disabled
            >

                <i class="bi bi-trash3"></i>

            </button>

        </td>

    `;

    tbody.appendChild(row);
    enableSalesRow(row);
    updateSalesMeasureHeader(row);
    calculateSalesTotals();

};


/* =========================================================
   تفعيل الصف
   ========================================================= */

window.enableSalesRow = function (row) {

    if (!row) return;

    row.querySelectorAll('input, select, button')
    .forEach(function (element) {

        if (!element.classList.contains('row-total')) {

            element.disabled =
                SalesInvoiceState.mode === 'view';

        }

    });

};


/* =========================================================
   حذف الصف
   ========================================================= */

window.removeSalesRow = function (button) {

    if (SalesInvoiceState.mode === 'view') return;

    const row = button.closest('tr');
    if (row) row.remove();

    renumberSalesRows();
    calculateSalesTotals();

    const tbody = document.getElementById('salesInvoiceDetails');

    if (tbody && tbody.querySelectorAll('.sales-detail-row').length === 0) {

        tbody.innerHTML = `

            <tr>

                <td colspan="11" class="text-center text-muted py-4">
                    لا توجد أصناف مضافة إلى الفاتورة
                </td>

            </tr>

        `;

    }

};


/* =========================================================
   إعادة ترقيم الصفوف
   ========================================================= */

window.renumberSalesRows = function () {

    document
        .querySelectorAll(
            '#salesInvoiceDetails .sales-detail-row'
        )
        .forEach(function (row, index) {

            const number = row.querySelector('.row-num');

            if (number) {

                number.textContent = index + 1;

            }

        });

};