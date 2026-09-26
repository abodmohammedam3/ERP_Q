/* ============================================================
   حركات المخزون — ربط كامل بـ Laravel API + التابات
   ============================================================ */

/* ============================================================
   الثوابت
   ============================================================ */

const MOVEMENT_TYPE_LABELS = {
    supply: 'توريد مخزني',
    issue: 'صرف مخزني',
    purchase: 'توريد شراء',
    sale: 'صرف بيع',
    purchase_return: 'مرتجع شراء',
    sale_return: 'مرتجع بيع',
    sorting_out: 'فرز تحويل كيلو',
    sorting_in: 'فرز تحويل حبه',
};

const MOVEMENT_DIRECTION_LABELS = {
    in: 'دخول',
    out: 'خروج',
};

const PIECE_UNIT_NAMES = ['الحبة', 'حبة', 'حبه', 'حب', 'قطعة', 'قطعه'];
const DEFAULT_PROFIT_MARGIN = 0.20;

/* ✅ تصدير للـ Console (للتشخيص) */
if (typeof window !== 'undefined') {
    window.DEFAULT_PROFIT_MARGIN = DEFAULT_PROFIT_MARGIN;
    window.PIECE_UNIT_NAMES = PIECE_UNIT_NAMES;
}

/* ============================================================
   الحالة العامة
   ============================================================ */

let movementMode = 'view';
let currentMovementType = null;
let currentMovementId = null;
let activeMovementRow = null;
let activeWarehouseTarget = null;
let isSavingMovement = false;

/* حالة التابات والأرصدة */
let activeTab = 'movements';
let stockBalancesCache = [];
let balancesLoaded = false;
let activeSortingBalance = null;
let sortingFromBalanceModalInstance = null;

/* ============================================================
   الكاش
   ============================================================ */

const cache = {
    warehouses: [],
    items: [],
    types: [],
    units: [],
};

/* ============================================================
   النوافذ
   ============================================================ */

let movementItemModal,
    movementTypeModal,
    movementWarehouseModal,
    movementUnitModal;

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* ============================================================
   Helpers
   ============================================================ */

function apiHeaders(json = false) {
    const h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN };
    if (json) h['Content-Type'] = 'application/json';
    return h;
}

async function apiGet(url) {
    const r = await fetch(url, { headers: apiHeaders() });
    if (!r.ok) throw new Error(`فشل الطلب: ${r.status}`);
    return r.json();
}

async function apiSend(url, method, body) {
    const r = await fetch(url, {
        method, headers: apiHeaders(true), body: JSON.stringify(body),
    });
    const json = await r.json().catch(() => ({}));
    if (!r.ok) {
        let message = json.message || 'فشل الطلب';

        if (json.errors) {
            message = Object.values(json.errors).flat().join(' | ');
        } else if (json.error) {
            message = json.error;
        }

        const e = new Error(message);
        e.errors = json.errors;
        e.raw = json;
        console.error('API Error:', json);
        throw e;
    }
    return json;
}

function debounce(fn, ms = 200) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function cloneTemplate(id) {
    const tpl = document.getElementById(id);
    if (!tpl) throw new Error(`Template not found: ${id}`);
    return tpl.content.firstElementChild.cloneNode(true);
}

function unwrap(data) {
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.data)) return data.data;
    return [];
}

function notify(message, type = 'info') {
    if (typeof window.showSystemToast === 'function') {
        window.showSystemToast(message, type);
    } else {
        console.warn(`[${type}] ${message}`);
        alert(message);
    }
}

function formatMoney(v) {
    return Number(v || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function isKiloUnit(unitName) {
    if (!unitName) return false;
    const n = String(unitName).trim().toLowerCase();
    return n.includes('كيلو') || n.includes('كجم') || n === 'kg' || n.includes('kilogram');
}

function findDefaultPieceUnit() {
    for (const name of PIECE_UNIT_NAMES) {
        const found = cache.units.find(u => u.UnitName === name);
        if (found) return found;
    }
    return null;
}

/* ============================================================
   التهيئة
   ============================================================ */

document.addEventListener('DOMContentLoaded', async () => {

    const initModal = (id) => {
        const el = document.getElementById(id);
        if (!el) { console.warn(`⚠️ Modal غير موجود: #${id}`); return null; }
        return new bootstrap.Modal(el);
    };

    movementItemModal = initModal('movementItemModal');
    movementTypeModal = initModal('movementTypeModal');
    movementWarehouseModal = initModal('movementWarehouseModal');
    movementUnitModal = initModal('movementUnitModal');
    sortingFromBalanceModalInstance = initModal('sortingFromBalanceModal');

    await preloadAll();

    setMovementMode('view');
    clearMovementForm();

    initTabs();
});

async function preloadAll() {
    try {
        const [warehouses, items, types, units] = await Promise.all([
            apiGet('/setting/inventory/warehouses/list'),
            apiGet('/setting/inventory/items/list'),
            apiGet('/setting/inventory/types/list'),
            apiGet('/setting/inventory/units/list'),
        ]);

        cache.warehouses = unwrap(warehouses);
        cache.items = unwrap(items);
        cache.types = unwrap(types);
        cache.units = unwrap(units);

        console.log('✅ تم تحميل البيانات المرجعية', {
            warehouses: cache.warehouses.length,
            items: cache.items.length,
            types: cache.types.length,
            units: cache.units.length,
        });
    } catch (e) {
        console.error('فشل تحميل البيانات المرجعية', e);
        notify('تعذّر تحميل البيانات المرجعية', 'danger');
    }
}

/* ============================================================
   ✅ التابات
   ============================================================ */

function initTabs() {
    const tabs = document.querySelectorAll('#movementTabs .nav-link');
    if (!tabs.length) return;

    const activeInput = document.getElementById('activeMovementTab');

    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function () {
            const tabName = this.dataset.tab || 'movements';
            activeTab = tabName;

            if (activeInput) activeInput.value = tabName;

            // تحميل الأرصدة عند أول دخول
            if (tabName === 'balances' && !balancesLoaded) {
                loadStockBalances();
            }
        });
    });
}

/* ============================================================
   أوضاع الشاشة (حركات المخزون)
   ============================================================ */

