# خطة الإصلاح الاحترافية — تقرير تحليلي شامل (تحليل فقط، دون تنفيذ)
**التاريخ:** 2026-11-03
**المبادئ المُلزِمة:** ① لا كسر للنظام ② لا أخطاء صامتة ③ حماية جسر البيانات ④ عدم انتهاك الطبقات ⑤ فصل المسؤوليات ⑥ فحص الفهارس.

---

# الجزء الأول — قواعد الهندسة المُلزِمة للتنفيذ

## 1.1 قاعدة "لا كسر"
- كل تغيير في مسار الكتابة (فواتير/مرتجعات/فرز/سندات) يجب أن يبقى **قابلاً للتراجع** خلال نفس الجلسة: تعديل القارئ قبل الكاتب، والكاتب يبقى كما هو حتى يجتاز القارئ اختبار التوازي.
- قبل أي تبديل مسار قراءة: تشغيل **فترة تشغيل موازٍ (Shadow Mode)** — الكاش الجديد يُبنى بجانب القديم ويُسجّل الفرق في log. لا تبديل قبل صفر فرق لعدة أيام.
- الاختبارات الموجودة (`tests/Feature/InventorySystemTest.php`, `DashboardDataTest.php`) هي خط الدفاع — لا يُقبَل إصلاح يخفض تغطيتها.

## 1.2 قاعدة "لا أخطاء صامتة"
كل نقطة تستدعي `catch (\Throwable)` تُراجَع: السماح فقط بالكاتش الذي **يسجّل + يرفع** أو **يسجّل + يُبدّل لمسار مكافئ مُعلَن**. حالتان مخالفتان الآن:
- `PaymentVoucherService::fetchDescendantIds` (سطر 801): عند فشل CTE يهبط صامتاً إلى `fetchDescendantIdsLegacy` مع `Log::warning` فقط — **النتيجة صحيحة لكن لا أحد يعرف أن النظام يعمل بالمسار البطيء**. الإصلاح: إضافة metric/flag ظاهر (سجل يُحصى يومياً) وليس مجرد warning مدفون.
- `CharAccountController::update` (سطر 790): يُعيد رسالة `$e->getMessage()` الخام للمستخدم مع 500 — قد يكشف تفاصيل داخلية ويبلع الخطأ الأصلي غير مُصنَّف. الإصلاح: تصنيف `RuntimeException` (أخطاء أعمال → 422 برسالة واضحة) عن باقي الأخطاء (→ 500 برسالة عامة + log كامل + معرّف تتبع).

## 1.3 قاعدة "جسر البيانات"
جسر البيانات الحالي = `inventory_movement_details` + `inventory_movements` هو **مصدر الحقيقة الوحيد** للأرصدة. أي إضافة جدول أرصدة (`inventory_balances`) تُعدّ **طبقة مشتقة (Derived)** لا مصدراً:
- المصدر يظل المرجع عند الشك (أمر reconcile يقارن الجدولين ويُوقف التبديل عند فرق).
- `applyMovement()/reverseMovement()` في `InventoryService` (سطر 542/550 — no-op مقصود) هما **نقطة الحقن الوحيدة** المسموح بها. ممنوع تحديث الرصيد من أي Controller أو Observer مباشرة.

## 1.4 قاعدة "الطبقات"
الاتجاه المسموح: `Controller → Service → Model`. الانتهاكات المكتشفة فعلياً:
| الانتهاك | المكان | الخطورة |
|---|---|---|
| Observer يستدعي Controller مباشرة | `CustomerObserver:17,52,92` → `OpeningBalanceController::forgetAllCache()` (static) | عالية — طبقة العرض تُستدعى من طبقة الأحداث؛ أي refactor للController يكسر الObserver صامتاً |
| Observer يدير منطق أعمال كامل | `PurchaseInvoiceObserver::deleted` يحذف قيداً محاسبياً + حركات مخزنية (38 سطراً من منطق) | متوسطة — الموضع صحيح (Event layer) لكن المنطق يجب أن ينزل إلى Service |
| `DB::transaction` + `DB::beginTransaction` داخل Controller | `InventoryMovementController:168`, `PurchaseDistributionController:258` | متوسطة — الـ transaction جزء من منطق الأعمال لا من العرض |

