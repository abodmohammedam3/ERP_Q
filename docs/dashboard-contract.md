# عقد بيانات لوحة المعلومات (Dashboard Contract)

**الملف:** `docs/dashboard-contract.md`
**نقطة النهاية:** `GET /dashboard/data` → `routes\web.php` (`dashboard.data`)
**المتحكم:** `app\Http\Controllers\Dashboard\DashboardController.php`
**الخدمة:** `app\Services\Dashboard\DashboardService.php`
**الاختبار:** `tests\Feature\Dashboard\DashboardDataTest.php`

> أي تغيير في أسماء الحقول أو أنواعها هنا يجب أن يُطبَّق في:
> `DashboardService`, `resources/js/pages/dashboard.js`, وهذا الملف, والاختبار.
> الغرض: منع التخمين في الواجهة (نفس مبدأ `tests\Feature\Reports\ReportContractTest.php`).

---

## 1) الشكل العام للرد

```json
{
  "success": true,
  "data": {
    "indicators":   { ... },
    "summary":      { ... },
    "supply":       [ ... ],
    "distribution": [ ... ],
    "inventory":    { ... },
    "alerts":       [ ... ],
    "version":      12,
    "generated_at": "2026-10-25T10:00:00+03:00"
  }
}
```

**عند الفشل:**

```json
{ "success": false, "message": "تعذّر تحميل بيانات لوحة المعلومات" }
```
رمز الحالة: `500`. الواجهة تُظهر `#dashboardErrorBanner` ولا تمسح ما هو معروض.

---

## 2) `indicators` — object

| الحقل | النوع | المصدر | ملاحظة |
|---|---|
| `sales_invoices` | int | `COUNT(*)` على `sales_invoices` | كل الفواتير (مرحّلة وغير مرحّلة) |
| `purchase_invoices` | int | `COUNT(*)` على `purchase_invoices` | كل الفواتير |
| `customers` | int | `COUNT(*)` على `customers` حيث `is_active = 1` | العمود أُضيف عبر `2026_09_27_193337` |
| `suppliers` | int | `COUNT(*)` على `Suppliers` حيث `is_active = 1` | `supStoped` المحذوف استُبدل بـ `is_active` عبر `2026_09_27_222027` |

---

## 3) `summary` — object

| الحقل | النوع | المعادلة |
|---|---|---|
| `sales_total` | float (منزلتان) | `SUM(COALESCE(total_in_base_currency, (items_total - discount_total) * COALESCE(exchange_rate,1)))` |
| `purchase_total` | float (منزلتان) | نفس الصيغة على `purchase_invoices` |
| `stock_movements` | int | `COUNT(*)` على `inventory_movements` |

**لماذا `COALESCE` مزدوج؟**
`sales_invoices.total_in_base_currency` أُضيف بـ `->nullable()` في `2026_10_12_155543`،
وبالتالي الفواتير الأقدم قد تحمل `NULL`. و`SUM(NULL)` يتجاهل الصف بصمت ⇒ رقم أقل من الحقيقة.
`COALESCE(SUM(...), 0)` وحدها **لا تكفي**, لأنها تحمي المجموع النهائي لا صفوف المدخلات.

**لماذا لا `total_invoice_currency`؟**
لأنه accessor بلا عمود في قاعدة البيانات (`SalesInvoice.php:110-113`) وغير قابل للجمع في SQL.
وجمعه بلا تحويل عملة يخلط عملات مختلفة في رقم واحد.

---

## 4) `supply[]` و `distribution[]` — array of rows

كل صف يمثل **سطر حركة** (لا رأس حركة), لأن كل أعمدة الجدول
(الصنف/النوع/الرمز/الوحدة/الكمية/السعر) موجودة في `inventory_movement_details`
وليس في `inventory_movements` (انظر `create_inventory_movement_details_table.php`).

| الحقل | النوع | المصدر |
|---|---|---|
| `movement_id` | int | `inventory_movements.movement_id` |
| `display_id` | string | `inventory_movements.display_id` |
| `movement_type` | string | `inventory_movements.movement_type` (ثوابت `InventoryMovement::TYPE_*`) |
| `type_label` | string | `InventoryMovement::labelForType()` (عربي جاهز) |
| `direction` | string | `in` أو `out` |
| `date` | string `Y-m-d` | `inventory_movements.movement_date` |
| `day_name` | string | اسم اليوم بالعربية (من نفس التاريخ) |
| `statement` | string | `inventory_movements.statement` |
| `supplier_name` | string | اسم الطرف من مصدر الحركة (انظر `party_name`) — يبقى للتوافق |
| `party_name` | string | الاسم الموحّد للطرف حسب `source_type`: `purchase_invoice`/`purchase_returns` → `Suppliers.supName`، `sales_invoice`/`sales_returns` → `customers.CustomersName2`، ثم `characcount.accName` كبديل. `''` للحركات اليدوية (`supply`/`issue`) |
| `item_name` | string | `Items.itemName2` |
| `type_name` | string | `type.name` |
| `unit_name` | string | `units.UnitName` |
| `code` | string | `inventory_movement_details.code` (الرمز التاريخي) |
| `quantity` | float | `inventory_movement_details.quantity` |
| `unit_cost` | float | `inventory_movement_details.unit_cost` |
| `sale_price` | float | `inventory_movement_details.sale_price` |
| `line_total` | float | `inventory_movement_details.total` |