function setMovementMode(mode) {
    if (mode !== 'view' && mode !== 'add') return;
    movementMode = mode;

    const hasMovement = !!currentMovementId;

    const editableHeaderIds = [
        'movementDate',
        'movementDocumentNumber',
        'movementStatement',
        'movementWarehouse',
    ];

    editableHeaderIds.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (mode === 'add') el.removeAttribute('readonly');
        else el.setAttribute('readonly', true);
    });

    const btnAddRow = document.getElementById('btnAddMovementRow');
    if (btnAddRow) btnAddRow.disabled = (mode === 'view');

    const saveActions = document.getElementById('movementSaveActions');
    if (saveActions) {
        if (mode === 'add') saveActions.classList.remove('d-none');
        else saveActions.classList.add('d-none');
    }

    /* ✅ منطق جديد: يعتمد على وجود حركة محمَّلة */
    const btnSupply = document.getElementById('btnAddSupplyMovement');
    const btnIssue = document.getElementById('btnAddIssueMovement');
    const btnPrint = document.getElementById('btnPrintMovement');

    if (mode === 'add') {
        // ✅ قيد الإضافة → تعطيل الكل
        if (btnSupply) btnSupply.disabled = true;
        if (btnIssue) btnIssue.disabled = true;
        if (btnPrint) btnPrint.disabled = true;
    } else {
        // ✅ view mode
        // - إذا كانت هناك حركة محمَّلة → عطِّل التوريد/الصرف، فعِّل الطباعة
        // - إذا لا → فعِّل التوريد/الصرف، عطِّل الطباعة
        if (btnSupply) btnSupply.disabled = hasMovement;
        if (btnIssue) btnIssue.disabled = hasMovement;
        if (btnPrint) btnPrint.disabled = !hasMovement;
    }

    document.querySelectorAll('#movementDetails .movement-detail-row').forEach(row => {
        applyRowMode(row);
    });
}

/* ============================================================
   تفريغ النموذج
   ============================================================ */

function clearMovementForm() {
    currentMovementId = null;
    currentMovementType = null;
    activeMovementRow = null;
    activeWarehouseTarget = null;

    setValue('movementDisplayId', '');
    setValue('movementType', '');
    setValue('movementDirection', '');
    setValue('movementDate', '');
    setValue('movementDocumentNumber', '');
    setValue('movementStatement', '');
    setValue('movementWarehouse', '');
    setValue('movementWarehouseId', '');

    const tbody = document.getElementById('movementDetails');
    if (tbody) tbody.replaceChildren();

    setValue('movementTotal', '0.00');

    const searchResults = document.getElementById('movementSearchResults');
    if (searchResults) searchResults.classList.add('d-none');

    /* ✅ تعطيل زر الطباعة (لا حركة محمَّلة) */
    const btnPrint = document.getElementById('btnPrintMovement');
    if (btnPrint) btnPrint.disabled = true;

    setMovementMode('view');
}

/* ============================================================
   إدارة الصفوف (حركات المخزون)
   ============================================================ */

function addMovementRow() {
    if (movementMode !== 'add') return;

    const tbody = document.getElementById('movementDetails');
    if (!tbody) return;

    const row = cloneTemplate('movementRowTemplate');

    const headerWarehouseId = getValue('movementWarehouseId');
    const headerWarehouseName = getValue('movementWarehouse');

    if (headerWarehouseId && headerWarehouseName) {
        row.querySelector('.movement-warehouse-id').value = headerWarehouseId;
        row.querySelector('.movement-warehouse').value = headerWarehouseName;
    }

    tbody.appendChild(row);
    enableMovementRow(row);
    renumberMovementRows();
    calculateMovementTotal();

    return row;
}

function enableMovementRow(row) {
    if (!row) return;
    applyRowMode(row);
}

function applyRowMode(row) {
    if (!row) return;

    const isView = movementMode === 'view';

    row.querySelectorAll('input').forEach(input => {
        if (input.classList.contains('movement-total')) return;
        if (input.type === 'hidden') return;

        if (isView) {
            input.setAttribute('readonly', true);
        } else {
            input.removeAttribute('readonly');
        }
    });
}

function renumberMovementRows() {
    document.querySelectorAll('#movementDetails .movement-detail-row')
        .forEach((row, index) => {
            const numEl = row.querySelector('.movement-row-number');
            if (numEl) numEl.textContent = index + 1;
        });
}

/* ============================================================
   الحسابات (حركات المخزون)
   ============================================================ */

function calculateMovementRow(element) {
    if (!element) return;

    let row = null;

    if (element.tagName === 'TR') {
        row = element;
    } else if (element.closest) {
        row = element.closest('tr');
    }

    if (!row) return;

    const quantity = parseFloat(row.querySelector('.movement-quantity')?.value) || 0;
    const unitCost = parseFloat(row.querySelector('.movement-unit-cost')?.value) || 0;
    const total = quantity * unitCost;

    const totalInput = row.querySelector('.movement-total');
    if (totalInput) totalInput.value = total.toFixed(2);

    calculateMovementTotal();
}

function calculateMovementTotal() {
    let total = 0;

    document.querySelectorAll('#movementDetails .movement-detail-row').forEach(row => {
        const rowTotal = parseFloat(row.querySelector('.movement-total')?.value) || 0;
        total += rowTotal;
    });

    setValue('movementTotal', total.toFixed(2));
}

function validateSalePrice(row) {
    if (!row) return;

    const minPriceInput = row.querySelector('.movement-min-price');
    const maxPriceInput = row.querySelector('.movement-max-price');
    const salePriceInput = row.querySelector('.movement-sale-price');

    if (!salePriceInput) return;

    const min = minPriceInput && minPriceInput.value !== '' ? parseFloat(minPriceInput.value) : null;
    const max = maxPriceInput && maxPriceInput.value !== '' ? parseFloat(maxPriceInput.value) : null;
    const sale = salePriceInput.value !== '' ? parseFloat(salePriceInput.value) : null;

    salePriceInput.classList.remove('is-invalid');

    if (sale === null) return;

    if (min !== null && sale < min) {
        salePriceInput.classList.add('is-invalid');
        return;
    }

    if (max !== null && sale > max) {
        salePriceInput.classList.add('is-invalid');
    }
}

