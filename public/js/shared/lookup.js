/* =========================================================================
   النظام الموحّد للنوافذ المنبثقة (Unified Lookup System)
   =========================================================================
   الفتح: Tab / Enter / مغادرة الحقل (blur)
   الإغلاق: ESC / backdrop / اختيار صف
   عند الإغلاق بدون اختيار → يعود التركيز إلى الحقل الأصلي
   ========================================================================= */

/* =========================================================================
   1) الإعدادات
   ========================================================================= */

const LookupConfigs = {

    /* ----------------------- المورد ----------------------- */
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

    /* ----------------------- العميل ----------------------- */
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

    /* ----------------------- العملة ----------------------- */
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

    /* ----------------------- المخزن ----------------------- */
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

    /* ----------------------- الصنف ----------------------- */
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

    /* ----------------------- النوع ----------------------- */
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

    /* ----------------------- الوحدة ----------------------- */
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

    /* ----------------------- الصناديق ----------------------- */
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

    /* ----------------------- البنوك ----------------------- */
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
   2) الكاش
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

/* =========================================================================
   3) الحالة
   ========================================================================= */

let lookupModalInstance = null;
let activeLookupKey = null;
let activeLookupTarget = null;
let lookupJustOpened = false;
let lookupRowSelected = false;   // ← جديد: لتمييز الاختيار عن الإغلاق

/* =========================================================================
   4) أدوات مساعدة
   ========================================================================= */

function lookupNormalize(data) {
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.data)) return data.data;
    return [];
}

function lookupApplyAlign(el, align) {
    let value = 'right';
    if (align === 'center') value = 'center';
    else if (align === 'end') value = 'left';
    else if (align === 'start') value = 'right';

    el.style.setProperty('text-align', value, 'important');
    el.style.setProperty('box-sizing', 'border-box', 'important');
    el.style.setProperty('vertical-align', 'middle', 'important');
}

/* =========================================================================
   5) تحميل البيانات
   ========================================================================= */

async function lookupPreload() {
    const endpoints = {
        suppliers: '/setting/suppliers/search',
        customers: '/setting/customers/search',
        coins: '/setting/accounting/coins/list',
        warehouses: '/setting/inventory/warehouses/list',
        items: '/setting/inventory/items/list',
        types: '/setting/inventory/types/list',
        units: '/setting/inventory/units/list',
        boxes: '/setting/accounting/boxes/list',
        banks: '/setting/accounting/banks/list',
    };

    const tasks = Object.entries(endpoints).map(async ([key, url]) => {
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) { lookupCache[key] = []; return; }
            const json = await res.json();
            lookupCache[key] = lookupNormalize(json);
        } catch (e) {
            console.warn(`lookup preload failed: ${key}`, e);
            lookupCache[key] = [];
        }
    });

    await Promise.all(tasks);
    console.log('✅ تم تحميل بيانات Lookup');
}

/* =========================================================================
   6) التهيئة
   ========================================================================= */

document.addEventListener('DOMContentLoaded', async () => {

    const modalEl = document.getElementById('unifiedLookupModal');
    if (modalEl) {
        lookupModalInstance = new bootstrap.Modal(modalEl);

        modalEl.addEventListener('shown.bs.modal', () => {
            setTimeout(lookupFocusFirstRow, 80);
        });

        // ✅ عند إغلاق النافذة — إن لم يُختَر صف، أعد التركيز للحقل الأصلي
        modalEl.addEventListener('hidden.bs.modal', () => {
            const shouldReturnFocus = !lookupRowSelected;
            const target = activeLookupTarget;

            // تصفير الحالة
            lookupRowSelected = false;
            activeLookupTarget = null;
            activeLookupKey = null;

            if (shouldReturnFocus && target && document.body.contains(target)) {
                // تأخير بسيط لترك Bootstrap يُكمل الإغلاق
                setTimeout(() => {
                    target.focus();
                    // تحديد النص المكتوب لتسهيل الكتابة فوقه
                    if (typeof target.setSelectionRange === 'function') {
                        try {
                            const len = (target.value || '').length;
                            target.setSelectionRange(len, len);
                        } catch (_) { /* بعض أنواع الحقول لا تدعمها */ }
                    }
                }, 60);
            }
        });

        lookupSetupFocusTrap(modalEl);
    }

    lookupBindAllFields();
    await lookupPreload();
});

/* =========================================================================
   7) Focus Trap
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
   8) ربط الحقول
   ========================================================================= */

function lookupBindAllFields() {
    document.querySelectorAll('[data-lookup]').forEach(el => {
        if (el.__lookupBound) return;
        el.__lookupBound = true;

        el.addEventListener('keydown', lookupFieldKeyDown);
        el.addEventListener('blur', lookupFieldBlur);
    });
}

