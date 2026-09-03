/**
 * صفحة سند الصرف - النسخة النهائية مع PaymentMethod
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 بدء تهيئة صفحة سند الصرف');

    // ========================================
    // 1. تهيئة المودالات
    // ========================================
    Modals.initAll();

    // ========================================
    // 2. إنشاء مدير الحالة (StateManager)
    // ========================================
    const state = new StateManager('payment-voucher');

    // تعيين الحقول
    state.setFields([
        'SupplierName', 'Amount', 'CurrencyName', 'ExchangeRate',
        'PaymentMethod', 'CashAccount', 'BankAccount', 'WalletAccount', 'Notes'
    ]);

    // تعيين الأزرار
    state.setButtons({
        add: 'btnNewPaymentVoucher',
        save: 'btnSavePaymentVoucher',
        saveNew: 'btnSaveAndNewPaymentVoucher',
        cancel: 'btnCancelPaymentVoucher',
        edit: 'btnEditPaymentVoucher',
        print: 'btnPrintPaymentVoucher',
        search: 'btnSearchPaymentVoucher'
    });

    // ✅ هنا نضيف دالة onModeChange (كما طلبت)
    state.onModeChange(function(mode) {
        console.log('🔄 تغيير الوضع إلى:', mode);
        
        // عند تغيير الوضع، نقوم بتحديث حالة طريقة الدفع
        PaymentMethod.setDisabled(mode === 'view'); // تعطيل الحقول إذا كان العرض
        PaymentMethod.change(); // إعادة تطبيق الإظهار/الإخفاء حسب الطريقة المختارة
        
        updateDisplay(); // تحديث عرض رقم السند
    });

    // ========================================
    // 3. ربط المكونات العامة (المورد والعملة)
    // ========================================
    Supplier.setTargets('SupplierName', 'SupplierID');
    Currency.setTargets('CurrencyName', 'CoinsID', 'ExchangeRate');

    // ========================================
    // 4. ✅ تهيئة PaymentMethod (هنا نضيف السطر الجديد)
    // ========================================
    PaymentMethod.init('PaymentMethod', {
        cash: 'cashAccountContainer',
        bank: 'bankAccountContainer',
        network: 'walletAccountContainer'
    });
    // بعد هذه التهيئة، أصبح الـ select مربوطاً تلقائياً
    // ولا نحتاج إلى onchange في HTML

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
    const num = document.getElementById('VoucherNumber')?.value || '';
    const date = document.getElementById('VoucherDate')?.value || '';

    const displayNum = document.getElementById('VoucherNumberDisplay');
    const displayDate = document.getElementById('VoucherDateDisplay');

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
        const fields = ['VoucherNumber', 'VoucherDate', 'SupplierID', 'SupplierName',
                        'Amount', 'CoinsID', 'CurrencyName', 'ExchangeRate',
                        'PaymentMethod', 'CashAccount', 'BankAccount', 'WalletAccount', 'Notes'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = '';
            }
        });
        PaymentMethod.change(); // إخفاء جميع الحاويات
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
        document.getElementById('VoucherNumber').value = 'صرف-' + now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0') + '-001';
        document.getElementById('VoucherDate').value = now.toISOString().slice(0,10);
        state.setMode('add');
        updateDisplay();
    };

   window.saveVoucher = function() {
    const supplier = document.getElementById('SupplierName').value.trim();
    const amount = parseFloat(document.getElementById('Amount').value) || 0;
    const currency = document.getElementById('CurrencyName').value.trim();

    if (!supplier) { alert('يرجى اختيار المورد.'); return; }
    if (amount <= 0) { alert('المبلغ يجب أن يكون أكبر من صفر.'); return; }
    if (!currency) { alert('يرجى اختيار العملة.'); return; }

    // 🔹 التأكد من وجود رقم سند (إذا كان فارغاً، قم بإنشائه)
    let voucherNumber = document.getElementById('VoucherNumber').value.trim();
    if (!voucherNumber) {
        const now = new Date();
        voucherNumber = 'صرف-' + now.getFullYear() + '-' + 
                        String(now.getMonth()+1).padStart(2,'0') + 
                        String(now.getDate()).padStart(2,'0') + '-001';
        document.getElementById('VoucherNumber').value = voucherNumber;
        document.getElementById('VoucherDate').value = now.toISOString().slice(0,10);
        console.log('✅ تم إنشاء رقم سند جديد:', voucherNumber);
    }

    alert(state.mode === 'edit' ? 'تم تعديل السند بنجاح.' : 'تم حفظ السند بنجاح.');

    // 🔹 الانتقال إلى وضع العرض
    state.setMode('view');
    
    // 🔹 تحديث واجهة المستخدم (رقم السند والتاريخ)
    updateDisplay();

    // 🔹 (هام) إعادة تطبيق الحالة لتفعيل الأزرار (تأكيد)
    state.setMode('view'); // إعادة استدعاء للتأكد من تحديث الأزرار

    console.log('✅ تم حفظ السند، رقم السند:', document.getElementById('VoucherNumber').value);
};

    window.saveAndNewVoucher = function() {
        saveVoucher();
        if (state.mode === 'view') resetVoucher();
    };

    window.editVoucher = function() {
        if (!state.hasData('VoucherNumber')) {
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
        if (!state.hasData('VoucherNumber')) {
            alert('لا يوجد سند للطباعة.');
            return;
        }
        window.print();
    };

    window.searchVoucher = function() {
    if (!Modals.get('PaymentVoucherSearchModal')) {
        Modals.init('PaymentVoucherSearchModal');
    }

    PaymentVoucherSearch.setTargets({
        number: 'VoucherNumber',
        date: 'VoucherDate',
        supplier: 'SupplierName',
        supplierId: 'SupplierID',
        amount: 'Amount',
        currency: 'CurrencyName',
        currencyId: 'CoinsID',
        exchangeRate: 'ExchangeRate',
        paymentMethod: 'PaymentMethod',
        notes: 'Notes'
    });

    PaymentVoucherSearch.setOnSelect(function(data) {
        state.setMode('view');
        updateDisplay();
        updateSummary();
        PaymentMethod.change();
    });

    PaymentVoucherSearch.openModal();
};

    // ========================================
    // 6. ربط الأحداث بالحقول
    // ========================================
    document.getElementById('SupplierName').addEventListener('keydown', function(e) {
        Supplier.keyDown(e);
    });

    document.getElementById('CurrencyName').addEventListener('keydown', function(e) {
        Currency.keyDown(e);
    });

    document.getElementById('Amount').addEventListener('input', updateSummary);
    document.getElementById('ExchangeRate').addEventListener('input', updateSummary);

    // ✅ لا حاجة لـ onchange هنا لأن PaymentMethod.init قام بربطه

    // ========================================
    // 7. ربط الأزرار
    // ========================================
    document.getElementById('btnNewPaymentVoucher').addEventListener('click', resetVoucher);
    document.getElementById('btnSavePaymentVoucher').addEventListener('click', saveVoucher);
    document.getElementById('btnSaveAndNewPaymentVoucher').addEventListener('click', saveAndNewVoucher);
    document.getElementById('btnEditPaymentVoucher').addEventListener('click', editVoucher);
    document.getElementById('btnCancelPaymentVoucher').addEventListener('click', cancelVoucher);
    document.getElementById('btnPrintPaymentVoucher').addEventListener('click', printVoucher);
    document.getElementById('btnSearchPaymentVoucher').addEventListener('click', searchVoucher);

    // ========================================
    // 8. تعيين الوضع الافتراضي
    // ========================================
    clearForm();

    console.log('✅ تم تهيئة صفحة سند الصرف بنجاح');
});