

**المشكلة الحالية:** حقل "الحساب" في مركز التقارير يفتح المودال **بالنقر فقط**، ولا يقبل الكتابة، ولا يُفلتر بحسب نص المستخدم.

**المطلوب:** تجربة مستخدم ديناميكية تعمل بالكيبورد وماوس معاً، مع قفل ذكي يمنع فتح مودال فوق مودال.

**الملف الأساسي:** `resources/js/reports/reportCenter.js`
**الملف الخلفي:** `app/Http/Controllers/Reports/ReportCenterController.php`
**المساعد:** `app/Services/ChartAccountScope.php`

---

## الحقول الأربعة المتأثرة

| الحقل | المفتاح | الوضع |
|-------|---------|-------|
| الحساب (الأب) | `account_parent` | parent mode |
| من حساب | `account_from` | child mode |
| إلى حساب | `account_to` | child mode |
| من تاريخ / إلى تاريخ | `date_from` / `date_to` | **لا يتأثر** |

---

## آلة الحالة (State Machine) — لكل حقل

### الحالات (States)

```
IDLE          : الحقل فارغ، انتقال حر
TYPING        : فيه نص (حرف واحد+)، مقفل — لا انتقال
MODAL_OPEN    : المودال مفتوح، التركيز فيه
SELECTED      : اختُير حساب من المودال، القفل فُكَّ، النص مُعبَّأ
LOCKED_BY_PARENT : (لمن/إلى فقط) الأب لم يُختَر بعد — الحقل معطّل
```

### الانتقالات (Transitions)

| من | الحدث | إلى | الأثر |
|-----|-------|-----|-------|
| IDLE | كتابة حرف | TYPING | قفل التركيز + تمكين Enter |
| IDLE | Enter / دبل-كلك | MODAL_OPEN | فتح المودال بقائمة كاملة |
| TYPING | Enter / Tab / دبل-كلك | MODAL_OPEN | فتح المودال مع فلترة بالنص |
| TYPING | محو النص كاملاً | IDLE | فك القفل |
| MODAL_OPEN | اختيار حساب | SELECTED | تعبئة النص + فك القفل + التركيز يبقى |
| MODAL_OPEN | ESC أو X | IDLE | مسح الحقل + فك القفل |
| SELECTED | مسح النص يدوياً | IDLE | فك القفل |
| SELECTED | Enter / دبل-كلك | MODAL_OPEN | إعادة الفتح للتغيير |
| LOCKED_BY_PARENT | اختيار الأب | IDLE | تمكين الحقل |

---

## المنطق التفصيلي لكل حقل

### 1) حقل "الحساب" (parent mode)

**القائمة المعروضة في المودال:** كل الآباء القابلين للاختيار (الذين لديهم حفيد مؤهل واحد على الأقل). اليوم: 5 آباء.

**الفلترة الأولية:**
- فتح فارغ → كل الآباء
- فتح بعد كتابة "1211" → آباء يبدأ `accCode` بـ "1211"
- فتح بعد كتابة "صناديق" → آباء يحتوي `accName` على "صناديق"

**بعد الاختيار:**
- تعبئة الحقل بالنص `{accCode} - {accName}`
- حفظ `accountID` في الحقل المخفي
- **فك القفل** — يمكن الانتقال
- **التركيز يبقى في الحقل** — لا انتقال تلقائي
- **تمكين** حقلي "من حساب" و "إلى حساب"

### 2) حقلا "من حساب" و "إلى حساب" (child mode)

**الشرط المسبق:** الأب مُختار. خلافه الحقلان معطّلان.

**القائمة المعروضة:** أبناء الأب المختار فقط (لا غيرهم).

**الفلترة:** نفس منطق حقل الأب (بالكود أو الاسم).

**بعد الاختيار:** تعبئة + فك قفل + التركيز يبقى.

**ملاحظة:** بمجرد تعبئة "من حساب" → "إلى حساب" يُقفل (لا يُسمح إلا بحقل واحد منهما).

---

## القفل الذكي (Smart Lock)

**يمنع:** Tab/Click على أي حقل فلتر آخر (تواريخ، حقول أخرى).
**يسمح:** النقر على أزرار "عرض" / "طباعة" / "Excel" / القائمة الجانبية / التمرير.