/* ============================================================
   KeyDown Handlers
   ============================================================ */

function movementItemKeyDown(e) {
    if (movementMode !== 'add') return;
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault();
        activeMovementRow = e.target.closest('tr');
        openMovementItemModal();
    }
}

function movementTypeKeyDown(e) {
    if (movementMode !== 'add') return;
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault();
        activeMovementRow = e.target.closest('tr');
        openMovementTypeModal();
    }
}

function movementWarehouseRowKeyDown(e) {
    if (movementMode !== 'add') return;
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault();
        activeMovementRow = e.target.closest('tr');
        activeWarehouseTarget = 'row';
        openMovementWarehouseModal();
    }
}

function movementWarehouseHeaderKeyDown(e) {
    if (movementMode !== 'add') return;
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault();
        activeMovementRow = null;
        activeWarehouseTarget = 'header';
        openMovementWarehouseModal();
    }
}

function movementUnitKeyDown(e) {
    if (movementMode !== 'add') return;
    if (e.key === 'Enter' || (e.key === 'Tab' && e.target.value.trim())) {
        e.preventDefault();
        activeMovementRow = e.target.closest('tr');
        openMovementUnitModal();
    }
}

/* ============================================================
   Modal الصنف
   ============================================================ */

function openMovementItemModal() {
    if (movementMode !== 'add' || !movementItemModal) return;
    if (!activeMovementRow) return;

    document.getElementById('movementItemSearchInput').value =
        activeMovementRow.querySelector('.movement-item')?.value || '';

    movementItemModal.show();
    setTimeout(() => {
        document.getElementById('movementItemSearchInput').focus();
        searchMovementItems();
    }, 200);
}

function searchMovementItems() {
    const search = document.getElementById('movementItemSearchInput').value.trim();
    const tbody = document.getElementById('movementItemResults');
    tbody.replaceChildren();

    const filtered = cache.items.filter(i =>
        !search ||
        (i.itemName2 || '').includes(search) ||
        String(i.itemID || '').includes(search)
    );

    filtered.forEach(item => {
        const tr = cloneTemplate('movementItemRowTemplate');
        tr.querySelector('.c-id').textContent = item.itemID;
        tr.querySelector('.c-name').textContent = item.itemName2 ?? '';
        tr.addEventListener('click', () => selectMovementItem(item.itemID, item.itemName2));
        tbody.appendChild(tr);
    });
}

function selectMovementItem(id, name) {
    if (!activeMovementRow) return;

    activeMovementRow.querySelector('.movement-item-id').value = id;
    activeMovementRow.querySelector('.movement-item').value = name;

    activeMovementRow.querySelector('.movement-type-id').value = '';
    activeMovementRow.querySelector('.movement-type').value = '';

    if (movementItemModal) movementItemModal.hide();
    setTimeout(() => activeMovementRow.querySelector('.movement-type')?.focus(), 250);
}

const _debouncedSearchItems = debounce(searchMovementItems, 200);
const _debouncedSearchTypes = debounce(searchMovementTypes, 200);
const _debouncedSearchWarehouses = debounce(searchMovementWarehouses, 200);
const _debouncedSearchUnits = debounce(searchMovementUnits, 200);

function movementItemInput(e) { _debouncedSearchItems(); }

/* ============================================================
   Modal النوع
   ============================================================ */

function openMovementTypeModal() {
    if (movementMode !== 'add' || !movementTypeModal) return;
    if (!activeMovementRow) return;

    const itemId = activeMovementRow.querySelector('.movement-item-id')?.value;
    if (!itemId) {
        notify('يرجى اختيار الصنف أولاً', 'warning');
        return;
    }

    document.getElementById('movementTypeSearchInput').value =
        activeMovementRow.querySelector('.movement-type')?.value || '';

    movementTypeModal.show();
    setTimeout(() => {
        document.getElementById('movementTypeSearchInput').focus();
        searchMovementTypes();
    }, 200);
}

function searchMovementTypes() {
    const search = document.getElementById('movementTypeSearchInput').value.trim();
    const tbody = document.getElementById('movementTypeResults');
    tbody.replaceChildren();

    const filtered = cache.types.filter(t =>
        !search || (t.name || '').includes(search)
    );

    filtered.forEach(type => {
        const tr = cloneTemplate('movementTypeRowTemplate');
        tr.querySelector('.c-id').textContent = type.id;
        tr.querySelector('.c-name').textContent = type.name ?? '';
        tr.addEventListener('click', () => selectMovementType(type.id, type.name));
        tbody.appendChild(tr);
    });
}

function selectMovementType(id, name) {
    if (!activeMovementRow) return;

    activeMovementRow.querySelector('.movement-type-id').value = id;
    activeMovementRow.querySelector('.movement-type').value = name;

    if (movementTypeModal) movementTypeModal.hide();
    setTimeout(() => activeMovementRow.querySelector('.movement-code')?.focus(), 250);
}

function movementTypeInput(e) { _debouncedSearchTypes(); }

/* ============================================================
   Modal المخزن
   ============================================================ */

function openMovementWarehouseModal() {
    if (movementMode !== 'add' || !movementWarehouseModal) return;

    const currentValue = activeWarehouseTarget === 'header'
        ? getValue('movementWarehouse')
        : activeMovementRow?.querySelector('.movement-warehouse')?.value || '';

    document.getElementById('movementWarehouseSearchInput').value = currentValue;

    movementWarehouseModal.show();
    setTimeout(() => {
        document.getElementById('movementWarehouseSearchInput').focus();
        searchMovementWarehouses();
    }, 200);
}

function searchMovementWarehouses() {
    const search = document.getElementById('movementWarehouseSearchInput').value.trim();
    const tbody = document.getElementById('movementWarehouseResults');
    tbody.replaceChildren();

    const filtered = cache.warehouses.filter(w =>
        !search || (w.StockName || '').includes(search)
    );

    filtered.forEach(w => {
        const tr = cloneTemplate('movementWarehouseRowTemplate');
        tr.querySelector('.c-id').textContent = w.StockID;
        tr.querySelector('.c-name').textContent = w.StockName ?? '';
        tr.addEventListener('click', () => selectMovementWarehouse(w.StockID, w.StockName));
        tbody.appendChild(tr);
    });
}

