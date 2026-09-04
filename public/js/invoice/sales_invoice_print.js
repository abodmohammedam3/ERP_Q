window.printSalesInvoice = function () {
    const invoiceNo = document.getElementById('SalesInvoiceNo').value.trim();
    if (!invoiceNo) {
        alert('لا توجد فاتورة للطباعة.');
        return;
    }

    // إنشاء محتوى الطباعة
    let printContent = `
        <html dir="rtl" lang="ar">
        <head>
            <title>فاتورة بيع رقم ${invoiceNo}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 30px; direction: rtl; }
                h2 { text-align: center; margin-bottom: 25px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #000; padding: 8px; text-align: center; }
                th { background: #f2f2f2; }
                .info { margin-bottom: 20px; }
                .info span { margin-left: 20px; }
                .total { font-weight: bold; font-size: 1.2em; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <h2>فاتورة بيع</h2>
            <div class="info">
                <span><strong>رقم الفاتورة:</strong> ${invoiceNo}</span>
                <span><strong>التاريخ:</strong> ${document.getElementById('SalesInvoiceDate').value}</span>
                <span><strong>العميل:</strong> ${document.getElementById('customerName').value}</span>
                <span><strong>العملة:</strong> ${document.getElementById('salesCurrencyName').value}</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الصنف</th>
                        <th>النوع</th>
                        <th>الرمز</th>
                        <th>الوحدة</th>
                        <th>العدد</th>
                        <th>سعر الوحدة</th>
                        <th>الخصم</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
    `;

    document.querySelectorAll('#salesInvoiceDetails .sales-detail-row').forEach(function (row, index) {
        const item = row.querySelector('.row-item')?.value || '';
        const type = row.querySelector('.row-type')?.value || '';
        const code = row.querySelector('.row-code')?.value || '';
        const unit = row.querySelector('.row-unit')?.value || '';
        const quantity = row.querySelector('.row-measure')?.value || '0';
        const price = row.querySelector('.row-price')?.value || '0';
        const discount = row.querySelector('.row-discount')?.value || '0';
        const total = row.querySelector('.row-total')?.value || '0';

        printContent += `
            <tr>
                <td>${index + 1}</td>
                <td>${item}</td>
                <td>${type}</td>
                <td>${code}</td>
                <td>${unit}</td>
                <td>${quantity}</td>
                <td>${price}</td>
                <td>${discount}</td>
                <td>${total}</td>
            </tr>
        `;
    });

    const totalDisplay = document.getElementById('salesInvoiceTotalDisplay').textContent;
    const discountDisplay = document.getElementById('totalSalesDiscountDisplay').textContent;

    printContent += `
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7"></td>
                        <td><strong>إجمالي الخصم:</strong></td>
                        <td>${discountDisplay}</td>
                    </tr>
                    <tr>
                        <td colspan="7"></td>
                        <td><strong>إجمالي الفاتورة:</strong></td>
                        <td>${totalDisplay}</td>
                    </tr>
                </tfoot>
            </table>
            <div style="margin-top: 30px; text-align: left;">
                <span>تاريخ الطباعة: ${new Date().toLocaleDateString('ar-YE')}</span>
            </div>
        </body>
        </html>
    `;

    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
        alert('تعذر فتح نافذة الطباعة. يرجى السماح بالنوافذ المنبثقة.');
        return;
    }
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.onload = function () {
        printWindow.print();
    };
};