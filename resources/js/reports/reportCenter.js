/**
 * =========================================================
 * مركز التقارير — Report Center
 * =========================================================
 * منطق الشاشة الرئيسية للتقارير.
 * JS نقي — لا HTML strings، لا CSS، لا inline styles.
 * كل بناء DOM عبر createElement / textContent.
 * =========================================================
 */

const RC = {
    config: null,
    definitions: [],
    activeKey: null,
    sources: null,
    sourcesPromise: null,
    columns: [],
    rows: [],
    totals: {},
    meta: {},
    controller: null,
    sort: { key: null, dir: 'asc' },
    quickSearch: '',

    pickerAccounts: null,
    pickerParents: null,
    pickerTree: null,
    pickerPromise: null,
    pickerTargetId: null,
    pickerMode: 'all',
    pickerScope: null,
    pickerScopeKey: null,
    pickerIsOpen: false,
    pickerSelectionMade: false,
};

/* ════════════════════════════════════════════════════════
   1) أدوات مساعدة
   ════════════════════════════════════════════════════════ */

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

/**
 * بناء عنصر DOM بأمان — بديل كامل لـ innerHTML.
 */
function rcEl(tag, opts) {
    const el = document.createElement(tag);
    if (!opts) return el;

    if (opts.className) el.className = opts.className;
    if (opts.text !== undefined && opts.text !== null) el.textContent = String(opts.text);
    if (opts.title) el.title = opts.title;

    if (opts.attrs) {
        Object.entries(opts.attrs).forEach(([k, v]) => {
            if (v !== null && v !== undefined) el.setAttribute(k, v);
        });
    }

    if (opts.data) {
        Object.entries(opts.data).forEach(([k, v]) => {
            if (v !== null && v !== undefined) el.dataset[k] = v;
        });
    }

    if (opts.children) {
        opts.children.forEach((child) => {
            if (child) el.appendChild(child);
        });
    }

    if (opts.events) {
        Object.entries(opts.events).forEach(([event, handler]) => {
            el.addEventListener(event, handler);
        });
    }

    return el;
}

function rcIcon(cls) {
    return rcEl('i', { className: `bi ${cls}` });
}

/**
 * بناء رابط الطباعة الموحّد.
 * يمرّر الفلاتر + البحث السريع (_q) للنافذة المنبثقة.
 */
function rcBuildPrintUrl() {
    const def = rcActiveDef();
    if (!def) return null;

    const params = new URLSearchParams(rcCollectFilters());

    // تمرير البحث السريع للطباعة — يُفلتر في Blade
    if (RC.quickSearch) {
        params.set('_q', RC.quickSearch);
    }

    const base = RC.config.urls.print + '/' + encodeURIComponent(def.key);

    return params.toString() ? `${base}?${params.toString()}` : base;
}

function rcOpenPrintPreview() {
    const url = rcBuildPrintUrl();
    if (!url) return;

    window.open(
        url,
        'rc_print_preview',
        'width=1100,height=850,scrollbars=yes,resizable=yes'
    );
}

/* ════════════════════════════════════════════════════════
   2) مصادر القوائم المنسدلة
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
    } catch { /* ignore */ }
}

function rcLoadSources() {
    if (RC.sources) return Promise.resolve(RC.sources);

    const cached = rcReadSourcesCache();
    if (cached) {
        RC.sources = cached;
        return Promise.resolve(RC.sources);
    }

    if (RC.sourcesPromise) return RC.sourcesPromise;

    RC.sourcesPromise = fetch(RC.config.urls.sources, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((r) => r.json())
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
        .finally(() => { RC.sourcesPromise = null; });

    return RC.sourcesPromise;
}

function rcHydrateSelects() {
    const def = rcActiveDef();
    if (!def || !def.filters) return;

    def.filters.forEach((filter) => {
        if (filter.type !== 'select' && filter.type !== 'item') return;

        const element = document.getElementById(`rcf-${filter.key}`);
        if (!element) return;

        const selected = element.value || String(filter.value ?? '');
        const options = rcSourceOptions(filter.source);

        const placeholder = rcEl('option', {
            text: filter.placeholder ?? (filter.type === 'item' ? 'اختر الصنف...' : 'الكل'),
        });
        placeholder.value = '';

        const opts = options.map((option) => {
            const opt = rcEl('option', { text: option.text });
            opt.value = option.value;
            if (selected === option.value) opt.selected = true;
            return opt;
        });

        element.replaceChildren(placeholder, ...opts);
        element.value = selected;
    });
}

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
   3) رأس الشاشة
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
   4) بناء الفلاتر
   ════════════════════════════════════════════════════════ */

