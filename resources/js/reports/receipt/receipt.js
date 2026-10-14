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

    console.log('🚀 بدء تهيئة صفحة سند القبض');

    function toast(message, type = 'info') {
        if (typeof showSystemToast === 'function') {
            showSystemToast(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    }

    function toastInfo(message)    { toast(message, 'info'); }
    function toastSuccess(message) { toast(message, 'success'); }
    function toastWarning(message) { toast(message, 'warning'); }
    function toastError(message)   { toast(message, 'danger'); }

    if (typeof Modals !== 'undefined' && Modals.initAll) {
        Modals.initAll();
    }

    const state = new StateManager('receipt-voucher');

    let saveInProgress = false;
    let lastSaveTime = 0;
    const MIN_SAVE_INTERVAL = 1500;

    let creditBalanceInfo = null;
    let currentVoucherIsPosted = false;

    // ══════════════════════════════════════════════════════════
    // LOCK EXCHANGE RATE
    // ══════════════════════════════════════════════════════════

    function lockExchangeRate() {
        const exchangeEl = document.getElementById('ExchangeRate');
        if (!exchangeEl) return;

        exchangeEl.readOnly = true;
        exchangeEl.tabIndex = -1;
        exchangeEl.style.cursor = 'not-allowed';
        exchangeEl.style.backgroundColor = 'var(--bs-tertiary-bg)';
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
        add:     'btnNewReceiptVoucher',
        save:    'btnSaveReceiptVoucher',
        saveNew: 'btnSaveAndNewReceiptVoucher',
        cancel:  'btnCancelReceiptVoucher',
        edit:    'btnEditReceiptVoucher',
        print:   'btnPrintReceiptVoucher',
        search:  'btnSearchReceiptVoucher',
    });

    state.onModeChange(function (mode) {
        toggleFormDisabled(mode === 'view');
        updateDisplay();
        lockExchangeRate();

        const hasData = !!document.getElementById('ReceiptVoucherNumber').dataset.id;
        updateActionButtons(mode, currentVoucherIsPosted, hasData);

        updateSummary();
    });

    // ══════════════════════════════════════════════════════════
    // ACTION BUTTONS — مطابق لمرتجعات البيع
    // ══════════════════════════════════════════════════════════

    /**
     * يُدير ظهور وتعطيل جميع أزرار العمليات.
     */
    function updateActionButtons(mode, isPosted, hasData) {

        const isView = (mode === 'view');

        /* ═══ أزرار الحفظ / حفظ وإضافة / إلغاء ═══ */
        const saveBtn    = document.getElementById('btnSaveReceiptVoucher');
        const saveNewBtn = document.getElementById('btnSaveAndNewReceiptVoucher');
        const cancelBtn  = document.getElementById('btnCancelReceiptVoucher');

        if (isView) {
            if (saveBtn)    saveBtn.disabled = true;
            if (saveNewBtn) saveNewBtn.disabled = true;
            if (cancelBtn)  cancelBtn.classList.add('d-none');
        } else {
            if (saveBtn)    saveBtn.disabled = false;
            if (saveNewBtn) saveNewBtn.disabled = false;
            if (cancelBtn)  cancelBtn.classList.remove('d-none');
        }

        /* ═══ زر الإضافة (الأعلى) ═══ */
        const newBtn = document.getElementById('btnNewReceiptVoucher');
        if (newBtn) newBtn.disabled = !isView;

        /* ═══ زر البحث (الأعلى) ═══ */
        const searchBtn = document.getElementById('btnSearchReceiptVoucher');
        if (searchBtn) searchBtn.disabled = !isView || hasData;

        /* ═══ زر تعديل ═══ */
        const editBtn = document.getElementById('btnEditReceiptVoucher');
        if (editBtn) {
            editBtn.disabled = !hasData || isPosted || !isView;
            editBtn.classList.toggle('d-none', isPosted);
        }

        /* ═══ زر ترحيل ═══ */
        const postBtn = document.getElementById('btnPostReceiptVoucher');
        if (postBtn) {
            const show = hasData && !isPosted;
            postBtn.classList.toggle('d-none', !show);
            postBtn.disabled = !hasData || isPosted || !isView;
        }

        /* ═══ زر إلغاء الترحيل ═══ */
        const unpostBtn = document.getElementById('btnUnpostReceiptVoucher');
        if (unpostBtn) {
            const show = hasData && isPosted;
            unpostBtn.classList.toggle('d-none', !show);
            unpostBtn.disabled = !hasData || !isPosted || !isView;
        }

        /* ═══ زر طباعة ═══ */
        const printBtn = document.getElementById('btnPrintReceiptVoucher');
        if (printBtn) printBtn.disabled = !hasData;
    }

    // ══════════════════════════════════════════════════════════
    // تحميل العملات
    // ══════════════════════════════════════════════════════════

    async function loadCurrencies() {
        try {
            const response = await fetch(
                '/operation/accounting/receiptVouchers/currencies',
                { headers: { 'Accept': 'application/json' } }
            );
            const data = await response.json();

            if (!data.success || !data.rows) return;

            const select = document.getElementById('CoinsID');
            if (!select) return;

            select.innerHTML = '<option value="">اختر العملة</option>';

            data.rows.forEach(c => {
                const option = document.createElement('option');
                option.value = c.id;
                option.textContent = `${c.code} - ${c.name}`;
                option.dataset.rate = c.exchangeRate;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('❌ خطأ في تحميل العملات:', error);
        }
    }

    async function loadNextVoucherNumber() {
        try {
            const response = await fetch(
                '/operation/accounting/receiptVouchers/next-number',
                { headers: { 'Accept': 'application/json' } }
            );
            const data = await response.json();

            if (data.success && data.nextNumber) {
                const numberEl = document.getElementById('ReceiptVoucherNumber');
                if (numberEl) numberEl.value = data.nextNumber;

                updateDisplay();
                return data.nextNumber;
            }
        } catch (error) {
            console.error('❌ خطأ في جلب رقم السند:', error);
        }
        return null;
    }

    document.getElementById('CoinsID')?.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const rate = parseFloat(selectedOption?.dataset?.rate) || 1;

        const exchangeRateEl = document.getElementById('ExchangeRate');
        if (exchangeRateEl) exchangeRateEl.value = rate;

        const debitAccountID = document.getElementById('DebitAccountID');
        const debitAccountName = document.getElementById('DebitAccountName');

        if (this.value && debitAccountID && debitAccountID.value) {
            debitAccountID.value = '';
            if (debitAccountName) debitAccountName.value = '';
        }

        if (
            state.mode !== 'view' &&
            !debitAccountID?.dataset?.currencyLocked
        ) {
            this.disabled = false;
            this.classList.remove('bg-body-secondary');
        }

        updateSummary();
    });

    loadCurrencies();

    // ══════════════════════════════════════════════════════════
    // الحساب الدائن — العميل
    // ══════════════════════════════════════════════════════════

    document.getElementById('CreditAccountName')?.addEventListener('click', function (e) {
        e.preventDefault();

        if (state.mode === 'view' || saveInProgress) return;

        if (typeof window.AccountPicker === 'undefined') {
            toastError('مودال اختيار الحسابات غير محمّل');
            return;
        }

        window.AccountPicker.open('customer', function (account) {
            document.getElementById('CreditAccountID').value = account.id;
            document.getElementById('CreditAccountName').value =
                account.code + ' - ' + account.name;

            creditBalanceInfo = {
                id: account.id,
                name: account.name,
                balance: parseFloat(account.balance) || 0,
                nature: (account.nature !== null && account.nature !== undefined)
                    ? parseInt(account.nature)
                    : 0,
            };

            updateSummary();
        });
    });

    // ══════════════════════════════════════════════════════════
    // الحساب المدين — الصندوق / البنك
    // ══════════════════════════════════════════════════════════

    document.getElementById('DebitAccountName')?.addEventListener('click', function (e) {
        e.preventDefault();

        if (state.mode === 'view' || saveInProgress) return;

        if (typeof window.AccountPicker === 'undefined') {
            toastError('مودال اختيار الحسابات غير محمّل');
            return;
        }

        const paymentMethod = document.getElementById('PaymentMethod').value;
        const methodInt = parseInt(paymentMethod) || 2;
        const type = methodInt === 3 ? 'bank' : 'cash';

        const currentCoinsID = document.getElementById('CoinsID').value || null;

        window.AccountPicker.open(
            type,
            function (account) {
                document.getElementById('DebitAccountID').value = account.id;
                document.getElementById('DebitAccountName').value =
                    account.code + ' - ' + account.name;

                const debitAccountID = document.getElementById('DebitAccountID');

                if (account.currency_id) {
                    const coinsSelect = document.getElementById('CoinsID');

                    const exists = Array.from(coinsSelect.options).some(
                        o => String(o.value) === String(account.currency_id)
                    );

                    if (!exists) {
                        const opt = document.createElement('option');
                        opt.value = account.currency_id;
                        opt.textContent = account.currency_code || '';
                        opt.dataset.rate = account.exchange_rate || 1;
                        coinsSelect.appendChild(opt);
                    }

                    coinsSelect.value = account.currency_id;
                    coinsSelect.disabled = true;
                    coinsSelect.classList.add('bg-body-secondary');
                    coinsSelect.dataset.locked = '1';

                    const exchangeEl = document.getElementById('ExchangeRate');
                    if (exchangeEl) exchangeEl.value = account.exchange_rate || 1;

                    lockExchangeRate();

                    if (debitAccountID) {
                        debitAccountID.dataset.currencyLocked = '1';
                    }

                    updateSummary();

                    toastInfo('تم قفل العملة على: ' + (account.currency_code || ''));
                }
            },
            { coinsID: currentCoinsID, allowedTypes: [type] }
        );
    });

    // ══════════════════════════════════════════════════════════
    // طريقة القبض — change
    // ══════════════════════════════════════════════════════════

    document.getElementById('PaymentMethod')?.addEventListener('change', function () {
        const methodInt = parseInt(this.value) || 0;

        const debitContainer = document.getElementById('debitAccountContainer');
        const debitLabel = document.getElementById('debitAccountLabel');

        if (!debitContainer) return;

        if (methodInt === 2 || methodInt === 3) {
            debitContainer.classList.remove('d-none');
            if (debitLabel) {
                debitLabel.textContent = methodInt === 2 ? 'حساب الصندوق' : 'حساب البنك';
            }
        } else {
            debitContainer.classList.add('d-none');

            const debitID = document.getElementById('DebitAccountID');
            const debitName = document.getElementById('DebitAccountName');

            if (debitID) {
                debitID.value = '';
                delete debitID.dataset.currencyLocked;
            }
            if (debitName) debitName.value = '';
        }

        const coinsSelect = document.getElementById('CoinsID');
        const debitID = document.getElementById('DebitAccountID');

        if (coinsSelect && state.mode !== 'view') {
            if (!debitID?.value) {
                coinsSelect.disabled = false;
                coinsSelect.classList.remove('bg-body-secondary');
                delete coinsSelect.dataset.locked;
            }
        }
    });

    // ══════════════════════════════════════════════════════════
    // تعطيل النموذج
    // ══════════════════════════════════════════════════════════

    function toggleFormDisabled(disabled) {
        ['Amount', 'CoinsID', 'PaymentMethod', 'Notes'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = disabled;
        });

        ['CreditAccountName', 'DebitAccountName'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;

            el.classList.toggle('bg-body-secondary', disabled);
            el.style.cursor = disabled ? 'not-allowed' : 'pointer';
        });

        lockExchangeRate();
    }

    // ══════════════════════════════════════════════════════════
    // SUMMARY
    // ══════════════════════════════════════════════════════════

    window.updateSummary = function () {

        const amount = parseFloat(document.getElementById('Amount')?.value) || 0;
        const rate = parseFloat(document.getElementById('ExchangeRate')?.value) || 1;
        const paid = amount * rate;

        /* ═══ التفقيط ═══ */
        const coinsSelect = document.getElementById('CoinsID');
        const selectedOption = coinsSelect?.options[coinsSelect.selectedIndex];
        const currencyName =
            selectedOption?.textContent?.split(' - ')[1]?.trim() || '';

        const amountWordsEl = document.getElementById('AmountWords');
        if (amountWordsEl) {
            if (amount <= 0 || isNaN(amount)) {
                amountWordsEl.value = '';
            } else if (typeof Utils !== 'undefined' && Utils.numberToWords) {
                amountWordsEl.value = Utils.numberToWords(amount, currencyName);
            }
        }

        /* ═══ الرصيد ═══ */
        const prevBalance = creditBalanceInfo?.balance || 0;
        const nature = parseInt(creditBalanceInfo?.nature ?? 0);

        /*
         * طبيعة الحساب (nature):
         *
         * nature = 0 → مدين (العميل الذي علينا تحصيله)
         *              القبض يُقلّل رصيده (يدفع)
         *              remain = prevBalance - paid
         *
         * nature = 1 → دائن (الإيرادات / المورد)
         *              القبض يُزيد رصيده
         *              remain = prevBalance + paid
         */
        const remain = nature === 1
            ? prevBalance + paid
            : prevBalance - paid;

        const summaryPrevious = document.getElementById('SummaryPrevious');
        const summaryPaid     = document.getElementById('SummaryPaid');
        const summaryRemain   = document.getElementById('SummaryRemain');

        if (summaryPrevious) summaryPrevious.textContent = prevBalance.toFixed(2);
        if (summaryPaid)     summaryPaid.textContent     = paid.toFixed(2);
        if (summaryRemain)   summaryRemain.textContent   = remain.toFixed(2);
    };

    // ══════════════════════════════════════════════════════════
    // DISPLAY
    // ══════════════════════════════════════════════════════════

    function updateDisplay() {
        const num  = document.getElementById('ReceiptVoucherNumber')?.value || '';
        const date = document.getElementById('ReceiptVoucherDate')?.value   || '';

        const displayNum  = document.getElementById('ReceiptVoucherNumberDisplay');
        const displayDate = document.getElementById('ReceiptVoucherDateDisplay');

        if (displayNum) {
            displayNum.textContent = num ? 'رقم السند: ' + num : 'رقم السند: --';
        }

        if (displayDate) {
            displayDate.innerHTML = date
                ? '<i class="bi bi-calendar3 me-1"></i> ' + date
                : '<i class="bi bi-calendar3 me-1"></i> --';
        }
    }

    // ══════════════════════════════════════════════════════════
    // ORIGINAL DATA
    // ══════════════════════════════════════════════════════════

    let originalVoucherData = null;

    function captureVoucherData() {
        return {
            creditAccountID: document.getElementById('CreditAccountID').value.trim(),
            debitAccountID:  document.getElementById('DebitAccountID').value.trim(),
            coinsID:         document.getElementById('CoinsID').value.trim(),
            amount:          parseFloat(document.getElementById('Amount').value) || 0,
            exchangeRate:    parseFloat(document.getElementById('ExchangeRate').value) || 1,
            paymentMethod:   document.getElementById('PaymentMethod').value || '',
            notes:           document.getElementById('Notes').value.trim(),
            voucherDate:     document.getElementById('ReceiptVoucherDate').value || '',
        };
    }

    function hasChanges() {
        if (!originalVoucherData) return true;

        return JSON.stringify(captureVoucherData())
            !== JSON.stringify(originalVoucherData);
    }

    // ══════════════════════════════════════════════════════════
    // CLEAR
    // ══════════════════════════════════════════════════════════

    function clearForm() {
        [
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
        ].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;

            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else {
                el.value = '';
            }
        });

        document.getElementById('debitAccountContainer')?.classList.add('d-none');

        const amountWords = document.getElementById('AmountWords');
        if (amountWords) amountWords.value = '';

        ['SummaryPrevious', 'SummaryPaid', 'SummaryRemain'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '0.00';
        });

        const coinsSelect = document.getElementById('CoinsID');
        if (coinsSelect) {
            coinsSelect.disabled = false;
            coinsSelect.classList.remove('bg-body-secondary');
            delete coinsSelect.dataset.locked;
        }

        const debitAccountID = document.getElementById('DebitAccountID');
        if (debitAccountID) delete debitAccountID.dataset.currencyLocked;

        lockExchangeRate();

        const numEl = document.getElementById('ReceiptVoucherNumber');
        if (numEl) numEl.dataset.id = '';

        creditBalanceInfo = null;
        originalVoucherData = null;
        currentVoucherIsPosted = false;

        state.setMode('view');
        updateActionButtons('view', false, false);
        updateDisplay();
    }

    // ══════════════════════════════════════════════════════════
    // NEW
    // ══════════════════════════════════════════════════════════

    window.resetVoucher = async function () {
        if (saveInProgress) return;

        const hasUnsavedChanges =
            state.mode === 'edit' ||
            (state.mode === 'add' && hasChanges() && originalVoucherData !== null);

        if (hasUnsavedChanges) {
            const msg = 'لديك تغييرات غير محفوظة. هل تريد تجاهلها والبدء بسند جديد؟';

            const ok = typeof window.appConfirm === 'function'
                ? await window.appConfirm(msg, {
                    title: 'تغييرات غير محفوظة',
                    okText: 'تجاهل والبدء',
                    danger: true,
                })
                : confirm(msg);

            if (!ok) return;
        }

        clearForm();

        document.getElementById('ReceiptVoucherDate').value =
            new Date().toISOString().slice(0, 10);

        await loadNextVoucherNumber();

        currentVoucherIsPosted = false;

        state.setMode('add');
        updateActionButtons('add', false, false);
        updateDisplay();
        lockExchangeRate();

        toastInfo('يمكنك الآن إدخال بيانات سند القبض');

        setTimeout(() => document.getElementById('PaymentMethod')?.focus(), 300);
    };

    // ══════════════════════════════════════════════════════════
    // VALIDATE
    // ══════════════════════════════════════════════════════════

    function validateForm() {
        const errors = [];

        const creditAccountID = document.getElementById('CreditAccountID').value.trim();
        const debitAccountID  = document.getElementById('DebitAccountID').value.trim();
        const amount          = parseFloat(document.getElementById('Amount').value) || 0;
        const coinsID         = document.getElementById('CoinsID').value.trim();
        const exchangeRate    = parseFloat(document.getElementById('ExchangeRate').value) || 0;
        const paymentMethod   = document.getElementById('PaymentMethod').value;

        if (!creditAccountID) errors.push('يرجى اختيار الحساب الدائن.');
        if (!paymentMethod)   errors.push('يرجى اختيار طريقة القبض.');
        if (!debitAccountID)  errors.push('يرجى اختيار حساب القبض.');

        if (creditAccountID && debitAccountID && creditAccountID === debitAccountID) {
            errors.push('لا يمكن أن يكون الحساب الدائن هو نفس حساب القبض.');
        }

        if (amount <= 0 || isNaN(amount)) {
            errors.push('المبلغ يجب أن يكون أكبر من صفر.');
        }

        if (!coinsID) errors.push('يرجى اختيار العملة.');

        if (exchangeRate <= 0 || isNaN(exchangeRate)) {
            errors.push('سعر الصرف يجب أن يكون أكبر من صفر.');
        }

        return errors.length > 0 ? errors : null;
    }

    // ══════════════════════════════════════════════════════════
    // SAVE
    // ══════════════════════════════════════════════════════════

    window.saveVoucher = async function () {
        if (saveInProgress) return false;

        const now = Date.now();
        if (now - lastSaveTime < MIN_SAVE_INTERVAL) return false;

        const isEdit = state.mode === 'edit';

        if (isEdit && !hasChanges()) {
            toastError('لم تُجرِ أي تعديل على السند.');
            return false;
        }

        const errors = validateForm();
        if (errors) {
            toastError(errors[0]);
            return false;
        }

        saveInProgress = true;
        lastSaveTime = now;

        const saveBtn = document.getElementById('btnSaveReceiptVoucher');
        const originalSaveText = saveBtn ? saveBtn.innerHTML : '';

        if (saveBtn) {
            saveBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span> جاري الحفظ...';
        }

        const id = document.getElementById('ReceiptVoucherNumber').dataset.id;

        const payload = {
            voucherNumber:   document.getElementById('ReceiptVoucherNumber').value.trim() || null,
            creditAccountID: document.getElementById('CreditAccountID').value.trim(),
            debitAccountID:  document.getElementById('DebitAccountID').value.trim(),
            coinsID:         document.getElementById('CoinsID').value.trim(),
            amount:          parseFloat(document.getElementById('Amount').value) || 0,
            exchangeRate:    parseFloat(document.getElementById('ExchangeRate').value) || 1,
            paymentMethod:   parseInt(document.getElementById('PaymentMethod').value) || null,
            notes:           document.getElementById('Notes').value.trim() || null,
            voucherDate:     document.getElementById('ReceiptVoucherDate').value || null,
        };

        try {
            const url = isEdit
                ? `/operation/accounting/receiptVouchers/${id}`
                : `/operation/accounting/receiptVouchers`;

            const method = isEdit ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!data.success) {
                toastError(data.message || 'حدث خطأ غير متوقع.');
                return false;
            }

            toastSuccess(isEdit ? 'تم تعديل السند بنجاح.' : 'تم حفظ السند بنجاح.');

            if (data.voucherID) {
                document.getElementById('ReceiptVoucherNumber').dataset.id = data.voucherID;
            }

            if (data.voucherNumber) {
                document.getElementById('ReceiptVoucherNumber').value = data.voucherNumber;
            }

            currentVoucherIsPosted = !!data.isPosted;

            state.setMode('view');
            updateActionButtons('view', currentVoucherIsPosted, true);
            updateDisplay();

            /* بعد الإنشاء: أعد التحميل لعرض الرصيد الصحيح */
            if (!isEdit && data.voucherID) {
                saveInProgress = false;
                await loadVoucher(data.voucherID);
                return true;
            }

            updateSummary();
            originalVoucherData = null;

            return true;

        } catch (error) {
            console.error('❌ خطأ في الحفظ:', error);
            toastError('فشل الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
            return false;

        } finally {
            saveInProgress = false;

            /* لا نُعيد التفعيل إلا إذا كنا لا نزال في add/edit */
            if (state.mode !== 'view') {
                ['btnSaveReceiptVoucher', 'btnSaveAndNewReceiptVoucher']
                    .forEach(id => {
                        const btn = document.getElementById(id);
                        if (btn) btn.disabled = false;
                    });
            }

            if (saveBtn) saveBtn.innerHTML = originalSaveText;
        }
    };

    // ══════════════════════════════════════════════════════════
    // SAVE + NEW
    // ══════════════════════════════════════════════════════════

    window.saveAndNewVoucher = async function () {
        if (saveInProgress) return;

        const saved = await saveVoucher();

        if (saved === true) {
            await resetVoucher();
        }
    };

    // ══════════════════════════════════════════════════════════
    // EDIT
    // ══════════════════════════════════════════════════════════

    window.editVoucher = function () {
        if (saveInProgress) return;

        const id = document.getElementById('ReceiptVoucherNumber').dataset.id;

        if (!id) {
            toastError('لا يوجد سند محدد للتعديل.');
            return;
        }

        if (currentVoucherIsPosted) {
            toastError('لا يمكن التعديل لأن السند مُرحّل. ألغِ الترحيل أولاً.');
            return;
        }

        originalVoucherData = captureVoucherData();

        state.setMode('edit');
        updateActionButtons('edit', false, true);

        toastInfo('يمكنك الآن تعديل بيانات سند القبض');
    };

    // ══════════════════════════════════════════════════════════
    // CANCEL
    // ══════════════════════════════════════════════════════════

    window.cancelVoucher = async function () {
        if (saveInProgress) return;

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm('هل أنت متأكد من إلغاء العملية؟', {
                title: 'إلغاء العملية',
                okText: 'نعم، إلغاء',
                danger: true,
            })
            : confirm('هل أنت متأكد من إلغاء العملية؟');

        if (!ok) return;

        clearForm();
        loadNextVoucherNumber();

        toastInfo('تم إلغاء العملية');
    };

    // ══════════════════════════════════════════════════════════
    // PRINT
    // ══════════════════════════════════════════════════════════

    window.printVoucher = function () {
        const voucherId = document.getElementById('ReceiptVoucherNumber').dataset.id;

        if (!voucherId) {
            toastError('لا يمكن الطباعة لأنه لا يوجد سند محفوظ. يرجى حفظ السند أولاً.');
            return;
        }

        const printUrl = `/operation/accounting/receiptVouchers/${voucherId}/print`;

        window.open(printUrl, '_blank', 'width=900,height=700');
    };

    // ══════════════════════════════════════════════════════════
    // POST — ترحيل
    // ══════════════════════════════════════════════════════════

    window.postVoucher = async function () {
        if (saveInProgress) return;

        const id = document.getElementById('ReceiptVoucherNumber').dataset.id;
        if (!id) { toastError('لا يوجد سند محدد.'); return; }

        const msg = 'هل تريد ترحيل هذا السند؟\nسيتم إنشاء القيد المحاسبي.';

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm(msg, { title: 'ترحيل السند', okText: 'ترحيل' })
            : confirm(msg);

        if (!ok) return;

        saveInProgress = true;

        try {
            const response = await fetch(
                `/operation/accounting/receiptVouchers/${id}/post`,
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                }
            );

            const data = await response.json();

            if (!data.success) {
                toastError(data.message || 'فشل ترحيل السند.');
                return;
            }

            toastSuccess(data.message || 'تم ترحيل السند بنجاح.');

            /* ★ صفّر saveInProgress قبل loadVoucher */
            saveInProgress = false;

            await loadVoucher(id);

            return;

        } catch (error) {
            console.error('❌ خطأ في الترحيل:', error);
            toastError('فشل الاتصال بالخادم.');
        } finally {
            saveInProgress = false;
        }
    };

    // ══════════════════════════════════════════════════════════
    // UNPOST — إلغاء الترحيل
    // ══════════════════════════════════════════════════════════

    window.unpostVoucher = async function () {
        if (saveInProgress) return;

        const id = document.getElementById('ReceiptVoucherNumber').dataset.id;
        if (!id) { toastError('لا يوجد سند محدد.'); return; }

        const msg = 'هل تريد إلغاء ترحيل هذا السند؟\nسيتم حذف القيد المحاسبي.';

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm(msg, {
                title: 'إلغاء الترحيل',
                okText: 'إلغاء الترحيل',
                danger: true,
            })
            : confirm(msg);

        if (!ok) return;

        saveInProgress = true;

        try {
            const response = await fetch(
                `/operation/accounting/receiptVouchers/${id}/unpost`,
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                }
            );

            const data = await response.json();

            if (!data.success) {
                toastError(data.message || 'فشل إلغاء الترحيل.');
                return;
            }

            toastSuccess(data.message || 'تم إلغاء الترحيل بنجاح.');

            /* ★ صفّر saveInProgress قبل loadVoucher */
            saveInProgress = false;

            await loadVoucher(id);

            return;

        } catch (error) {
            console.error('❌ خطأ في إلغاء الترحيل:', error);
            toastError('فشل الاتصال بالخادم.');
        } finally {
            saveInProgress = false;
        }
    };

    // ══════════════════════════════════════════════════════════
    // SEARCH
    // ══════════════════════════════════════════════════════════

    window.searchVoucher = function () {
        if (saveInProgress) return;
        openSearchModal();
    };

    let searchModal = null;
    let searchTimeout = null;

    function openSearchModal() {
        const modalEl = document.getElementById('ReceiptVoucherSearchModal');

        if (!searchModal) {
            searchModal = new bootstrap.Modal(modalEl);
        }

        document.getElementById('ReceiptVoucherSearchInput').value = '';

        document.getElementById('ReceiptVoucherSearchResults').innerHTML =
            '<tr><td colspan="9" class="text-center text-muted py-4">جاري التحميل...</td></tr>';

        searchModal.show();

        setTimeout(() => {
            document.getElementById('ReceiptVoucherSearchInput')?.focus();
            performSearch();
        }, 300);
    }

    // ══════════════════════════════════════════════════════════
    // PERFORM SEARCH
    // ══════════════════════════════════════════════════════════

    async function performSearch() {
        const search = document.getElementById('ReceiptVoucherSearchInput').value.trim();
        const tbody = document.getElementById('ReceiptVoucherSearchResults');

        tbody.innerHTML =
            '<tr><td colspan="9" class="text-center text-muted py-4">جاري البحث...</td></tr>';

        try {
            const response = await fetch(
                `/operation/accounting/receiptVouchers/list?search=${encodeURIComponent(search)}`,
                { headers: { 'Accept': 'application/json' } }
            );

            const data = await response.json();

            tbody.innerHTML = '';

            if (!data.success || !data.rows || data.rows.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="9" class="text-center text-muted py-4">لا توجد نتائج</td></tr>';
                return;
            }

            data.rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';

                const paymentMethodText = {
                    2: 'نقد',
                    3: 'تحويل بنكي',
                }[row.paymentMethod] || '—';

                const statusBadge = row.isPosted
                    ? '<span class="badge bg-success">مرحّل</span>'
                    : '<span class="badge bg-secondary">مسودة</span>';

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

                    <td class="text-center">
                        ${statusBadge}
                    </td>

                    <td class="text-center text-nowrap"
                        data-actions-cell></td>
                `;

                const tdActions = tr.querySelector('[data-actions-cell]');
                tdActions.addEventListener('click', e => e.stopPropagation());

                if (row.isPosted) {
                    const btnUnpost = document.createElement('button');
                    btnUnpost.type = 'button';
                    btnUnpost.className = 'btn btn-sm btn-outline-warning me-1';
                    btnUnpost.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
                    btnUnpost.title = 'إلغاء الترحيل';
                    btnUnpost.addEventListener('click', async e => {
                        e.stopPropagation();
                        await quickUnpost(row.id);
                    });
                    tdActions.appendChild(btnUnpost);
                } else {
                    const btnPost = document.createElement('button');
                    btnPost.type = 'button';
                    btnPost.className = 'btn btn-sm btn-outline-success me-1';
                    btnPost.innerHTML = '<i class="bi bi-check2-circle"></i>';
                    btnPost.title = 'ترحيل';
                    btnPost.addEventListener('click', async e => {
                        e.stopPropagation();
                        await quickPost(row.id);
                    });
                    tdActions.appendChild(btnPost);
                }

                const btnDelete = document.createElement('button');
                btnDelete.type = 'button';
                btnDelete.className = 'btn btn-sm btn-outline-danger';
                btnDelete.innerHTML = '<i class="bi bi-trash"></i>';
                btnDelete.title = 'حذف السند نهائياً';
                btnDelete.addEventListener('click', async e => {
                    e.stopPropagation();
                    await deleteReceiptVoucher(row.id, row.voucherNumber);
                });
                tdActions.appendChild(btnDelete);

                tr.addEventListener('click', () => {
                    loadVoucher(row.id);
                    searchModal.hide();
                });

                tbody.appendChild(tr);
            });

        } catch (error) {
            console.error('❌ خطأ في البحث:', error);
            tbody.innerHTML =
                '<tr><td colspan="9" class="text-center text-danger py-4">فشل الاتصال بالخادم</td></tr>';
        }
    }

    // ══════════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════════

    async function deleteReceiptVoucher(id, voucherNumber) {
        const label = voucherNumber ? 'رقم ' + voucherNumber : '#' + id;

        const msg = 'هل أنت متأكد من حذف السند ' + label + '؟\nلا يمكن التراجع عن هذه العملية.';

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm(msg, {
                title: 'حذف سند قبض',
                okText: 'حذف نهائي',
                danger: true,
            })
            : confirm(msg);

        if (!ok) return;

        try {
            const response = await fetch(
                `/operation/accounting/receiptVouchers/${id}`,
                {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                }
            );

            const data = await response.json();

            if (!data.success) {
                toastError(data.message || 'فشل حذف السند.');
                return;
            }

            toastSuccess('تم حذف السند بنجاح.');

            const currentID = document.getElementById('ReceiptVoucherNumber').dataset.id;

            if (currentID && String(currentID) === String(id)) {
                clearForm();
                loadNextVoucherNumber();
            }

            performSearch();

        } catch (error) {
            console.error('❌ خطأ في الحذف:', error);
            toastError('فشل الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
        }
    }

    // ══════════════════════════════════════════════════════════
    // QUICK POST / UNPOST
    // ══════════════════════════════════════════════════════════

    async function quickPost(id) {
        const msg = 'هل تريد ترحيل هذا السند؟';

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm(msg, { title: 'ترحيل', okText: 'ترحيل' })
            : confirm(msg);

        if (!ok) return;

        try {
            const r = await fetch(
                `/operation/accounting/receiptVouchers/${id}/post`,
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                }
            );
            const d = await r.json();

            if (!d.success) { toastError(d.message || 'فشل.'); return; }
            toastSuccess(d.message || 'تم الترحيل.');
            performSearch();
        } catch (e) {
            console.error(e);
            toastError('فشل الاتصال.');
        }
    }

    async function quickUnpost(id) {
        const msg = 'هل تريد إلغاء ترحيل هذا السند؟';

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm(msg, {
                title: 'إلغاء الترحيل',
                okText: 'إلغاء الترحيل',
                danger: true,
            })
            : confirm(msg);

        if (!ok) return;

        try {
            const r = await fetch(
                `/operation/accounting/receiptVouchers/${id}/unpost`,
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                }
            );
            const d = await r.json();

            if (!d.success) { toastError(d.message || 'فشل.'); return; }
            toastSuccess(d.message || 'تم إلغاء الترحيل.');
            performSearch();
        } catch (e) {
            console.error(e);
            toastError('فشل الاتصال.');
        }
    }

    // ══════════════════════════════════════════════════════════
    // LOAD VOUCHER
    // ══════════════════════════════════════════════════════════

    async function loadVoucher(id) {
        if (saveInProgress) return;

        try {
            const response = await fetch(
                `/operation/accounting/receiptVouchers/${id}`,
                { headers: { 'Accept': 'application/json' } }
            );

            const data = await response.json();

            if (!data.success || !data.voucher) {
                toastError('فشل تحميل السند.');
                return;
            }

            const v = data.voucher;

            document.getElementById('ReceiptVoucherNumber').value = v.voucherNumber;
            document.getElementById('ReceiptVoucherNumber').dataset.id = v.id;
            document.getElementById('ReceiptVoucherDate').value = v.voucherDate;

            document.getElementById('CreditAccountID').value = v.creditAccountID;
            document.getElementById('CreditAccountName').value =
                v.creditAccountCode + ' - ' + v.creditAccountName;

            creditBalanceInfo = {
                id: v.creditAccountID,
                name: v.creditAccountName,
                balance: parseFloat(v.creditBalanceBefore) || 0,
                nature: parseInt(v.creditNature) || 0,
            };

            document.getElementById('PaymentMethod').value =
                v.paymentMethod ? String(v.paymentMethod) : '';

            if (v.paymentMethod) {
                document.getElementById('PaymentMethod')
                    .dispatchEvent(new Event('change'));
            }

            document.getElementById('DebitAccountID').value = v.debitAccountID;
            document.getElementById('DebitAccountName').value =
                v.debitAccountCode + ' - ' + v.debitAccountName;

            document.getElementById('Amount').value = v.amount;
            document.getElementById('CoinsID').value = v.currencyID;
            document.getElementById('ExchangeRate').value = v.exchangeRate;
            document.getElementById('Notes').value = v.notes || '';

            const coinsSelect = document.getElementById('CoinsID');
            if (coinsSelect) {
                coinsSelect.disabled = true;
                coinsSelect.classList.add('bg-body-secondary');
            }

            lockExchangeRate();

            currentVoucherIsPosted = !!v.isPosted;

            updateSummary();
            updateDisplay();

            state.setMode('view');
            updateActionButtons('view', currentVoucherIsPosted, true);

            toastSuccess('تم تحميل السند');

        } catch (error) {
            console.error('❌ خطأ:', error);
            toastError('فشل تحميل السند.');
        }
    }

    // ══════════════════════════════════════════════════════════
    // EVENTS
    // ══════════════════════════════════════════════════════════

    document.getElementById('Amount')
        ?.addEventListener('input', updateSummary);

    document.getElementById('ReceiptVoucherSearchInput')
        ?.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 300);
        });

    document.getElementById('ReceiptVoucherClearBtn')
        ?.addEventListener('click', function () {
            document.getElementById('ReceiptVoucherSearchInput').value = '';
            document.getElementById('ReceiptVoucherSearchInput').focus();
            performSearch();
        });

    document.getElementById('ReceiptVoucherSearchInput')
        ?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });

    document.getElementById('btnNewReceiptVoucher')
        ?.addEventListener('click', resetVoucher);

    document.getElementById('btnSaveReceiptVoucher')
        ?.addEventListener('click', saveVoucher);

    document.getElementById('btnSaveAndNewReceiptVoucher')
        ?.addEventListener('click', saveAndNewVoucher);

    document.getElementById('btnEditReceiptVoucher')
        ?.addEventListener('click', editVoucher);

    document.getElementById('btnCancelReceiptVoucher')
        ?.addEventListener('click', cancelVoucher);

    document.getElementById('btnPrintReceiptVoucher')
        ?.addEventListener('click', printVoucher);

    document.getElementById('btnSearchReceiptVoucher')
        ?.addEventListener('click', searchVoucher);

    document.getElementById('btnPostReceiptVoucher')
        ?.addEventListener('click', postVoucher);

    document.getElementById('btnUnpostReceiptVoucher')
        ?.addEventListener('click', unpostVoucher);

    // ══════════════════════════════════════════════════════════
    // INITIALIZE
    // ══════════════════════════════════════════════════════════

    clearForm();
    loadNextVoucherNumber();

    console.log('✅ تم تهيئة صفحة سند القبض بنجاح');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReceiptVoucherPage);
} else {
    initReceiptVoucherPage();
}