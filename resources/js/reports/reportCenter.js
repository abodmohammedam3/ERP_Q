/**
 * =========================================================
 * مركز التقارير — Report Center
 * =========================================================
 * منطق الشاشة الرئيسية للتقارير:
 *  - بناء الفلاتر ديناميكياً من تعريف التقرير (JSON declaration).
 *  - جلب البيانات عبر /reports/data/{key} مع AbortController.
 *  - رسم الجدول من columns + صف الإجماليات + شرائح KPI.
 *  - ترتيب الأعمدة + بحث سريع + ترقيم صفحات.
 *  - الطباعة (/reports/print/{key}) والتصدير (/reports/export/{key}).
 * لا يحتوي هذا الملف على أي تعريف تقرير — كل شيء من Registry.
 * =========================================================
 */

const RC = {
    config: null,        // window.REPORT_CENTER
    definitions: [],
    activeKey: null,
    sources: null,       // مصادر القوائم المنسدلة (حسابات، أصناف، أنواع...)
    columns: [],
    rows: [],            // صفوف الصفحة الحالية (أصلية)
    totals: {},
    meta: {},
    controller: null,    // AbortController للطلب الحالي
    sort: { key: null, dir: 'asc' },
    quickSearch: '',
};

/* ════════════════════════════════════════════════════════
   1) أدوات مساعدة
   ════════════════════════════════════════════════════════ */

function rcEscape(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function rcFormatMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function rcFormatNumber(value) {
    return Number(value || 0).toLocaleString('en-US', {
        maximumFractionDigits: 3,
    });
}

function rcToast(message, type = 'success') {
    if (typeof window.showSystemToast === 'function') {
        window.showSystemToast(message, type);
    }
}

function rcActiveDef() {
    return RC.definitions.find((d) => d.key === RC.activeKey) || RC.definitions[0] || null;
}

function rcEndpoint(base, key, params = {}) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) {
            query.set(k, v);
        }
    });

    const qs = query.toString();

    return `${base}/${encodeURIComponent(key)}${qs ? `?${qs}` : ''}`;
}

/* ════════════════════════════════════════════════════════
   2) مصادر القوائم المنسدلة (تُحمَّل مرة واحدة)
   ════════════════════════════════════════════════════════ */

