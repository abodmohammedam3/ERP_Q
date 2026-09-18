/* ============================================================
   شاشة قيود اليومية — عرض فقط
   بدون أي HTML في JavaScript
   ============================================================ */

'use strict';

// ─────────────────────────────────────────────────────────────
//  الحالة العامة
// ─────────────────────────────────────────────────────────────
const State = {
    currentPage:  1,
    perPage:      10,
    totalRows:    0,
    lastPage:     1,
    searchTimer:  null,
    listAbort:    null,
    cachedTotals: {
        debit:  0,
        credit: 0,
    },
    currentEntry: null,
};

// ─────────────────────────────────────────────────────────────
//  المسارات
// ─────────────────────────────────────────────────────────────
const API = {
    list: '/operation/accounting/journalEntries/list',
    show: (id) => `/operation/accounting/journalEntries/${id}/show`,
};

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

// ─────────────────────────────────────────────────────────────
//  عناصر الصفحة
// ─────────────────────────────────────────────────────────────
const El = {};

// ─────────────────────────────────────────────────────────────
//  أدوات
// ─────────────────────────────────────────────────────────────
const formatMoney = (v) => Number(v || 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function toast(message, type = 'info') {
    if (typeof showSystemToast === 'function') {
        showSystemToast(message, type);
    } else {
        console.log(`[${type}] ${message}`);
    }
}

// ─────────────────────────────────────────────────────────────
//  تحميل القائمة
// ─────────────────────────────────────────────────────────────
async function loadList(page = 1) {
    if (State.listAbort) {
        State.listAbort.abort();
    }
    State.listAbort = new AbortController();

    const params = new URLSearchParams({
        page:      page,
        search:    El.searchInput?.value.trim() || '',
        date_from: El.dateFrom?.value || '',
        date_to:   El.dateTo?.value || '',
        doc_type:  El.docType?.value || '',
    });

    try {
        const response = await fetch(`${API.list}?${params}`, {
            headers: { 'Accept': 'application/json' },
            signal:  State.listAbort.signal,
        });

        const json = await response.json();

        if (!json.success) {
            toast(json.message || 'فشل تحميل البيانات', 'danger');
            return;
        }

        State.currentPage = json.pagination.current_page;
        State.lastPage    = json.pagination.last_page;
        State.totalRows   = json.pagination.total;

        State.cachedTotals.debit  = json.totals.debit  || 0;
        State.cachedTotals.credit = json.totals.credit || 0;

        renderTable(json.rows || []);
        renderTotals();
        renderPagination();

    } catch (error) {
        if (error.name === 'AbortError') return;

        console.error('فشل تحميل القيود:', error);
        toast('تعذّر تحميل البيانات', 'danger');
    }
}

// ─────────────────────────────────────────────────────────────
//  عرض الجدول — يستخدم <template> فقط
// ─────────────────────────────────────────────────────────────
function renderTable(rows) {
    El.tableBody.querySelectorAll('tr:not(#jeNoDataRow)').forEach((r) => r.remove());

    if (!rows.length) {
        El.noDataRow.style.display = '';
        return;
    }

    El.noDataRow.style.display = 'none';

    const fragment = document.createDocumentFragment();

    rows.forEach((row, index) => {
        fragment.appendChild(buildRow(row, index));
    });

    El.tableBody.appendChild(fragment);
}

function buildRow(row, index) {
    const clone = El.rowTemplate.content.cloneNode(true);
    const tr = clone.querySelector('tr');

    const startIndex = (State.currentPage - 1) * State.perPage + index + 1;

    tr.querySelector('.je-row-index').textContent      = startIndex;
    tr.querySelector('.je-row-no').textContent         = row.entryNo;
    tr.querySelector('.je-row-date').textContent       = row.entryDate;
    tr.querySelector('.je-row-doc-type').textContent   = row.docType || '—';
    tr.querySelector('.je-row-doc-number').textContent = row.docNumber || '—';
    tr.querySelector('.je-row-desc').textContent       = row.description || '—';
    tr.querySelector('.je-row-debit').textContent      = formatMoney(row.debit);
    tr.querySelector('.je-row-credit').textContent     = formatMoney(row.credit);

    tr.querySelector('.je-btn-show').dataset.id = row.id;

    return tr;
}

// ─────────────────────────────────────────────────────────────
//  الإجماليات
// ─────────────────────────────────────────────────────────────
function renderTotals() {
    El.totalCount.textContent  = State.totalRows;
    El.totalDebit.textContent  = formatMoney(State.cachedTotals.debit);
    El.totalCredit.textContent = formatMoney(State.cachedTotals.credit);
}

// ─────────────────────────────────────────────────────────────
//  الترقيم — يستخدم <template> فقط
// ─────────────────────────────────────────────────────────────
function renderPagination() {
    const container = El.pagination;
    if (!container) return;

    container.replaceChildren();

    if (State.lastPage <= 1) return;

    const start = (State.currentPage - 1) * State.perPage + 1;
    const end   = Math.min(State.currentPage * State.perPage, State.totalRows);

    // ─── نص المعلومات ───
    const infoText = El.paginationInfoTemplate.content.cloneNode(true);
    const infoSpan = infoText.querySelector('.pg-info');

    infoSpan.textContent = `عرض ${start} - ${end} من ${State.totalRows}`;

    // ─── قائمة الأزرار ───
    const listClone = El.paginationListTemplate.content.cloneNode(true);
    const list      = listClone.querySelector('.pg-list');

    // زر السابق
    list.appendChild(buildPageButton(
        State.currentPage - 1,
        'prev',
        State.currentPage === 1
    ));

    // أرقام الصفحات
    for (let i = 1; i <= State.lastPage; i++) {
        if (
            i === 1 ||
            i === State.lastPage ||
            (i >= State.currentPage - 2 && i <= State.currentPage + 2)
        ) {
            list.appendChild(buildPageButton(i, 'number', false, i === State.currentPage));
        } else if (i === State.currentPage - 3 || i === State.currentPage + 3) {
            list.appendChild(buildPageButton(i, 'dots'));
        }
    }

    // زر التالي
    list.appendChild(buildPageButton(
        State.currentPage + 1,
        'next',
        State.currentPage === State.lastPage
    ));

    // ─── التجميع ───
    const wrapper = document.createDocumentFragment();
    wrapper.appendChild(infoText);
    wrapper.appendChild(listClone);

    container.appendChild(wrapper);

    // ─── ربط الأحداث ───
    container.querySelectorAll('.pg-btn[data-page]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const page = parseInt(btn.dataset.page);

            if (page < 1 || page > State.lastPage) return;
            if (page === State.currentPage) return;

            State.currentPage = page;
            loadList(page);
        });
    });
}

