# وثيقة تحليل هندسي — ERP_Q
## تحليل النظام الحالي + خطة تنفيذ المرتجعات (بيع/شراء) وشاشة التقارير

> **مرجع الطلب:** مبنية على ملف `تقارير فحص/retern.md` (52 بندًا).
>
> **ملاحظة منهجية:** كل ما ورد أدناه مبني على قراءة الكود الفعلي في مشروعي المحلي `c:\Laravel\qaatSystem` (وهو نفس بنية `ERP_Q`). لم أفترض أي ملف. وحيث لم أجد ملفًا قلت صراحة "غير موجود في المشروع الحالي".

---

## 1. ملخص النظام الحالي

النظام `ERP_Q` هو ERP لتجارة وتوزيع القات، مبني على:

| الطبقة | التقنية / الأسلوب الفعلي |
|---|---|
| Backend | Laravel 12 / PHP 8.2 |
| الواجهة | Blade + Bootstrap 5.3 RTL + Bootstrap Icons |
| JS | Vanilla JS، نمط "ملف لكل شاشة" + نظام Lookup موحّد |
| البناء | Vite (`resources/js/pages/*.js` كنقاط دخول) |
| DB | MySQL، أسماء جداول/أعمدة قديمة غير مسمّاة snake_case دائمًا (`characcount`, `Items`, `type`, `units`, `stocks`) |
| المخزون | **لا يوجد جدول أرصدة** — الرصيد يُحسب على الطلب من الحركات |
| المحاسبة | `journal_entries` + `journal_entry_lines` (تُكتب يدويًا عبر سندات القبض/الصرف والقيود الافتتاحية فقط) |

**أهم 4 حقائق ظهرت من الكود وتغيَّر بها كل شيء في خطة المرتجعات والتقارير:**

1. **حركات المخزون لا تُطبَّق على رصيد مخزَّن** — `InventoryService::applyMovement()` و `reverseMovement()` **فارغتان (no-op)**. الرصيد يُحسب لحظيًا بجمع `in − out` من `inventory_movement_details` (السطور 39–69). وهذا يعني أن **حذف الحركة = إلغاء أثرها الرصيدي تلقائيًا**، دون أي حاجة لعكس يدوي.

2. **فواتير البيع والشراء لا تُنشئ أي قيد محاسبي.** لا يوجد استدعاء لـ `JournalEntryService` إلا في ثلاثة أماكن فقط: سند القبض، سند الصرف، والقيود الافتتاحية. أما الفواتير فتُسجّل **المخزون فقط** ولا تلمس `journal_entries` إطلاقًا.

3. **`account_balances` غير موصولة بأي شيء.** الميجريشن موجود في `database/migrations/` لكنه **غير مستخدم في أي Service أو Controller**، ولا يوجد Updater له.

4. **نظام المرتجعات غير موجود كمنطق.** يوجد فقط عنوانان فارغان في القائمة الجانبية (`<a href="#">`) + ملفان Blade ثابتان نصهما "ستظهر القائمة هنا" + ثوابت `TYPE_SALE_RETURN` / `TYPE_PURCHASE_RETURN` معرَّفة في الموديل لكن **لا كود يستخدمها**. أي: المرتجعات **مُخطَّط لها ثابتًا لكنها لم تُنفَّذ**.

---

## 2. الملفات التي تم تحليلها (فعليًا)

```
routes/web.php                                   (911 سطر — كل المسارات)
app/Http/Controllers/Operation/Sales/SalesInvoiceController.php
app/Http/Controllers/Operation/Purchases/PurchaseInvoiceController.php
app/Http/Controllers/Operation/Movements/InventoryMovementController.php
app/Services/Sales/SalesInvoiceService.php
app/Services/Purchases/PurchaseInvoiceService.php
app/Services/Inventory/InventoryService.php
app/Services/JournalEntryService.php
app/Models/Sales/SalesInvoice.php  +  SalesInvoiceDetail.php
app/Models/Purchases/PurchaseInvoice.php  +  PurchaseInvoiceDetail.php
app/Models/Inventory/InventoryMovement.php  +  InventoryMovementDetail.php
app/Models/Accounting/JournalEntry.php  +  JournalEntryLine.php
app/Observers/SalesInvoiceObserver.php  +  PurchaseInvoiceObserver.php
app/Providers/AppServiceProvider.php
resources/views/operation/sales/returns/index.blade.php        ← فارغ
resources/views/operation/purchases/returns/index.blade.php    ← فارغ
resources/views/layouts/sidebar.blade.php
resources/views/operation/movements/{operations,search}.blade.php
resources/views/dashboard/index.blade.php
resources/js/{app.js, shared/lookup.js, invoice/sales_invoice.js, pages/sales-invoice.js}
database/migrations/2026_09_27_231500_create_account_balances_table.php
database/bac/2026_09_16_210929_create_sales_invoices_table.php
database/bac/2026_09_13_235256_create_purchase_invoices_table.php
database/bac/2026_09_15_162447_create_inventory_movements_table.php
database/bac/2026_09_15_162519_create_inventory_movement_details_table.php
database/bac/2026_09_16_211030_create_sales_invoice_details_table.php
database/seeders/ChartOfAccountsSeeder.php
tests/Feature/BusinessFlow/BusinessFlowTest.php   (44 اختبارًا)
tests/Feature/InventorySystemTest.php
```

### ⚠️ مشكلة `database/bac/` المذكورة في البند 40

**الحقيقة من الكود:** المجلد اسمه الحقيقي **`database/bac`** (وليس `back`) وهو يحتوي **كل ميجريشنات النظام الفعلية** (فواتير، مخزون، حسابات، عملات...). أما `database/migrations/` فلا يحوي إلا **3 ميجريشنات جديدة فقط** (تعديل حقول `is_active` + إنشاء `account_balances`).

بما أن Laravel لا يقرأ `database/bac`، فهذا يعني أحد أمرين: إما أن الجداول أُنشئت يدويًا/SQL خارجي، أو أن المجلد نُقل بالخطأ. **هذا أهم خطر بنيوي في المشروع** وسنعالجه كخطوة تحقق أولى (انظر المخاطر، البند 32).

---

## 3. كيف تعمل فواتير البيع (`SalesInvoiceService`)

**التدفق الفعلي (create):**

```
DB::transaction
 ├─ validateStockAvailability()      ← يفحص availableQuantity لكل صف (unit-aware)
 ├─ nextInvoiceNumber()              ← last.invoice_number + 1
 ├─ SalesInvoice::create(header)
 ├─ saveDetails()                    ← يحسب cost_price بذكاء (انظر أدناه)
 ├─ recalculateTotals()              ← items_total = Σ(qty×price)، discount_total = Σ discount
 ├─ syncInventoryMovement()
 │    ├─ deleteInventoryMovement()   ← يحذف أي حركة سابقة لنفس المصدر
 │    ├─ ينشئ رأس حركة: movement_type='sale', direction='out',
 │    │   source_type='sales_invoice', source_id=sales_invoice_id
 │    ├─ ينشئ تفاصيل الحركة لكل صف (وحدة/صنف/مخزن/كمية/unit_cost=detail.cost_price)
 │    └─ applyMovement()             ← no-op
 └─ Commit
```

