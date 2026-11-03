# تقرير تنفيذ إصلاحات العمليات التجارية — 2026-11-03
**النطاق:** مخزون، بيع، شراء، مرتجعات، فرز، توزيعات، داشبورد فقط.
**غير مُلمس (كما أُمر):** دليل الحسابات، قيود اليومية، سندات القبض/الصرف، الأرصدة الافتتاحية.
**الميجرشن:** كُتب الملف فقط (`2026_11_03_100000_add_lot_read_and_source_doc_indexes.php`) — **لم يُشغَّل** (تشغيله بيدك).
**إثبات صحة:** سكربتا تحقق منفّذان فعلياً على قاعدة البيانات الحقيقية:
- `storage/app/verify_batch_equivalence.php` → **PASS 59 / FAIL 0**
- `storage/app/verify_cte_and_lock.php` → **CTE مطابقة تامة (19 دفعة) + LOCK نجح (25 تركيبة، استعلام واحد)**
- إقلاع التطبيق: `artisan route:list` → 223 مساراً بلا أي خطأ (كل الكلاسات المعدّلة تُحمّل).
- `php -l` على كل ملف معدّل → سليم.

---

# 1) `app/Services/Inventory/InventoryService.php`

## 1.1 دالة جديدة: `lockRowsForPairs()` — قفل مجمّع
**قبل (مكرر في 4 خدمات):**
```php
foreach ($pairs as $pair) {
    $query = DB::table('inventory_movement_details')
        ->where('item_id', $pair['item_id'])
        ->where('warehouse_id', $pair['warehouse_id']);
    if ($pair['unit_id']) { $query->where('unit_id', $pair['unit_id']); }
    if ($pair['supplier_account_id'] !== null) { $query->where('supplier_account_id', $pair['supplier_account_id']); }
    $query->select('movement_detail_id')->lockForUpdate()->get();   // ← استعلام قفل لكل تركيبة
}
```
**بعد:** استعلام `lockForUpdate` واحد بشرط OR على كل التركيبات، مع `usort` بنفس مفتاح `ksort` القديم (نفس ترتيب القفل ⇒ نفس سلوك منع الـ deadlock).

**السبب:** فاتورة 15 سطراً كانت تنفّذ 15 استعلام قفل متسلسلاً داخل transaction ترفع زمن الاحتفاظ بالأقفال على جدول مشترك. الآن استعلام واحد.

## 1.2 دالة جديدة: `availableQuantityBatch()`
**قبل:** `availableQuantity()` / `availableQuantityForSupplier()` — **استعلامان SUM كاملان لكل مفتاح**، تُستدعى في حلقات.
**بعد:** استعلام `GROUP BY (item, warehouse, unit, supplier, direction)` واحد لكل التركيبات المطلوبة، مع **دلالة مطابقة حرفياً** للفردي:
- `unit_id = null` ⇒ بدون شرط وحدة (مجموع كل الوحدات).
- `supplier = null` مع وجود المفتاح ⇒ `whereNull(supplier_account_id)` (التوريد العام).
- بدون مفتاح supplier ⇒ يتجاهل المورد (دلالة `availableQuantity`).
- key الناتج: `"item|warehouse|unit|sup"` (أو `"item|warehouse|unit|x"` عند تجاهل المورد).

**السبب:** جذور N+1 — كل فحص مخزون كان 2 استعلام SUM على جدول ضخم.

## 1.3 دالة جديدة: `deleteMovementsBySource(sourceType, sourceId)`
**قبل (مكرر في 5 أماكن):**
```php
$movements = InventoryMovement::where('source_type', ...)->where('source_id', ...)->with('details')->get();
foreach ($movements as $movement) {
    $this->inventoryService->reverseMovement($movement);
    $movement->details()->delete();   // استعلام لكل حركة
    $movement->delete();              // استعلام لكل حركة
}
```
**بعد:** `reverseMovement` لكل حركة (hook محفوظ) ثم **حذفان مجمّعان** (`whereIn movement_id` للتفاصيل ثم الرؤوس) + `DashboardCache::bump()` صراحةً.

**السبب:** 2N استعلامات حذف → 2. **حماية السلوك:** الحذف الجماعي لا يطلق أحداث Eloquent — لكن تفاصيل الحركات لم تكن تطلق أحداثاً أصلاً في النمط القديم (`details()->delete()` = query builder)، وأحداث حذف الرؤوس كانت تُلبي فقط `FlushDashboardCache` — أُعوّضت بـ `bump()` صريح. **لا حدث مفقود.**

---

