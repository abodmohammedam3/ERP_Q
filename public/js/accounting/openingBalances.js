document.addEventListener('DOMContentLoaded', function () {

    // ============================================================
    // المراجع الأساسية
    // ============================================================
    const obForm         = document.getElementById('obForm');
    const addEditModalEl = document.getElementById('addEditModal');
    const addEditTitle   = document.getElementById('addEditTitle');
    const editIdInput    = document.getElementById('editId');
    const formType       = document.getElementById('formType');
    const linesBody      = document.getElementById('linesBody');
    const lineTemplate   = document.getElementById('lineRowTemplate');
    const btnAddLine     = document.getElementById('btnAddLine');

    const tableBody      = document.getElementById('obTableBody');
    const rowTemplate    = document.getElementById('obRowTemplate');
    const noDataRow      = document.getElementById('noDataRow');

    const btnAdd         = document.getElementById('btnAddOpeningBalance');
    const btnPrint       = document.getElementById('btnPrintOpeningBalances');
    const searchInput    = document.getElementById('searchInput');
    const btnClearSearch = document.getElementById('btnClearSearch');
    const currentType    = document.getElementById('currentType');
    const tabs           = document.querySelectorAll('#obTabs .nav-link');

    const deleteModalEl  = document.getElementById('deleteBoxModal');
    const deleteIdInput  = document.getElementById('deleteId');
    const deleteTarget   = document.getElementById('deleteTargetName');
    const btnDeleteOk    = document.getElementById('deleteBoxConfirmBtn');
    const btnDeleteCancel= document.getElementById('deleteBoxCancelBtn');

    const totalDebitEl   = document.getElementById('totalDebit');
    const totalCreditEl  = document.getElementById('totalCredit');
    const totalNetEl     = document.getElementById('totalNet');

    // ============================================================
    // تهيئة المودلات
    // ============================================================
    const addEditModal = new bootstrap.Modal(addEditModalEl);

    let lineCounter = 0;
    let searchTimer = null;

    // ============================================================
    // فتح مودل الإضافة
    // ============================================================
    btnAdd.addEventListener('click', function () {
        addEditTitle.innerText = 'إضافة رصيد افتتاحي';
        editIdInput.value = '';
        obForm.reset();
        linesBody.innerHTML = '';
        lineCounter = 0;

        formType.value = currentType.value || 'CASH';

        addLine();
        addEditModal.show();
    });

    // ============================================================
    // إعادة تعيين المودل عند الإغلاق
    // ============================================================
    addEditModalEl.addEventListener('hidden.bs.modal', function () {
        obForm.reset();
        editIdInput.value = '';
        linesBody.innerHTML = '';
        lineCounter = 0;
    });

    // ============================================================
    // إضافة سطر جديد
    // ============================================================
    function addLine(data = {}) {
        lineCounter++;

        const clone = lineTemplate.content.cloneNode(true);
        const row   = clone.querySelector('tr');

        row.querySelector('.line-number').innerText = lineCounter;

        const select = row.querySelector('.line-account');
        const debit  = row.querySelector('.line-debit');
        const credit = row.querySelector('.line-credit');
        const notes  = row.querySelector('.line-notes');

        select.name = `lines[${lineCounter}][account_id]`;
        debit.name  = `lines[${lineCounter}][debit]`;
        credit.name = `lines[${lineCounter}][credit]`;
        notes.name  = `lines[${lineCounter}][notes]`;

        if (data.account_id) select.value = data.account_id;
        if (data.debit)      debit.value  = data.debit;
        if (data.credit)     credit.value = data.credit;
        if (data.notes)      notes.value  = data.notes;

        row.querySelector('.btn-remove-line').addEventListener('click', function () {
            row.remove();
            renumberLines();
        });

        linesBody.appendChild(clone);

        // التمرير إلى آخر صف تمت إضافته
        scrollToLastLine();
    }

    // ============================================================
    // التمرير إلى آخر سطر في جدول الأسطر
    // ============================================================
    function scrollToLastLine() {
        const lastRow = linesBody.querySelector('tr:last-child');
        if (lastRow) {
            lastRow.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }
    }

    // ============================================================
    // زر إضافة سطر
    // ============================================================
    btnAddLine.addEventListener('click', function () {
        addLine();
    });

    // ============================================================
    // إعادة ترقيم الأسطر
    // ============================================================
    function renumberLines() {
        const rows = linesBody.querySelectorAll('tr');
        rows.forEach((row, i) => {
            row.querySelector('.line-number').innerText = i + 1;
        });
        lineCounter = rows.length;
    }

    // ============================================================
    // فتح مودل التعديل
    // ============================================================
    function openEditModal(id) {
        addEditTitle.innerText = 'تعديل رصيد افتتاحي';

        fetch(`/opening-balances/${id}/edit`)
            .then(res => res.json())
            .then(data => {
                editIdInput.value = data.id;
                formType.value    = data.type;

                linesBody.innerHTML = '';
                lineCounter = 0;

                data.lines.forEach(line => addLine(line));

                addEditModal.show();
            })
            .catch(() => alert('حدث خطأ أثناء جلب البيانات'));
    }

    // ============================================================
    // جدول العرض - تحميل وعرض
    // ============================================================
    function loadTable(search = '') {
        const type = currentType.value;
        fetch(`/opening-balances/list?type=${type}&search=${encodeURIComponent(search)}`)
            .then(res => res.json())
            .then(data => {
                renderTable(data.rows || data);
                updateTotals(data.totals || {});
            })
            .catch(() => console.error('فشل تحميل البيانات'));
    }

    function renderTable(data) {
        // إزالة الصفوف القديمة (ما عدا صف لا توجد بيانات)
        tableBody.querySelectorAll('tr:not(#noDataRow)').forEach(r => r.remove());

        if (!data || !data.length) {
            noDataRow.style.display = '';
            return;
        }

        noDataRow.style.display = 'none';

        data.forEach((row, i) => {
            const clone = rowTemplate.content.cloneNode(true);
            const tr    = clone.querySelector('tr');

            tr.querySelector('.row-index').innerText        = i + 1;
            tr.querySelector('.row-account-code').innerText = row.account_code;
            tr.querySelector('.row-account-name').innerText = row.account_name;
            tr.querySelector('.row-debit').innerText        = formatNumber(row.debit);
            tr.querySelector('.row-credit').innerText       = formatNumber(row.credit);
            tr.querySelector('.row-net').innerText          = formatNumber(row.net);
            tr.querySelector('.row-notes').innerText        = row.notes || '';

            tr.querySelector('.btn-edit-row').dataset.id   = row.id;
            tr.querySelector('.btn-delete-row').dataset.id = row.id;

            tableBody.appendChild(clone);
        });
    }

    // ============================================================
    // ربط أزرار الجدول (Event Delegation)
    // ============================================================
    tableBody.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.btn-edit-row');
        if (editBtn) {
            openEditModal(editBtn.dataset.id);
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-row');
        if (deleteBtn) {
            openDeleteModal(deleteBtn.dataset.id);
        }
    });

    // ============================================================
    // مودل الحذف
    // ============================================================
    function openDeleteModal(id) {
        deleteIdInput.value = id;
        deleteTarget.innerText = '';
        deleteModalEl.classList.add('show');
    }

    function closeDeleteModal() {
        deleteModalEl.classList.remove('show');
        deleteIdInput.value = '';
        deleteTarget.innerText = '';
    }

    btnDeleteCancel.addEventListener('click', closeDeleteModal);

    btnDeleteOk.addEventListener('click', function () {
        const id = deleteIdInput.value;
        if (!id) return;

        fetch(`/opening-balances/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(() => {
            closeDeleteModal();
            loadTable(searchInput.value);
        })
        .catch(() => alert('حدث خطأ أثناء الحذف'));
    });

    // ============================================================
    // البحث
    // ============================================================
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            loadTable(this.value);
        }, 300);
    });

    btnClearSearch.addEventListener('click', function () {
        searchInput.value = '';
        loadTable('');
        searchInput.focus();
    });

    // ============================================================
    // التابات
    // ============================================================
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function () {
            currentType.value = this.dataset.type;
            loadTable(searchInput.value);
        });
    });

    // ============================================================
    // حفظ النموذج
    // ============================================================
    obForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('/opening-balances/save', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                addEditModal.hide();
                loadTable(searchInput.value);
            } else {
                alert(data.message || 'حدث خطأ');
            }
        })
        .catch(() => alert('حدث خطأ أثناء الحفظ'));
    });

    // ============================================================
    // الإجماليات
    // ============================================================
    function updateTotals(totals) {
        totalDebitEl.innerText  = formatNumber(totals.debit  || 0);
        totalCreditEl.innerText = formatNumber(totals.credit || 0);
        totalNetEl.innerText    = formatNumber(totals.net    || 0);
    }

    // ============================================================
    // تنسيق الأرقام
    // ============================================================
    function formatNumber(value) {
        const n = parseFloat(value) || 0;
        return n.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // ============================================================
    // تحميل أولي
    // ============================================================
    loadTable();

});