**إجابات الأسئلة أ–ح:**

| السؤال | الجواب الفعلي |
|---|---|
| **أ. الإنشاء** | كأعلاه. السعر والخصم من الواجهة، والتكلفة من السيرفر. |
| **ب. التعديل** | يحذف الحركة القديمة → يحذف كل التفاصيل → يعيد الإنشاء → يعيد الحركة. `invoice_number` غير قابل للتعديل (`unset`). |
| **ج. الحذف/العكس** | يحذف الحركة كاملة (`reverseMovement` = no-op + حذف التفاصيل والرأس) ثم يحذف الفاتورة. + `SalesInvoiceObserver::deleted` كشبكة أمان. **لا يوجد Void/Reverse — الحذف نهائي.** |
| **د. الحسابات** | `total = max(0, qty×price − discount)` لكل صف. `items_total = Σ(qty×price)`, `discount_total = Σ discount`. الإجمالي بعملة الفاتورة = `items_total − discount_total`، وبالعملة المحلية = `×exchange_rate`. |
| **د. التكلفة (مهم جدًا)** | `cost_price` يُحدَّد بـ **Option C** (سطور 276–284): إن أرسلت الواجهة 0 → يُستخدم `serverCost` (من `InventoryService::lastCost`)؛ إن كان الاثنان > 0 → إن كان الفرق ≤ 10% يُقبل سعر الواجهة، وإن > 10% **يُرفض ويُستخدم سعر السيرفر** (حماية من التلاعب). ← **هذا هو مصدر تكلفة البضاعة المبيعة تاريخيًا، وهو المعتمد لتقارير الربح.** |
| **هـ. حركة المخزون** | حركة واحدة (`sale` / `out`) لكل فاتورة، تفاصيلها بعدد أصناف الفاتورة. أُهمل تمامًا `min/max/sale_price` (تُكتب `null`). |
| **و. الربط** | عبر `source_type='sales_invoice'` + `source_id=sales_invoice_id`، مع `document_number = invoice_number`. |
| **ز. المستودع** | على مستوى الصف (`details.warehouse_id`)؛ ورأس الحركة يأخذ **مخزن أول صف** (`$firstDetail->warehouse_id`). |
| **ح. الحساب/العميل** | `account_id` = حساب العميل في `characcount`، و`payment_account_id` = حساب الدفع الفوري (إلزامي للـ cash/bank/network، وممنوع للـ credit). **لا قيد محاسبي يُنشأ.** |

---

## 4. كيف تعمل فواتير الشراء (`PurchaseInvoiceService`)

التدفق مشابه، والفرق الجوهري في **حساب التكلفة الحقيقية (Landed Cost)** داخل `syncInventoryMovement()`:

```
extraCostsFC = expenses + tax_cost + transportation + other_cost
totalNetFC   = Σ max(0, qty×price − discount)                 ← صافي كل الصفوف
لكل صف:
   lineNetFC  = max(0, qty×price − discount)
   shareFC    = (lineNetFC / totalNetFC) × extraCostsFC       ← توزيع نسبي للتكاليف
   landedFC   = lineNetFC + shareFC                            ← التكلفة الواقعة
   unitCostFC = landedFC / qty
   unitCostBC = unitCostFC × exchange_rate                     ← ⭐ التكلفة الحقيقية
   ...
   min_price  = unitCostBC
   max_price  = unitCostBC × 2
   sale_price = unitCostBC × 1.5
```

**نقاط مهمة:**

- **التكلفة الفعلية تُخزَّن في `inventory_movement_details.unit_cost`** وليست في `purchase_invoice_details` (الذي يحوي `price` خام فقط). ← هذا يحدد مصدر تكلفة مرتجع الشراء (البند 19 في طلبك).
- رأس الفاتورة يحمل `warehouse_id` (مستودع واحد للفاتورة كلها) — بخلاف فاتورة البيع.
- **`PurchaseInvoiceService::deleteInventoryMovement` لا يستدعي `reverseMovement`** (سطور 161–174) بخلاف نظيره في المبيعات. آمن حاليًا لأن العكس no-op، لكنه **تناقض يجب توحيده**.
- `total_in_base_currency = (items_total − discount_total) × exchange_rate` — **لا تشمل التكاليف الإضافية**.

---

## 5. كيف تعمل حركات المخزون (`InventoryService` + `InventoryMovement`)

**الأنواع المعرَّفة فعليًا (ثوابت `InventoryMovement`):**

| الثابت | القيمة | الاتجاه | هل مُستخدم؟ |
|---|---|---|---|
| `TYPE_SUPPLY` | `supply` | in | ✅ أمر توريد يدوي |
| `TYPE_ISSUE` | `issue` | out | ✅ أمر صرف يدوي (والفرز) |
| `TYPE_PURCHASE` | `purchase` | in | ✅ فاتورة الشراء |
| `TYPE_SALE` | `sale` | out | ✅ فاتورة البيع |
| `TYPE_PURCHASE_RETURN` | `purchase_return` | out | ❌ **معرَّف فقط، لا كود يستخدمه** |
| `TYPE_SALE_RETURN` | `sale_return` | in | ❌ **معرَّف فقط، لا كود يستخدمه** |
| `TYPE_SORTING_OUT/IN` | `sorting_*` | out/in | ✅ الفرز/التجهيز |

**خريطة الاتجاه موجودة جاهزة** (`TYPE_DIRECTION_MAP`) وتشمل المرتجعين — أي أن `InventoryMovement::directionForType('sale_return')` سترجع `'in'` و`'purchase_return'` سترجع `'out'` **بدون أي تعديل**. ← **إعادة استخدام ممتازة للمرتجعات.**

**`source_type` الفعلي:** `purchase_invoice` / `sales_invoice` / `sorting` / `null`. ← ستحتاج إضافة `sales_return` و `purchase_return` كثوابت جديدة.

**الرصيد الحالي** (`availableQuantity`) = `SUM(in) − SUM(out)` مفلترًا بـ `item_id + warehouse_id + unit_id(اختياري)`. **ملاحظة خطيرة:** الدالة تستخدم `static $cache` ولا يُبطَّل الكاش داخل نفس الطلب (موثّق كتحذير في الكود) — عند كتابة اختبارات مرتجع في نفس الطلب يجب الحذر.

---

## 6. كيف تعمل الأرصدة

