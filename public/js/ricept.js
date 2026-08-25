
  
        // --- دالة لتحديث المبلغ كتابة (نموذجية) ---
        function updateAmountWords(value) {
            const num = parseFloat(value) || 0;
            // يمكنك استبدال هذه الدالة بخدمة خارجية لتحويل الأرقام إلى حروف
            // لكن هنا نضع نصاً نموذجياً
            const words = num.toLocaleString('ar') + ' ريال سعودي';
            document.getElementById('amountWords').value = words;
            // تحديث الملخص
            document.getElementById('summaryTotal').textContent = num.toFixed(2);
            document.getElementById('summaryPaid').textContent = num.toFixed(2);
            // إعادة حساب المتبقي (إذا كان المبلغ الإجمالي مساوياً للمدفوع)
            const total = num;
            const paid = num; // نفترض أن المبلغ كله مدفوع
            document.getElementById('summaryRemain').textContent = (total - paid).toFixed(2);
        }

        // --- تحديث صف الفاتورة (حساب المتبقي) ---
        function updateInvoiceRow(input) {
            const row = input.closest('tr');
            const total = parseFloat(row.querySelector('.invoice-total').value) || 0;
            const paid = parseFloat(row.querySelector('.invoice-paid').value) || 0;
            const balance = total - paid;
            row.querySelector('.invoice-balance').textContent = balance.toFixed(2);
            // تحديث حقل المسدد الآن: إذا كان المتبقي صفراً نعطله
            const nowInput = row.querySelector('.invoice-now');
            if (balance <= 0.01) {
                nowInput.disabled = true;
                nowInput.value = '0.00';
            } else {
                nowInput.disabled = false;
                // إذا كانت القيمة الحالية أكبر من المتبقي نصححها
                if (parseFloat(nowInput.value) > balance) {
                    nowInput.value = balance.toFixed(2);
                }
            }
            updateTotals();
        }

        // --- حساب إجمالي المدفوع الآن من جميع الصفوف ---
        function updateTotals() {
            const rows = document.querySelectorAll('#invoiceBody tr');
            let totalPaid = 0;
            rows.forEach(row => {
                const now = parseFloat(row.querySelector('.invoice-now').value) || 0;
                totalPaid += now;
            });
            document.getElementById('totalPaidDisplay').textContent = totalPaid.toFixed(2);
            document.getElementById('summaryPaid').textContent = totalPaid.toFixed(2);
            // تحديث المبلغ الإجمالي (من حقل المبلغ الأساسي)
            const mainAmount = parseFloat(document.getElementById('amount').value) || 0;
            const remain = mainAmount - totalPaid;
            document.getElementById('summaryRemain').textContent = remain.toFixed(2);
            document.getElementById('summaryTotal').textContent = mainAmount.toFixed(2);
            // تحديث المبلغ كتابة (بناءً على المبلغ الأساسي)
            updateAmountWords(mainAmount);
        }

        // --- إضافة صف جديد في جدول الفواتير ---
        function addInvoiceRow() {
            const tbody = document.getElementById('invoiceBody');
            const rowCount = tbody.children.length + 1;
            const newRow = document.createElement('tr');
            newRow.dataset.id = rowCount;
            newRow.innerHTML = `
                <td>${rowCount}</td>
                <td><input type="text" class="form-control form-control-sm" placeholder="رقم الفاتورة"></td>
                <td><input type="date" class="form-control form-control-sm" value="${new Date().toISOString().split('T')[0]}"></td>
                <td><input type="number" class="form-control form-control-sm invoice-total" value="0.00" step="0.01" onchange="updateInvoiceRow(this)"></td>
                <td><input type="number" class="form-control form-control-sm invoice-paid" value="0.00" step="0.01" readonly></td>
                <td class="invoice-balance">0.00</td>
                <td><input type="number" class="form-control form-control-sm invoice-now" value="0.00" step="0.01" min="0" oninput="updateTotals()"></td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash3"></i></button></td>
            `;
            tbody.appendChild(newRow);
            updateTotals();
        }

        // --- حذف صف من الجدول ---
        function removeRow(btn) {
            if (confirm('هل أنت متأكد من حذف هذا الصف؟')) {
                const row = btn.closest('tr');
                row.remove();
                // إعادة ترقيم الصفوف
                document.querySelectorAll('#invoiceBody tr').forEach((row, idx) => {
                    row.querySelector('td:first-child').textContent = idx + 1;
                });
                updateTotals();
            }
        }

        // --- البحث عن عميل (محاكاة) ---
        function searchCustomer() {
            alert('فتح نافذة البحث عن العملاء (يمكن ربطها بواجهة بحث متقدمة)');
        }

        // --- حفظ السند (التحقق من الصحة) ---
        function saveVoucher() {
            const form = document.getElementById('voucherForm');
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                alert('يرجى تعبئة جميع الحقول الإلزامية.');
                return;
            }
            // تحقق من وجود صفوف ومدفوعات
            const rows = document.querySelectorAll('#invoiceBody tr');
            let hasPayment = false;
            rows.forEach(row => {
                const now = parseFloat(row.querySelector('.invoice-now').value) || 0;
                if (now > 0) hasPayment = true;
            });
            if (!hasPayment) {
                alert('يجب أن يكون هناك مبلغ مسدد في سند القبض.');
                return;
            }
            alert('تم حفظ السند بنجاح!');
        }

        // --- اعتماد وطباعة ---
        function approveAndPrint() {
            if (confirm('هل أنت متأكد من اعتماد هذا السند؟')) {
                alert('تم اعتماد السند وسيتم فتح نافذة الطباعة.');
                window.print();
            }
        }

        // --- حفظ كمسودة ---
        function saveDraft() {
            alert('تم حفظ السند كمسودة.');
        }

        // --- إلغاء السند ---
        function cancelVoucher() {
            if (confirm('هل أنت متأكد من إلغاء السند؟ سيتم فقدان البيانات غير المحفوظة.')) {
                resetForm();
            }
        }

        // --- إعادة تعيين النموذج (جديد) ---
        function resetForm() {
            if (confirm('سيتم مسح جميع البيانات. هل تريد المتابعة؟')) {
                document.getElementById('voucherForm').reset();
                document.getElementById('voucherForm').classList.remove('was-validated');
                // إعادة تعيين الجدول إلى صف واحد فقط
                const tbody = document.getElementById('invoiceBody');
                tbody.innerHTML = '';
                // نضيف صفاً افتراضياً
                const defaultRow = document.createElement('tr');
                defaultRow.dataset.id = 1;
                defaultRow.innerHTML = `
                    <td>1</td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="رقم الفاتورة"></td>
                    <td><input type="date" class="form-control form-control-sm" value="${new Date().toISOString().split('T')[0]}"></td>
                    <td><input type="number" class="form-control form-control-sm invoice-total" value="0.00" step="0.01" onchange="updateInvoiceRow(this)"></td>
                    <td><input type="number" class="form-control form-control-sm invoice-paid" value="0.00" step="0.01" readonly></td>
                    <td class="invoice-balance">0.00</td>
                    <td><input type="number" class="form-control form-control-sm invoice-now" value="0.00" step="0.01" min="0" oninput="updateTotals()"></td>
                    <td><button class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash3"></i></button></td>
                `;
                tbody.appendChild(defaultRow);
                // إعادة تعيين المبلغ الأساسي
                document.getElementById('amount').value = '';
                document.getElementById('amountWords').value = '';
                updateTotals();
                alert('تم إنشاء سند جديد.');
            }
        }

        // --- تهيئة الصفحة ---
        document.addEventListener('DOMContentLoaded', function() {
            // تحديث المبلغ كتابة أول مرة
            updateAmountWords(document.getElementById('amount').value);
            // تحديث المجاميع
            updateTotals();
            // تطبيق التحقق من صحة الحقول عند الخروج
            const form = document.getElementById('voucherForm');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                saveVoucher();
            });
        });

        // --- تحسين زر الطباعة (لطباعة جزء معين) ---
        // يمكن إضافة media query للطباعة في الـ CSS
        // لكننا نكتفي بالطباعة العادية.
    
