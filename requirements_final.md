# 📋 وثيقة المتطلبات النهائية — نظام qaatSystem ERP
**الإصدار:** 1.0 النهائي | **التاريخ:** 2026-09-04 | **الحالة:** ✅ مؤكدة — جاهزة للتنفيذ

---

## 🏗️ معايير توحيد هيكلية الكود (القاعدة الإلزامية)

> **هذه المعايير تطبق على كل شاشة بدون استثناء قبل البدء بأي تعديل**

### 1. متغيرات كل شاشة (State Variables)
```javascript
/* ---- يُعلَن في أعلى ملف JS الخاص بكل شاشة ---- */
let screenMode  = 'view';   // 'view' | 'add' | 'edit'
let activeRow   = null;     // الصف النشط في التفاصيل (للنوافذ المنبثقة)
let currentData = {};       // بيانات السجل المعروض
```

### 2. نمط النوافذ المنبثقة (Modal Pattern)
**قاعدة ثابتة:** كل نافذة منبثقة تُعرَّف مرة واحدة في ملف Blade ولا تُبنى ديناميكياً بـ JS.

```
HTML (blade) ──► نافذة ثابتة بـ id فريد
JS            ──► let xyzModal = null;  // يُهيَّأ في DOMContentLoaded
```

**بنية النافذة الموحدة:**
```html
<div class="modal fade" id="[prefix]Modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-[icon]"></i> [العنوان]</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- حقل بحث -->
        <div class="row g-2 mb-3">
          <div class="col-md-10">
            <input id="[prefix]SearchInput" class="form-control" placeholder="...">
          </div>
          <div class="col-md-2">
            <button onclick="search[Prefix]()" class="btn btn-primary w-100">بحث</button>
          </div>
        </div>
        <!-- جدول النتائج -->
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle text-center">
            <thead class="table-light"><tr>...</tr></thead>
            <tbody id="[prefix]Results"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
```

**دوال JS الموحدة لكل نافذة:**
```javascript
// 1. تهيئة في DOMContentLoaded
xyzModal = new bootstrap.Modal(document.getElementById('[prefix]Modal'));

// 2. فتح النافذة
function open[Prefix]Modal(sourceInput) {
    activeTargetField = sourceInput;
    document.getElementById('[prefix]SearchInput').value = sourceInput?.value || '';
    [prefix]Modal.show();
    setTimeout(() => {
        document.getElementById('[prefix]SearchInput').focus();
        search[Prefix]();
    }, 300);
}

// 3. ربط الحقول (Enter / blur)
function [prefix]KeyDown(event) {
    if (event.key === 'Enter' || event.key === 'Tab') {
        event.preventDefault();
        open[Prefix]Modal(event.target);
    }
}
function [prefix]Blur(event) {
    if (screenMode !== 'view') {
        const active = document.activeElement.id;
        if (active !== '[prefix]SearchInput') {
            setTimeout(() => open[Prefix]Modal(event.target), 200);
        }
    }
}

// 4. البحث
function search[Prefix]() { /* منطق البحث */ }

// 5. الاختيار
function select[Prefix](id, name, ...extras) {
    if (activeTargetField) activeTargetField.value = name;
    // ملء الحقول الإضافية
    [prefix]Modal.hide();
}
```

### 3. نمط وضع الشاشة (Mode Pattern)
```javascript
const SCREEN_CONFIG = {
    editableFields: ['field1', 'field2', ...], // IDs الحقول القابلة للتعديل
    requiredFields: [
        { id: 'field1', label: 'اسم الحقل' },
        ...
    ]
};

function setMode(mode) {
    screenMode = mode;
    SCREEN_CONFIG.editableFields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = (mode === 'view');
    });
    updateButtons(mode);
}

function updateButtons(mode) {
    const btnSave   = document.getElementById('btn[X]Save');
    const btnCancel = document.getElementById('btn[X]Cancel');
    const btnEdit   = document.getElementById('btn[X]Edit');
    const btnPrint  = document.getElementById('btn[X]Print');
    // ... منطق الأزرار
}
```

### 4. نمط الحفظ مع التحقق (Validation Pattern)
```javascript
function validateForm() {
    const errors = [];
    SCREEN_CONFIG.requiredFields.forEach(field => {
        const el = document.getElementById(field.id);
        if (!el || !el.value.trim()) errors.push(field.label);
    });
    if (errors.length > 0) {
        alert('الحقول التالية مطلوبة:\n' + errors.join('\n'));
        return false;
    }
    return true;
}
```

