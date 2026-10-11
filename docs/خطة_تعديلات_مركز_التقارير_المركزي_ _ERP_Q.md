# خطة تعديلات مركز التقارير المركزي — ERP\_Q

مبنية على فحص كود الملف `ERP_Q-main (2).zip` (المحرك، السجل، الـ Controller، التقارير السبعة، الواجهة، الطباعة، الاختبارات) بتاريخ 2026-10-09.

**الرموز:** 🔴 خلل مؤكد من الكود · 🟠 خطر أو نقص · 🟡 تحسين · **(؟)** يحتاج تحققاً بتشغيل النظام لأني قرأت الكود ولم أشغّله.

## 1) الملخص التنفيذي

| المحور | أهم ما وجدته |
| --- | --- |
| الكفاءة | الطباعة والتصدير يخرجان الصفحة الأولى فقط، وفلاتر بلا مصادر بيانات، وإجماليات تخلط العملات |
| السرعة | `whereDate` يعطّل الفهارس، و`COUNT` على كل طلب، وتحميل الحسابات والأصناف كلها في القوائم |
| كثرة البيانات | ترقيم بـ offset، وسندات تُجلب كلها للذاكرة، وكشف حساب يرفض عند 20 ألف صف بدل أن يقسّم |
| سهولة الاستخدام | لا Excel/PDF، ولا حفظ للفلاتر، ولا روابط قابلة للمشاركة، ولا تفاصيل عند النقر على الصف |
| استكشاف الأخطاء | لا تسجيل لزمن التقرير، ولا استثناءات واضحة، وتحقق ضعيف من التواريخ |
| مراجعة الكود | تكرار كبير بين التقارير، ومجاميع بـ float، وكل شيء static |

ترتيب التنفيذ المقترح في آخر المستند.

---

## 2) أخطاء وتعديلات مؤكدة (الأولوية القصوى)

### 🔴 B1 — الطباعة والتصدير يعطيان الصفحة الأولى فقط

**الدليل:** الدالة `rcCollectFilters()` لا ترسل `page`، و`ReportEngine` يمرر `page` فقط إن وُجدت، وكل تقرير مقسّم يقص النتيجة عند `PER_PAGE = 50`. فملف CSV يحتوي 50 صفاً كحد أقصى، وصفحة الطباعة تكتب «عدد السجلات: 50». والإجماليات صحيحة لأنها تُحسب على كل النتائج، فيبدو التقرير سليماً وهو ناقص. هذا يؤثر على: دفتر الأستاذ، حركة الصنف، فواتير البيع والشراء، السندات.

**الإصلاح:**

```php
// ReportEngine
public static function execute(string $key, array $raw, bool $all = false): array
{
    $report  = ReportRegistry::get($key);
    $filters = self::sanitizeFilters($report, $raw);

    if ($all) {                 // داخلي فقط: لا يمكن تمريره من الطلب
        unset($filters['page']);
        $filters['_all'] = true;
    }
    // ... بقية الدالة كما هي
}

// داخل كل تقرير مقسّم (مثال المبيعات)
$perPage = !empty($filters['_all'])
    ? min($total, (int) config('reports.export_max_rows', 50000))
    : self::PER_PAGE;
// استبدل self::PER_PAGE بـ $perPage في حساب lastPage وفي take()

// ReportCenterController: print() و export()
$result = ReportEngine::execute($key, $request->query->all(), all: true);
```

وأضف في `config/reports.php`: `'export_max_rows' => 50000`. وإذا تجاوز العدد الحد، أرجع رسالة «النتيجة كبيرة، ضيّق الفلاتر أو استخدم التصدير في الخلفية» (انظر L5). وغيّر سطر الطباعة ليعرض `meta.pagination.total` بدل `count($rows)`.

### 🔴 B2 — فلاتر بلا مصادر بيانات

تقريرا المبيعات والمشتريات يطلبان المصدر `paymentMethods`، وتقرير المبيعات يطلب `customers`، لكن `sources()` يرجع فقط: accounts, items, coins, docTypes, voucherTypes. والنتيجة قائمة طريقة الدفع فارغة، وفلتر العميل لا يعمل **(؟)**. وتقرير المشتريات يحتاج التحقق من مصدر المورد أيضاً. وفي الواجهة `rcBuildSelect` يكتب «اختر الصنف...» لكل فلتر من نوع `item` حتى لو كان عميلاً.