**لا يوجد جدول أرصدة.** شاشة الأرصدة (`StockController` + `views/operation/movements/balances/*`) تعتمد على `InventoryMovementController::allStockBalances()` التي تجمع الحركات. الدالتان `applyMovement()` و `reverseMovement()` فارغتان تحسّبًا لمستقبل جدول `inventory_balances` (موثّق في تعليق صريح).

> ✅ **ملاحظة معمارية مهمة جدًا للمرتجعات:** بما أن الرصيد مشتق من الحركات، فمجرد إنشاء حركة `sale_return/in` ينشئ الرصيد، وحذفها يلغيه. لا نحتاج أي منطق "تحديث رصيد".

---

## 7. كيف تعمل الحسابات

- `journal_entries` + `journal_entry_lines` مع `JournalEntryService::create()` (static، متوازنة إلزاميًا، تُرجع null وتُسجّل تحذيرًا إن اختل التوازن).
- **تنشئها فقط:** `ReceiptVoucherService` (`docType='سند قبض'`)، `PaymentVoucherService` (`'سند صرف'`)، `OpeningBalanceController` (`'قيد افتتاحي'`).
- **فواتير البيع والشراء لا تنشئ قيودًا** — دليل قاطع من الـ grep: `JournalEntryService::create` ظهر في 3 مواضع فقط.
- دليل الحسابات (`ChartOfAccountsSeeder`) **يحتوي جاهزًا** على `sales_returns` (4201) و`sales_returns_discounts` (42) و`cost_of_goods_sold` (5101) و`inventory` (1104) و`customers` (1103) و`suppliers` (2101). ← **الحسابات المحاسبية للمرتجعات موجودة مسبقًا في دفتر الحسابات.**

---

## 8. ما الموجود حاليًا للمرتجعات

| المكوّن | الحالة |
|---|---|
| شاشات Blade | ملفان بالعنوان فقط (`sales/returns/index.blade.php`, `purchases/returns/index.blade.php`) — بدون JS أو API |
| Controller | ❌ غير موجود في المشروع الحالي |
| Service | ❌ غير موجود في المشروع الحالي |
| Models (`SalesReturn`/`PurchaseReturn`) | ❌ غير موجودة في المشروع الحالي |
| Routes | ❌ غير موجودة في المشروع الحالي |
| Migrations | ❌ غير موجودة (لا جدول `sales_returns` ولا `purchase_returns`) |
| JavaScript | ❌ غير موجود (لكن `lookupCache` و `LookupConfigs` و`apiGet/apiSend` جاهزة لإعادة الاستخدام) |
| Tests | ❌ غير موجودة في المشروع الحالي |
| ثوابت المخزون | ✅ `TYPE_SALE_RETURN` / `TYPE_PURCHASE_RETURN` معرَّفة ومربوطة بالاتجاه الصحيح |
| حسابات المرتجعات | ✅ موجودة في `ChartOfAccountsSeeder` |
| روابط القائمة الجانبية | ⚠️ رابطا "مردود المبيعات/المشتريات" موجودان لكن `href="#"` ولا يقودان لشيء فعلي |

---

## 9. ما الناقص للمرتجعات (= الفجوة الحقيقية)

1. جدولا `sales_returns` + `sales_return_details` وجدولا `purchase_returns` + `purchase_return_details`.
2. موديلات Relations وربطها بالفاتورة الأصلية وبالسطر الأصلي.
3. خدمتا `SalesReturnService` / `PurchaseReturnService` مع منطق التحقق من الكمية المتبقية وتسلسل الحركة.
4. ثابتا `SOURCE_SALES_RETURN` / `SOURCE_PURCHASE_RETURN`.
5. متحكما المرتجعات + مساراتهما.
6. واجهات Blade + JS للمرتجعات (مع استخدام Lookup الموجود).
7. استراتيجية القيود المحاسبية (البند 22 من طلبك — انظر الخيارين في القسم 14 أدناه).
8. منظومة التقارير بالكامل (لا يوجد مجلد `Reports` ولا أي Controller/Service تقارير — الـ grep لـ `reports|Reports|تقارير` أعاد **صفر نتائج** في الكود).

---

## 10. التصميم المقترح للمرتجعات

**المبدأ:** مستند مستقل تمامًا (لا `quantity = -10` في الفاتورة الأصلية).

### جداول المرتجعات

**`sales_returns`** (رأس)

```
return_id (PK, auto)
return_number (unique)          ← تسلسل مستقل
return_date (date)
original_sales_invoice_id (FK → sales_invoices)
account_id (FK → characcount)   ← العميل (منسوخ من الأصل)
payment_account_id (FK → characcount, nullable)
coin_id (FK → coins)
exchange_rate (decimal 18,6)
payment_method (tinyint)
items_total, discount_total (decimal 18,6)
statement, reference (text null)
timestamps
indexes: return_date, original_sales_invoice_id, account_id
```

**`sales_return_details`**

```
sales_return_detail_id (PK)
sales_return_id (FK cascade)
sales_invoice_detail_id (FK → sales_invoice_details)   ← ⭐ الربط بالسطر
item_id, type_id, unit_id, warehouse_id  (FK)
code (nullable)
quantity, price, cost_price, discount, total
timestamps
index: sales_return_id, sales_invoice_detail_id, item_id
```

**`purchase_returns` / `purchase_return_details`** — بنفس النمط مع:

- `original_purchase_invoice_id`
- `purchase_invoice_detail_id`
- `unit_cost` في التفاصيل = التكلفة الفعلية المأخوذة من `inventory_movement_details.unit_cost` للسطر الأصلي (Landed Cost).

---

## 11. تدفق عملية مرتجع البيع

```
المستخدم يختار الفاتورة الأصلية (Lookup/نافذة بحث)
        ↓
النظام يعرض الأصناف مع: الكمية الأصلية | المرتجع سابقًا | المتبقي | التكلفة الأصلية
        ↓
إدخال كمية المرتجع (تتحقق فورًا في JS: return ≤ remaining)
        ↓
POST /operation/sales/returns
        ↓
[HARD] DB::transaction
   ├─ validate: الفاتورة موجودة + كل سطر: qty>0 && qty ≤ (original − Σ previous returns)
   ├─ SalesReturn::create (header, رقم تسلسلي داخل القفل)
   ├─ SalesReturnDetail::create لكل سطر (cost_price منسوخ من السطر الأصلي)
   ├─ recalculateTotals
   ├─ InventoryMovement::create:
   │      movement_type = 'sale_return'
   │      direction     = directionForType('sale_return') → 'in'   ✅ جاهز
   │      source_type   = 'sales_return'                          🆕 ثابت جديد
   │      source_id     = return_id                               ✅ مستقل لكل مرتجع
   │      document_number = return_number
   │      details: unit_cost = cost_price الأصلي (لعكس أثر التكلفة)
   └─ Commit  (عكسي عند أي خطأ)
```

