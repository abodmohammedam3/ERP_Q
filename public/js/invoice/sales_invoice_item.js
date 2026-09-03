/* =========================================================
   الأصناف - فاتورة البيع
   ========================================================= */


/* =========================================================
   الضغط على Enter
   ========================================================= */

window.salesItemKeyDown = function (event) {

    if (event.key === 'Enter') {

        event.preventDefault();

        SalesInvoiceState.activeRow =
            event.target.closest('tr');

        openSalesItemModal();

    }

};


/* =========================================================
   مغادرة حقل الصنف
   ========================================================= */

window.salesItemBlur = function (input) {

    if (
        SalesInvoiceState.mode !== 'view' &&
        document.activeElement.id !==
        'salesItemSearchInput'
    ) {

        SalesInvoiceState.activeRow =
            input.closest('tr');


        setTimeout(function () {

            openSalesItemModal();

        }, 200);

    }

};


/* =========================================================
   فتح نافذة الأصناف
   ========================================================= */

window.openSalesItemModal = function () {

    if (
        SalesInvoiceState.mode ===
        'view'
    ) {

        return;

    }


    const value =
        SalesInvoiceState.activeRow
            ?.querySelector(
                '.row-item'
            )
            ?.value || '';


    const search =
        document.getElementById(
            'salesItemSearchInput'
        );


    if (search) {

        search.value = value;

    }


    SalesInvoiceState.modals.item.show();


    setTimeout(function () {

        if (search) {

            search.focus();

        }

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


    if (!tbody) {

        return;

    }


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

            return (
                item.name.includes(search) ||
                item.code
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
                    colspan="4"
                    class="text-center text-muted py-3"
                >
                    لا توجد نتائج
                </td>

            </tr>

        `;

        return;

    }


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

};


/* =========================================================
   اختيار الصنف
   ========================================================= */

window.selectSalesItem = function (
    id,
    name,
    code
) {

    const row =
        SalesInvoiceState.activeRow;


    if (!row) {

        return;

    }


    row.querySelector(
        '.row-item'
    ).value = name;


    row.querySelector(
        '.row-code'
    ).value = code;


    SalesInvoiceState.modals.item.hide();

};