# 2) توحيد القفل في الخدمات الأربع
| الملف | الدالة | التغيير |
|---|---|---|
| `SalesInvoiceService.php` | `lockStockRows()` | حلقة القفل → `lockRowsForPairs(array_values($pairs))` |
| `SalesReturnService.php` | `lockStockRows()` | نفس التغيير |
| `PurchaseInvoiceService.php` | `lockStockRows()` | نفس التغيير + إضافة `use InventoryService` (كانت بلا خاصية الخدمة) |
| `PurchaseReturnService.php` | `lockStockRows()` | نفس التغيير **+ إصلاح صامت كان مخفياً:** pairs كانت تُبنى **بلا `warehouse_id`** (كان يُضاف داخل الحلقة القديمة) — أُضيف `'warehouse_id' => (int) $warehouseId` للـ pair وإلا كان القفل الجديد سيقفل بدون مخزن |

**السبب:** DRY — إصلاح أي مشكلة قفل مستقبلاً في مكان واحد.

---

# 3) `SalesInvoiceService::validateStockAvailability()` — إصلاح N+1
**قبل:**
```php
foreach ($details as $i => $row) {
    ...
    $available = $this->inventoryService->availableQuantityForSupplier(...);  // ← 2 SUM لكل سطر
}
```
**بعد:** تجميع كل التركيبات → `availableQuantityBatch()` مرة واحدة → مقارنة من الذاكرة بنفس رسالة الخطأ `"الكمية المتوفرة من هذه الدفعة {$available} أقل من المطلوب {$qty}"` حرفياً وبنفس مفتاح الخطأ `details.{i}.quantity`.

**الأثر:** فاتورة 20 سطراً: **40 استعلام SUM → 1 استعلام GROUP BY.**

---

# 4) `PurchaseReturnService` — 3 إصلاحات

## 4.1 `validateStockAvailability()` (سطر ~1112)
**قبل:** حلقة `stockNeeds` تستدعي `availableQuantity()` لكل (صنف|وحدة) — 2 SUM لكل مفتاح.
**بعد:** قراءة مجمّعة واحدة عبر `availableQuantityBatch` (بدون مفتاح supplier = دلالة `availableQuantity` تماماً)، وفرع `exceptReturnId` (SUM للمرتجع المستثنى) بقي كما هو.

## 4.2 `batchesFromSorting()` (سطر ~357)
**قبل:** `foreach ($candidates) { $this->inventoryService->availableQuantityForSupplier(...) }` — استعلامان لكل دفعة مرشحة.
**بعد:** استدعاء مجمّع واحد لكل الدفعات، بنفس مفتاح التركيبة `item|warehouse|unit|supplier`.

## 4.3 تتبّع الوراثة — CTE بدل التعاود N×depth
**قبل (لكل دفعة مرشحة):** `traceLotToInvoice()` — استعلام ancestor لكل مستوى حتى عمق 5.
**بعد:**
1. في `batchesFromSorting`: تُجمع كل `lot_source_detail_id` وتُمرر لـ `lotsTracingToInvoice()` **بعد** فلترة الوحدات — تشذيب مضاعف (أقل صفوف تدخل التتبع).
2. دالة جديدة `lotsTracingToInvoice()`: CTE RECURSIVE واحد بحد `depth < 4` (**عمق مفحوص 0..4 = نفس عقد الفردي الخمسة بالضبط** — تحقق بالعدّ وليس تخميناً).
3. **لا فشل صامت:** عند عدم توفر CTE هبوط واضح للفردي مع `Log::warning` مفصّل (invoice_id، عدد الدفعات، رسالة الخطأ) — النتيجة مطابقة، الأداء فقط يتراجع.

**إثبات:** مقارنة CTE مقابل الفردي على 19 دفعة حقيقية → مطابقة تامة.

---

# 5) `DashboardService` — أسماء الأطراف
**قبل (`partyNameForAccount`, سطر 426):** لكل حساب فريد: 3 استعلامات (`Supplier::value`, `Customer::value`, `CharAccount::value`) — حتى 30 استعلاماً لكل بناء داشبورد.
**بعد:**
- دالة جديدة `partyNamesForAccounts(array $ids)`: **3 استعلامات whereIn ثابتة** مهما كان عدد الحسابات، مع **نفس أولوية الاسم حرفياً:** مورد → عميل → حساب.
- `partyNameForAccount()` حُوّلت إلى wrapper يفوّض للمجمّعة مع الحفاظ على كاش الطلب (`partyNameCache`) — التوافق محفوظ لكل مستدعياتها.

---

# 6) الـ Observers
| الملف | قبل | بعد |
|---|---|---|
| `SalesInvoiceObserver::deleted` | `with('details')->get()` + حلقة حذف ثنائي | استدعاء `deleteMovementsBySource(SOURCE_SALES_INVOICE, id)` |
| `PurchaseInvoiceObserver::deleted` | نفس النمط | استدعاء `deleteMovementsBySource(SOURCE_PURCHASE_INVOICE, id)` |