**لماذا `source_id = return_id` وليس الفاتورة الأصلية:** لأن الفاتورة الواحدة قد تُرجع على 3 مستندات، ولكل مستند حركة مستقلة — كما نصّيت تمامًا في البند 8.

---

## 12. تدفق عملية مرتجع الشراء

```
اختيار فاتورة الشراء الأصلية
        ↓
عرض: الكمية | المرتجع سابقًا | المتبقي | تكلفة الشراء الفعلية (Landed)
        ↓
POST /operation/purchases/returns
        ↓
DB::transaction
   ├─ validate الكميات المتبقية
   ├─ PurchaseReturn::create
   ├─ PurchaseReturnDetail::create (unit_cost = التكلفة الفعلية من حركة الشراء)
   ├─ InventoryMovement::create:
   │      movement_type = 'purchase_return'
   │      direction     = 'out'                                   ✅ جاهز
   │      source_type   = 'purchase_return'                      🆕
   │      source_id     = return_id
   └─ Commit
```

> **قاعدة تكلفة مرتجع الشراء (ردًا على البند 19):** لا يُستخدم `purchase_invoice_details.price` (سعر خام). يُستخدم `unit_cost` من `inventory_movement_details` للسطر الأصلي، لأنها القيمة التي **فعلاً** خرجت من المخزون عند الشراء وشملت النقل/الضريبة/المصاريف.

---

## 13. تأثير المرتجعات على المخزون

| | نوع الحركة | الاتجاه | أثر الرصيد |
|---|---|---|---|
| مرتجع بيع | `sale_return` | `in` | **+ رصيد** (العميل أعاد) ← يُعكس أثر البيع |
| مرتجع شراء | `purchase_return` | `out` | **− رصيد** (نُعيد للمورد) |

**لا حاجة لأي كود رصيد إضافي** لأن الرصيد مشتق من الحركات، والدالتان `apply/reverse` فارغتان.

---

## 14. تأثير المرتجعات على الحسابات — قرار تصميمي (تحليل خيارات)

هنا الخطورة الحقيقية: **الفواتير الحالية لا تُنشئ قيودًا.** فمن أين يأتي "العكس المحاسبي"؟

### الخيار A — المرتجع يُنشئ قيدًا محاسبيًا مباشرًا (Reversing Entry)

| | |
|---|---|
| **المزايا** | يسجّل أثرًا محاسبيًا للمرتجع فورًا؛ يستخدم `JournalEntryService` الموجود + `sales_returns` 4201 و`cost_of_goods_sold` 5101 الجاهزة في الدليل. |
| **العيوب** | سيُنتج قيدًا لمرتجع بيع **بدون قيد مقابل لفاتورة البيع الأصلية** → قيد "وحيد الطرف" في دفتر اليومية، واختلال منطقي في التقارير المحاسبية والإقفال. |
| **التوافق المعماري** | جزئي — يستخدم الخدمة لكن يكسر التماثل (لا يوجد قيد أصلي لعكسه). |

### الخيار B (الموصى به) — المحاسبة تبقى لا تُنشئ قيودًا، وتُبنى التقارير على الحركات + التكاليف

| | |
|---|---|
| **المزايا** | ✅ **متوافق 100% مع الوضع الفعلي للفواتير** (لا قيد ⇒ لا عكس). ✅ يعتمد نفس آلية النظام للمخزون والتكلفة. ✅ يُنتج نفس النتائج التي ستُنتجها فاتورة الشراء/البيع: تؤثر على المخزون والتكلفة وتقارير المبيعات/الأرباح. ✅ لا يكسر شيئًا قائمًا. |
| **العيوب** | لا قيد في `journal_entries` — لكن نفس الشيء ينطبق على الفواتير نفسها، فلا يوجد تناسق مفقود. |
| **التوافق المعماري** | **كامل.** |

### الحل الأمثل عمليًا = **B الآن + إعداد A كجسر مستقبلي**

- **الآن (B):** المرتجع يعمل كعملية عكسية متوافقة مع النظام الحالي = حركة مخزون عكسية + تسجيل التكلفة الأصلية.
- **مستقبلًا (A):** إن أراد المشروع تنشيط `account_balances`، فتُضاف خدمة `AccountingIntegrationService` **تُشغَّل للفواتير والمرتجعات معًا** (تولّد قيدًا للفاتورة وقيدًا عكسيًا للمرتجع) — أي **لا تُشغَّل للمرتجع وحده أبدًا**، حتى لا يقع "قيد وحيد الطرف". الثوابت والحسابات جاهزة في الدليل اليوم.

> هذا يلبّي حرفيًا البند 22 من طلبك: "اجعل المرتجع عملية عكسية متوافقة مع نفس النظام" — وبما أن النظام لا يقيّد الفواتير، فالمرتجع لا يُقيّدها هو أيضًا.

---

## 15. تصميم شاشة المرتجعات

نفس تصميم شاشات الفواتير الحالية (نفس الـ CSS ونفس المكونات):

- **رأس:** رقم المرتجع / تاريخه / الفاتورة الأصلية / العميل (أو المورد) / العملة / طريقة الدفع.
- **جدول:** الصنف | النوع | الوحدة | الكمية الأصلية | المرتجع سابقًا | المتبقي | كمية المرتجع | السعر/التكلفة | الخصم | الإجمالي | المستودع.
- **تذييل:** الإجمالي / الخصم / الصافي + أزرار: حفظ | إلغاء | طباعة.
- **شاشة القائمة:** إضافة | بحث | طباعة + جدول المرتجعات.
- **تحقق JS حيّ:** `return ≤ remaining` مع تعطيل الحفظ وإظهار تحذير.

---

## 16. تصميم قاعدة البيانات

المخطط التفصيلي كامل في القسم 10 أعلاه. الملفات:

```
database/migrations/xxxx_xx_xx_xxxxxx_create_sales_returns_table.php
database/migrations/xxxx_xx_xx_xxxxxx_create_sales_return_details_table.php
database/migrations/xxxx_xx_xx_xxxxxx_create_purchase_returns_table.php
database/migrations/xxxx_xx_xx_xxxxxx_create_purchase_return_details_table.php
```

**قواعد المخطط:**
- `original_*_invoice_id` في الرأس، و`*_invoice_detail_id` في التفاصيل.
- نسخ أعمدة الوصف (unit/type/warehouse/code) للتفاصيل لضمان ثبات التقارير التاريخية.
- `cost_price` (بيع) / `unit_cost` (شراء) يُخزَّنان في التفاصيل لتقارير الربح وعكس التكلفة.

---

## 17. تصميم الـ Models