function buildPageButton(page, type, disabled = false, active = false) {
    const clone = El.paginationItemTemplate.content.cloneNode(true);
    const li    = clone.querySelector('.pg-item');
    const link  = clone.querySelector('.pg-btn');

    // حالة العنصر
    if (disabled) li.classList.add('disabled');
    if (active)   li.classList.add('active');

    // بيانات الصفحة
    if (type === 'number') {
        link.dataset.page = page;
        link.textContent  = page;
    } else if (type === 'dots') {
        li.classList.add('disabled');
        link.textContent = '...';
        link.removeAttribute('data-page');
    } else {
        link.dataset.page = page;
        const icon = clone.querySelector('.pg-icon');
        icon.classList.remove('bi-chevron-right', 'bi-chevron-left');
        icon.classList.add(type === 'prev' ? 'bi-chevron-right' : 'bi-chevron-left');
    }

    return clone;
}

// ─────────────────────────────────────────────────────────────
//  عرض تفاصيل قيد — يستخدم <template>
// ─────────────────────────────────────────────────────────────
async function showEntryDetails(id) {
    try {
        const response = await fetch(API.show(id), {
            headers: { 'Accept': 'application/json' },
        });
        const json = await response.json();

        if (!json.success) {
            toast(json.message || 'فشل جلب التفاصيل', 'danger');
            return;
        }

        State.currentEntry = json.entry;

        renderDetails(json.entry);
        bootstrap.Modal.getOrCreateInstance(El.detailsModal).show();

    } catch (error) {
        console.error(error);
        toast('تعذّر جلب التفاصيل', 'danger');
    }
}

