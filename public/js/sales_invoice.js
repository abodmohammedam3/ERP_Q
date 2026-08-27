// --- تهيئة الصفحة عند التحميل ---
document.addEventListener('DOMContentLoaded', function() {
    addSalesRow(); // إضافة صف افتراضي
});

// --- إضافة صف جديد ---
function addSalesRow() {
    const tbody = document.getElementById('salesInvoiceDetails');
    const emptyRow = document.getElementById('emptySalesRow');
    
    if (emptyRow) {
        emptyRow.remove();
    }

    const rowCount = tbody.children.length + 1;
    const newRow = document.createElement('tr');
    
    newRow.innerHTML = `
        <td class="row-num text-center align-middle">${rowCount}</td>
        <td><input type="text" class="form-control form-control-sm" placeholder="اسم الصنف"></td>
        <td>
            <select class="form-select form-select-sm">
                <option>منتج نهائي</option>
                <option>خدمة</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm">
                <option>المعرض</option>
                <option>المستودع</option>
            </select>
        </td>
        <td><input type="number" class="form-control form-control-sm row-qty" value="1" min="1" step="0.01" oninput="calculateSalesRow(this)"></td>
        <td><input type="number" class="form-control form-control-sm row-cost" value="0.00" readonly></td>
        <td><input type="number" class="form-control form-control-sm row-price" value="0.00" min="0" step="0.01" oninput="calculateSalesRow(this)"></td>
        <td><input type="number" class="form-control form-control-sm row-subtotal" value="0.00" readonly></td>
        <td><input type="number" class="form-control form-control-sm row-discount" value="0.00" min="0" step="0.01" oninput="calculateSalesRow(this)"></td>
        <td><input type="number" class="form-control form-control-sm row-total" value="0.00" readonly></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSalesRow(this)">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(newRow);
    calculateSalesTotals();
}

// --- حساب تفاصيل الصف الواحد ---
function calculateSalesRow(input) {
    const row = input.closest('tr');
    
    const qty = parseFloat(row.querySelector('.row-qty').value) || 0;
    const price = parseFloat(row.querySelector('.row-price').value) || 0;
    const discount = parseFloat(row.querySelector('.row-discount').value) || 0;
    
    const subtotal = qty * price;
    row.querySelector('.row-subtotal').value = subtotal.toFixed(2);
    
    const total = subtotal - discount;
    row.querySelector('.row-total').value = total > 0 ? total.toFixed(2) : '0.00';
    
    calculateSalesTotals();
}

// --- حساب المجاميع والملخص ---
function calculateSalesTotals() {
    const rows = document.querySelectorAll('#salesInvoiceDetails tr:not(#emptySalesRow)');
    
    let totalQtySum = 0;
    let itemsTotalSum = 0;
    let itemCount = rows.length;

    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.row-qty').value) || 0;
        const total = parseFloat(row.querySelector('.row-total').value) || 0;
        
        totalQtySum += qty;
        itemsTotalSum += total;
    });

    // تحديث معلومات العملية
    document.getElementById('totalItems').value = itemCount;
    document.getElementById('totalQuantity').value = totalQtySum.toFixed(2);

    // تحديث إجماليات الفاتورة
    document.getElementById('SalesTotalAmount3').value = itemsTotalSum.toFixed(2);
    
    const mainDiscount = parseFloat(document.getElementById('SalesDiscount3').value) || 0;
    const netTotal = itemsTotalSum - mainDiscount;
    
    document.getElementById('SalesNetAmount3').value = netTotal > 0 ? netTotal.toFixed(2) : '0.00';
}

// --- حذف صف معين ---
function removeSalesRow(btn) {
    const tbody = document.getElementById('salesInvoiceDetails');
    const row = btn.closest('tr');
    row.remove();
    
    // إعادة الترقيم
    document.querySelectorAll('#salesInvoiceDetails tr:not(#emptySalesRow)').forEach((tr, index) => {
        const numCell = tr.querySelector('.row-num');
        if (numCell) numCell.textContent = index + 1;
    });

    // إظهار رسالة الجدول الفارغ إذا لزم الأمر
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr id="emptySalesRow">
                <td colspan="11" class="text-center text-muted py-4">
                    لا توجد أصناف مضافة إلى الفاتورة
                </td>
            </tr>
        `;
    }
    
    calculateSalesTotals();
}

// --- تفريغ كافة التفاصيل ---
function clearSalesItems() {
    if (confirm('هل أنت متأكد من مسح جميع الأصناف؟')) {
        document.getElementById('salesInvoiceDetails').innerHTML = `
            <tr id="emptySalesRow">
                <td colspan="11" class="text-center text-muted py-4">
                    لا توجد أصناف مضافة إلى الفاتورة
                </td>
            </tr>
        `;
        calculateSalesTotals();
    }
}

// --- محاكاة اختيار العميل ---
function selectCustomer() {
    document.getElementById('customerID').value = "201";
    document.getElementById('customerName').value = "مؤسسة الأفق التجارية";
}

// --- حفظ الفاتورة ---
function saveSalesInvoice() {
    const netTotal = parseFloat(document.getElementById('SalesNetAmount3').value) || 0;
    if (netTotal <= 0) {
        alert('يرجى إضافة أصناف قبل الحفظ.');
        return;
    }
    alert('تم حفظ الفاتورة بنجاح!');
}

// --- حفظ وإضافة جديد ---
function saveAndNewSalesInvoice() {
    saveSalesInvoice();
    resetSalesInvoice();
}

// --- إعادة التهيئة (إلغاء / جديد) ---
function resetSalesInvoice() {
    document.querySelectorAll('input[type="text"], input[type="number"], input[type="date"]').forEach(input => {
        input.value = '';
    });
    
    document.getElementById('salesInvoiceDetails').innerHTML = '';
    addSalesRow();
}

function cancelSalesInvoice() {
    if (confirm('هل أنت متأكد من التراجع؟')) {
        resetSalesInvoice();
    }
}
