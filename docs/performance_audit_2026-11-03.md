# تقرير تحليل الأداء — اختناقات البيانات، الاستعلامات الثقيلة، و N+1
**التاريخ:** 2026-11-03 — تحليل فقط، دون أي تعديل.

---

## ملخص تنفيذي
النظام مُعدّ جيداً في معظمه (Eager loading في التقارير، فهارس كثيرة، Cache في الداشبورد). المشاكل الحقيقية تتركز في:

1. **رصيد المخزون يُحسب من الحركات (SUM) في كل استدعاء** — أكبر عنق زجاجة في النظام.
2. **حلقات N+1 في `ReceiptVoucherService` / `DashboardService` / `PurchaseReturnService`.**
3. **تقرير السندات يحمل كل السندات ثم يرقّم في الذاكرة (OOM).**
4. **شاشة العملاء/الموردين بدون ترقيم.**
5. **مسح المخزون الكامل في `allStockBalances`.**

---

## القسم 1 — مخزون: عنق الزجاجة الأول

### 1.1 الرصيد = SUM على الحركات في كل مرة (الأخطر)
**الملفات:**
- `app/Services/Inventory/InventoryService.php`
  - `availableQuantity()` (سطر 76) — استعلامان SUM كاملان (in + out) مع join.
  - `availableQuantityForSupplier()` (سطر 131) — استعلامان SUM.
  - `currentUnitCost()` / `lastCost()` / `lastPricing()` — استعلامات فرز كاملة على الجدول الكبير.

**المنطق:** كما يوثّق الملف نفسه (سطر 526-537): *"لا يوجد جدول أرصدة منفصل — الرصيد يُحسب دائماً مباشرة من inventory_movements + inventory_movement_details"*.

**أين يتضاعف الضرر:**
| الاستدعاء | التكلفة |
|---|---|
| `validateStockAvailability()` في `SalesInvoiceService:304` و `PurchaseReturnService:~930` | استدعاء لكل سطر فاتورة ⇒ فاتورة بـ 20 سطراً = **40 استعلام SUM** على جدول يضم مئات الآلاف |
| `availableForReturn()` في `PurchaseReturnService:424` | استدعاء داخل حلقة `foreach ($grouped as ...)` (سطر 931) |
| `batchesFromSorting()` (سطر 357) | استدعاء لكل دفعة مرشحة |
| `DistributionService` أثناء التوزيع | لكل صنف/مخزن |
| شاشات اللحظة (`available()`, `pricing()`) | لكل طلب AJAX من الواجهة |

**الأثر:** كل فاتورة بيع = 40–60 استعلاماً ثقيلاً؛ ومع نمو الحركات تتباطأ كل العمليات خطياً. الفهارس `imd_supplier_lot_idx` الموجودة تخفف لكن لا تحل (SUM يجمع صفوفاً بالملايين مع الوقت).

**الإصلاح الاحترافي:** جدول `inventory_balances` (item, type, warehouse, unit, supplier) يُحدَّث ذرياً (upsert/increment) داخل نفس الـ transaction عند إنشاء/حذف/عكس أي حركة، مع أمر `artisan` لإعادة البناء، وفترة ترانزيشن مقارنة بالحساب المباشر. `applyMovement()/reverseMovement()` الحاليان hook جاهزان لهذا بالضبط.

### 1.2 قفل صفوف المخزون صف-صف (N استعلام قفل)
**الملفات:** `InventoryService::lockStockRows()` (سطر 23)، والنسخ المكررة في `SalesInvoiceService:252`, `SalesReturnService`, `PurchaseInvoiceService:269`, `PurchaseReturnService:1060`.
**المنطق:** حلقة `foreach ($pairs)` تنفّذ `SELECT ... lockForUpdate()` **لكل تركيبة صنف/مخزن/وحدة/مورد على حدة**. فاتورة 15 صنفاً = 15 قفلاً متسلسلاً (كل واحد عشرات ms) داخل transaction يرفع زمن الاحتفاظ بالقفل على جدول مشترك.
**الإصلاح:** استعلام واحد بمجموعات OR على `(item_id, warehouse_id, unit_id, supplier_account_id)`، أو قفل على مستوى أعلى (range lock / named lock على المفتاح المركب).

### 1.3 توليد `display_id` بقفل كامل الجدول
`InventoryService::sortInventory()` سطر 420: `InventoryMovement::lockForUpdate()->orderByDesc('movement_id')->first()` — قفل صفوص + فرز كامل لتوليد رقم تسلسلي، ويزيد خطر deadlock مع عمليات متزامنة. نفس النمط في توليد أرقام الفواتير (`generateSortingNumber` سطر 508).
**الإصلاح:** جدول `sequences` (موجود أصلًا في خطط الفحص) أو `DB::getPdo()->lastInsertId` مع unique constraint.