**الإصلاح:**

```php
// sources(): أضف
'paymentMethods' => [
    ['id' => SalesInvoice::PAYMENT_CREDIT,  'text' => 'آجل'],
    ['id' => SalesInvoice::PAYMENT_CASH,    'text' => 'نقد'],
    ['id' => SalesInvoice::PAYMENT_BANK,    'text' => 'بنك'],
    ['id' => SalesInvoice::PAYMENT_NETWORK, 'text' => 'شبكة'],
],
```

- العملاء والموردون **لا** تُحمَّل كلها في القائمة، بل عبر بحث AJAX (انظر L8).
- في تعريف الفلتر أضف `'placeholder' => 'اختر العميل...'`، وفي JS: `filter.placeholder ?? 'الكل'`.
- أضف اختباراً يمر على كل فلتر من كل تقرير ويتأكد أن `source` موجود في ما يرجعه `sources()` أو في نقطة البحث.

### 🔴 B3 — لا مصادقة ولا صلاحيات على التقارير

مسارات `/reports/*` بلا أي middleware، والملف نفسه يذكر `SECURITY GAP` قرب السطر 964، وهناك مسارات `admin/export` مكشوفة أيضاً. أي شخص يصل للرابط يقرأ القيود والأرصدة ويصدّرها.

```php
Route::middleware(['auth', 'throttle:reports'])->prefix('reports')->group(function () {
    Route::get('/', [ReportCenterController::class, 'index'])->name('reports.index');
    Route::get('/data/{key}',   ...)->middleware('can:view-report,key');
    Route::get('/print/{key}',  ...)->middleware('can:view-report,key');
    Route::get('/export/{key}', ...)->middleware(['can:export-report,key', 'throttle:5,1']);
});
```

- أضف للعقد `Report` دالة `permission(): string` (مثل `reports.accounting.trial-balance`)، وفلتر `ReportRegistry::all()` حسب صلاحية المستخدم حتى لا يرى القائمة ما لا يحق له.
- إذا لم يكن في النظام تسجيل دخول أصلاً (جدول users موجود لكن لم أرَ شاشة دخول)، فهذه مرحلة تسبق كل شيء.
- سجّل من صدّر ماذا ومتى (Audit log).

### 🔴 B4 — إجماليات الفواتير تخلط العملات

`SalesInvoicesReport` يجمع `items_total` و`discount_total` لكل الفواتير في رقم واحد، ولو اختلفت العملات يصبح المجموع بلا معنى (ريال + دولار).

**الإصلاح:** إما إجماليات لكل عملة، وهو الأسلم:

```php
$byCoin = (clone $query)->toBase()->cloneWithout(['columns'])
    ->select('coin_id')   // اسم عمود العملة حسب جدول الفواتير (تحقق)
    ->selectRaw('SUM(items_total) AS items, SUM(discount_total) AS disc')
    ->groupBy('coin_id')->get();
// أرجعها في meta['totals_by_currency'] واعرضها في الواجهة تحت الجدول
```

أو التحويل للعملة المحلية بسعر الصرف المحفوظ في الفاتورة إن وُجد. وكرر الأمر في `PurchaseInvoicesReport`.

### 🔴 B5 — حركة الصنف: عمود القيمة يجمع الداخل والخارج معاً

`SUM(inventory_movement_details.total)` يجمع قيمة التوريد وقيمة الصرف في رقم واحد، وهو رقم غير مفيد. ولا يوجد رصيد تراكمي للكمية ولا رصيد افتتاحي، مع أن اختبار `test_item_ledger_quantity_balance` موجود.

- افصل `value_in` و`value_out` في الأعمدة والإجماليات.
- أضف عمود **الرصيد** (كمية وقيمة) برصيد افتتاحي = مجموع ما قبل `date_from` لكل صنف.
- اجعل فلتر الصنف **إلزامياً أو يعرض تجميعاً لكل صنف**، لأن العرض بلا صنف يخلط أصنافاً مختلفة في سلسلة واحدة.
- الرصيد التراكمي في SQL (يحتاج MySQL 8):

```sql
SUM(CASE WHEN im.direction='in' THEN d.quantity ELSE -d.quantity END)
  OVER (PARTITION BY d.item_id ORDER BY im.movement_date, im.movement_id, d.<PK>)
```

ولا تحسبه فوق النتيجة المفلترة بالتاريخ وحدها، وإلا بدأ من صفر. أضف الرصيد الافتتاحي إليه.

