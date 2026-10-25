/**
 * ═══════
 *  لوحة المعلومات — Dashboard
 * ═══════
 *
 * المبدأ:
 *   1) الرسم الأول من SSR (window.DASHBOARD_INITIAL) — يمنع ظهور "0" كاذب.
 *   2) ثم تأكيد من /dashboard/data — نفس الأرقام عادةً (بفضل كاش الإصدار).
 *
 * التحديث التلقائي:
 *   - لا polling. فقط visibilitychange + focus.
 *   - عند عودة المستخدم للتبويب نُعيد الجلب؛ فإن كانت هناك كتابة أثناء غيابه
 *     يكون الإصدار قد ارتفع في الخادم ⇒ تُرجَع بيانات جديدة.
 *
 * الحماية من race:
 *   - AbortController يُلغي الطلب السابق قبل بدء طلب جديد
 *     (نفس نمط resources/js/reports/reportCenter.js:492-493).
 *
 * الحماية من XSS:
 *   - كل النصوص تُكتب بـ textContent لا innerHTML.
 *   - لا حاجة لـ escapeHtml؛ المتصفح لا يفسّر textContent كـ HTML.
 *     (نفس المنهج في reportCenter.js:81-104)
 */

'use strict';

/* ────────────────────────────────
   الحالة
   ──────────────────────────────── */
const DASH = {
    dataUrl: '/dashboard/data',
    controller: null,
    lastVersion: null,
    initialized: false,
};

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* ────────────────────────────────
   Helpers
   ──────────────────────────────── */

/** نص آمن: يحوّل null/undefined/'' إلى شرطة */
const textOr = (value, fallback = '—') => {
    if (value === null || value === undefined) return fallback;
    const str = String(value).trim();
    return str === '' ? fallback : str;
};

/** رقم آمن للتنسيق */
const numOr = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};

/** مبلغ بفواصل الآلاف (يستخدم Utils المشترك المحمّل في app.js) */
const money = (value) => {
    const n = numOr(value);
    return typeof window.Utils?.formatAmount === 'function'
        ? window.Utils.formatAmount(n)
        : n.toFixed(2);
};

/** عدد صحيح بفواصل الآلاف */
const int = (value) => numOr(value).toLocaleString('en-US');

/**
 * كتابة نص في عنصر إن وُجد (لا انفجار لو تغيّر الـ markup).
 */
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

/**
 * بناء صف جدول آمن بـ DOM API.
 * @param {Array<string>} cells قيم الأعمدة (textContent)
 */
function buildRow(cells, className = '') {
    const tr = document.createElement('tr');
    if (className) tr.className = className;

    cells.forEach((cell) => {
        const td = document.createElement('td');
        td.className = 'text-center';
        td.textContent = cell;
        tr.appendChild(td);
    });

    return tr;
}

/** صف "لا توجد بيانات" بعرض الجدول الكامل */
function buildEmptyRow(colspan, message) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = colspan;
    td.className = 'text-center text-muted py-4';
    td.textContent = message;
    tr.appendChild(td);
    return tr;
}

/* ────────────────────────────────
   الرسم
   ──────────────────────────────── */

