/**
 * ═══════════════════════════════════════════════════════════
 *  صفحة سند القبض - النسخة النهائية
 *  + رسائل التحذير باللون الأحمر
 *  + رسائل المعلومات تبقى كما هي
 * ═══════════════════════════════════════════════════════════
 */

import '../receipt/accountPicker.js';
    

document.addEventListener('DOMContentLoaded', function () {
    console.log('🚀 بدء تهيئة صفحة سند القبض');

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
    function toastError(message)   { toast(message, 'danger');  } // ⭐ أحمر

    Modals.initAll();

    const state = new StateManager('receipt-voucher');

    let saveInProgress = false;
    let lastSaveTime = 0;
    const MIN_SAVE_INTERVAL = 1500;

    state.setFields([
        'ReceiptVoucherNumber', 'ReceiptVoucherDate',
        'CreditAccountName', 'DebitAccountName',
        'Amount', 'CoinsID', 'ExchangeRate',
        'PaymentMethod', 'Notes',
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
        console.log('🔄 تغيير الوضع إلى:', mode);
        toggleFormDisabled(mode === 'view');
        updateDisplay();
    });

    // ══════════════════════════════════════════════════════════
    //  تحميل العملات
    // ══════════════════════════════════════════════════════════

    async function loadCurrencies() {
        try {
            const response = await fetch('/operation/accounting/receiptVouchers/currencies', {
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
            const response = await fetch('/operation/accounting/receiptVouchers/next-number', {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (data.success && data.nextNumber) {
                document.getElementById('ReceiptVoucherNumber').value = data.nextNumber;
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
    // ══════════════════════════════════════════════════════════

    document.getElementById('CreditAccountName')?.addEventListener('click', function () {
        if (state.mode === 'view' || saveInProgress) return;
        if (typeof window.AccountPicker === 'undefined') {
            toastError('مودال اختيار الحسابات غير محمّل'); // ⭐ أحمر
            return;
        }

        window.AccountPicker.open('customer', function (account) {
            document.getElementById('CreditAccountID').value   = account.id;
            document.getElementById('CreditAccountName').value = account.code + ' - ' + account.name;
        });
    });

    document.getElementById('DebitAccountName')?.addEventListener('click', function () {
        if (state.mode === 'view' || saveInProgress) return;
        if (typeof window.AccountPicker === 'undefined') {
            toastError('مودال اختيار الحسابات غير محمّل'); // ⭐ أحمر
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
    //  طريقة الدفع
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
            'btnNewReceiptVoucher', 'btnSaveReceiptVoucher',
            'btnSaveAndNewReceiptVoucher', 'btnEditReceiptVoucher',
            'btnCancelReceiptVoucher', 'btnPrintReceiptVoucher',
            'btnSearchReceiptVoucher',
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
        const num  = document.getElementById('ReceiptVoucherNumber')?.value || '';
        const date = document.getElementById('ReceiptVoucherDate')?.value || '';

        const displayNum  = document.getElementById('ReceiptVoucherNumberDisplay');
        const displayDate = document.getElementById('ReceiptVoucherDateDisplay');

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
            voucherDate:     document.getElementById('ReceiptVoucherDate').value || '',
        };
    }

    function hasChanges() {
        if (!originalVoucherData) return true;
        return JSON.stringify(captureVoucherData()) !== JSON.stringify(originalVoucherData);
    }

    function clearForm() {
        const fields = [
            'ReceiptVoucherNumber', 'ReceiptVoucherDate',
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

        const numEl = document.getElementById('ReceiptVoucherNumber');
        if (numEl) numEl.dataset.id = '';

        originalVoucherData = null;
        state.setMode('view');
        updateDisplay();
    }

    // ⭐⭐ إضافة سند جديد
    window.resetVoucher = async function () {
        if (saveInProgress) return;

        clearForm();
        document.getElementById('ReceiptVoucherDate').value = new Date().toISOString().slice(0, 10);

        await loadNextVoucherNumber();

        state.setMode('add');
        updateDisplay();
        toastInfo('يمكنك الآن إدخال بيانات السند الجديد'); // ⭐ يبقى info

        setTimeout(() => document.getElementById('CreditAccountName')?.focus(), 300);
    };

    function validateForm() {
        const errors = [];
        const creditAccountID = document.getElementById('CreditAccountID').value.trim();
        const debitAccountID  = document.getElementById('DebitAccountID').value.trim();
        const amount          = parseFloat(document.getElementById('Amount').value) || 0;
        const coinsID         = document.getElementById('CoinsID').value.trim();
        const exchangeRate    = parseFloat(document.getElementById('ExchangeRate').value) || 0;
        const paymentMethod   = document.getElementById('PaymentMethod').value;

        if (!creditAccountID) errors.push('يرجى اختيار الحساب الدائن (العميل).');
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

    // ══════════════════════════════════════════════════════════
    //  الحفظ
    // ══════════════════════════════════════════════════════════

    window.saveVoucher = async function () {
        if (saveInProgress) return;

        const now = Date.now();
        if (now - lastSaveTime < MIN_SAVE_INTERVAL) return;

        const isEdit = state.mode === 'edit';

        if (isEdit && !hasChanges()) {
            toastError('لم تُجرِ أي تعديل على السند.'); // ⭐ أحمر
            return;
        }

        const errors = validateForm();
        if (errors) { toastError(errors[0]); return; } // ⭐ أحمر

        saveInProgress = true;
        lastSaveTime = now;
        setButtonsDisabled(true);

        const saveBtn = document.getElementById('btnSaveReceiptVoucher');
        const originalSaveText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري الحفظ...';

        const id = document.getElementById('ReceiptVoucherNumber').dataset.id;

        const payload = {
            voucherNumber:   document.getElementById('ReceiptVoucherNumber').value.trim() || null,
            creditAccountID: document.getElementById('CreditAccountID').value.trim(),
            debitAccountID:  document.getElementById('DebitAccountID').value.trim(),
            coinsID:         document.getElementById('CoinsID').value.trim(),
            amount:          parseFloat(document.getElementById('Amount').value) || 0,
            exchangeRate:    parseFloat(document.getElementById('ExchangeRate').value) || 1,
            paymentMethod:   document.getElementById('PaymentMethod').value || null,
            notes:           document.getElementById('Notes').value.trim() || null,
            voucherDate:     document.getElementById('ReceiptVoucherDate').value || null,
        };

        try {
            const url    = isEdit
                ? `/operation/accounting/receiptVouchers/${id}`
                : `/operation/accounting/receiptVouchers`;
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
                toastError(data.message || 'حدث خطأ غير متوقع.'); // ⭐ أحمر
                return;
            }

            toastSuccess(isEdit ? 'تم تعديل السند بنجاح.' : 'تم حفظ السند بنجاح.'); // ⭐ أخضر

            // ⭐ لا نفرّغ الشاشة - نُبقي البيانات للطباعة
            if (data.voucherID) {
                document.getElementById('ReceiptVoucherNumber').dataset.id = data.voucherID;
            }
            if (data.voucherNumber) {
                document.getElementById('ReceiptVoucherNumber').value = data.voucherNumber;
            }

            state.setMode('view');
            updateDisplay();
            originalVoucherData = null;

        } catch (error) {
            console.error('❌ خطأ في الحفظ:', error);
            toastError('فشل الاتصال بالخادم. يرجى المحاولة مرة أخرى.'); // ⭐ أحمر
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

        const id = document.getElementById('ReceiptVoucherNumber').dataset.id;
        if (!id) {
            toastError('لا يوجد سند محدد للتعديل.'); // ⭐ أحمر
            return;
        }

        originalVoucherData = captureVoucherData();
        state.setMode('edit');
        toastInfo('يمكنك الآن تعديل بيانات السند'); // ⭐ info
    };

    window.cancelVoucher = function () {
        if (saveInProgress) return;
        if (!confirm('هل أنت متأكد من إلغاء العملية؟')) return;
        clearForm();
        loadNextVoucherNumber();
        toastInfo('تم إلغاء العملية'); // ⭐ info
    };

     /**
     * ⭐ طباعة سند القبض (فتح صفحة الطباعة من السيرفر)
     */
    window.printVoucher = function () {
        const voucherId = document.getElementById('ReceiptVoucherNumber').dataset.id;

        if (!voucherId) {
            toastError('لا يمكن الطباعة لأنه لا يوجد سند محفوظ. يرجى حفظ السند أولاً.');
            return;
        }

        // ⭐ فتح صفحة الطباعة في نافذة جديدة
        const printUrl = `/operation/accounting/receiptVouchers/${voucherId}/print`;
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
    let searchTimeout = null;

    function openSearchModal() {
        const modalEl = document.getElementById('ReceiptVoucherSearchModal');
        if (!searchModal) searchModal = new bootstrap.Modal(modalEl);

        document.getElementById('ReceiptVoucherSearchInput').value = '';
        document.getElementById('ReceiptVoucherSearchResults').innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">ابحث عن سند قبض</td></tr>';

        searchModal.show();
        setTimeout(() => {
            document.getElementById('ReceiptVoucherSearchInput')?.focus();
            performSearch();
        }, 300);
    }

    async function performSearch() {
        const search = document.getElementById('ReceiptVoucherSearchInput').value.trim();
        const tbody  = document.getElementById('ReceiptVoucherSearchResults');

        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">جاري البحث...</td></tr>';

        try {
            const response = await fetch(
                `/operation/accounting/receiptVouchers/list?search=${encodeURIComponent(search)}`,
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
                    <td class="text-center text-success fw-bold">${parseFloat(row.amount).toFixed(2)}</td>
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
            const response = await fetch(`/operation/accounting/receiptVouchers/${id}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (!data.success || !data.voucher) { toastError('فشل تحميل السند.'); return; } // ⭐ أحمر

            const v = data.voucher;

            document.getElementById('ReceiptVoucherNumber').value = v.voucherNumber;
            document.getElementById('ReceiptVoucherNumber').dataset.id = v.id;
            document.getElementById('ReceiptVoucherDate').value = v.voucherDate;

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
            toastSuccess('تم تحميل السند'); // ⭐ أخضر
        } catch (error) {
            console.error('❌ خطأ:', error);
            toastError('فشل تحميل السند.'); // ⭐ أحمر
        }
    }

    // ══════════════════════════════════════════════════════════
    //  ربط الأحداث
    // ══════════════════════════════════════════════════════════

    document.getElementById('Amount')?.addEventListener('input', updateSummary);
    document.getElementById('ExchangeRate')?.addEventListener('input', updateSummary);

    document.getElementById('ReceiptVoucherSearchInput')?.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });

    document.getElementById('ReceiptVoucherClearBtn')?.addEventListener('click', function () {
        document.getElementById('ReceiptVoucherSearchInput').value = '';
        performSearch();
    });

    document.getElementById('ReceiptVoucherSearchInput')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); performSearch(); }
    });

    document.getElementById('btnNewReceiptVoucher')?.addEventListener('click', resetVoucher);
    document.getElementById('btnSaveReceiptVoucher')?.addEventListener('click', saveVoucher);
    document.getElementById('btnSaveAndNewReceiptVoucher')?.addEventListener('click', saveAndNewVoucher);
    document.getElementById('btnEditReceiptVoucher')?.addEventListener('click', editVoucher);
    document.getElementById('btnCancelReceiptVoucher')?.addEventListener('click', cancelVoucher);
    document.getElementById('btnPrintReceiptVoucher')?.addEventListener('click', printVoucher);
    document.getElementById('btnSearchReceiptVoucher')?.addEventListener('click', searchVoucher);

    clearForm();
    loadNextVoucherNumber();

    console.log('✅ تم تهيئة صفحة سند القبض بنجاح');
});