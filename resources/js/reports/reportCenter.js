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
    pickerAccounts: null,        // الحسابات المؤهلة — تُحمَّل مرة واحدة
    pickerParents: null,         // الآباء القابلون للاختيار (من API) — مرة واحدة
    pickerTree: null,            // كامل الشجرة (للتحقق من المخزون)
    pickerPromise: null,         // وعد التحميل الجاري (منع الطلبات المكرّرة)
    pickerTargetId: null,        // الحقل المخفي المستهدف للاختيار
    pickerMode: 'all',           // parent | child | all
    pickerScope: null,           // IDs أبناء الأب (لوضع child)
    pickerScopeKey: null,        // مفتاح النطاق لتمييز الكاش
    pickerIsOpen: false,         // هل المودال مفتوح حالياً؟
    pickerSelectionMade: false,  // هل اختار المستخدم حساباً في آخر جلسة فتح؟
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
        placeholder.textContent = filter.placeholder
            ?? (filter.type === 'item' ? 'اختر الصنف...' : 'الكل');
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
    const placeholder = filter.placeholder
        ?? (filter.type === 'item' ? 'اختر الصنف...' : 'الكل');

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
        if (filter.hidden) return;

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
        wrapper.className = col + ' rc-filter-col';
        wrapper.innerHTML = `
            <label class="form-label small mb-1" for="${id}">${rcEscape(filter.label)}</label>
            ${control}`;

        container.appendChild(wrapper);

        // ربط أحداث لوحة المفاتيح بعد إدراج DOM (لحقول اختيار الحساب فقط)
        if (filter.type === 'account') {
            const textInput = wrapper.querySelector(`#${id}-text`);
            if (textInput) {
                const pickerMode = filter.mode === 'parent' || filter.key === 'account_parent'
                    ? 'parent'
                    : (filter.mode === 'child' ? 'child' : 'all');
                rcAttachPickerFieldEvents(textInput, id, pickerMode);
            }
        }
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
        if (filter.hidden) return;

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
                    <span class="rc-kpi-value ${rcAmountClass(column || { key }, value)}">${rcFormatMoney(value)}</span>
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