## 1.5 قاعدة "فصل المسؤوليات"
- `CharAccountController` يحتوي مولّد أكواد + خوارزميتي شجرة تعاوديتين (`generateChildCode`, `updateChildrenLevels`, `updateChildrenCodes`, `isDescendant`) — كلها منطق أعمال في طبقة العرض (~700 سطر كونترولر). الحل: `AccountTreeService`.
- `PurchaseDistributionController` (1100+ سطر) يدير التوزيع والفوترة والقيود — يُفصل إلى `DistributionService` (يوجد هيكل Services جاهز في `app/Services/Purchases/`).

---

# الجزء الثاني — فحص الفهارس (كل جدول رئيسي)

## 2.1 `inventory_movement_details` — ⚠️ خلل ترتيب فعلي
الفهارس الحالية:
| الاسم | الأعمدة |
|---|---|
| `imd_item_wh_unit_idx` | (item_id, warehouse_id, unit_id) |
| `imd_item_wh_unit_type_idx` | (item_id, warehouse_id, unit_id, type_id) |
| `imd_supplier_lot_idx` | (item_id, **type_id**, warehouse_id, unit_id, supplier_account_id) |
| `imd_lot_source_idx` | (lot_source_detail_id) |
| مفردة | movement_id, item_id, type_id, unit_id, warehouse_id |
| FK | movement_id (cascade), item/type/unit/warehouse (restrict) |

**الخلل:** استعلامات القراءة الأساسية (`availableQuantityForSupplier`, `lastCostForSupplier`, `lockStockRows`) تفلتر بـ **(item_id, warehouse_id, unit_id, supplier_account_id) بدون type_id**. لأن `type_id` في الموضع الثاني من `imd_supplier_lot_idx`، يوقفه المحسّن بعد `item_id` — أي أن المورد يُستبعد بـ **scan** على كل صفوف الصنف لا بـ seek.
**الإصلاح المقترح (migration عكسية آمنة):** إضافة `imd_lot_read_idx (item_id, warehouse_id, unit_id, supplier_account_id)` ثم بعد القياس حذف `imd_item_wh_unit_idx` (مضمّن في الجديد) ومراجعة `imd_item_wh_unit_type_idx` مقابل حقيقي الفلترة بـ type (استخدامها واحد فقط: `currentUnitCost`). **لا حذف فوري — إضافة أولاً وقياس `EXPLAIN`.**

## 2.2 `JournalEntrryLine` — ✅ سليم
`entryID`, `accountID`, `coinsID`, و(`accountID, entryID`) مركب. استعلامات كشف الحساب ودفتر الأستاذ (`whereIn accountID` + join على entryID) مشمولة. الفجوة الوحيدة: لا فهرس على `entryDate` هنا لكن الفلترة الزمنية تتم على `Journal_Entries` بعد الـ join — يجب التأكد من فهرس `entryDate` على `Journal_Entries` (يوجد `idx_je_date` من migration 2026-10-22) ✅.

## 2.3 `inventory_movements` — ✅ سليم مع فجوة
`movement_type`, `direction`, `movement_date`, `warehouse_id`, (`source_type, source_id`), (`direction, movement_type`), (`movement_date, direction`), `display_id` unique.
**فجوة واحدة:** `generateSortingNumber()` يفلتر بـ (`source_type` + `document_number LIKE 'SORT-%'`) — `document_number` بلا فهرس ⇒ فحص كامل لصفوف الفرز. إضافة `idx_im_source_doc (source_type, document_number)`.