### 🔴 B6 — ميزان المراجعة ناقص محاسبياً

يعرض مدين ودائن الفترة فقط. الميزان المعتمد يحتاج: رصيد أول المدة، حركة الفترة، رصيد آخر المدة، مع التحقق من التوازن.

```php
->selectRaw('SUM(CASE WHEN je.entryDate < ? THEN l.localDebit - l.localCredit ELSE 0 END) AS opening', [$from])
->selectRaw('SUM(CASE WHEN je.entryDate BETWEEN ? AND ? THEN l.localDebit  ELSE 0 END) AS debit',  [$from, $to])
->selectRaw('SUM(CASE WHEN je.entryDate BETWEEN ? AND ? THEN l.localCredit ELSE 0 END) AS credit', [$from, $to])
->where('je.entryDate', '<=', $to)
// closing = opening + debit - credit ثم يُعرض في عمود مدين أو دائن حسب الإشارة
```

- أضف `meta['balanced']` و`meta['difference']` وتنبيهاً ظاهراً إذا `abs(مدين - دائن) > 0.005`.
- الأرقام تُجمع الآن بـ `float` بدون تقريب في هذا التقرير (انظر القسم 6).
- أضف خيار «تجميع حسب الحساب الأب» ومستوى العمق، وإخفاء الأصفار.

### 🟠 B7 — «صمام الزمن» في كشف الحساب لا يحمي

يقيس الزمن **بعد** انتهاء الاستعلام، فلا يمنع استعلاماً بطيئاً من تعليق الخادم، وإنما يرفض نتيجة جاهزة. الصمام الفعلي هو حد الصفوف (20000) فقط. وهذا الحد يأتي بعد `COUNT` على نفس الـ JOIN، فالاستعلام يُنفَّذ مرتين.

```php
// MySQL: حد زمن حقيقي على مستوى الاستعلام
$query->from(DB::raw('JournalEntrryLine /*+ MAX_EXECUTION_TIME(5000) */'));
// أو: DB::statement('SET SESSION max_execution_time = 5000'); داخل try/finally ثم أعده
```

التقط الاستثناء وأرجع رسالة «استغرق التقرير وقتاً طويلاً».

### 🟠 B8 — `whereDate` يعطّل الفهارس

كل التقارير تستخدم `whereDate('je.entryDate', ...)`، وهو يتحول إلى `DATE(col) >= ?` فلا يُستفاد من الفهارس `idx_je_date` و`idx_je_date_no` التي أنشأتها في الـ migration. وعمود `entryDate` من نوع `date` أصلاً، فالمقارنة المباشرة آمنة:

```php
final class DateRange
{
    public static function apply($q, string $col, array $f): void
    {
        if (!empty($f['date_from'])) $q->where($col, '>=', $f['date_from']);
        if (!empty($f['date_to']))   $q->where($col, '<=', $f['date_to']);
    }
}
```

أما الأعمدة من نوع datetime (فواتير، سندات؟) فاستخدم `>= 'from 00:00:00'` و`< 'to + يوم'`. تحقق من نوع `invoice_date` و`voucherDate` و`movement_date`. وهذه الدالة تُنهي أيضاً تكرار الشرط في خمسة تقارير.

### 🟠 B9 — تحقق ضعيف من المدخلات

`castValue` يترك التواريخ نصوصاً، وتاريخ خاطئ مثل `2026-13-45` يسبب خطأ SQL (500) بدل رسالة واضحة. ولا يوجد فحص لكون `date_from` أكبر من `date_to`، ولا حد أعلى لطول `search`، ولا هروب لرموز `%` و`_` في `LIKE`.

```php
'date' => self::strictDate($value),   // createFromFormat('Y-m-d') + التحقق من المطابقة
// عند الفشل: throw new ReportValidationException('تاريخ غير صالح: ' . $label);
// في sanitizeFilters: إذا from > to فبدّلهما وأضف meta.swapped = true
// text: mb_substr(trim($v), 0, 100) + str_replace(['%','_'], ['\\%','\\_'], $v)
```

والـ Controller يلتقط `ReportValidationException` ويرجع 422 برسالة عربية.

### 🟠 B10 — تقرير السندات يحمّل كل شيء في الذاكرة

يجلب كل سندات القبض ثم كل سندات الصرف بـ `->get()` ثم `usort` ثم `array_slice`. مع عشرات الآلاف يستهلك الذاكرة ويبطؤ كل صفحة.