### 1.4 شاشة أرصدة المخزون — مسح كامل مضاعف
`InventoryMovementController::allStockBalances()` (سطر 756):
- تجميع خماسي SUM على **كل** `inventory_movement_details` بلا حدود زمنية ولا ترقيم.
- ثم استعلام ثانٍ `MAX(movement_detail_id)` على الجدول كامل (سطر 815).
- ثم `whereIn` بمئات المعرفات.
مع نمو البيانات تصبح الشاشة بطيئة جداً وهي شاشة افتتاحية للمخزن. **الإصلاح:** يعالج تلقائياً مع جدول الأرصدة (1.1)، أو HAVING بعد التجميع بدل `filter()` في PHP، وترقيم صفحات.

### 1.5 `traceLotToInvoice` — استعلام متسلسل تعاودي
`PurchaseReturnService:388` — لكل صف دفعة: استعلام ancestor، ومع التعاود حتى عمق 5. يُستدعى من حلقة `batchesFromSorting` (سطر 332). **الإصلاح:** CTE RECURSIVE واحد لكل الدفعات (مثلما فعل `PaymentVoucherService::fetchDescendantIds` — المثال الجيد الموجود في النظام).

---

## القسم 2 — N+1

### 2.1 `DashboardService::partyNameForAccount()` — 3 استعلامات لكل حركة
**الملف:** `app/Services/Dashboard/DashboardService.php` سطر 426-445.
**المنطق:** لكل `account_id` فريد في آخر 10 حركات: استعلام Supplier + استعلام Customer + استعلام CharAccount (3 استعلامات لكل حساب، حتى 30 استعلاماً لطلب واحد). يوجد `partyNameCache` للطلب الواحد فقط، والنتيجة تُبنى كل refresh كاش.
**الإصلاح:** `Supplier::whereIn('accountID', $ids)->pluck(...)` ×3 مجمّعة قبل الحلقة (استعلامات ثابتة بغض النظر عن عدد الحسابات).

### 2.2 `DashboardService::row()` — وصول لعلاقات within foreach
سطر 298-300: `$detail->item->...`, `$detail->type->...`, `$detail->unit->...` — هذه محمّلة eager في `movementRows()` (سطر 226) ✅. لكن `recentMovements()` (سطر 504) يحمّل `item` فقط، وسطر 523 يقرأ `$first->item->itemName2` — آمن. **غير مكتمل:** `type` و`unit` غير مستخدمين هنا، لا مشكلة.
**الحكم:** هذا الملف سليم إلا النقطة 2.1.

### 2.3 `ReceiptVoucherService::getPicker()` — وصول `$entity->coin` داخل foreach
`app/Services/ReceiptVoucherService.php` سطر 647-659 و `PaymentVoucherService` سطر 682-693: `$entity->coin->coinsCode` داخل الحلقة — لكن `getPickerEntities()` تحمّل `with(['coin:...'])` ✅ eager. **سليم.**
**المشكلة الحقيقية هنا:** `getDescendantIds()` في `ReceiptVoucherService:700` يستخدم الحلقة التدريجية (استعلام لكل مستوى × DESCENDANT_MAX_LEVELS) بينما `PaymentVoucherService:779` يستخدم CTE RECURSIVE. انسخ نمط CTE إلى Receipt (استعلام واحد بدل 4–6).

### 2.4 `PurchaseReturnService::batchesFromSorting()` — N+1 حقيقي
سطر 357: `foreach ($candidates) { $this->inventoryService->availableQuantityForSupplier(...) }` — استعلامان SUM لكل دفعة (يضاعف مشكلة 1.1). **الإصلاح:** استعلام GROUP BY واحد لكل الدفعات دفعة واحدة.

### 2.5 `availableForReturn()` داخل حلقة التحقق
`PurchaseReturnService:931` `foreach ($grouped) { $available = $this->availableForReturn(...) }` — كل استدعاء = `find` + `get` + SUM مخزون. فاتورة 20 سطراً = 60+ استعلاماً. (نفس وحدة الألم مع 1.1)

### 2.6 `SalesInvoiceService` / `SalesReturnService` / `PurchaseInvoiceService`
- `validateStockAvailability()` (Sales:304, PurchaseReturn:~930) — N+1 على SUM (منهجية 1.1).
- الحلقات الأخرى (`recalculateTotals`، بناء القيود) تعمل على collections محمّلة مسبقاً — سليمة.
- `PurchaseInvoiceService:402` يجلب الحركات المصدرية بـ whereIn ثم يحذف تفاصيلها حلقة-حلقة (`$movement->details()->delete()` سطر 404) — 2N استعلامات حذف. **الإصلاح:** `InventoryMovementDetail::whereIn('movement_id', $ids)->delete()` واحد + حذف الرؤوس واحد.