## 2.4 `purchase_returns` / `purchase_return_details` — ✅ شامل (13 فهرساً)
## 2.5 `sales_returns` / `sales_invoice_details` — ✅ شامل (بما فيه `sid_supplier_idx`)
## 2.6 `purchase_invoices` / تفاصيلها — ✅ (أعيد بناؤها في 2026-10-22 بعد إسقاط مكررات)
## 2.7 `distributions` / `distribution_details` — ✅ لكن `distribution_details.sales_invoice_id` مفهرس و`whereNull` عليه يحتاج مراجعة `EXPLAIN` (فهارس nullable تعمل في MySQL 8 لكن بحدود).
## 2.8 `characcount` — `accParent`, `IsActive`, `system_key` موجودة. **فجوة:** `accCode` يُستخدم في مقارنات نطاق (`AccountStatementReport` سطر 205-209) وبحث `LIKE 'prefix%'` — يحتاج فهرساً (تحقق من unique constraint إن وُجد فهو يكفي).
## 2.9 `payment_vouchers` / `receipt_vouchers` — ✅ شاملة (المعرفات + is_posted + التواريخ).

**إجراء إلزامي قبل أي تغيير:** تشغيل سكربت `SHOW INDEX FROM <table>` + `EXPLAIN` للاستعلامات الـ 6 الأثقل وتوثيق النتائج في `docs/index-audit.md` — هذا الملف أعلاه مبني على هجرات الإنشاء، والحقيقة النهائية حالة القاعدة الفعلية.

---

# الجزء الثالث — خطة الإصلاح مشكلة-بمشكلة

> لكل مشكلة: السبب → خطر الكسر → الإصلاح → كيف يحفظ المبادئ الستة.

## P0-1: جدول أرصدة المخزن المشتق (`inventory_balances`)
- **السبب:** الرصيد = SUM على جدول الحركات في كل قراءة؛ 40+ استعلاماً لفاتورة واحدة.
- **خطر الكسر:** عالٍ إن نُفّذ دفعة واحدة (جسر البيانات يتغير).
- **الإصلاح المرحلي:**
  1. **مرحلة أ (قارئ فقط):** migration لجدول `inventory_balances (item_id, type_id, warehouse_id, unit_id, supplier_account_id, qty_in, qty_out, last_detail_id, updated_at)` بمفتاح فريد خماسي + فهرس مقلوب للقراءة.
  2. **مرحلة ب:** أمر `inventory:balances:rebuild` يبني الجدول من الحركات (المصدر) بـ transaction واحد، ويطبع نتيجة مطابقة مع الحساب المباشر.
  3. **مرحلة ج:** تعبئة `applyMovement()/reverseMovement()` داخل `InventoryService` (upsert ذري `increment/decrement` داخل نفس الـ transaction للفاتورة — لا transaction جديدة). الحذف عبر الـ Observer يمر بنفس الدالتين.
  4. **مرحلة د (Shadow):** `availableQuantityForSupplier()` تقرأ من الجدول الجديد وتقارن بالحساب المباشر في الحاوية نفسها، تسجّل أي فرق في `storage/logs/balance_drift.log` دون إرجاعه للمستخدم.
  5. **مرحلة هـ:** بعد صفر فرق — التبديل، مع أمر `inventory:balances:verify` يعمل مجدولاً ليلاً كصف حماية (يرفع استثناءً مرئياً لا صامتاً عند الفرق).
- **حفظ المبادئ:** المصدر يظل الحركات (جسر محفوظ)، التبديل معكوس بmigration down، لا كاتش صامت (الفرق يُبلَّغ)، التحديث من Service فقط (طبقات).

## P0-2: N+1 أرصدة الدفعات في المرتجعات
- **الملفات:** `PurchaseReturnService::batchesFromSorting` (سطر 357) و `validateStockAvailability` (سطر ~930)، `SalesInvoiceService::validateStockAvailability` (سطر 304).
- **الإصلاح:** دالة جديدة في `InventoryService`: `availableQuantityForSupplierBatch(array $keys): array` — استعلام `GROUP BY` واحد يعيد الرصيد لكل (item, warehouse, unit, supplier) مطلوبة، ثم الحلقة تقرأ من الذاكرة. النسخة الفردية تبقى (تستخدمها شاشات اللحظة).
- **حفظ المبادئ:** تعديل في Service فقط، الـ Controllers والـ validation لا يتغير سلوكها؛ رسالة الخطأ نفسها.