function renderDetails(entry) {
    El.detailsNo.textContent      = `#${entry.entryNo}`;
    El.detailsDate.textContent    = entry.entryDate;
    El.detailsDocType.textContent = entry.docType || '—';
    El.detailsDocNo.textContent   = entry.docNumber || '—';
    El.detailsTotal.textContent   = formatMoney(entry.totalAmount);
    El.detailsDesc.textContent    = entry.description || '—';

    // ─── أسطر التفاصيل ───
    El.detailsBody.replaceChildren();

    const fragment = document.createDocumentFragment();
    let totalDebit      = 0;
    let totalCredit     = 0;
    let totalLocalDebit = 0;
    let totalLocalCredit = 0;

    entry.lines.forEach((line, i) => {
        const clone = El.detailsRowTemplate.content.cloneNode(true);
        const tr = clone.querySelector('tr');

        tr.querySelector('.je-line-index').textContent        = i + 1;
        tr.querySelector('.je-line-code').textContent         = line.accountCode;
        tr.querySelector('.je-line-name').textContent         = line.accountName;
        tr.querySelector('.je-line-currency').textContent     = line.currencyCode || '—';
        tr.querySelector('.je-line-rate').textContent         = line.exchangRate;
        tr.querySelector('.je-line-debit').textContent        = formatMoney(line.debit);
        tr.querySelector('.je-line-credit').textContent       = formatMoney(line.credit);
        tr.querySelector('.je-line-local-debit').textContent  = formatMoney(line.localDebit);
        tr.querySelector('.je-line-local-credit').textContent = formatMoney(line.localCredit);
        tr.querySelector('.je-line-notes').textContent        = line.description || '';

        totalDebit       += line.debit;
        totalCredit      += line.credit;
        totalLocalDebit  += line.localDebit;
        totalLocalCredit += line.localCredit;

        fragment.appendChild(clone);
    });

    El.detailsBody.appendChild(fragment);

    // ─── الإجماليات (من template) ───
    El.detailsFooter.replaceChildren();

    const footerClone = El.detailsFooterTemplate.content.cloneNode(true);

    footerClone.querySelector('.je-foot-debit').textContent        = formatMoney(totalDebit);
    footerClone.querySelector('.je-foot-credit').textContent       = formatMoney(totalCredit);
    footerClone.querySelector('.je-foot-local-debit').textContent  = formatMoney(totalLocalDebit);
    footerClone.querySelector('.je-foot-local-credit').textContent = formatMoney(totalLocalCredit);

    El.detailsFooter.appendChild(footerClone);
}

// ─────────────────────────────────────────────────────────────
//  الطباعة
// ─────────────────────────────────────────────────────────────
function printCurrentList() {
    const rows = collectCurrentRows();

    if (!rows.length) {
        toast('لا توجد بيانات للطباعة', 'warning');
        return;
    }

    const html = buildPrintHTML(rows);
    openPrintWindow(html);
}

function collectCurrentRows() {
    const rows = [];

    El.tableBody.querySelectorAll('tr:not(#jeNoDataRow)').forEach((tr) => {
        rows.push({
            index:       tr.querySelector('.je-row-index')?.textContent || '',
            entryNo:     tr.querySelector('.je-row-no')?.textContent || '',
            entryDate:   tr.querySelector('.je-row-date')?.textContent || '',
            docType:     tr.querySelector('.je-row-doc-type')?.textContent || '',
            docNumber:   tr.querySelector('.je-row-doc-number')?.textContent || '',
            description: tr.querySelector('.je-row-desc')?.textContent || '',
            debit:       tr.querySelector('.je-row-debit')?.textContent || '',
            credit:      tr.querySelector('.je-row-credit')?.textContent || '',
        });
    });

    return rows;
}