function selectMovementWarehouse(id, name) {
    if (activeWarehouseTarget === 'header') {
        setValue('movementWarehouseId', id);
        setValue('movementWarehouse', name);
    } else if (activeMovementRow) {
        activeMovementRow.querySelector('.movement-warehouse-id').value = id;
        activeMovementRow.querySelector('.movement-warehouse').value = name;
    }

    if (movementWarehouseModal) movementWarehouseModal.hide();

    setTimeout(() => {
        if (activeWarehouseTarget === 'header') {
            const firstRow = document.querySelector('#movementDetails .movement-detail-row');
            if (firstRow) {
                firstRow.querySelector('.movement-item')?.focus();
            } else {
                const row = addMovementRow();
                row?.querySelector('.movement-item')?.focus();
            }
        } else if (activeMovementRow) {
            activeMovementRow.querySelector('.movement-unit')?.focus();
        }
    }, 250);
}

function movementWarehouseInput(e) { _debouncedSearchWarehouses(); }

/* ============================================================
   Modal الوحدة
   ============================================================ */

function openMovementUnitModal() {
    if (movementMode !== 'add' || !movementUnitModal) return;
    if (!activeMovementRow) return;

    document.getElementById('movementUnitSearchInput').value =
        activeMovementRow.querySelector('.movement-unit')?.value || '';

    movementUnitModal.show();
    setTimeout(() => {
        document.getElementById('movementUnitSearchInput').focus();
        searchMovementUnits();
    }, 200);
}

function searchMovementUnits() {
    const search = document.getElementById('movementUnitSearchInput').value.trim();
    const tbody = document.getElementById('movementUnitResults');
    tbody.replaceChildren();

    const filtered = cache.units.filter(u =>
        !search || (u.UnitName || '').includes(search)
    );

    filtered.forEach(u => {
        const tr = cloneTemplate('movementUnitRowTemplate');
        tr.querySelector('.c-id').textContent = u.UnitID;
        tr.querySelector('.c-name').textContent = u.UnitName ?? '';
        tr.addEventListener('click', () => selectMovementUnit(u.UnitID, u.UnitName));
        tbody.appendChild(tr);
    });
}

function selectMovementUnit(id, name) {
    if (!activeMovementRow) return;

    activeMovementRow.querySelector('.movement-unit-id').value = id;
    activeMovementRow.querySelector('.movement-unit').value = name;

    if (movementUnitModal) movementUnitModal.hide();
    setTimeout(() => activeMovementRow.querySelector('.movement-quantity')?.focus(), 250);
}

function movementUnitInput(e) { _debouncedSearchUnits(); }

/* ============================================================
   بدء الحركات
   ============================================================ */

async function startSupplyMovement() {
    clearMovementForm();
    currentMovementType = 'supply';

    setMovementMode('add');
    setValue('movementType', MOVEMENT_TYPE_LABELS.supply);
    setValue('movementDirection', MOVEMENT_DIRECTION_LABELS.in);
    setTodayDate();

    /* ✅ تعطيل زر الطباعة (لا حركة محفوظة) */
    const btnPrint = document.getElementById('btnPrintMovement');
    if (btnPrint) btnPrint.disabled = true;

    const displayEl = document.getElementById('movementDisplayId');
    if (displayEl) {
        try {
            const res = await apiGet('/operation/movements/next-number');
            displayEl.value = res.next_number || '';
        } catch (e) {
            displayEl.value = '';
            notify('تعذّر جلب رقم الحركة التالي', 'danger');
        }
    }

    document.getElementById('movementWarehouse')?.focus();
}

async function startIssueMovement() {
    clearMovementForm();
    currentMovementType = 'issue';

    setMovementMode('add');
    setValue('movementType', MOVEMENT_TYPE_LABELS.issue);
    setValue('movementDirection', MOVEMENT_DIRECTION_LABELS.out);
    setTodayDate();

    /* ✅ تعطيل زر الطباعة (لا حركة محفوظة) */
    const btnPrint = document.getElementById('btnPrintMovement');
    if (btnPrint) btnPrint.disabled = true;

    const displayEl = document.getElementById('movementDisplayId');
    if (displayEl) {
        try {
            const res = await apiGet('/operation/movements/next-number');
            displayEl.value = res.next_number || '';
        } catch (e) {
            displayEl.value = '';
            notify('تعذّر جلب رقم الحركة التالي', 'danger');
        }
    }

    document.getElementById('movementWarehouse')?.focus();
}

/* ============================================================
   البحث عن الحركات
   ============================================================ */

async function searchMovements() {
    const warehouseId = getValue('searchWarehouse');
    const movementType = getValue('searchMovementType');
    const dateFrom = getValue('searchDateFrom');
    const dateTo = getValue('searchDateTo');

    const params = new URLSearchParams();
    if (warehouseId) params.append('warehouse_id', warehouseId);
    if (movementType) params.append('movement_type', movementType);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    try {
        const raw = await apiGet('/operation/movements/list?' + params.toString());
        displayMovementSearchResults(unwrap(raw));
    } catch (e) {
        console.error(e);
        notify('فشل البحث عن الحركات', 'danger');
    }
}

function displayMovementSearchResults(results) {
    const container = document.getElementById('movementSearchResults');
    const tbody = document.getElementById('movementSearchResultsBody');
    if (!container || !tbody) return;

    tbody.replaceChildren();

    if (!results.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 6;
        td.className = 'text-center text-muted py-3';
        td.textContent = 'لا توجد حركات مطابقة للبحث.';
        tr.appendChild(td);
        tbody.appendChild(tr);
        container.classList.remove('d-none');
        return;
    }

    results.forEach(m => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';

        [
            m.display_id,
            m.movement_label || MOVEMENT_TYPE_LABELS[m.movement_type] || m.movement_type,
            MOVEMENT_DIRECTION_LABELS[m.direction] || m.direction,
            m.movement_date,
            m.document_number ?? '',
            m.warehouse_name ?? '',
        ].forEach(value => {
            const td = document.createElement('td');
            td.textContent = value;
            tr.appendChild(td);
        });

        tr.addEventListener('click', () => loadMovement(m.movement_id));
        tbody.appendChild(tr);
    });

    container.classList.remove('d-none');
}