**السبب:** توحيد مع الخدمات + إزالة 2N حذف. القيد المحاسبي (شبكة الأمان) لم يُلمس في أي من الملفين.

---

# 7) حذف الحركات في الخدمات
| الملف | التغيير |
|---|---|
| `SalesInvoiceService::deleteInventoryMovement` | → `deleteMovementsBySource` |
| `SalesReturnService::deleteInventoryMovement` | → `deleteMovementsBySource` |
| `PurchaseReturnService::deleteInventoryMovement` | → `deleteMovementsBySource` |
| `PurchaseInvoiceService::deleteInventoryMovement` | → `deleteMovementsBySource` (كان بلا reverseMovement أصلاً — الدالة المشتركة تستدعي hook الـ no-op فلا فرق سلوكياً) |

---

# 8) `PurchaseDistributionController`
## 8.1 `list()` — إجماليات في SQL
**قبل:** `with(['details:...'])` يجلب **كل تفاصيل** 300 توزيع ثم يحسب (details_count, customers_count, total_cost, total_sale) في PHP.
**بعد:** إزالة eager details + استعلام `GROUP BY distribution_id` واحد:
```php
COUNT(*) details_count, COUNT(DISTINCT customer_id) customers_count,
COALESCE(SUM(quantity*unit_cost),0) total_cost, COALESCE(SUM(quantity*sale_price),0) total_sale
```
الأرقام الناتجة **متطابقة** مع الحساب PHP القديم (COUNT DISTINCT = pluck→unique→count، SUM = الحلقة).

## 8.2 `stats()` و`list()` — عدّادات
**قبل:** 3 استعلامات `COUNT(*)` منفصلة. **بعد:** استعلام واحد `COUNT + SUM(status='draft') + SUM(status='invoiced')`.

## 8.3 `save()` — transaction
**قبل:** `DB::beginTransaction()` + try/commit/catch/rollBack يدوي داخل Controller.
**بعد:** `DB::transaction(function () {...})` تُعيد `$distribution` — نفس الذرّية، rollback مضمون، أقل احتمال تسريب transaction عند استثناء متداخل.

---

# 9) الميجرشن (كُتب — لم يُشغَّل)
`database/migrations/2026_11_03_100000_add_lot_read_and_source_doc_indexes.php`
1. `imd_lot_read_idx (item_id, warehouse_id, unit_id, supplier_account_id)` على `inventory_movement_details` — لأن `imd_supplier_lot_idx` (item_id, **type_id**, ...) يكسر سلسلة الفهرس عند الاستعلامات التي لا تمرر type_id.
2. `idx_im_source_doc (source_type, document_number)` على `inventory_movements` — لمسار `generateSortingNumber`.
كلاهما **إضافة فقط** (down يلغيها نظيفاً) — صفر خطر.

---

# 10) جدول الأثر الإجمالي
| المسار | قبل | بعد |
|---|---|---|
| حفظ فاتورة بيع 20 سطراً — فحص المخزون | 40 SUM | 1 GROUP BY |
| قفل المخزون لفاتورة 15 سطراً | 15 lock | 1 lock |
| مرتجع شراء — فحص الصلاحية | 2 SUM × مفاتيح + تعاود×دفعات | 1 GROUP BY + 1 CTE |
| حذف فاتورة (الحركات) | 2N حذف | 2 حذف |
| بناء الداشبورد (10 حركات) | ~30 استعلام أسماء | 3 |
| شاشة التوزيعات `list()` | 300 رأس + كل تفاصيلها | 300 رأس + 1 GROUP BY |
| `stats()` | 3 COUNT | 1 |

# 11) المخاطر المتبقية / ملاحظات
1. **الميجرشن لم يُشغَّل** — القراءات المجمّعة تعمل صح بدون الفهرس (تستخدم `imd_item_wh_unit_idx` جزئياً)، الفهرس يضاعف السرعة بعد تشغيله.
2. اختبارات PHPUnit Feature لم تُشغَّل: قاعدة `qaat_system_test` بلا جداول (هجرتها تتطلب تشغيل migration — محظور بطلبك). التعويض: إثبات التكافؤ على البيانات الحقيقية أعلاه. بعد تشغيلك الميجرشن شغّل `php artisan test` للتأكيد النهائي.
3. `sortedBatchRatio` (مسار مرتجع الشراء عند الحفظ) ما زال يستخدم `traceLotToInvoice` الفردي — مقصود: يُستدعى بعدد وحدات محدود (≤50) مع memoization، وتعديله يخاطر بتغيير اختيار الدفعة.
4. ملفا التحقق في `storage/app/` أُبقيا كإثبات — يمكن حذفهما.