function rcAmountClass(column, value) {
    // حماية من null/undefined/''/صفر (نص أو رقم)
    if (value === null || value === undefined || value === '') return '';
    const num = Number(value);
    if (isNaN(num) || num === 0) return '';

    // R2/M5: الأولوية لحقل color الصريح (metadata من تعريف التقرير)
    if (column.color === 'red') return 'amount-red';
    if (column.color === 'green') return 'amount-green';

    const key = column.key;
    if (key === 'debit') return num > 0 ? 'amount-debit' : '';
    if (key === 'credit') return num > 0 ? 'amount-credit' : '';
    if (key === 'balance') return num < 0 ? 'amount-neg' : 'amount-pos';
    return '';
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

    // M2: سقف عرض للجدول (500) — الفلترة/الترتيب على الكل ثم القصّ لتسريع الرسم
    const RC_MAX_TABLE_ROWS = 500;
    const allRows = rcSortedRows();
    const totalRows = allRows.length;
    const rows = totalRows > RC_MAX_TABLE_ROWS
        ? allRows.slice(0, RC_MAX_TABLE_ROWS)
        : allRows;

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
                            `<td class="${rcAlignClass(column)} ${rcAmountClass(column, row[column.key])}">${rcFormatCell(column, row[column.key])}</td>`
                    )
                    .join('')}</tr>`
            )
            .join('');

        // M2: رسالة سقف العرض — تحت الجدول (العدّاد في rcRenderPagination يبقى الكامل)
        if (totalRows > RC_MAX_TABLE_ROWS) {
            body.innerHTML += `<tr><td colspan="${RC.columns.length}" class="text-center text-muted small py-2">
                تُعرض أول ${RC_MAX_TABLE_ROWS} من ${totalRows} نتيجة — طبّق فلاتر للوصول لبقية الصفوف.
                (الطباعة والتصدير يشملان كل الصفوف)
            </td></tr>`;
        }
    }

    // ── تذييل الإجماليات ──
    const hasFooter = RC.columns.some((column) => column.footer && column.footer !== 'none');

    if (!hasFooter) {
        foot.innerHTML = '';
        return;
    }

    // B: عدد الأعمدة غير الرقمية الرائدة — خانة "الإجمالي" تجمعها
    let labelSpan = 0;
    while (
        labelSpan < RC.columns.length &&
        RC.columns[labelSpan].type !== 'money' &&
        RC.columns[labelSpan].type !== 'number'
    ) {
        labelSpan++;
    }

    const labelCell = labelSpan > 0
        ? `<td colspan="${labelSpan}" class="text-end fw-bold">الإجمالي</td>`
        : '';

    foot.innerHTML = `<tr class="rc-tfoot">${labelCell}${RC.columns
        .slice(labelSpan)
        .map((column) => {
            if (!column.footer || column.footer === 'none') {
                return '<td></td>';
            }

            let value = RC.totals[column.key];

            if (value === undefined || value === null) {
                // M3: footer='last' بلا totals (وضع متعدد كشف الحساب)
                // مطابقة لسلوك print.blade.php ("—")
                return column.footer === 'last' ? '<td>—</td>' : '<td></td>';
            }

            const formatted = column.footer === 'sum' && column.type === 'number'
                ? rcFormatNumber(value)
                : rcFormatMoney(value);

            return `<td class="${rcAlignClass(column)} fw-bold ${rcAmountClass(column, value)}">${formatted}</td>`;
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

    // البحث السريع داخل النتائج المعروضة (M2: debounce 250ms — نفس نمط rcPickerSearchTimer)
    const searchInput = document.getElementById('rcQuickSearch');
    if (searchInput) {
        let rcQuickSearchTimer = null;

        searchInput.addEventListener('input', () => {
            clearTimeout(rcQuickSearchTimer);
            rcQuickSearchTimer = setTimeout(() => {
                RC.quickSearch = searchInput.value.trim();
                rcRenderTable();
                rcRenderPagination();
            }, 250);
        });
    }

    // ── مودال اختيار الحساب: فتح/مسح (تفويض — يتحمل إعادة بناء الفلاتر) ──
    document.getElementById('rcFilters').addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-picker-open]');

        if (openTrigger) {
            const hiddenId = openTrigger.dataset.pickerOpen;
            const mode = openTrigger.dataset.pickerMode || 'all';
            // نمرّر ما كتبه المستخدم ليُطبَّق كفلترة أولية داخل المودال (يتطابق مع مسار Enter)
            const textInput = openTrigger.matches('input[type="text"]')
                ? openTrigger
                : document.getElementById(`${hiddenId}-text`);

            rcOpenAccountPicker(hiddenId, mode, textInput ? textInput.value : '');
            return;
        }

        const clearTrigger = event.target.closest('[data-picker-clear]');

        if (clearTrigger) {
            const hiddenId = clearTrigger.dataset.pickerClear;
            // AC-E3 / BR-E10: المسح يفتح القفل أيضاً
            rcCancelPicker(hiddenId);
        }
    });

    // ── مودال الدليل: البحث (مرة واحدة — المودال ثابت في DOM) ──
    const rcPickerModalEl = document.getElementById('rcAccountPickerModal');

    if (rcPickerModalEl) {
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

        // BR-E10 / AC-E3: إغلاق المودال بـ ESC أو زر X → مسح + فتح القفل
        rcPickerModalEl.addEventListener('hidden.bs.modal', () => {
            RC.pickerIsOpen = false;
            if (RC.pickerTargetId && !RC.pickerSelectionMade) {
                // لم يتم اختيار → إذا الحقل فارغ، أبقِه فارغاً وافتح القفل
                const hidden = document.getElementById(RC.pickerTargetId);
                const text = document.getElementById(`${RC.pickerTargetId}-text`);
                if (hidden && !hidden.value && text) {
                    text.value = '';
                }
                rcUnlockFilters();
            }
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
   - بحث محلي — بلا أي طلب شبكة أثناء الاستخدام
   - يُملأ الحقل المخفي rcf-{key} (رقم الحساب) الذي يقرأه
     rcCollectFilters — فيعمل العرض والطباعة والتصدير كما كانت
   ════════════════════════════════════════════════════════ */

const RC_PICKER_CACHE_KEY = 'rc.picker.v1';
const RC_PICKER_MAX_RENDER = 300;
let rcPickerSearchTimer = null;

/**
 * حقل الفلتر: مخفي (رقم الحساب) + حقل نصي قابل للكتابة يفتح المودال بـ Enter.
 * معرّف الحقل المخفي هو ما يلتقطه rcCollectFilters.
 */
function rcBuildAccountPicker(filter, id) {
    const isParent = filter.mode === 'parent' || filter.key === 'account_parent';
    const isChild = filter.mode === 'child';
    const mode = isParent ? 'parent' : (isChild ? 'child' : 'all');
    const placeholder = isParent
        ? 'اكتب أو اضغط Enter لاختيار الحساب الرئيسي...'
        : (isChild ? 'اكتب أو اضغط Enter لاختيار حساب...' : 'اكتب أو اضغط Enter لاختيار حساباً...');
    const disabled = isChild ? ' disabled' : '';

    return `
        <input type="hidden" id="${id}" value="${rcEscape(filter.value ?? '')}">
        <div class="input-group input-group-sm">
            <input type="text"
                   class="form-control"
                   id="${id}-text"
                   data-picker-open="${id}"
                   data-picker-mode="${mode}"
                   placeholder="${placeholder}"
                   autocomplete="off"${disabled}>
            <button type="button"
                    class="btn btn-outline-secondary"
                    data-picker-open="${id}"
                    data-picker-mode="${mode}"
                    title="اختيار حساب"${disabled}>
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
 * mode: 'parent' (الأبواب + صف الكل) | 'child' (أبناء الأب فقط) | 'all' (الكل)
 * initialSearch: نص أولي يُملأ في حقل بحث المودال (يعكس ما كتبه المستخدم)
 */
async function rcOpenAccountPicker(hiddenId, mode = 'all', initialSearch = '') {
    const modalEl = document.getElementById('rcAccountPickerModal');

    if (!modalEl) return;

    RC.pickerTargetId = hiddenId;
    RC.pickerSelectionMade = false; // إعادة تعيين قبل كل فتح
    RC.pickerIsOpen = true;

    // وضع child يتطلب أباً مختاراً — تقييد بالدليل المؤهل تحته فقط
    if (mode === 'child') {
        const parent = rcReadParentChoice();
        const scope = rcChildrenOfParent(parent);

        if (!scope || scope.length === 0) {
            rcToast('اختر الحساب الرئيسي أولاً', 'warning');
            RC.pickerIsOpen = false;
            return;
        }

        RC.pickerMode = 'child';
        RC.pickerScope = scope.map((a) => String(a.accountID));
        RC.pickerScopeKey = String(parent);
    } else {
        RC.pickerMode = mode;
        RC.pickerScope = null;
        RC.pickerScopeKey = null;
    }

    // فتح بحالة نظيفة: تعبئة بحث بما كتبه المستخدم
    const search = document.getElementById('rcPickerSearch');
    if (search) search.value = initialSearch.trim();

    rcRenderPickerLoading();

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();

    await rcLoadPickerAccounts();

    // رسم القائمة (مع تطبيق نص البحث الأولي إن وُجد)
    rcRenderPickerList();

    setTimeout(() => document.getElementById('rcPickerSearch')?.focus(), 250);
}

/**
 * قراءة اختيار الأب الحالي من حقول الفلاتر.
 */
function rcReadParentChoice() {
    const el = document.getElementById('rcf-account_parent');
    return el ? (el.value || '') : '';
}

/**
 * أبناء الأب المباشرين من كاش المودال (الحسابات تحمل accParent).
 */
function rcChildrenOfParent(parent) {
    if (!parent || parent === 'all') return (RC.pickerAccounts || []).slice();

    // API يرسل parent (وليس accParent) — نقبل الاثنين للتوافق
    return (RC.pickerAccounts || []).filter(
        (a) => String(a.parent ?? a.accParent ?? '') === String(parent)
    );
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
            const cached = JSON.parse(raw);
            // الكاش القديم قد يكون مصفوفة مباشرة (قبل التحديث)
            if (Array.isArray(cached)) {
                RC.pickerAccounts = cached;
            } else {
                RC.pickerAccounts = cached.accounts || [];
                RC.pickerParents = cached.parents || [];
                RC.pickerTree = cached.tree || [];
            }
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
            // API الجديد يرسل: { success, accounts, parents, eligible, tree, cached_at }
            RC.pickerAccounts = payload.accounts || payload.eligible || payload.data || [];
            RC.pickerParents = payload.parents || [];
            RC.pickerTree = payload.tree || [];

            try {
                sessionStorage.setItem(RC_PICKER_CACHE_KEY, JSON.stringify({
                    accounts: RC.pickerAccounts,
                    parents: RC.pickerParents,
                    tree: RC.pickerTree,
                }));
            } catch {
                /* نتجاهل */
            }

            return RC.pickerAccounts;
        })
        .catch((error) => {
            console.error('[Reports] فشل تحميل حسابات الدليل', error);
            RC.pickerAccounts = [];
            RC.pickerParents = [];
            RC.pickerTree = [];
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

/**
 * رسم القائمة — تصفية محلية بالبحث (بلا شبكة).
 * وضع parent: يعرض الأبواب المباشرين + صف "كل الحسابات" أولاً.
 * وضع child: يعرض أبناء الأب فقط.
 */
function rcRenderPickerList() {
    const body = document.getElementById('rcPickerBody');
    const empty = document.getElementById('rcPickerEmpty');
    const template = document.getElementById('rcPickerRowTemplate');

    if (!body || !template) return;

    const search = (document.getElementById('rcPickerSearch')?.value || '').trim();
    const isParentMode = RC.pickerMode === 'parent';
    const isChildMode = RC.pickerMode === 'child';
    let rows = RC.pickerAccounts || [];

    if (isParentMode) {
        rows = rcPickerParents();
    } else if (isChildMode && RC.pickerScope) {
        const inScope = new Set(RC.pickerScope);
        rows = rows.filter((a) => inScope.has(String(a.accountID)));
    }

    if (search !== '') {
        const q = search.toLowerCase();
        rows = rows.filter(
            (account) =>
                String(account.accCode ?? '').toLowerCase().includes(q) ||
                String(account.accName ?? '').toLowerCase().includes(q)
        );
    }

    body.replaceChildren();

    const fragment = document.createDocumentFragment();

    // صف "كل الحسابات التفصيلية" — أول القائمة في وضع parent (بلا بحث)
    if (isParentMode && search === '') {
        const allRow = template.content.cloneNode(true);
        const allTr = allRow.querySelector('tr');
        const allCode = allRow.querySelector('.rc-picker-code');
        const allName = allRow.querySelector('.rc-picker-name');

        if (allTr) allTr.classList.add('table-success', 'fw-bold');
        if (allCode) allCode.textContent = '★';
        if (allName) allName.textContent = 'كل الحسابات التفصيلية';

        allTr.addEventListener('click', () => rcSelectPickerAll());
        fragment.appendChild(allRow);
    }

    if (empty) empty.style.display = (rows.length === 0 && fragment.childNodes.length === 0) ? '' : 'none';

    rows.slice(0, RC_PICKER_MAX_RENDER).forEach((account) => {
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
 * الأبواب القابلة للاختيار (وضع parent):
 * يستخدم قائمة الآباء من API إن توفرت (أدق — بيانات مباشرة من الخادم).
 * Fallback: اشتقاقها من حقل parent للحسابات المؤهلة.
 */
function rcPickerParents() {
    // أفضل: استخدام بيانات الآباء من API (أسماء وأكواد صحيحة)
    if (RC.pickerParents && RC.pickerParents.length > 0) {
        return RC.pickerParents.slice();
    }

    // Fallback: اشتقاق الآباء من الحسابات المؤهلة (الطريقة القديمة)
    const rows = RC.pickerAccounts || [];
    const byId = new Map();
    const order = [];

    rows.forEach((account) => {
        const pid = account.parent ?? account.accParent ?? null;
        if (pid === null || pid === '') return;

        const key = String(pid);
        if (!byId.has(key)) {
            order.push(key);
            byId.set(key, { accountID: key, accCode: '', accName: '' });
        }
    });

    // الاسم/الكود من أول ابن
    rows.forEach((account) => {
        const pid = String(account.parent ?? account.accParent ?? '');
        if (byId.has(pid) && !byId.get(pid).accCode) {
            byId.get(pid).accCode = account.accCode ?? '';
            byId.get(pid).accName = account.accName ?? '';
        }
    });

    return order.map((id) => byId.get(id));
}

/**
 * اختيار "كل الحسابات التفصيلية" — القيمة 'all' تدخل الحقل المخفي،
 * ويقرأها الخادم (parentRaw === 'all') كنطاق كامل.
 */
function rcSelectPickerAll() {
    const hiddenId = RC.pickerTargetId;
    if (!hiddenId) return;

    const hidden = document.getElementById(hiddenId);
    const text = document.getElementById(`${hiddenId}-text`);

    if (hidden) hidden.value = 'all';
    if (text) text.value = 'كل الحسابات التفصيلية';

    // ث1: تغيير الأب → مسح النطاق (من/إلى) + تفعيل حقول الأبناء
    rcClearChildRange(hiddenId);

    // BR-E8: رفع علامة المخزون (الكل ليس مخزوناً)
    document.body.classList.remove('rc-empty-parent');

    // تسجيل الاختيار + فتح القفل
    RC.pickerSelectionMade = true;
    rcUnlockFilters();

    const modalEl = document.getElementById('rcAccountPickerModal');
    if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

    // AC-E13: التركيز يبقى في الحقل النصي بعد الاختيار
    setTimeout(() => {
        const textInput = document.getElementById(`${hiddenId}-text`);
        if (textInput) textInput.focus();
    }, 50);
}

/**
 * ث1: مسح حقلَي النطاق (from/to) — يُستدعى عند تغيير الأب أو اختيار "الكل".
 */
function rcClearChildRange(parentHiddenId) {
    ['rcf-account_from', 'rcf-account_to'].forEach((childId) => {
        const childHidden = document.getElementById(childId);
        const childText = document.getElementById(`${childId}-text`);

        if (childHidden) {
            childHidden.value = '';
            // إعادة تفعيل حقلي النطاق بعد اختيار الأب
            if (childId !== parentHiddenId) {
                childHidden.removeAttribute('disabled');
                const textInput = document.getElementById(`${childId}-text`);
                const openBtn = childHidden.closest('.input-group')?.querySelector('[data-picker-open]');
                const clearBtn = childHidden.closest('.input-group')?.querySelector('[data-picker-clear]');
                if (textInput) textInput.removeAttribute('disabled');
                if (openBtn) openBtn.removeAttribute('disabled');
                if (clearBtn) clearBtn.removeAttribute('disabled');
            }
        }
        if (childText) childText.value = '';
    });
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

    // BR-E8: فحص المخزون — إذا كان الحساب المختار مخزوناً
    if (rcIsInventoryAccount(account)) {
        document.body.classList.add('rc-empty-parent');
    } else {
        document.body.classList.remove('rc-empty-parent');
    }

    // ث1: تغيير الأب → مسح حقلَي النطاق (from/to) تلقائياً
    if (RC.pickerMode === 'parent') {
        rcClearChildRange(hiddenId);
    }

    // تسجيل الاختيار + فتح القفل
    RC.pickerSelectionMade = true;
    rcUnlockFilters();

    const modalEl = document.getElementById('rcAccountPickerModal');
    if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

    // AC-E13: التركيز يبقى في الحقل النصي بعد الاختيار
    setTimeout(() => {
        const textInput = document.getElementById(`${hiddenId}-text`);
        if (textInput) textInput.focus();
    }, 50);
}

/**
 * تعطيل حقلَي النطاق (from/to) — عند اختيار أب جديد أو إلغاء الاختيار.
 */
function rcDisableChildFields() {
    ['rcf-account_from', 'rcf-account_to'].forEach((childId) => {
        const childHidden = document.getElementById(childId);
        const childText = document.getElementById(`${childId}-text`);
        const openBtn = childHidden?.closest('.input-group')?.querySelector('[data-picker-open]');
        const clearBtn = childHidden?.closest('.input-group')?.querySelector('[data-picker-clear]');

        if (childHidden) { childHidden.value = ''; childHidden.setAttribute('disabled', ''); }
        if (childText) { childText.value = ''; childText.setAttribute('disabled', ''); }
        if (openBtn) openBtn.setAttribute('disabled', '');
        if (clearBtn) clearBtn.setAttribute('disabled', '');
    });
}

/**
 * هل المودال مفتوح حالياً؟ (يُستخدم لمنع حلقة blur/focus)
 */
function rcIsPickerOpen() {
    return RC.pickerIsOpen === true;
}

/**
 * BR-E6: قفل كل حقول الفلتر ما عدا العمود النشط وأزرار الإجراءات.
 * يُضيف كلاساً على body ويُخفّف العناصر المقيّدة عبر CSS.
 */
function rcLockFilters(activeColWrapper) {
    document.body.classList.add('rc-filter-locked');

    // تمييز العمود النشط بكلاس خاص لإعفائه من القفل
    document.querySelectorAll('.rc-filter-col').forEach((col) => {
        col.classList.toggle('rc-active-col', col === activeColWrapper);
    });
}

/**
 * BR-E6: فتح قفل الفلاتر وإزالة كلاس العمود النشط.
 */
function rcUnlockFilters() {
    document.body.classList.remove('rc-filter-locked');
    document.querySelectorAll('.rc-filter-col').forEach((col) => {
        col.classList.remove('rc-active-col');
    });
}

/**
 * AC-E3 / BR-E10: إلغاء اختيار الحقل (ESC أو زر المسح) — يمسح + يفتح القفل.
 */
function rcCancelPicker(hiddenId) {
    if (!hiddenId) return;

    const hidden = document.getElementById(hiddenId);
    const text = document.getElementById(`${hiddenId}-text`);

    if (hidden) hidden.value = '';
    if (text) text.value = '';

    document.body.classList.remove('rc-empty-parent');
    RC.pickerSelectionMade = false;
    rcUnlockFilters();

    // إذا كان الحقل أباً → عطّل حقول النطاق أيضاً
    if (hiddenId === 'rcf-account_parent') {
        rcDisableChildFields();
    }
}

/**
 * ربط أحداث لوحة المفاتيح والفأرة بحقل الاختيار النصي.
 * AC-E1: Enter → فتح المودال بنص الحقل فلتراً.
 * AC-E2: Tab أثناء الكتابة → محظور.
 * AC-E3: ESC → مسح + فتح القفل.
 * AC-E6: الكتابة → قفل باقي الحقول.
 * AC-E12: نقرة مزدوجة على حقل ممتلئ → إعادة فتح المودال.
 */
function rcAttachPickerFieldEvents(textInput, hiddenId, mode) {
    if (!textInput) return;

    // البحث عن العمود الحاوي (لتمييزه أثناء القفل)
    const colWrapper = textInput.closest('.rc-filter-col');

    // ── الكتابة: قفل الفلاتر الأخرى ──
    textInput.addEventListener('input', () => {
        if (textInput.value.trim().length > 0) {
            rcLockFilters(colWrapper);
        } else {
            rcUnlockFilters();
        }
    });

    // ── لوحة المفاتيح ──
    textInput.addEventListener('keydown', (e) => {
        // IME support (BR-E11)
        if (e.isComposing) return;

        if (e.key === 'Enter') {
            e.preventDefault();
            rcOpenAccountPicker(hiddenId, mode || 'all', textInput.value.trim());
            return;
        }

        if (e.key === 'Tab' && textInput.value.trim().length > 0) {
            // AC-E2: منع Tab أثناء الكتابة
            e.preventDefault();
            return;
        }

        if (e.key === 'Escape') {
            // AC-E3: ESC يمسح + يفتح القفل
            e.preventDefault();
            rcCancelPicker(hiddenId);
            return;
        }
    });

    // ── نقرة مزدوجة: إعادة الفتح ──
    textInput.addEventListener('dblclick', () => {
        const hidden = document.getElementById(hiddenId);
        if (hidden && hidden.value) {
            rcOpenAccountPicker(hiddenId, mode || 'all', '');
        }
    });

    // ── blur: إبقاء التركيز في الحقل المملوء ما دام المودال مغلقاً (يمنع الحلقة عبر rcIsPickerOpen) ──
    textInput.addEventListener('blur', () => {
        if (textInput.value.length > 0 && !rcIsPickerOpen()) {
            setTimeout(() => textInput.focus(), 0);
        }
    });
}

/**
 * BR-E8: هل الحساب ينتمي لمجموعة المخزون؟
 * يفحص system_key في بيانات الشجرة المخزّرة.
 */
function rcIsInventoryAccount(account) {
    if (!account) return false;

    // فحص مباشر
    if (account.system_key === 'inventory') return true;

    // فحص عبر الشجرة (pickerTree)
    if (RC.pickerTree && RC.pickerTree.length > 0) {
        const byId = new Map(RC.pickerTree.map((a) => [String(a.accountID), a]));
        let current = byId.get(String(account.accountID));
        let hops = 0;
        while (current && hops < 6) {
            if (current.system_key === 'inventory') return true;
            const pid = current.accParent ?? current.parent;
            if (!pid) break;
            current = byId.get(String(pid));
            hops++;
        }
    }

    return false;
}

// ✅ التهيئة الصحيحة — تعمل سواء كان DOM جاهزاً أم لا
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', rcInit);
} else {
    // DOM جاهز بالفعل (Vite module يُحمَّل بعد DOMContentLoaded)
    rcInit();
}