**التنفيذ:**
- عند دخول حالة TYPING → `document.body.classList.add('rc-filter-locked')`
- عبر CSS: حقول الفلاتر الأخرى تحصل على `pointer-events: none; opacity: 0.5`
- زر "عرض" **يبقى نشطاً** في CSS (استثناء صريح)

**نموذج CSS:**
```css
body.rc-filter-locked .rc-filter-field:not(.rc-active-field) {
    pointer-events: none;
    opacity: 0.6;
}
```

---

## التعديلات الخلفية (Backend)

### 1) `ChartAccountScope` — إضافة دالتين

```php
/**
 * الآباء القابلون للاختيار (من له حفيد مؤهل)
 */
public static function parents(): Collection
{
    // الحسابات التي:
    // - isPostable = 0 (تجميعية)
    // - ليس لها system_key = 'inventory'
    // - لها حفيد واحد على الأقل isPostable=1 وغير مخزني
    // - مع accCode + accName
}

/**
 * أبناء أب معين (المؤهلون فقط)
 */
public static function childrenOf(int $parentId): Collection
{
    // where accParent = $parentId AND isPostable = 1
}
```

### 2) `ReportCenterController::accounts()` — البنية الجديدة

```php
return $this->ok([
    'parents'  => ChartAccountScope::parents(),    // ← جديد
    'eligible' => ChartAccountScope::eligible(),   // الأبناء المؤهلون
    'tree'     => $fullTree,                        // للتصنيف (system_key)
    'cached_at' => now()->toIso8601String(),
]);
```

**الحجم المتوقع:** ~60 حساباً → **client-side كافٍ**. لا حاجة server-side.

---

## التعديلات الأمامية (Frontend)

### دالة جديدة: `rcBuildAccountPicker(filter, id)`

```javascript
// تُنشئ HTML للحقل مع data-* attributes + مستمعي الأحداث
function rcBuildAccountPicker(filter, id) {
    const isParent = filter.key === 'account_parent';
    const mode = isParent ? 'parent' : 'child';
    
    return `
        <input type="hidden" id="rcf-${filter.key}" value="">
        <div class="input-group input-group-sm rc-filter-field rc-account-field"
             data-mode="${mode}"
             data-target="rcf-${filter.key}">
            <input type="text" class="form-control"
                   id="rcf-${filter.key}-text"
                   placeholder="${filter.label}..."
                   autocomplete="off"
                   ${isParent ? '' : 'disabled'}>
            <button type="button" class="btn btn-outline-secondary" data-rc-picker-x>
                <i class="bi bi-x-lg"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-rc-picker-open>
                <i class="bi bi-search"></i>
            </button>
        </div>
    `;
}
```

### الأحداث المطلوب ربطها

```javascript
// 1) keydown على الحقل الظاهر
input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        rcOpenAccountPicker(mode, input.value);
    } else if (e.key === 'Tab' && input.value.length > 0) {
        e.preventDefault(); // قفل
    } else if (e.key === 'Escape') {
        rcClearAccountField(field);
    }
});

// 2) input (كتابة/حذف)
input.addEventListener('input', () => {
    if (input.value.length > 0) {
        rcLockFilters(field);
    } else {
        rcUnlockFilters();
    }
});

// 3) dblclick
input.addEventListener('dblclick', () => {
    rcOpenAccountPicker(mode, input.value);
});

// 4) blur — منع الانتقال إن كان مقفلاً
input.addEventListener('blur', (e) => {
    if (input.value.length > 0 && !rcIsPickerOpen()) {
        setTimeout(() => input.focus(), 0); // إعادة التركيز
    }
});
```

### عند اختيار حساب من المودال

```javascript
function rcSelectPickerRow(account) {
    const field = document.querySelector(`[data-target="${RC.pickerTargetId}"]`);
    const text = document.getElementById(`${RC.pickerTargetId}-text`);
    const hidden = document.getElementById(RC.pickerTargetId);
    
    text.value = `${account.accCode} - ${account.accName}`;
    hidden.value = account.accountID;
    
    // فك القفل
    rcUnlockFilters();
    document.body.classList.remove('rc-filter-locked');
    
    // التمركز: التركيز يبقى في الحقل الحالي (لا انتقال تلقائي)
    text.focus();
    
    // إن كان "الحساب": تمكين "من/إلى"
    if (RC.pickerMode === 'parent') {
        rcEnableChildFields();
    }
    
    // إغلاق المودال
    bootstrap.Modal.getInstance(document.getElementById('rcAccountPickerModal')).hide();
}
```