### تصنيف الأنواع

| الحزمة | الأنواع (`InventoryMovement::TYPE_*`) |
|---|---|
| `supply` | `supply` (توريد مخزني), `purchase` (توريد شراء), `sale_return` (مرتجع بيع يدخل) |
| `distribution` | `issue` (صرف مخزني), `sale` (صرف بيع), `purchase_return` (مرتجع شراء يخرج) |

> `sorting_in` / `sorting_out` **غير مشمولين** عن قصد: هما عملية داخلية لتحويل الوحدات
> لا توريد ولا توزيع, وإدراجهما يضخّم الأرقام ويضلّل.
> الحد الأقصى لكل مصفوفة: `DashboardService::RECENT_LIMIT` = 10 صفوف.

---

## 5) `inventory` — object (للجزء المعلّق `Inventory.blade.php`)

| الحقل | النوع | المصدر |
|---|---|---|
| `stocks` | int | `COUNT(*)` على `stocks` |
| `items` | int | `COUNT(*)` على `Items` حيث `is_active = 1` |
| `types` | int | `COUNT(*)` على `type` |
| `units` | int | `COUNT(*)` على `units` |
| `recent[]` | array | آخر 10 حركات |

`recent[]` حقول: `movement_id`, `display_id`, `movement_type`, `type_label`,
`date`, `statement`, `item_name`, `quantity`, `unit_cost`.

> بعض هذه العدّادات بلا `is_active` لأن الجدول نفسه لا يمتلكه — العدّ كامل عن قصد
> (`stocks`, `type`, `units`).

---

## 6) `alerts[]` — array

| الحقل | النوع | المعنى |
|---|---|---|
| `level` | string | `warning` أو `info` |
| `key` | string | معرّف ثابت (`draft_sales`, `draft_purchases`, `blank_statement`) |
| `text` | string | نص عربي جاهز للعرض |
| `count` | int | العدد |

القواعد (لا جداول جديدة):
- `draft_sales` — فواتير بيع `is_posted = 0`.
- `draft_purchases` — فواتير شراء `is_posted = 0`.
- `blank_statement` — حركات مخزون `statement` فارغ أو `NULL`.

---

## 7) `version` — int

رقم إصدار كاش اللوحة (`App\Support\DashboardCache::version()`).

- يزيد عند **أي** `created` / `updated` / `deleted` على:
  `SalesInvoice`, `PurchaseInvoice`, `SalesReturn`, `PurchaseReturn`,
  `InventoryMovement`, `InventoryMovementDetail`, `JournalEntry`, `Customer`, `Supplier`
  (`AppServiceProvider::boot()`).
- يُستخدم كجزء من مفتاح الكاش: `dashboard.v{version}.{part}`.
- الواجهة لا تعتمد عليه للمقارنة — هو للتشخيص فقط.

---

## 8) دورة التحديث

```
[فتح الصفحة]
   GET /dashboard            → Blade + SSR (indicators + summary + version)
   GET /dashboard/data       → كل الأجزاء (JS)
        ↓
[أي كتابة: حفظ/حذف فاتورة أو حركة]
   Eloquent Event → FlushDashboardCache → DashboardCache::bump()  (version++)
        ↓
[العودة للتبويب / focus]  → GET /dashboard/data → مفتاح جديد ⇒ بيانات حديثة
```

**لا polling.** التحديث الدوري غير موجود عن قصد (`CACHE_STORE=file`،
وكل طلب يفتح ملف جلسة + كاش).

---

## 9) أداء

| ما | لماذا |
|---|---|
| استجابة واحدة `/dashboard/data` | الجلسة `file` و`web` middleware تُشغَّل لكل طلب — طلب واحد أرخص |
| `DashboardCache::TTL = 30` | يخدم إعادة التحميل المتتالية فقط؛ ليس آلية تحديث |
| `limit(10)` على الحركات | يمنع `ORDER BY` على الجدول كاملًا |
| `with('item:itemID,itemName2', 'type:id,name', 'unit:UnitID,UnitName')` | تحديد الأعمدة بعدد الحقول المستخدمة فعلًا فقط |
| `Cache::flush()` في `ImportBackupJob:117` | يمسح كاش اللوحة تلقائيًا بعد استيراد نسخة احتياطية |
