/* =========================================================================
   النظام الموحّد للنوافذ المنبثقة (Unified Lookup System)
   =========================================================================
   - تحميل قبل الإظهار (لا يبقى في "جاري التحميل")
   - Event Delegation (أداء عالي)
   - Preload للوحدات
   - Enter: يفتح دائمًا | Tab: يفتح مع نص فقط | Tab فارغ: ينتقل
   - blur: يفتح فقط عند وجود نص
   ========================================================================= */

/* =========================================================================
   1) الإعدادات
   ========================================================================= */

const LookupConfigs = {

    supplier: {
        title: 'اختيار المورد',
        icon: 'bi-person-fill',
        columns: [
            { key: 'suplierID', label: 'الرقم', width: '70px', align: 'center' },
            { key: 'supName', label: 'اسم المورد', align: 'start' },
            { key: 'accountCode', label: 'الرقم المحاسبي', width: '130px', align: 'center' },
        ],
        cacheKey: 'suppliers',
        filter: (row, s) =>
            !s ||
            (row.supName || '').includes(s) ||
            (row.accountCode || '').includes(s),
    },

    customer: {
        title: 'اختيار العميل',
        icon: 'bi-person-badge-fill',
        columns: [
            { key: 'CustomersID', label: 'الرقم', width: '70px', align: 'center' },
            { key: 'CustomersName2', label: 'اسم العميل', align: 'start' },
            { key: 'accountCode', label: 'الرقم المحاسبي', width: '130px', align: 'center' },
        ],
        cacheKey: 'customers',
        filter: (row, s) =>
            !s ||
            (row.CustomersName2 || '').includes(s) ||
            (row.accountCode || '').includes(s),
    },

    currency: {
        title: 'اختيار العملة',
        icon: 'bi-currency-exchange',
        columns: [
            { key: 'coinsID', label: 'الرقم', width: '70px', align: 'center' },
            { key: 'coinsName', label: 'العملة', align: 'start' },
            { key: 'coinsCode', label: 'الرمز', width: '90px', align: 'center' },
            { key: 'coinsExchangeRate', label: 'سعر الصرف', width: '110px', align: 'center' },
        ],
        cacheKey: 'coins',
        filter: (row, s) =>
            !s ||
            (row.coinsName || '').includes(s) ||
            (row.coinsCode || '').includes(s),
    },

    warehouse: {
        title: 'اختيار المخزن',
        icon: 'bi-building',
        columns: [
            { key: 'StockID', label: 'الرقم', width: '70px', align: 'center' },
            { key: 'StockName', label: 'اسم المخزن', align: 'start' },
        ],
        cacheKey: 'warehouses',
        filter: (row, s) =>
            !s || (row.StockName || '').includes(s),
    },

    item: {
        title: 'اختيار الصنف',
        icon: 'bi-box-seam',
        columns: [
            { key: 'itemID', label: 'الرقم', width: '70px', align: 'center' },
            { key: 'itemName2', label: 'الصنف', align: 'start' },
        ],
        cacheKey: 'items',
        filter: (row, s) =>
            !s || (row.itemName2 || '').includes(s),
    },

    type: {
        title: 'اختيار النوع',
        icon: 'bi-tags',
        columns: [
            { key: 'id', label: 'الرقم', width: '70px', align: 'center' },
            { key: 'name', label: 'النوع', align: 'start' },
        ],
        cacheKey: 'types',
        filter: (row, s) =>
            !s || (row.name || '').includes(s),
    },

    unit: {
        title: 'اختيار الوحدة',
        icon: 'bi-rulers',
        columns: [
            { key: 'UnitID', label: 'الرقم', width: '70px', align: 'center' },
            { key: 'UnitName', label: 'الوحدة', align: 'start' },
        ],
        cacheKey: 'units',
        filter: (row, s) =>
            !s || (row.UnitName || '').includes(s),
    },

    box: {
        title: 'اختيار الصندوق',
        icon: 'bi-cash-stack',
        columns: [
            { key: 'accountID', label: 'الرقم المحاسبي', width: '130px', align: 'center' },
            { key: 'boxName', label: 'اسم الصندوق', align: 'start' },
        ],
        cacheKey: 'boxes',
        filter: (row, s) =>
            !s || (row.boxName || '').includes(s),
    },

    bank: {
        title: 'اختيار البنك',
        icon: 'bi-bank',
        columns: [
            { key: 'accountID', label: 'الرقم المحاسبي', width: '130px', align: 'center' },
            { key: 'bankName', label: 'اسم البنك', align: 'start' },
        ],
        cacheKey: 'banks',
        filter: (row, s) =>
            !s || (row.bankName || '').includes(s),
    },
};

