// --- تهيئة الصفحة عند التحميل ---
document.addEventListener('DOMContentLoaded', function() {
    // إدراج صف افتراضي عند فتح الشاشة
    addInvoiceRow();
});

// --- إضافة صف جديد للأصناف ---
function addInvoiceRow() {
    const tbody = document.getElementById('purchaseInvoiceDetails');
    
    // إزالة رسالة "لا توجد أصناف" إذا كانت موجودة
    if (tbody.querySelector('td[colspan="11"]')) {
        tbody.innerHTML = '';
    }

    const rowCount = tbody.children.length + 1;
    const newRow = document.createElement('tr');
    
    newRow.innerHTML = `
        <td class="row-num text-center">${rowCount}</td>
        <td><input type="text" class="form-control form-control-sm" placeholder="اسم الصنف"></td>
        <td>
            <select class="form-select form-select-sm">
                <option>مخزني</option>
                <option>خدمي</option>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" placeholder="الرمز"></td>
        <td>
            <select class="form-select form-select-sm">
                <option>المستودع الرئيسي</option>
            </select>
        </td>
        <td><input type="number" class="form-control form-control-sm" value="0" step="0.01"></td>
        <td><input type="number" class="form-control form-control-sm row-qty" value="1" step="0.01" min="1" oninput="calculateRow(this)"></td>
        <td><input type="number" class="form-control form-control-sm row-price" value="0.00" step="0.01" min="0" oninput="calculateRow(this)"></td>
        <td><input type="number" class="form-control form-control-sm row-subtotal" value="0.00" readonly></td>
        <td><input type="number" class="form-control form-control-sm row-discount" value="0.00" step="0.01" min="0" oninput="calculateRow(this)"></td>
        <td><input type="number" class="form-control form-control-sm row-total" value="0.00" readonly></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(newRow);
    calculateTotals();
}

// --- حساب تفاصيل الصف الواحد ---
function calculateRow(input) {
    const row = input.closest('tr');
    
    const qty = parseFloat(row.querySelector('.row-qty').value) || 0;
    const price = parseFloat(row.querySelector('.row-price').value) || 0;
    const discount = parseFloat(row.querySelector('.row-discount').value) || 0;
    
    // حساب السعر قبل الخصم
    const subtotal = qty * price;
    row.querySelector('.row-subtotal').value = subtotal.toFixed(2);
    
    // حساب الإجمالي بعد الخصم الخاص بالصنف
    const total = subtotal - discount;
    row.querySelector('.row-total').value = total > 0 ? total.toFixed(2) : '0.00';
    
    calculateTotals();
}

// --- حساب إجماليات الفاتورة ---
function calculateTotals() {
    let itemsTotalSum = 0;
    
    // جمع إجماليات جميع الأصناف
    document.querySelectorAll('.row-total').forEach(input => {
        itemsTotalSum += parseFloat(input.value) || 0;
    });

    // جلب قيم التكاليف الإضافية
    const expenses = parseFloat(document.getElementById('PuInExpenses').value) || 0;
    const transport = parseFloat(document.getElementById('PuInTransportation').value) || 0;
    const tax = parseFloat(document.getElementById('PuInTaxCost').value) || 0;
    const other = parseFloat(document.getElementById('PuInOtherCost').value) || 0;
    
    // حساب إجمالي الفاتورة (الأصناف + التكاليف)
    const grossTotal = itemsTotalSum + expenses + transport + tax + other;
    document.getElementById('PuInTptalPrice2').value = grossTotal.toFixed(2);
    
    // جلب الخصم الرئيسي وحساب الصافي
    const mainDiscount = parseFloat(document.getElementById('PuInDIscount2').value) || 0;
    const netTotal = grossTotal - mainDiscount;
    
    document.getElementById('PuInTotalAfterDiscount2').value = netTotal > 0 ? netTotal.toFixed(2) : '0.00';
}

// --- حذف صف ---
function removeRow(btn) {
    const tbody = document.getElementById('purchaseInvoiceDetails');
    const row = btn.closest('tr');
    row.remove();
    
    // إعادة ترقيم الصفوف
    document.querySelectorAll('#purchaseInvoiceDetails tr').forEach((tr, index) => {
        const numCell = tr.querySelector('.row-num');
        if (numCell) numCell.textContent = index + 1;
    });

    // إذا تم حذف كل الصفوف، أظهر رسالة فارغة
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center text-muted py-4">
                    لا توجد أصناف مضافة إلى الفاتورة
                </td>
            </tr>
        `;
    }
    
    calculateTotals();
}

// --- محاكاة اختيار مورد ---
function selectSupplier() {
    // يمكن هنا فتح Modal لاختيار المورد
    document.getElementById('suplierID').value = "101";
    document.getElementById('supplierName').value = "شركة التوريدات العالمية";
}

// --- حفظ الفاتورة ---
function saveInvoice() {
    const netTotal = parseFloat(document.getElementById('PuInTotalAfterDiscount2').value) || 0;
    if (netTotal <= 0) {
        alert('لا يمكن حفظ فاتورة بقيمة صفر أو بدون أصناف.');
        return;
    }
    alert('تم حفظ الفاتورة بنجاح!');
}

// --- إلغاء أو تفريغ الفاتورة ---
function cancelInvoice() {
    if (confirm('هل أنت متأكد من التراجع؟ سيتم مسح كافة البيانات.')) {
        resetInvoice();
    }
}

// --- إعادة تهيئة الشاشة ---
function resetInvoice() {
    // تصفير جميع المدخلات
    document.querySelectorAll('input[type="text"], input[type="number"], input[type="date"], textarea').forEach(input => {
        input.value = '';
    });
    
    // إعادة تعيين القوائم المنسدلة
    document.querySelectorAll('select').forEach(select => {
        select.selectedIndex = 0;
    });

    // تفريغ الجدول وإضافة صف فارغ
    document.getElementById('purchaseInvoiceDetails').innerHTML = '';
    addInvoiceRow();
}

// --- بحث ---
function searchInvoice() {
    alert('فتح نافذة البحث عن الفواتير السابقة...');
}