function rcBuildSelect(filter, id) {
    const options = rcSourceOptions(filter.source);
    const placeholder = filter.placeholder ?? (filter.type === 'item' ? 'اختر الصنف...' : 'الكل');

    const select = rcEl('select', { className: 'form-select form-select-sm' });
    select.id = id;

    const ph = rcEl('option', { text: placeholder });
    ph.value = '';

    const opts = options.map((option) => {
        const opt = rcEl('option', { text: option.text });
        opt.value = option.value;
        if (String(filter.value ?? '') === option.value) opt.selected = true;
        return opt;
    });

    select.appendChild(ph);
    opts.forEach((o) => select.appendChild(o));

    return select;
}

function rcRenderFilters() {
    const container = document.getElementById('rcFilters');
    const def = rcActiveDef();

    container.replaceChildren();

    if (!def || !def.filters || def.filters.length === 0) {
        const info = rcEl('div', {
            className: 'col-12 text-muted small',
            children: [
                rcIcon('bi-info-circle'),
                rcEl('span', { text: ' هذا التقرير لا يتطلب فلاتر — اضغط "عرض" لتحميل البيانات.' }),
            ],
        });
        container.appendChild(info);
        return;
    }

    def.filters.forEach((filter) => {
        if (filter.hidden) return;

        const id = `rcf-${filter.key}`;
        const col = filter.col || 'col-md-3';

        let control;

        switch (filter.type) {
            case 'date': {
                control = rcEl('input', { className: 'form-control form-control-sm' });
                control.type = 'date';
                control.id = id;
                control.value = filter.value ?? '';
                break;
            }
            case 'number': {
                control = rcEl('input', { className: 'form-control form-control-sm' });
                control.type = 'number';
                control.step = 'any';
                control.id = id;
                control.value = filter.value ?? '';
                break;
            }
            case 'account':
                control = rcBuildAccountPicker(filter, id);
                break;
            case 'select':
            case 'item':
                control = rcBuildSelect(filter, id);
                break;
            default: {
                control = rcEl('input', {
                    className: 'form-control form-control-sm',
                    attrs: { autocomplete: 'off', placeholder: filter.label },
                });
                control.type = 'text';
                control.id = id;
                control.value = filter.value ?? '';
            }
        }

        const label = rcEl('label', {
            className: 'form-label small mb-1',
            text: filter.label,
        });
        label.setAttribute('for', id);

        const wrapper = rcEl('div', {
            className: col + ' rc-filter-col',
            children: [label, control],
        });

        container.appendChild(wrapper);

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

    const applyBtn = rcEl('button', {
        className: 'btn btn-sm btn-primary flex-grow-1',
        children: [rcIcon('bi-funnel'), rcEl('span', { text: ' تطبيق' })],
    });
    applyBtn.type = 'button';
    applyBtn.id = 'rcBtnApply';
    applyBtn.addEventListener('click', () => rcRun(1));

    const resetBtn = rcEl('button', {
        className: 'btn btn-sm btn-outline-secondary',
        title: 'إعادة تعيين الفلاتر',
        children: [rcIcon('bi-x-lg')],
    });
    resetBtn.type = 'button';
    resetBtn.id = 'rcBtnReset';
    resetBtn.addEventListener('click', rcResetFilters);

    const actionRow = rcEl('div', {
        className: 'd-flex gap-1',
        children: [applyBtn, resetBtn],
    });

    container.appendChild(rcEl('div', {
        className: 'col-md-2',
        children: [actionRow],
    }));

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
            const pickerText = document.getElementById(`rcf-${filter.key}-text`);
            if (pickerText) pickerText.value = '';
        }
    });

    rcRun(1);
}

/* ════════════════════════════════════════════════════════
   5) جلب البيانات
   ════════════════════════════════════════════════════════ */

function rcSetLoading(isLoading) {
    const button = document.getElementById('rcBtnRun');
    button.disabled = isLoading;

    if (isLoading) {
        const spinner = rcEl('span', { className: 'spinner-border spinner-border-sm me-1' });
        const text = rcEl('span', { text: ' جاري التحميل...' });
        button.replaceChildren(spinner, text);
    } else {
        button.replaceChildren(
            rcIcon('bi-play-circle'),
            rcEl('span', { text: ' عرض' })
        );
    }
}

function rcShowMessage(message) {
    document.getElementById('rcTableHead').replaceChildren();
    document.getElementById('rcTableFoot').replaceChildren();

    const body = document.getElementById('rcTableBody');
    const icon = rcIcon('bi-info-circle fs-5 d-block mb-1');
    const text = rcEl('span', { text: message });
    const cell = rcEl('td', {
        className: 'rc-empty text-center text-muted py-4',
        children: [icon, text],
    });
    body.replaceChildren(rcEl('tr', { children: [cell] }));

    document.getElementById('rcPagination').replaceChildren();
    document.getElementById('rcRowCount').textContent = '—';
    document.getElementById('rcTotals').replaceChildren();
}

