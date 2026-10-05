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
    sourcesPromise: null, // وعد التحميل الجاري (منع الطلبات المكرّرة)
    columns: [],
    rows: [],            // صفوف الصفحة الحالية (أصلية)
    totals: {},
    meta: {},
    controller: null,    // AbortController للطلب الحالي
    sort: { key: null, dir: 'asc' },
    quickSearch: '',

    // مودال اختيار الحساب (دليل الحسابات) — خاص بهذه الشاشة
    pickerAccounts: null,   // الحسابات المؤهلة — تُحمَّل مرة واحدة
    pickerPromise: null,    // وعد التحميل الجاري (منع الطلبات المكرّرة)
    pickerTargetId: null,   // الحقل المخفي المستهدف للاختيار
    pickerTab: 'ALL',
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

const RC_SOURCES_CACHE_KEY = 'rc.sources.v1';

function rcReadSourcesCache() {
    try {
        const raw = sessionStorage.getItem(RC_SOURCES_CACHE_KEY);

        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function rcWriteSourcesCache(sources) {
    try {
        sessionStorage.setItem(RC_SOURCES_CACHE_KEY, JSON.stringify(sources));
    } catch {
        /* التخزين ممتلئ أو محجوب — نتجاهل بهدوء */
    }
}

/**
 * تحميل مصادر القوائم المنسدلة مرة واحدة فقط.
 * - نسخة فورية من sessionStorage (بلا شبكة) إن وُجدت.
 * - يُعاد الوعد نفسه لكل الاستدعاءات المتزامنة (لا طلبات مكرّرة).
 */
function rcLoadSources() {
    if (RC.sources) return Promise.resolve(RC.sources);

    // تحميل فوري من ذاكرة الجلسة — بلا انتظار شبكة
    const cached = rcReadSourcesCache();

    if (cached) {
        RC.sources = cached;

        return Promise.resolve(RC.sources);
    }

    // تفادي طلبات متوازية مكرّرة (نفس الوعد للجميع)
    if (RC.sourcesPromise) return RC.sourcesPromise;

    RC.sourcesPromise = fetch(RC.config.urls.sources, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((response) => response.json())
        .then((payload) => {
            RC.sources = payload.sources || {};
            rcWriteSourcesCache(RC.sources);

            return RC.sources;
        })
        .catch((error) => {
            console.error('[Reports] فشل تحميل المصادر', error);
            RC.sources = {};

            return RC.sources;
        })
        .finally(() => {
            RC.sourcesPromise = null;
        });

    return RC.sourcesPromise;
}

/**
 * تحديث القوائم المنسدلة بعد وصول المصادر — دون إعادة بناء كل الفلاتر
 * (حفاظاً على القيم التي أدخلها المستخدم).
 */
function rcHydrateSelects() {
    const def = rcActiveDef();

    if (!def || !def.filters) return;

    def.filters.forEach((filter) => {
        if (filter.type !== 'select' && filter.type !== 'item') {
            return;   // نوع account يديره مودال الدليل — لا يحتاج تحميل مصادر
        }

        const element = document.getElementById(`rcf-${filter.key}`);

        if (!element) return;

        // القيمة المختارة حالياً (قد يكون المستخدم قد اختار شيئاً قبل التحميل)
        const selected = element.value || String(filter.value ?? '');
        const options = rcSourceOptions(filter.source);

        element.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = filter.type === 'item'
            ? 'اختر الصنف...'
            : 'الكل';
        element.appendChild(placeholder);

        options.forEach((option) => {
            const opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.text;
            opt.selected = selected === option.value;
            element.appendChild(opt);
        });

        element.value = selected;
    });
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
    const placeholder = filter.type === 'item'
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

            case 'account':
                control = rcBuildAccountPicker(filter, id);
                break;

            case 'select':
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

    // Enter في أي حقل نصي/تاريخ → تشغيل التقرير (خارج حقل المودال)
    container.querySelectorAll('input[type="text"]:not([data-picker-open]), input[type="date"]').forEach((input) => {
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

            // حقل عرض المودال (إن وُجد) يتبع إعادة التعيين
            const pickerText = document.getElementById(`rcf-${filter.key}-text`);
            if (pickerText) pickerText.value = '';
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

    // هيكل تحميل فوري — يُظهر استجابة بصرية قبل وصول البيانات
    rcShowSkeleton();
    rcRun(1);
}

/* ════════════════════════════════════════════════════════
   11) التهيئة
   ════════════════════════════════════════════════════════ */

function rcInit() {
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

    // ── مودال اختيار الحساب: فتح/مسح (تفويض — يتحمل إعادة بناء الفلاتر) ──
    document.getElementById('rcFilters').addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-picker-open]');

        if (openTrigger) {
            rcOpenAccountPicker(openTrigger.dataset.pickerOpen);
            return;
        }

        const clearTrigger = event.target.closest('[data-picker-clear]');

        if (clearTrigger) {
            const hiddenId = clearTrigger.dataset.pickerClear;
            const hidden = document.getElementById(hiddenId);
            const text = document.getElementById(`${hiddenId}-text`);

            if (hidden) hidden.value = '';
            if (text) text.value = '';
        }
    });

    // ── مودال الدليل: التبويبات والبحث (مرة واحدة — المودال ثابت في DOM) ──
    const rcPickerModalEl = document.getElementById('rcAccountPickerModal');

    if (rcPickerModalEl) {
        rcPickerModalEl.querySelectorAll('#rcPickerTabs .nav-link').forEach((tab) => {
            tab.addEventListener('click', () => rcActivatePickerTab(tab.dataset.type));
        });

        document.getElementById('rcPickerSearch')?.addEventListener('input', () => {
            clearTimeout(rcPickerSearchTimer);
            rcPickerSearchTimer = setTimeout(rcRenderPickerList, 150);
        });

        document.getElementById('rcPickerClear')?.addEventListener('click', () => {
            const search = document.getElementById('rcPickerSearch');
            if (search) {
                search.value = '';
                search.focus();
            }
            rcRenderPickerList();
        });
    }

    rcRenderHeader();

    // ── 1) شاشة فورية: عنوان + فلاتر + هيكل تحميل + تشغيل البيانات ──
    //    البيانات والمصادر تُطلبان معاً بالتوازي بدل انتظار أحدهما للآخر.
    rcRenderFilters();
    rcShowSkeleton();
    rcRun(1);

    // ── 2) المصادر: بالتوازي، وتُحدِّث القوائم المنسدلة عند وصولها ──
    rcLoadSources().then(() => {
        if (RC.sources && Object.keys(RC.sources).length > 0) {
            rcHydrateSelects();
        }
    });
}

/**
 * هيكل تحميل مؤقت — يمنع الإحساس بشاشة ميتة/بيضاء أثناء جلب البيانات.
 */
function rcShowSkeleton() {
    const head = document.getElementById('rcTableHead');
    const body = document.getElementById('rcTableBody');
    const foot = document.getElementById('rcTableFoot');
    const def = rcActiveDef();

    const span = (def && def.columns && def.columns.length) || 4;

    head.innerHTML = `<tr>${Array.from({ length: span })
        .map(() => '<th class="rc-skeleton-cell">&nbsp;</th>')
        .join('')}</tr>`;

    foot.innerHTML = '';

    body.innerHTML = Array.from({ length: 5 })
        .map(() => `<tr>${Array.from({ length: span })
            .map(() => '<td class="rc-skeleton-cell">&nbsp;</td>')
            .join('')}</tr>`)
        .join('');
}

/* ════════════════════════════════════════════════════════
   12) مودال اختيار الحساب — دليل الحسابات (خاص بالشاشة)
   ════════════════════════════════════════════════════════
   مودال مستقل تماماً عن النظام الموحّد (shared/lookup):
   - بيانات تُحمَّل مرة واحدة (sessionStorage + كاش الخادم)
   - بحث وتبويبات محلية — بلا أي طلب شبكة أثناء الاستخدام
   - يُملأ الحقل المخفي rcf-{key} (رقم الحساب) الذي يقرأه
     rcCollectFilters — فيعمل العرض والطباعة والتصدير كما كانت
   ════════════════════════════════════════════════════════ */

const RC_PICKER_TAB_TITLES = {
    ALL:      'اختيار حساب من دليل الحسابات',
    CUSTOMER: 'اختيار حساب عميل',
    SUPPLIER: 'اختيار حساب مورد',
    CASH:     'اختيار حساب صندوق',
    BANK:     'اختيار حساب بنك',
};

const RC_PICKER_CACHE_KEY = 'rc.picker.v1';
const RC_PICKER_MAX_RENDER = 300;
let rcPickerSearchTimer = null;

/**
 * حقل الفلتر: مخفي (رقم الحساب) + حقل عرض يفتح المودال.
 * معرّف الحقل المخفي هو ما يلتقطه rcCollectFilters.
 */
function rcBuildAccountPicker(filter, id) {
    return `
        <input type="hidden" id="${id}" value="${rcEscape(filter.value ?? '')}">
        <div class="input-group input-group-sm">
            <input type="text"
                   class="form-control"
                   id="${id}-text"
                   data-picker-open="${id}"
                   placeholder="اضغط لاختيار حساباً من الدليل..."
                   autocomplete="off"
                   readonly>
            <button type="button"
                    class="btn btn-outline-secondary"
                    data-picker-open="${id}"
                    title="اختيار حساب">
                <i class="bi bi-search"></i>
            </button>
            <button type="button"
                    class="btn btn-outline-secondary"
                    data-picker-clear="${id}"
                    title="مسح الاختيار">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>`;
}

/**
 * فتح المودال — تحميل كسول أولاً (مرة واحدة لكل جلسة).
 */
async function rcOpenAccountPicker(hiddenId) {
    const modalEl = document.getElementById('rcAccountPickerModal');

    if (!modalEl) return;

    RC.pickerTargetId = hiddenId;

    // فتح بحالة نظيفة: تبويب الكل + بحث فارغ
    RC.pickerTab = 'ALL';
    rcSyncPickerTabUi();

    const search = document.getElementById('rcPickerSearch');
    if (search) search.value = '';

    rcRenderPickerLoading();

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();

    await rcLoadPickerAccounts();
    rcRenderPickerList();

    setTimeout(() => document.getElementById('rcPickerSearch')?.focus(), 250);
}

/**
 * تحميل الحسابات مرة واحدة فقط:
 * - نسخة فورية من sessionStorage (بلا شبكة).
 * - يُعاد الوعد نفسه لكل الاستدعاءات المتزامنة (منع الطلبات المكرّرة).
 */
function rcLoadPickerAccounts() {
    if (RC.pickerAccounts) return Promise.resolve(RC.pickerAccounts);
    if (RC.pickerPromise) return RC.pickerPromise;

    try {
        const raw = sessionStorage.getItem(RC_PICKER_CACHE_KEY);

        if (raw) {
            RC.pickerAccounts = JSON.parse(raw);
            return Promise.resolve(RC.pickerAccounts);
        }
    } catch {
        /* ذاكرة ممتلئة أو محجوبة — نتجاهل */
    }

    RC.pickerPromise = fetch(RC.config.urls.accounts, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
        .then((response) => response.json())
        .then((payload) => {
            RC.pickerAccounts = payload.data || [];

            try {
                sessionStorage.setItem(RC_PICKER_CACHE_KEY, JSON.stringify(RC.pickerAccounts));
            } catch {
                /* نتجاهل */
            }

            return RC.pickerAccounts;
        })
        .catch((error) => {
            console.error('[Reports] فشل تحميل حسابات الدليل', error);
            RC.pickerAccounts = [];
            return RC.pickerAccounts;
        })
        .finally(() => {
            RC.pickerPromise = null;
        });

    return RC.pickerPromise;
}

function rcRenderPickerLoading() {
    const body = document.getElementById('rcPickerBody');
    const empty = document.getElementById('rcPickerEmpty');
    const template = document.getElementById('rcPickerLoadingTemplate');

    if (!body || !template) return;

    body.replaceChildren(template.content.cloneNode(true));
    if (empty) empty.style.display = 'none';
}

function rcSyncPickerTabUi() {
    const modalEl = document.getElementById('rcAccountPickerModal');
    if (!modalEl) return;

    modalEl.querySelectorAll('#rcPickerTabs .nav-link').forEach((tab) => {
        tab.classList.toggle('active', tab.dataset.type === RC.pickerTab);
    });

    const title = document.getElementById('rcPickerTitle');
    if (title) {
        title.innerHTML = `<i class="bi bi-journal-bookmark text-primary me-2"></i> ${
            RC_PICKER_TAB_TITLES[RC.pickerTab] || RC_PICKER_TAB_TITLES.ALL
        }`;
    }
}

function rcActivatePickerTab(type) {
    RC.pickerTab = type || 'ALL';
    rcSyncPickerTabUi();
    rcRenderPickerList();
}

/**
 * رسم القائمة — تصفية محلية بالتبويب والبحث (بلا شبكة).
 */
function rcRenderPickerList() {
    const body = document.getElementById('rcPickerBody');
    const empty = document.getElementById('rcPickerEmpty');
    const template = document.getElementById('rcPickerRowTemplate');

    if (!body || !template) return;

    const search = (document.getElementById('rcPickerSearch')?.value || '').trim();
    let rows = RC.pickerAccounts || [];

    if (RC.pickerTab !== 'ALL') {
        rows = rows.filter((account) => account.group === RC.pickerTab);
    }

    if (search !== '') {
        rows = rows.filter(
            (account) =>
                String(account.accCode ?? '').includes(search) ||
                String(account.accName ?? '').includes(search)
        );
    }

    body.replaceChildren();

    if (rows.length === 0) {
        if (empty) empty.style.display = '';
        return;
    }

    if (empty) empty.style.display = 'none';

    const fragment = document.createDocumentFragment();
    const visible = rows.slice(0, RC_PICKER_MAX_RENDER);

    visible.forEach((account) => {
        const row = template.content.cloneNode(true);
        const tr = row.querySelector('tr');
        const codeEl = row.querySelector('.rc-picker-code');
        const nameEl = row.querySelector('.rc-picker-name');

        if (codeEl) codeEl.textContent = account.accCode ?? '—';
        if (nameEl) nameEl.textContent = account.accName ?? '—';

        tr.addEventListener('click', () => rcSelectPickerRow(account));

        fragment.appendChild(row);
    });

    // سطر تلميحي عند تجاوز الحد — حفاظاً على سرعة الرسم
    if (rows.length > RC_PICKER_MAX_RENDER) {
        const hint = document.createElement('tr');
        hint.innerHTML = `<td colspan="2" class="text-center text-muted py-2 small">
                يُعرض ${RC_PICKER_MAX_RENDER} من ${rows.length} — اكتب للبحث
            </td>`;
        fragment.appendChild(hint);
    }

    body.appendChild(fragment);
}

/**
 * الاختيار: رقم الحساب في الحقل المخفي + العرض في الحقل الظاهر.
 */
function rcSelectPickerRow(account) {
    const hiddenId = RC.pickerTargetId;
    if (!hiddenId) return;

    const hidden = document.getElementById(hiddenId);
    const text = document.getElementById(`${hiddenId}-text`);

    if (hidden) hidden.value = account.accountID;
    if (text) text.value = `${account.accCode ?? ''} - ${account.accName ?? ''}`.trim();

    const modalEl = document.getElementById('rcAccountPickerModal');
    if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();
}

// ✅ التهيئة الصحيحة — تعمل سواء كان DOM جاهزاً أم لا
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', rcInit);
} else {
    // DOM جاهز بالفعل (Vite module يُحمَّل بعد DOMContentLoaded)
    rcInit();
}