# مهارات سريعة: تطوير حقل الحساب الديناميكي

## 🎯 قواعد ذهبية

### 1. لا تقرأ الملفات من الصفر كل مرة
استخدم هذا الملف كمرجع. إذا احتجت قراءة، اقرأ فقط:
- `resources/js/reports/reportCenter.js` (السطور 800-1300)
- `app/Services/ChartAccountScope.php`
- `app/Http/Controllers/Reports/ReportCenterController.php`

### 2. بعد كل تعديل
```powershell
node --check resources\js\reports\reportCenter.js
php -l app\Http\Controllers\Reports\ReportCenterController.php
npm run build 2>&1 | Select-Object -Last 3
```

### 3. الاختبار في المتصفح
```powershell
Start-Process -WindowStyle Hidden -FilePath php -ArgumentList 'artisan','serve','--host=127.0.0.1','--port=8000'
# ثم استخدم browser-automation skill
```

---

## 🔧 أنماط الكود الجاهزة

### نمط 1: فحص حالة الحقل
```javascript
function rcFieldState(field) {
    const text = field.querySelector('input[type="text"]');
    const hidden = field.querySelector('input[type="hidden"]');
    if (!text.value) return 'IDLE';
    if (!hidden.value) return 'TYPING';
    return 'SELECTED';
}
```

### نمط 2: قفل ذكي
```javascript
function rcLockOtherFilters(activeField) {
    document.querySelectorAll('.rc-filter-field').forEach(f => {
        if (f !== activeField) {
            f.classList.add('rc-filter-disabled');
        }
    });
}
```

### نمط 3: مستمع keydown موحد
```javascript
function rcAttachKeydownHandler(input, mode) {
    input.addEventListener('keydown', (e) => {
        if (e.isComposing) return; // IME
        if (e.key === 'Enter') {
            e.preventDefault();
            rcOpenAccountPicker(mode, input.value);
        } else if (e.key === 'Tab' && input.value.length > 0) {
            e.preventDefault();
        } else if (e.key === 'Escape') {
            rcCancelPicker();
        }
    });
}
```

### نمط 4: فلترة الآباء client-side
```javascript
function rcFilterParents(query) {
    const q = (query || '').trim().toLowerCase();
    if (!q) return RC.pickerParents;
    return RC.pickerParents.filter(p =>
        String(p.accCode).includes(q) ||
        (p.accName || '').toLowerCase().includes(q)
    );
}
```

---

## ⚠️ أخطاء شائعة يجب تجنبها

| الخطأ | الصواب |
|-------|--------|
| استخدام `tinker --execute` | ملف PHP مؤقت |
| `blur` يفتح المودال تلقائياً | احفظ التركيز، لا تفتح |
| قفل كامل للصفحة | قفل حقول الفلاتر فقط |
| فلترة server-side مع 60 حساباً | client-side فوري |
| نسيان `e.preventDefault()` في Tab | التركيز سيهرب |

---

## 🧪 أوامر اختبار سريعة

### اختبار API الأب
```powershell
Invoke-RestMethod 'http://127.0.0.1:8000/reports/accounts' | ConvertTo-Json -Depth 2
```

### اختبار التقرير
```powershell
Invoke-RestMethod 'http://127.0.0.1:8000/reports/data/account-statement?account_parent=10' | Select-Object meta
```

### تشغيل build
```powershell
$env:Path = "C:\Program Files\nodejs;" + $env:Path
npm run build 2>&1 | Select-Object -Last 5
```

---

## 📊 خريطة الملفات

```
resources/js/reports/reportCenter.js
├── السطور 30-50: RC state
├── السطور 900-1100: rcBuildAccountPicker (تحتاج تعديل)
├── السطور 1100-1250: rcPickerParents, rcSelectPickerRow (تحتاج تعديل)
└── السطور 1250+: rcAttachEventListeners

app/Services/ChartAccountScope.php
├── eligible() — موجود
├── parents() — جديد
└── childrenOf() — جديد

app/Http/Controllers/Reports/ReportCenterController.php
└── accounts() — توسيع

resources/views/reports/accountPicker.blade.php
└── #rcAccountPickerModal — موجود، لا يحتاج تعديلاً كبيراً
```

---

## 🎬 سيناريو تنفيذ نموذجي

```
1. اقرأ هذا الملف كاملاً
2. اقرأ plan.md للأهداف
3. افتح ChartAccountScope → أضف parents() + childrenOf()
4. افتح ReportCenterController → حدّث accounts()
5. أعد تشغيل build للتأكد
6. افتح reportCenter.js → عدّل rcBuildAccountPicker
7. أضف مستمعي الأحداث
8. اختبر T15 في المتصفح
9. إن نجح → T16, T17...
10. إن فشل → راجع السطور 30-50 في RC
```

---

## 📌 عند العطل

### المشكلة: المودال لا يفتح
- تحقق من `RC.pickerMode` — هل يُضبط قبل `rcOpenAccountPicker`؟
- تحقق من `#rcAccountPickerModal` — هل مُدرج في `center.blade.php`؟

### المشكلة: الفلترة لا تعمل
- تحقق من `RC.pickerParents` — هل يحوي البيانات؟
- افتح Console → `RC.pickerParents.length`
- المتوقع: 5

### المشكلة: القفل لا يعمل
- تحقق من CSS: `body.rc-filter-locked`
- افتح Elements tab → ابحث عن الكلاس

### المشكلة: التركيز يهرب بعد الاختيار
- تحقق من `blur` handler
- يجب أن يكون: `if (!rcIsPickerOpen()) return;`

---

## 🎯 معايير النجاح

- [ ] T15-T25 كلها ✅
- [ ] لا console errors
- [ ] `npm run build` نظيف
- [ ] `php -l` نظيف
- [ ] لا regression على T1-T14
- [ ] 60 حساباً → المودال يفتح < 200ms
- [ ] الفلترة الفورية < 50ms
```