/* =========================================================================
   2) خريطة النقاط (Lazy Loading)
   ========================================================================= */

const lookupEndpointMap = {
    supplier: '/setting/suppliers/search',
    customer: '/setting/customers/search',
    currency: '/setting/accounting/coins/list',
    warehouse: '/setting/inventory/warehouses/list',
    item: '/setting/inventory/items/list',
    type: '/setting/inventory/types/list',
    unit: '/setting/inventory/units/list',
    box: '/setting/accounting/boxes/list',
    bank: '/setting/accounting/banks/list',
};

/* =========================================================================
   3) الكاش
   ========================================================================= */

const lookupCache = {
    suppliers: [],
    customers: [],
    coins: [],
    warehouses: [],
    items: [],
    types: [],
    units: [],
    boxes: [],
    banks: [],
};

const lookupLoadedFlags = {};

/* =========================================================================
   4) الحالة
   ========================================================================= */

let lookupModalInstance = null;
let activeLookupKey = null;
let activeLookupTarget = null;
let lookupJustOpened = false;
let lookupRowSelected = false;
let lookupKeyDownHandled = false;
let lookupLastClosedAt = 0;

/* =========================================================================
   5) الصفحات المستثناة من التحميل المسبق
   ========================================================================= */

const lookupSkipPreloadPages = [
    'openingBalances',
    'journalEntries',
];

function lookupShouldSkipPreload() {
    const currentPath = window.location.pathname;
    return lookupSkipPreloadPages.some(page => currentPath.includes(page));
}

/* =========================================================================
   6) أدوات مساعدة
   ========================================================================= */

function lookupNormalize(data) {
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.data)) return data.data;
    return [];
}

/* =========================================================================
   7) Lazy Loading — تحميل نقاط البيانات عند الطلب
   ========================================================================= */

async function lookupEnsureLoaded(key) {
    if (lookupLoadedFlags[key]) return;

    const url = lookupEndpointMap[key];
    if (!url) return;

    const cacheKey = LookupConfigs[key]?.cacheKey;
    if (!cacheKey) return;

    try {
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json' }
        });

        if (!res.ok) {
            lookupCache[cacheKey] = [];
            lookupLoadedFlags[key] = true;
            return;
        }

        const json = await res.json();
        lookupCache[cacheKey] = lookupNormalize(json);
        lookupLoadedFlags[key] = true;

    } catch (e) {
        console.warn(`lookup load failed: ${key}`, e);
        lookupCache[cacheKey] = [];
        lookupLoadedFlags[key] = true;
    }
}

/* =========================================================================
   8) التهيئة
   ========================================================================= */

document.addEventListener('DOMContentLoaded', () => {

    const modalEl = document.getElementById('unifiedLookupModal');
    if (!modalEl) return;

    lookupModalInstance = new bootstrap.Modal(modalEl);

    modalEl.addEventListener('shown.bs.modal', () => {
        setTimeout(lookupFocusFirstRow, 60);
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        const shouldReturnFocus = !lookupRowSelected;
        const target = activeLookupTarget;

        lookupLastClosedAt = Date.now();

        lookupRowSelected = false;
        activeLookupTarget = null;
        activeLookupKey = null;

        if (shouldReturnFocus && target && document.body.contains(target)) {
            setTimeout(() => {
                target.focus();
                if (typeof target.setSelectionRange === 'function') {
                    try {
                        const len = (target.value || '').length;
                        target.setSelectionRange(len, len);
                    } catch (_) { /* بعض الحقول لا تدعمها */ }
                }
            }, 60);
        }
    });

    lookupSetupFocusTrap(modalEl);

    // ✅ Preload الوحدات — فقط إذا لم نكن في صفحة مستثناة
    if (!lookupShouldSkipPreload()) {
        lookupEnsureLoaded('unit');
    }
});

/* =========================================================================
   9) Event Delegation — لكل حقول data-lookup
   =========================================================================
   - Enter       → يفتح النافذة (دائمًا)
   - Tab مع نص   → يفتح النافذة
   - Tab فارغ    → ينتقل بشكل طبيعي
   ========================================================================= */

document.addEventListener('keydown', (e) => {
    const target = e.target;
    if (!target || !target.dataset || !target.dataset.lookup) return;

    const value = (target.value || '').trim();

    // ────────────────────────────────────────
    // Enter → يفتح النافذة دائماً
    // ────────────────────────────────────────
    if (e.key === 'Enter') {
        e.preventDefault();

        lookupKeyDownHandled = true;
        lookupJustOpened = true;

        setTimeout(() => {
            lookupKeyDownHandled = false;
            lookupJustOpened = false;
        }, 600);

        openLookup(target.dataset.lookup, target);
        return;
    }

    // ────────────────────────────────────────
    // Tab مع نص → يفتح النافذة
    // Tab فارغ → ينتقل بشكل طبيعي (لا نمنع)
    // ────────────────────────────────────────
    if (e.key === 'Tab' && !e.shiftKey && value !== '') {
        e.preventDefault();

        lookupKeyDownHandled = true;
        lookupJustOpened = true;

        setTimeout(() => {
            lookupKeyDownHandled = false;
            lookupJustOpened = false;
        }, 600);

        openLookup(target.dataset.lookup, target);
    }
}, true);

