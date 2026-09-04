/* =========================================================
   العملات - فاتورة البيع
   ========================================================= */


/* =========================================================
   الضغط على Enter أو Tab
   ========================================================= */

window.salesCurrencyKeyDown = function (event) {

    const input = event.target;

    if (event.key === 'Enter') {

        event.preventDefault();
        openSalesCurrencyModal();

    } else if (event.key === 'Tab') {

        if (input.value.trim() !== '') {

            event.preventDefault();
            openSalesCurrencyModal();

        }

    }

};


/* =========================================================
   الكتابة في حقل العملة
   ========================================================= */

window.salesCurrencyInput = function (event) {

    if (SalesInvoiceState.mode !== 'view') {

        clearTimeout(window.currencyInputTimeout);

        window.currencyInputTimeout = setTimeout(function() {

            const input = event.target;

            if (input.value.trim() !== '') {

                openSalesCurrencyModal();

            }

        }, 50);

    }

};


/* =========================================================
   فتح نافذة العملات
   ========================================================= */

window.openSalesCurrencyModal = function () {

    if (SalesInvoiceState.mode === 'view') return;

    const value = document.getElementById('salesCurrencyName')?.value || '';
    const search = document.getElementById('salesCurrencySearchInput');

    if (search) search.value = value;

    SalesInvoiceState.modals.currency.show();

    setTimeout(function() {

        if (search) search.focus();
        searchSalesCurrencies();

    }, 300);

};


/* =========================================================
   البحث عن العملات
   ========================================================= */

window.searchSalesCurrencies = function () {

    const search =
        document.getElementById('salesCurrencySearchInput')?.value.trim() || '';

    const tbody = document.getElementById('salesCurrencyResults');

    if (!tbody) return;

    const currencies = [

        { id: 1, name: 'ريال يمني', symbol: 'YER', rate: 1 },
        { id: 2, name: 'ريال سعودي', symbol: 'SAR', rate: 140 },
        { id: 3, name: 'دولار أمريكي', symbol: 'USD', rate: 530 }

    ];

    const results = currencies.filter(function(currency) {

        return currency.name.includes(search) ||
               currency.symbol.toLowerCase().includes(search.toLowerCase());

    });

    tbody.innerHTML = '';

    if (results.length === 0) {

        tbody.innerHTML = `

            <tr>

                <td colspan="5" class="text-center text-muted py-3">
                    لا توجد نتائج
                </td>

            </tr>

        `;

        return;

    }

    results.forEach(function(currency) {

        tbody.innerHTML += `

            <tr>

                <td>${currency.id}</td>
                <td>${currency.name}</td>
                <td>${currency.symbol}</td>
                <td>${currency.rate}</td>
                <td>

                    <button
                        type="button"
                        class="btn btn-sm btn-success"
                        onclick="selectSalesCurrency(
                            '${currency.id}',
                            '${currency.name}',
                            '${currency.rate}'
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
   اختيار العملة
   ========================================================= */

window.selectSalesCurrency = function (id, name, rate) {

    document.getElementById('salesCoinsID').value = id;
    document.getElementById('salesCurrencyName').value = name;
    document.getElementById('SalesExchangeRate').value = rate;

    SalesInvoiceState.modals.currency.hide();

    // الانتقال إلى حقل سعر الصرف (ثم المخزن)
    document.getElementById('SalesExchangeRate').focus();

    // إعادة حساب الإجماليات
    if (typeof calculateSalesTotals === 'function') {
        calculateSalesTotals();
    }

};