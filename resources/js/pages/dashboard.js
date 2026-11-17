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
    distributionUrl: '/dashboard/distribution/daily',
    controller: null,
    distController: null,
    lastVersion: null,
    initialized: false,
    /** حالة جدول التوزيع اليومي */
    dist: {
        date: new Date().toISOString().slice(0, 10),
        customerId: null,
        customers: [],
    },
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

/**
 * اسم الطرف (مورد أو عميل) — حقل `party_name` الجديد،
 * مع رجوع آمن إلى `supplier_name` إن كان الخادم بقاعدةِ كود أقدم (توافق خلفي).
 */
const partyNameOr = (row, fallback = '—') =>
    (row && row.party_name !== undefined && row.party_name !== null)
        ? textOr(row.party_name, fallback)
        : textOr(row?.supplier_name, fallback);

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
            partyNameOr(row),
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
    // الجدول القديم أُستبدل بجدول التوزيع اليومي (distributionDaily) — لا يوجد tbody
    if (!document.getElementById('dashboardPurchaseInvoices')) return;

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
            partyNameOr(row),
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
   جدول التوزيع اليومي
   ──────────────────────────────── */

/**
 * رسم جدول التوزيع اليومي من استجابة /dashboard/distribution/daily.
 * الأعمدة الخمسة: الكمية، الرمز، سعر الحبة، الاجمالي، النوع.
 * الرأس: اليوم + التاريخ + اسم العميل.
 * الأسفل: الإجمالي + الإجمالي بالريال السعودي + الوزن كم بالكيلو.
 */
function paintDistributionDaily(data) {
    if (!data) return;

    const tbody = document.getElementById('dashboardDistributionDaily');
    if (!tbody) return;

    // الرأس
    setText('distDayDate', data.date || '—');
    setText('distDayName', data.day_name || '—');
    setText('distCustomerName', data.customer?.name || 'كل العملاء');

    // حفظ قائمة عملاء اليوم (للاقتراحات في البحث النصي)
    DASH.dist.customers = Array.isArray(data.customers) ? data.customers : [];

    // عند تحديد عميل نكتب اسمه في حقل البحث، وإلا نفرّغه
    const filter = document.getElementById('distCustomerFilter');
    if (filter) {
        const selected = (data.customer && data.customer.id)
            ? DASH.dist.customers.find((c) => String(c.id) === String(data.customer.id))
            : null;
        filter.value = selected ? (selected.name || '') : '';
    }

    // صفوف الجدول
    tbody.replaceChildren();

    const rows = Array.isArray(data.rows) ? data.rows : [];

    if (rows.length === 0) {
        tbody.appendChild(buildEmptyRow(5, 'لا توجد عمليات توزيع في هذا اليوم'));
    } else {
        const fragment = document.createDocumentFragment();

        rows.forEach((row) => {
            const tr = document.createElement('tr');
            tr.className = 'text-center';

            [
                money(row.quantity),
                textOr(row.code),
                money(row.sale_price),
                money(row.line_total),
                textOr(row.type_name),
            ].forEach((cell) => {
                const td = document.createElement('td');
                td.textContent = cell;
                tr.appendChild(td);
            });

            fragment.appendChild(tr);
        });

        tbody.appendChild(fragment);
    }

    // سطر الإجماليات
    const totals = data.totals || {};
    setText('distTotalQty', Number(numOr(totals.total_qty)).toLocaleString('en-US', { maximumFractionDigits: 3 }));
    setText('distTotalAmount', money(totals.total_base));
    setText('distTotalSar', money(totals.total_sar));
    setText('distTotalKg', totals.total_kg != null ? Number(totals.total_kg).toFixed(3) : '0.000');

    const unknownEl = document.getElementById('distKgUnknown');
    if (unknownEl) unknownEl.classList.toggle('d-none', !totals.has_unknown_kg);

    const rateEl = document.getElementById('distSarRate');
    if (rateEl && data.sar_rate) rateEl.textContent = ` (سعر الصرف: ${data.sar_rate})`;

    // أزرار السهمين (تعطيل عند الأطراف)
    DASH.dist.customers = Array.isArray(data.customers) ? data.customers : [];
    const idx = DASH.dist.customers.findIndex((c) => String(c.id) === String(DASH.dist.customerId ?? ''));

    const prevBtn = document.getElementById('distCustomerPrevBtn');
    const nextBtn = document.getElementById('distCustomerNextBtn');
    if (prevBtn) prevBtn.disabled = idx <= 0;
    if (nextBtn) nextBtn.disabled = idx === -1 || idx >= DASH.dist.customers.length - 1;
}

/**
 * جلب بيانات جدول التوزيع اليومي.
 */