```
app/Models/Sales/SalesReturn.php          → hasMany(SalesReturnDetail), belongsTo(SalesInvoice, CharAccount, Coin)
app/Models/Sales/SalesReturnDetail.php    → belongsTo(SalesReturn, SalesInvoiceDetail, Item, Type, Unit, Stock)
app/Models/Purchases/PurchaseReturn.php
app/Models/Purchases/PurchaseReturnDetail.php
```

**تعديلات إضافية:**

- `SalesInvoice`: إضافة `returns()` (`hasMany(SalesReturn, ...)`).
- `PurchaseInvoice`: إضافة `returns()`.
- `InventoryMovement`: إضافة `SOURCE_SALES_RETURN = 'sales_return'` و`SOURCE_PURCHASE_RETURN = 'purchase_return'`.

---

## 18. تصميم Services

```
app/Services/Sales/SalesReturnService.php
app/Services/Purchases/PurchaseReturnService.php
```

**المسؤوليات (لكل منها):**
1. التحقق من الفاتورة الأصلية ووجودها.
2. حساب **الكمية المتبقية لكل سطر** = `detail.quantity − Σ(returns for that detail)`.
3. رفض `return_qty > remaining` (ValidationException).
4. حفظ الرأس + التفاصيل داخل `DB::transaction()`.
5. إنشاء حركة المخزون بالاتجاه والنوع الصحيحين (بدون أي رصيد يدوي).
6. دالة `availableForReturn(int $invoiceDetailId): float` تُستخدم في الواجهة وفي التحقق.

**يعيد استخدام:** `InventoryService` (availableQuantity / lastCost) و`InventoryMovement` (الثوابت + `directionForType`) — **لا يعيد بناء أي شيء**.

---

## 19. تصميم Controllers

```
app/Http/Controllers/Operation/Sales/SalesReturnController.php
app/Http/Controllers/Operation/Purchases/PurchaseReturnController.php
```

بنفس نمط `SalesInvoiceController` بالحرف:

```
index()      → شاشة القائمة
list()       → JSON للبحث
show($id)    → رأس + تفاصيل + الكميات المتبقية
nextNumber() → رقم المرتجع التالي
print($id)   → view('print.sales-return' | 'print.purchase-return')
store()      → Validation + Service
update($id)  → (اختياري — حسب قرارك؛ يمكن حجبه لضمان سلامة التاريخ)
destroy($id) → (يُفضّل منعه/تحويله إلى Void — انظر البند 24)
```

---

## 20. تصميم Routes

تُضاف في `routes/web.php` مباشرة تحت مجموعتي المبيعات/المشتريات، بنفس الأسلوب:

```php
// مرتجعات البيع
Route::get   ('/operation/sales/returns',              [SalesReturnController::class, 'index'])->name('sales.returns.index');
Route::get   ('/operation/sales/returns/list',         [SalesReturnController::class, 'list'])->name('sales.returns.list');
Route::get   ('/operation/sales/returns/next-number',  [SalesReturnController::class, 'nextNumber'])->name('sales.returns.nextNumber');
Route::get   ('/operation/sales/returns/{id}/print',   [SalesReturnController::class, 'print'])->name('sales.returns.print');
Route::get   ('/operation/sales/returns/{id}',         [SalesReturnController::class, 'show'])->name('sales.returns.show');
Route::post  ('/operation/sales/returns',              [SalesReturnController::class, 'store'])->name('sales.returns.store');

// مرتجعات الشراء (نفس النمط تحت purchases.returns.*)
```

> ⚠️ الترتيب مهم: `next-number` و`list` قبل `/{id}` — كما هو مُطبَّق حرفيًا في مسارات الحركات الحالية (تعليق صريح في `routes/web.php`: "يجب أن يوضع قبل /{id}").

---

## 21. تصميم JavaScript

```
resources/js/sales_return.js
resources/js/purchases_return.js
resources/js/pages/sales-return.js      → import '../shared/lookup'; import '../sales_return';
resources/js/pages/purchase-return.js
```

**إعادة استخدام إلزامية (ردًا على البند 36 و37):**

- `LookupConfigs` + `lookupCache` من `resources/js/shared/lookup.js` — لاختيار العميل/المورد/الصنف/المستودع/العملة.
- نمط `apiGet` / `apiSend` الموجود في `resources/js/invoice/sales_invoice.js`.
- نافذة `resources/views/shared/lookup-modal.blade.php`.
- دالة تحميل الفاتورة الأصلية عبر `GET .../invoices/{id}` الموجودة مسبقًا — **لا ننشئ API تحميل فاتورة جديدًا**.

**يُمنع:** إنشاء `sales_return_lookup.js` أو أي Lookup جديد.

---

## 22. تصميم Blade Views

```
resources/views/operation/sales/returns/index.blade.php      ← تحديث الملف الفارغ (قائمة + أزرار)
resources/views/operation/sales/returns/create.blade.php     ← شاشة الإدخال (أو Modal في index)
resources/views/operation/sales/returns/head.blade.php
resources/views/operation/sales/returns/details.blade.php
resources/views/operation/purchases/returns/index.blade.php  ← نفس الشيء
resources/views/print/sales-return.blade.php                 ← بنمط print/sales-invoice.blade.php الموجود
resources/views/print/purchase-return.blade.php
```

مع تفعيل الرابطين في `resources/views/layouts/sidebar.blade.php` (سطور 270–272 و299–301) بـ `route('sales.returns.index')` و`route('purchases.returns.index')` بدل `href="#"`.

---

## 23. الاختبارات

اختبارات جديدة في `tests/Feature/` بنمط `BusinessFlowTest` الموجود:

```
tests/Feature/SalesReturnSystemTest.php
tests/Feature/PurchaseReturnSystemTest.php
tests/Feature/ReturnQuantityValidationTest.php
tests/Feature/ReturnInventoryTest.php
tests/Feature/ReturnAccountingTest.php
```

**سيناريوهات إلزامية (البند 38):**

| السيناريو | المتوقّع |
|---|---|
| 100 → مرتجع 20 | ينجح، المتبقي 80 |
| المتبقي 80 → مرتجع 30 | ينجح، المتبقي 50 |
| المتبقي 50 → مرتجع 50 | ينجح، المتبقي 0 |
| المتبقي 0 → أي مرتجع | **يفشل** |
| المتبقي 50 → مرتجع 60 | **يفشل** (ValidationException) |
| حركة مرتجع البيع | `movement_type=sale_return`, `direction=in` |
| حركة مرتجع الشراء | `movement_type=purchase_return`, `direction=out` |
| `source_type/source_id` | `sales_return`/`return_id` مستقل لكل مرتجع |
| عكس أثر التكلفة | مطابق لتكلفة السطر الأصلي |