### 2.7 `SalesInvoiceObserver`
سطر 40: يجلب الحركات بـ whereIn ثم حلقة تحذف كل حركة وتفاصيلها منفصلة (`$movement->details()->delete(); $movement->delete();`) — 2N. نفس إصلاح 2.6. لا N+1 على العلاقات هنا.

### 2.8 `ReportCenterController`
سطر 199-217: ثلاث استعلامات مجمّعة بـ limit — سليم.

---

## القسم 3 — استعلامات ثقيلة / اختناقات ذاكرة

### 3.1 `VouchersReport` (تقرير السندات) — الأثقل في التقارير
`app/Reports/Vouchers/VouchersReport.php:82-196`
- يجلب **كل** سندات القبض و**كل** سندات الصرف بـ `->get()` (سطر 107، 137) بلا أي حد.
- يبني مصفوفات كاملة، `usort`، ثم `array_slice` للصفحة.
**الأثر:** عشرات الآلاف من السندات ⇒ ذاكرة عالية وبطء كل فتح للتقرير. **الإصلاح:** يونيون SQL مع window function أو على الأقل limit معقّل + عدّ/إجمالي بـ aggregate منفصل.

### 3.2 `AccountStatementReport` — محمي جزئياً لكن ثقيل
سطر 250 (صمام عدد الصفوف 20000) وسطر 296 (صمام زمن) ممتاز ✅. لكنه يجلب كل السطور دفعة واحدة (`->get()->groupBy()` سطر 285-291) بدل بثّ. يعمل اليوم بسبب الصمام، ومع كشوف متوسطة (10-20 ألف سطر) لا يزال ثقيلاً على الذاكرة.
**الإصلاح (لاحق):** `lazy()`/`cursor()` وبناء الصفوف تدريجياً، أو تجميع في SQL.

### 3.3 شاشات القوائم بلا ترقيم
- `CustomerController::index()` سطر 170 و `list()` سطر 185: **كل العملاء** `->get()` ثم render blade كامل لكل طلب AJAX.
- `SupplierController` سطر 162، 177، 493: نفس النمط لكل الموردين.
- `ItemService::allForView()` سطر 63: كل الأصناف.
- `TypeService:61` / `UnitService:61`: كل الأنواع/الوحدات (أصغر حجماً لكن نفس النمط).
**الأثر:** مع آلاف العملاء/الموردين تتحول قائمة أساسية إلى أثقل صفحة في النظام. **الإصلاح:** ترقيم خادمي (نفس النمط الموجود في `ItemService::list()`).

### 3.4 `PurchaseDistributionController`
- `list()` سطر 421: يجلب 300 توزيع مع كل تفاصيلها (قد تكون آلاف الصفوف) ثم يحسب الإجماليات في PHP — يجب أن يكون SUM في SQL + ترقيم.
- `customerDistributions()` سطر 498: `->get()` بلا حد لكل تفاصيل العميل مع 4 علاقات متداخلة (`movementDetail.item`...) — مقبول لعميل واحد، لكن `customerSummary()` سطر 557 يجلب كل صفوف العميل بلا ترقيم أيضاً.
- `stats()`/`list()` ينفذان 3 `count()` منفصلة — تُدمج في استعلام واحد.

### 3.5 `CharAccountController::generateChildCode()`
سطر 334-462: يجلب **كل أبناء الحساب** ككائنات Eloquent ثم يحسب أقصى لاحقة في PHP. يُستدعى عند كل إنشاء حساب/مخزن/صندوق (أيضاً في `StockController:182` المسار). **الإصلاح:** `max(CAST(SUBSTRING(accCode, len) AS UNSIGNED))` في SQL — استعلام واحد قيمة واحدة. مذكور أصلًا في `system_performance_report.txt` القديم ولم يُصلح.

### 3.6 `TrialBalanceReport` سطر 94
`->get()` على ميزان المراجعة كامل بدون صفحة — عادة صغير (عدد الحسابات)، مقبول لكن أضف حداً.

### 3.7 بحث `like '%...%'`
في `GeneralLedgerReport` (سطر 123-127)، `ReceiptVoucherService::getList`، `DistributionController::list` (code like) — `%prefix%` لا يستخدم فهرساً. مقبول بجداول صغيرة، مؤلم على `JournalEntrryLine` والسندات الكبيرة. **الإصلاح:** FULLTEXT أو بحث بقيمة بادئة فقط حيث ممكن.

