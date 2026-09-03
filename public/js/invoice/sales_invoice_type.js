/* =========================================================
   أنواع الأصناف - فاتورة البيع
   ========================================================= */


/* =========================================================
   الضغط على Enter
   ========================================================= */

window.salesTypeKeyDown = function (event) {

    if (event.key === 'Enter') {

        event.preventDefault();

        SalesInvoiceState.activeRow =
            event.target.closest('tr');

        openSalesTypeModal();

    }

};


/* =========================================================
   مغادرة حقل النوع
   ========================================================= */

window.salesTypeBlur = function (input) {

    if (
        SalesInvoiceState.mode !== 'view' &&
        document.activeElement.id !==
        'salesTypeSearchInput'
    ) {

        SalesInvoiceState.activeRow =
            input.closest('tr');


        setTimeout(function () {

            openSalesTypeModal();

        }, 200);

    }

};


/* =========================================================
   فتح نافذة الأنواع
   ========================================================= */

window.openSalesTypeModal = function () {

    if (
        SalesInvoiceState.mode ===
        'view'
    ) {

        return;

    }


    const value =
        SalesInvoiceState.activeRow
            ?.querySelector(
                '.row-type'
            )
            ?.value || '';


    const search =
        document.getElementById(
            'salesTypeSearchInput'
        );


    if (search) {

        search.value = value;

    }


    SalesInvoiceState.modals.type.show();


    setTimeout(function () {

        if (search) {

            search.focus();

        }

        searchSalesTypes();

    }, 300);

};


/* =========================================================
   البحث عن الأنواع
   ========================================================= */

window.searchSalesTypes = function () {

    const search =
        document.getElementById(
            'salesTypeSearchInput'
        )?.value.trim() || '';


    const tbody =
        document.getElementById(
            'salesTypeResults'
        );


    if (!tbody) {

        return;

    }


    const types = [

        {
            id: 1,
            name: 'عود'
        },

        {
            id: 2,
            name: 'بزغه'
        },

        {
            id: 3,
            name: 'اميال'
        },

        {
            id: 4,
            name: 'نقفه'
        }

    ];


    const results =
        types.filter(function (type) {

            return type.name.includes(search);

        });


    tbody.innerHTML = '';


    if (results.length === 0) {

        tbody.innerHTML = `

            <tr>

                <td
                    colspan="3"
                    class="text-center text-muted py-3"
                >
                    لا توجد نتائج
                </td>

            </tr>

        `;

        return;

    }


    results.forEach(function (type) {

        tbody.innerHTML += `

            <tr>

                <td>
                    ${type.id}
                </td>

                <td>
                    ${type.name}
                </td>

                <td>

                    <button
                        type="button"
                        class="btn btn-sm btn-success"
                        onclick="selectSalesType(
                            '${type.id}',
                            '${type.name}'
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
   اختيار النوع
   ========================================================= */

window.selectSalesType = function (
    id,
    name
) {

    const row =
        SalesInvoiceState.activeRow;


    if (!row) {

        return;

    }


    row.querySelector(
        '.row-type'
    ).value = name;


    SalesInvoiceState.modals.type.hide();

};