**تنبيه:** ضع في الحسبان الكاش الثابت في `availableQuantity` عند اختبار حركة ثم قراءة الرصيد في نفس الطلب.

---

## 24. تحليل شاشة التقارير

- **غير موجودة:** لا مجلد `Reports`، لا Controller، لا Service، لا Query، لا View، لا Route تقارير (grep = 0 نتائج).
- **الواجهة الحالية:** `dashboard/*` معروضة بـ Blade ثابت + JS بسيط؛ يمكن إعادة استخدام نفس البنية والـ CSS.
- **التقارير Read Only** — SELECT فقط، لا INSERT/UPDATE/DELETE.
- **الطباعة:** إعادة استخدام نمط `resources/views/print/*` (A4 RTL) — **لا نظام PDF جديد**.

---

## 25. أنواع التقارير

| # | التقرير | الأعمدة الأساسية |
|---|---|---|
| 1 | المبيعات | رقم الفاتورة، التاريخ، العميل، الصنف، الكمية، السعر، الخصم، الإجمالي، التكلفة، الربح |
| 2 | المشتريات | رقم الفاتورة، التاريخ، المورد، الصنف، الكمية، التكلفة، المصاريف، الإجمالي |
| 3 | مرتجعات المبيعات | رقم المرتجع، التاريخ، الفاتورة الأصلية، العميل، الصنف، الكمية، القيمة |
| 4 | مرتجعات المشتريات | رقم المرتجع، التاريخ، الفاتورة الأصلية، المورد، الصنف، الكمية، القيمة |
| 5 | حركة المخزون | التاريخ، الصنف، المستودع، نوع الحركة، دخول، خروج، الكمية، التكلفة، المستند المصدر |
| 6 | المخزون | الصنف، المستودع، الوحدة، الرصيد الحالي، آخر/متوسط تكلفة، قيمة المخزون |
| 7 | الأرباح | الصيغة أدناه |

**صيغة الربح (البند 46):** تُبنى على `sales_invoice_details.cost_price` الفعلي وليس `price`:

```
إجمالي الربح = Σ(qty×price − discount) − Σ(qty×cost_price)                       [المبيعات]
             − Σ(return_qty×price − discount) + Σ(return_qty×cost_price)         [عكس المرتجعات]
```

**كشوف الحساب (البند 28):** تُبنى من `journal_entries`/`journal_entry_lines` (الموجودة) — **لا جدول ديون جديد**.

---

## 26. Query Architecture

مع إبقاء هيكل المشروع (Controller ← Service ← DB):

```
app/Http/Controllers/Operation/Reports/ReportController.php
app/Services/Reports/ReportService.php
app/Http/Controllers/Operation/Reports/{Sales,Purchase,Inventory,Return,Profit}Controller.php  (اختياري)
```

> أُفضّل **Controller واحد + Service واحد + Queries منفصلة** لأنها تحفظ نمط المشروع القائم وتمنع تضخم الملفات. لا نطبّق هيكل "Queries/" حرفيًا كما في طلبك إلا إن رأيت أنه يخدم المشروع.

**قواعد إجبارية (البند 30):** الفلترة والتجميع في SQL (`WHERE / JOIN / GROUP BY / SUM / COUNT / ORDER BY / LIMIT`) — **لا** تحميل الكل إلى PHP ثم فلترة في JS.

---

## 27. الفلاتر

`from/to` إلزاميان منطقيًا، والباقي اختياري بالكامل (عميل، مورد، صنف، نوع، وحدة، مستودع، عملة، طريقة دفع، نوع الحركة).

**مثال مقبول:** تقرير مبيعات من 01/09 إلى 30/09 بدون عميل — أو مع عميل = محمد. **لا تجعل جميع الفلاتر إجبارية.**

---

## 28. Pagination

إلزامي. استخدم `paginate()` في Query Builder. ممنوع تحميل 500,000 سجل إلى المتصفح. واجهة الترقيم تُبنى بـ Bootstrap كما في الجداول الحالية.

---

## 29. Indexes

لا تُضاف عشوائيًا. المرشحون بناءً على الفهارس الفعلية:

- ✅ **موجودة مسبقًا:** `inventory_movements(movement_type, direction, movement_date, warehouse_id, [source_type, source_id])` و`inventory_movement_details(movement_id, item_id, type_id, unit_id, warehouse_id)` و`sales_invoices(invoice_number, invoice_date, payment_method)`.
- 🆕 المطلوبة للمرتجعات/التقارير: `sales_returns(return_date, original_sales_invoice_id, account_id)`، `sales_return_details(sales_return_id, sales_invoice_detail_id, item_id)`، ونفسها للمشتريات، و`inventory_movements(movement_date, movement_type)` إن أثبت القياس الحاجة.

**القاعدة:** حدد الاستعلامات الفعلية أولًا، ثم قِس، ثم أضف.

---

## 30. الطباعة والتصدير

- **موجود وجاهز لإعادة الاستخدام:** `resources/views/print/{sales-invoice, purchase-invoice, movement, sorting}.blade.php` (HTML A4 RTL يُطبع من المتصفح).
- **لا تُنشئ نظام PDF جديدًا بلا سبب.**
- التقارير: عرض + طباعة + تصدير (التصدير يُبنى على نفس مصدر البيانات من SQL، لا توليد في JS).

---

## 31. خطة التنفيذ المرحلية

> الترتيب مُعدَّل: أضفت **Phase 0** للتحقق البنيوي، وأخّرتُ المحاسبة (Phase 5) لأنها لا تُشغَّل مع المرتجع وحده وفق الخيار B.

