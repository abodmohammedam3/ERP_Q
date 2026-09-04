/* =========================================================
   العملاء - فاتورة البيع
   ========================================================= */


/* =========================================================
   الضغط على Enter أو Tab
   ========================================================= */

window.customerKeyDown = function (event) {

    const input = event.target;

    if (event.key === 'Enter') {

        event.preventDefault();
        openCustomerModal();

    } else if (event.key === 'Tab') {

        if (input.value.trim() !== '') {

            event.preventDefault();
            openCustomerModal();

        }

    }

};


/* =========================================================
   الكتابة في حقل العميل
   ========================================================= */

window.customerInput = function (event) {

    if (SalesInvoiceState.mode !== 'view') {

        clearTimeout(window.customerInputTimeout);

        window.customerInputTimeout = setTimeout(function() {

            const input = event.target;

            if (input.value.trim() !== '') {

                openCustomerModal();

            }

        }, 50);

    }

};


/* =========================================================
   فتح نافذة العملاء
   ========================================================= */

window.openCustomerModal = function () {

    if (SalesInvoiceState.mode === 'view') return;

    const value = document.getElementById('customerName')?.value || '';
    const search = document.getElementById('customerSearchInput');

    if (search) search.value = value;

    SalesInvoiceState.modals.customer.show();

    setTimeout(function() {

        if (search) search.focus();
        searchCustomers();

    }, 300);

};


/* =========================================================
   البحث عن العملاء
   ========================================================= */

window.searchCustomers = function () {

    const search =
        document.getElementById('customerSearchInput')?.value.trim() || '';

    const tbody = document.getElementById('customerResults');

    if (!tbody) return;

    const customers = [

        { id: 201, name: 'مؤسسة الأفق التجارية', account: '301001' },
        { id: 202, name: 'تاجر المستقبل', account: '301002' },
        { id: 203, name: 'مؤسسة النور', account: '301003' }

    ];

    const results = customers.filter(function(customer) {

        return customer.name.includes(search) ||
               customer.account.includes(search);

    });

    tbody.innerHTML = '';

    if (results.length === 0) {

        tbody.innerHTML = `

            <tr>

                <td colspan="4" class="text-center text-muted py-3">
                    لا توجد نتائج
                </td>

            </tr>

        `;

        return;

    }

    results.forEach(function(customer) {

        tbody.innerHTML += `

            <tr>

                <td>${customer.id}</td>
                <td>${customer.name}</td>
                <td>${customer.account}</td>
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

    document.getElementById('customerID').value = id;
    document.getElementById('customerName').value = name;

    SalesInvoiceState.modals.customer.hide();

    // الانتقال إلى حقل العملة
    document.getElementById('salesCurrencyName').focus();

};