## P1-3: تقرير السندات — بث بدل تحميل كامل
- **الملف:** `VouchersReport::run` (سطر 82-196).
- **الإصلاح:** استعلام `UNION ALL` واحد (receipts + payments بأعمدة موحّدة) مع `ORDER BY date, kind, id` و`LIMIT/OFFSET` في SQL، والإجماليات بـ `SUM` منفصلة على كل فرع. إن تعذّر UNION (فروق أعمدة) فالحل الأدنى: `->limit(min($page*$perPage+1, cap))->get()` على كل فرع مع إجمالي بـ aggregate — لا يبقى `get()` غير محدود.
- **حفظ المبادئ:** نفس شكل الـ JSON تماماً (عقد التقرير محفوظ) — يُختبر بمقارنة ناتج القديم والجديد لنفس الفلاتر.

## P1-4: ترقيم خادمي لشاشات القوائم
- **الملفات:** `CustomerController` (سطر 168/183)، `SupplierController` (سطر ~160/176/490)، `ItemService::allForView()`.
- **الإصلاح:** `paginate(50)` مع الحفاظ على شكل `table.blade.php` عبر تمرير paginator (Blade يدعم links) — endpoint `list` يعيد نفس الـ HTML مع meta ترقيم؛ الطباعة/التصدير تمر بـ `allForView` كما هي (مقصودة كاملة).
- **حفظ المبادئ:** لا تغيير في طبقة النموذج؛ العرض فقط. التصدير يبقى كاملاً (سلوك موثّق).

## P1-5: `generateChildCode` بالـ SQL
- **الملفات:** `CharAccountController:334` (ويُستدعى من `StockController` عبر إنشاء الحساب).
- **الإصلاح:** استعلام واحد `SELECT MAX(CAST(SUBSTRING(accCode, LENGTH(:parent)+1) AS UNSIGNED)) FROM characcount WHERE accParent = :id AND accCode LIKE CONCAT(:code,'%')` + الفلترة بطول اللاحقة في PHP على قيمة واحدة. ينقل إلى `AccountTreeService` مع `lockForUpdate` على الأب داخل transaction (يوجد transaction أصلاً) لمنع سباق كودين متزامنين — **هذا إصلاح صحة لا أداء فقط**.
- **حفظ المبادئ:** نفس قواعد الترقيم (خانة/خانتان، حد 9/99) دون تغيير أي كود قائم.

## P1-6: شجرة الحسابات — تعاود N+1 وإصلاح الكود
- **الملف:** `CharAccountController::updateChildrenLevels` (سطر 819: استعلام + update لكل ابن، تعاودياً) و`updateChildrenCodes` (سطر 854: `exists()` لكل ابن!).
- **الإصلاح:** تحديث `accLevel` بـ UPDATE واحد على الشجرة (CTE RECURSIVE لحساب الأبناء ثم `UPDATE ... JOIN`)، و`updateChildrenCodes`: تحضير كل الأكواد الجديدة في الذاكرة أولاً، فحص التعارض بـ `whereIn(accCode, $newCodes)->whereNotIn('accountID', $ids)->exists()` **مرة واحدة**، ثم التحديث. ينتقل كله إلى `AccountTreeService`.
- **حفظ المبادئ:** نفس transaction، نفس رسائل الخطأ، نفس الترتيب (أب ثم أبناء).