| المرحلة | الهدف | الملفات المتأثرة / الجديدة | يعيد استخدام | الاختبار |
|---|---|---|---|---|
| **0. تحقيق بنيوي** | تحديد المسار الرسمي للميجريشن (مشكلة `database/bac`) وتأكيد الجداول الفعلية | استعلام على `information_schema` / `php artisan migrate:status` | — | يدوي |
| **1. Database** | إنشاء جداول المرتجعات الأربعة | `database/migrations/*` (4 ملفات جديدة) | نمط `sales_invoices` الحالي | Migration run |
| **2. Models & Relations** | موديلات المرتجع + `returns()` في الفواتير + ثوابت `SOURCE_*` في `InventoryMovement` | 4 موديلات + تعديل `SalesInvoice.php` + `PurchaseInvoice.php` + `InventoryMovement.php` | `TYPE_*` و`directionForType()` الموجودتان | Unit |
| **3. Services** | `SalesReturnService` + `PurchaseReturnService` مع قاعدة الكمية المتبقية داخل `DB::transaction()` | ملفان جديدان | `InventoryService` (availableQuantity, lastCost) | `ReturnQuantityValidationTest` |
| **4. Inventory Integration** | إنشاء حركة `sale_return/in` و`purchase_return/out` مع `source_type/source_id` | داخل الخدمتين | `InventoryMovement` (الثوابت والاتجاه جاهزة) | `ReturnInventoryTest` |
| **5. Accounting Integration** | (اختياري الآن) جسر اختياري؛ **لا يُشغَّل للمرتجع وحده** | `app/Services/Accounting/*` | `JournalEntryService` + `ChartOfAccountsSeeder` | `ReturnAccountingTest` |
| **6. Controllers & Routes** | متحكما المرتجعات + مساراتهما | `app/Http/Controllers/Operation/{Sales,Purchases}/*ReturnController.php` + `routes/web.php` | نمط `SalesInvoiceController` | Feature |
| **7. Sales Return UI** | شاشة إدخال/قائمة مرتجع البيع | `resources/views/operation/sales/returns/*` + `resources/js/sales_return.js` | `shared/lookup.js`، `pages/sales-invoice.js` | Manual + Feature |
| **8. Purchase Return UI** | شاشة إدخال/قائمة مرتجع الشراء | `resources/views/operation/purchases/returns/*` + `resources/js/purchases_return.js` | نفس ما فوق | Manual + Feature |
| **9. Tests** | تجميع اختبارات المرتجعات الكاملة | `tests/Feature/Return*.php` | نمط `BusinessFlowTest` | ✅ |
| **10. Reports Architecture** | هيكل التقارير (Controller + Service + Queries) | `app/Http/Controllers/Operation/Reports/*` + `app/Services/Reports/*` | نمط المشروع | Route test |
| **11. Reports Queries** | استعلامات SQL مجمّعة (مبيعات/مشتريات/مرتجعات/حركات/مخزون/أرباح) | ملفات Queries | نمط `availableQuantity` المجمّع | Feature |
| **12. Reports UI** | شاشة تقارير + فلاتر + Pagination | `resources/views/.../reports/*` + JS | Bootstrap + JS الحالي | Manual |
| **13. Print/Export** | عرض/طباعة/تصدير بنفس نظام الطباعة | `resources/views/print/*` | الأنماط الحالية | Manual |
| **14. Performance** | فهارس + قياس الاستعلامات الثقيلة | Migrations فهارس جديدة | — | Query count/زمن |

---

## 32. المخاطر المحتملة

| # | الخطر | التخفيف |
|---|---|---|
| 1 | **موقع الميجريشنات — `database/bac` يحوي الميجريشنات الفعلية و`database/migrations` لا** | Phase 0: تحقق من الحالة الحقيقية قبل إنشاء أي ميجريشن. لا تحذف/تنقل `bac` عشوائيًا. |
| 2 | **الكاش الثابت في `availableQuantity`** لا يُبطَّل داخل نفس الطلب | تجنّب الاستدعاء المزدوج بعد الكتابة، أو أضف مسار إبطال/إزالة الكاش (تعديل دقيق محسوب). |
| 3 | **الفواتير لا تُنشئ قيودًا** ⇒ المرتجع لا يجد قيدًا لعكسه | تبنّي الخيار B (واضح ومتوافق). |
| 4 | حذف حركة تاريخية مباشرة (`deleteInventoryMovement` يحذف نهائيًا) | توصية بتوفير `Void/Reverse` مستقبلي بدل الحذف؛ في المرتجعات استخدم `source_id` مستقلًا. |
| 5 | تفاوت `reverseMovement` بين مبيعات (يستدعيها) وشراء (لا يستدعيها) | توحيد السلوك أثناء العمل على المرتجعات (تعديل دقيق جدًا). |
| 6 | `min/max/sale_price` في حركة البيع = `null` دائمًا | لا يُعتمد عليها في أي حساب ربح أو تقرير. |
| 7 | أسماء الجداول/الأعمدة غير الموحّدة (`characcount`, `Items`, `type`) | الالتزام الحرفي بالمخطط، وعدمه الجديد. |

---

## 33. الملفات التي يجب تعديلها

```
routes/web.php                                        ← إضافة مجموعتي مسارات المرتجعات + التقارير
app/Models/Inventory/InventoryMovement.php            ← إضافة ثابتَي SOURCE_SALES_RETURN / SOURCE_PURCHASE_RETURN
app/Models/Sales/SalesInvoice.php                     ← علاقة returns()
app/Models/Purchases/PurchaseInvoice.php              ← علاقة returns()
resources/views/layouts/sidebar.blade.php             ← تفعيل رابطَي مردود المبيعات/المشتريات
resources/views/operation/sales/returns/index.blade.php
resources/views/operation/purchases/returns/index.blade.php
```

---

## 34. الملفات الجديدة المطلوبة

```
database/migrations/*_create_sales_returns_table.php
database/migrations/*_create_sales_return_details_table.php
database/migrations/*_create_purchase_returns_table.php
database/migrations/*_create_purchase_return_details_table.php

app/Models/Sales/SalesReturn.php
app/Models/Sales/SalesReturnDetail.php
app/Models/Purchases/PurchaseReturn.php
app/Models/Purchases/PurchaseReturnDetail.php

app/Services/Sales/SalesReturnService.php
app/Services/Purchases/PurchaseReturnService.php

app/Http/Controllers/Operation/Sales/SalesReturnController.php
app/Http/Controllers/Operation/Purchases/PurchaseReturnController.php

app/Http/Controllers/Operation/Reports/ReportController.php
app/Services/Reports/ReportService.php

resources/js/sales_return.js
resources/js/purchases_return.js
resources/js/pages/sales-return.js
resources/js/pages/purchase-return.js

resources/views/operation/sales/returns/*.blade.php
resources/views/operation/purchases/returns/*.blade.php
resources/views/print/sales-return.blade.php
resources/views/print/purchase-return.blade.php
resources/views/operation/reports/*.blade.php

tests/Feature/SalesReturnSystemTest.php
tests/Feature/PurchaseReturnSystemTest.php
tests/Feature/ReturnQuantityValidationTest.php
tests/Feature/ReturnInventoryTest.php
tests/Feature/ReturnAccountingTest.php
```

---

## 35. الملفات التي لا يجب تعديلها

```
app/Services/Sales/SalesInvoiceService.php          ← لا تُلمس (عدا تعديل لاحق مُبرَّر ومستقل)
app/Services/Purchases/PurchaseInvoiceService.php   ← لا تُلمس
app/Services/Inventory/InventoryService.php         ← باستثناء إبطال الكاش إن لزم بتعديل مستقل
app/Services/JournalEntryService.php                ← يُعاد استخدامه فقط، لا يُعدّل
app/Observers/{CharAccount,Bank,Box,Stock,...}Observer.php
database/bac/**                                     ← لا نقل ولا حذف ولا تعديل عشوائي
resources/js/shared/lookup.js                       ← يُعاد استخدامه كما هو
resources/views/dashboard/**                        ← لا تُلمس
resources/views/operation/accounting/**             ← لا تُلمس
```