/* =========================================================================
   blur ذكي — يفتح فقط عند وجود نص
   ========================================================================= */

document.addEventListener('blur', (e) => {
    const target = e.target;
    if (!target || !target.dataset || !target.dataset.lookup) return;

    // ✅ لا تفتح النافذة لو الحقل فارغ
    const value = (target.value || '').trim();
    if (value === '') return;

    // Tab/Enter → عالجناه
    if (lookupKeyDownHandled) return;
    if (lookupJustOpened) return;

    // نافذة مفتوحة
    if (document.querySelector('.modal.show')) return;

    // أُغلقت قبل لحظة → لا تُعِد الفتح
    if (Date.now() - lookupLastClosedAt < 400) return;

    // ✅ فحص التركيز الجديد
    const next = e.relatedTarget;

    // لا يوجد تركيز جديد → المستخدم ضغط خارج الصفحة
    if (!next) return;

    // التركيز انتقل إلى زر / رابط / نافذة → لا تفتح
    const tag = next.tagName;
    const isButtonLike =
        tag === 'BUTTON' ||
        tag === 'A' ||
        (tag === 'INPUT' && (next.type === 'submit' || next.type === 'button' || next.type === 'reset')) ||
        (next.closest && (
            next.closest('.modal') ||
            next.closest('.offcanvas') ||
            next.closest('.sidebar') ||
            next.closest('.navbar') ||
            next.closest('.btn-group')
        ));

    if (isButtonLike) return;

    setTimeout(() => {
        if (document.querySelector('.modal.show')) return;
        openLookup(target.dataset.lookup, target);
    }, 150);
}, true);

/* =========================================================================
   10) فتح النافذة — تحميل قبل الإظهار
   ========================================================================= */

async function openLookup(key, target) {
    if (document.querySelector('.modal.show')) return;

    const config = LookupConfigs[key];
    if (!config) {
        console.warn(`Lookup config not found: ${key}`);
        return;
    }

    if (!lookupModalInstance) {
        console.warn('Lookup modal غير موجود في الصفحة');
        return;
    }

    activeLookupKey = key;
    activeLookupTarget = target;
    lookupRowSelected = false;

    // 1. العنوان
    const titleEl = document.getElementById('unifiedLookupTitle');
    if (titleEl) {
        titleEl.innerHTML =
            `<i class="bi ${config.icon || 'bi-search'} text-primary me-2"></i> ${config.title}`;
    }

    // 2. رأس الجدول
    lookupRenderHeader(config);

    // 3. حقل البحث
    const searchInput = document.getElementById('unifiedLookupSearch');
    if (searchInput) {
        searchInput.value = target?.value || '';
    }

    // 4. إظهار "جاري التحميل" أو البيانات
    if (!lookupLoadedFlags[key]) {
        lookupRenderLoading(config);
    } else {
        lookupFilterAndRender();
    }

    // 5. فتح النافذة
    lookupModalInstance.show();

    // 6. جلب البيانات إن لم تكن محمّلة
    if (!lookupLoadedFlags[key]) {
        await lookupEnsureLoaded(key);

        // اعرض الصفوف بغض النظر عن حالة النافذة
        if (activeLookupKey === key) {
            lookupFilterAndRender();
        }
    }
}

/* =========================================================================
   11) عرض الجدول
   ========================================================================= */

function lookupRenderHeader(config) {
    const colgroup = document.getElementById('unifiedLookupColgroup');
    if (colgroup) {
        colgroup.replaceChildren();
        config.columns.forEach(col => {
            const colEl = document.createElement('col');
            if (col.width) colEl.style.width = col.width;
            colgroup.appendChild(colEl);
        });
    }

    const tr = document.getElementById('unifiedLookupHeader');
    if (!tr) return;
    tr.replaceChildren();

    config.columns.forEach(col => {
        const th = document.createElement('th');
        th.textContent = col.label;

        th.className = col.align === 'center' ? 'text-center'
            : col.align === 'end' ? 'text-end'
                : 'text-start';

        tr.appendChild(th);
    });
}

function lookupRenderLoading(config) {
    const tbody = document.getElementById('unifiedLookupBody');
    if (!tbody) return;

    tbody.replaceChildren();

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = config.columns.length;
    td.className = 'lookup-loading';
    td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
    tr.appendChild(td);
    tbody.appendChild(tr);
}

