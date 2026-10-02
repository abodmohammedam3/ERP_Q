/**

* ═══════════════════════════════════════════════════════════
* صفحة سند القبض
* ═══════════════════════════════════════════════════════════
  */

import '../receipt/accountPicker.js';

if (
window.AccountPicker &&
typeof window.AccountPicker.setUrl === 'function'
) {
window.AccountPicker.setUrl(
'/operation/accounting/receiptVouchers/picker'
);
}

function initReceiptVoucherPage() {

console.log(
    '🚀 بدء تهيئة صفحة سند القبض'
);

function toast(
    message,
    type = 'info'
) {
    if (
        typeof showSystemToast ===
        'function'
    ) {
        showSystemToast(
            message,
            type
        );
    } else {
        console.log(
            `[${type}] ${message}`
        );
    }
}

function toastInfo(message) {
    toast(message, 'info');
}

function toastSuccess(message) {
    toast(message, 'success');
}

function toastWarning(message) {
    toast(message, 'warning');
}

function toastError(message) {
    toast(message, 'danger');
}

if (
    typeof Modals !== 'undefined' &&
    Modals.initAll
) {
    Modals.initAll();
}

const state =
    new StateManager(
        'receipt-voucher'
    );

let saveInProgress = false;

let lastSaveTime = 0;

const MIN_SAVE_INTERVAL = 1500;

/*
 * رصيد الحساب الدائن:
 * يستخدم لعرض الرصيد السابق والحالي.
 */
let creditBalanceInfo = null;

// ══════════════════════════════════════════════════════════
// LOCK EXCHANGE RATE
// ══════════════════════════════════════════════════════════

function lockExchangeRate() {

    const exchangeEl =
        document.getElementById(
            'ExchangeRate'
        );

    if (!exchangeEl) {
        return;
    }

    exchangeEl.readOnly = true;
    exchangeEl.tabIndex = -1;

    exchangeEl.style.cursor =
        'not-allowed';

    exchangeEl.style.backgroundColor =
        'var(--bs-tertiary-bg)';
}

lockExchangeRate();

// ══════════════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════════════

state.setFields([
    'ReceiptVoucherNumber',
    'ReceiptVoucherDate',

    'CreditAccountName',
    'DebitAccountName',

    'Amount',
    'CoinsID',
    'ExchangeRate',
    'PaymentMethod',
    'Notes',
]);

state.setButtons({
    add:
        'btnNewReceiptVoucher',

    save:
        'btnSaveReceiptVoucher',

    saveNew:
        'btnSaveAndNewReceiptVoucher',

    cancel:
        'btnCancelReceiptVoucher',

    edit:
        'btnEditReceiptVoucher',

    print:
        'btnPrintReceiptVoucher',

    search:
        'btnSearchReceiptVoucher',
});

state.onModeChange(
    function (mode) {

        toggleFormDisabled(
            mode === 'view'
        );

        updateDisplay();

        lockExchangeRate();
    }
);

// ══════════════════════════════════════════════════════════
// تحميل العملات
// ══════════════════════════════════════════════════════════

async function loadCurrencies() {

    try {

        const response =
            await fetch(
                '/operation/accounting/receiptVouchers/currencies',
                {
                    headers: {
                        'Accept':
                            'application/json',
                    },
                }
            );

        const data =
            await response.json();

        if (
            !data.success ||
            !data.rows
        ) {
            return;
        }

        const select =
            document.getElementById(
                'CoinsID'
            );

        if (!select) {
            return;
        }

        select.innerHTML =
            '<option value="">اختر العملة</option>';

        data.rows.forEach(c => {

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                c.id;

            option.textContent =
                `${c.code} - ${c.name}`;

            option.dataset.rate =
                c.exchangeRate;

            select.appendChild(
                option
            );
        });

    } catch (error) {

        console.error(
            '❌ خطأ في تحميل العملات:',
            error
        );
    }
}

async function loadNextVoucherNumber() {

    try {

        const response =
            await fetch(
                '/operation/accounting/receiptVouchers/next-number',
                {
                    headers: {
                        'Accept':
                            'application/json',
                    },
                }
            );

        const data =
            await response.json();

        if (
            data.success &&
            data.nextNumber
        ) {

            const numberEl =
                document.getElementById(
                    'ReceiptVoucherNumber'
                );

            if (numberEl) {
                numberEl.value =
                    data.nextNumber;
            }

            updateDisplay();

            return data.nextNumber;
        }

    } catch (error) {

        console.error(
            '❌ خطأ في جلب رقم السند:',
            error
        );
    }

    return null;
}

document
    .getElementById('CoinsID')
    ?.addEventListener(
        'change',
        function () {

            const selectedOption =
                this.options[
                    this.selectedIndex
                ];

            const rate =
                parseFloat(
                    selectedOption?.dataset?.rate
                ) || 1;

            const exchangeRateEl =
                document.getElementById(
                    'ExchangeRate'
                );

            if (exchangeRateEl) {

                exchangeRateEl.value =
                    rate;
            }

            /*
             * عند تغيير العملة يدويًا:
             * الحساب المدين السابق لم يعد مضمونًا
             * أنه تابع للعملة الجديدة، لذلك يتم مسحه.
             */
            const debitAccountID =
                document.getElementById(
                    'DebitAccountID'
                );

            const debitAccountName =
                document.getElementById(
                    'DebitAccountName'
                );

            if (
                this.value &&
                debitAccountID &&
                debitAccountID.value
            ) {

                debitAccountID.value = '';

                if (debitAccountName) {
                    debitAccountName.value = '';
                }
            }

            /*
             * إذا لم تكن العملة مقفلة من حساب الصندوق/البنك،
             * تبقى قابلة للاختيار.
             */
            if (
                state.mode !== 'view' &&
                !debitAccountID?.dataset?.currencyLocked
            ) {

                this.disabled =
                    false;

                this.classList.remove(
                    'bg-body-secondary'
                );
            }

            updateSummary();
        }
    );

loadCurrencies();

// ══════════════════════════════════════════════════════════
// الحساب الدائن
// العميل
// ══════════════════════════════════════════════════════════

document
    .getElementById(
        'CreditAccountName'
    )
    ?.addEventListener(
        'click',
        function (e) {

            e.preventDefault();

            if (
                state.mode === 'view' ||
                saveInProgress
            ) {
                return;
            }

            if (
                typeof window.AccountPicker
                === 'undefined'
            ) {

                toastError(
                    'مودال اختيار الحسابات غير محمّل'
                );

                return;
            }

            window.AccountPicker.open(
                'customer',

                function (account) {

                    document.getElementById(
                        'CreditAccountID'
                    ).value =
                        account.id;

                    document.getElementById(
                        'CreditAccountName'
                    ).value =
                        account.code
                        + ' - '
                        + account.name;

                    creditBalanceInfo = {

                        id:
                            account.id,

                        name:
                            account.name,

                        balance:
                            parseFloat(
                                account.balance
                            ) || 0,

                        nature:
                            account.nature !== null
                            && account.nature !== undefined
                                ? parseInt(
                                    account.nature
                                )
                                : 0,
                    };

                    updateSummary();
                }
            );
        }
    );

// ══════════════════════════════════════════════════════════
// الحساب المدين
// الصندوق / البنك
// ══════════════════════════════════════════════════════════

document
    .getElementById(
        'DebitAccountName'
    )
    ?.addEventListener(
        'click',
        function (e) {

            e.preventDefault();

            if (
                state.mode === 'view' ||
                saveInProgress
            ) {
                return;
            }

            if (
                typeof window.AccountPicker
                === 'undefined'
            ) {

                toastError(
                    'مودال اختيار الحسابات غير محمّل'
                );

                return;
            }

            const paymentMethod =
                document.getElementById(
                    'PaymentMethod'
                ).value || 'cash';

            const type =
                paymentMethod === 'bank'
                    ? 'bank'
                    : 'cash';

            const currentCoinsID =
                document.getElementById(
                    'CoinsID'
                ).value || null;

            window.AccountPicker.open(
                type,

                function (account) {

                    document.getElementById(
                        'DebitAccountID'
                    ).value =
                        account.id;

                    document.getElementById(
                        'DebitAccountName'
                    ).value =
                        account.code
                        + ' - '
                        + account.name;

                    const debitAccountID =
                        document.getElementById(
                            'DebitAccountID'
                        );

                    /*
                     * عند اختيار الصندوق/البنك:
                     * يتم تحديد العملة تلقائيًا من الحساب
                     * ثم قفلها.
                     */
                    if (
                        account.currency_id
                    ) {

                        const coinsSelect =
                            document.getElementById(
                                'CoinsID'
                            );

                        const exists =
                            Array.from(
                                coinsSelect.options
                            ).some(
                                o =>
                                    String(
                                        o.value
                                    )
                                    ===
                                    String(
                                        account.currency_id
                                    )
                            );

                        if (!exists) {

                            const opt =
                                document.createElement(
                                    'option'
                                );

                            opt.value =
                                account.currency_id;

                            opt.textContent =
                                account.currency_code
                                || '';

                            opt.dataset.rate =
                                account.exchange_rate
                                || 1;

                            coinsSelect.appendChild(
                                opt
                            );
                        }

                        coinsSelect.value =
                            account.currency_id;

                        coinsSelect.disabled =
                            true;

                        coinsSelect.classList.add(
                            'bg-body-secondary'
                        );

                        coinsSelect.dataset.locked =
                            '1';

                        const exchangeEl =
                            document.getElementById(
                                'ExchangeRate'
                            );

                        if (exchangeEl) {

                            exchangeEl.value =
                                account.exchange_rate
                                || 1;
                        }

                        lockExchangeRate();

                        if (debitAccountID) {
                            debitAccountID.dataset.currencyLocked =
                                '1';
                        }

                        updateSummary();

                        toastInfo(
                            'تم قفل العملة على: '
                            +
                            (
                                account.currency_code
                                || ''
                            )
                        );
                    }
                },

                {
                    coinsID:
                        currentCoinsID,

                    allowedTypes: [
                        type,
                    ],
                }
            );
        }
    );

// ══════════════════════════════════════════════════════════
// طريقة القبض
// ══════════════════════════════════════════════════════════

document
    .getElementById(
        'PaymentMethod'
    )
    ?.addEventListener(
        'change',
        function () {

            const method =
                this.value;

            const debitContainer =
                document.getElementById(
                    'debitAccountContainer'
                );

            const debitLabel =
                document.getElementById(
                    'debitAccountLabel'
                );

            if (!debitContainer) {
                return;
            }

            if (
                method === 'cash' ||
                method === 'bank'
            ) {

                debitContainer.classList.remove(
                    'd-none'
                );

                if (debitLabel) {

                    debitLabel.textContent =
                        method === 'cash'
                            ? 'حساب الصندوق'
                            : 'حساب البنك';
                }

            } else {

                debitContainer.classList.add(
                    'd-none'
                );

                const debitID =
                    document.getElementById(
                        'DebitAccountID'
                    );

                const debitName =
                    document.getElementById(
                        'DebitAccountName'
                    );

                if (debitID) {
                    debitID.value = '';
                    delete debitID.dataset.currencyLocked;
                }

                if (debitName) {
                    debitName.value = '';
                }
            }

            /*
             * تغيير طريقة القبض يعني أننا قد نحتاج
             * إلى اختيار صندوق/بنك جديد.
             *
             * لذلك نعيد فتح العملة للاختيار
             * إذا لم يكن هناك حساب مدين محدد.
             */
            const coinsSelect =
                document.getElementById(
                    'CoinsID'
                );

            const debitID =
                document.getElementById(
                    'DebitAccountID'
                );

            if (
                coinsSelect &&
                state.mode !== 'view'
            ) {

                if (!debitID?.value) {

                    coinsSelect.disabled =
                        false;

                    coinsSelect.classList.remove(
                        'bg-body-secondary'
                    );

                    delete coinsSelect.dataset.locked;
                }
            }
        }
    );

// ══════════════════════════════════════════════════════════
// تعطيل النموذج
// ══════════════════════════════════════════════════════════

function toggleFormDisabled(
    disabled
) {

    const fields = [
        'Amount',
        'CoinsID',
        'PaymentMethod',
        'Notes',
    ];

    fields.forEach(id => {

        const el =
            document.getElementById(
                id
            );

        if (el) {
            el.disabled =
                disabled;
        }
    });

    [
        'CreditAccountName',
        'DebitAccountName',
    ].forEach(id => {

        const el =
            document.getElementById(
                id
            );

        if (!el) {
            return;
        }

        el.classList.toggle(
            'bg-body-secondary',
            disabled
        );

        el.style.cursor =
            disabled
                ? 'not-allowed'
                : 'pointer';
    });

    lockExchangeRate();
}

function setButtonsDisabled(
    disabled
) {

    const buttons = [
        'btnNewReceiptVoucher',
        'btnSaveReceiptVoucher',
        'btnSaveAndNewReceiptVoucher',
        'btnEditReceiptVoucher',
        'btnCancelReceiptVoucher',
        'btnPrintReceiptVoucher',
        'btnSearchReceiptVoucher',
    ];

    buttons.forEach(id => {

        const btn =
            document.getElementById(
                id
            );

        if (btn) {
            btn.disabled =
                disabled;
        }
    });
}

// ══════════════════════════════════════════════════════════
// SUMMARY
// ══════════════════════════════════════════════════════════

window.updateSummary =
    function () {

        const amount =
            parseFloat(
                document.getElementById(
                    'Amount'
                )?.value
            ) || 0;

        const rate =
            parseFloat(
                document.getElementById(
                    'ExchangeRate'
                )?.value
            ) || 1;

        const paid =
            amount * rate;

        const coinsSelect =
            document.getElementById(
                'CoinsID'
            );

        const selectedOption =
            coinsSelect?.options[
                coinsSelect.selectedIndex
            ];

        const currencyName =
            selectedOption
                ?.textContent
                ?.split(' - ')[1]
                ?.trim()
                || '';

        const amountWordsEl =
            document.getElementById(
                'AmountWords'
            );

        if (amountWordsEl) {

            if (
                amount <= 0 ||
                isNaN(amount)
            ) {

                amountWordsEl.value =
                    '';

            } else if (
                typeof Utils !==
                'undefined' &&
                Utils.numberToWords
            ) {

                amountWordsEl.value =
                    Utils.numberToWords(
                        amount,
                        currencyName
                    );
            }
        }

        const prevBalance =
            creditBalanceInfo?.balance
            || 0;

        const nature =
            parseInt(
                creditBalanceInfo?.nature
                ?? 0
            );

        /*
         * الطبيعة:
         *
         * 1 = دائن
         *     القبض يقلل رصيد العميل
         *
         * 0 = مدين
         *     القبض يزيد رصيد العميل
         */

        const remain =
            nature === 1
                ? prevBalance - paid
                : prevBalance + paid;

        const summaryPrevious =
            document.getElementById(
                'SummaryPrevious'
            );

        const summaryPaid =
            document.getElementById(
                'SummaryPaid'
            );

        const summaryRemain =
            document.getElementById(
                'SummaryRemain'
            );

        if (summaryPrevious) {

            summaryPrevious.textContent =
                prevBalance.toFixed(2);
        }

        if (summaryPaid) {

            summaryPaid.textContent =
                paid.toFixed(2);
        }

        if (summaryRemain) {

            summaryRemain.textContent =
                remain.toFixed(2);
        }
    };

// ══════════════════════════════════════════════════════════
// DISPLAY
// ══════════════════════════════════════════════════════════

function updateDisplay() {

    const num =
        document.getElementById(
            'ReceiptVoucherNumber'
        )?.value || '';

    const date =
        document.getElementById(
            'ReceiptVoucherDate'
        )?.value || '';

    const displayNum =
        document.getElementById(
            'ReceiptVoucherNumberDisplay'
        );

    const displayDate =
        document.getElementById(
            'ReceiptVoucherDateDisplay'
        );

    if (displayNum) {

        displayNum.textContent =
            num
                ? 'رقم السند: ' + num
                : 'رقم السند: --';
    }

    if (displayDate) {

        displayDate.innerHTML =
            date
                ? '<i class="bi bi-calendar3 me-1"></i> '
                  + date
                : '<i class="bi bi-calendar3 me-1"></i> --';
    }
}

// ══════════════════════════════════════════════════════════
// ORIGINAL DATA
// ══════════════════════════════════════════════════════════

let originalVoucherData =
    null;

function captureVoucherData() {

    return {

        creditAccountID:
            document.getElementById(
                'CreditAccountID'
            ).value.trim(),

        debitAccountID:
            document.getElementById(
                'DebitAccountID'
            ).value.trim(),

        coinsID:
            document.getElementById(
                'CoinsID'
            ).value.trim(),

        amount:
            parseFloat(
                document.getElementById(
                    'Amount'
                ).value
            ) || 0,

        exchangeRate:
            parseFloat(
                document.getElementById(
                    'ExchangeRate'
                ).value
            ) || 1,

        paymentMethod:
            document.getElementById(
                'PaymentMethod'
            ).value || '',

        notes:
            document.getElementById(
                'Notes'
            ).value.trim(),

        voucherDate:
            document.getElementById(
                'ReceiptVoucherDate'
            ).value || '',
    };
}

function hasChanges() {

    if (!originalVoucherData) {
        return true;
    }

    return (
        JSON.stringify(
            captureVoucherData()
        )
        !==
        JSON.stringify(
            originalVoucherData
        )
    );
}

// ══════════════════════════════════════════════════════════
// CLEAR
// ══════════════════════════════════════════════════════════

function clearForm() {

    const fields = [
        'ReceiptVoucherNumber',
        'ReceiptVoucherDate',

        'CreditAccountID',
        'CreditAccountName',

        'DebitAccountID',
        'DebitAccountName',

        'Amount',
        'CoinsID',
        'ExchangeRate',
        'PaymentMethod',
        'Notes',
    ];

    fields.forEach(id => {

        const el =
            document.getElementById(
                id
            );

        if (!el) {
            return;
        }

        if (
            el.tagName === 'SELECT'
        ) {

            el.selectedIndex = 0;

        } else {

            el.value = '';
        }
    });

    document
        .getElementById(
            'debitAccountContainer'
        )
        ?.classList.add(
            'd-none'
        );

    const amountWords =
        document.getElementById(
            'AmountWords'
        );

    if (amountWords) {
        amountWords.value = '';
    }

    const summaryPrevious =
        document.getElementById(
            'SummaryPrevious'
        );

    const summaryPaid =
        document.getElementById(
            'SummaryPaid'
        );

    const summaryRemain =
        document.getElementById(
            'SummaryRemain'
        );

    if (summaryPrevious) {
        summaryPrevious.textContent =
            '0.00';
    }

    if (summaryPaid) {
        summaryPaid.textContent =
            '0.00';
    }

    if (summaryRemain) {
        summaryRemain.textContent =
            '0.00';
    }

    const coinsSelect =
        document.getElementById(
            'CoinsID'
        );

    if (coinsSelect) {

        coinsSelect.disabled =
            false;

        coinsSelect.classList.remove(
            'bg-body-secondary'
        );

        delete coinsSelect.dataset.locked;
    }

    const debitAccountID =
        document.getElementById(
            'DebitAccountID'
        );

    if (debitAccountID) {
        delete debitAccountID.dataset.currencyLocked;
    }

    lockExchangeRate();

    const numEl =
        document.getElementById(
            'ReceiptVoucherNumber'
        );

    if (numEl) {
        numEl.dataset.id = '';
    }

    creditBalanceInfo =
        null;

    originalVoucherData =
        null;

    state.setMode('view');

    updateDisplay();
}

// ══════════════════════════════════════════════════════════
// NEW
// ══════════════════════════════════════════════════════════

window.resetVoucher =
    async function () {

        if (saveInProgress) {
            return;
        }

        clearForm();

        document.getElementById(
            'ReceiptVoucherDate'
        ).value =
            new Date()
                .toISOString()
                .slice(0, 10);

        await loadNextVoucherNumber();

        state.setMode('add');

        updateDisplay();

        lockExchangeRate();

        toastInfo(
            'يمكنك الآن إدخال بيانات سند القبض'
        );

        setTimeout(
            () =>
                document
                    .getElementById(
                        'PaymentMethod'
                    )
                    ?.focus(),
            300
        );
    };

// ══════════════════════════════════════════════════════════
// VALIDATE
// ══════════════════════════════════════════════════════════

function validateForm() {

    const errors = [];

    const creditAccountID =
        document.getElementById(
            'CreditAccountID'
        ).value.trim();

    const debitAccountID =
        document.getElementById(
            'DebitAccountID'
        ).value.trim();

    const amount =
        parseFloat(
            document.getElementById(
                'Amount'
            ).value
        ) || 0;

    const coinsID =
        document.getElementById(
            'CoinsID'
        ).value.trim();

    const exchangeRate =
        parseFloat(
            document.getElementById(
                'ExchangeRate'
            ).value
        ) || 0;

    const paymentMethod =
        document.getElementById(
            'PaymentMethod'
        ).value;

    if (!creditAccountID) {

        errors.push(
            'يرجى اختيار الحساب الدائن.'
        );
    }

    if (!paymentMethod) {

        errors.push(
            'يرجى اختيار طريقة القبض.'
        );
    }

    if (!debitAccountID) {

        errors.push(
            'يرجى اختيار حساب القبض.'
        );
    }

    if (
        creditAccountID &&
        debitAccountID &&
        creditAccountID ===
            debitAccountID
    ) {

        errors.push(
            'لا يمكن أن يكون الحساب الدائن هو نفس حساب القبض.'
        );
    }

    if (
        amount <= 0 ||
        isNaN(amount)
    ) {

        errors.push(
            'المبلغ يجب أن يكون أكبر من صفر.'
        );
    }

    if (!coinsID) {

        errors.push(
            'يرجى اختيار العملة.'
        );
    }

    if (
        exchangeRate <= 0 ||
        isNaN(exchangeRate)
    ) {

        errors.push(
            'سعر الصرف يجب أن يكون أكبر من صفر.'
        );
    }

    return errors.length > 0
        ? errors
        : null;
}

// ══════════════════════════════════════════════════════════
// SAVE
// ══════════════════════════════════════════════════════════

window.saveVoucher =
    async function () {

        if (saveInProgress) {
            return false;
        }

        const now =
            Date.now();

        if (
            now - lastSaveTime
            < MIN_SAVE_INTERVAL
        ) {
            return false;
        }

        const isEdit =
            state.mode === 'edit';

        if (
            isEdit &&
            !hasChanges()
        ) {

            toastError(
                'لم تُجرِ أي تعديل على السند.'
            );

            return false;
        }

        const errors =
            validateForm();

        if (errors) {

            toastError(
                errors[0]
            );

            return false;
        }

        saveInProgress = true;

        lastSaveTime =
            now;

        setButtonsDisabled(
            true
        );

        const saveBtn =
            document.getElementById(
                'btnSaveReceiptVoucher'
            );

        const originalSaveText =
            saveBtn
                ? saveBtn.innerHTML
                : '';

        if (saveBtn) {

            saveBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span> جاري الحفظ...';
        }

        const id =
            document.getElementById(
                'ReceiptVoucherNumber'
            ).dataset.id;

        const payload = {

            voucherNumber:
                document.getElementById(
                    'ReceiptVoucherNumber'
                ).value.trim()
                || null,

            creditAccountID:
                document.getElementById(
                    'CreditAccountID'
                ).value.trim(),

            debitAccountID:
                document.getElementById(
                    'DebitAccountID'
                ).value.trim(),

            coinsID:
                document.getElementById(
                    'CoinsID'
                ).value.trim(),

            amount:
                parseFloat(
                    document.getElementById(
                        'Amount'
                    ).value
                ) || 0,

            exchangeRate:
                parseFloat(
                    document.getElementById(
                        'ExchangeRate'
                    ).value
                ) || 1,

            paymentMethod:
                document.getElementById(
                    'PaymentMethod'
                ).value
                || null,

            notes:
                document.getElementById(
                    'Notes'
                ).value.trim()
                || null,

            voucherDate:
                document.getElementById(
                    'ReceiptVoucherDate'
                ).value
                || null,
        };

        try {

            const url =
                isEdit
                    ? `/operation/accounting/receiptVouchers/${id}`
                    : `/operation/accounting/receiptVouchers`;

            const method =
                isEdit
                    ? 'PUT'
                    : 'POST';

            const response =
                await fetch(
                    url,
                    {
                        method:
                            method,

                        headers: {
                            'Content-Type':
                                'application/json',

                            'X-CSRF-TOKEN':
                                document
                                    .querySelector(
                                        'meta[name="csrf-token"]'
                                    )
                                    ?.content
                                || '',

                            'Accept':
                                'application/json',
                        },

                        body:
                            JSON.stringify(
                                payload
                            ),
                    }
                );

            const data =
                await response.json();

            if (!data.success) {

                toastError(
                    data.message
                    || 'حدث خطأ غير متوقع.'
                );

                return false;
            }

            toastSuccess(
                isEdit
                    ? 'تم تعديل السند بنجاح.'
                    : 'تم حفظ السند بنجاح.'
            );

            if (data.voucherID) {

                document.getElementById(
                    'ReceiptVoucherNumber'
                ).dataset.id =
                    data.voucherID;
            }

            if (data.voucherNumber) {

                document.getElementById(
                    'ReceiptVoucherNumber'
                ).value =
                    data.voucherNumber;
            }

            state.setMode(
                'view'
            );

            updateDisplay();

            originalVoucherData =
                null;

            return true;

        } catch (error) {

            console.error(
                '❌ خطأ في الحفظ:',
                error
            );

            toastError(
                'فشل الاتصال بالخادم. يرجى المحاولة مرة أخرى.'
            );

            return false;

        } finally {

            saveInProgress =
                false;

            setButtonsDisabled(
                false
            );

            if (saveBtn) {

                saveBtn.innerHTML =
                    originalSaveText;
            }
        }
    };

// ══════════════════════════════════════════════════════════
// SAVE + NEW
// ══════════════════════════════════════════════════════════

window.saveAndNewVoucher =
    async function () {

        if (saveInProgress) {
            return;
        }

        const saved =
            await saveVoucher();

        if (saved === true) {
            await resetVoucher();
        }
    };

// ══════════════════════════════════════════════════════════
// EDIT
// ══════════════════════════════════════════════════════════

window.editVoucher =
    function () {

        if (saveInProgress) {
            return;
        }

        const id =
            document.getElementById(
                'ReceiptVoucherNumber'
            ).dataset.id;

        if (!id) {

            toastError(
                'لا يوجد سند محدد للتعديل.'
            );

            return;
        }

        originalVoucherData =
            captureVoucherData();

        state.setMode(
            'edit'
        );

        toastInfo(
            'يمكنك الآن تعديل بيانات سند القبض'
        );
    };

// ══════════════════════════════════════════════════════════
// CANCEL
// ══════════════════════════════════════════════════════════

window.cancelVoucher =
    function () {

        if (saveInProgress) {
            return;
        }

        if (
            !confirm(
                'هل أنت متأكد من إلغاء العملية؟'
            )
        ) {
            return;
        }

        clearForm();

        loadNextVoucherNumber();

        toastInfo(
            'تم إلغاء العملية'
        );
    };

// ══════════════════════════════════════════════════════════
// PRINT
// ══════════════════════════════════════════════════════════

window.printVoucher =
    function () {

        const voucherId =
            document.getElementById(
                'ReceiptVoucherNumber'
            ).dataset.id;

        if (!voucherId) {

            toastError(
                'لا يمكن الطباعة لأنه لا يوجد سند محفوظ. يرجى حفظ السند أولاً.'
            );

            return;
        }

        const printUrl =
            `/operation/accounting/receiptVouchers/${voucherId}/print`;

        window.open(
            printUrl,
            '_blank',
            'width=900,height=700'
        );
    };

// ══════════════════════════════════════════════════════════
// SEARCH
// ══════════════════════════════════════════════════════════

window.searchVoucher =
    function () {

        if (saveInProgress) {
            return;
        }

        openSearchModal();
    };

let searchModal = null;

let searchTimeout = null;

function openSearchModal() {

    const modalEl =
        document.getElementById(
            'ReceiptVoucherSearchModal'
        );

    if (!searchModal) {

        searchModal =
            new bootstrap.Modal(
                modalEl
            );
    }

    document.getElementById(
        'ReceiptVoucherSearchInput'
    ).value = '';

    document.getElementById(
        'ReceiptVoucherSearchResults'
    ).innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">جاري التحميل...</td></tr>';

    searchModal.show();

    setTimeout(
        () => {

            document
                .getElementById(
                    'ReceiptVoucherSearchInput'
                )
                ?.focus();

            performSearch();

        },
        300
    );
}

// ══════════════════════════════════════════════════════════
// PERFORM SEARCH
// ══════════════════════════════════════════════════════════

async function performSearch() {

    const search =
        document.getElementById(
            'ReceiptVoucherSearchInput'
        ).value.trim();

    const tbody =
        document.getElementById(
            'ReceiptVoucherSearchResults'
        );

    tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">جاري البحث...</td></tr>';

    try {

        const response =
            await fetch(
                `/operation/accounting/receiptVouchers/list?search=${encodeURIComponent(search)}`,
                {
                    headers: {
                        'Accept':
                            'application/json',
                    },
                }
            );

        const data =
            await response.json();

        tbody.innerHTML = '';

        if (
            !data.success ||
            !data.rows ||
            data.rows.length === 0
        ) {

            tbody.innerHTML =
                '<tr><td colspan="7" class="text-center text-muted py-4">لا توجد نتائج</td></tr>';

            return;
        }

        data.rows.forEach(
            row => {

                const tr =
                    document.createElement(
                        'tr'
                    );

                tr.style.cursor =
                    'pointer';

                const paymentMethodText =
                    {
                        cash:
                            'نقد',

                        bank:
                            'تحويل بنكي',

                    }[
                        row.paymentMethod
                    ]
                    ||
                    row.paymentMethod
                    ||
                    '';

                tr.innerHTML = `
                    <td class="text-center fw-bold">
                        ${row.voucherNumber}
                    </td>

                    <td class="text-center">
                        ${row.voucherDate}
                    </td>

                    <td>
                        ${row.creditAccountCode}
                        -
                        ${row.creditAccountName}
                    </td>

                    <td>
                        ${row.debitAccountCode}
                        -
                        ${row.debitAccountName}
                    </td>

                    <td class="text-center text-success fw-bold">
                        ${parseFloat(row.amount).toFixed(2)}
                    </td>

                    <td class="text-center">
                        ${row.currencyCode || '—'}
                    </td>

                    <td class="text-center">
                        ${paymentMethodText}
                    </td>
                `;

                tr.addEventListener(
                    'click',
                    () => {

                        loadVoucher(
                            row.id
                        );

                        searchModal.hide();
                    }
                );

                tbody.appendChild(
                    tr
                );
            }
        );

    } catch (error) {

        console.error(
            '❌ خطأ في البحث:',
            error
        );

        tbody.innerHTML =
            '<tr><td colspan="7" class="text-center text-danger py-4">فشل الاتصال بالخادم</td></tr>';
    }
}

// ══════════════════════════════════════════════════════════
// LOAD VOUCHER
// ══════════════════════════════════════════════════════════

async function loadVoucher(id) {

    if (saveInProgress) {
        return;
    }

    try {

        const response =
            await fetch(
                `/operation/accounting/receiptVouchers/${id}`,
                {
                    headers: {
                        'Accept':
                            'application/json',
                    },
                }
            );

        const data =
            await response.json();

        if (
            !data.success ||
            !data.voucher
        ) {

            toastError(
                'فشل تحميل السند.'
            );

            return;
        }

        const v =
            data.voucher;

        document.getElementById(
            'ReceiptVoucherNumber'
        ).value =
            v.voucherNumber;

        document.getElementById(
            'ReceiptVoucherNumber'
        ).dataset.id =
            v.id;

        document.getElementById(
            'ReceiptVoucherDate'
        ).value =
            v.voucherDate;

        // الحساب الدائن
        document.getElementById(
            'CreditAccountID'
        ).value =
            v.creditAccountID;

        document.getElementById(
            'CreditAccountName'
        ).value =
            v.creditAccountCode
            + ' - '
            + v.creditAccountName;

        creditBalanceInfo = {

            id:
                v.creditAccountID,

            name:
                v.creditAccountName,

            balance:
                parseFloat(
                    v.creditBalanceBefore
                ) || 0,

            nature:
                parseInt(
                    v.creditNature
                ) || 0,
        };

        // طريقة القبض
        document.getElementById(
            'PaymentMethod'
        ).value =
            v.paymentMethod || '';

        if (v.paymentMethod) {

            document
                .getElementById(
                    'PaymentMethod'
                )
                .dispatchEvent(
                    new Event('change')
                );
        }

        // الحساب المدين
        document.getElementById(
            'DebitAccountID'
        ).value =
            v.debitAccountID;

        document.getElementById(
            'DebitAccountName'
        ).value =
            v.debitAccountCode
            + ' - '
            + v.debitAccountName;

        // البيانات المالية
        document.getElementById(
            'Amount'
        ).value =
            v.amount;

        document.getElementById(
            'CoinsID'
        ).value =
            v.currencyID;

        document.getElementById(
            'ExchangeRate'
        ).value =
            v.exchangeRate;

        document.getElementById(
            'Notes'
        ).value =
            v.notes || '';

        /*
         * السند المحمل محفوظ بالفعل،
         * لذلك العملة تظهر كجزء من بيانات السند
         * ولا يتم تعديلها في وضع العرض.
         */
        const coinsSelect =
            document.getElementById(
                'CoinsID'
            );

        if (coinsSelect) {

            coinsSelect.disabled =
                true;

            coinsSelect.classList.add(
                'bg-body-secondary'
            );
        }

        lockExchangeRate();

        updateSummary();

        updateDisplay();

        state.setMode(
            'view'
        );

        toastSuccess(
            'تم تحميل السند'
        );

    } catch (error) {

        console.error(
            '❌ خطأ:',
            error
        );

        toastError(
            'فشل تحميل السند.'
        );
    }
}

// ══════════════════════════════════════════════════════════
// EVENTS
// ══════════════════════════════════════════════════════════

document
    .getElementById('Amount')
    ?.addEventListener(
        'input',
        updateSummary
    );

document
    .getElementById(
        'ReceiptVoucherSearchInput'
    )
    ?.addEventListener(
        'input',
        function () {

            clearTimeout(
                searchTimeout
            );

            searchTimeout =
                setTimeout(
                    performSearch,
                    300
                );
        }
    );

document
    .getElementById(
        'ReceiptVoucherClearBtn'
    )
    ?.addEventListener(
        'click',
        function () {

            document.getElementById(
                'ReceiptVoucherSearchInput'
            ).value = '';

            document.getElementById(
                'ReceiptVoucherSearchInput'
            ).focus();

            performSearch();
        }
    );

document
    .getElementById(
        'ReceiptVoucherSearchInput'
    )
    ?.addEventListener(
        'keydown',
        function (e) {

            if (e.key === 'Enter') {

                e.preventDefault();

                performSearch();
            }
        }
    );

document
    .getElementById(
        'btnNewReceiptVoucher'
    )
    ?.addEventListener(
        'click',
        resetVoucher
    );

document
    .getElementById(
        'btnSaveReceiptVoucher'
    )
    ?.addEventListener(
        'click',
        saveVoucher
    );

document
    .getElementById(
        'btnSaveAndNewReceiptVoucher'
    )
    ?.addEventListener(
        'click',
        saveAndNewVoucher
    );

document
    .getElementById(
        'btnEditReceiptVoucher'
    )
    ?.addEventListener(
        'click',
        editVoucher
    );

document
    .getElementById(
        'btnCancelReceiptVoucher'
    )
    ?.addEventListener(
        'click',
        cancelVoucher
    );

document
    .getElementById(
        'btnPrintReceiptVoucher'
    )
    ?.addEventListener(
        'click',
        printVoucher
    );

document
    .getElementById(
        'btnSearchReceiptVoucher'
    )
    ?.addEventListener(
        'click',
        searchVoucher
    );

// ══════════════════════════════════════════════════════════
// INITIALIZE
// ══════════════════════════════════════════════════════════

clearForm();

loadNextVoucherNumber();

console.log(
    '✅ تم تهيئة صفحة سند القبض بنجاح'
);

}

if (
document.readyState ===
'loading'
) {

document.addEventListener(
    'DOMContentLoaded',
    initReceiptVoucherPage
);

} else {

initReceiptVoucherPage();

}