## P2-7: توحيد `lockStockRows`
- **الإصلاح:** النسخ الأربع (Sales/SalesReturn/PurchaseInvoice/PurchaseReturn) تُستبدل باستدعاء `InventoryService::lockStockRows()` (الموجودة أصلاً)، وتتحصل الدالة نفسها: استعلام واحد بشرط `OR` على مجموعات الأعمدة `(item_id, warehouse_id, unit_id, supplier_account_id)` مرتبة تصاعدياً (نفس ترتيب القفل الحالي لمنع deadlock).
- **حفظ المبادئ:** نفس ترتيب القفل = نفس سلوك التزامن؛ حذف الكود المكرر يقلل سطح الكسر مستقبلاً.

## P2-8: حذف الحركات مجمّعاً
- **الملفات:** `SalesInvoiceObserver`, `PurchaseInvoiceObserver`, `PurchaseInvoiceService:402`, `SalesReturnService:558`.
- **الإصلاح:** بعد جلب الحركات (`with('details')` محفوظ)، حذف بجملتين: `InventoryMovementDetail::whereIn('movement_id', $ids)->delete()` ثم `InventoryMovement::whereIn(...)->delete()` — **مع تنبيه موثّق:** الحذف الجماعي لا يطلق أحداث Eloquent للتفاصيل؛ listener `FlushDashboardCache` يعمل عبر حدث حذف الرأس فقط (مصرّح به في تعليق `AppServiceProvider:84-86` — السلوك الحالي نفسه، لا تغيير دلالي).
- **حفظ المبادئ:** نفس النتيجة النهائية للجسر؛ لا أحداث مفقودة إضافية.

## P2-9: الداشبورد — أسماء الأطراف مجمّعة
- **الملف:** `DashboardService::partyNameForAccount` (سطر 426).
- **الإصلاح:** قبل الحلقة: ثلاث `whereIn(accountID, $uniqueIds)->pluck(...)` (Suppliers, Customers, CharAccount) وتجميعها في map. الكاش داخل الطلب يبقى للتوافق.
- **حفظ المبادئ:** لا تغيير في أولوية الاسم (مورد → عميل → حساب) — يُنقل منطق الأولوية كما هو إلى بناء الـ map.

## P2-10: CTE موحّد لأبناء دليل الحسابات
- **الإصلاح:** نقل `fetchDescendantIds` (نسخة CTE من `PaymentVoucherService:779`) إلى `AccountTreeService` واستدعاؤها من الخدمتين. كاش `receipt_picker_descendants_*` كما هو.
- **لا خطأ صامت:** عند فشل CTE يُسجَّل error بمقياس معدّل (عدّاد في log بنمط ثابت يمكن تنبيه عليه) وليس مجرد warning — والهبوط Legacy يبقى (توافر أهم من الفشل).

## P2-11: `traceLotToInvoice` بـ CTE واحد
- **الملف:** `PurchaseReturnService:388`.
- **الإصلاح:** CTE RECURSIVE من `lot_source_detail_id` يرجع الشكل حتى عمق 5 لكل الدفعات دفعة واحدة (بديل N×depth). الفلترة في PHP كما هي.
- **حفظ المبادئ:** عمق 5 ورسالة عدم الوصول تبقى؛ سلوك مطابق.

## P3-12: `allStockBalances` على جدول الأرصدة
- **بعد P0-1 فقط.** الاستعلامان الكبيران يُستبدلان بقراءة `inventory_balances` مع join أسماء، وترقيم. `HAVING` بدل `filter()` في PHP.

## P3-13: `PurchaseDistributionController` تنظيف
- `list()`: إجماليات بـ SUM مجمّع (subquery على distribution_details) بدل جلب التفاصيل؛ ترقيم بدل limit(300) الصامت.
- `stats()`/`list()`: 3 counts في استعلام واحد (`SELECT COUNT(*), SUM(status='draft'), SUM(status='invoiced')`).
- `customerSummary()`: ترقيم + الإجماليات aggregate.
- فصل منطق `issueInvoice` (سطر 768-944) إلى `DistributionService`.
- **حفظ المبادئ:** إصلاح `DB::beginTransaction` في السطر 258 إلى `DB::transaction()` (catch/rollback مضمون بدل يدوي).

