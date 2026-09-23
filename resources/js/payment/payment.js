/**
 * ═══════════════════════════════════════════════════════════
 *  صفحة سند الصرف - بنفس نمط سند القبض
 *  (الحساب الدائن = المورد + الحساب المدين = الصندوق/البنك)
 * ═══════════════════════════════════════════════════════════
 */

import '../receipt/accountPicker.js';

// ⭐ تعيين مسار الـ picker الخاص بسندات الصرف
if (window.AccountPicker && typeof window.AccountPicker.setUrl === 'function') {
    window.AccountPicker.setUrl('/operation/accounting/paymentVouchers/picker');
}

document.addEventListener('DOMContentLoaded', function () {
    console.log('🚀 بدء تهيئة صفحة سند الصرف');

    // ══════════════════════════════════════════════════════════
    //  دالة الرسائل
    // ══════════════════════════════════════════════════════════
    function toast(message, type = 'info') {
        if (typeof showSystemToast === 'function') {
            showSystemToast(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    }

    // ⭐ دوال مساعدة
    function toastInfo(message)    { toast(message, 'info');    }
    function toastSuccess(message) { toast(message, 'success'); }
    function toastWarning(message) { toast(message, 'warning'); }
    function toastError(message)   { toast(message, 'danger');  }

    Modals.initAll();

    const state = new StateManager('payment-voucher');

    let saveInProgress = false;
    let lastSaveTime = 0;
    const MIN_SAVE_INTERVAL = 1500;

    state.setFields([
        'PaymentVoucherNumber', 'PaymentVoucherDate',
        'CreditAccountName', 'DebitAccountName',
        'Amount', 'CoinsID', 'ExchangeRate',
        'PaymentMethod', 'Notes',
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
        console.log('🔄 تغيير الوضع إلى:', mode);
        toggleFormDisabled(mode === 'view');
        updateDisplay();
    });

    // ══════════════════════════════════════════════════════════
    //  تحميل العملات
    // ══════════════════════════════════════════════════════════

    async function loadCurrencies() {
        try {
            const response = await fetch('/operation/accounting/paymentVouchers/currencies', {
                headers: { 'Accept': 'application/json' },
            });
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
            const response = await fetch('/operation/accounting/paymentVouchers/next-number', {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (data.success && data.nextNumber) {
                document.getElementById('PaymentVoucherNumber').value = data.nextNumber;
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
        const rate = parseFloat(selectedOption.dataset.rate) || 1;
        const exchangeRateEl = document.getElementById('ExchangeRate');
        if (exchangeRateEl) exchangeRateEl.value = rate;
        updateSummary();
    });

    loadCurrencies();

    // ══════════════════════════════════════════════════════════
    //  حقول الحسابات
    //  ⭐ الحساب الدائن = المورد
    //  ⭐ الحساب المدين = الصندوق/البنك
    // ══════════════════════════════════════════════════════════

    document.getElementById('CreditAccountName')?.addEventListener('click', function () {
        if (state.mode === 'view' || saveInProgress) return;
        if (typeof window.AccountPicker === 'undefined') {
            toastError('مودال اختيار الحسابات غير محمّل');
            return;
        }

        window.AccountPicker.open('supplier', function (account) {
            document.getElementById('CreditAccountID').value   = account.id;
            document.getElementById('CreditAccountName').value = account.code + ' - ' + account.name;
        });
    });

    document.getElementById('DebitAccountName')?.addEventListener('click', function () {
        if (state.mode === 'view' || saveInProgress) return;
        if (typeof window.AccountPicker === 'undefined') {
            toastError('مودال اختيار الحسابات غير محمّل');
            return;
        }

        const paymentMethod = document.getElementById('PaymentMethod').value || 'cash';
        const type = paymentMethod === 'bank' ? 'bank' : 'cash';

        window.AccountPicker.open(type, function (account) {
            document.getElementById('DebitAccountID').value   = account.id;
            document.getElementById('DebitAccountName').value = account.code + ' - ' + account.name;
        });
    });

    // ══════════════════════════════════════════════════════════
    //  طريقة الدفع - تُظهر/تُخفي حقل الحساب المدين
    // ══════════════════════════════════════════════════════════

    document.getElementById('PaymentMethod')?.addEventListener('change', function () {
        const method = this.value;
        const debitContainer = document.getElementById('debitAccountContainer');
        const debitLabel     = document.getElementById('debitAccountLabel');

        if (!debitContainer) return;

        if (method === 'cash' || method === 'bank') {
            debitContainer.classList.remove('d-none');
            if (debitLabel) {
                debitLabel.textContent = method === 'cash' ? 'حساب الصندوق' : 'حساب البنك';
            }
        }
        else {
            debitContainer.classList.add('d-none');
            const debitID   = document.getElementById('DebitAccountID');
            const debitName = document.getElementById('DebitAccountName');
            if (debitID)   debitID.value = '';
            if (debitName) debitName.value = '';
        }
    });

    // ══════════════════════════════════════════════════════════
    //  دوال الصفحة
    // ══════════════════════════════════════════════════════════

    function toggleFormDisabled(disabled) {
        const fields = [
            'CreditAccountName', 'DebitAccountName', 'Amount',
            'CoinsID', 'ExchangeRate', 'PaymentMethod', 'Notes',
        ];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = disabled;
        });
    }

    function setButtonsDisabled(disabled) {
        const buttons = [
            'btnNewPaymentVoucher', 'btnSavePaymentVoucher',
            'btnSaveAndNewPaymentVoucher', 'btnEditPaymentVoucher',
            'btnCancelPaymentVoucher', 'btnPrintPaymentVoucher',
            'btnSearchPaymentVoucher',
        ];
        buttons.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.disabled = disabled;
        });
    }

    window.updateSummary = function () {
        const amount = parseFloat(document.getElementById('Amount')?.value) || 0;
        const rate   = parseFloat(document.getElementById('ExchangeRate')?.value) || 1;
        const paid   = amount * rate;

        const coinsSelect = document.getElementById('CoinsID');
        const selectedOption = coinsSelect?.options[coinsSelect.selectedIndex];
        const currencyName = selectedOption?.textContent?.split(' - ')[1]?.trim() || '';

        const amountWordsEl = document.getElementById('AmountWords');
        if (amountWordsEl) {
            if (amount <= 0 || isNaN(amount)) amountWordsEl.value = '';
            else amountWordsEl.value = Utils.numberToWords(amount, currencyName);
        }

        const summaryPaid     = document.getElementById('SummaryPaid');
        const summaryPrevious = document.getElementById('SummaryPrevious');
        const summaryRemain   = document.getElementById('SummaryRemain');

        if (summaryPaid)     summaryPaid.textContent     = paid.toFixed(2);
        if (summaryPrevious) summaryPrevious.textContent = '0.00';
        if (summaryRemain)   summaryRemain.textContent   = (0 - paid).toFixed(2);
    };

    function updateDisplay() {
        const num  = document.getElementById('PaymentVoucherNumber')?.value || '';
        const date = document.getElementById('PaymentVoucherDate')?.value || '';

        const displayNum  = document.getElementById('PaymentVoucherNumberDisplay');
        const displayDate = document.getElementById('PaymentVoucherDateDisplay');

        if (displayNum) displayNum.textContent = num ? 'رقم السند: ' + num : 'رقم السند: --';
        if (displayDate) {
            displayDate.innerHTML = date
                ? '<i class="bi bi-calendar3 me-1"></i> ' + date
                : '<i class="bi bi-calendar3 me-1"></i> --';
        }
    }

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
            voucherDate:     document.getElementById('PaymentVoucherDate').value || '',
        };
    }

    function hasChanges() {
        if (!originalVoucherData) return true;
        return JSON.stringify(captureVoucherData()) !== JSON.stringify(originalVoucherData);
    }

    function clearForm() {
        const fields = [
            'PaymentVoucherNumber', 'PaymentVoucherDate',
            'CreditAccountID', 'CreditAccountName',
            'DebitAccountID', 'DebitAccountName',
            'Amount', 'CoinsID', 'ExchangeRate',
            'PaymentMethod', 'Notes',
        ];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = '';
            }
        });

        document.getElementById('debitAccountContainer')?.classList.add('d-none');

        document.getElementById('AmountWords').value           = '';
        document.getElementById('SummaryPrevious').textContent = '0.00';
        document.getElementById('SummaryPaid').textContent     = '0.00';
        document.getElementById('SummaryRemain').textContent   = '0.00';

        const numEl = document.getElementById('PaymentVoucherNumber');
        if (numEl) numEl.dataset.id = '';

        originalVoucherData = null;
        state.setMode('view');
        updateDisplay();
    }

    window.resetVoucher = async function () {
        if (saveInProgress) return;

        clearForm();
        document.getElementById('PaymentVoucherDate').value = new Date().toISOString().slice(0, 10);

        await loadNextVoucherNumber();

        state.setMode('add');
        updateDisplay();
        toastInfo('يمكنك الآن إدخال بيانات السند الجديد');

        setTimeout(() => document.getElementById('PaymentMethod')?.focus(), 300);
    };

    function validateForm() {
        const errors = [];
        const creditAccountID = document.getElementById('CreditAccountID').value.trim();
        const debitAccountID  = document.getElementById('DebitAccountID').value.trim();
        const amount          = parseFloat(document.getElementById('Amount').value) || 0;
        const coinsID         = document.getElementById('CoinsID').value.trim();
        const exchangeRate    = parseFloat(document.getElementById('ExchangeRate').value) || 0;
        const paymentMethod   = document.getElementById('PaymentMethod').value;

        if (!creditAccountID) errors.push('يرجى اختيار الحساب الدائن (المورد).');
        if (!paymentMethod)   errors.push('يرجى اختيار طريقة الدفع.');
        if (!debitAccountID)  errors.push('يرجى اختيار الحساب المدين.');
        if (creditAccountID && debitAccountID && creditAccountID === debitAccountID) {
            errors.push('لا يمكن أن يكون الحساب المدين هو نفس الحساب الدائن.');
        }
        if (amount <= 0 || isNaN(amount)) errors.push('المبلغ يجب أن يكون أكبر من صفر.');
        if (!coinsID) errors.push('يرجى اختيار العملة.');
        if (exchangeRate <= 0 || isNaN(exchangeRate)) errors.push('سعر الصرف يجب أن يكون أكبر من صفر.');

        return errors.length > 0 ? errors : null;
    }

    window.saveVoucher = async function () {
        if (saveInProgress) return;

        const now = Date.now();
        if (now - lastSaveTime < MIN_SAVE_INTERVAL) return;

        const isEdit = state.mode === 'edit';

        if (isEdit && !hasChanges()) {
            toastError('لم تُجرِ أي تعديل على السند.');
            return;
        }

        const errors = validateForm();
        if (errors) { toastError(errors[0]); return; }

        saveInProgress = true;
        lastSaveTime = now;
        setButtonsDisabled(true);

        const saveBtn = document.getElementById('btnSavePaymentVoucher');
        const originalSaveText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري الحفظ...';

        const id = document.getElementById('PaymentVoucherNumber').dataset.id;

        const payload = {
            voucherNumber:   document.getElementById('PaymentVoucherNumber').value.trim() || null,
            creditAccountID: document.getElementById('CreditAccountID').value.trim(),
            debitAccountID:  document.getElementById('DebitAccountID').value.trim(),
            coinsID:         document.getElementById('CoinsID').value.trim(),
            amount:          parseFloat(document.getElementById('Amount').value) || 0,
            exchangeRate:    parseFloat(document.getElementById('ExchangeRate').value) || 1,
            paymentMethod:   document.getElementById('PaymentMethod').value || null,
            notes:           document.getElementById('Notes').value.trim() || null,
            voucherDate:     document.getElementById('PaymentVoucherDate').value || null,
        };

        try {
            const url    = isEdit
                ? `/operation/accounting/paymentVouchers/${id}`
                : `/operation/accounting/paymentVouchers`;
            const method = isEdit ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept':       'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!data.success) {
                toastError(data.message || 'حدث خطأ غير متوقع.');
                return;
            }

            toastSuccess(isEdit ? 'تم تعديل السند بنجاح.' : 'تم حفظ السند بنجاح.');

            if (data.voucherID) {
                document.getElementById('PaymentVoucherNumber').dataset.id = data.voucherID;
            }
            if (data.voucherNumber) {
                document.getElementById('PaymentVoucherNumber').value = data.voucherNumber;
            }

            state.setMode('view');
            updateDisplay();
            originalVoucherData = null;

        } catch (error) {
            console.error('❌ خطأ في الحفظ:', error);
            toastError('فشل الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
        } finally {
            saveInProgress = false;
            setButtonsDisabled(false);
            saveBtn.innerHTML = originalSaveText;
        }
    };

    window.saveAndNewVoucher = async function () {
        if (saveInProgress) return;
        await saveVoucher();
        await resetVoucher();
    };

    window.editVoucher = function () {
        if (saveInProgress) return;

        const id = document.getElementById('PaymentVoucherNumber').dataset.id;
        if (!id) {
            toastError('لا يوجد سند محدد للتعديل.');
            return;
        }

        originalVoucherData = captureVoucherData();
        state.setMode('edit');
        toastInfo('يمكنك الآن تعديل بيانات السند');
    };

    window.cancelVoucher = function () {
        if (saveInProgress) return;
        if (!confirm('هل أنت متأكد من إلغاء العملية؟')) return;
        clearForm();
        loadNextVoucherNumber();
        toastInfo('تم إلغاء العملية');
    };

    window.printVoucher = function () {
        const voucherId = document.getElementById('PaymentVoucherNumber').dataset.id;

        if (!voucherId) {
            toastError('لا يمكن الطباعة لأنه لا يوجد سند محفوظ. يرجى حفظ السند أولاً.');
            return;
        }

        const printUrl = `/operation/accounting/paymentVouchers/${voucherId}/print`;
        window.open(printUrl, '_blank', 'width=900,height=700');
    };

    window.searchVoucher = function () {
        if (saveInProgress) return;
        openSearchModal();
    };

    // ══════════════════════════════════════════════════════════
    //  مودال البحث
    // ══════════════════════════════════════════════════════════

    let searchModal   = null;
    let searchTimeout = null;  // ⭐ تم التصريح عنه

    function openSearchModal() {
        const modalEl = document.getElementById('PaymentVoucherSearchModal');
        if (!searchModal) searchModal = new bootstrap.Modal(modalEl);

        document.getElementById('PaymentVoucherSearchInput').value = '';
        document.getElementById('PaymentVoucherSearchResults').innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">جاري التحميل...</td></tr>';

        searchModal.show();
        setTimeout(() => {
            document.getElementById('PaymentVoucherSearchInput')?.focus();
            performSearch();
        }, 300);
    }

    async function performSearch() {
        const search = document.getElementById('PaymentVoucherSearchInput').value.trim();
        const tbody  = document.getElementById('PaymentVoucherSearchResults');

        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">جاري البحث...</td></tr>';

        try {
            const response = await fetch(
                `/operation/accounting/paymentVouchers/list?search=${encodeURIComponent(search)}`,
                { headers: { 'Accept': 'application/json' } }
            );
            const data = await response.json();
            tbody.innerHTML = '';

            if (!data.success || !data.rows || data.rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">لا توجد نتائج</td></tr>';
                return;
            }

            data.rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';

                const paymentMethodText = {
                    cash: 'نقد',
                    bank: 'تحويل بنكي',
                }[row.paymentMethod] || row.paymentMethod || '';

                tr.innerHTML = `
                    <td class="text-center fw-bold">${row.voucherNumber}</td>
                    <td class="text-center">${row.voucherDate}</td>
                    <td>${row.creditAccountCode} - ${row.creditAccountName}</td>
                    <td>${row.debitAccountCode} - ${row.debitAccountName}</td>
                    <td class="text-center text-danger fw-bold">${parseFloat(row.amount).toFixed(2)}</td>
                    <td class="text-center">${row.currencyCode || '—'}</td>
                    <td class="text-center">${paymentMethodText}</td>
                `;

                tr.addEventListener('click', () => {
                    loadVoucher(row.id);
                    searchModal.hide();
                });

                tbody.appendChild(tr);
            });
        } catch (error) {
            console.error('❌ خطأ في البحث:', error);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">فشل الاتصال بالخادم</td></tr>';
        }
    }

    async function loadVoucher(id) {
        if (saveInProgress) return;

        try {
            const response = await fetch(`/operation/accounting/paymentVouchers/${id}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (!data.success || !data.voucher) { toastError('فشل تحميل السند.'); return; }

            const v = data.voucher;

            document.getElementById('PaymentVoucherNumber').value = v.voucherNumber;
            document.getElementById('PaymentVoucherNumber').dataset.id = v.id;
            document.getElementById('PaymentVoucherDate').value = v.voucherDate;

            document.getElementById('CreditAccountID').value   = v.creditAccountID;
            document.getElementById('CreditAccountName').value = v.creditAccountCode + ' - ' + v.creditAccountName;

            document.getElementById('PaymentMethod').value = v.paymentMethod || '';
            if (v.paymentMethod) {
                document.getElementById('PaymentMethod').dispatchEvent(new Event('change'));
            }

            document.getElementById('DebitAccountID').value   = v.debitAccountID;
            document.getElementById('DebitAccountName').value = v.debitAccountCode + ' - ' + v.debitAccountName;

            document.getElementById('Amount').value       = v.amount;
            document.getElementById('CoinsID').value      = v.currencyID;
            document.getElementById('ExchangeRate').value = v.exchangeRate;
            document.getElementById('Notes').value        = v.notes || '';

            updateSummary();
            updateDisplay();
            state.setMode('view');
            toastSuccess('تم تحميل السند');
        } catch (error) {
            console.error('❌ خطأ:', error);
            toastError('فشل تحميل السند.');
        }
    }

    // ══════════════════════════════════════════════════════════
    //  ربط الأحداث
    // ══════════════════════════════════════════════════════════

    document.getElementById('Amount')?.addEventListener('input', updateSummary);
    document.getElementById('ExchangeRate')?.addEventListener('input', updateSummary);

    document.getElementById('PaymentVoucherSearchInput')?.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });

    document.getElementById('PaymentVoucherClearBtn')?.addEventListener('click', function () {
        document.getElementById('PaymentVoucherSearchInput').value = '';
        document.getElementById('PaymentVoucherSearchInput').focus();
        performSearch();
    });

    document.getElementById('PaymentVoucherSearchInput')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); performSearch(); }
    });

    document.getElementById('btnNewPaymentVoucher')?.addEventListener('click', resetVoucher);
    document.getElementById('btnSavePaymentVoucher')?.addEventListener('click', saveVoucher);
    document.getElementById('btnSaveAndNewPaymentVoucher')?.addEventListener('click', saveAndNewVoucher);
    document.getElementById('btnEditPaymentVoucher')?.addEventListener('click', editVoucher);
    document.getElementById('btnCancelPaymentVoucher')?.addEventListener('click', cancelVoucher);
    document.getElementById('btnPrintPaymentVoucher')?.addEventListener('click', printVoucher);
    document.getElementById('btnSearchPaymentVoucher')?.addEventListener('click', searchVoucher);

    clearForm();
    loadNextVoucherNumber();

    console.log('✅ تم تهيئة صفحة سند الصرف بنجاح');
});