/* ============================================================
   تحميل حركة
   ============================================================ */

async function loadMovement(movementId) {
    try {
        const res = await apiGet(`/operation/movements/${movementId}`);
        const h = res.header;
        const details = res.details || [];

        clearMovementForm();

        setValue('movementDisplayId', h.display_id ?? '');
        setValue('movementType', h.movement_label || MOVEMENT_TYPE_LABELS[h.movement_type] || '');
        setValue('movementDirection', MOVEMENT_DIRECTION_LABELS[h.direction] || '');
        setValue('movementDate', h.movement_date ?? '');
        setValue('movementDocumentNumber', h.document_number ?? '');
        setValue('movementStatement', h.statement ?? '');
        setValue('movementWarehouse', h.warehouse_name ?? '');
        setValue('movementWarehouseId', h.warehouse_id ?? '');

        const tbody = document.getElementById('movementDetails');
        tbody.replaceChildren();

        details.forEach(d => {
            const row = cloneTemplate('movementRowTemplate');

            row.querySelector('.movement-item-id').value = d.item_id ?? '';
            row.querySelector('.movement-item').value = d.item_name ?? '';

            row.querySelector('.movement-type-id').value = d.type_id ?? '';
            row.querySelector('.movement-type').value = d.type_name ?? '';

            row.querySelector('.movement-code').value = d.code ?? '';

            row.querySelector('.movement-warehouse-id').value = d.warehouse_id ?? '';
            row.querySelector('.movement-warehouse').value = d.warehouse_name ?? '';

            row.querySelector('.movement-unit-id').value = d.unit_id ?? '';
            row.querySelector('.movement-unit').value = d.unit_name ?? '';

            row.querySelector('.movement-quantity').value = d.quantity ?? 0;

            /* ✅ تنسيق unit_cost إلى منزلتين عشريتين */
            row.querySelector('.movement-unit-cost').value = Number(d.unit_cost ?? 0).toFixed(2);

            row.querySelector('.movement-min-price').value = d.min_price !== null && d.min_price !== undefined
                ? Number(d.min_price).toFixed(2)
                : '';

            row.querySelector('.movement-max-price').value = d.max_price !== null && d.max_price !== undefined
                ? Number(d.max_price).toFixed(2)
                : '';

            row.querySelector('.movement-sale-price').value = d.sale_price !== null && d.sale_price !== undefined
                ? Number(d.sale_price).toFixed(2)
                : '0.00';

            row.querySelector('.movement-total').value = Number(d.total).toFixed(2);

            tbody.appendChild(row);
        });

        renumberMovementRows();
        calculateMovementTotal();
        setMovementMode('view');

        currentMovementId = h.movement_id;
        currentMovementType = h.movement_type;

        /* ✅ تفعيل زر الطباعة (تم تحميل حركة) */
        const btnPrint = document.getElementById('btnPrintMovement');
        if (btnPrint) btnPrint.disabled = false;

        const searchResults = document.getElementById('movementSearchResults');
        if (searchResults) searchResults.classList.add('d-none');

        notify('تم تحميل الحركة بنجاح', 'success');
    } catch (e) {
        notify('فشل تحميل الحركة: ' + e.message, 'danger');
    }
}

/* ============================================================
   حفظ حركة
   ============================================================ */

async function saveMovement() {
    if (isSavingMovement) return;
    if (movementMode !== 'add') return;

    if (!currentMovementType) {
        notify('لم يتم تحديد نوع الحركة.', 'warning');
        return;
    }

    if (!validateMovement()) return;

    const payload = collectMovementData();

    isSavingMovement = true;
    const saveBtn = document.getElementById('btnSaveMovement');
    const cancelBtn = document.getElementById('btnCancelMovement');
    if (saveBtn) saveBtn.disabled = true;
    if (cancelBtn) cancelBtn.disabled = true;

    try {
        const r = await apiSend('/operation/movements', 'POST', payload);

        if (r.display_id) setValue('movementDisplayId', r.display_id);
        currentMovementId = r.movement_id;

        notify(r.message || 'تم حفظ حركة المخزون بنجاح', 'success');
        setMovementMode('view');

        /* ✅ تفعيل زر الطباعة (تم الحفظ) */
        const btnPrint = document.getElementById('btnPrintMovement');
        if (btnPrint) btnPrint.disabled = false;

    } catch (e) {
        let m = e.message;
        if (e.errors) m += ' — ' + Object.values(e.errors).flat().join(' | ');
        notify(m, 'danger');
    } finally {
        isSavingMovement = false;
        if (saveBtn) saveBtn.disabled = false;
        if (cancelBtn) cancelBtn.disabled = false;
    }
}

function collectMovementData() {
    const details = [];

    document.querySelectorAll('#movementDetails .movement-detail-row').forEach(row => {
        details.push({
            item_id: row.querySelector('.movement-item-id')?.value || null,
            type_id: row.querySelector('.movement-type-id')?.value || null,
            unit_id: row.querySelector('.movement-unit-id')?.value || null,
            code: row.querySelector('.movement-code')?.value || null,
            warehouse_id: row.querySelector('.movement-warehouse-id')?.value || null,
            quantity: parseFloat(row.querySelector('.movement-quantity')?.value) || 0,
            unit_cost: parseFloat(row.querySelector('.movement-unit-cost')?.value) || 0,
            min_price: row.querySelector('.movement-min-price')?.value !== ''
                ? parseFloat(row.querySelector('.movement-min-price').value) : null,
            max_price: row.querySelector('.movement-max-price')?.value !== ''
                ? parseFloat(row.querySelector('.movement-max-price').value) : null,
            sale_price: row.querySelector('.movement-sale-price')?.value !== ''
                ? parseFloat(row.querySelector('.movement-sale-price').value) : null,
        });
    });

    return {
        movement_type: currentMovementType,
        movement_date: getValue('movementDate'),
        document_number: getValue('movementDocumentNumber') || null,
        warehouse_id: getValue('movementWarehouseId') || null,
        statement: getValue('movementStatement') || null,
        details: details,
    };
}

