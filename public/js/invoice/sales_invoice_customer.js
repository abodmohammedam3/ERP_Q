/* =========================================================
   العملاء - فاتورة البيع
   ========================================================= */


/* =========================================================
   الضغط على Enter
   ========================================================= */

window.customerKeyDown = function (event) {

    if (event.key === 'Enter') {

        event.preventDefault();

        openCustomerModal();

    }

};


/* =========================================================
   مغادرة حقل العميل
   ========================================================= */

window.customerBlur = function () {

    if (
        SalesInvoiceState.mode !== 'view' &&
        document.activeElement.id !==
        'customerSearchInput'
    ) {

        setTimeout(function () {

            openCustomerModal();

        }, 200);

    }

};


/* =========================================================
   فتح نافذة العملاء
   ========================================================= */

window.openCustomerModal = function () {

    if (
        SalesInvoiceState.mode ===
        'view'
    ) {

        return;

    }


    const value =
        document.getElementById(
            'customerName'
        )?.value || '';


    const search =
        document.getElementById(
            'customerSearchInput'
        );


    if (search) {

        search.value = value;

    }


    SalesInvoiceState.modals.customer.show();


    setTimeout(function () {

        if (search) {

            search.focus();

        }

        searchCustomers();

    }, 300);

};


/* =========================================================
   البحث عن العملاء
   ========================================================= */

window.searchCustomers = function () {

    const search =
        document.getElementById(
            'customerSearchInput'
        )?.value.trim() || '';


    const tbody =
        document.getElementById(
            'customerResults'
        );


    if (!tbody) {

        return;

    }


    const customers = [

        {
            id: 201,
            name: 'مؤسسة الأفق التجارية',
            account: '301001'
        },

        {
            id: 202,
            name: 'تاجر المستقبل',
            account: '301002'
        },

        {
            id: 203,
            name: 'مؤسسة النور',
            account: '301003'
        }

    ];


    const results =
        customers.filter(function (customer) {

            return (
                customer.name.includes(search) ||
                customer.account.includes(search)
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


    results.forEach(function (customer) {

        tbody.innerHTML += `

            <tr>

                <td>
                    ${customer.id}
                </td>

                <td>
                    ${customer.name}
                </td>

                <td>
                    ${customer.account}
                </td>

                <td>

                    <button
                        type="button"
                        class="btn btn-sm btn-success"
                        onclick="selectCustomer(
                            '${customer.id}',
                            '${customer.name}'
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
   اختيار العميل
   ========================================================= */

window.selectCustomer = function (id, name) {

    const customerID =
        document.getElementById(
            'customerID'
        );


    const customerName =
        document.getElementById(
            'customerName'
        );


    if (customerID) {

        customerID.value = id;

    }


    if (customerName) {

        customerName.value = name;

    }


    SalesInvoiceState.modals.customer.hide();

};