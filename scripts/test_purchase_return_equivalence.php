<?php

/*
|--------------------------------------------------------------------------
| اختبار التكافؤ الرقمي — المسار الفردي vs المسار المجمّع
|--------------------------------------------------------------------------
| تشغيل:  php scripts/test_purchase_return_equivalence.php
|
| يقارن ناتج availableForReturn() الفردي مع availableForReturnMany()
| لكل سطر فاتورة شراء — قراءة فقط، لا يكتب شيئاً، ولا يشغّل أي migration.
|
| بوابة القبول: فرق <= 1e-9 لكل سطر في الحالتين:
|   1) بدون exceptReturnId   (مسار invoicePicker)
|   2) مع exceptReturnId     (مسار show — على سطور مرتجع موجود)
*/

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\PurchaseReturnDetail;
use App\Services\Purchases\PurchaseReturnService;
use Illuminate\Support\Facades\DB;

$service = app(PurchaseReturnService::class);

/* ✅ عدّاد استعلامات لإثبات التحسين رقمياً */
$queryCount = 0;
DB::listen(function () use (&$queryCount) {
    $queryCount++;
});

/* ---------- عيّنة السطور: فواتير مرحّلة حديثة (تغطي أيضاً الأصناف المفرزة) ----------
   MariaDB لا يدعم LIMIT داخل IN — لذا نجلّب IDs الفواتير أولاً في استعلام مستقل */
$recentInvoiceIds = PurchaseInvoice::query()
    ->where('is_posted', true)
    ->orderByDesc('purchase_invoice_id')
    ->limit(60)
    ->pluck('purchase_invoice_id')
    ->all();

$detailIds = empty($recentInvoiceIds)
    ? []
    : PurchaseInvoiceDetail::query()
        ->whereIn('purchase_invoice_id', $recentInvoiceIds)
        ->limit(300)
        ->pluck('purchase_invoice_detail_id')
        ->all();

if (empty($detailIds)) {
    exit("لا توجد بيانات كافية للاختبار (لا فواتير مرحّلة).\n");
}

echo "عدد السطور قيد الاختبار: " . count($detailIds) . "\n";
$failures = 0;

/* ---------- الحالة 1: بدون استثناء (مسار invoicePicker) ---------- */
$queryCount = 0;
$t0 = microtime(true);

$single = [];
foreach ($detailIds as $id) {
    $single[$id] = $service->availableForReturn((int) $id);
}
$legacyCount1 = $queryCount;
$legacyTime1  = microtime(true) - $t0;

$queryCount = 0;
$t0 = microtime(true);
$batch = $service->availableForReturnMany($detailIds);
$batchCount1 = $queryCount;
$batchTime1  = microtime(true) - $t0;

$maxDiff1 = 0.0;
foreach ($detailIds as $id) {
    $diff = abs(((float) $single[$id]) - ((float) ($batch[$id] ?? 0.0)));
    $maxDiff1 = max($maxDiff1, $diff);
    if ($diff > 1e-9) {
        $failures++;
        echo "  FAIL (picker) detail={$id} single={$single[$id]} batch=" . ($batch[$id] ?? 'MISSING') . "\n";
    }
}

printf(
    "[picker]  single: %d query / %.3fs | batch: %d query / %.3fs | maxDiff=%.2e\n",
    $legacyCount1, $legacyTime1, $batchCount1, $batchTime1, $maxDiff1
);

/* ---------- الحالة 2: مع exceptReturnId (مسار show) ---------- */
$return = PurchaseReturn::query()->orderByDesc('purchase_return_id')->first();
$returnDetailIds = [];

if ($return) {
    $returnDetailIds = PurchaseReturnDetail::query()
        ->where('purchase_return_id', $return->purchase_return_id)
        ->pluck('purchase_invoice_detail_id')
        ->filter()
        ->unique()
        ->values()
        ->all();
}

if (!empty($returnDetailIds)) {
    $exceptId = (int) $return->purchase_return_id;

    $queryCount = 0;
    $t0 = microtime(true);
    $single2 = [];
    foreach ($returnDetailIds as $id) {
        $single2[$id] = $service->availableForReturn((int) $id, $exceptId);
    }
    $legacyCount2 = $queryCount;
    $legacyTime2  = microtime(true) - $t0;

    $queryCount = 0;
    $t0 = microtime(true);
    $batch2 = $service->availableForReturnMany($returnDetailIds, $exceptId);
    $batchCount2 = $queryCount;
    $batchTime2  = microtime(true) - $t0;

    $maxDiff2 = 0.0;
    foreach ($returnDetailIds as $id) {
        $diff = abs(((float) $single2[$id]) - ((float) ($batch2[$id] ?? 0.0)));
        $maxDiff2 = max($maxDiff2, $diff);
        if ($diff > 1e-9) {
            $failures++;
            echo "  FAIL (show) detail={$id} single={$single2[$id]} batch=" . ($batch2[$id] ?? 'MISSING') . "\n";
        }
    }

    printf(
        "[show]    single: %d query / %.3fs | batch: %d query / %.3fs | maxDiff=%.2e\n",
        $legacyCount2, $legacyTime2, $batchCount2, $batchTime2, $maxDiff2
    );
} else {
    echo "[show]    تخطّي — لا يوجد مرتجع بسطور للفحص.\n";
}

/* ---------- الحكم النهائي ---------- */
if ($failures === 0) {
    echo "\nPASS — التكافؤ الرقمي مضمون (فرق <= 1e-9 في كل الحالات).\n";
    exit(0);
}

echo "\nFAIL — عدد الاختلافات: {$failures} — لا يُفعَّل الـ Feature Flag.\n";
exit(1);