function validateMovement() {
    if (!getValue('movementDate')) {
        notify('يرجى تحديد تاريخ الحركة.', 'warning');
        return false;
    }

    if (!getValue('movementWarehouseId')) {
        notify('يرجى اختيار المخزن في رأس الحركة.', 'warning');
        return false;
    }

    const rows = document.querySelectorAll('#movementDetails .movement-detail-row');
    if (!rows.length) {
        notify('يجب إضافة صنف واحد على الأقل.', 'warning');
        return false;
    }

    for (const row of rows) {
        const itemId = row.querySelector('.movement-item-id')?.value;
        const unitId = row.querySelector('.movement-unit-id')?.value;
        const warehouseId = row.querySelector('.movement-warehouse-id')?.value;
        const quantity = parseFloat(row.querySelector('.movement-quantity')?.value) || 0;
        const unitCost = parseFloat(row.querySelector('.movement-unit-cost')?.value) || 0;

        if (!itemId) { notify('يجب اختيار الصنف في جميع الصفوف.', 'warning'); return false; }
        if (!warehouseId) { notify('يجب اختيار المخزن في جميع الصفوف.', 'warning'); return false; }
        if (!unitId) { notify('يجب اختيار الوحدة في جميع الصفوف.', 'warning'); return false; }
        if (quantity <= 0) { notify('الكمية يجب أن تكون أكبر من صفر.', 'warning'); return false; }
        if (unitCost < 0) { notify('سعر التكلفة لا يمكن أن يكون سالبًا.', 'warning'); return false; }

        const minPrice = row.querySelector('.movement-min-price')?.value;
        const maxPrice = row.querySelector('.movement-max-price')?.value;
        const salePrice = row.querySelector('.movement-sale-price')?.value;

        if (minPrice !== '' && maxPrice !== '' && parseFloat(minPrice) > parseFloat(maxPrice)) {
            notify('الحد الأدنى للسعر لا يمكن أن يكون أكبر من الحد الأعلى.', 'warning');
            return false;
        }

        if (salePrice !== '') {
            const sale = parseFloat(salePrice);
            if (minPrice !== '' && sale < parseFloat(minPrice)) {
                notify('سعر البيع أقل من الحد الأدنى.', 'warning');
                return false;
            }
            if (maxPrice !== '' && sale > parseFloat(maxPrice)) {
                notify('سعر البيع أكبر من الحد الأعلى.', 'warning');
                return false;
            }
        }
    }

    return true;
}

/* ============================================================
   إلغاء / طباعة
   ============================================================ */

function cancelMovement() {
    if (movementMode !== 'add') return;
    if (!confirm('هل تريد إلغاء الحركة الحالية؟')) return;

    clearMovementForm();
    notify('تم إلغاء العملية', 'info');
}

/**
 * ✅ طباعة الحركة — تفتح صفحة طباعة في نافذة جديدة
 */
function printMovement() {
    if (movementMode !== 'view' || !currentMovementId) {
        notify('يجب حفظ الحركة أولاً قبل طباعتها.', 'warning');
        return;
    }

    window.open(
        `/operation/movements/${currentMovementId}/print`,
        '_blank',
        'width=900,height=700'
    );
}

/* ============================================================
   Utilities
   ============================================================ */

function setTodayDate() {
    const input = document.getElementById('movementDate');
    if (!input) return;

    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');

    input.value = `${y}-${m}-${d}`;
}

function getValue(id) {
    const el = document.getElementById(id);
    return el ? (el.value || '') : '';
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;

    const safeValue = value ?? '';

    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
        el.value = safeValue;
    } else {
        el.textContent = safeValue;
    }
}

/* =========================================================================
   ✅ قسم أرصدة المخزون (التاب 2)
   ========================================================================= */