/* =========================================================================
   9) معالجات الحقول
   ========================================================================= */

function lookupFieldKeyDown(e) {
    if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)) {
        e.preventDefault();

        lookupJustOpened = true;
        setTimeout(() => { lookupJustOpened = false; }, 500);

        openLookup(e.target.dataset.lookup, e.target);
    }
}

function lookupFieldBlur(e) {
    const target = e.target;
    if (!target.dataset.lookup) return;

    if (lookupJustOpened) return;
    if (document.querySelector('.modal.show')) return;

    setTimeout(() => {
        if (document.querySelector('.modal.show')) return;
        openLookup(target.dataset.lookup, target);
    }, 120);
}

/* =========================================================================
   10) فتح النافذة
   ========================================================================= */

function openLookup(key, target) {
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
    lookupRowSelected = false;   // ← إعادة تصفير الحالة

    const titleEl = document.getElementById('unifiedLookupTitle');
    if (titleEl) {
        titleEl.innerHTML =
            `<i class="bi ${config.icon || 'bi-search'} text-primary me-2"></i> ${config.title}`;
    }

    lookupRenderHeader(config);

    const searchInput = document.getElementById('unifiedLookupSearch');
    if (searchInput) {
        searchInput.value = target?.value || '';
    }

    lookupFilterAndRender();
    lookupModalInstance.show();
}

/* =========================================================================
   11) عرض الجدول
   ========================================================================= */

function lookupRenderHeader(config) {
    const table = document.querySelector('.lookup-table');
    if (!table) return;

    let colgroup = table.querySelector('colgroup');
    if (!colgroup) {
        colgroup = document.createElement('colgroup');
        table.insertBefore(colgroup, table.firstChild);
    }
    colgroup.replaceChildren();

    config.columns.forEach(col => {
        const colEl = document.createElement('col');
        if (col.width) colEl.style.width = col.width;
        colgroup.appendChild(colEl);
    });

    const tr = document.getElementById('unifiedLookupHeader');
    if (!tr) return;
    tr.replaceChildren();

    config.columns.forEach(col => {
        const th = document.createElement('th');
        th.textContent = col.label;

        lookupApplyAlign(th, col.align);

        if (col.width) {
            th.style.setProperty('width', col.width, 'important');
            th.style.setProperty('min-width', col.width, 'important');
            th.style.setProperty('max-width', col.width, 'important');
        }

        th.style.setProperty('padding', '10px 12px', 'important');
        th.style.setProperty('white-space', 'nowrap', 'important');
        th.style.setProperty('overflow', 'hidden', 'important');
        th.style.setProperty('text-overflow', 'ellipsis', 'important');

        tr.appendChild(th);
    });
}

function lookupRenderRows(rows, config) {
    const tbody = document.getElementById('unifiedLookupBody');
    if (!tbody) return;

    tbody.replaceChildren();

    if (!rows.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = config.columns.length;
        td.style.setProperty('text-align', 'center', 'important');
        td.style.setProperty('padding', '30px 20px', 'important');
        td.style.setProperty('color', '#6c757d', 'important');
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
        tr.style.cursor = 'pointer';

        config.columns.forEach(col => {
            const td = document.createElement('td');
            td.textContent = row[col.key] ?? '';

            lookupApplyAlign(td, col.align);

            if (col.width) {
                td.style.setProperty('width', col.width, 'important');
                td.style.setProperty('min-width', col.width, 'important');
                td.style.setProperty('max-width', col.width, 'important');
            }

            td.style.setProperty('padding', '10px 12px', 'important');
            td.style.setProperty('white-space', 'nowrap', 'important');
            td.style.setProperty('overflow', 'hidden', 'important');
            td.style.setProperty('text-overflow', 'ellipsis', 'important');

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

    // ✅ علّم أن الاختيار حدث
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
   15) تصدير الدوال العامة
   ========================================================================= */

window.openLookup = openLookup;
window.lookupFieldKeyDown = lookupFieldKeyDown;
window.lookupFieldBlur = lookupFieldBlur;
window.lookupSearchInput = lookupSearchInput;
window.lookupSearchKeyDown = lookupSearchKeyDown;
window.lookupCache = lookupCache;
window.LookupConfigs = LookupConfigs;

/* =========================================================================
   16) ربط تلقائي بعد أي تعديل على DOM
   ========================================================================= */

const lookupObserver = new MutationObserver(() => {
    lookupBindAllFields();
});

document.addEventListener('DOMContentLoaded', () => {
    lookupObserver.observe(document.body, { childList: true, subtree: true });
});