async function rcRun(page = 1) {
    const def = rcActiveDef();
    if (!def) return;

    if (RC.controller) RC.controller.abort();
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
   6) شرائح الإجماليات
   ════════════════════════════════════════════════════════ */

function rcRenderTotals() {
    const container = document.getElementById('rcTotals');
    const entries = Object.entries(RC.totals || {});

    if (entries.length === 0) {
        container.replaceChildren();
        return;
    }

    const chips = entries.map(([key, value]) => {
        const column = RC.columns.find((c) => c.key === key);
        const label = column ? column.label : key;

        const labelEl = rcEl('small', {
            className: 'rc-kpi-label',
            text: label,
        });

        const valueEl = rcEl('span', {
            className: `rc-kpi-value ${rcAmountClass(column || { key }, value)}`,
            text: rcFormatMoney(value),
        });

        return rcEl('div', {
            className: 'rc-kpi',
            children: [labelEl, valueEl],
        });
    });

    container.replaceChildren(...chips);
}

/* ════════════════════════════════════════════════════════
   7) الجدول
   ════════════════════════════════════════════════════════ */

function rcAlignClass(column) {
    if (column.type === 'money' || column.type === 'number') return 'text-end';
    if (column.type === 'date') return 'text-center';
    return 'text-start';
}

function rcAmountClass(column, value) {
    if (value === null || value === undefined || value === '') return '';
    const num = Number(value);
    if (isNaN(num) || num === 0) return '';

    if (column.color === 'red') return 'amount-red';
    if (column.color === 'green') return 'amount-green';

    const key = column.key;
    if (key === 'debit') return num > 0 ? 'amount-debit' : '';
    if (key === 'credit') return num > 0 ? 'amount-credit' : '';
    if (key === 'balance') return num < 0 ? 'amount-neg' : 'amount-pos';
    return '';
}

function rcFormatCellText(column, value) {
    if (value === null || value === undefined || value === '') return '—';
    switch (column.type) {
        case 'money':
            return rcFormatMoney(value);
        case 'number':
            return rcFormatNumber(value);
        default:
            return String(value);
    }
}

