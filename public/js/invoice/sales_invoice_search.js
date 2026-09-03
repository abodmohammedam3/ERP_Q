/* =========================================================
   البحث عن فاتورة البيع
   ========================================================= */


/* =========================================================
   فتح نافذة البحث
   ========================================================= */

window.searchSalesInvoice = function () {

    const input =
        document.getElementById(
            'salesInvoiceSearchInput'
        );


    const tbody =
        document.getElementById(
            'salesInvoiceSearchResults'
        );


    if (input) {

        input.value = '';

    }


    if (tbody) {

        tbody.innerHTML = `

            <tr>

                <td
                    colspan="7"
                    class="text-center text-muted py-4"
                >
                    أدخل بيانات البحث ثم اضغط بحث
                </td>

            </tr>

        `;

    }


    SalesInvoiceState.modals.search.show();


    setTimeout(function () {

        if (input) {

            input.focus();

        }

    }, 300);

};


/* =========================================================
   تنفيذ البحث
   ========================================================= */

window.performSalesInvoiceSearch = function () {

    const search =
        document.getElementById(
            'salesInvoiceSearchInput'
        )?.value.trim() || '';


    const tbody =
        document.getElementById(
            'salesInvoiceSearchResults'
        );


    if (!tbody) {

        return;

    }


    const invoices = [

        {
            id: 1,
            number: 'SAL-0001',
            date: '2026-08-20',
            customer: 'مؤسسة الأفق التجارية',
            currency: 'ريال يمني',
            payment: 'نقد',
            total: '150000'
        },

        {
            id: 2,
            number: 'SAL-0002',
            date: '2026-08-21',
            customer: 'تاجر المستقبل',
            currency: 'ريال سعودي',
            payment: 'أجل',
            total: '225000'
        },

        {
            id: 3,
            number: 'SAL-0003',
            date: '2026-08-22',
            customer: 'مؤسسة النور',
            currency: 'دولار أمريكي',
            payment: 'تحويل بنكي',
            total: '320000'
        }

    ];


    const results =
        invoices.filter(function (invoice) {

            return (
                invoice.number
                    .toLowerCase()
                    .includes(
                        search.toLowerCase()
                    ) ||

                invoice.customer
                    .toLowerCase()
                    .includes(
                        search.toLowerCase()
                    )

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

                <td>
                    ${invoice.number}
                </td>

                <td>
                    ${invoice.date}
                </td>

                <td>
                    ${invoice.customer}
                </td>

                <td>
                    ${invoice.currency}
                </td>

                <td>
                    ${invoice.payment}
                </td>

                <td>
                    ${invoice.total}
                </td>

                <td>

                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        onclick="loadSalesInvoice(
                            ${invoice.id}
                        )"
                    >

                        <i class="bi bi-eye"></i>
                        عرض

                    </button>

                </td>

            </tr>

        `;

    });

};


/* =========================================================
   تحميل الفاتورة
   ========================================================= */

window.loadSalesInvoice = function (invoiceId) {

    clearSalesInvoiceForm();


    document.getElementById(
        'SalesInvoiceNo'
    ).value =
        'SAL-0001';


    document.getElementById(
        'SalesInvoiceDate'
    ).value =
        '2026-08-20';


    document.getElementById(
        'customerName'
    ).value =
        'مؤسسة الأفق التجارية';


    document.getElementById(
        'customerID'
    ).value =
        '201';


    document.getElementById(
        'salesCurrencyName'
    ).value =
        'ريال يمني';


    document.getElementById(
        'salesCoinsID'
    ).value =
        '1';


    document.getElementById(
        'SalesExchangeRate'
    ).value =
        '1';


    document.getElementById(
        'SalesPaymentMethod'
    ).value =
        'cash';


    salesPaymentMethodChanged();


    document.getElementById(
        'salesCashAccount'
    ).value =
        'main';


    document.getElementById(
        'SalesStatement'
    ).value =
        'فاتورة بيع تجريبية';


    document.getElementById(
        'SalesReference'
    ).value =
        'REF-SAL-001';


    const tbody =
        document.getElementById(
            'salesInvoiceDetails'
        );


    if (tbody) {

        tbody.innerHTML = '';

        addReadOnlySalesRow(
            tbody,
            1
        );

    }


    calculateSalesTotals();


    SalesInvoiceState.modals.search.hide();


    setSalesInvoiceMode('view');

};


/* =========================================================
   صف للعرض
   ========================================================= */

window.addReadOnlySalesRow = function (
    tbody,
    number
) {

    const row =
        document.createElement('tr');


    row.className =
        'sales-detail-row';


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

            <input
                type="text"
                class="form-control form-control-sm row-unit"
                value="كيلو"
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

            <input
                type="number"
                class="form-control form-control-sm row-measure"
                value="10"
                disabled
            >

        </td>

        <td>

            <input
                type="text"
                class="form-control form-control-sm row-warehouse"
                value="المخزن الرئيسي"
                disabled
            >

        </td>

        <td>

            <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                disabled
            >

                <i class="bi bi-trash3"></i>

            </button>

        </td>

    `;


    tbody.appendChild(row);


    updateSalesMeasureHeader(row);

};