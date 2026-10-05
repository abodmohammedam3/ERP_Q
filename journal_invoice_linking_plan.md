# 📋 خطة تنفيذ: ربط القيود المحاسبية بفواتير البيع والشراء والمرتجعات

**المشروع:** Qaat ERP — `c:\Laravel\qaatSystem`
**التاريخ:** 2026-10-04
**الحالة:** 📝 موثقة فقط — **لم يُنفَّذ أي بند منها بعد**
**الأساس:** `accounting_invoices_analysis.md` + تحقق مباشر من الكود الفعلي (مراجع موثقة أدناه)

---

## 0. أهم ثلاث ضوابط حمراء قبل كتابة أي سطر (Guard Rails)

> [!IMPORTANT]
> **1- `JournalEntryService::create()` تُعيد `null` وتبتلع الاستثناءات** (سطور 20-22 و38-49 في `JournalEntryService.php`).
> أي فشل صامت = القيد غير موجود والفاتورة محفوظة = **نفس المشكلة الأصلية في صورة جديدة**.
> **إلزامي:** بعد كل استدعاء، تحقق من `null` وارمِ استثناء داخل الـ transaction حتى يُلغى كل شيء.

> **2- `delete()` تُعيد `false` عند الفشل** (سطر 273) — تحقق من قيمتها في مسار الحذف وإلا يبقى قيد يتيم بعد حذف الفاتورة.

> **3- حدود الـ Transaction:** الاستدعاءات توضع **داخل** `DB::transaction` الحالي (وليست بعده). `create()` تكتشف transaction الأب تلقائيًا (سطر 29). الحدث الحاسم: فشل القيد يجب أن يُسقط الفاتورة، والعكس صحيح.

---

## 1. أسس موثقة من الكود (Verified — ليست ادعاءات)

| # | العنصر | الموقع الفعلي |
|---|--------|---------------|
| 1 | `JournalEntryService::create(array $options): ?int` — مع `validateLines` إلزامية (التوازن على `localDebit/localCredit` ±0.01) | `app/Services/JournalEntryService.php:16-96، 434-504` |
| 2 | `delete(int $entryID): bool` — idempotent (القيد غير الموجود → `true`) | `:244-275` |
| 3 | `generateNextEntryNumber()` يستخدم `lockForUpdate` | `:558-566` |
| 4 | إعادة حساب الأرصدة عبر `DB::afterCommit` → `AccountBalanceService::recalculateBatch` | `:612-656` |
| 5 | نموذج القالب الحي: `PaymentVoucherService` — `createJournalEntry` :1467، `buildEntryLines` :1494، `buildJournalDescription` :1556، الاستدعاءات عند 380/520/547/638/645 | `PaymentVoucherService.php` |
| 6 | **الفواتير والمرتجعات تستقبل `array` فقط (فصل HTTP مكتمل)** | `SalesInvoiceService:26,52,80` · `PurchaseInvoiceService:13,35,59` · `SalesReturnService:49,84,117` · `PurchaseReturnService:41,85,130` |
| 7 | `create/update/delete` كلها داخل `DB::transaction` بالفعل | نفس الملفات أعلاه |
| 8 | المبلغ الصافي: بيع = `items_total − discount_total` · شراء = `total_in_base_currency` (= صافي × سعر الصرف) + تكاليف إضافية تُوزَّع على المخزون | `SalesInvoiceService:334-349` · `PurchaseInvoiceService:274-295` |
| 9 | الحسابات النظامية في الـ seeder عبر `updateOrCreate` على `system_key` (**idempotent — يُعاد تشغيله بأمان**) | `ChartOfAccountsSeeder:572-586` |
| 10 | مفاتيح موجودة فعليًا: `sales_revenue=4101`، `inventory=1104`، `cost_of_goods_sold=5101`، `customers=1103`، `suppliers=2101`، `sales_returns=4201` — **ولا مفتاح `purchases`** | `ChartOfAccountsSeeder` |
| 11 | قالب `entryID` (camelCase + FK إلى `Journal_Entries` + index + `down()`) | `migrations/2026_09_30_232932_add_entry_id_to_payment_vouchers_table.php` |
| 12 | `payment_method`: `1=آجل, 2=نقد, 3=بنك, 4=شبكة` | `sales_invoice.js` labels |

