/**
 * صفحة سند القبض - النسخة المعيارية (مبنية على سند الصرف)
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 بدء تهيئة صفحة سند القبض');

    // ========================================
    // 1. تهيئة المودالات
    // ========================================
    Modals.initAll();

    // ========================================
    // 2. إنشاء مدير الحالة (StateManager)
    // ========================================
    const state = new StateManager('receipt-voucher');

    // تعيين الحقول (إضافة ChequeAccount)
    state.setFields([
        'CustomerName', 'Amount', 'CurrencyName', 'ExchangeRate',
        'PaymentMethod', 'CashAccount', 'BankAccount', 'WalletAccount', 'ChequeAccount', 'Notes'
    ]);

    // تعيين الأزرار
    state.setButtons({
        add: 'btnNewReceiptVoucher',
        save: 'btnSaveReceiptVoucher',
        saveNew: 'btnSaveAndNewReceiptVoucher',
        cancel: 'btnCancelReceiptVoucher',
        edit: 'btnEditReceiptVoucher',
        print: 'btnPrintReceiptVoucher',
        search: 'btnSearchReceiptVoucher'
    });

    // دالة onModeChange
    state.onModeChange(function(mode) {
        console.log('🔄 تغيير الوضع إلى:', mode);
        
        PaymentMethod.setDisabled(mode === 'view');
        PaymentMethod.change();
        updateDisplay();
    });

    // ========================================
    // 3. ربط المكونات العامة (العميل والعملة)
    // ========================================
    Customer.setTargets('CustomerName', 'CustomerID');
    Currency.setTargets('CurrencyName', 'CoinsID', 'ExchangeRate');

    // ========================================
    // 4. تهيئة PaymentMethod (مع إضافة cheque)
    // ========================================
    PaymentMethod.init('PaymentMethod', {
        cash: 'cashAccountContainer',
        bank: 'bankAccountContainer',
        network: 'walletAccountContainer',
        cheque: 'chequeAccountContainer'  // ← إضافة الشيك
    });

    // ========================================
    // 5. تعريف الدوال الخاصة بالصفحة
    // ========================================
    window.updateSummary = function() {
        const amount = parseFloat(document.getElementById('Amount').value) || 0;
        const rate = parseFloat(document.getElementById('ExchangeRate').value) || 1;
        const paid = amount * rate;

        document.getElementById('AmountWords').value = Utils.numberToWords(amount);
        document.getElementById('SummaryPaid').textContent = paid.toFixed(2);
        document.getElementById('SummaryPrevious').textContent = '0.00';
        document.getElementById('SummaryRemain').textContent = (0 - paid).toFixed(2);
    };

    function updateDisplay() {
        const num = document.getElementById('ReceiptVoucherNumber')?.value || '';
        const date = document.getElementById('ReceiptVoucherDate')?.value || '';

        const displayNum = document.getElementById('ReceiptVoucherNumberDisplay');
        const displayDate = document.getElementById('ReceiptVoucherDateDisplay');

        if (displayNum) {
            displayNum.textContent = num
                ? 'رقم السند: ' + num
                : 'رقم السند: --';
        }

        if (displayDate) {
            displayDate.innerHTML = date
                ? '<i class="bi bi-calendar3 me-1"></i> ' + date
                : '<i class="bi bi-calendar3 me-1"></i> --';
        }
    }

    function clearForm() {
        const fields = ['ReceiptVoucherNumber', 'ReceiptVoucherDate', 'CustomerID', 'CustomerName',
                        'Amount', 'CoinsID', 'CurrencyName', 'ExchangeRate',
                        'PaymentMethod', 'CashAccount', 'BankAccount', 'WalletAccount', 'ChequeAccount', 'Notes'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = '';
            }
        });
        PaymentMethod.change();
        document.getElementById('AmountWords').value = '';
        document.getElementById('SummaryPrevious').textContent = '0.00';
        document.getElementById('SummaryPaid').textContent = '0.00';
        document.getElementById('SummaryRemain').textContent = '0.00';
        state.setMode('view');
        updateDisplay();
    }

    window.resetVoucher = function() {
        clearForm();
        const now = new Date();
        document.getElementById('ReceiptVoucherNumber').value = 'قبض-' + now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0') + '-001';
        document.getElementById('ReceiptVoucherDate').value = now.toISOString().slice(0,10);
        state.setMode('add');
        updateDisplay();
    };

    window.saveVoucher = function() {
        const customer = document.getElementById('CustomerName').value.trim();
        const amount = parseFloat(document.getElementById('Amount').value) || 0;
        const currency = document.getElementById('CurrencyName').value.trim();

        if (!customer) { alert('يرجى اختيار العميل.'); return; }
        if (amount <= 0) { alert('المبلغ يجب أن يكون أكبر من صفر.'); return; }
        if (!currency) { alert('يرجى اختيار العملة.'); return; }

        let voucherNumber = document.getElementById('ReceiptVoucherNumber').value.trim();
        if (!voucherNumber) {
            const now = new Date();
            voucherNumber = 'قبض-' + now.getFullYear() + '-' + 
                            String(now.getMonth()+1).padStart(2,'0') + 
                            String(now.getDate()).padStart(2,'0') + '-001';
            document.getElementById('ReceiptVoucherNumber').value = voucherNumber;
            document.getElementById('ReceiptVoucherDate').value = now.toISOString().slice(0,10);
            console.log('✅ تم إنشاء رقم سند جديد:', voucherNumber);
        }

        alert(state.mode === 'edit' ? 'تم تعديل السند بنجاح.' : 'تم حفظ السند بنجاح.');

        state.setMode('view');
        updateDisplay();
        state.setMode('view'); // تأكيد

        console.log('✅ تم حفظ السند، رقم السند:', document.getElementById('ReceiptVoucherNumber').value);
    };

    window.saveAndNewVoucher = function() {
        saveVoucher();
        if (state.mode === 'view') resetVoucher();
    };

    window.editVoucher = function() {
        if (!state.hasData('ReceiptVoucherNumber')) {
            alert('لا يوجد سند محدد للتعديل.');
            return;
        }
        state.setMode('edit');
    };

    window.cancelVoucher = function() {
        if (confirm('هل أنت متأكد من إلغاء العملية؟')) {
            clearForm();
        }
    };

    window.printVoucher = function() {
        if (!state.hasData('ReceiptVoucherNumber')) {
            alert('لا يوجد سند للطباعة.');
            return;
        }
        window.print();
    };

    window.searchVoucher = function() {
        if (!Modals.get('ReceiptVoucherSearchModal')) {
            Modals.init('ReceiptVoucherSearchModal');
        }

        ReceiptVoucherSearch.setTargets({
            number: 'ReceiptVoucherNumber',
            date: 'ReceiptVoucherDate',
            customer: 'CustomerName',
            customerId: 'CustomerID',
            amount: 'Amount',
            currency: 'CurrencyName',
            currencyId: 'CoinsID',
            exchangeRate: 'ExchangeRate',
            paymentMethod: 'PaymentMethod',
            notes: 'Notes'
        });

        ReceiptVoucherSearch.setOnSelect(function(data) {
            state.setMode('view');
            updateDisplay();
            updateSummary();
            PaymentMethod.change();
        });

        ReceiptVoucherSearch.openModal();
    };

    // ========================================
    // 6. ربط الأحداث بالحقول
    // ========================================
    document.getElementById('CustomerName').addEventListener('keydown', function(e) {
        Customer.keyDown(e);
    });

    document.getElementById('CurrencyName').addEventListener('keydown', function(e) {
        Currency.keyDown(e);
    });

    document.getElementById('Amount').addEventListener('input', updateSummary);
    document.getElementById('ExchangeRate').addEventListener('input', updateSummary);

    // ========================================
    // 7. ربط الأزرار
    // ========================================
    document.getElementById('btnNewReceiptVoucher').addEventListener('click', resetVoucher);
    document.getElementById('btnSaveReceiptVoucher').addEventListener('click', saveVoucher);
    document.getElementById('btnSaveAndNewReceiptVoucher').addEventListener('click', saveAndNewVoucher);
    document.getElementById('btnEditReceiptVoucher').addEventListener('click', editVoucher);
    document.getElementById('btnCancelReceiptVoucher').addEventListener('click', cancelVoucher);
    document.getElementById('btnPrintReceiptVoucher').addEventListener('click', printVoucher);
    document.getElementById('btnSearchReceiptVoucher').addEventListener('click', searchVoucher);

    // ========================================
    // 8. تعيين الوضع الافتراضي
    // ========================================
    clearForm();

    console.log('✅ تم تهيئة صفحة سند القبض بنجاح');
});