// فلترة حركات المخزون
function filterMovements() {
    const stock = document.getElementById('filterStock').value;
    const type = document.getElementById('filterType').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;

    const rows = document.querySelectorAll('tr.movement-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowStock = row.querySelector('.row-stock').innerText;
        const rowType = row.querySelector('.row-type').innerText;
        const rowDate = row.querySelector('.row-date').innerText;

        let match = true;

        if (stock && rowStock !== stock) match = false;
        if (type && rowType !== type) match = false;
        if (dateFrom && rowDate < dateFrom) match = false;
        if (dateTo && rowDate > dateTo) match = false;

        if (match) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // تحديث العداد
    document.getElementById('movementsCountBadge').innerText = visibleCount;

    // إظهار/إخفاء رسالة الجدول الفارغ
    const emptyRow = document.getElementById('emptyMovementsRow');
    if (visibleCount === 0) {
        emptyRow.style.display = '';
    } else {
        emptyRow.style.display = 'none';
    }
}

// طباعة الجدول
function printMovements() {
    const table = document.getElementById('movementsTable');
    
    let printContents = `
        <html dir="rtl" lang="ar">
        <head>
            <title>طباعة حركات المخزون</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h2 { text-align: center; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; text-align: center; }
                th, td { border: 1px solid #000; padding: 10px; }
                th { background-color: #f8f9fa; }
                .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                .bg-success { background-color: #198754; color: white; }
                .bg-danger { background-color: #dc3545; color: white; }
                .no-print { display: none !important; }
            </style>
        </head>
        <body>
            <h2>سجل حركات المخزون</h2>
            <table>
    `;

    // جلب الهيدر واستبعاد عمود التفاصيل
    const thead = table.querySelector('thead').innerHTML;
    printContents += `<thead>${thead}</thead><tbody>`;

    // جلب الصفوف الظاهرة فقط
    const rows = table.querySelectorAll('tbody tr.movement-row');
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            printContents += `<tr>${row.innerHTML}</tr>`;
        }
    });

    printContents += `</tbody></table></body></html>`;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContents);
    printWindow.document.close();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 300);
}