**الإصلاح:** استعلام `UNION ALL` واحد مع ترقيم في قاعدة البيانات:

```php
$r = ReceiptVoucher::query()->selectRaw("'R' AS kind, receiptID AS id, voucherDate, localAmount, ...");
$p = PaymentVoucher::query()->selectRaw("'P' AS kind, paymentID AS id, voucherDate, localAmount, ...");
$union = $r->unionAll($p);
$q = DB::query()->fromSub($union, 'v')->orderBy('voucherDate')->orderBy('kind')->orderBy('id');
// ثم forPage أو cursor، والإجماليات SUM على نفس الـ fromSub
```

ثم حمّل أسماء الحسابات بـ `whereIn` لصفحة واحدة فقط.

### 🟠 B11 — كاش القوائم لا يُبطَل

`Cache::remember('reports.sources.v1', 3600)` لا يُمسح عند إضافة حساب أو صنف أو عملة، فالحساب الجديد لا يظهر حتى ساعة. وفي الواجهة المفتاح `rc.sources.v1` يوحي بتخزين آخر في المتصفح **(؟)**، فيتضاعف التأخير.

- أضف `Cache::forget` في الـ Observers الموجودة (`CharAccountObserver`، `CoinObserver`... إلخ).
- في المتصفح: خزّن مع `version` يأتي من الخادم أو TTL قصير، وتحقق بـ ETag.

### 🟠 B12 — جداول الـ migrations الأساسية خارج `database/migrations`

مجلد `database/migrations` يحتوي 6 ملفات فقط، وإنشاء جداول القيود والأصناف والمخازن في `database/bac/`. تشغيل `php artisan migrate` على خادم جديد لن ينشئ جداول التقارير، والاختبارات قد تفشل أو تعتمد على قاعدة جاهزة **(؟)**. انقل الملفات إلى المكان الصحيح أو وثّق الاستعادة.

### 🟠 B13 — القيود بلا حالة

جدول `Journal_Entries` ليس فيه عمود حالة (مسودة/مرحّل/ملغى). كل التقارير تقرأ كل القيود. إذا كانت الفواتير أو السندات تُلغى أو تُعدَّل، تأكد كيف ينعكس ذلك على القيد (حذف أم قيد عكسي)، وإلا بقيت أرقام ملغاة في الميزان **(؟)**. ويتحقق الأمر نفسه لفواتير البيع والشراء.

### 🟡 B14 — اتساق العقد

- `footer => 'last'` في كشف الحساب غير معرّف في العقد (`sum|none`). أضفه في توثيق `Report` وتحقق منه في `ReportEngine`.
- `ReportRegistry::has()` ثم `get()` يمران على كل الـ classes في كل استدعاء (O(n))، و`index()` يستدعي `all()` مرتين. استخدم مصفوفة مفتاح→class.
- رد «التقرير غير موجود» يجب أن يكون **404** وليس 422.
- `Log::warning` لحالة تطبيع عادية (`account_to` بدون `account_from`) يملأ السجل. نزّله إلى `debug` أو احذفه.

---

## 3) كثرة البيانات (Scale)

الأرقام الآتية افتراضية: افترض ملايين الأسطر في `JournalEntrryLine` بعد سنوات من الاستخدام.