async function rcLoadSources() {
    if (RC.sources) return RC.sources;

    try {
        const response = await fetch(RC.config.urls.sources, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        const payload = await response.json();

        RC.sources = payload.sources || {};
    } catch (error) {
        console.error('[Reports] فشل تحميل المصادر', error);
        RC.sources = {};
    }

    return RC.sources;
}

/**
 * تطبيع أي مصدر إلى صيغة {value, text}.
 * يدعم: مصفوفة نصوص (docTypes) ومصفوفة كائنات {id, text} (accounts, items).
 */
function rcSourceOptions(source) {
    const list = (RC.sources || {})[source] || [];

    return list.map((entry) => {
        if (entry !== null && typeof entry === 'object') {
            return {
                value: String(entry.id ?? entry.value ?? ''),
                text: String(entry.text ?? entry.label ?? ''),
            };
        }

        return { value: String(entry), text: String(entry) };
    });
}

/* ════════════════════════════════════════════════════════
   3) رأس الشاشة (العنوان + التصنيف)
   ════════════════════════════════════════════════════════ */

const RC_CATEGORY_LABELS = {
    accounting: 'محاسبة',
    sales: 'مبيعات',
    purchases: 'مشتريات',
    inventory: 'مخزون',
    vouchers: 'سندات',
};

function rcRenderHeader() {
    const def = rcActiveDef();

    if (!def) return;

    document.getElementById('rcReportIcon').className = `bi bi-${def.icon}`;
    document.getElementById('rcReportTitle').textContent = def.title;
    document.getElementById('rcReportDesc').textContent = def.description;

    const badge = document.getElementById('rcReportCategory');
    badge.textContent = RC_CATEGORY_LABELS[def.category] || def.category;
}

/* ════════════════════════════════════════════════════════
   4) بناء الفلاتر ديناميكياً من التعريف
   ════════════════════════════════════════════════════════ */

function rcBuildSelect(filter, id) {
    const options = rcSourceOptions(filter.source);
    const placeholder = filter.type === 'account'
        ? 'اختر الحساب...'
        : filter.type === 'item'
            ? 'اختر الصنف...'
            : 'الكل';

    let html = `<select id="${id}" class="form-select form-select-sm">
                    <option value="">${placeholder}</option>`;

    options.forEach((option) => {
        const selected = String(filter.value ?? '') === option.value ? ' selected' : '';
        html += `<option value="${rcEscape(option.value)}"${selected}>${rcEscape(option.text)}</option>`;
    });

    html += '</select>';

    return html;
}

function rcRenderFilters() {
    const container = document.getElementById('rcFilters');
    const def = rcActiveDef();

    container.innerHTML = '';

    if (!def || !def.filters || def.filters.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-muted small">
                <i class="bi bi-info-circle"></i>
                هذا التقرير لا يتطلب فلاتر — اضغط "عرض" لتحميل البيانات.
            </div>`;

        return;
    }

    def.filters.forEach((filter) => {
        const id = `rcf-${filter.key}`;
        const col = filter.col || 'col-md-3';

        let control = '';

        switch (filter.type) {
            case 'date':
                control = `<input type="date" id="${id}" class="form-control form-control-sm"
                                  value="${rcEscape(filter.value ?? '')}">`;
                break;

            case 'number':
                control = `<input type="number" step="any" id="${id}" class="form-control form-control-sm"
                                  value="${rcEscape(filter.value ?? '')}">`;
                break;

            case 'select':
            case 'account':
            case 'item':
                control = rcBuildSelect(filter, id);
                break;

            default:
                control = `<input type="text" id="${id}" class="form-control form-control-sm"
                                  autocomplete="off"
                                  placeholder="${rcEscape(filter.label)}"
                                  value="${rcEscape(filter.value ?? '')}">`;
        }

        const wrapper = document.createElement('div');
        wrapper.className = col;
        wrapper.innerHTML = `
            <label class="form-label small mb-1" for="${id}">${rcEscape(filter.label)}</label>
            ${control}`;

        container.appendChild(wrapper);
    });

    // أزرار تطبيق / إعادة تعيين
    const actions = document.createElement('div');
    actions.className = 'col-md-2';
    actions.innerHTML = `
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-primary flex-grow-1" id="rcBtnApply">
                <i class="bi bi-funnel"></i> تطبيق
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="rcBtnReset"
                    title="إعادة تعيين الفلاتر">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>`;

    container.appendChild(actions);

    document.getElementById('rcBtnApply').addEventListener('click', () => rcRun(1));
    document.getElementById('rcBtnReset').addEventListener('click', rcResetFilters);

    // Enter في أي حقل نصي/تاريخ → تشغيل التقرير
    container.querySelectorAll('input[type="text"], input[type="date"]').forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                rcRun(1);
            }
        });
    });
}

function rcCollectFilters() {
    const def = rcActiveDef();
    const params = {};

    if (!def || !def.filters) return params;

    def.filters.forEach((filter) => {
        const element = document.getElementById(`rcf-${filter.key}`);

        if (element && element.value !== '') {
            params[filter.key] = element.value;
        }
    });

    return params;
}

function rcResetFilters() {
    const def = rcActiveDef();

    if (!def || !def.filters) return;

    def.filters.forEach((filter) => {
        const element = document.getElementById(`rcf-${filter.key}`);

        if (element) {
            element.value = filter.value ?? '';
        }
    });

    rcRun(1);
}

/* ════════════════════════════════════════════════════════
   5) جلب البيانات وتشغيل التقرير
   ════════════════════════════════════════════════════════ */

function rcSetLoading(isLoading) {
    const button = document.getElementById('rcBtnRun');

    button.disabled = isLoading;
    button.innerHTML = isLoading
        ? '<span class="spinner-border spinner-border-sm me-1"></span> جاري التحميل...'
        : '<i class="bi bi-play-circle"></i> عرض';
}

function rcShowMessage(message) {
    document.getElementById('rcTableHead').innerHTML = '';
    document.getElementById('rcTableFoot').innerHTML = '';
    document.getElementById('rcTableBody').innerHTML = `
        <tr>
            <td class="rc-empty text-center text-muted py-4">
                <i class="bi bi-info-circle fs-5 d-block mb-1"></i>
                ${rcEscape(message)}
            </td>
        </tr>`;
    document.getElementById('rcPagination').innerHTML = '';
    document.getElementById('rcRowCount').textContent = '—';
    document.getElementById('rcTotals').innerHTML = '';
}

async function rcRun(page = 1) {
    const def = rcActiveDef();

    if (!def) return;

    // إلغاء أي طلب سابق
    if (RC.controller) {
        RC.controller.abort();
    }

    RC.controller = new AbortController();

    const params = { ...rcCollectFilters(), page };
    const url = rcEndpoint(RC.config.urls.data, def.key, params);

    rcSetLoading(true);

    try {
        const response = await fetch(url, {
            signal: RC.controller.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });

        const payload = await response.json();

        if (!payload.success) {
            rcShowMessage(payload.message || 'تعذر تنفيذ التقرير');
            rcToast(payload.message || 'تعذر تنفيذ التقرير', 'danger');
            return;
        }

        RC.columns = payload.columns || def.columns || [];
        RC.rows = payload.rows || [];
        RC.totals = payload.totals || {};
        RC.meta = payload.meta || {};
        RC.sort = { key: null, dir: 'asc' };
        RC.quickSearch = '';

        const searchInput = document.getElementById('rcQuickSearch');
        if (searchInput) searchInput.value = '';

        // رسالة إرشادية من التقرير (مثل: اختر الحساب أولاً)
        if (RC.meta.message && RC.rows.length === 0) {
            rcShowMessage(RC.meta.message);
            return;
        }

        rcRenderTotals();
        rcRenderTable();
        rcRenderPagination();
    } catch (error) {
        if (error.name === 'AbortError') return;

        console.error('[Reports]', error);
        rcShowMessage('حدث خطأ أثناء جلب بيانات التقرير');
        rcToast('حدث خطأ أثناء جلب بيانات التقرير', 'danger');
    } finally {
        rcSetLoading(false);
    }
}

/* ════════════════════════════════════════════════════════
   6) شرائح الإجماليات (KPI chips)
   ════════════════════════════════════════════════════════ */

function rcRenderTotals() {
    const container = document.getElementById('rcTotals');
    const entries = Object.entries(RC.totals || {});

    if (entries.length === 0) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = entries
        .map(([key, value]) => {
            const column = RC.columns.find((c) => c.key === key);
            const label = column ? column.label : key;

            return `
                <div class="rc-kpi">
                    <small class="rc-kpi-label">${rcEscape(label)}</small>
                    <span class="rc-kpi-value">${rcFormatMoney(value)}</span>
                </div>`;
        })
        .join('');
}

/* ════════════════════════════════════════════════════════
   7) جدول التقرير
   ════════════════════════════════════════════════════════ */

function rcAlignClass(column) {
    if (column.type === 'money' || column.type === 'number') return 'text-end';
    if (column.type === 'date') return 'text-center';
    return 'text-start';
}

function rcFormatCell(column, value) {
    if (value === null || value === undefined || value === '') {
        return '<span class="text-muted">—</span>';
    }

    switch (column.type) {
        case 'money':
            return rcFormatMoney(value);
        case 'number':
            return rcFormatNumber(value);
        default:
            return rcEscape(value);
    }
}

function rcVisibleRows() {
    if (!RC.quickSearch) return RC.rows;

    const term = RC.quickSearch.toLowerCase();

    return RC.rows.filter((row) =>
        Object.values(row).some((value) =>
            String(value ?? '').toLowerCase().includes(term)
        )
    );
}

function rcSortedRows() {
    const rows = [...rcVisibleRows()];

    if (!RC.sort.key) return rows;

    const column = RC.columns.find((c) => c.key === RC.sort.key);
    const isNumeric = column && (column.type === 'money' || column.type === 'number');
    const dir = RC.sort.dir === 'asc' ? 1 : -1;

    return rows.sort((a, b) => {
        const left = a[RC.sort.key];
        const right = b[RC.sort.key];

        if (isNumeric) {
            return ((Number(left) || 0) - (Number(right) || 0)) * dir;
        }

        return String(left ?? '').localeCompare(String(right ?? ''), 'ar') * dir;
    });
}

function rcRenderTable() {
    const head = document.getElementById('rcTableHead');
    const body = document.getElementById('rcTableBody');
    const foot = document.getElementById('rcTableFoot');

    // ── الرأس (قابل للترتيب) ──
    head.innerHTML = `<tr>${RC.columns
        .map((column) => {
            const active = RC.sort.key === column.key;
            const icon = active
                ? (RC.sort.dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
                : 'bi-arrow-down-up rc-sort-icon';

            return `<th class="${rcAlignClass(column)} rc-sortable"
                        data-sort-key="${rcEscape(column.key)}"
                        title="ترتيب حسب ${rcEscape(column.label)}">
                        ${rcEscape(column.label)}
                        <i class="bi ${icon} ms-1"></i>
                    </th>`;
        })
        .join('')}</tr>`;

    head.querySelectorAll('th[data-sort-key]').forEach((th) => {
        th.addEventListener('click', () => {
            const key = th.dataset.sortKey;

            if (RC.sort.key === key) {
                RC.sort.dir = RC.sort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                RC.sort = { key, dir: 'asc' };
            }

            rcRenderTable();
        });
    });

    // ── الجسم ──
    const rows = rcSortedRows();

    if (rows.length === 0) {
        body.innerHTML = `
            <tr>
                <td class="rc-empty text-center text-muted py-4" colspan="${RC.columns.length}">
                    <i class="bi bi-inbox fs-5 d-block mb-1"></i>
                    لا توجد نتائج
                </td>
            </tr>`;
    } else {
        body.innerHTML = rows
            .map(
                (row) => `<tr>${RC.columns
                    .map(
                        (column) =>
                            `<td class="${rcAlignClass(column)}">${rcFormatCell(column, row[column.key])}</td>`
                    )
                    .join('')}</tr>`
            )
            .join('');
    }

    // ── تذييل الإجماليات ──
    const hasFooter = RC.columns.some((column) => column.footer && column.footer !== 'none');

    if (!hasFooter) {
        foot.innerHTML = '';
        return;
    }

    const lastRow = rows[rows.length - 1] || {};

    foot.innerHTML = `<tr class="rc-tfoot">${RC.columns
        .map((column) => {
            if (!column.footer || column.footer === 'none') {
                return '<td></td>';
            }

            let value = RC.totals[column.key];

            if ((value === undefined || value === null) && column.footer === 'last') {
                value = lastRow[column.key];
            }

            if (value === undefined || value === null) {
                return '<td></td>';
            }

            const formatted = column.footer === 'sum' && column.type === 'number'
                ? rcFormatNumber(value)
                : rcFormatMoney(value);

            return `<td class="${rcAlignClass(column)} fw-bold">${formatted}</td>`;
        })
        .join('')}</tr>`;
}

/* ════════════════════════════════════════════════════════
   8) الترقيم
   ════════════════════════════════════════════════════════ */

function rcRenderPagination() {
    const container = document.getElementById('rcPagination');
    const pagination = RC.meta.pagination;
    const visible = rcVisibleRows().length;

    document.getElementById('rcRowCount').textContent = pagination
        ? `عدد النتائج: ${pagination.total} صفاً`
        : `عدد النتائج: ${visible} صفاً`;

    if (!pagination || pagination.last_page <= 1) {
        container.innerHTML = '';
        return;
    }

    const { current_page: current, last_page: last, per_page: perPage, total } = pagination;
    const from = (current - 1) * perPage + 1;
    const to = Math.min(current * perPage, total);

    // نافذة الصفحات (5 صفحات حول الحالية)
    const start = Math.max(1, Math.min(current - 2, last - 4));
    const end = Math.min(last, start + 4);

    let pages = '';

    for (let page = start; page <= end; page += 1) {
        pages += `
            <li class="page-item ${page === current ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${page}">${page}</a>
            </li>`;
    }

    container.innerHTML = `
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">عرض ${from} – ${to} من ${total}</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item ${current <= 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${current - 1}">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    ${pages}
                    <li class="page-item ${current >= last ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${current + 1}">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>`;

    container.querySelectorAll('a[data-page]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();

            const page = parseInt(link.dataset.page, 10);

            if (!Number.isNaN(page) && page >= 1 && page <= last && page !== current) {
                rcRun(page);
            }
        });
    });
}

/* ════════════════════════════════════════════════════════
   9) الطباعة والتصدير
   ════════════════════════════════════════════════════════ */

function rcPrint() {
    const def = rcActiveDef();

    if (!def) return;

    const url = rcEndpoint(RC.config.urls.print, def.key, rcCollectFilters());

    window.open(url, '_blank', 'noopener');
}

function rcExport() {
    const def = rcActiveDef();

    if (!def) return;

    const url = rcEndpoint(RC.config.urls.export, def.key, rcCollectFilters());

    window.location.href = url;
}

/* ════════════════════════════════════════════════════════
   10) التبديل بين التقارير (بدون إعادة تحميل)
   ════════════════════════════════════════════════════════ */

function rcSwitchReport(key) {
    if (key === RC.activeKey) return;

    RC.activeKey = key;
    RC.sort = { key: null, dir: 'asc' };
    RC.quickSearch = '';

    // تحديث الرابط في المتصفح بدون إعادة تحميل
    const url = new URL(window.location.href);
    url.searchParams.set('report', key);
    window.history.replaceState({}, '', url);

    // تحديث القائمة الجانبية للشاشة
    document.querySelectorAll('.rc-report-item').forEach((button) => {
        button.classList.toggle('active', button.dataset.reportKey === key);
    });

    const searchInput = document.getElementById('rcQuickSearch');
    if (searchInput) searchInput.value = '';

    rcRenderHeader();
    rcRenderFilters();
    rcRun(1);
}

/* ════════════════════════════════════════════════════════
   11) التهيئة
   ════════════════════════════════════════════════════════ */

async function rcInit() {
    RC.config = window.REPORT_CENTER;

    if (!RC.config || !RC.config.definitions || RC.config.definitions.length === 0) {
        console.warn('[Reports] لا توجد تعريفات تقارير في window.REPORT_CENTER');
        return;
    }

    RC.definitions = RC.config.definitions;
    RC.activeKey = RC.config.activeKey || RC.definitions[0].key;

    // أزرار الشريط العلوي
    const btnRun = document.getElementById('rcBtnRun');
    const btnPrint = document.getElementById('rcBtnPrint');
    const btnExport = document.getElementById('rcBtnExport');

    if (btnRun) btnRun.addEventListener('click', () => rcRun(1));
    if (btnPrint) btnPrint.addEventListener('click', rcPrint);
    if (btnExport) btnExport.addEventListener('click', rcExport);

    // قائمة التقارير (التبديل الديناميكي)
    document.querySelectorAll('.rc-report-item').forEach((button) => {
        button.addEventListener('click', () => rcSwitchReport(button.dataset.reportKey));
    });

    // البحث السريع داخل النتائج المعروضة
    const searchInput = document.getElementById('rcQuickSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            RC.quickSearch = searchInput.value.trim();
            rcRenderTable();
            rcRenderPagination();
        });
    }

    rcRenderHeader();

    // تحميل المصادر (حسابات/أصناف/أنواع) ثم بناء الفلاتر ثم التشغيل
    await rcLoadSources();

    rcRenderFilters();
    rcRun(1);
}

// ✅ التهيئة الصحيحة — تعمل سواء كان DOM جاهزاً أم لا
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', rcInit);
} else {
    // DOM جاهز بالفعل (Vite module يُحمَّل بعد DOMContentLoaded)
    rcInit();
}