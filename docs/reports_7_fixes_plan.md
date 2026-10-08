
# خطة: التعديلات الستة المتبقية — دفعات

## القيود الحرجة (regressions — لا تُعاد)

| القيد | السبب |
|-------|-------|
| `$isDebitNature` يبقى `!== 1` | إصلاح regression |
| `$isDebit` يبقى `!== 1` | نفس السبب |
| لا `account_type` بـ `nature` | يصنّف العملاء والموردين معاً خطأً |
| لا تكرار `account_id` | تعريف واحد فقط |
| التصنيف: `system_key` (customers/suppliers) | لا nature |

---

## قواعد الألوان (نهائية)

```css
.amount-debit { color: #dc3545; font-weight: 600; } /* مدين */
.amount-credit { color: #198754; font-weight: 600; } /* دائن */
.amount-pos { color: #198754; font-weight: 600; }    /* رصيد موجب */
.amount-neg { color: #dc3545; font-weight: 600; }    /* رصيد سالب */
```

**تطبيق:**
- خلايا الأرقام **فقط** — لا الصف كامل
- كل تقرير يستخدم الـ classes → يحصل على الألوان
- يُطبَّق على أي تقرير مستقبلي

---

## الدفعة 1 (الأقل خطورة)

### التعديل #7: الألوان

**الملفات:**
- `resources/css/reports/report-center.css` — إضافة CSS
- `resources/views/reports/print.blade.php` — إضافة `class` لكل خلية رقم

**قبل التنفيذ:** اقرأ `print.blade.php` — كيف تُرسم الخلايا؟
- اذكر السطر الذي يرسم `debit`
- اذكر السطر الذي يرسم `credit`
- اذكر السطر الذي يرسم `balance`

**التنفيذ:**
- لكل خلية رقم، أضف class مناسب:
  ```blade
  <td class="amount-debit">{{ number_format($row['debit'], 2) }}</td>
  <td class="amount-credit">{{ number_format($row['credit'], 2) }}</td>
  <td class="{{ $row['balance'] < 0 ? 'amount-neg' : 'amount-pos' }}">
      {{ number_format($row['balance'], 2) }}
  </td>
  ```

**تحقق:** افتح الطباعة في المتصفح — الألوان تظهر في خلايا الأرقام فقط.

---

### التعديل #4: التوقيعات

**الملف:** `resources/views/reports/print.blade.php`

**قبل التنفيذ:** اقرأ قسم التواقيع الحالي — اذكر:
- الأسماء الحالية
- السطر
- الـ HTML

**التنفيذ:** استبدل بـ:
```blade
<div class="signatures-row">
    <div class="signature-box">
        <div class="sig-line"></div>
        <div class="sig-label">المحاسب</div>
    </div>
    <div class="signature-box">
        <div class="sig-line"></div>
        <div class="sig-label">المراجع</div>
    </div>
    <div class="signature-box">
        <div class="sig-line"></div>
        <div class="sig-label">مدير الحسابات</div>
    </div>
</div>
```

**تحقق:** افتح الطباعة — ثلاث توقيعات بالأسماء المطلوبة.

---

## الدفعة 2

### التعديل #5: التذييل من فاتورة البيع

**قبل التنفيذ — اقرأ أولاً:**
```powershell
Get-ChildItem c:\Laravel\qaatSystem\resources\views -Recurse -Filter "*sale*.blade.php"
Get-ChildItem c:\Laravel\qaatSystem\resources\views -Recurse -Filter "*print*invoice*.blade.php"
```

**المطلوب قبل التنفيذ:**
- مسار قالب فاتورة البيع (بالتحديد)
- محتوى قسم التذييل فيه
- الفرق بينه وبين تذييل `print.blade.php` الحالي

**التنفيذ:** نسخ التذييل مع تعديل القالب ليعمل مع كشف الحساب.

---

### التعديل #2: كشف العملاء/الموردين

**الطريقة:** فلتر select بـ `system_key`:

```php
[
    'key'     => 'account_group',
    'label'   => 'نطاق الحسابات',
    'type'    => 'select',
    'options' => [
        ''          => 'كل الحسابات',
        'customers' => 'العملاء فقط',
        'suppliers' => 'الموردون فقط',
        'boxes'     => 'الصناديق فقط',
        'banks'     => 'البنوك فقط',
    ],
    'col'     => 'col-md-4',
]
```

**منطق التصفية في `run()`:**
- يُصفّى `$scope` بالصعود الشجري بحثاً عن `system_key`
- **لا** يعتمد على `nature`

**الملفات:**
- `app/Reports/Accounting/AccountStatementReport.php`
- `app/Services/ChartAccountScope.php` — قد تحتاج دالة `groupOf()`

**تحقق:**
- `?account_parent=all&account_group=customers` → العملاء فقط
- `?account_parent=all&account_group=suppliers` → الموردون فقط

---

## الدفعة 3

### التعديل #3: حقل البيان