function lookupRenderRows(rows, config) {
    const tbody = document.getElementById('unifiedLookupBody');
    if (!tbody) return;

    tbody.replaceChildren();

    if (!rows.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = config.columns.length;
        td.className = 'lookup-empty';
        td.textContent = 'لا توجد نتائج';
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    const fragment = document.createDocumentFragment();

    rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.className = 'lookup-row';
        tr.tabIndex = 0;

        config.columns.forEach(col => {
            const td = document.createElement('td');
            td.textContent = row[col.key] ?? '';

            td.className = col.align === 'center' ? 'text-center'
                : col.align === 'end' ? 'text-end'
                    : 'text-start';

            tr.appendChild(td);
        });

        tr.addEventListener('click', () => lookupSelectRow(row));

        tr.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                lookupSelectRow(row);
            }
        });

        fragment.appendChild(tr);
    });

    tbody.appendChild(fragment);
}

function lookupFilterAndRender() {
    const config = LookupConfigs[activeLookupKey];
    if (!config) return;

    const search = (document.getElementById('unifiedLookupSearch')?.value || '').trim();
    const source = lookupCache[config.cacheKey] || [];

    const filtered = !search
        ? source
        : source.filter(row => config.filter(row, search));

    lookupRenderRows(filtered, config);
}

/* =========================================================================
   12) البحث داخل النافذة
   ========================================================================= */

function lookupSearchInput() {
    lookupFilterAndRender();
}

function lookupSearchKeyDown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        lookupFocusFirstRow();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const first = document.querySelector('#unifiedLookupBody .lookup-row');
        if (first) first.click();
    }
}

/* =========================================================================
   13) التركيز
   ========================================================================= */

function lookupFocusFirstRow() {
    const first = document.querySelector('#unifiedLookupBody .lookup-row');
    if (first) first.focus();
}

/* =========================================================================
   14) اختيار صف
   ========================================================================= */

function lookupSelectRow(row) {
    if (!row || !activeLookupTarget) return;

    lookupRowSelected = true;

    const target = activeLookupTarget;
    const config = LookupConfigs[activeLookupKey];

    const idField = target.dataset.lookupIdField;
    const targetId = target.dataset.lookupTarget;
    const display = target.dataset.lookupDisplayField;
    const nextId = target.dataset.lookupNext;

    if (targetId && idField) {
        const idEl = document.getElementById(targetId);
        if (idEl) idEl.value = row[idField] ?? '';
    }

    if (display) {
        target.value = row[display] ?? '';
    } else {
        const displayCol = config.columns[1]?.key;
        target.value = displayCol ? (row[displayCol] ?? '') : '';
    }

    if (lookupModalInstance) lookupModalInstance.hide();

    if (typeof config.onSelect === 'function') {
        config.onSelect(row, target);
    }

    if (nextId) {
        setTimeout(() => {
            const next = document.getElementById(nextId);
            if (next) next.focus();
        }, 280);
    }
}

/* =========================================================================
   15) Focus Trap — حصر التركيز داخل النافذة
   ========================================================================= */

function lookupSetupFocusTrap(modalEl) {
    if (!modalEl) return;

    modalEl.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;
        if (!modalEl.classList.contains('show')) return;

        const searchInput = document.getElementById('unifiedLookupSearch');
        const rows = Array.from(
            modalEl.querySelectorAll('#unifiedLookupBody .lookup-row')
        );

        const focusables = [];
        if (searchInput) focusables.push(searchInput);
        rows.forEach(r => focusables.push(r));

        if (focusables.length === 0) return;

        const current = document.activeElement;
        const idx = focusables.indexOf(current);

        e.preventDefault();
        e.stopPropagation();

        if (idx === -1) {
            focusables[0].focus();
            return;
        }

        if (e.shiftKey) {
            const prevIdx = idx === 0 ? focusables.length - 1 : idx - 1;
            focusables[prevIdx].focus();
        } else {
            const nextIdx = idx === focusables.length - 1 ? 0 : idx + 1;
            focusables[nextIdx].focus();
        }
    }, true);
}

/* =========================================================================
   16) تصدير الدوال العامة
   ========================================================================= */

window.openLookup = openLookup;
window.lookupSearchInput = lookupSearchInput;
window.lookupSearchKeyDown = lookupSearchKeyDown;
window.lookupCache = lookupCache;
window.LookupConfigs = LookupConfigs;
window.lookupEnsureLoaded = lookupEnsureLoaded;
window.lookupLoadedFlags = lookupLoadedFlags;

/* =========================================================================
   17) نهاية الملف
   ========================================================================= */