### 5. نمط التحذير عند التنقل (Navigation Guard Pattern)
```javascript
/* في app.blade.php — نافذة واحدة مشتركة */
let pendingNavUrl = null;

function interceptNavigation(url) {
    if (typeof screenMode !== 'undefined' && screenMode !== 'view') {
        pendingNavUrl = url;
        navigationWarningModal.show();
        return;
    }
    window.location.href = url;
}

// تلتقط كل روابط السايدبار
document.querySelectorAll('#sidebar a[href]').forEach(link => {
    link.addEventListener('click', e => {
        if (typeof screenMode !== 'undefined' && screenMode !== 'view') {
            e.preventDefault();
            interceptNavigation(link.href);
        }
    });
});

// حماية زر الرجوع في المتصفح
window.addEventListener('beforeunload', e => {
    if (typeof screenMode !== 'undefined' && screenMode !== 'view') {
        e.preventDefault();
        e.returnValue = '';
    }
});
```

### 6. نمط الترقيم التلقائي (Auto-Number Pattern)
```javascript
/* دالة مشتركة لتوليد الرقم التالي */
function getNextNumber(prefix, records) {
    if (!records || records.length === 0) return `${prefix}-0001`;
    const maxId = Math.max(...records.map(r => r.id));
    return `${prefix}-${String(maxId + 1).padStart(4, '0')}`;
}
// مثال: getNextNumber('PUR', invoices) → 'PUR-0004'
// رقم بحت: فقط maxId + 1
```

---

## 📋 المرحلة الأولى — أولوية عالية جداً

### ✅ [M1-01] الترقيم التلقائي لجميع شاشات الإدخال

**الشاشات المستهدفة:**

| الشاشة | حقل الرقم | البادئة | الملاحظة |
|---|---|---|---|
| فواتير الشراء | `PurchaseInvoicesON2` | بحت (1, 2, 3...) | disabled دائماً |
| فواتير البيع | رقم مشابه في header.blade | بحت | disabled دائماً |
| سند الصرف | يُعرض في badge `VoucherNumberDisplay` | بحت | |
| سند القبض | مشابه | بحت | |
| حركة المخزون | `movementDisplayId` | بحت | |

**السلوك:**
- يُولَّد تلقائياً عند الضغط على زر "إضافة" (setMode → add)
- `maxId + 1` من مصفوفة البيانات (حالياً) أو قاعدة البيانات (لاحقاً)
- الحقل دائماً `disabled` (لا يُعدَّل يدوياً)
- يظهر فوراً قبل الحفظ

---

### ✅ [M1-02] شاشة البنوك — إضافة حقول جديدة

**الحقول الجديدة في نموذج إضافة/تعديل بنك:**

| الحقل | النوع | الإلزامية | الوصف |
|---|---|---|---|
| اسم البنك | text | ✅ | موجود |
| رقم الحساب (رقم الحساب البنكي) | text | ✅ | **جديد** |
| حساب في دليل الحسابات | text + hidden | ✅ | **جديد** — نافذة منبثقة من دليل الحسابات |
| عملة الحساب | text + hidden | ✅ | **جديد** — نافذة منبثقة من جدول العملات |

**عرض الجدول بعد الإضافة:**

| # | رقم | اسم البنك | رقم الحساب | الحساب في الدليل | العملة | إجراءات |
|---|---|---|---|---|---|---|

---

### ✅ [M1-03] أزرار الطباعة للشاشات التي تفتقرها

**الشاشات التي تحتاج زر طباعة (باستثناء حركة المخزون):**

| الشاشة | الوصف |
|---|---|
| الموردين | طباعة قائمة الموردين |
| العملاء | طباعة قائمة العملاء |
| الأصناف | طباعة قائمة الأصناف |
| الأنواع | طباعة قائمة الأنواع |
| الوحدات | طباعة قائمة الوحدات |
| المخازن | طباعة قائمة المخازن |
| العملات | طباعة قائمة العملات |
| الأرصدة الافتتاحية | طباعة الأرصدة |

**نمط الطباعة الموحد:** نافذة print جديدة بـ RTL + تاريخ الطباعة + جدول البيانات.

---

### ✅ [M1-04] حقل رقم الحساب التحليلي في شاشات الموردين والعملاء والصناديق

**الموردين — حقول جديدة في نموذج الإضافة:**
- رقم الحساب التحليلي (نافذة منبثقة من دليل الحسابات)

**العملاء — حقول جديدة في نموذج الإضافة:**
- رقم الحساب التحليلي (نافذة منبثقة من دليل الحسابات)

**الصناديق — حقول جديدة في نموذج الإضافة:**
- رقم الحساب التحليلي (نافذة منبثقة من دليل الحسابات)

---