| # | المشكلة | الحل |
| --- | --- | --- |
| L1 | `COUNT(*)` على JOIN في كل طلب صفحة | اجلب `per_page + 1` لمعرفة وجود صفحة تالية، واخزّن الإجمالي والمجاميع 30–60 ثانية بمفتاح `md5(filters)` |
| L2 | الترقيم بـ `skip/take` يبطؤ في الصفحات العميقة | ترقيم بالمؤشر (keyset): `WHERE (entryDate, entryID, entryLineID) > (?, ?, ?)` وإرجاع `next_cursor` |
| L3 | كشف الحساب يبني الأرصدة في PHP ويرفض فوق 20 ألف صف | رصيد تراكمي بـ Window Function في SQL، ومعالجة حساب حساب بـ `cursor()`، وإزالة الرفض لصالح الترقيم |
| L4 | الرصيد الافتتاحي والميزان يعيدان جمع كل التاريخ | جدول أرصدة شهرية مغلقة (يوجد جدول `account_balances` في الـ migrations، تحقق من محتواه) واجمع منه ما قبل الفترة |
| L5 | التصدير يعمل داخل الطلب | تدفق `streamDownload` مع `cursor()` ودفعات 1000 صف، وفوق `export_max_rows` يُرسل إلى Job في الطابور ويُحفظ الملف في `storage/exports` مع إشعار |
| L6 | البحث `LIKE '%x%'` في 5 أعمدة | حد أدنى 3 أحرف، وبحث ببداية الكلمة، أو FULLTEXT، وفلتر رقم القيد على حدة |
| L7 | تقارير الفترات المغلقة تُعاد حسابها | كاش بنسخة (`reports:version`) تزيد عند أي ترحيل قيد، وتُبطل كل التخزين |
| L8 | `sources()` يحمّل كل الحسابات والأصناف | احذفها من `sources()` وأنشئ `/reports/lookup/{type}?q=` يرجع 20 نتيجة مع debounce في الواجهة |
| L9 | `->get()->groupBy()` لعشرات الآلاف | استخدم `cursor()` أو `lazyById()` وبناء الصفوف تدريجياً |
| L10 | قاعدة افتراضية SQLite في `.env.example` | وثّق أن التقارير الثقيلة تتطلب MySQL 8 أو PostgreSQL (Window Functions، تلميحات الزمن) |

**الفهارس المقترحة (تحقق أولاً بـ `EXPLAIN`):**

```sql
-- تغطية تجميعات الميزان والكشف دون الرجوع للجدول
CREATE INDEX idx_jel_acc_entry_amt ON JournalEntrryLine (accountID, entryID, localDebit, localCredit);
-- فواتير البيع والشراء (أسماء الأعمدة حسب الجدول)
CREATE INDEX idx_si_date ON sales_invoices (invoice_date);
CREATE INDEX idx_si_customer_date ON sales_invoices (account_id, invoice_date);
CREATE INDEX idx_si_payment_date ON sales_invoices (payment_method, invoice_date);
-- السندات
CREATE INDEX idx_rv_date ON receipt_vouchers (voucherDate);
CREATE INDEX idx_pv_date ON payment_vouchers (voucherDate);
```

فهارس القيود الأساسية (`idx_je_date`، `idx_jel_account_entry`) موجودة فعلاً، لكن `whereDate` (B8) كان يمنع استخدامها.

---

## 4) السرعة (الخادم والواجهة)

| # | الملاحظة | الإصلاح |
| --- | --- | --- |
| S1 | `accounts()` يرجع نفس القائمة مرتين (`eligible` و`accounts`) ومعها الشجرة كاملة، بلا كاش | احذف المكرر، واكاش النتيجة بمفتاح نسخة، وأضف ETag/304 |
| S2 | `reportCenter.js` ملف واحد 1625 سطراً (62KB) يحمّل كل شيء مع أول فتح | قسّمه (فلاتر، جدول، مودال الحساب، تصدير) وحمّل مودال الحساب عند الحاجة بـ `import()` عبر Vite |
| S3 | لا إلغاء للطلبات القديمة عند تغيير الفلتر أو التبديل بين التقارير، فقد يصل رد قديم بعد الجديد | `AbortController` لكل طلب، وتجاهل أي رد لا يطابق آخر طلب |
| S4 | حقول النص ترسل طلباً مع كل تغيير **(؟)** | debounce بمقدار 300ms، وزر «عرض» صريح للفلاتر الثقيلة |
| S5 | البحث السريع `quickSearch` يصفّي **الصفحة الحالية فقط** (`rcVisibleRows` على `RC.rows`)، فيظن المستخدم أنه يبحث في كل التقرير | سمّه «تصفية الصفحة الحالية» أو انقله للخادم |
| S6 | كل رد `data` يحمل الأعمدة والحقول الداخلية (`_account_id`) | أرجع `meta.columns` فقط حين تتغير، واحذف الحقول الداخلية، وفعّل gzip/brotli |
| S7 | لا كاش لنتائج الاستعلامات المتكررة | كاش قصير (30–60 ث) بمفتاح `report + md5(filters) + page + version`، ويُبطل بـ `reports:version` (انظر L7) |
| S8 | `ChartAccountScope::eligible()` يُستدعى في كل تشغيل لكشف الحساب وفي `accounts()` | لم أقرأ هذا الكلاس: تحقق إن كان يقرأ الدليل كاملاً كل مرة، واكاشه |
| S9 | `ReportRegistry::has()` ثم `get()` يعيدان المرور على كل التقارير | مصفوفة مفتاح→class تُبنى مرة واحدة |