---

## 44. الممنوعات (ملتزم بها في الخطة)

- ✅ لا إعادة بناء للمخزون.
- ✅ لا إعادة بناء لفواتير البيع.
- ✅ لا إعادة بناء لفواتير الشراء.
- ✅ لا إنشاء نظام حسابات جديد.
- ✅ لا جدول ديون جديد بلا دليل.
- ✅ لا تغيير Architecture بلا ضرورة.
- ✅ لا Framework Frontend جديد (لا React/Vue/Tailwind/Angular).
- ✅ لا تكرار لأنظمة موجودة.
- ✅ لا Negative Quantity كبديل لمستند المرتجع.
- ✅ لا تحميل كل بيانات التقارير للمتصفح.
- ✅ لا حساب تقارير في JS إذا أمكن في SQL.
- ✅ لا حذف حركة مخزون تاريخية بلا دراسة.

---

## 45. نقطة مهمة جدًا حول الأرصدة

**لا تخلط بين Inventory Balance و Reports:**

- **الأرصدة:** «كم يوجد الآن؟» (الصنف: عود ممتاز، المستودع: الرئيسي، الموجود: 35 علاقية).
- **التقارير:** «ماذا حدث خلال فترة معينة؟» (من 01/09 إلى 30/09: إجمالي المبيعات، المشتريات، المرتجعات، الربح، حركة المخزون).

**الرصيد الحالي لا يخبرك كم اشتريت هذا الشهر أو كم بعت أو كم رجعت أو كم تلف — هذه وظيفة التقارير.**

---

## 46. نقطة مهمة جدًا حول الأرباح

**ممنوع:** `إجمالي المبيعات = الأرباح`.

**الصيغة المتوافقة مع المشروع (تعتمد `cost_price` الفعلي المخزَّن):**

```
الربح = المبيعات
      − مرتجعات المبيعات
      − تكلفة البضاعة المبيعة (Σ qty×cost_price)
      + عكس تكلفة البضاعة المرتجعة (Σ return_qty×cost_price)
```

---

## 47. نقطة مهمة حول المرتجعات والتقارير

المرتجعات ليست مجرد شاشة إدخال، بل جزء من دورة النظام:

```
Invoice → Inventory → Accounting → Return
        → Inventory Reverse Effect → Accounting Reverse Effect → Reports
```

لذلك تُصمَّم كوحدة متكاملة، لا كشاشة معزولة.

---

## 48. عند وجود أكثر من حل

تم تطبيق هذا البند على **قرار المحاسبة** (القسم 14): عرض الخيار A والخيار B بالمزايا والعيوب ومدى التوافق المعماري، ثم التوصية بالخيار B لأسباب تقنية (توافق 100% مع الوضع الفعلي للفواتير التي لا تُنشئ قيودًا).

---

## 49. لا تفترض الملفات

التزمتُ به حرفيًا: كل "غير موجود" أعلاه مُثبت بـ grep/glob على الكود الفعلي (مثال: `reports|Reports|تقارير` ⇒ 0 نتائج؛ `JournalEntryService::create` ⇒ 3 مواضع فقط، لا تشمل الفواتير).

---

## 50. التحليل مبني على الكود الفعلي

كل إشارة لمكوّن أعلاه تحمل **المسار الحقيقي** في المستودع (مثل `resources/js/invoice/sales_invoice.js`، `app/Services/Inventory/InventoryService.php`، `database/bac/2026_09_15_162447_create_inventory_movements_table.php`) — لا وصفًا عامًا يصلح لأي Laravel ERP.

---

## 51. النتيجة النهائية (هيكل الوثيقة)

```
CURRENT SYSTEM  (الأقسام 1–7)
      ↓
GAP ANALYSIS    (الأقسام 8–9)
      ↓
RETURN DESIGN   (الأقسام 10–12, 15–22)
      ↓
INVENTORY INTEGRATION  (القسم 13)
      ↓
ACCOUNTING INTEGRATION (القسم 14)
      ↓
REPORTING DESIGN       (الأقسام 24–30)
      ↓
PERFORMANCE DESIGN     (الأقسام 28–29)
      ↓
TESTING                (القسم 23)
      ↓
IMPLEMENTATION PLAN    (القسم 31 + 33–35)
```

**الهدف النهائي:** إضافة نظام مرتجعات المبيعات والمشتريات وشاشة تقارير متكاملة مع النظام الحالي، مع المحافظة على هيكل المشروع، وإعادة استخدام أكبر قدر ممكن من الكود الموجود، وعدم كسر فواتير البيع والشراء أو حركة المخزون أو الحسابات أو الواجهة الحالية.

---

## 52. أهم تعليمات أخيرة

لا تبدأ بكتابة الكود قبل اعتماد الخطة.

```
تحليل المستودع → قراءة الملفات → فهم العلاقات
→ تحديد الموجود → تحديد الناقص → تحديد المخاطر
→ وضع التصميم → وضع خطة التنفيذ
```

ثم بعد موافقتك على الخطة يمكن الانتقال إلى التنفيذ خطوة بخطوة (بدءًا من **Phase 0** التحقق البنيوي).

---

## الخلاصة التنفيذية

الحقيقة المحورية التي غيّرت مسار التحليل كله:

1. **المرتجعات غير منفَّذة إطلاقًا** — لا جداول، لا Services، لا Controllers، لا JS. الموجود فقط ثوابت مخزون جاهزة + حسابات جاهزة في الدليل + عنوانان فارغان في القائمة الجانبية.
2. **الرصيد مشتق من الحركات** ⇒ إنشاء حركة المرتجع ينشئ الرصيد تلقائيًا، وحذفها يلغيه. **لا حاجة لأي منطق أرصدة.**
3. **الفواتير لا تُنشئ قيودًا محاسبية** ⇒ الخيار المتوافق معماريًا (B) هو إبقاء المرتجع بلا قيود، بنفس وضع الفواتير، مع ترك جسر محاسبي اختياري مستقبلي للفواتير والمرتجعات معًا.
4. **تكلفة مرتجع الشراء** تُؤخذ من `inventory_movement_details.unit_cost` (Landed Cost) لا من سعر الفاتورة الخام.
5. **تكلفة مرتجع البيع** تُنسخ من `sales_invoice_details.cost_price` لتُعكس أثر COGS في تقارير الربح.
6. **مشكلة `database/bac`** خطر بنيوي يجب حلّه أولًا قبل أي ميجريشن جديد.

**لا يبدأ أي كود قبل اعتماد الخطة. بمجرد الاعتماد نبدأ بـ Phase 0 (التحقق البنيوي)، ثم المراحل 1→14 بالترتيب.**