function buildPrintHTML(rows) {
    // ⚠️ هذا القالب الكبير للطباعة فقط (window.open يحتاج HTML نصي)
    // لا يمكن استخدام <template> لأن النافذة الجديدة لا تعرف DOM الأصلي.

    const now = new Date().toLocaleString('ar-YE', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });

    const totalDebit  = El.totalDebit.textContent;
    const totalCredit = El.totalCredit.textContent;

    const rowsPerPage = 20;
    const pages       = chunkArray(rows, rowsPerPage);

    const pagesHTML = pages.map((pageRows, pageIndex) => {
        const isLastPage = pageIndex === pages.length - 1;
        const startIndex = pageIndex * rowsPerPage;

        const tableRows = pageRows.map((r, i) => `
            <tr>
                <td>${startIndex + i + 1}</td>
                <td>${r.entryNo}</td>
                <td>${r.entryDate}</td>
                <td>${r.docType}</td>
                <td>${r.docNumber}</td>
                <td class="text-start">${r.description}</td>
                <td class="amount">${r.debit}</td>
                <td class="amount">${r.credit}</td>
            </tr>
        `).join('');

        return `
            <div class="page">
                <div class="header">
                    <h1>قيود اليومية</h1>
                    <div class="meta">
                        <div>التاريخ: ${now}</div>
                        <div>صفحة ${pageIndex + 1} من ${pages.length}</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>رقم القيد</th>
                            <th>التاريخ</th>
                            <th>نوع المستند</th>
                            <th>رقم المستند</th>
                            <th>البيان</th>
                            <th>مدين</th>
                            <th>دائن</th>
                        </tr>
                    </thead>
                    <tbody>${tableRows}</tbody>
                    ${isLastPage ? `
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-start">الإجماليات:</td>
                                <td class="amount">${totalDebit}</td>
                                <td class="amount">${totalCredit}</td>
                            </tr>
                        </tfoot>
                    ` : ''}
                </table>
            </div>
        `;
    }).join('');

    return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قيود اليومية</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; direction: rtl; margin: 0; padding: 0; }
        .page { padding: 10px; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0d6efd; padding-bottom: 6px; margin-bottom: 8px; }
        .header h1 { font-size: 1rem; margin: 0; color: #0d6efd; }
        .header .meta { font-size: 0.68rem; color: #6c757d; text-align: left; }
        table { width: 100%; border-collapse: collapse; font-size: 0.72rem; }
        th, td { border: 1px solid #dee2e6; padding: 4px 5px; text-align: center; }
        th { background: #e7f1ff; color: #0d6efd; }
        td.amount { font-weight: 600; text-align: right; direction: ltr; font-family: 'Consolas', monospace; }
        td.text-start { text-align: right; }
        tfoot td { background: #e7f1ff; font-weight: 700; color: #0d6efd; }
        @media print { .page { padding: 0; } }
    </style>
</head>
<body>
    ${pagesHTML}
    <script>
        window.onload = function () {
            setTimeout(function () { window.print(); }, 400);
        };
    <\/script>
</body>
</html>`;
}

function openPrintWindow(html) {
    const win = window.open('', '_blank', 'width=1000,height=800');
    if (!win) {
        toast('تم منع النافذة المنبثقة. الرجاء السماح بها.', 'danger');
        return;
    }
    win.document.open();
    win.document.write(html);
    win.document.close();
}

function chunkArray(arr, size) {
    const chunks = [];
    for (let i = 0; i < arr.length; i += size) {
        chunks.push(arr.slice(i, i + size));
    }
    return chunks;
}

// ─────────────────────────────────────────────────────────────
//  الفلاتر
// ─────────────────────────────────────────────────────────────
function applyFilters() {
    State.currentPage = 1;
    loadList(1);
}

function resetFilters() {
    if (El.searchInput) El.searchInput.value = '';
    if (El.dateFrom)    El.dateFrom.value = '';
    if (El.dateTo)      El.dateTo.value = '';
    if (El.docType)     El.docType.value = '';

    State.currentPage = 1;
    loadList(1);
}

// ─────────────────────────────────────────────────────────────
//  التهيئة
// ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cacheElements();
    bindGlobalEvents();
    loadList();
});

function cacheElements() {
    // الجدول
    El.tableBody          = document.getElementById('jeTableBody');
    El.rowTemplate        = document.getElementById('jeRowTemplate');
    El.noDataRow          = document.getElementById('jeNoDataRow');
    El.pagination         = document.getElementById('jePagination');

    // قوالب الترقيم
    El.paginationInfoTemplate = document.getElementById('jePaginationInfoTemplate');
    El.paginationListTemplate = document.getElementById('jePaginationListTemplate');
    El.paginationItemTemplate = document.getElementById('jePaginationItemTemplate');

    // الفلاتر
    El.searchInput        = document.getElementById('jeSearch');
    El.dateFrom           = document.getElementById('jeDateFrom');
    El.dateTo             = document.getElementById('jeDateTo');
    El.docType            = document.getElementById('jeDocType');
    El.btnApplyFilters    = document.getElementById('jeBtnApply');
    El.btnResetFilters    = document.getElementById('jeBtnReset');

    // الأزرار
    El.btnPrint           = document.getElementById('btnPrintJournalEntries');

    // الإجماليات
    El.totalCount         = document.getElementById('jeTotalCount');
    El.totalDebit         = document.getElementById('jeTotalDebit');
    El.totalCredit        = document.getElementById('jeTotalCredit');

    // مودل التفاصيل
    El.detailsModal          = document.getElementById('jeDetailsModal');
    El.detailsNo             = document.getElementById('jeDetailsNo');
    El.detailsDate           = document.getElementById('jeDetailsDate');
    El.detailsDocType        = document.getElementById('jeDetailsDocType');
    El.detailsDocNo          = document.getElementById('jeDetailsDocNo');
    El.detailsTotal          = document.getElementById('jeDetailsTotal');
    El.detailsDesc           = document.getElementById('jeDetailsDesc');
    El.detailsBody           = document.getElementById('jeDetailsBody');
    El.detailsFooter         = document.getElementById('jeDetailsFooter');
    El.detailsRowTemplate    = document.getElementById('jeDetailsRowTemplate');
    El.detailsFooterTemplate = document.getElementById('jeDetailsFooterTemplate');
    El.btnPrintDetails       = document.getElementById('jeBtnPrintDetails');
}

function bindGlobalEvents() {
    // زر الطباعة
    El.btnPrint?.addEventListener('click', printCurrentList);

    // الفلاتر
    El.btnApplyFilters?.addEventListener('click', applyFilters);
    El.btnResetFilters?.addEventListener('click', resetFilters);

    // البحث بـ Debounce
    El.searchInput?.addEventListener('input', () => {
        clearTimeout(State.searchTimer);
        State.searchTimer = setTimeout(applyFilters, 500);
    });

    // الفلاتر الفورية
    El.dateFrom?.addEventListener('change', applyFilters);
    El.dateTo?.addEventListener('change', applyFilters);
    El.docType?.addEventListener('change', applyFilters);

    // عرض التفاصيل
    El.tableBody?.addEventListener('click', (e) => {
        const btn = e.target.closest('.je-btn-show');
        if (btn) {
            showEntryDetails(btn.dataset.id);
        }
    });

    // إغلاق المودل بـ ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = bootstrap.Modal.getInstance(El.detailsModal);
            if (modal) modal.hide();
        }
    });
}