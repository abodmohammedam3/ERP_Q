<?php

/*
| فحص طرف-إلى-طرف للمسار المجمّع invoicePickerBatched:
|  1) يُنفَّذ دون أخطاء SQL.
|  2) الـ JSON الناتج مطابق بنيوياً ورقمياً للمسار القديم (flag OFF) —
|     باستثناء اختلاف الترتيب/الاكتمال المسموح (المسار الجديد يجلب حتى 100
|     فاتورة صالحة عبر جولات، والقديم يقتطع 100 قبل الفلترة).
| قراءة فقط — لا migrations، لا كتابة.
*/

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Operation\Purchases\PurchaseReturnApiController;
use App\Services\Purchases\PurchaseReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$queryCount = 0;
DB::listen(function () use (&$queryCount) {
    $queryCount++;
});

$controller = app(PurchaseReturnApiController::class);
$request = new Request(['search' => '']);

/* ---------- المسار القديم (flag OFF) ---------- */
config(['features.purchase_return_batch_queries' => false]);
$queryCount = 0;
$oldResponse = $controller->invoicePicker($request);
$oldData = json_decode($oldResponse->getContent(), true)['data'];
$oldCount = $queryCount;

/* ---------- المسار الجديد (flag ON) ---------- */
config(['features.purchase_return_batch_queries' => true]);
$queryCount = 0;
$newResponse = $controller->invoicePicker($request);
$newData = json_decode($newResponse->getContent(), true)['data'];
$newCount = $queryCount;

printf("old: %d فاتورة | %d استعلام\n", count($oldData), $oldCount);
printf("new: %d فاتورة | %d استعلام\n", count($newData), $newCount);

/* مطابقة حقل-بحقل لكل فاتورة مشتركة (by purchase_invoice_id) */
$oldById = collect($oldData)->keyBy('purchase_invoice_id');
$mismatches = 0;
$maxDiff = 0.0;

foreach ($newData as $row) {
    $old = $oldById->get($row['purchase_invoice_id']);

    if (!$old) {
        continue; // فاتورة صالحة اكتشفها الجديد وفتّرها القديم بسبب limit(100) — مكسب لا كسر
    }

    foreach ($row as $k => $v) {
        $ov = $old[$k];

        if (is_float($v) || is_int($v)) {
            $diff = abs((float) $v - (float) $ov);
            $maxDiff = max($maxDiff, $diff);
            if ($diff > 1e-9) {
                $mismatches++;
                echo "  MISMATCH inv={$row['purchase_invoice_id']} field={$k}: old={$ov} new={$v}\n";
            }
        } elseif ($v !== $ov) {
            $mismatches++;
            echo "  MISMATCH inv={$row['purchase_invoice_id']} field={$k}: old=" . var_export($ov, true) . " new=" . var_export($v, true) . "\n";
        }
    }
}

echo $mismatches === 0
    ? "\nPASS — تطابق كامل بين المسارين على كل الفواتير المشتركة (maxDiff=" . sprintf('%.2e', $maxDiff) . ").\n"
    : "\nFAIL — عدد الاختلافات: {$mismatches}\n";
exit($mismatches === 0 ? 0 : 1);