function rcFormatCell(column, value) {
    if (value === null || value === undefined || value === '') {
        return rcEl('span', { className: 'text-muted', text: '—' });
    }
    return document.createTextNode(rcFormatCellText(column, value));
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

function rcIsStatementMulti() {
    return RC.activeKey === 'account-statement'
        && !RC.sort.key
        && !RC.quickSearch
        && Array.isArray(RC.meta.accounts)
        && RC.meta.accounts.length > 1;
}

function rcBuildDataRow(row) {
    const tds = RC.columns.map((column) => {
        const className = `${rcAlignClass(column)} ${rcAmountClass(column, row[column.key])}`.trim();
        return rcEl('td', {
            className,
            children: [rcFormatCell(column, row[column.key])],
        });
    });

    return rcEl('tr', { children: tds });
}

function rcBuildGroupHeaderRow(acc) {
    const folderIcon = rcIcon('bi-folder2-open me-2');
    const label = rcEl('span', {
        className: 'fw-bold',
        children: [folderIcon, document.createTextNode(`${acc.code} - ${acc.name}`)],
    });

    const meta = rcEl('span', {
        className: 'text-muted small',
        text: `${acc.rows_count} حركة — ختامي: ${rcFormatMoney(acc.closing)}`,
    });

    const inner = rcEl('div', {
        className: 'd-flex justify-content-between align-items-center',
        children: [label, meta],
    });

    const td = rcEl('td', { children: [inner] });
    td.setAttribute('colspan', String(RC.columns.length));

    const tr = rcEl('tr', { className: 'rc-statement-group-header', children: [td] });
    return tr;
}

function rcRenderStatementRows(rows, tbody) {
    const fragment = document.createDocumentFragment();

    if (!rcIsStatementMulti()) {
        rows.forEach((row) => fragment.appendChild(rcBuildDataRow(row)));
        tbody.replaceChildren(fragment);
        return;
    }

    const groups = new Map();
    const order = [];
    for (const row of rows) {
        const id = row._account_id;
        if (!groups.has(id)) { groups.set(id, []); order.push(id); }
        groups.get(id).push(row);
    }

    const accList = Array.isArray(RC.meta.accounts) ? RC.meta.accounts : [];
    const accMap = new Map(accList.map((a) => [a.id, a]));

    for (const id of order) {
        const acc = accMap.get(id);
        if (acc) {
            fragment.appendChild(rcBuildGroupHeaderRow(acc));
        }
        for (const row of groups.get(id)) {
            fragment.appendChild(rcBuildDataRow(row));
        }
    }

    tbody.replaceChildren(fragment);
}

function rcBuildTableHead() {
    const thead = document.getElementById('rcTableHead');
    const fragment = document.createDocumentFragment();

    RC.columns.forEach((column) => {
        const active = RC.sort.key === column.key;
        const iconCls = active
            ? (RC.sort.dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
            : 'bi-arrow-down-up rc-sort-icon';

        const icon = rcIcon(iconCls + ' ms-1');
        const label = document.createTextNode(column.label + ' ');

        const th = rcEl('th', {
            className: `${rcAlignClass(column)} rc-sortable`,
            title: `ترتيب حسب ${column.label}`,
            children: [label, icon],
        });
        th.dataset.sortKey = column.key;

        th.addEventListener('click', () => {
            const key = th.dataset.sortKey;
            if (RC.sort.key === key) {
                RC.sort.dir = RC.sort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                RC.sort = { key, dir: 'asc' };
            }
            rcRenderTable();
        });

        fragment.appendChild(th);
    });

    thead.replaceChildren(rcEl('tr', { children: Array.from(fragment.children) }));
}

function rcBuildTableFoot() {
    const foot = document.getElementById('rcTableFoot');
    const hasFooter = RC.columns.some((column) => column.footer && column.footer !== 'none');

    if (!hasFooter) {
        foot.replaceChildren();
        return;
    }

    let labelSpan = 0;
    while (
        labelSpan < RC.columns.length &&
        RC.columns[labelSpan].type !== 'money' &&
        RC.columns[labelSpan].type !== 'number'
    ) {
        labelSpan++;
    }

    const cells = [];

    if (labelSpan > 0) {
        const labelTd = rcEl('td', {
            className: 'text-end fw-bold',
            text: 'الإجمالي',
        });
        labelTd.setAttribute('colspan', String(labelSpan));
        cells.push(labelTd);
    }

    RC.columns.slice(labelSpan).forEach((column) => {
        if (!column.footer || column.footer === 'none') {
            cells.push(rcEl('td'));
            return;
        }

        let value = RC.totals[column.key];

        if (value === undefined || value === null) {
            cells.push(rcEl('td', {
                text: column.footer === 'last' ? '—' : '',
            }));
            return;
        }

        const formatted = column.footer === 'sum' && column.type === 'number'
            ? rcFormatNumber(value)
            : rcFormatMoney(value);

        cells.push(rcEl('td', {
            className: `${rcAlignClass(column)} fw-bold ${rcAmountClass(column, value)}`.trim(),
            text: formatted,
        }));
    });

    foot.replaceChildren(rcEl('tr', { className: 'rc-tfoot', children: cells }));
}

function rcRenderTable() {
    rcBuildTableHead();

    const body = document.getElementById('rcTableBody');

    const RC_MAX_TABLE_ROWS = 500;
    const allRows = rcSortedRows();
    const totalRows = allRows.length;
    const rows = totalRows > RC_MAX_TABLE_ROWS
        ? allRows.slice(0, RC_MAX_TABLE_ROWS)
        : allRows;

    if (rows.length === 0) {
        const icon = rcIcon('bi-inbox fs-5 d-block mb-1');
        const text = document.createTextNode('لا توجد نتائج');
        const td = rcEl('td', {
            className: 'rc-empty text-center text-muted py-4',
            children: [icon, text],
        });
        td.setAttribute('colspan', String(RC.columns.length));
        body.replaceChildren(rcEl('tr', { children: [td] }));
    } else {
        rcRenderStatementRows(rows, body);

        if (totalRows > RC_MAX_TABLE_ROWS) {
            const notice = rcEl('td', {
                className: 'text-center text-muted small py-2',
                text: `تُعرض أول ${RC_MAX_TABLE_ROWS} من ${totalRows} نتيجة — طبّق فلاتر للوصول لبقية الصفوف. (الطباعة والتصدير يشملان كل الصفوف)`,
            });
            notice.setAttribute('colspan', String(RC.columns.length));
            body.appendChild(rcEl('tr', { children: [notice] }));
        }
    }

    rcBuildTableFoot();
}

/* ════════════════════════════════════════════════════════
   8) الترقيم
   ════════════════════════════════════════════════════════ */

function rcRenderPagination() {
    const container = document.getElementById('rcPagination');
    const pagination = RC.meta.pagination;
    const visible = rcVisibleRows().length;

    let counterText;
    if (RC.quickSearch) {
        const totalKnown = pagination ? pagination.total : RC.rows.length;
        counterText = `عدد النتائج (بعد البحث السريع): ${visible} من ${totalKnown} صفاً`;
    } else if (pagination) {
        counterText = `عدد النتائج: ${pagination.total} صفاً`;
    } else {
        counterText = `عدد النتائج: ${visible} صفاً`;
    }
    document.getElementById('rcRowCount').textContent = counterText;

    if (!pagination || pagination.last_page <= 1) {
        container.replaceChildren();
        return;
    }

    const { current_page: current, last_page: last, per_page: perPage, total } = pagination;
    const from = (current - 1) * perPage + 1;
    const to = Math.min(current * perPage, total);

    const start = Math.max(1, Math.min(current - 2, last - 4));
    const end = Math.min(last, start + 4);

    const info = rcEl('small', {
        className: 'text-muted',
        text: `عرض ${from} – ${to} من ${total}`,
    });

    const prevLi = rcEl('li', { className: `page-item ${current <= 1 ? 'disabled' : ''}` });
    const prevLink = rcEl('a', { className: 'page-link', children: [rcIcon('bi-chevron-right')] });
    prevLink.href = '#';
    prevLink.dataset.page = String(current - 1);
    prevLi.appendChild(prevLink);

    const pageLis = [];
    for (let page = start; page <= end; page += 1) {
        const li = rcEl('li', { className: `page-item ${page === current ? 'active' : ''}` });
        const a = rcEl('a', { className: 'page-link', text: String(page) });
        a.href = '#';
        a.dataset.page = String(page);
        li.appendChild(a);
        pageLis.push(li);
    }

    const nextLi = rcEl('li', { className: `page-item ${current >= last ? 'disabled' : ''}` });
    const nextLink = rcEl('a', { className: 'page-link', children: [rcIcon('bi-chevron-left')] });
    nextLink.href = '#';
    nextLink.dataset.page = String(current + 1);
    nextLi.appendChild(nextLink);

    const ul = rcEl('ul', {
        className: 'pagination pagination-sm mb-0',
        children: [prevLi, ...pageLis, nextLi],
    });

    const nav = rcEl('nav', { children: [ul] });

    const wrap = rcEl('div', {
        className: 'd-flex justify-content-between align-items-center flex-wrap gap-2',
        children: [info, nav],
    });

    container.replaceChildren(wrap);

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
    rcOpenPrintPreview();
}

function rcExport() {
    const def = rcActiveDef();
    if (!def) return;
    const url = rcEndpoint(RC.config.urls.export, def.key, rcCollectFilters());
    window.location.href = url;
}

/* ════════════════════════════════════════════════════════
   10) تبديل التقرير
   ════════════════════════════════════════════════════════ */

function rcSwitchReport(key) {
    if (key === RC.activeKey) return;

    RC.activeKey = key;
    RC.sort = { key: null, dir: 'asc' };
    RC.quickSearch = '';

    const url = new URL(window.location.href);
    url.searchParams.set('report', key);
    window.history.replaceState({}, '', url);

    document.querySelectorAll('.rc-report-item').forEach((button) => {
        button.classList.toggle('active', button.dataset.reportKey === key);
    });

    const searchInput = document.getElementById('rcQuickSearch');
    if (searchInput) searchInput.value = '';

    rcRenderHeader();
    rcRenderFilters();
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

    const btnRun = document.getElementById('rcBtnRun');
    const btnPrint = document.getElementById('rcBtnPrint');
    const btnExport = document.getElementById('rcBtnExport');

    if (btnRun) btnRun.addEventListener('click', () => rcRun(1));
    if (btnPrint) btnPrint.addEventListener('click', rcPrint);
    if (btnExport) btnExport.addEventListener('click', rcExport);

    document.querySelectorAll('.rc-report-item').forEach((button) => {
        button.addEventListener('click', () => rcSwitchReport(button.dataset.reportKey));
    });

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

    document.getElementById('rcFilters').addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-picker-open]');
        if (openTrigger) {
            const hiddenId = openTrigger.dataset.pickerOpen;
            const mode = openTrigger.dataset.pickerMode || 'all';
            const textInput = openTrigger.matches('input[type="text"]')
                ? openTrigger
                : document.getElementById(`${hiddenId}-text`);
            rcOpenAccountPicker(hiddenId, mode, textInput ? textInput.value : '');
            return;
        }

        const clearTrigger = event.target.closest('[data-picker-clear]');
        if (clearTrigger) {
            rcCancelPicker(clearTrigger.dataset.pickerClear);
        }
    });

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

        rcPickerModalEl.addEventListener('hidden.bs.modal', () => {
            RC.pickerIsOpen = false;
            if (RC.pickerTargetId && !RC.pickerSelectionMade) {
                const hidden = document.getElementById(RC.pickerTargetId);
                const text = document.getElementById(`${RC.pickerTargetId}-text`);
                if (hidden && !hidden.value && text) {
                    text.value = '';
                }
                rcUnlockFilters();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            if (!RC.activeKey) return;
            e.preventDefault();
            rcOpenPrintPreview();
        }
    });

    rcRenderHeader();
    rcRenderFilters();
    rcShowSkeleton();
    rcRun(1);

    rcLoadSources().then(() => {
        if (RC.sources && Object.keys(RC.sources).length > 0) {
            rcHydrateSelects();
        }
    });
}