---

## 2. قرارات تصميم مسبقة (Decision Log) — يجب حسمها قبل كتابة الكود

| # | القرار | التوصية | البديل |
|---|--------|---------|--------|
| **D1** | صيغة حقل الرابط | ✅ **`entryID`** (camelCase) مطابقة لقالب `payment_vouchers` | `entry_id` ❌ — انتهاك للنمط القائم (قاعدة AI_SKILLS #168) |
| **D2** | منهجية قيد الشراء | ✅ **دائم (Perpetual): مدين `inventory` مباشرة** — النظام ي maintain أرصدة `inventory_balances` بـ avg cost أصلاً، فلا حاجة لحساب `purchases` | دوري: يحتاج إضافة مفتاح `purchases` للSeeder |
| **D3** | قيد COGS | ✅ **إلزامي من اليوم الأول** (مدين `cost_of_goods_sold` / دائن `inventory` = Σ `quantity × cost_price`) — وإلا أرباح الدليل ≠ أرباح المخزون | اختياري ❌ — يعيد إنتاج مشكلة الربط |
| **D4** | سياسة الفواتير القديمة (Backfill) | ⏳ **قرار مطلوب من الفريق** — (أ) أمر Console يقيّد القديم دفعة واحدة، أو (ب) "القيود تبدأ من تاريخ التفعيل" موثّقة صراحة | تركها صامتة ❌ = تاريخ مالي مكسور |
| **D5** | حذف القيود | ✅ حذف مباشر (`delete()`) — متوافق مع نمط السندات الحالي | SoftDeletes للقيود مطلوب في توثيق `7_ACCOUNTING` بند 2 — **backlog منفصل** لا يعطّل هذه الخطة |
| **D6** | فهرس `unique(entryNo)` | ✅ بعد فحص التكرارات أولًا | بدون فحص ❌ — قد يفشل الـ migrate |

### ⏳ بنود تحتاج تأكيد زميلك المحاسبي قبل المرحلة 2:
1. هل ذمة المورد تشمل التكاليف الإضافية (نولون/ضريبة/نقل/أخرى)؟ (التوصية: نعم → دائن المورد = مدين المخزون بكلفة مُحتسبة)
2. تأكيد أن البيع يُقيَّد صافيًا بعد الخصم، وأن COGS إلزامي (D3).
3. حسم D4 (Backfill).

## 3. المرحلة التحضيرية (1-5) — تُنفَّذ قبل أي ربط

### T1 — ميغريشن `entryID` للجداول الأربعة

**ملف:** `database/migrations/2026_10_04_000001_add_entry_id_to_invoices_and_returns_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'sales_invoices'    => 'idx_si_entry',
        'purchase_invoices' => 'idx_pi_entry',
        'sales_returns'     => 'idx_sret_entry',
        'purchase_returns'  => 'idx_pret_entry',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $index) {
            Schema::table($table, function (Blueprint $tbl) use ($index) {
                $tbl->unsignedBigInteger('entryID')->nullable();

                $tbl->foreign('entryID')
                    ->references('entryID')
                    ->on('Journal_Entries')
                    ->onDelete('set null')
                    ->onUpdate('cascade');

                $tbl->index('entryID', $index);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $index) {
            Schema::table($table, function (Blueprint $tbl) use ($index) {
                $tbl->dropForeign(['entryID']);
                $tbl->dropIndex($index);
                $tbl->dropColumn('entryID');
            });
        }
    }
};
```

> [!WARNING]
> **فحص ما قبل التنفيذ:** بعض الجداول القديمة موجودة في `database/bac/` خارج مجلد `migrations/`.
> تأكد أن الجداول الأربعة موجودة فعلًا في قاعدة البيانات المستهدفة قبل تشغيل `php artisan migrate`، وإلا يفشل الميغريشن:
> ```sql
> SELECT TABLE_NAME FROM information_schema.tables
> WHERE TABLE_NAME IN ('sales_invoices','purchase_invoices','sales_returns','purchase_returns');
> ```

### T2 — حساب المشتريات في الـ Seeder (شرطي على D2)

- إذا اخترت **D2 = دائم** (التوصية): **لا شيء مطلوب** — القيد يذهب لـ `inventory` الموجود (1104).
- إذا اخترت **D2 = دوري**: أضف في `ChartOfAccountsSeeder.php` بعد بند `cost_of_services` (سطر 441):

```php
[
    'system_key' => 'purchases',
    'accCode'    => '5103',
    'accName'    => 'المشتريات',
    'accParentKey' => 'cost_of_sales',
    'accLevel'   => 3,
    'nature'     => 0,
],
```

الـ Seeder idempotent (`updateOrCreate` على `system_key`) — إعادة تشغيله آمنة:
```powershell
php artisan db:seed --class=ChartOfAccountsSeeder
```

### T3 — توسيع الـ Observers (شبكة أمان للقيد اليتيم)

**`app/Observers/SalesInvoiceObserver.php`** — يُضاف في `deleted()` بعد التنظيف المخزني:

```php
use App\Services\JournalEntryService;

public function deleted(SalesInvoice $invoice): void
{
    $this->inventoryService->deleteSourceMovements(
        InventoryMovement::SOURCE_SALES_INVOICE,
        $invoice->sales_invoice_id
    );

    // شبكة أمان: تنظيف القيد اليتيم إن وُجد
    // (الحذف الرئيسي يتم من الخدمة — هنا نغطي الحذف المباشر من النماذج)
    if ($invoice->entryID) {
        JournalEntryService::delete((int) $invoice->entryID);
    }
}
```

نفس السطرين في **`PurchaseInvoiceObserver`** (`SOURCE_PURCHASE_INVOICE` + `purchase_invoice_id`).
آمن من المزدوجة: `delete()` idempotent تعيد `true` لو كان القيد محذوفًا أصلًا (سطر 294).

### T4 — فهرس `unique` على `entryNo` (قرار D6)

**فحص أولًا** (قبل أي migration) — إن أعاد صفوفًا، لا تُنشئ الفهرس حتى تنظيف التكرارات:
```sql
SELECT entryNo, COUNT(*) c FROM Journal_Entries GROUP BY entryNo HAVING c > 1;
```
عند النجاح، ميغريشن منفصل:
```php
Schema::table('Journal_Entries', function (Blueprint $table) {
    $table->unique('entryNo', 'uq_je_entry_no');
});
```

### T5 — اختبار Feature أساسي (قبل الربط: يتوقع فشله الآن = تأكيد الفجوة)

**ملف:** `tests/Feature/InvoiceJournalEntryTest.php` — يُكتب بأسلوب `ReturnsSystemTest` (`DatabaseTransactions` + `withoutMiddleware` + بيانات تجريبية في `setUp`).

سيناريوهات الاختبار (تُكتب الآن، **وتُترك `.markTestSkipped` أو تفشل**) حتى تنفيذ المرحلة 1:

| # | السيناريو | التحقق |
|---|-----------|--------|
| 1 | بيع آجل (`payment_method=1`) | يوجد قيد `docNumber=SI-{id}` · مدين العميل = الصافي · دائن `sales_revenue` · مجموع `localDebit` = `localCredit` · `invoice.entryID` غير فارغ |
| 2 | بيع نقدي (`payment_method=2`) | مدين `payment_account_id` بدل العميل |
| 3 | COGS | سطران إضافيان: مدين `cost_of_goods_sold` / دائن `inventory` بمجموع `qty × cost_price` |
| 4 | تعديل الفاتورة | قيد واحد فقط لهذا `docNumber` (لا تكرار) بمبالغ محدّثة |
| 5 | حذف الفاتورة | القيد محذوف أيضًا |
| 6 | رصيد العميل | ⚠️ انظر ملاحظة afterCommit أدناه |

> [!CAUTION]
> **ملاحظة اختبار حاسمة:** اختبارات `DatabaseTransactions` لا تعمل `commit` أبدًا، و`scheduleBalanceRecalculation` تعتمد على `DB::afterCommit` (سطر 639) — لذلك **لن يتحدث `account_balances` تلقائيًا داخل الاختبار**.
> الحل: في اختبار الرصيد، استدعِ `AccountBalanceService::recalculateBatch([$accountId])` يدويًا ثم اقرأ `account_balances`، أو اكتفِ في بقية الاختبارات بالتحقق من أسطر القيد.

**حسابات النظامية في الاختبار:** لأن الـ seeder قد لا يعمل على قاعدة الاختبار، أدرج صفوف `characcount` يدوية في `createTestData()` بنفس نمط الموجود:
```php
$this->salesRevenueId = DB::table('characcount')->insertGetId([
    'accName' => 'المبيعات', 'accCode' => '4101', 'system_key' => 'sales_revenue',
]);
// + cost_of_goods_sold (5101) + inventory (1104)
```

## 4. المرحلة 1 — ربط فاتورة البيع (أول قيد حي)

### 4.1 خريطة القيود (4 حالات + COGS)

| الحالة | المدين | الدائن | المبلغ (بالعملة الأساسية) |
|--------|--------|--------|---------------------------|
| آجل (`payment_method=1`) | حساب العميل (`account_id`) | حساب المبيعات (`sales_revenue`) | `net = items_total − discount_total` |
| نقد/بنك/شبكة (`2,3,4`) | حساب الدفع (`payment_account_id`) | حساب المبيعات (`sales_revenue`) | `net × exchange_rate` |
| COGS (دائماً إن > 0) | تكلفة البضاعة المباعة (`cost_of_goods_sold`) | المخزون (`inventory`) | `Σ quantity × cost_price` |

- `docType = 'فاتورة بيع'` · `docNumber = 'SI-{sales_invoice_id}'` · `entryDate = invoice_date`
- إن كان `net = 0` وCOGS = 0 (فاتورة مجانية بلا أثر مالي) → **تخطي القيد** (وليست رسالة خطأ).
- التحقق المسبق: `payment_method=1` يتطلب `account_id`، وغيرها تتطلب `payment_account_id` — وإلا `throw` قبل إنشاء القيد.

### 4.2 نقاط الإدراج في `SalesInvoiceService.php`

```php
// create() — بعد syncInventoryMovement (السطر 46) وقبل return:
$this->syncInventoryMovement($invoice);
$this->applyJournalEntry($invoice);        // ← جديد
return $invoice;

// update() — بعد syncInventoryMovement (السطر 74):
$this->syncInventoryMovement($invoice);
$this->applyJournalEntry($invoice);        // ← جديد (يحذف القيد القديم ويعيد بناءه)
return $invoice;

// delete() — قبل $invoice->delete() (السطر 89):
$this->removeJournalEntry($invoice);       // ← جديد (الـ Observer شبكة أمان ثانية)
$invoice->details()->delete();
$invoice->delete();
```

### 4.3 الدوال الجديدة — الجزء 1 (إنشاء القيد)

```php
use App\Models\CharAccount;
use App\Services\JournalEntryService;

/**
 * إنشاء/تحديث قيد الفاتورة — يُستدعى داخل DB::transaction فقط.
 */
protected function applyJournalEntry(SalesInvoice $invoice): void
{
    // 1) حذف القيد السابق عند التحديث
    if ($invoice->entryID) {
        JournalEntryService::delete((int) $invoice->entryID);
        $invoice->entryID = null;
    }

    $net = max(0, (float) $invoice->items_total - (float) $invoice->discount_total);
    $lines = $this->buildJournalLines($invoice, $net);

    if (empty($lines)) {
        return; // فاتورة بلا أثر مالي — لا قيد
    }

    // 2) إنشاء القيد — والتحقق الإلزامي من null (Guard #1)
    $entryID = JournalEntryService::create([
        'docType'     => 'فاتورة بيع',
        'docNumber'   => 'SI-' . $invoice->sales_invoice_id,
        'entryDate'   => $invoice->invoice_date,
        'description' => $this->buildJournalDescription($invoice),
        'lines'       => $lines,
    ]);

    if (!$entryID) {
        throw new \RuntimeException(
            'فشل إنشاء القيد المحاسبي لفاتورة البيع رقم ' . $invoice->invoice_number
        ); // يُلغي transaction بالكامل — الفاتورة لا تُحفظ بلا قيد
    }

    // 3) حفظ الرابط
    $invoice->entryID = $entryID;
    $invoice->save();
}
```

### 4.4 الدوال الجديدة — الجزء 2 (بناء الأسطر والحذف)

```php
protected function buildJournalLines(SalesInvoice $invoice, float $net): array
{
    $rate   = (float) ($invoice->exchange_rate ?: 1);
    $method = (int) $invoice->payment_method;
    $desc   = 'فاتورة بيع رقم ' . $invoice->invoice_number;

    $salesRevenueId = (int) CharAccount::where('system_key', 'sales_revenue')->value('accountID');

    if ($salesRevenueId <= 0) {
        throw new \RuntimeException('حساب النظام sales_revenue غير موجود في الدليل');
    }

    $lines    = [];
    $localNet = round($net * $rate, 2);

    if ($localNet > 0) {
        if ($method === 1) { // آجل: مدين العميل
            if (!$invoice->account_id) {
                throw new \RuntimeException('فاتورة بيع آجلة بدون account_id');
            }
            $lines[] = $this->journalLine((int) $invoice->account_id, $invoice->coin_id, $rate,
                                          $net, $localNet, 0, 0, $desc);
        } else {             // نقدي/بنك/شبكة: مدين حساب الدفع
            if (!$invoice->payment_account_id) {
                throw new \RuntimeException('فاتورة بيع نقدية بدون payment_account_id');
            }
            $lines[] = $this->journalLine((int) $invoice->payment_account_id, $invoice->coin_id, $rate,
                                          $net, $localNet, 0, 0, $desc);
        }

        // دائن المبيعات
        $lines[] = $this->journalLine($salesRevenueId, $invoice->coin_id, $rate,
                                      0, 0, $net, $localNet, $desc);
    }

    // COGS (إلزامي — D3)
    $cogs = (float) $invoice->details->sum(
        fn ($d) => (float) $d->quantity * (float) $d->cost_price
    );

    if ($cogs > 0) {
        $cogsLocal = round($cogs * $rate, 2);
        $cogsAcc = (int) CharAccount::where('system_key', 'cost_of_goods_sold')->value('accountID');
        $invAcc  = (int) CharAccount::where('system_key', 'inventory')->value('accountID');

        if ($cogsAcc <= 0 || $invAcc <= 0) {
            throw new \RuntimeException('حسابا cost_of_goods_sold/inventory غير موجودين');
        }

        $lines[] = $this->journalLine($cogsAcc, $invoice->coin_id, $rate,
                                      $cogs, $cogsLocal, 0, 0, 'تكلفة المباع - ' . $desc);
        $lines[] = $this->journalLine($invAcc, $invoice->coin_id, $rate,
                                      0, 0, $cogs, $cogsLocal, 'صرف مخزون - ' . $desc);
    }

    return $lines;
}

/** سطر قيد موحد بنفس مفاتيح PaymentVoucherService::buildEntryLines */
protected function journalLine(int $accountId, $coinId, float $rate,
                               float $debit, float $localDebit,
                               float $credit, float $localCredit,
                               string $desc): array
{
    return [
        'accountID'    => $accountId,
        'coinsID'      => (int) $coinId,
        'description2' => $desc,
        'exchangRate'  => $rate,
        'debit'        => $debit,
        'credit'       => $credit,
        'localDebit'   => $localDebit,
        'localCredit'  => $localCredit,
    ];
}

protected function buildJournalDescription(SalesInvoice $invoice): string
{
    return 'فاتورة بيع رقم ' . $invoice->invoice_number
         . ($invoice->account ? ' - ' . $invoice->account->accName : '');
}

/** حذف القيد عند حذف الفاتورة — مع Guard #2 */
protected function removeJournalEntry(SalesInvoice $invoice): void
{
    if (!$invoice->entryID) {
        return;
    }

    $ok = JournalEntryService::delete((int) $invoice->entryID);

    if (!$ok) {
        throw new \RuntimeException(
            'فشل حذف القيد المرتبط بفاتورة البيع رقم ' . $invoice->invoice_number
        ); // يُلغي حذف الفاتورة — لا قيد يتيم أبدًا
    }
}
```

**لماذا هذا النمط آمن:**
- فشل القيد → `throw` → rollback للفاتورة والمخزون معًا (Atomicity كاملة).
- التحديث = حذف + إعادة بناء داخل نفس transaction (أبسط وأضمن من `updateEntry` عند تغير عدد الأسطر).
- الحذف في الخدمة + شبكة أمان في الـ Observer (تغطي الحذف المباشر من أي مسار آخر).

## 5. المرحلة 2 — ربط فاتورة الشراء

نفس النمط تمامًا في `PurchaseInvoiceService.php` (نقاط الإدراج: بعد `syncInventoryMovement` في `create` :29 و`update` :53، وقبل `$invoice->delete()` في `delete` :65).

### 5.1 خريطة القيود (منهج دائم D2)

| الحالة | المدين | الدائن |
|--------|--------|--------|
| آجل (`payment_method=1`) | المخزون `inventory` (1104) | حساب المورد (`account_id`) |
| نقد/بنك/شبكة (`2,3,4`) | المخزون `inventory` | حساب الدفع (`payment_account_id`) |

- **المبلغ المدين** = `total_in_base_currency + (expenses + tax_cost + transportation + other_cost) × rate`
  (تكاليف إضافية تُجمَّع في المخزون — متسقة مع توزيعها في `syncInventoryMovement` :139-142)
- **المبلغ الدائن** = نفس القيمة ⚠️ **(شريطة تأكيد بند المحاسب رقم 1 في القسم 2)**
- `docType = 'فاتورة شراء'` · `docNumber = 'PI-{purchase_invoice_id}'`

```php
protected function buildJournalLines(PurchaseInvoice $invoice): array
{
    $rate = (float) ($invoice->exchange_rate ?: 1);

    $extras = (float) $invoice->expenses + (float) $invoice->tax_cost
            + (float) $invoice->transportation + (float) $invoice->other_cost;

    $costLocal = round(((float) $invoice->total_in_base_currency) + ($extras * $rate), 2);

    $invAcc = (int) CharAccount::where('system_key', 'inventory')->value('accountID');
    if ($invAcc <= 0) {
        throw new \RuntimeException('حساب النظام inventory غير موجود في الدليل');
    }

    $lines   = [];
    $desc    = 'فاتورة شراء رقم ' . $invoice->invoice_number;
    $method  = (int) $invoice->payment_method;

    if ($costLocal <= 0) {
        return $lines; // بلا أثر مالي — لا قيد
    }

    // مدين المخزون
    $lines[] = $this->journalLine($invAcc, $invoice->coin_id, $rate,
                                  $costLocal / $rate, $costLocal, 0, 0, $desc);

    // الدائن: المورد (آجل) أو حساب الدفع (نقدي)
    if ($method === 1) {
        if (!$invoice->account_id) {
            throw new \RuntimeException('فاتورة شراء آجلة بدون account_id');
        }
        $lines[] = $this->journalLine((int) $invoice->account_id, $invoice->coin_id, $rate,
                                      0, 0, $costLocal / $rate, $costLocal, $desc);
    } else {
        if (!$invoice->payment_account_id) {
            throw new \RuntimeException('فاتورة شراء نقدية بدون payment_account_id');
        }
        $lines[] = $this->journalLine((int) $invoice->payment_account_id, $invoice->coin_id, $rate,
                                      0, 0, $costLocal / $rate, $costLocal, $desc);
    }

    return $lines;
}
```

> **ملاحظة:** عكس `applyJournalEntry/removeJournalEntry` في الخدمة نفسها (بنفس منطق البيع، مع `docType='فاتورة شراء'` و`PI-`).

---

## 6. المرحلة 3 — المرتجعات (عكس الاتجاه)

> [!IMPORTANT]
> قاعدة توثيق `re_sal_pur.md`: **لا تُقيَّد المرتجع وحده أبدًا** — المرحلة 3 تأتي *بعد* اكتمال 1 و2 لأن المرتجع يفترض وجود قيد فاتورة أصل.
> (سيُضاف `entryID` للجداول في T1 مسبقًا — لن يحتاج ميغريشن جديد.)

| المستند | القيد | `docNumber` |
|---------|-------|-------------|
| مرتجع بيع | مدين `sales_returns` (4201) / دائن العميل (`account_id`) + عكس COGS: مدين `inventory` / دائن `cost_of_goods_sold` | `SR-{sales_return_id}` |
| مرتجع شراء | مدين المورد (`account_id`) / دائن `inventory` | `PR-{purchase_return_id}` |

**نقاط الإدراج:**
- `SalesReturnService`: `create` (:49) و`update` (:84) بعد الحفظ · `delete` (:117) قبل الحذف
- `PurchaseReturnService`: `create` (:41) و`update` (:85) · `delete` (:130)
- Observers: `SalesReturnObserver` + `PurchaseReturnObserver` (شبكة أمان — نفس سطرين T3)
- شرط: إن كان للمرتجع حقل `return_type` (كلي/جزئي) فالمبلغ = قيمة المرتجع الفعلية لا الفاتورة كاملة — **تحقق من بنية الجدول عند التنفيذ**.

## 7. الـ Backfill (قرار D4) — أمر Console

**ملف:** `app/Console/Commands/BackfillInvoiceEntries.php` (يُكتب بعد حسم D4)

```php
protected $signature = 'invoices:backfill-entries {--dry-run : عرض بدون قيّد}';
// يمر على: sales_invoices + purchase_invoices حيث entryID IS NULL
// يستدعي نفس applyJournalEntry() عبر الخدمة (لا منطق مزدوج)
// يطبع: عدد المقيَّد/المتخطي/الفاشل + يوقف عند أول خطأ إن لم يكن --dry-run
```

- **خيار (أ):** تشغيله مرة واحدة عند الإطلاق → تاريخ مالي كامل.
- **خيار (ب):** عدم تشغيله + توثيق صريح في `requirements_final.md`: "القيود المحاسبية تبدأ من تاريخ التفعيل؛ الفواتير الأسبق تُعدَّل يدويًا/تقرير مطابقة".
- ❌ الممنوع: الاختيار الصامت (لا أ ولا ب).

---

## 8. ترتيب التنفيذ وأوامر التحقق

### التسلسل (لا تُقَّم مراحل متداخلة):

```
[تحضير] T1 migration → T2 seeder (لو D2 دوري) → T3 observers → T4 unique index → T5 اختبار (يفشل)
   ↓
[حسم] D2 / D4 / بنود المحاسب الثلاثة (القاسمة 2)
   ↓
[المرحلة 1] فاتورة البيع → php artisan test --filter=InvoiceJournalEntryTest → مراجعة
   ↓
[المرحلة 2] فاتورة الشراء → نفس الاختبارات + سيناريوهات شراء
   ↓
[المرحلة 3] المرتجعات → ReturnsSystemTest موسعة
   ↓
[Backfill] حسب قرار D4 → تقرير مطابقة
```

### الأوامر:

```powershell
# 1) فحوص ما قبل التنفيذ (SQL الثلاثة في T1/T4)
# 2) ترحيل
php artisan migrate

# 3) الحسابات النظامية (idempotent)
php artisan db:seed --class=ChartOfAccountsSeeder

# 4) الاختبارات
php artisan test --filter=InvoiceJournalEntryTest
php artisan test   # كامل الحزمة — لا تكسر الموجود

# 5) فحص يدوي بعد أول فاتورة تجريبية بيئة اختبار
php artisan tinker --execute="dump(DB::table('Journal_Entries')->where('docType','فاتورة بيع')->latest('entryID')->first());"
```

---

## 9. معايير القبول (Acceptance Criteria)

- [ ] **A1** فاتورة بيع آجلة → قيد موجود (`SI-{id}`) ومتوازن (`ΣlocalDebit = ΣlocalCredit ±0.01`) و`entryID` محفوظ.
- [ ] **A2** فاتورة بيع نقدية → مدين `payment_account_id` وليس العميل.
- [ ] **A3** COGS قيَّد مع كل بيع (>0) ومجموعه = `Σ qty × cost_price`.
- [ ] **A4** تعديل الفاتورة → قيد واحد فقط بعنوان `SI-{id}` بالمبلغ الجديد (لا تراكم).
- [ ] **A5** حذف الفاتورة (من الخدمة أو من النموذج مباشرة) → لا قيد يتيم.
- [ ] **A6** فشل القيد (حساب نظامي ناقص) → لا تُحفظ الفاتورة إطلاقًا (rollback مُختبر).
- [ ] **A7** `account_balances` لحساب العميل = صافي فواتيره الآجلة (بعد `recalculateBatch`).
- [ ] **A8** شراء آجل → مدين `inventory` / دائن المورد بالصافي + التكاليف.
- [ ] **A9** المرتجعات معكوسة تمامًا ولا تُقيَّد قبل فواتيرها.
- [ ] **A10** `php artisan test` كامل = أخضر (لا انحدار في اختبارات المخزون/المرتجعات الموجودة).
- [ ] **A11** قرار D4 مُتخذ ومُوثّق (أ أو ب).

---

## 10. سجل المخاطر المتبقي

| # | المخاطرة | الحد من |
|---|----------|--------|
| R1 | جداول `bac/` قد لا تكون مُرحّلة في بيئة نظيفة | فحص `information_schema` قبل migrate (T1) |
| R2 | فشل صامت في `create()`=`null` / `delete()`=`false` | Guard #1 و #2 (إلزاميان في كل استدعاء) |
| R3 | `afterCommit` لا يعمل داخل `DatabaseTransactions` → اختبار رصيد مضلِّل | استدعاء يدوي لـ `recalculateBatch` في الاختبار (T5) |
| R4 | قيود تاريخية غير مقيَّدة (D4 مُهمل) | قسم 7 — حسم قبل الإطلاق |
| R5 | التكاليف الإضافية في الشراء قد لا تكون من ذمة المورد | بند تأكيد المحاسب #1 |
| R6 | تكرار `entryNo` يفشل الفهرس الفريد | فحص SQL قبل T4 |
| R7 | تعارض مع أي عمل جارٍ على `SalesInvoiceService`/`PurchaseInvoiceService` | نقاط الإدراج سطور محددة صغيرة (4.2) قابلة للدمج يدويًا |
| R8 | القيود كـ Hard Delete (مخالف لتوصية `7_ACCOUNTING` بند 2) | D5 كـ backlog منفصل — لا يعطّل الخطة |

---

## 11. خانات الاعتماد

| البند | المسؤول | الحالة |
|-------|---------|--------|
| D1 صيغة `entryID` | تقني | ✅ محسوم |
| D2 منهج الشراء (دائم/دوري) | محاسب الفريق | ⏳ مطلوب |
| D3 COGS إلزامي | محاسب الفريق | ⏳ تأكيد |
| D4 سياسة الفواتير القديمة | الفريق | ⏳ مطلوب |
| B1 ذمة المورد والتكاليف الإضافية | محاسب الفريق | ⏳ مطلوب |
| B2 قيد البيع صافي بعد الخصم | محاسب الفريق | ⏳ تأكيد |
| T1–T5 تحضيري | تقني | ⏳ لم يبدأ |
| المرحلة 1: البيع | تقني | ⏳ |
| المرحلة 2: الشراء | تقني | ⏳ |
| المرحلة 3: المرتجعات | تقني | ⏳ |

---

**مرجع داخل هذا الملف:** أقسام 0 و1 (الحقائق) · 2 (القرارات) · 3 (تحضير) · 4-6 (تنفيذ) · 7-9 (إطلاق وتحقق) · 10-11 (مخاطر واعتماد).
**المصدر الأساسي:** `accounting_invoices_analysis.md` — مُتحقَّق منه بنسبة ~95% مقابل الكود (وقسمي 1 هنا).