---

## القسم 4 — نقاط قوة موجودة (للإنصاف)
- Eager loading صحيح في كل التقارير الشبيهة بالقوائم (`SalesInvoicesReport:130`, `PurchaseInvoicesReport`, `GeneralLedgerReport`).
- `DashboardCache::remember()` بفلوش عند الكتابة (`FlushDashboardCache`) — تصميم صحيح.
- `ChartAccountScope` مخزّن ساعة (سطر 26-51) — يمنع إعادة قراءة الدليل في كل تقرير.
- فهارس شاملة أُضيفت في هجرات 2026_10 (فواتير، مرتجعات، سندات، `imd_supplier_lot_idx`).
- صمامات الحماية في `AccountStatementReport` (عدد صفوف + زمن).
- أقفال Cache Lock لمنع الازدواجية في كل الخدمات.
- CTE RECURSIVE في `PaymentVoucherService::fetchDescendantIds` — النموذج المطلوب تعميمه.

---

## القسم 5 — خطة الإصلاح ذات الأولوية

| # | المشكلة | الملفات | الأثر المتوقع | الصعوبة |
|---|---|---|---|---|
| **P0-1** | جدول `inventory_balances` بدل SUM على الحركات | `InventoryService` + كل الخدمات عبر `applyMovement/reverseMovement` | تحويل 40+ استعلام/فاتورة إلى استعلامين | متوسطة (تحتاج إعادة بناء + اختبار توازي) |
| **P0-2** | تجميع أرصدة الدفعات دفعة واحدة في `batchesFromSorting` و`validateStockAvailability` | `PurchaseReturnService`, `SalesInvoiceService`, `SalesReturnService` | إزالة N+1 الأثقل | سهلة (تنتج تلقائياً من P0-1) |
| **P1-3** | تقرير السندات: استعلام مجمّع بترقيم حقيقي | `VouchersReport` | ذاكرة −90% | متوسطة (UNION + window) |
| **P1-4** | ترقيم خادمي للعملاء/الموردين/الأصناف (Blade + AJAX) | `CustomerController`, `SupplierController`, `ItemService::allForView` | أسرع شاشات الإدخال | سهلة |
| **P1-5** | `generateChildCode` بـ SQL MAX | `CharAccountController`, `StockController` | إنشاء حسابات أسرع + أقل قفلًا | سهلة |
| **P2-6** | قفل مخزون مجمّع (استعلام واحد) | `InventoryService::lockStockRows` + النسخ المكررة (DRY: انقل النسخ الخمس إلى الخدمة الواحدة) | زمن transaction أقصر | سهلة |
| **P2-7** | CTE RECURSIVE في `ReceiptVoucherService::getDescendantIds` (انسخ من Payment) | `ReceiptVoucherService` | 1 استعلام بدل 4-6 | سهلة |
| **P2-8** | حذف الحركات مجمّعاً في الـ Observer وPurchaseInvoiceService | `SalesInvoiceObserver`, `PurchaseInvoiceService` | إزالة 2N حذف | سهلة |
| **P2-9** | `DashboardService::partyNameForAccount` → 3 plucks مجمّعة | `DashboardService` | 30→3 استعلامات | سهلة |
| **P3-10** | `allStockBalances` على جدول الأرصدة + ترقيم | `InventoryMovementController` | شاشة أرصدة فورية | بعد P0-1 |
| **P3-11** | دمج `stats()` في استعلام واحد + SUM بدل حساب PHP | `PurchaseDistributionController` | طفيف | سهلة |
| **P3-12** | `AccountStatementReport` بـ lazy() بدل get()+groupBy | `AccountStatementReport` | ذاكرة أقل للكشوف الكبيرة | متوسطة |

### ملاحظات معمارية
1. **تكرار الكود** = مصدر خطر الأداء: `lockStockRows` مكرر 4 مرات، `getDescendantIds` مرتين (بواسطة مختلفتين!)، منطق validateStockAvailability مكرر 3 مرات. الإصلاح في `InventoryService` الواحدة يصلح الجميع.
2. **بدون جدول أرصدة**، كل تحسين آخر تخفيف فقط — P0-1 هو الإصلاح الجذري الوحيد ويجب أن يأتي أولًا مع أمر `inventory:rebuild-balances` وفترة تشغيل موازي (log الفروق قبل التبديل).
3. قبل أي تعديل: تشغيل قياس فعلي (يوجد `storage/app/measure_tool.php` جاهز لذلك) لتوثيق الأرقام قبل/بعد.