function rcShowSkeleton() {
    const head = document.getElementById('rcTableHead');
    const body = document.getElementById('rcTableBody');
    const foot = document.getElementById('rcTableFoot');
    const def = rcActiveDef();

    const span = (def && def.columns && def.columns.length) || 4;

    const ths = Array.from({ length: span }).map(() =>
        rcEl('th', { className: 'rc-skeleton-cell', text: '\u00A0' })
    );
    head.replaceChildren(rcEl('tr', { children: ths }));
    foot.replaceChildren();

    const trs = Array.from({ length: 5 }).map(() => {
        const tds = Array.from({ length: span }).map(() =>
            rcEl('td', { className: 'rc-skeleton-cell', text: '\u00A0' })
        );
        return rcEl('tr', { children: tds });
    });
    body.replaceChildren(...trs);
}

/* ════════════════════════════════════════════════════════
   12) مودال اختيار الحساب
   ════════════════════════════════════════════════════════ */

const RC_PICKER_CACHE_KEY = 'rc.picker.v1';
const RC_PICKER_MAX_RENDER = 300;
let rcPickerSearchTimer = null;

function rcBuildAccountPicker(filter, id) {
    const isParent = filter.mode === 'parent' || filter.key === 'account_parent';
    const isChild = filter.mode === 'child';
    const mode = isParent ? 'parent' : (isChild ? 'child' : 'all');
    const placeholder = isParent
        ? 'اكتب أو اضغط Enter لاختيار الحساب الرئيسي...'
        : (isChild ? 'اكتب أو اضغط Enter لاختيار حساب...' : 'اكتب أو اضغط Enter لاختيار حساباً...');

    const hidden = rcEl('input');
    hidden.type = 'hidden';
    hidden.id = id;
    hidden.value = filter.value ?? '';

    const text = rcEl('input', { className: 'form-control', attrs: { autocomplete: 'off' } });
    text.type = 'text';
    text.id = `${id}-text`;
    text.dataset.pickerOpen = id;
    text.dataset.pickerMode = mode;
    text.placeholder = placeholder;
    if (isChild) text.disabled = true;

    const searchBtn = rcEl('button', {
        className: 'btn btn-outline-secondary',
        title: 'اختيار حساب',
        children: [rcIcon('bi-search')],
    });
    searchBtn.type = 'button';
    searchBtn.dataset.pickerOpen = id;
    searchBtn.dataset.pickerMode = mode;
    if (isChild) searchBtn.disabled = true;

    const clearBtn = rcEl('button', {
        className: 'btn btn-outline-secondary',
        title: 'مسح الاختيار',
        children: [rcIcon('bi-x-lg')],
    });
    clearBtn.type = 'button';
    clearBtn.dataset.pickerClear = id;

    const group = rcEl('div', {
        className: 'input-group input-group-sm',
        children: [text, searchBtn, clearBtn],
    });

    const wrapper = rcEl('div', { children: [hidden, group] });
    return wrapper;
}