---

## 5) سهولة الاستخدام

**أولوية عالية**

- **قيم جاهزة للتاريخ:** اليوم، هذا الشهر، الشهر الماضي، الربع، السنة المالية، مع تحقق فوري إذا كان «من» بعد «إلى».
- **حفظ آخر فلاتر لكل تقرير** في المتصفح، وزر «إعادة تعيين» (موجود جزئياً في `rcResetFilters`).
- **رابط قابل للمشاركة:** اكتب الفلاتر في الـ URL (`?report=trial-balance&date_from=...`) فيفتح الرابط نفس النتيجة.
- **الضغط على الصف يفتح المستند:** الصفوف تحمل `id` أصلاً (القيد، الفاتورة، السند). السندات تحمل بادئة `R`/`P` ويجب فكّها عند بناء الرابط.
- **فرز حسب العمود من الخادم** بقائمة أعمدة مسموحة (whitelist) حتى لا يُحقن اسم عمود.
- **تصدير Excel حقيقي (xlsx)** بدل CSV فقط (مكتبة مثل OpenSpout أو Maatwebsite)، مع عنوان التقرير والفلاتر في أول الملف.
- **تصدير PDF** عبر mPDF أو DomPDF مع دعم العربية، بدل الاعتماد على طباعة المتصفح.

**أولوية متوسطة**

- شريط يعرض الفلاتر المطبقة على شكل شارات قابلة للإزالة.
- أعمدة قابلة للإخفاء، وتذكّر اختيار المستخدم.
- في كشف الحساب: اكتب «مدين/دائن» بجانب الرصيد بدل الاعتماد على اللون فقط (الخطة المرفقة `reports_7_fixes_plan.md` تعتمد ألواناً: أحمر/أخضر).
- حالات واضحة: تحميل (skeleton)، لا نتائج، خطأ مع زر «إعادة المحاولة»، وتعطيل الأزرار أثناء التنفيذ لمنع النقر المزدوج.
- شارة «تم اقتطاع النتيجة» إذا طُبّق حد الصفوف.
- الطباعة: ترويسة تتكرر في كل صفحة، رقم الصفحة، اتجاه أفقي للتقارير العريضة، واسم من طبع وتاريخ الطباعة.
- تحسين الجوال: الجدول العريض في حاوية قابلة للتمرير أفقياً مع تثبيت أول عمود.

---

## 6) استكشاف الأخطاء (Debuggability)

### 6.1 ثغرات صغيرة وجدتها في الكود

- **🟠 مدخل مصفوفة يسقط الخادم:** `?date_from[]=x` يمر إلى `trim((string) $value)` فيرمي خطأ «Array to string conversion» (500). أضف في `sanitizeFilters`: `if (!is_scalar($value)) continue;`.
- **🟠 حقن صيغ في CSV (CSV Injection):** أي بيان يبدأ بـ `=` أو `+` أو `-` أو `@` يُنفَّذ كمعادلة في Excel. عالجها عند التصدير:

```php
$safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/u', $v) ? "'" . $v : $v;
```

- **🟠 صف الإجماليات في CSV يتجاهل `footer = last`:** الكود يعالج `sum` فقط، فيخرج رصيد كشف الحساب فارغاً.
- **🟡 `castValue` يحوّل أي قيمة رقمية في `select` إلى int:** رمز مثل `007` يصبح `7`. اجعله حسب نوع المصدر.

### 6.2 تسجيل منظم

```php
// ReportEngine::execute
$t = hrtime(true);
$result = $report->run($filters);
$ms = (hrtime(true) - $t) / 1e6;

Log::channel('reports')->info('report.run', [
    'key' => $key, 'user' => auth()->id(), 'ms' => round($ms),
    'rows' => count($result['rows'] ?? []), 'mem_mb' => round(memory_get_peak_usage() / 1048576),
    'filters' => $filters,
]);
if ($ms > 2000) Log::channel('reports')->warning('report.slow', ['key' => $key, 'ms' => $ms]);
```

### 6.3 معالجة موحدة للأخطاء