async function loadStockBalances() {
    const tbody = document.getElementById('balancesTableBody');
    if (tbody) {
        tbody.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 11;
        td.className = 'text-center text-muted py-4';
        td.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري التحميل...';
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    try {
        const res = await apiGet('/operation/movements/helpers/stock-balances');
        stockBalancesCache = Array.isArray(res.data) ? res.data : [];
        balancesLoaded = true;

        filterStockBalances();
    } catch (e) {
        console.error(e);
        notify('فشل تحميل الأرصدة', 'danger');
        if (tbody) {
            tbody.replaceChildren();
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 11;
            td.className = 'text-center text-danger py-4';
            td.textContent = 'فشل تحميل الأرصدة';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    }
}

function filterStockBalances() {
    const search = (document.getElementById('balancesSearchInput')?.value || '').trim().toLowerCase();

    if (!search) {
        renderStockBalances(stockBalancesCache);
        return;
    }

    const filtered = stockBalancesCache.filter(row => {
        return (
            (row.item_name || '').toLowerCase().includes(search) ||
            (row.type_name || '').toLowerCase().includes(search) ||
            (row.code || '').toLowerCase().includes(search) ||
            (row.warehouse_name || '').toLowerCase().includes(search) ||
            (row.unit_name || '').toLowerCase().includes(search)
        );
    });

    renderStockBalances(filtered);
}

function renderStockBalances(rows) {
    const tbody = document.getElementById('balancesTableBody');
    const hint = document.getElementById('balancesCountHint');
    if (!tbody) return;

    tbody.replaceChildren();

    if (hint) hint.textContent = `عدد النتائج: ${rows.length}`;

    if (!rows.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 11;
        td.className = 'text-center text-muted py-4';
        td.textContent = 'لا توجد أرصدة متاحة';
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    const fragment = document.createDocumentFragment();

    rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.className = 'text-center';

        if (isKiloUnit(row.unit_name)) {
            tr.classList.add('row-kilo');
        }

        // الصنف
        const tdItem = document.createElement('td');
        tdItem.className = 'text-start';
        tdItem.textContent = row.item_name || '—';
        tr.appendChild(tdItem);

        // النوع
        const tdType = document.createElement('td');
        tdType.textContent = row.type_name || '—';
        tr.appendChild(tdType);

        // الرمز
        const tdCode = document.createElement('td');
        tdCode.textContent = row.code || '—';
        tr.appendChild(tdCode);

        // المخزن
        const tdWh = document.createElement('td');
        tdWh.textContent = row.warehouse_name || '—';
        tr.appendChild(tdWh);

        // الوحدة
        const tdUnit = document.createElement('td');
        tdUnit.textContent = row.unit_name || '—';
        tr.appendChild(tdUnit);

        // الكمية
        const tdQty = document.createElement('td');
        tdQty.className = 'balance-qty';
        const qty = Number(row.quantity) || 0;
        tdQty.textContent = Number.isInteger(qty) ? qty : qty.toFixed(2);
        tr.appendChild(tdQty);

        // التكلفة
        const tdCost = document.createElement('td');
        tdCost.className = 'balance-cost';
        tdCost.textContent = formatMoney(row.unit_cost);
        tr.appendChild(tdCost);

        // سعر البيع
        const tdSale = document.createElement('td');
        const saleInput = document.createElement('input');
        saleInput.type = 'number';
        saleInput.className = 'form-control form-control-sm price-input';
        saleInput.value = row.sale_price || 0;
        saleInput.step = '0.01';
        saleInput.min = '0';
        saleInput.addEventListener('change', () => {
            updatePricingInline(row, { sale_price: parseFloat(saleInput.value) || 0 });
        });
        tdSale.appendChild(saleInput);
        tr.appendChild(tdSale);

        // الحد الأدنى
        const tdMin = document.createElement('td');
        const minInput = document.createElement('input');
        minInput.type = 'number';
        minInput.className = 'form-control form-control-sm price-input';
        minInput.value = row.min_price || 0;
        minInput.step = '0.01';
        minInput.min = '0';
        minInput.addEventListener('change', () => {
            updatePricingInline(row, { min_price: parseFloat(minInput.value) || 0 });
        });
        tdMin.appendChild(minInput);
        tr.appendChild(tdMin);

        // الحد الأعلى
        const tdMax = document.createElement('td');
        const maxInput = document.createElement('input');
        maxInput.type = 'number';
        maxInput.className = 'form-control form-control-sm price-input';
        maxInput.value = row.max_price || 0;
        maxInput.step = '0.01';
        maxInput.min = '0';
        maxInput.addEventListener('change', () => {
            updatePricingInline(row, { max_price: parseFloat(maxInput.value) || 0 });
        });
        tdMax.appendChild(maxInput);
        tr.appendChild(tdMax);

        // الإجراءات
        const tdActions = document.createElement('td');
        const actionsWrap = document.createElement('div');
        actionsWrap.className = 'd-flex gap-1 justify-content-center';

        // زر فرز (للكيلو فقط)
        if (isKiloUnit(row.unit_name)) {
            const btnSort = document.createElement('button');
            btnSort.type = 'button';
            btnSort.className = 'btn btn-warning btn-action';
            btnSort.innerHTML = '<i class="bi bi-scissors"></i> فرز';
            btnSort.title = 'فرز الدفعة';
            btnSort.addEventListener('click', () => openSortingFromBalance(row));
            actionsWrap.appendChild(btnSort);
        }

        tdActions.appendChild(actionsWrap);
        tr.appendChild(tdActions);

        fragment.appendChild(tr);
    });

    tbody.appendChild(fragment);
}

async function updatePricingInline(rowData, changes) {
    const toNullable = (v) => {
        const n = Number(v);
        return (v === '' || v === null || v === undefined || isNaN(n) || n <= 0) ? null : n;
    };

    const saleValue = changes.sale_price !== undefined ? changes.sale_price : rowData.sale_price;
    const minValue = changes.min_price !== undefined ? changes.min_price : rowData.min_price;
    const maxValue = changes.max_price !== undefined ? changes.max_price : rowData.max_price;

    const payload = {
        item_id: rowData.item_id,
        warehouse_id: rowData.warehouse_id,
        unit_id: rowData.unit_id || null,
        sale_price: toNullable(saleValue),
        min_price: toNullable(minValue),
        max_price: toNullable(maxValue),
    };

    const sale = payload.sale_price;
    const min = payload.min_price;
    const max = payload.max_price;

    if (min !== null && max !== null && min > max) {
        notify(`الحد الأدنى (${min}) أكبر من الحد الأعلى (${max})`, 'warning');
        return;
    }
    if (sale !== null && min !== null && sale < min) {
        notify(`سعر البيع (${sale}) أقل من الحد الأدنى (${min})`, 'warning');
        return;
    }
    if (sale !== null && max !== null && sale > max) {
        notify(`سعر البيع (${sale}) أكبر من الحد الأعلى (${max})`, 'warning');
        return;
    }

    try {
        const r = await fetch('/operation/movements/helpers/pricing', {
            method: 'PUT',
            headers: apiHeaders(true),
            body: JSON.stringify(payload),
        });

        const data = await r.json().catch(() => ({}));

        if (!r.ok) {
            let errorMsg = data.message || 'فشل تحديث التسعير';
            if (data.errors) {
                const messages = Object.values(data.errors).flat();
                if (messages.length) errorMsg = messages.join(' | ');
            }
            throw new Error(errorMsg);
        }

        rowData.sale_price = payload.sale_price ?? 0;
        rowData.min_price = payload.min_price ?? 0;
        rowData.max_price = payload.max_price ?? 0;

        notify('تم تحديث التسعير', 'success');
    } catch (e) {
        console.error('updatePricingInline failed:', e);
        notify(e.message, 'danger');
    }
}

/* =========================================================================
   ✅ الفرز
   ========================================================================= */

function openSortingFromBalance(rowData) {
    activeSortingBalance = rowData;

    document.getElementById('sortingItemName').textContent = rowData.item_name || '—';
    document.getElementById('sortingTypeName').textContent = rowData.type_name || '—';
    document.getElementById('sortingWarehouseName').textContent = rowData.warehouse_name || '—';
    document.getElementById('sortingAvailable').textContent = formatMoney(rowData.quantity);
    document.getElementById('sortingUnitCost').textContent = formatMoney(rowData.unit_cost);

    // ✅ default الكمية = الكمية الكاملة
    document.getElementById('sortingInputQty').value = rowData.quantity;
    document.getElementById('sortingOutputQty').value = '';
    document.getElementById('sortingSalePrice').value = '';
    document.getElementById('sortingMinPrice').value = '';
    document.getElementById('sortingMaxPrice').value = '';
    document.getElementById('sortingResultUnitCost').textContent = '0.00';
    document.getElementById('sortingResultTotal').textContent = '0.00';

    sortingFromBalanceModalInstance?.show();

    setTimeout(() => document.getElementById('sortingOutputQty')?.focus(), 400);
}

function calculateSortingFromBalance() {
    if (!activeSortingBalance) return;

    const inputQty = parseFloat(document.getElementById('sortingInputQty').value) || 0;
    const outputQty = parseFloat(document.getElementById('sortingOutputQty').value) || 0;
    const kgCost = parseFloat(activeSortingBalance.unit_cost) || 0;

    if (inputQty <= 0 || outputQty <= 0 || kgCost <= 0) {
        document.getElementById('sortingResultUnitCost').textContent = '0.00';
        document.getElementById('sortingResultTotal').textContent = '0.00';
        return;
    }

    const totalCost = inputQty * kgCost;
    const unitCost = totalCost / outputQty;

    document.getElementById('sortingResultUnitCost').textContent = formatMoney(unitCost);
    document.getElementById('sortingResultTotal').textContent = formatMoney(totalCost);

    // ✅ هامش ربح تلقائي (فقط إذا كانت الحقول فارغة)
    const saleInput = document.getElementById('sortingSalePrice');
    const minInput = document.getElementById('sortingMinPrice');
    const maxInput = document.getElementById('sortingMaxPrice');

    if (!saleInput.value || parseFloat(saleInput.value) <= 0) {
        saleInput.value = (unitCost * (1 + DEFAULT_PROFIT_MARGIN)).toFixed(2);
    }
    if (!minInput.value || parseFloat(minInput.value) <= 0) {
        minInput.value = unitCost.toFixed(2);
    }
    if (!maxInput.value || parseFloat(maxInput.value) <= 0) {
        maxInput.value = (unitCost * (1 + DEFAULT_PROFIT_MARGIN * 2)).toFixed(2);
    }
}

async function saveSortingFromBalance() {
    if (!activeSortingBalance) return;

    const inputQty = parseFloat(document.getElementById('sortingInputQty').value) || 0;
    const outputQty = parseFloat(document.getElementById('sortingOutputQty').value) || 0;

    if (inputQty <= 0) {
        notify('الكمية المفرزة يجب أن تكون أكبر من صفر', 'warning');
        return;
    }
    if (outputQty <= 0) {
        notify('عدد الحبات يجب أن يكون أكبر من صفر', 'warning');
        return;
    }
    if (inputQty > activeSortingBalance.quantity) {
        notify(`الكمية المتاحة (${formatMoney(activeSortingBalance.quantity)}) أقل من المطلوب`, 'warning');
        return;
    }

    // ✅ تحذير عند سعر < تكلفة
    const kgCost = parseFloat(activeSortingBalance.unit_cost) || 0;
    const unitCost = (inputQty * kgCost) / outputQty;
    const salePrice = parseFloat(document.getElementById('sortingSalePrice').value) || 0;

    if (salePrice > 0 && salePrice < unitCost) {
        const deficit = ((unitCost - salePrice) / unitCost * 100).toFixed(1);
        const confirmed = confirm(
            `⚠️ تحذير: سعر البيع (${formatMoney(salePrice)}) أقل من تكلفة الحبة (${formatMoney(unitCost)}) بنسبة ${deficit}%.\n\nهل تريد المتابعة؟`
        );
        if (!confirmed) return;
    }

    const pieceUnit = findDefaultPieceUnit();
    if (!pieceUnit) {
        notify('وحدة "الحبة" غير موجودة في النظام', 'danger');
        return;
    }

    const payload = {
        item_id: activeSortingBalance.item_id,
        type_id: activeSortingBalance.type_id,
        warehouse_id: activeSortingBalance.warehouse_id,
        input_unit_id: activeSortingBalance.unit_id,
        output_unit_id: pieceUnit.UnitID,
        input_quantity: inputQty,
        output_quantity: outputQty,
        code: activeSortingBalance.code || null,
        sale_price: parseFloat(document.getElementById('sortingSalePrice').value) || null,
        min_price: parseFloat(document.getElementById('sortingMinPrice').value) || null,
        max_price: parseFloat(document.getElementById('sortingMaxPrice').value) || null,
    };

    const btn = document.getElementById('btnSaveSorting');
    if (btn) btn.disabled = true;

    try {
        const r = await fetch('/operation/movements/sort', {
            method: 'POST',
            headers: apiHeaders(true),
            body: JSON.stringify(payload),
        });
        const data = await r.json();

        if (!r.ok) {
            throw new Error(data.message || 'فشل الفرز');
        }

        notify('تم الفرز بنجاح', 'success');

        sortingFromBalanceModalInstance?.hide();

        // إعادة تحميل الأرصدة
        balancesLoaded = false;
        setTimeout(() => loadStockBalances(), 400);

    } catch (e) {
        notify(e.message, 'danger');
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* ═══════════════════════════════════════════════════════════
   تصدير الدوال
   ═══════════════════════════════════════════════════════════ */

Object.assign(window, {
    // الحركات
    startSupplyMovement,
    startIssueMovement,
    searchMovements,
    saveMovement,
    cancelMovement,
    printMovement,
    addMovementRow,
    calculateMovementRow,
    calculateMovementTotal,
    validateSalePrice,

    // keydown
    movementItemKeyDown,
    movementTypeKeyDown,
    movementWarehouseRowKeyDown,
    movementWarehouseHeaderKeyDown,
    movementUnitKeyDown,

    // input
    movementItemInput,
    movementTypeInput,
    movementWarehouseInput,
    movementUnitInput,

    // modals
    openMovementItemModal,
    openMovementTypeModal,
    openMovementWarehouseModal,
    openMovementUnitModal,

    searchMovementItems,
    searchMovementTypes,
    searchMovementWarehouses,
    searchMovementUnits,

    selectMovementItem,
    selectMovementType,
    selectMovementWarehouse,
    selectMovementUnit,

    loadMovement,
    clearMovementForm,
    setMovementMode,

    // ✅ الأرصدة
    loadStockBalances,
    filterStockBalances,
    renderStockBalances,
    updatePricingInline,

    // ✅ الفرز
    openSortingFromBalance,
    calculateSortingFromBalance,
    saveSortingFromBalance,

    findDefaultPieceUnit,
    isKiloUnit,
});