async function rcOpenAccountPicker(hiddenId, mode = 'all', initialSearch = '') {
    const modalEl = document.getElementById('rcAccountPickerModal');
    if (!modalEl) return;

    RC.pickerTargetId = hiddenId;
    RC.pickerSelectionMade = false;
    RC.pickerIsOpen = true;

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

    const search = document.getElementById('rcPickerSearch');
    if (search) search.value = initialSearch.trim();

    rcRenderPickerLoading();

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
    modal.show();

    await rcLoadPickerAccounts();
    rcRenderPickerList();

    setTimeout(() => document.getElementById('rcPickerSearch')?.focus(), 250);
}

function rcReadParentChoice() {
    const el = document.getElementById('rcf-account_parent');
    return el ? (el.value || '') : '';
}

function rcChildrenOfParent(parent) {
    if (!parent || parent === 'all') return (RC.pickerAccounts || []).slice();
    return (RC.pickerAccounts || []).filter(
        (a) => String(a.parent ?? '') === String(parent)
    );
}

function rcLoadPickerAccounts() {
    if (RC.pickerAccounts) return Promise.resolve(RC.pickerAccounts);
    if (RC.pickerPromise) return RC.pickerPromise;

    try {
        const raw = sessionStorage.getItem(RC_PICKER_CACHE_KEY);
        if (raw) {
            const cached = JSON.parse(raw);
            if (Array.isArray(cached)) {
                RC.pickerAccounts = cached;
            } else {
                RC.pickerAccounts = cached.accounts || [];
                RC.pickerParents = cached.parents || [];
                RC.pickerTree = cached.tree || [];
            }
            return Promise.resolve(RC.pickerAccounts);
        }
    } catch { /* ignore */ }

    RC.pickerPromise = fetch(RC.config.urls.accounts, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
        .then((r) => r.json())
        .then((payload) => {
            RC.pickerAccounts = payload.accounts || payload.eligible || payload.data || [];
            RC.pickerParents = payload.parents || [];
            RC.pickerTree = payload.tree || [];
            try {
                sessionStorage.setItem(RC_PICKER_CACHE_KEY, JSON.stringify({
                    accounts: RC.pickerAccounts,
                    parents: RC.pickerParents,
                    tree: RC.pickerTree,
                }));
            } catch { /* ignore */ }
            return RC.pickerAccounts;
        })
        .catch((error) => {
            console.error('[Reports] فشل تحميل حسابات الدليل', error);
            RC.pickerAccounts = [];
            RC.pickerParents = [];
            RC.pickerTree = [];
            return RC.pickerAccounts;
        })
        .finally(() => { RC.pickerPromise = null; });

    return RC.pickerPromise;
}

function rcRenderPickerLoading() {
    const body = document.getElementById('rcPickerBody');
    const empty = document.getElementById('rcPickerEmpty');
    if (!body) return;

    const spinner = rcEl('span', { className: 'spinner-border spinner-border-sm me-2' });
    const text = rcEl('span', { text: 'جارٍ التحميل...' });
    const cell = rcEl('td', {
        className: 'text-center text-muted py-3',
        children: [spinner, text],
    });
    cell.setAttribute('colspan', '2');

    body.replaceChildren(rcEl('tr', { children: [cell] }));
    if (empty) empty.style.display = 'none';
}

function rcRenderPickerList() {
    const body = document.getElementById('rcPickerBody');
    const empty = document.getElementById('rcPickerEmpty');
    if (!body) return;

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

    const fragment = document.createDocumentFragment();
    let visibleCount = 0;

    if (isParentMode && search === '') {
        const codeCell = rcEl('td', { className: 'rc-picker-code', text: '★' });
        const nameCell = rcEl('td', { className: 'rc-picker-name', text: 'كل الحسابات التفصيلية' });
        const tr = rcEl('tr', {
            className: 'table-success fw-bold',
            children: [codeCell, nameCell],
        });
        tr.addEventListener('click', () => rcSelectPickerAll());
        fragment.appendChild(tr);
        visibleCount++;
    }

    rows.slice(0, RC_PICKER_MAX_RENDER).forEach((account) => {
        const codeCell = rcEl('td', {
            className: 'rc-picker-code',
            text: account.accCode ?? '—',
        });
        const nameCell = rcEl('td', {
            className: 'rc-picker-name',
            text: account.accName ?? '—',
        });
        const tr = rcEl('tr', {
            children: [codeCell, nameCell],
        });
        tr.addEventListener('click', () => rcSelectPickerRow(account));
        fragment.appendChild(tr);
        visibleCount++;
    });

    if (rows.length > RC_PICKER_MAX_RENDER) {
        const hint = rcEl('td', {
            className: 'text-center text-muted py-2 small',
            text: `يُعرض ${RC_PICKER_MAX_RENDER} من ${rows.length} — اكتب للبحث`,
        });
        hint.setAttribute('colspan', '2');
        fragment.appendChild(rcEl('tr', { children: [hint] }));
    }

    body.replaceChildren(fragment);
    if (empty) empty.style.display = visibleCount === 0 ? '' : 'none';
}

function rcPickerParents() {
    if (RC.pickerParents && RC.pickerParents.length > 0) {
        return RC.pickerParents.slice();
    }

    const rows = RC.pickerAccounts || [];
    const byId = new Map();
    const order = [];

    rows.forEach((account) => {
        const pid = account.parent ?? null;
        if (pid === null || pid === '') return;
        const key = String(pid);
        if (!byId.has(key)) {
            order.push(key);
            byId.set(key, { accountID: key, accCode: '', accName: '' });
        }
    });

    rows.forEach((account) => {
        const pid = String(account.parent ?? '');
        if (byId.has(pid) && !byId.get(pid).accCode) {
            byId.get(pid).accCode = account.accCode ?? '';
            byId.get(pid).accName = account.accName ?? '';
        }
    });

    return order.map((id) => byId.get(id));
}

function rcSelectPickerAll() {
    const hiddenId = RC.pickerTargetId;
    if (!hiddenId) return;

    const hidden = document.getElementById(hiddenId);
    const text = document.getElementById(`${hiddenId}-text`);

    if (hidden) hidden.value = 'all';
    if (text) text.value = 'كل الحسابات التفصيلية';

    rcClearChildRange(hiddenId);
    document.body.classList.remove('rc-empty-parent');

    RC.pickerSelectionMade = true;
    rcUnlockFilters();

    const modalEl = document.getElementById('rcAccountPickerModal');
    if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

    setTimeout(() => {
        const textInput = document.getElementById(`${hiddenId}-text`);
        if (textInput) textInput.focus();
    }, 50);
}

function rcClearChildRange(parentHiddenId) {
    ['rcf-account_from', 'rcf-account_to'].forEach((childId) => {
        const childHidden = document.getElementById(childId);
        const childText = document.getElementById(`${childId}-text`);

        if (childHidden) {
            childHidden.value = '';
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

function rcSelectPickerRow(account) {
    const hiddenId = RC.pickerTargetId;
    if (!hiddenId) return;

    const hidden = document.getElementById(hiddenId);
    const text = document.getElementById(`${hiddenId}-text`);

    if (hidden) hidden.value = account.accountID;
    if (text) text.value = `${account.accCode ?? ''} - ${account.accName ?? ''}`.trim();

    if (rcIsInventoryAccount(account)) {
        document.body.classList.add('rc-empty-parent');
    } else {
        document.body.classList.remove('rc-empty-parent');
    }

    if (RC.pickerMode === 'parent') {
        rcClearChildRange(hiddenId);
    }

    RC.pickerSelectionMade = true;
    rcUnlockFilters();

    const modalEl = document.getElementById('rcAccountPickerModal');
    if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();

    setTimeout(() => {
        const textInput = document.getElementById(`${hiddenId}-text`);
        if (textInput) textInput.focus();
    }, 50);
}

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

function rcIsPickerOpen() {
    return RC.pickerIsOpen === true;
}

function rcLockFilters(activeColWrapper) {
    document.body.classList.add('rc-filter-locked');
    document.querySelectorAll('.rc-filter-col').forEach((col) => {
        col.classList.toggle('rc-active-col', col === activeColWrapper);
    });
}

function rcUnlockFilters() {
    document.body.classList.remove('rc-filter-locked');
    document.querySelectorAll('.rc-filter-col').forEach((col) => {
        col.classList.remove('rc-active-col');
    });
}

function rcCancelPicker(hiddenId) {
    if (!hiddenId) return;

    const hidden = document.getElementById(hiddenId);
    const text = document.getElementById(`${hiddenId}-text`);

    if (hidden) hidden.value = '';
    if (text) text.value = '';

    document.body.classList.remove('rc-empty-parent');
    RC.pickerSelectionMade = false;
    rcUnlockFilters();

    if (hiddenId === 'rcf-account_parent') {
        rcDisableChildFields();
    }
}

function rcAttachPickerFieldEvents(textInput, hiddenId, mode) {
    if (!textInput) return;

    const colWrapper = textInput.closest('.rc-filter-col');

    textInput.addEventListener('input', () => {
        if (textInput.value.trim().length > 0) {
            rcLockFilters(colWrapper);
        } else {
            rcUnlockFilters();
        }
    });

    textInput.addEventListener('keydown', (e) => {
        if (e.isComposing) return;

        if (e.key === 'Enter') {
            e.preventDefault();
            rcOpenAccountPicker(hiddenId, mode || 'all', textInput.value.trim());
            return;
        }

        if (e.key === 'Tab' && textInput.value.trim().length > 0) {
            e.preventDefault();
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            rcCancelPicker(hiddenId);
            return;
        }
    });

    textInput.addEventListener('dblclick', () => {
        const hidden = document.getElementById(hiddenId);
        if (hidden && hidden.value) {
            rcOpenAccountPicker(hiddenId, mode || 'all', '');
        }
    });

    textInput.addEventListener('blur', () => {
        if (textInput.value.length > 0 && !rcIsPickerOpen()) {
            setTimeout(() => textInput.focus(), 0);
        }
    });
}

function rcIsInventoryAccount(account) {
    if (!account) return false;
    if (account.system_key === 'inventory') return true;

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

/* ════════════════════════════════════════════════════════
   13) التهيئة الأولية
   ════════════════════════════════════════════════════════ */

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', rcInit);
} else {
    rcInit();
}