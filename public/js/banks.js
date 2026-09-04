document.addEventListener('DOMContentLoaded', function () {

    let banks = [];
    let lastAnalyticalAccount = 12110000; // بداية الترقيم
    let bankModalInstance;

    const banksTable = document.querySelector('#banksTable tbody');
    const searchInput = document.getElementById('bankSearch');
    const searchButton = document.getElementById('searchBankBtn');
    const addButton = document.getElementById('addBankBtn');
    const printButton = document.getElementById('printBanksBtn');
    const saveBankBtn = document.getElementById('saveBankBtn');

    // عناصر المودال
    const bankModal = document.getElementById('bankModal');
    const bankForm = document.getElementById('bankForm');
    const bankIdInput = document.getElementById('bankId');
    const bankNameInput = document.getElementById('bankName');
    const bankAccountInput = document.getElementById('bankAccount');
    const bankAnalyticalInput = document.getElementById('bankAnalytical');
    const bankCurrencySelect = document.getElementById('bankCurrency');
    const bankModalLabel = document.getElementById('bankModalLabel');

    // تهيئة المودال
    if (bankModal) {
        bankModalInstance = new bootstrap.Modal(bankModal);
    }

    // بيانات تجريبية (مع العملة)
    banks = [
        {
            id: 1,
            name: 'بنك الكريمي',
            accountNumber: '123456',
            analyticalAccount: '12110001',
            currency: 'ريال يمني'
        },
        {
            id: 2,
            name: 'بنك اليمن والكويت',
            accountNumber: '789012',
            analyticalAccount: '12110002',
            currency: 'ريال سعودي'
        },
        {
            id: 3,
            name: 'بنك التضامن',
            accountNumber: '345678',
            analyticalAccount: '12110003',
            currency: 'دولار أمريكي'
        }
    ];

    // حساب آخر رقم تحليلي من البيانات الحالية
    function updateLastAnalytical() {
        let max = 12110000;
        banks.forEach(b => {
            const num = parseInt(b.analyticalAccount, 10);
            if (!isNaN(num) && num > max) max = num;
        });
        lastAnalyticalAccount = max;
    }
    updateLastAnalytical();

    // =========================================================
    // عرض البنوك
    // =========================================================
    function renderBanks(data = banks) {
        if (!banksTable) return;

        banksTable.innerHTML = '';

        if (data.length === 0) {
            banksTable.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-bank fs-2 d-block mb-2"></i>
                        لا توجد بنوك مسجلة
                    </td>
                </tr>
            `;
            return;
        }

        data.forEach(function (bank, index) {
            const row = document.createElement('tr');
            row.className = 'bank-row';
            row.dataset.id = bank.id;

            row.innerHTML = `
                <td class="text-center row-number">${index + 1}</td>
                <td class="row-name">${escapeHtml(bank.name)}</td>
                <td class="row-account">${escapeHtml(bank.accountNumber)}</td>
                <td class="row-analytical">${escapeHtml(bank.analyticalAccount)}</td>
                <td class="row-currency">${escapeHtml(bank.currency)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-primary edit-bank" data-id="${bank.id}">
                        <i class="bi bi-pencil"></i> تعديل
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-bank" data-id="${bank.id}">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </td>
            `;

            banksTable.appendChild(row);
        });
    }

    // =========================================================
    // إعادة ترقيم الصفوف
    // =========================================================
    function renumberRows() {
        const rows = document.querySelectorAll('tr.bank-row');
        rows.forEach((row, index) => {
            const numCell = row.querySelector('.row-number');
            if (numCell) numCell.innerText = index + 1;
        });
    }

    // =========================================================
    // فتح مودال الإضافة
    // =========================================================
    function openAddBankModal() {
        bankForm.reset();
        bankIdInput.value = '';
        bankModalLabel.innerText = 'إضافة بنك جديد';

        // توليد رقم تحليلي جديد
        const newAnalytical = lastAnalyticalAccount + 1;
        bankAnalyticalInput.value = newAnalytical;

        // تعيين العملة الافتراضية (اختياري)
        bankCurrencySelect.value = 'ريال يمني';

        bankModalInstance.show();
    }

    // =========================================================
    // فتح مودال التعديل
    // =========================================================
    function openEditBankModal(id) {
        const bank = banks.find(b => b.id === id);
        if (!bank) return;

        bankIdInput.value = bank.id;
        bankNameInput.value = bank.name;
        bankAccountInput.value = bank.accountNumber;
        bankAnalyticalInput.value = bank.analyticalAccount;
        bankCurrencySelect.value = bank.currency;

        bankModalLabel.innerText = 'تعديل بيانات البنك';
        bankModalInstance.show();
    }

    // =========================================================
    // حفظ البنك (إضافة أو تعديل)
    // =========================================================
    function saveBank() {
        // التحقق من صحة النموذج
        if (!bankForm.checkValidity()) {
            bankForm.reportValidity();
            return;
        }

        const id = bankIdInput.value;
        const name = bankNameInput.value.trim();
        const accountNumber = bankAccountInput.value.trim();
        const analyticalAccount = bankAnalyticalInput.value.trim();
        const currency = bankCurrencySelect.value;

        if (!name || !accountNumber || !currency) {
            alert('يرجى ملء جميع الحقول المطلوبة.');
            return;
        }

        if (id) {
            // تعديل
            const existing = banks.find(b => b.id === parseInt(id));
            if (existing) {
                existing.name = name;
                existing.accountNumber = accountNumber;
                existing.analyticalAccount = analyticalAccount;
                existing.currency = currency;
            }
        } else {
            // إضافة
            const newId = banks.length > 0 ? Math.max(...banks.map(b => b.id)) + 1 : 1;
            banks.push({
                id: newId,
                name: name,
                accountNumber: accountNumber,
                analyticalAccount: analyticalAccount,
                currency: currency
            });
            // تحديث آخر رقم تحليلي
            const num = parseInt(analyticalAccount, 10);
            if (!isNaN(num) && num > lastAnalyticalAccount) {
                lastAnalyticalAccount = num;
            }
        }

        renderBanks();
        renumberRows();
        bankModalInstance.hide();
        alert('تم حفظ البنك بنجاح.');
    }

    // =========================================================
    // حذف البنك
    // =========================================================
    function deleteBank(id) {
        const bank = banks.find(b => b.id === id);
        if (!bank) return;

        if (!confirm(`هل أنت متأكد من حذف البنك "${bank.name}"؟`)) return;

        banks = banks.filter(b => b.id !== id);
        renderBanks();
        renumberRows();
        alert('تم حذف البنك بنجاح.');
    }

    // =========================================================
    // البحث
    // =========================================================
    function searchBanks() {
        const searchValue = searchInput.value.trim().toLowerCase();
        if (searchValue === '') {
            renderBanks();
            return;
        }
        const filtered = banks.filter(b => b.name.toLowerCase().includes(searchValue));
        renderBanks(filtered);
    }

    // =========================================================
    // الطباعة
    // =========================================================
    function printBanks() {
        const searchValue = searchInput.value.trim().toLowerCase();
        let dataToPrint = banks;
        if (searchValue !== '') {
            dataToPrint = banks.filter(b => b.name.toLowerCase().includes(searchValue));
        }

        if (dataToPrint.length === 0) {
            alert('لا توجد بيانات للطباعة.');
            return;
        }

        let rows = '';
        dataToPrint.forEach((bank, index) => {
            rows += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(bank.name)}</td>
                    <td>${escapeHtml(bank.accountNumber)}</td>
                    <td>${escapeHtml(bank.analyticalAccount)}</td>
                    <td>${escapeHtml(bank.currency)}</td>
                </tr>
            `;
        });

        const printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>طباعة البنوك</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 30px; direction: rtl; }
                    h2 { text-align: center; margin-bottom: 25px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #000; padding: 10px; text-align: center; }
                    th { background: #eee; }
                    .date { text-align: left; margin-bottom: 15px; }
                    @media print { button { display: none; } }
                </style>
            </head>
            <body>
                <h2>قائمة البنوك</h2>
                <div class="date">تاريخ الطباعة: ${new Date().toLocaleDateString('ar-YE')}</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم البنك</th>
                            <th>رقم الحساب</th>
                            <th>رقم الحساب التحليلي</th>
                            <th>عملة الحساب</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <script>
                    window.onload = function () { window.print(); };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // =========================================================
    // حماية النصوص
    // =========================================================
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =========================================================
    // الأحداث
    // =========================================================

    // زر الإضافة
    if (addButton) {
        addButton.addEventListener('click', openAddBankModal);
    }

    // زر الحفظ في المودال
    if (saveBankBtn) {
        saveBankBtn.addEventListener('click', saveBank);
    }

    // البحث
    if (searchButton) {
        searchButton.addEventListener('click', searchBanks);
    }
    if (searchInput) {
        searchInput.addEventListener('keyup', function (e) {
            if (e.key === 'Enter') searchBanks();
        });
    }

    // الطباعة
    if (printButton) {
        printButton.addEventListener('click', printBanks);
    }

    // أحداث أزرار التعديل والحذف (delegation)
    if (banksTable) {
        banksTable.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-bank');
            const deleteBtn = e.target.closest('.delete-bank');

            if (editBtn) {
                const id = Number(editBtn.dataset.id);
                openEditBankModal(id);
            }

            if (deleteBtn) {
                const id = Number(deleteBtn.dataset.id);
                deleteBank(id);
            }
        });
    }

    // =========================================================
    // التشغيل الأول
    // =========================================================
    renderBanks();
    renumberRows();

});