**قبل التنفيذ — اقرأ:**
1. `app/Services/PaymentVoucherService.php` — كيف يُنشئ `Journal_Entries`؟
2. `app/Services/ReceiptVoucherService.php` — نفس السؤال
3. `app/Services/SalesInvoiceService.php` — أين البيان؟
4. `app/Services/Purchases/PurchaseInvoiceService.php` — أين البيان؟
5. `app/Models/Accounting/JournalEntry.php` — علاقات `source_type` / `source_id`

**المنطق:**
- `Journal_Entries.description2` = الافتراضي
- إن وُجد `source_type` + `source_id` → اقرأ البيان من المستند المصدر
- البيان من المصدر أولى

**السؤال الحرج:** ما هي أعمدة البيان في كل جدول مصدر؟
- `PaymentVoucher.description`?
- `ReceiptVoucher.description`?
- `SalesInvoice.notes`? `description`?
- `PurchaseInvoice.notes`?

**يجب الإجابة قبل التنفيذ.**

---

### التعديل #6: تقارير فواتير البيع والشراء

**قبل التنفيذ — اقرأ:**
1. `app/Reports/Sales/SalesInvoicesReport.php`
2. `app/Reports/Purchases/PurchaseInvoicesReport.php`
3. `app/Reports/ReportRegistry.php`
4. `app/Reports/Vouchers/VouchersReport.php` (نموذج ناجح)

**المطلوب:**
- التأكد أنهما مسجّلان في `ReportRegistry`
- الأعمدة كاملة: `invoice_no`, `date`, `party`, `subtotal`, `discount`, `total`, `status`
- فلترة: `date_from`, `date_to`, `status`
- ألوان (ترث من الدفعة 1)

---

## ترتيب التنفيذ (دفعات)

| الدفعة | التعديلات | اختبار بعدها |
|--------|-----------|---------------|
| **1** | #7 (ألوان) + #4 (توقيعات) | افتح الطباعة — تحقق بصرياً |
| **2** | #5 (تذييل) + #2 (كشف الجميع) | T2, T4, T8 (regression) + لقطة التذييل |
| **3** | #3 (بيان) + #6 (تقارير) | كشف 3 سندات + افتح التقارير الجديدة |

**بين كل دفعة:** التوقف + تقرير + موافقة.

---

## قواعد صارمة

- ❌ لا `&&` في PowerShell → استخدم `;`
- ❌ لا `grep` → استخدم `Select-String`
- ❌ لا `cat` → استخدم `Get-Content`
- ❌ لا `tinker --execute` → ملف PHP مؤقت
- ✅ قبل كل دفعة: نسخة احتياطية للملفات
- ✅ بعد كل تعديل: `php -l` أو `node --check`
- ✅ **أعلن قبل التنفيذ**
- ✅ **التزم بحد 2 تعديلات في الدفعة**

---

## نقاط التوقف الإلزامية

بعد كل دفعة:
1. اختبار الميزة الجديدة
2. اختبار regression (T2, T4, T8)
3. `npm run build`
4. `php artisan view:cache` (إن عدّلت Blade)
5. تقرير قصير + انتظار الموافقة
```

---

## 📄 رسالة البدء إلى GLM

انسخ والصق:

---

**مهمة: تنفيذ خطة التعديلات على مركز التقارير — بالدفعات**

**السياق:**
- التعديل الأول (طبيعة nature) أُصلح في جلسة سابقة ✅
- 6 تعديلات متبقية — تُنفَّذ في **3 دفعات × 2 تعديلات**
- جلسة سابقة (Cline) فشلت — أنتجت أكواداً خاطئة وألواناً معكوسة

**المطلوب فوراً — قبل أي تعديل:**

1. اقرأ `docs/reports_7_fixes_plan.md` كاملاً
2. اقرأ `resources/views/reports/print.blade.php` كاملاً
3. اقرأ `resources/css/reports/report-center.css` كاملاً
4. أعلن ما يلي:

**للتعديل #7 (الألوان):**
- كيف تُرسم خلايا `debit` / `credit` / `balance` في `print.blade.php`؟
- أرقام السطور الدقيقة
- هل هناك class موجود مسبقاً؟

**للتعديل #4 (التوقيعات):**
- ما هو HTML قسم التواقيع الحالي؟
- ما الأسماء الحالية؟

5. **توقف وانتظر موافقتي** قبل البدء في الدفعة 1.

**بعد موافقتي على التنفيذ:**

**الدفعة 1:** نفّذ #7 + #4 → اختبر → أعلن → توقف
**الدفعة 2:** نفّذ #5 + #2 → اختبر → أعلن → توقف
**الدفعة 3:** نفّذ #3 + #6 → اختبر → أعلن → انتهت

**قواعد صارمة:**
- ❌ لا `&&` في PowerShell — استخدم `;`
- ❌ لا `grep` / `cat`
- ❌ لا `tinker --execute`
- ✅ قبل كل دفعة: نسخة احتياطية من الملفات
- ✅ بعد كل تعديل: `php -l` أو `node --check`
- ✅ **2 تعديلات فقط في الدفعة — لا أكثر**
- ✅ أعلن قبل التنفيذ

**الآن — ابدأ بقراءة الملفات المطلوبة فقط. لا تعدّل شيئاً.**

---