function paintDate() {
    const el = document.getElementById('dashboardDate');
    if (!el) return;

    try {
        el.textContent = new Date().toLocaleDateString('ar-EG', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch (e) {
        el.textContent = new Date().toLocaleDateString();
    }
}

function paintIndicators(indicators) {
    if (!indicators) return;

    setText('salesInvoicesCount', int(indicators.sales_invoices));
    setText('purchaseInvoicesCount', int(indicators.purchase_invoices));
    setText('customersCount', int(indicators.customers));
    setText('suppliersCount', int(indicators.suppliers));
}

function paintSummary(summary) {
    if (!summary) return;

    setText('dashboardSalesTotal', money(summary.sales_total));
    setText('dashboardPurchaseTotal', money(summary.purchase_total));
    setText('dashboardStockMovementsCount', int(summary.stock_movements));
}

function paintSupply(rows) {
    const tbody = document.getElementById('dashboardSalesInvoices');
    if (!tbody) return;

    tbody.replaceChildren();

    if (!Array.isArray(rows) || rows.length === 0) {
        tbody.appendChild(buildEmptyRow(10, 'لا توجد حركات توريد'));
        return;
    }

    const fragment = document.createDocumentFragment();

    rows.forEach((row) => {
        fragment.appendChild(buildRow([
            textOr(row.display_id),
            textOr(row.supplier_name),
            textOr(row.date),
            textOr(row.day_name),
            textOr(row.item_name),
            textOr(row.type_name),
            textOr(row.code),
            textOr(row.unit_name),
            money(row.quantity),
            money(row.sale_price),
        ]));
    });

    tbody.appendChild(fragment);
}

function paintDistribution(rows) {
    const tbody = document.getElementById('dashboardPurchaseInvoices');
    if (!tbody) return;

    tbody.replaceChildren();

    if (!Array.isArray(rows) || rows.length === 0) {
        tbody.appendChild(buildEmptyRow(9, 'لا توجد حركات توزيع'));
        return;
    }

    const fragment = document.createDocumentFragment();

    rows.forEach((row) => {
        fragment.appendChild(buildRow([
            textOr(row.display_id),
            textOr(row.supplier_name),
            textOr(row.movement_type),
            textOr(row.type_name),
            textOr(row.code),
            textOr(row.unit_name),
            money(row.quantity),
            money(row.unit_cost),
            money(row.line_total),
        ]));
    });

    tbody.appendChild(fragment);
}

function paintInventory(inventory) {
    if (!inventory) return;

    setText('stocksCount', int(inventory.stocks));
    setText('itemsCount', int(inventory.items));
    setText('typesCount', int(inventory.types));
    setText('unitsCount', int(inventory.units));

    const tbody = document.getElementById('dashboardStockMovements');
    if (!tbody) return;

    tbody.replaceChildren();

    const rows = Array.isArray(inventory.recent) ? inventory.recent : [];

    if (rows.length === 0) {
        tbody.appendChild(buildEmptyRow(6, 'لا توجد حركات مخزون'));
        return;
    }

    const fragment = document.createDocumentFragment();

    rows.forEach((row) => {
        fragment.appendChild(buildRow([
            textOr(row.date),
            textOr(row.type_label),
            textOr(row.statement),
            textOr(row.item_name),
            money(row.quantity),
            money(row.unit_cost),
        ]));
    });

    tbody.appendChild(fragment);
}

function paintAlerts(alerts) {
    const container = document.getElementById('dashboardAlerts');
    if (!container) return;

    container.replaceChildren();

    if (!Array.isArray(alerts) || alerts.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'list-group-item text-muted';
        empty.textContent = 'لا توجد تنبيهات حالياً';
        container.appendChild(empty);
        return;
    }

    alerts.forEach((alert) => {
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-center';

        const label = document.createElement('span');
        label.textContent = textOr(alert.text);

        const badge = document.createElement('span');
        badge.className = `badge ${alert.level === 'warning' ? 'text-bg-warning' : 'text-bg-info'}`;
        badge.textContent = int(alert.count);

        item.appendChild(label);
        item.appendChild(badge);
        container.appendChild(item);
    });
}

/**
 * رسم كل الأجزاء.
 */
function paintAll(data) {
    if (!data) return;

    paintIndicators(data.indicators);
    paintSummary(data.summary);
    paintSupply(data.supply);
    paintDistribution(data.distribution);
    paintInventory(data.inventory);
    paintAlerts(data.alerts);
}

/* ────────────────────────────────
   الجلب
   ──────────────────────────────── */

/**
 * جلب البيانات من الخادم مع إلغاء الطلب السابق (منع race).
 */
async function fetchDashboard() {
    if (DASH.controller) DASH.controller.abort();
    DASH.controller = new AbortController();

    try {
        const response = await fetch(DASH.dataUrl, {
            method: 'GET',
            signal: DASH.controller.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || !payload.success) {
            console.error('[Dashboard] fetch failed', response.status, payload);
            notifyError();
            return;
        }

        DASH.lastVersion = payload.data?.version ?? null;
        paintAll(payload.data);
        clearError();
    } catch (error) {
        if (error.name === 'AbortError') return; // طلب أُلغي — ليس خطأ

        console.error('[Dashboard]', error);
        notifyError();
    }
}

/**
 * شريط خطأ بسيط أعلى الصفحة — لا يكسر الشاشة.
 */
function notifyError() {
    const banner = document.getElementById('dashboardErrorBanner');
    if (!banner) return;
    banner.classList.remove('d-none');
}

function clearError() {
    const banner = document.getElementById('dashboardErrorBanner');
    if (!banner) return;
    banner.classList.add('d-none');
}

/* ────────────────────────────────
   التهيئة
   ──────────────────────────────── */

function initDashboard() {
    if (DASH.initialized) return;
    DASH.initialized = true;

    paintDate();

    // 1) الرسم الفوري من SSR (قد تكون فارغة في بيئة الاختبار)
    const initial = window.DASHBOARD_INITIAL;
    if (initial) {
        DASH.lastVersion = initial.version ?? null;
        paintIndicators(initial.indicators);
        paintSummary(initial.summary);
    }

    // 2) تأكيد/تحميل بقية الأجزاء من API
    fetchDashboard();

    // 3) تحديث عند العودة للتبويب/النافذة (بدل polling)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            fetchDashboard();
        }
    });

    window.addEventListener('focus', () => {
        fetchDashboard();
    });
}

document.addEventListener('DOMContentLoaded', initDashboard);

/* للتصحيح من الـ console فقط */
window.Dashboard = {
    refresh: fetchDashboard,
    state: DASH,
};