- استثناءات مخصصة: `ReportValidationException` (422)، `ReportTooLargeException` (422 مع رسالة واضحة)، `ReportTimeoutException` (503)، و404 للتقرير غير الموجود.
- أي استثناء آخر: 500 برسالة عامة و`request_id`، والتفاصيل في السجل فقط.
- الاستجابة دائماً بالشكل `{success, code, message, request_id}`.
- في الواجهة: تأكد أن `fetch` يتعامل مع ردود غير JSON وغير 2xx **(؟)** ويعرض `request_id` للدعم.

### 6.4 أدوات لصيد الأخطاء مبكراً

- **اختبار عقد يمر على كل تقرير في السجل تلقائياً:** كل `source` معرّف في `sources()` أو في نقطة البحث، كل مفتاح عمود موجود في الصفوف، كل مفتاح في `totals` له عمود `footer`، و`run([])` لا يرمي استثناء. هذا الاختبار وحده كان سيكشف B2 وB14.
- **أمر مطابقة `php artisan reports:verify`:** ميزان المراجعة متوازن، مجموع سندات القبض والصرف = مجموع قيودها، إجمالي الفواتير = قيودها، رصيد حركة الصنف = جدول المخزون. هذه مطابقات تكشف أخطاء البيانات في الإنتاج.
- في بيئة التطوير فقط: `meta.debug = {sql_count, sql_ms}` عبر `DB::listen`، أو Debugbar/Telescope.
- اختبار حمل بمليون سطر قيد (Factory) مع حد زمني، و`EXPLAIN` للتأكد من استخدام الفهارس.

---

## 7) مراجعة الكود (Code Review)

| # | الملاحظة | التوصية |
| --- | --- | --- |
| CR1 | منطق مكرر في 5 تقارير: فلتر التاريخ، `clone` للإجماليات، الترقيم، حساب `lastPage` | `abstract class BaseReport` + trait للترقيم وآخر للتاريخ (الهيكل أدناه) |
| CR2 | `ReportEngine` و`ReportRegistry` كلهم `static`، يصعب عمل mock أو feature flag | سجّلهما في الـ container وأحقنهما في الـ Controller |
| CR3 | قائمة التقارير ثابتة في `const REGISTRY` | انقلها إلى `config/reports.php` مع `enabled` و`permission` و`order` |
| CR4 | الأموال تُجمع بـ `float` (خاصة ميزان المراجعة بلا `round`)، فيظهر فرق 0.01 | استخدم `bcadd` أو اجمع في SQL (DECIMAL) وقارن بحد `0.005` |
| CR5 | الإرجاع مصفوفات بلا نوع | أنشئ `ReportResult` و`FilterDefinition` (readonly classes) |
| CR6 | أرقام سحرية: `PER_PAGE=50` مكررة، `3600`، `20000` | انقلها إلى `config/reports.php` |
| CR7 | أسماء الجداول مكتوبة نصاً في كل `join` (`JournalEntrryLine` ×6، فيها خطأ إملائي موروث) | استخدم `(new JournalEntryLine)->getTable()` أو ثابت واحد |
| CR8 | `AccountStatementReport::run()` نحو 250 سطراً بمسؤوليات متعددة | قسّمها: `resolveScope`، `normalizeRange`، `fetchOpenings`، `buildRows`، `buildTotals` |
| CR9 | الفلتر المخفي `account_id` للتوافق الخلفي | حدد تاريخاً لإزالته وسجّل استخدامه قبل ذلك |
| CR10 | `Log::warning` لحالة عادية وتعليقات برموز ❸ ❹ ❻ تشير لخطة خارجية | نزّل مستوى السجل، وانقل الشرح إلى توثيق التقرير |
| CR11 | الواجهة تبني HTML بقوالب نصية داخل `innerHTML` (آمنة اليوم بفضل `rcEscape`) | استخدم دالة `html` تهرب القيم افتراضياً، أو قاعدة lint تمنع `innerHTML` مع قيم غير مهربة |
| CR12 | التحقق من الحقول فقط داخل كل تقرير (`ChartAccountScope`) | انقل قواعد التحقق إلى تعريف الفلتر (`required`, `min`, `max`, `rules`) ليطبقها المحرك |

**هيكل مقترح لـ `BaseReport` (يقلّص كل تقرير إلى \~40 سطراً):**