async function fetchDistributionDaily() {
    if (DASH.distController) DASH.distController.abort();
    DASH.distController = new AbortController();

    const params = new URLSearchParams({ date: DASH.dist.date });
    if (DASH.dist.customerId) params.set('customer_id', String(DASH.dist.customerId));

    try {
        const response = await fetch(`${DASH.distributionUrl}?${params.toString()}`, {
            method: 'GET',
            signal: DASH.distController.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || !payload.success) {
            console.error('[Dashboard] distribution daily failed', response.status, payload);
            return;
        }

        paintDistributionDaily(payload.data);
    } catch (error) {
        if (error.name === 'AbortError') return;
        console.error('[Dashboard] distribution daily', error);
    }
}

/**
 * ربط أحداث الفلتر والسهمين.
 */
function bindDistributionDaily() {
    const dateInput = document.getElementById('distDateFilter');
    const customerFilter = document.getElementById('distCustomerFilter');

    if (dateInput && !dateInput.dataset.bound) {
        dateInput.value = DASH.dist.date;
        dateInput.addEventListener('change', () => {
            DASH.dist.date = dateInput.value || DASH.dist.date;
            DASH.dist.customerId = null; // يوم جديد: أعد العرض لكل العملاء
            fetchDistributionDaily();
        });
        dateInput.dataset.bound = '1';
    }

    const todayBtn = document.getElementById('distDateTodayBtn');
    if (todayBtn && !todayBtn.dataset.bound) {
        todayBtn.addEventListener('click', () => {
            DASH.dist.date = new Date().toISOString().slice(0, 10);
            DASH.dist.customerId = null;
            if (dateInput) dateInput.value = DASH.dist.date;
            fetchDistributionDaily();
        });
        todayBtn.dataset.bound = '1';
    }

    /**
     * حقل بحث العميل: نص مكتوب + قائمة اقتراحات تُفلتر بالكتابة.
     * الاختيار من القائمة يثبّت العميل؛ مسح النص يعيد عرض كل العملاء.
     */
    if (customerFilter && !customerFilter.dataset.bound) {
        const suggestions = document.getElementById('distCustomerSuggestions');

        const hideSuggestions = () => {
            if (suggestions) suggestions.classList.add('d-none');
        };

        const renderSuggestions = () => {
            if (!suggestions) return;

            const term = (customerFilter.value || '').trim().toLowerCase();
            const matches = DASH.dist.customers.filter((c) =>
                term === '' || String(c.name || '').toLowerCase().includes(term)
            );

            suggestions.replaceChildren();

            // خيار "كل العملاء" يظهر أولًا دائمًا
            const allItem = document.createElement('button');
            allItem.type = 'button';
            allItem.className = 'list-group-item list-group-item-action text-center small';
            allItem.textContent = 'كل العملاء';
            allItem.addEventListener('mousedown', (e) => {
                e.preventDefault();
                customerFilter.value = '';
                DASH.dist.customerId = null;
                hideSuggestions();
                fetchDistributionDaily();
            });
            suggestions.appendChild(allItem);

            matches.forEach((c) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small';
                item.textContent = c.name || '—';
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    customerFilter.value = c.name || '';
                    DASH.dist.customerId = c.id;
                    hideSuggestions();
                    fetchDistributionDaily();
                });
                suggestions.appendChild(item);
            });

            suggestions.classList.toggle('d-none', suggestions.children.length === 0);
        };

        // الكتابة تُظهر القائمة مصفّاة بالكلمات المكتوبة
        customerFilter.addEventListener('input', renderSuggestions);

        customerFilter.addEventListener('focus', renderSuggestions);

        // المسح الكامل للنص يعيد كل العملاء فورًا
        customerFilter.addEventListener('input', () => {
            if ((customerFilter.value || '').trim() === '' && DASH.dist.customerId !== null) {
                DASH.dist.customerId = null;
                fetchDistributionDaily();
            }
        });

        customerFilter.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') hideSuggestions();
        });

        // إخفاء القائمة عند النقر خارجها
        document.addEventListener('click', (e) => {
            if (!customerFilter.contains(e.target) && !(suggestions && suggestions.contains(e.target))) {
                hideSuggestions();
            }
        });

        customerFilter.dataset.bound = '1';
    }

    // سهما اليوم (السابق/التالي)
    const shiftDay = (days) => {
        const d = new Date(DASH.dist.date + 'T00:00:00');
        d.setDate(d.getDate() + days);
        DASH.dist.date = d.toISOString().slice(0, 10);
        DASH.dist.customerId = null;
        if (dateInput) dateInput.value = DASH.dist.date;
        fetchDistributionDaily();
    };

    const prevDay = document.getElementById('distDayPrevBtn');
    const nextDay = document.getElementById('distDayNextBtn');
    if (prevDay && !prevDay.dataset.bound) {
        prevDay.addEventListener('click', () => shiftDay(-1));
        prevDay.dataset.bound = '1';
    }
    if (nextDay && !nextDay.dataset.bound) {
        nextDay.addEventListener('click', () => shiftDay(1));
        nextDay.dataset.bound = '1';
    }

    // سهما العميل داخل نفس اليوم
    const jump = (dir) => {
        const customers = DASH.dist.customers;
        if (!customers.length) return;

        let idx = customers.findIndex((c) => String(c.id) === String(DASH.dist.customerId ?? ''));
        idx = idx === -1
            ? (dir > 0 ? 0 : customers.length - 1)
            : Math.min(customers.length - 1, Math.max(0, idx + dir));

        DASH.dist.customerId = customers[idx].id;
        if (customerFilter) customerFilter.value = customers[idx].name || '';
        fetchDistributionDaily();
    };

    const prevCust = document.getElementById('distCustomerPrevBtn');
    const nextCust = document.getElementById('distCustomerNextBtn');
    if (prevCust && !prevCust.dataset.bound) {
        prevCust.addEventListener('click', () => jump(-1));
        prevCust.dataset.bound = '1';
    }
    if (nextCust && !nextCust.dataset.bound) {
        nextCust.addEventListener('click', () => jump(1));
        nextCust.dataset.bound = '1';
    }
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

    // 3) جدول التوزيع اليومي (شاشة توزيع الأصناف المشتراة)
    bindDistributionDaily();
    fetchDistributionDaily();

    // 4) تحديث عند العودة للتبويب/النافذة (بدل polling)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            fetchDashboard();
            fetchDistributionDaily();
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