### ✅ [M1-05] شاشة البنوك — حقلي الحساب في الدليل ورقم الحساب والعملة

**مكرر ضمن [M1-02] — راجع التفصيل أعلاه**

---

## 📋 المرحلة الثانية — فواتير الشراء

### ✅ [M2-01] نافذة الصنف المنبثقة الموحدة

**المبدأ:** نافذة أصناف واحدة مشتركة لجميع الشاشات، تُضمَّن في [operation/models/item.blade.php](file:///c:/Laravel/qaatSystem/resources/views/operation/models/item.blade.php).

**الفرق بين شاشتي البيع والشراء:**

| العنصر | فواتير البيع | فواتير الشراء |
|---|---|---|
| الحقول المعروضة | الصنف، النوع، الرمز، الكمية، سعر الوحدة، **سعر البيع** | الصنف، النوع، الرمز، الكمية، سعر الوحدة |
| دالة الاختيار | [selectSalesItem()](file:///c:/Laravel/qaatSystem/public/js/purchase_invoice.js#1456-1478) | `selectPurchItem()` |
| المتغير النشط | `activeSalesRow` | `activePurchRow` |

**الاستدعاء الموحد:** كلتا الشاشتين تستدعيان `openItemModal(row, type)` حيث `type = 'purchase'` أو `'sale'`.

---

### ✅ [M2-02] الوحدة الافتراضية "كيلو" وحذف خيار الفراغ

**في [addInvoiceRow()](file:///c:/Laravel/qaatSystem/public/js/purchase_invoice.js#380-578):**
- إزالة `<option value="">الوحدة</option>`
- جعل "كيلو" أول خيار ومحدداً بـ `selected`
- استدعاء [unitChanged(select)](file:///c:/Laravel/qaatSystem/public/js/purchase_invoice.js#704-750) تلقائياً بعد إضافة الصف
- الوحدات تُجلب من مصفوفة ثابتة مؤقتاً (كيلو، حبه) مع إمكانية ربطها بقاعدة البيانات لاحقاً

---

### ✅ [M2-03] حقل البنك/الشبكة بنافذة منبثقة تلقائية

**عند اختيار "تحويل بنكي":**
- يظهر حقل نصي `bankAccountName` مع hidden `bankAccountId`
- عند الكتابة + Enter/Tab/blur → تفتح نافذة البنوك المنبثقة تلقائياً
- إلزامي (لا يُحفظ بدونه)

**عند اختيار "عبر شبكة":**
- يظهر حقل نصي `walletAccountName` مع hidden `walletAccountId`
- يفتح نافذة "المحافظ الإلكترونية" (تحتوي: اسم المحفظة/البنك + رقم الحساب)
- إلزامي

**عند اختيار "نقد":**
- يظهر حقل نصي `cashAccountName` مع hidden `cashAccountId`
- يفتح نافذة الصناديق المنبثقة
- إلزامي

**انعدام الزر:** لا توجد أزرار لفتح النوافذ — تفتح تلقائياً بالكتابة + Enter/Tab/blur فقط.

---

### ✅ [M2-04] الأسعار مع العملة

**السلوكيات المطلوبة (كلاهما):**
1. عند اختيار عملة → يُعرض رمزها بجانب: سعر الوحدة، الإجمالي، الإجماليات
2. عند تغيير سعر الصرف يدوياً → تُعاد حسابات الإجماليات بالعملة الأساسية

**التطبيق:**
- إضافة `<span class="currency-symbol ms-1">—</span>` بجانب حقول الأسعار
- تحديثه في [selectCurrency()](file:///c:/Laravel/qaatSystem/public/js/purchase_invoice.js#1209-1229) بـ رمز العملة المختارة

---

### ✅ [M2-05] شروط الحفظ الإلزامية

**الحقول الإلزامية:**
1. رقم الفاتورة (تلقائي — لا تحقق)
2. التاريخ
3. طريقة الدفع
4. الحساب المرتبط بطريقة الدفع (صندوق/بنك/محفظة) ← **إلزامي بحسب الاختيار**
5. المورد
6. العملة
7. المخزن
8. تفاصيل أصناف (صنف واحد ↑ مع وزن/عدد > 0)

**الحقول الاختيارية:** البيان، المرجع، النفقات، تكلفة الضريبة، تكلفة النقل، تكاليف أخرى، وصف التكلفة.

---

### ✅ [M2-06] إصلاح خطأ البحث (loadInvoice)

**السبب:** [loadInvoice(id)](file:///c:/Laravel/qaatSystem/public/js/purchase_invoice.js#2113-2230) لا تستخدم [id](file:///c:/Laravel/qaatSystem/public/js/purchase_invoice.js#804-821) — تُحمّل دائماً PUR-0001.

**الإصلاح:**
```javascript
function loadInvoice(invoiceId) {
    // البحث في مصفوفة الفواتير بـ invoiceId
    const invoice = invoices.find(inv => inv.id === invoiceId);
    if (!invoice) return;
    // ملء الحقول من invoice وليس قيم صلبة
    clearInvoiceForm();
    fillInvoiceForm(invoice);
    loadInvoiceDetails(invoice.details);
    invoiceSearchModal.hide();
    setMode('view');
}
```

---

### ✅ [M2-07] رسالة التحذير عند مغادرة الشاشة

**التطبيق في [app.blade.php](file:///c:/Laravel/qaatSystem/resources/views/layouts/app.blade.php):**
نافذة Bootstrap Modal واحدة مشتركة:
```html
<div class="modal fade" id="navigationWarningModal" data-bs-backdrop="static" ...>
  <!-- رسالة: يوجد فاتورة/سجل قيد الإدخال، هل تريد التراجع؟ -->
  <!-- زر "إلغاء": data-bs-dismiss="modal" — يُكمل الإدخال -->
  <!-- زر "تراجع": يُلغي الإدخال ويتنقل لـ pendingNavUrl -->
</div>
```

**الربط:**
- جميع روابط السايدبار → `interceptNavigation(href)`
- `window.beforeunload` → تحذير المتصفح الافتراضي

---

## 📋 المرحلة الثالثة — حركة المخزون

### ✅ [M3-01] إعادة هيكلة شاشة حركة المخزون

**التغييرات على [head.blade.php](file:///c:/Laravel/qaatSystem/resources/views/operation/movements/head.blade.php):**
- حذف حقل "المخزن" من الرأس (سيكون في التفاصيل لكل صف)
- إضافة حقل "نوع السند" (input + نافذة منبثقة)

**أنواع السندات:**

| الكود | النوع | نافذة رقم المستند |
|---|---|---|
| `purchase` | فاتورة شراء | نافذة فواتير الشراء |
| [sale](file:///c:/Laravel/qaatSystem/public/js/purchase_invoice.js#1276-1297) | فاتورة بيع | نافذة فواتير البيع |
| `purchase_return` | مرتجع شراء | نافذة مردود المشتريات |
| `sale_return` | مرتجع بيع | نافذة مردود المبيعات |
| `supply_direct` | أمر توريد مباشر | لا نافذة — يدوي |
| `issue_direct` | أمر صرف مباشر | لا نافذة — يدوي |
| `transfer` | تحويل مخزني | نافذة مخازن (من/إلى) |

---

### ✅ [M3-02] التفعيل التلقائي عند أوامر التوريد/الصرف المباشر

- عند اختيار `supply_direct` أو `issue_direct` → تُفعَّل حقول الإدخال اليدوي في التفاصيل
- النوافذ المنبثقة تعمل: الصنف، النوع، المخزن (لكل صف)، الوحدة
- حقول الكمية والسعر يدوية

---

### ✅ [M3-03] عند اختيار فاتورة كمستند → ملء التفاصيل تلقائياً

- بعد اختيار الفاتورة من النافذة المنبثقة:
  - تُملأ حقول الرأس: التاريخ، البيان، نوع الحركة تلقائياً
  - تُملأ صفوف التفاصيل من تفاصيل الفاتورة (الأصناف والكميات والأسعار)
  - مع إمكانية تعديل أي صف بعد الملء

---

### ✅ [M3-04] المخزن في التفاصيل (لكل صف)

- حذف حقل المخزن من رأس الحركة
- إضافة عمود "المخزن" لكل صف في التفاصيل
- النافذة المنبثقة لاختيار المخزن تظهر تلقائياً عند الكتابة + Enter/blur

---

### ✅ [M3-05] البحث في نافذة منبثقة

- تحويل شاشة البحث الحالية ([search.blade.php](file:///c:/Laravel/qaatSystem/resources/views/setting/customers/search.blade.php)) إلى نافذة Bootstrap Modal
- زر "بحث" في رأس الشاشة يفتح النافذة
- عند اختيار حركة → إغلاق النافذة + عرض التفاصيل في الشاشة الرئيسية

---

### ✅ [M3-06] الحركة التلقائية عند حفظ فاتورة (JavaScript Mock)

**عند [saveInvoice()](file:///c:/Laravel/qaatSystem/public/js/purchase_invoice.js#1835-1898) في فاتورة الشراء:**
```javascript
// إنشاء حركة مخزنية تلقائية
const movement = {
    id: getNextMovementId(),
    type: 'purchase',
    direction: 'in',
    date: invoiceData.date,
    documentNumber: invoiceData.number,
    details: invoiceData.details.map(row => ({
        item: row.item,
        type: row.type,
        warehouse: invoiceData.warehouse,
        unit: row.unit,
        quantity: row.quantity,
        costPrice: row.price,
        salePrice: 0
    }))
};
movementsStore.push(movement);
```

---

## 📋 الملفات المتأثرة بكل مرحلة

### المرحلة الأولى [M1]
```
public/js/banks.js                                    ← إضافة حقول + modal للبنوك
resources/views/setting/accounting/banks/index.blade.php ← إضافة أعمدة للجدول
resources/views/setting/suppliers/index.blade.php      ← حقل الحساب التحليلي
resources/views/setting/customers/index.blade.php      ← حقل الحساب التحليلي
resources/views/setting/accounting/boxes/index.blade.php ← حقل الحساب التحليلي
public/js/supplier.js                                 ← حقل الحساب التحليلي
public/js/customer.js                                 ← حقل الحساب التحليلي
public/js/boxes.js                                    ← حقل الحساب التحليلي
resources/views/layouts/app.blade.php                 ← شاشة حراسة التنقل (M2-07 يبدأ هنا)
```

### المرحلة الثانية [M2]
```
public/js/purchase_invoice.js          ← إعادة هيكلة كاملة (تقسيم وتصحيح)
resources/views/operation/purchases/invoicesPurch/index.blade.php
resources/views/operation/purchases/invoicesPurch/haed.blade.php
resources/views/operation/purchases/invoicesPurch/detals.blade.php
resources/views/operation/models/item.blade.php        ← نافذة أصناف موحدة
resources/views/operation/models/bank.blade.php        ← جديد: نافذة البنوك
resources/views/operation/models/wallet.blade.php      ← جديد: نافذة المحافظ
resources/views/operation/models/box.blade.php         ← جديد: نافذة الصناديق
```

### المرحلة الثالثة [M3]
```
public/js/movements.js                 ← إعادة هيكلة
resources/views/operation/movements/head.blade.php     ← حذف مخزن + إضافة نوع السند
resources/views/operation/movements/details.blade.php  ← إضافة عمود المخزن
resources/views/operation/movements/search.blade.php   ← تحويل لـ Modal
resources/views/operation/movements/index.blade.php    ← إضافة Modals
```

---

## 📌 الترتيب الزمني للتنفيذ

```
الخطوة 1 ── [M1-02] شاشة البنوك (حقول جديدة + Modal نموذج)
الخطوة 2 ── [M1-04] حقل الحساب التحليلي (موردين + عملاء + صناديق)
الخطوة 3 ── [M1-01] الترقيم التلقائي (كل الشاشات)
الخطوة 4 ── [M1-03] أزرار الطباعة (الشاشات الناقصة)
الخطوة 5 ── [M2-07] نافذة حراسة التنقل (app.blade.php)
الخطوة 6 ── [M2-06] إصلاح خطأ البحث (loadInvoice)
الخطوة 7 ── [M2-01] نافذة الصنف الموحدة
الخطوة 8 ── [M2-02] الوحدة الافتراضية
الخطوة 9 ── [M2-03] نوافذ البنك/الشبكة/الصندوق التلقائية
الخطوة 10 ─ [M2-04] الأسعار مع العملة
الخطوة 11 ─ [M2-05] شروط الحفظ الكاملة
الخطوة 12 ─ [M3-05] بحث حركة المخزون → Modal
الخطوة 13 ─ [M3-01,02] نوع السند + تفعيل التوريد/الصرف
الخطوة 14 ─ [M3-03,04] ملء تلقائي + مخزن في التفاصيل
الخطوة 15 ─ [M3-06] الحركة التلقائية من الفواتير
```

---

## ⚠️ قرارات تقنية مهمة

| القرار | التفاصيل |
|---|---|
| **لا JS ديناميكي للنوافذ** | كل نافذة منبثقة مُعرَّفة في Blade، لا تُبنى بـ innerHTML |
| **متغير واحد للوضع** | `screenMode` في كل شاشة، لا `salesInvoiceMode` داخل ملفات أخرى |
| **بيانات mock** | تُستخدم مصفوفات JS مؤقتة قابلة للاستبدال بـ fetch API |
| **الوحدات مؤقتة** | (كيلو، حبه) في code حتى يتم ربط قاعدة البيانات |
| **حركة المخزون** | تُنشأ في JS الآن مع إمكانية نقلها للـ backend لاحقاً |
| **مخزن الحركة** | يُحذف من الرأس ويُنقل لكل صف في التفاصيل |