### ESC / X داخل المودال

```javascript
function rcCancelPicker() {
    const field = document.querySelector(`[data-target="${RC.pickerTargetId}"]`);
    const text = document.getElementById(`${RC.pickerTargetId}-text`);
    const hidden = document.getElementById(RC.pickerTargetId);
    
    text.value = '';
    hidden.value = '';
    
    rcUnlockFilters();
    document.body.classList.remove('rc-filter-locked');
    
    text.focus();
}
```

---

## معالجة "المخزون" وحالات خاصة

### حين يُختار "المخزون" كأب

```javascript
// في rcSelectPickerRow — بعد الاختيار
if (account.accCode === '1104' || account.system_key === 'inventory') {
    // اقبل الاختيار
    // لكن قفل أزرار العمليات
    rcDisableActionButtons();
    rcShowEmptyStateMessage('لا توجد بيانات لعرضها لهذا الحساب');
}
```

**CSS للأزرار:**
```css
body.rc-empty-parent .rc-action-btn {
    pointer-events: none;
    opacity: 0.5;
}
```

### زر "عرض" مع حقول مكتملة جزئياً

| الحالة | السلوك |
|--------|--------|
| لا أب | رفض + تنبيه |
| أب فقط | تقرير لكل الأبناء |
| أب + من فقط | تقرير للحساب المحدد فقط (from=to) |
| أب + إلى فقط | من أول ابن إلى الـ "إلى" |
| أب + من + إلى (صحيح) | نطاق بين الاثنين |
| أب + من + إلى (مقلوب) | تبديل تلقائي |
| أب + من ثم محاولة تعبئة "إلى" | "إلى" يبقى مقفلاً |

---

## خطة الاختبار T15-T25

| # | السيناريو | المتوقع |
|---|-----------|---------|
| T15 | كتابة "1" في "الحساب" → Tab | لا انتقال + Enter يفتح المودال |
| T16 | فتح المودال فارغاً → عرض 5 آباء | ✅ |
| T17 | كتابة "11" → Enter | المودال يعرض آباء بـ accCode يبدأ "11" |
| T18 | كتابة "صناديق" → Enter | المودال يعرض "الصناديق" |
| T19 | اختيار "الصناديق" | تعبئة + فك قفل + تمكين "من/إلى" |
| T20 | بعد T19، الانتقال لـ "من حساب" | مسموح |
| T21 | كتابة "ب" في "من حساب" → Enter | يعرض أبناء الصندوق فقط |
| T22 | ESC في المودال | مسح الحقل + فك القفل |
| T23 | اختيار "المخزون" | قبول + قفل الأزرار + رسالة |
| T24 | "من" مملوء → محاولة ملء "إلى" | "إلى" مقفل |
| T25 | دبل-كلك على حقل مملوء | إعادة فتح المودال |

---

## ما لا يجب تغييره

- `AccountStatementReport::run()` — يعمل بشكل صحيح
- `ReportEngine::sanitizeFilters` — صفر تعديل
- `print.blade.php` — صفر تعديل
- `ChartAccountScope::eligible()` — يبقى كما هو (يُستخدَم في run())

---

## المخاطر

| # | الخطر | التخفيف |
|---|-------|---------|
| 1 | القفل الذكي يمنع الوصول للقائمة الجانبية | استثناء صريح في CSS |
| 2 | blur يعيد التركيز → حلقة لا نهائية | فحص `rcIsPickerOpen()` |
| 3 | التركيز يضيع عند إعادة فتح المودال | حفظ `RC.pickerTargetId` قبل الفتح |
| 4 | نظام الإدخال السريع (IME) | فحص `e.isComposing` في keydown |
| 5 | كيبورد عربي (RTL) | اختبار مع لوحة عربية |
```