```php
abstract class BaseReport implements Report
{
    abstract protected function baseQuery(array $f): Builder;      // joins + فلاتر
    abstract protected function mapRow(object $r): array;          // صف واحد للعرض
    abstract protected function totals(Builder $q): array;         // SUM على كل النتائج
    protected function orderBy(Builder $q): void {}

    public function run(array $f): array
    {
        $q = $this->baseQuery($f);
        $totals = $this->totals(clone $q);
        $this->orderBy($q);

        if (!empty($f['_all'])) {          // طباعة/تصدير: تدفق دون ترقيم
            return ['rows' => $q->cursor()->map(fn ($r) => $this->mapRow($r)), 'totals' => $totals, 'meta' => []];
        }
        $rows = $q->forPage($f['page'] ?? 1, $per = config('reports.per_page', 50) + 1)->get();
        // per_page + 1 لمعرفة وجود صفحة تالية بدون COUNT
        ...
    }
}
```

---

## 8) اختبارات يجب إضافتها

1. **عقد كل التقارير** (6.4) على الـ Registry كاملاً.
2. **التصدير والطباعة:** عدد صفوف CSV = إجمالي النتائج (> 50)، والحد الأقصى يرجع رسالة واضحة.
3. **الصلاحيات:** بدون تسجيل دخول 401/302، وبدون صلاحية 403، ولا يظهر في القائمة تقرير غير مصرح به.
4. **تعدد العملات:** فواتير بعملتين تعطي إجماليين منفصلين.
5. **ميزان المراجعة:** رصيد أول المدة = مجموع ما قبل التاريخ، والإقفال = افتتاحي + حركة، وعدم التوازن يظهر تنبيهاً.
6. **حركة الصنف:** الرصيد التراكمي مع فترة تبدأ بعد أول حركة.
7. **التحقق:** تاريخ خاطئ، `from > to`، مصفوفة بدل نص، `search` بطول 10000، رمز `%` في البحث.
8. **CSV Injection:** صف بيانه `=1+1` يخرج مسبوقاً بـ `'`.
9. **الأداء:** 1M سطر قيد، كل تقرير ضمن حد زمني، والاستعلام يستخدم الفهرس (`EXPLAIN`).
10. **السندات:** ترقيم على قاعدة البيانات (UNION)، وتتطابق الإجماليات مع النسخة القديمة.
11. **الكاش:** إضافة حساب جديد يظهر فوراً في القوائم.
12. **حالات حدية:** قاعدة بيانات فارغة، فترة بلا حركة، حساب بلا أبناء، نطاق مقلوب في كشف الحساب.

---

## 9) خارطة التنفيذ المقترحة

| المرحلة | المحتوى | معيار القبول |
| --- | --- | --- |
| **0 — فوري (يوم)** | B3 (حماية المسارات)، B2 (مصادر القوائم)، مدخل المصفوفة، CSV Injection، رد 404 | لا يفتح تقرير بلا دخول، وكل فلتر يعمل |
| **1 — الصحة (2–3 أيام)** | B1، B4، B5، B6، B8، B9، B10، اختبار العقد، التسجيل المنظم | التصدير يطابق الشاشة، وإجماليات العملات صحيحة، وميزان متوازن |
| **2 — الأداء (أسبوع)** | `BaseReport`، الترقيم بالمؤشر، إزالة `COUNT`، الفهارس، نقطة بحث `/reports/lookup`، إبطال الكاش، تقسيم JS | كل تقرير أقل من 500ms على مليون سطر |
| **3 — التجربة (أسبوعان)** | Excel/PDF، تصدير بالطابور، أرصدة شهرية مغلقة، الفلاتر المحفوظة، الروابط، الدخول للمستند | موافقة المستخدمين على سيناريوهات الاستخدام |

## 10) أسئلة تحتاج قرارك قبل البدء

1. هل في النظام تسجيل دخول وصلاحيات الآن، أم يجب بناؤهما أولاً (يؤثر على المرحلة 0)؟
2. أي قاعدة بيانات في الإنتاج: MySQL 8 أم SQLite؟ (الحلول المقترحة للتقارير الثقيلة تفترض MySQL 8 أو PostgreSQL.)
3. ما الحجم المتوقع بعد سنة: كم قيد وكم فاتورة؟ (يحدد هل نحتاج أرصدة شهرية مغلقة الآن أم لاحقاً.)
4. كيف يُلغى القيد أو الفاتورة: حذف أم قيد عكسي أم حالة؟ (يحدد B13.)
5. هل الفواتير تُحفظ بسعر صرف وقت إصدارها، لتحويل الإجماليات للعملة المحلية بدل عرضها لكل عملة؟
