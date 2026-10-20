/**
 * ═══════════════════════════════════════════════════════════
 * صفحة سند الصرف — متوافقة مع API v2
 * ═══════════════════════════════════════════════════════════
 */

import '../receipt/accountPicker.js';

if (
    window.AccountPicker &&
    typeof window.AccountPicker.setUrl === 'function'
) {
    window.AccountPicker.setUrl(
        '/operation/accounting/paymentVouchers/picker'
    );
}

function initPaymentVoucherPage() {

    console.log('🚀 بدء تهيئة صفحة سند الصرف');

    const API = '/operation/accounting/paymentVouchers';

    // ══════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════

    const $ = (id) => document.getElementById(id);

    function unwrap(json) {
        if (!json || typeof json !== 'object') return {};
        if (
            json.data &&
            typeof json.data === 'object' &&
            !Array.isArray(json.data)
        ) {
            return json.data;
        }
        return json;
    }

    function pickArray(obj, ...keys) {
        for (const k of keys) {
            const v = obj?.[k];
            if (Array.isArray(v)) return v;
        }
        return [];
    }

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function toast(message, type = 'info') {
        if (typeof showSystemToast === 'function') {
            showSystemToast(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    }

    const toastInfo    = (m) => toast(m, 'info');
    const toastSuccess = (m) => toast(m, 'success');
    const toastWarning = (m) => toast(m, 'warning');
    const toastError   = (m) => toast(m, 'danger');

    /**
     * ★ يستخرج أفضل رسالة خطأ من رد الـ API.
     * الأولوية:
     *   1) أول خطأ حقل في data.errors
     *   2) data.message
     *   3) رسالة عامة
     */
    function handleApiError(data, fallback = 'تعذر إتمام العملية. يرجى المحاولة لاحقاً.') {
        if (!data || typeof data !== 'object') return fallback;

        const errors = data.errors || {};

        for (const field in errors) {
            const val = errors[field];
            const msg = Array.isArray(val) ? val[0] : val;
            if (msg && String(msg).trim() !== '') {
                return String(msg).trim();
            }
        }

        if (data.message && String(data.message).trim() !== '') {
            return String(data.message).trim();
        }

        return fallback;
    }

    if (typeof Modals !== 'undefined' && Modals.initAll) {
        Modals.initAll();
    }

    // ══════════════════════════════════════════════════════════
    // STATE
    // ══════════════════════════════════════════════════════════

    const state = new StateManager('payment-voucher');

    let saveInProgress = false;
    let lastSaveTime = 0;
    const MIN_SAVE_INTERVAL = 1500;

    let beneficiaryBalanceInfo = null;
    let currentBalanceInfo = null;
    let originalPaymentAccountID = null;
    let originalPaymentLocalAmount = 0;
    let currentVoucherIsPosted = false;
    let originalVoucherData = null;

    let searchPagination = {
        cursor: null,
        hasMore: false,
        loading: false,
    };

    // ══════════════════════════════════════════════════════════
    // LOCK EXCHANGE RATE
    // ══════════════════════════════════════════════════════════

    function lockExchangeRate() {
        const exchangeEl = $('ExchangeRate');
        if (!exchangeEl) return;

        exchangeEl.readOnly = true;
        exchangeEl.tabIndex = -1;
        exchangeEl.style.cursor = 'not-allowed';
        exchangeEl.style.backgroundColor = 'var(--bs-tertiary-bg)';
    }

    lockExchangeRate();

    state.setFields([
        'PaymentVoucherNumber',
        'PaymentVoucherDate',
        'BeneficiaryAccountName',
        'PaymentAccountName',
        'Amount',
        'CoinsID',
        'ExchangeRate',
        'PaymentMethod',
        'Notes',
    ]);

    state.setButtons({
        add:     'btnNewPaymentVoucher',
        save:    'btnSavePaymentVoucher',
        saveNew: 'btnSaveAndNewPaymentVoucher',
        cancel:  'btnCancelPaymentVoucher',
        edit:    'btnEditPaymentVoucher',
        print:   'btnPrintPaymentVoucher',
        search:  'btnSearchPaymentVoucher',
    });

    state.onModeChange(function (mode) {
        toggleFormDisabled(mode === 'view');
        updateDisplay();
        lockExchangeRate();

        const hasData = !!$('PaymentVoucherNumber')?.dataset?.id;
        updateActionButtons(mode, currentVoucherIsPosted, hasData);
        updateSummary();
    });

    // ══════════════════════════════════════════════════════════
    // ACTION BUTTONS
    // ══════════════════════════════════════════════════════════

    function updateActionButtons(mode, isPosted, hasData) {

        const isView = (mode === 'view');

        const saveBtn    = $('btnSavePaymentVoucher');
        const saveNewBtn = $('btnSaveAndNewPaymentVoucher');
        const cancelBtn  = $('btnCancelPaymentVoucher');

        if (isView) {
            if (saveBtn)    saveBtn.disabled = true;
            if (saveNewBtn) saveNewBtn.disabled = true;
            if (cancelBtn)  cancelBtn.classList.add('d-none');
        } else {
            if (saveBtn)    saveBtn.disabled = false;
            if (saveNewBtn) saveNewBtn.disabled = false;
            if (cancelBtn)  cancelBtn.classList.remove('d-none');
        }

        const newBtn = $('btnNewPaymentVoucher');
        if (newBtn) newBtn.disabled = !isView;

        const searchBtn = $('btnSearchPaymentVoucher');
        if (searchBtn) searchBtn.disabled = !isView || hasData;

        const editBtn = $('btnEditPaymentVoucher');
        if (editBtn) {
            editBtn.disabled = !hasData || isPosted || !isView;
            editBtn.classList.toggle('d-none', isPosted);
        }

        const postBtn = $('btnPostPaymentVoucher');
        if (postBtn) {
            const show = hasData && !isPosted;
            postBtn.classList.toggle('d-none', !show);
            postBtn.disabled = !hasData || isPosted || !isView;
        }

        const unpostBtn = $('btnUnpostPaymentVoucher');
        if (unpostBtn) {
            const show = hasData && isPosted;
            unpostBtn.classList.toggle('d-none', !show);
            unpostBtn.disabled = !hasData || !isPosted || !isView;
        }

        const printBtn = $('btnPrintPaymentVoucher');
        if (printBtn) printBtn.disabled = !hasData;
    }

    // ══════════════════════════════════════════════════════════
    // CURRENCIES
    // ══════════════════════════════════════════════════════════

    async function loadCurrencies() {
        try {
            const response = await fetch(`${API}/currencies`, {
                headers: { 'Accept': 'application/json' },
            });

            const data = await response.json();

            if (!data.success) return;

            const payload = unwrap(data);
            const list = pickArray(payload, 'currencies', 'rows');

            const select = $('CoinsID');
            if (!select) return;

            select.innerHTML = '<option value="">اختر العملة</option>';

            list.forEach(c => {
                const option = document.createElement('option');
                option.value = c.id;
                option.textContent = `${c.code} - ${c.name}`;
                option.dataset.rate = c.exchangeRate;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('❌ فشل تحميل العملات:', error);
            toastError('تعذر تحميل قائمة العملات. يرجى تحديث الصفحة.');
        }
    }

    async function loadNextVoucherNumber() {
        try {
            const response = await fetch(`${API}/next-number`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (!data.success) return null;

            const payload = unwrap(data);
            const number = payload.nextNumber || payload.voucherNumber;

            if (number) {
                const numberEl = $('PaymentVoucherNumber');
                if (numberEl) numberEl.value = number;
                updateDisplay();
                return number;
            }
        } catch (error) {
            console.error('❌ فشل جلب رقم السند:', error);
            toastWarning('تعذر جلب رقم السند التالي. سيتم توليده عند الحفظ.');
        }
        return null;
    }

    $('CoinsID')?.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const rate = parseFloat(selectedOption?.dataset?.rate) || 1;

        const exchangeRateEl = $('ExchangeRate');
        if (exchangeRateEl) exchangeRateEl.value = rate;

        const paymentAccountID = $('PaymentAccountID');
        const paymentAccountName = $('PaymentAccountName');

        if (this.value && paymentAccountID && paymentAccountID.value) {
            paymentAccountID.value = '';
            if (paymentAccountName) paymentAccountName.value = '';
        }

        if (
            state.mode !== 'view' &&
            !paymentAccountID?.dataset?.currencyLocked
        ) {
            this.disabled = false;
            this.classList.remove('bg-body-secondary');
        }

        updateSummary();
    });

    loadCurrencies();

    // ══════════════════════════════════════════════════════════
    // BENEFICIARY PICKER
    // ══════════════════════════════════════════════════════════

    $('BeneficiaryAccountName')?.addEventListener('click', function (e) {
        e.preventDefault();

        if (state.mode === 'view' || saveInProgress) return;

        if (typeof window.AccountPicker === 'undefined') {
            toastError('مكوّن اختيار الحسابات غير محمّل. يرجى تحديث الصفحة.');
            return;
        }

        window.AccountPicker.open(
            'supplier',
            function (account) {
                $('BeneficiaryAccountID').value = account.id;
                $('BeneficiaryAccountName').value =
                    account.code + ' - ' + account.name;

                beneficiaryBalanceInfo = {
                    id: account.id,
                    name: account.name,
                    balance: parseFloat(account.balance) || 0,
                    nature: (account.nature !== null && account.nature !== undefined)
                        ? parseInt(account.nature)
                        : 0,
                };

                updateSummary();
            },
            { allowedTypes: ['supplier', 'customer', 'expense'] }
        );
    });

    // ══════════════════════════════════════════════════════════
    // PAYMENT PICKER
    // ══════════════════════════════════════════════════════════

    $('PaymentAccountName')?.addEventListener('click', function (e) {
        e.preventDefault();

        if (state.mode === 'view' || saveInProgress) return;

        if (typeof window.AccountPicker === 'undefined') {
            toastError('مكوّن اختيار الحسابات غير محمّل. يرجى تحديث الصفحة.');
            return;
        }

        const paymentMethod = $('PaymentMethod').value;
        const methodInt = parseInt(paymentMethod) || 2;
        const type = methodInt === 3 ? 'bank' : 'cash';

        const currentCoinsID = $('CoinsID').value || null;

        window.AccountPicker.open(
            type,
            function (account) {
                $('PaymentAccountID').value = account.id;
                $('PaymentAccountName').value =
                    account.code + ' - ' + account.name;

                currentBalanceInfo = {
                    id: account.id,
                    name: account.name,
                    balance: parseFloat(account.balance) || 0,
                };

                const paymentAccountID = $('PaymentAccountID');

                if (account.currency_id) {
                    const coinsSelect = $('CoinsID');

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

                    const exchangeEl = $('ExchangeRate');
                    if (exchangeEl) exchangeEl.value = account.exchange_rate || 1;

                    lockExchangeRate();

                    if (paymentAccountID) {
                        paymentAccountID.dataset.currencyLocked = '1';
                    }

                    updateSummary();

                    toastInfo('تم قفل العملة على: ' + (account.currency_code || ''));
                }
            },
            { coinsID: currentCoinsID, allowedTypes: [type] }
        );
    });

    // ══════════════════════════════════════════════════════════
    // PAYMENT METHOD CHANGE
    // ══════════════════════════════════════════════════════════

    $('PaymentMethod')?.addEventListener('change', function () {
        const methodInt = parseInt(this.value) || 0;

        const paymentContainer = $('paymentAccountContainer');
        const paymentLabel = $('paymentAccountLabel');

        if (!paymentContainer) return;

        if (methodInt === 2 || methodInt === 3) {
            paymentContainer.classList.remove('d-none');
            if (paymentLabel) {
                paymentLabel.textContent = methodInt === 2
                    ? 'حساب الصندوق'
                    : 'حساب البنك';
            }
        } else {
            paymentContainer.classList.add('d-none');

            const paymentID = $('PaymentAccountID');
            const paymentName = $('PaymentAccountName');

            if (paymentID) {
                paymentID.value = '';
                delete paymentID.dataset.currencyLocked;
            }
            if (paymentName) paymentName.value = '';

            currentBalanceInfo = null;
        }

        const coinsSelect = $('CoinsID');
        const paymentID = $('PaymentAccountID');

        if (coinsSelect && state.mode !== 'view') {
            if (!paymentID?.value) {
                coinsSelect.disabled = false;
                coinsSelect.classList.remove('bg-body-secondary');
                delete coinsSelect.dataset.locked;
            }
        }
    });

    // ══════════════════════════════════════════════════════════
    // FORM DISABLE
    // ══════════════════════════════════════════════════════════

    function toggleFormDisabled(disabled) {
        ['Amount', 'CoinsID', 'PaymentMethod', 'Notes'].forEach(id => {
            const el = $(id);
            if (el) el.disabled = disabled;
        });

        ['BeneficiaryAccountName', 'PaymentAccountName'].forEach(id => {
            const el = $(id);
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

        const amount = parseFloat($('Amount')?.value) || 0;
        const rate = parseFloat($('ExchangeRate')?.value) || 1;
        const paid = amount * rate;

        const coinsSelect = $('CoinsID');
        const selectedOption = coinsSelect?.options[coinsSelect.selectedIndex];
        const currencyName =
            selectedOption?.textContent?.split(' - ')[1]?.trim() || '';

        const amountWordsEl = $('AmountWords');
        if (amountWordsEl) {
            if (amount <= 0 || isNaN(amount)) {
                amountWordsEl.value = '';
            } else if (typeof Utils !== 'undefined' && Utils.numberToWords) {
                try {
                    amountWordsEl.value = Utils.numberToWords(amount, currencyName);
                } catch (_) {
                    amountWordsEl.value = '';
                }
            }
        }

        const prevBalance = beneficiaryBalanceInfo?.balance || 0;
        const nature = parseInt(beneficiaryBalanceInfo?.nature ?? 0);

        const remain = nature === 1
            ? prevBalance - paid
            : prevBalance + paid;

        const summaryPrevious = $('SummaryPrevious');
        const summaryPaid     = $('SummaryPaid');
        const summaryRemain   = $('SummaryRemain');

        if (summaryPrevious) summaryPrevious.textContent = prevBalance.toFixed(2);
        if (summaryPaid)     summaryPaid.textContent     = paid.toFixed(2);
        if (summaryRemain)   summaryRemain.textContent   = remain.toFixed(2);
    };

    // ══════════════════════════════════════════════════════════
    // DISPLAY
    // ══════════════════════════════════════════════════════════

    function updateDisplay() {
        const num  = $('PaymentVoucherNumber')?.value || '';
        const date = $('PaymentVoucherDate')?.value   || '';

        const displayNum  = $('PaymentVoucherNumberDisplay');
        const displayDate = $('PaymentVoucherDateDisplay');

        if (displayNum) {
            displayNum.textContent = num ? 'رقم السند: ' + num : 'رقم السند: --';
        }

        if (displayDate) {
            displayDate.innerHTML = date
                ? '<i class="bi bi-calendar3 me-1"></i> ' + esc(date)
                : '<i class="bi bi-calendar3 me-1"></i> --';
        }
    }

    // ══════════════════════════════════════════════════════════
    // CAPTURE / COMPARE
    // ══════════════════════════════════════════════════════════

    function captureVoucherData() {
        return {
            beneficiaryAccountID: $('BeneficiaryAccountID').value.trim(),
            paymentAccountID:     $('PaymentAccountID').value.trim(),
            coinsID:              $('CoinsID').value.trim(),
            amount:               parseFloat($('Amount').value) || 0,
            exchangeRate:         parseFloat($('ExchangeRate').value) || 1,
            paymentMethod:        $('PaymentMethod').value || '',
            notes:                $('Notes').value.trim(),
            voucherDate:          $('PaymentVoucherDate').value || '',
        };
    }

    function hasChanges() {
        if (!originalVoucherData) return true;

        return JSON.stringify(captureVoucherData())
            !== JSON.stringify(originalVoucherData);
    }

    function hasAnyInput() {
        return ['BeneficiaryAccountID', 'PaymentAccountID', 'Amount', 'Notes']
            .some(id => {
                const el = $(id);
                if (!el) return false;
                const val = (el.value ?? '').toString().trim();
                return val !== '';
            });
    }

    // ══════════════════════════════════════════════════════════
    // CLEAR
    // ══════════════════════════════════════════════════════════

    function clearForm() {
        [
            'PaymentVoucherNumber',
            'PaymentVoucherDate',
            'BeneficiaryAccountID',
            'BeneficiaryAccountName',
            'PaymentAccountID',
            'PaymentAccountName',
            'Amount',
            'CoinsID',
            'ExchangeRate',
            'PaymentMethod',
            'Notes',
        ].forEach(id => {
            const el = $(id);
            if (!el) return;

            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else {
                el.value = '';
            }
        });

        $('paymentAccountContainer')?.classList.add('d-none');

        const amountWords = $('AmountWords');
        if (amountWords) amountWords.value = '';

        ['SummaryPrevious', 'SummaryPaid', 'SummaryRemain'].forEach(id => {
            const el = $(id);
            if (el) el.textContent = '0.00';
        });

        const coinsSelect = $('CoinsID');
        if (coinsSelect) {
            coinsSelect.disabled = false;
            coinsSelect.classList.remove('bg-body-secondary');
            delete coinsSelect.dataset.locked;
        }

        const paymentAccountID = $('PaymentAccountID');
        if (paymentAccountID) delete paymentAccountID.dataset.currencyLocked;

        lockExchangeRate();

        const numEl = $('PaymentVoucherNumber');
        if (numEl) numEl.dataset.id = '';

        beneficiaryBalanceInfo = null;
        currentBalanceInfo = null;
        originalPaymentAccountID = null;
        originalPaymentLocalAmount = 0;
        originalVoucherData = null;
        currentVoucherIsPosted = false;

        state.setMode('view');
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

        $('PaymentVoucherDate').value =
            new Date().toISOString().slice(0, 10);

        await loadNextVoucherNumber();

        currentVoucherIsPosted = false;

        state.setMode('add');
        updateActionButtons('add', false, false);
        updateDisplay();
        lockExchangeRate();

        toastInfo('يمكنك الآن إدخال بيانات سند الصرف');

        setTimeout(() => $('PaymentMethod')?.focus(), 300);
    };

    // ══════════════════════════════════════════════════════════
    // VALIDATE
    // ══════════════════════════════════════════════════════════

    function validateForm() {
        const errors = [];

        const beneficiaryAccountID = $('BeneficiaryAccountID').value.trim();
        const paymentAccountID     = $('PaymentAccountID').value.trim();
        const amount               = parseFloat($('Amount').value) || 0;
        const coinsID              = $('CoinsID').value.trim();
        const exchangeRate         = parseFloat($('ExchangeRate').value) || 0;
        const paymentMethod        = $('PaymentMethod').value;

        if (!beneficiaryAccountID) errors.push('يرجى اختيار حساب المستفيد.');
        if (!paymentMethod)        errors.push('يرجى اختيار طريقة الصرف.');
        if (!paymentAccountID)     errors.push('يرجى اختيار حساب الصرف.');

        if (beneficiaryAccountID && paymentAccountID && beneficiaryAccountID === paymentAccountID) {
            errors.push('لا يمكن أن يكون حساب المستفيد هو نفسه حساب الصرف.');
        }

        if (amount <= 0 || isNaN(amount)) {
            errors.push('يرجى إدخال مبلغ صحيح أكبر من صفر.');
        }

        if (!coinsID) errors.push('يرجى اختيار العملة.');

        if (exchangeRate <= 0 || isNaN(exchangeRate)) {
            errors.push('سعر الصرف غير صالح. يرجى التحقق من العملة.');
        }

        if (currentBalanceInfo) {
            const localAmount = amount * exchangeRate;
            let available = currentBalanceInfo.balance;

            if (
                state.mode === 'edit' &&
                originalPaymentAccountID &&
                String(originalPaymentAccountID) === String(paymentAccountID)
            ) {
                available += originalPaymentLocalAmount;
            }

            if (localAmount > available) {
                errors.push(
                    'رصيد ' + currentBalanceInfo.name + ' غير كافٍ. ' +
                    'المتاح: ' + available.toFixed(2) +
                    ' | المطلوب: ' + localAmount.toFixed(2)
                );
            }
        }

        return errors.length > 0 ? errors : null;
    }

    // ══════════════════════════════════════════════════════════
    // SAVE
    // ══════════════════════════════════════════════════════════

    window.saveVoucher = async function (opts = {}) {
        if (saveInProgress) return false;

        const now = Date.now();
        if (now - lastSaveTime < MIN_SAVE_INTERVAL) return false;

        const isEdit = state.mode === 'edit';

        if (isEdit && !hasChanges()) {
            toastWarning('لم تُجرِ أي تعديل على السند.');
            return false;
        }

        const errors = validateForm();
        if (errors) {
            toastError(errors[0]);
            return false;
        }

        saveInProgress = true;
        lastSaveTime = now;

        const saveBtn = $('btnSavePaymentVoucher');
        const originalSaveText = saveBtn ? saveBtn.innerHTML : '';

        if (saveBtn) {
            saveBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span> جاري الحفظ...';
        }

        const id = $('PaymentVoucherNumber').dataset.id;

        /*
         * ★ لا نُرسل voucherNumber:
         *   - الخدمة (PaymentVoucherService) تُولّده تلقائياً
         *   - يمنع فشل الـ unique validation عند الحفظ المتكرر
         */
        const payload = {
            beneficiaryAccountID: $('BeneficiaryAccountID').value.trim(),
            paymentAccountID:     $('PaymentAccountID').value.trim(),
            coinsID:              $('CoinsID').value.trim(),
            amount:               parseFloat($('Amount').value) || 0,
            exchangeRate:         parseFloat($('ExchangeRate').value) || 1,
            paymentMethod:        parseInt($('PaymentMethod').value) || null,
            notes:                $('Notes').value.trim() || null,
            voucherDate:          $('PaymentVoucherDate').value || null,
        };

        try {
            const url = isEdit ? `${API}/${id}` : API;
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
                toastError(handleApiError(data));
                return false;
            }

            const inner = unwrap(data);
            const voucher = inner.voucher || {};

            const newId = voucher.paymentID || inner.voucherID || id;
            const newNumber = voucher.voucherNumber || inner.voucherNumber;
            const newIsPosted = !!(inner.isPosted ?? voucher.isPosted);

            toastSuccess(isEdit ? 'تم تعديل السند بنجاح.' : 'تم حفظ السند بنجاح.');

            if (newId) {
                $('PaymentVoucherNumber').dataset.id = newId;
            }
            if (newNumber) {
                $('PaymentVoucherNumber').value = newNumber;
            }

            currentVoucherIsPosted = newIsPosted;

            state.setMode('view');
            updateActionButtons('view', currentVoucherIsPosted, !!newId);
            updateDisplay();

            if (newId && !opts.skipReload) {
                saveInProgress = false;
                await loadVoucher(newId);
                return true;
            }

            updateSummary();
            originalVoucherData = null;

            return true;

        } catch (error) {
            console.error('❌ فشل حفظ السند:', error);
            toastError('تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت ثم أعد المحاولة.');
            return false;

        } finally {
            saveInProgress = false;

            if (state.mode !== 'view') {
                ['btnSavePaymentVoucher', 'btnSaveAndNewPaymentVoucher']
                    .forEach(id => {
                        const btn = $(id);
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

        const saved = await saveVoucher({ skipReload: true });

        if (saved === true) {
            await resetVoucher();
        }
    };

    // ══════════════════════════════════════════════════════════
    // EDIT
    // ══════════════════════════════════════════════════════════

    window.editVoucher = function () {
        if (saveInProgress) return;

        const id = $('PaymentVoucherNumber').dataset.id;

        if (!id) {
            toastWarning('لا يوجد سند محدد للتعديل. اختر سنداً من البحث أولاً.');
            return;
        }

        if (currentVoucherIsPosted) {
            toastError('لا يمكن تعديل سند مُرحّل. يرجى إلغاء الترحيل أولاً.');
            return;
        }

        originalVoucherData = captureVoucherData();
        originalPaymentAccountID = $('PaymentAccountID').value.trim();

        state.setMode('edit');
        updateActionButtons('edit', false, true);

        toastInfo('يمكنك الآن تعديل بيانات سند الصرف');
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
        const voucherId = $('PaymentVoucherNumber').dataset.id;

        if (!voucherId) {
            toastWarning('لا يمكن الطباعة قبل حفظ السند. يرجى حفظ السند أولاً.');
            return;
        }

        const printUrl = `${API}/${voucherId}/print`;

        window.open(printUrl, '_blank', 'width=900,height=700');
    };

    // ══════════════════════════════════════════════════════════
    // POST / UNPOST
    // ══════════════════════════════════════════════════════════

    window.postVoucher = async function () {
        if (saveInProgress) return;

        const id = $('PaymentVoucherNumber').dataset.id;
        if (!id) {
            toastWarning('لا يوجد سند محدد. اختر سنداً من البحث أولاً.');
            return;
        }

        const msg = 'هل تريد ترحيل هذا السند؟\nسيتم إنشاء القيد المحاسبي.';

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm(msg, { title: 'ترحيل السند', okText: 'ترحيل' })
            : confirm(msg);

        if (!ok) return;

        saveInProgress = true;

        try {
            const response = await fetch(`${API}/${id}/post`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            const data = await response.json();

            if (!data.success) {
                toastError(handleApiError(data, 'تعذر ترحيل السند. يرجى المحاولة لاحقاً.'));
                return;
            }

            toastSuccess(data.message || 'تم ترحيل السند بنجاح.');

            saveInProgress = false;
            await loadVoucher(id);

        } catch (error) {
            console.error('❌ فشل ترحيل السند:', error);
            toastError('تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت.');
        } finally {
            saveInProgress = false;
        }
    };

    window.unpostVoucher = async function () {
        if (saveInProgress) return;

        const id = $('PaymentVoucherNumber').dataset.id;
        if (!id) {
            toastWarning('لا يوجد سند محدد. اختر سنداً من البحث أولاً.');
            return;
        }

        const msg = 'هل تريد إلغاء ترحيل هذا السند؟\nسيتم حذف القيد المحاسبي المرتبط.';

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
            const response = await fetch(`${API}/${id}/unpost`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            const data = await response.json();

            if (!data.success) {
                toastError(handleApiError(data, 'تعذر إلغاء ترحيل السند. يرجى المحاولة لاحقاً.'));
                return;
            }

            toastSuccess(data.message || 'تم إلغاء ترحيل السند بنجاح.');

            saveInProgress = false;
            await loadVoucher(id);

        } catch (error) {
            console.error('❌ فشل إلغاء الترحيل:', error);
            toastError('تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت.');
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
        const modalEl = $('PaymentVoucherSearchModal');
        if (!modalEl) return;

        if (!searchModal) {
            searchModal = new bootstrap.Modal(modalEl);
        }

        $('PaymentVoucherSearchInput').value = '';

        searchPagination = { cursor: null, hasMore: false, loading: false };

        $('PaymentVoucherSearchResults').innerHTML =
            '<tr><td colspan="9" class="text-center text-muted py-4">جاري التحميل...</td></tr>';

        $('PaymentVoucherLoadMore')?.classList.add('d-none');

        searchModal.show();

        setTimeout(() => {
            $('PaymentVoucherSearchInput')?.focus();
            performSearch();
        }, 300);
    }

    // ══════════════════════════════════════════════════════════
    // PERFORM SEARCH
    // ══════════════════════════════════════════════════════════

    async function performSearch(loadMore = false) {
        const search = $('PaymentVoucherSearchInput').value.trim();
        const tbody = $('PaymentVoucherSearchResults');

        if (searchPagination.loading) return;

        if (!loadMore) {
            searchPagination = { cursor: null, hasMore: false, loading: false };
            tbody.innerHTML =
                '<tr><td colspan="9" class="text-center text-muted py-4">جاري البحث...</td></tr>';
        }

        searchPagination.loading = true;

        let url = `${API}/list?search=${encodeURIComponent(search)}`;
        if (loadMore && searchPagination.cursor) {
            url += `&cursor=${encodeURIComponent(searchPagination.cursor)}`;
        }

        try {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' },
            });

            const data = await response.json();

            if (!data.success) {
                if (!loadMore) {
                    tbody.innerHTML =
                        '<tr><td colspan="9" class="text-center text-danger py-4">تعذر تحميل النتائج</td></tr>';
                    toastError(handleApiError(data, 'تعذر تحميل قائمة السندات.'));
                }
                return;
            }

            const inner = unwrap(data);
            const rows = pickArray(inner, 'rows', 'data');
            const meta = inner.meta || {};

            if (!loadMore) {
                tbody.innerHTML = '';
            }

            if (rows.length === 0) {
                if (!loadMore) {
                    tbody.innerHTML =
                        '<tr><td colspan="9" class="text-center text-muted py-4">لا توجد سندات مطابقة</td></tr>';
                } else {
                    toastInfo('لا توجد نتائج إضافية.');
                }
                updateLoadMoreButton(false, null);
                return;
            }

            rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';

                const paymentMethodText = {
                    2: 'نقد',
                    3: 'تحويل بنكي',
                }[row.paymentMethod] || row.paymentMethodText || '—';

                const statusBadge = row.isPosted
                    ? '<span class="badge bg-success">مرحّل</span>'
                    : '<span class="badge bg-secondary">مسودة</span>';

                tr.innerHTML = `
                    <td class="text-center fw-bold">
                        ${esc(row.voucherNumber)}
                    </td>

                    <td class="text-center">
                        ${esc(row.voucherDate)}
                    </td>

                    <td>
                        ${esc(row.beneficiaryAccountCode || '')}
                        ${row.beneficiaryAccountCode ? ' - ' : ''}
                        ${esc(row.beneficiaryAccountName || row.beneficiaryName || '')}
                    </td>

                    <td>
                        ${esc(row.paymentAccountCode || '')}
                        ${row.paymentAccountCode ? ' - ' : ''}
                        ${esc(row.paymentAccountName || '')}
                    </td>

                    <td class="text-center text-danger fw-bold">
                        ${esc(parseFloat(row.amount || 0).toFixed(2))}
                    </td>

                    <td class="text-center">
                        ${esc(row.currencyCode || '—')}
                    </td>

                    <td class="text-center">
                        ${esc(paymentMethodText)}
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
                        await quickUnpost(row.paymentID || row.id);
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
                        await quickPost(row.paymentID || row.id);
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
                    await deletePaymentVoucher(
                        row.paymentID || row.id,
                        row.voucherNumber
                    );
                });
                tdActions.appendChild(btnDelete);

                tr.addEventListener('click', () => {
                    loadVoucher(row.paymentID || row.id);
                    searchModal.hide();
                });

                tbody.appendChild(tr);
            });

            const nextCursor = meta.next_cursor || null;
            const hasMore = !!meta.has_more;
            searchPagination.cursor = nextCursor;
            searchPagination.hasMore = hasMore;
            updateLoadMoreButton(hasMore, nextCursor);

        } catch (error) {
            console.error('❌ فشل تحميل نتائج البحث:', error);
            if (!loadMore) {
                tbody.innerHTML =
                    '<tr><td colspan="9" class="text-center text-danger py-4">تعذر الاتصال بالخادم</td></tr>';
                toastError('تعذر تحميل السندات. تحقق من اتصالك بالإنترنت.');
            }
        } finally {
            searchPagination.loading = false;
        }
    }

    function updateLoadMoreButton(show, cursor) {
        let btn = $('PaymentVoucherLoadMore');

        if (!btn) {
            const tbody = $('PaymentVoucherSearchResults');
            if (!tbody) return;

            const wrapper = tbody.closest('table')?.parentElement;
            if (!wrapper) return;

            const container = document.createElement('div');
            container.className = 'text-center my-2';

            btn = document.createElement('button');
            btn.id = 'PaymentVoucherLoadMore';
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary d-none';
            btn.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i> تحميل المزيد';

            btn.addEventListener('click', () => {
                if (!searchPagination.loading && searchPagination.hasMore) {
                    performSearch(true);
                }
            });

            container.appendChild(btn);
            wrapper.appendChild(container);
        }

        btn.classList.toggle('d-none', !show);
        btn.disabled = !cursor;
    }

    // ══════════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════════

    async function deletePaymentVoucher(id, voucherNumber) {
        if (saveInProgress) return;

        const label = voucherNumber ? 'رقم ' + voucherNumber : '#' + id;

        const msg = 'هل أنت متأكد من حذف السند ' + label + '؟\nلا يمكن التراجع عن هذه العملية.';

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm(msg, {
                title: 'حذف سند صرف',
                okText: 'حذف نهائي',
                danger: true,
            })
            : confirm(msg);

        if (!ok) return;

        saveInProgress = true;

        try {
            const response = await fetch(`${API}/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            const data = await response.json();

            if (!data.success) {
                toastError(handleApiError(data, 'تعذر حذف السند. يرجى المحاولة لاحقاً.'));
                return;
            }

            toastSuccess('تم حذف السند بنجاح.');

            const currentID = $('PaymentVoucherNumber').dataset.id;

            if (currentID && String(currentID) === String(id)) {
                clearForm();
                loadNextVoucherNumber();
            }

            performSearch();

        } catch (error) {
            console.error('❌ فشل حذف السند:', error);
            toastError('تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت.');
        } finally {
            saveInProgress = false;
        }
    }

    // ══════════════════════════════════════════════════════════
    // QUICK POST / UNPOST
    // ══════════════════════════════════════════════════════════

    async function quickPost(id) {
        if (saveInProgress) return;

        const msg = 'هل تريد ترحيل هذا السند؟';

        const ok = typeof window.appConfirm === 'function'
            ? await window.appConfirm(msg, { title: 'ترحيل', okText: 'ترحيل' })
            : confirm(msg);

        if (!ok) return;

        saveInProgress = true;

        try {
            const r = await fetch(`${API}/${id}/post`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const d = await r.json();

            if (!d.success) {
                toastError(handleApiError(d, 'تعذر ترحيل السند.'));
                return;
            }
            toastSuccess(d.message || 'تم ترحيل السند بنجاح.');

            const currentID = $('PaymentVoucherNumber').dataset.id;
            if (currentID && String(currentID) === String(id)) {
                saveInProgress = false;
                await loadVoucher(id);
                performSearch();
                return;
            }

            performSearch();
        } catch (e) {
            console.error('❌ فشل ترحيل السند:', e);
            toastError('تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت.');
        } finally {
            saveInProgress = false;
        }
    }

    async function quickUnpost(id) {
        if (saveInProgress) return;

        const msg = 'هل تريد إلغاء ترحيل هذا السند؟';

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
            const r = await fetch(`${API}/${id}/unpost`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const d = await r.json();

            if (!d.success) {
                toastError(handleApiError(d, 'تعذر إلغاء ترحيل السند.'));
                return;
            }
            toastSuccess(d.message || 'تم إلغاء الترحيل بنجاح.');

            const currentID = $('PaymentVoucherNumber').dataset.id;
            if (currentID && String(currentID) === String(id)) {
                saveInProgress = false;
                await loadVoucher(id);
                performSearch();
                return;
            }

            performSearch();
        } catch (e) {
            console.error('❌ فشل إلغاء الترحيل:', e);
            toastError('تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت.');
        } finally {
            saveInProgress = false;
        }
    }

    // ══════════════════════════════════════════════════════════
    // LOAD VOUCHER
    // ══════════════════════════════════════════════════════════

    async function loadVoucher(id) {
        if (saveInProgress) return;

        try {
            const response = await fetch(`${API}/${id}`, {
                headers: { 'Accept': 'application/json' },
            });

            const data = await response.json();

            if (!data.success) {
                toastError(handleApiError(data, 'تعذر تحميل السند.'));
                return;
            }

            const inner = unwrap(data);
            const v = inner.voucher;
            const balance = inner.balance || {};

            if (!v) {
                toastError('لم يتم العثور على بيانات السند.');
                return;
            }

            currentVoucherIsPosted = !!v.isPosted;

            $('PaymentVoucherNumber').value = v.voucherNumber;
            $('PaymentVoucherNumber').dataset.id = v.paymentID || v.id;
            $('PaymentVoucherDate').value = v.voucherDate;

            $('BeneficiaryAccountID').value = v.beneficiaryAccountID;
            $('BeneficiaryAccountName').value =
                (v.beneficiaryAccountCode || '') +
                ' - ' +
                (v.beneficiaryAccountName || v.beneficiaryName || '');

            beneficiaryBalanceInfo = {
                id: v.beneficiaryAccountID,
                name: v.beneficiaryAccountName || v.beneficiaryName,
                balance: parseFloat(balance.beneficiary?.before) || 0,
                nature: parseInt(
                    balance.beneficiary?.nature ?? v.beneficiaryNature ?? 0
                ),
            };

            $('PaymentMethod').value =
                v.paymentMethod ? String(v.paymentMethod) : '';

            if (v.paymentMethod) {
                $('PaymentMethod').dispatchEvent(new Event('change'));
            }

            $('PaymentAccountID').value = v.paymentAccountID;
            $('PaymentAccountName').value =
                (v.paymentAccountCode || '') +
                ' - ' +
                (v.paymentAccountName || '');

            currentBalanceInfo = {
                id: v.paymentAccountID,
                name: v.paymentAccountName,
                balance: parseFloat(balance.payment?.before) || 0,
            };

            originalPaymentAccountID = v.paymentAccountID;
            originalPaymentLocalAmount = parseFloat(v.localAmount) || 0;

            $('Amount').value = v.amount;
            $('CoinsID').value = v.currencyID || v.coinsID;
            $('ExchangeRate').value = v.exchangeRate;
            $('Notes').value = v.notes || '';

            const coinsSelect = $('CoinsID');
            if (coinsSelect) {
                coinsSelect.disabled = true;
                coinsSelect.classList.add('bg-body-secondary');
            }

            lockExchangeRate();

            updateSummary();
            updateDisplay();

            state.setMode('view');
            updateActionButtons('view', currentVoucherIsPosted, true);

            toastSuccess('تم تحميل السند');

        } catch (error) {
            console.error('❌ فشل تحميل السند:', error);
            toastError('تعذر تحميل السند. تحقق من اتصالك بالإنترنت.');
        }
    }

    // ══════════════════════════════════════════════════════════
    // EVENTS
    // ══════════════════════════════════════════════════════════

    $('Amount')?.addEventListener('input', updateSummary);

    $('PaymentVoucherSearchInput')?.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => performSearch(false), 300);
    });

    $('PaymentVoucherClearBtn')?.addEventListener('click', function () {
        $('PaymentVoucherSearchInput').value = '';
        $('PaymentVoucherSearchInput').focus();
        performSearch(false);
    });

    $('PaymentVoucherSearchInput')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch(false);
        }
    });

    $('btnNewPaymentVoucher')?.addEventListener('click', resetVoucher);
    $('btnSavePaymentVoucher')?.addEventListener('click', saveVoucher);
    $('btnSaveAndNewPaymentVoucher')?.addEventListener('click', saveAndNewVoucher);
    $('btnEditPaymentVoucher')?.addEventListener('click', editVoucher);
    $('btnCancelPaymentVoucher')?.addEventListener('click', cancelVoucher);
    $('btnPrintPaymentVoucher')?.addEventListener('click', printVoucher);
    $('btnSearchPaymentVoucher')?.addEventListener('click', searchVoucher);
    $('btnPostPaymentVoucher')?.addEventListener('click', postVoucher);
    $('btnUnpostPaymentVoucher')?.addEventListener('click', unpostVoucher);

    // ══════════════════════════════════════════════════════════
    // BEFOREUNLOAD
    // ══════════════════════════════════════════════════════════

    window.addEventListener('beforeunload', function (e) {
        if (state.mode === 'view') return;

        if (state.mode === 'add' && !originalVoucherData && !hasAnyInput()) {
            return;
        }

        if (!hasChanges()) return;

        e.preventDefault();
        e.returnValue = '';
    });

    // ══════════════════════════════════════════════════════════
    // INITIALIZE
    // ══════════════════════════════════════════════════════════

    clearForm();
    loadNextVoucherNumber();

    console.log('✅ تم تهيئة صفحة سند الصرف بنجاح');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPaymentVoucherPage);
} else {
    initPaymentVoucherPage();
}