## P3-14: `AccountStatementReport` — بث
- استبدال `->get()->groupBy('accountID')` (سطر 285-291) بـ `lazy()` مع تراكم الرصيد تسلسلياً (الاستعلام مرتب أصلاً بـ accountID ثم التاريخ). الصمامان (عدد/زمن) يبقيان — الترتيب الحالي للصفوف يبقى مطابقاً.

## P3-15: `ItemService::USAGE_TABLES` فحص الاستخدام (سطر 115)
- ثلاث `exists()` متتالية لكل جدول عند حذف صنف/نوع/وحدة — مقبولة (عدد ثابت 3، لا N+1) — **لا تعديل**، موثّقة لمنع "إصلاح" زائف.

## P3-16: `ReceiptVoucherService::generateVoucherNumber`
- حلقة `do-while` على `exists()` — سباق نظري بين الفحص والحفظ. الإصلاح: unique index على `voucherNumber` (يُفحص وجوده) + retry عند duplicate error **بسجل واضح**، لا صمت.

## P3-17: خطر طبقات — Observers
- `CustomerObserver` / `SupplierObserver`: نقل استدعاء `OpeningBalanceController::forgetAllCache()` إلى خدمة كاش مستقلة (`OpeningBalanceCache` أو استخدام `DashboardCache` بنمط إصدار) — الObserver يستدعي Service لا Controller.
- نقل منطق `PurchaseInvoiceObserver::deleted` (حذف القيد + الحركات) إلى `PurchaseInvoiceService::onDeleted($invoice)` — الObserver يبقى مُنفّذاً رفيعاً.
- `SalesInvoiceObserver` نفس المعالجة.
- **لا كسر:** الـ Observer يبقى مسجلاً في `AppServiceProvider` بنفس الأحداث؛ فقط جسمه ينزل للخدمة.

## P3-18: `DashboardCache::bump()` غير ذرّية (موثّقة داخل الكلاس)
- السلوك الحالي مقبول (TTL 30 ثانية يغلف الفجوة). الإصلاح الاختياري الآمن: `Cache::increment()` بدل get+put (مدعوم في file store). لا تغيير سلوكي.

---

# الجزء الرابع — تسلسل التنفيذ الآمن

```
المرحلة 0 (قراءة فقط): SHOW INDEX + EXPLAIN → docs/index-audit.md  [لا كود]
المرحلة 1 (خطر صفر):   فهرس imd_lot_read_idx + idx_im_source_doc     [إضافة فهارس فقط]
المرحلة 2 (خدمية):     AccountTreeService (توليد كود/شجرة/CTE) + استبدال
                       الاستدعاءات — سلوك مطابق، اختبارات الشجرة تمر
المرحلة 3 (N+1 آمنة):  batch reads في InventoryService + Dashboard + حذف
                       مجمّع — كل واحدة PR مستقل مع اختبار
المرحلة 4 (جسر):       inventory_balances: migration → rebuild → hooks →
                       shadow mode → تبديل → verify مجدول
المرحلة 5 (عرض):       ترقيم العملاء/الموردين/التوزيعات + تقرير السندات UNION
المرحلة 6 (طبقات):     نزول منطق الـ Observers والكونترولرات الكبيرة إلى Services
```
كل مرحلة: نشر منفصل + مراقبة `balance_drift.log` وزمن الاستجابة قبل المرحلة التالية. التراجع ممكن في كل مرحلة بمعزل عن غيرها.

# الجزء الخامس — ما لا يُلمس
- منطق `applyMovement/reverseMovement` كـ hooks (نقطة الحقن المصممة).
- `ChartAccountScope` الكاش.
- منطق `quotaEquivalent` وتسعير الفرز (دقة محاسبية حساسة — يُختبر فقط، لا يُعاد كتابته).
- صمّامات `AccountStatementReport` (هي الحماية الحالية).
- عقد JSON للداشبورد والتقارير (`docs/dashboard-contract.md`).
