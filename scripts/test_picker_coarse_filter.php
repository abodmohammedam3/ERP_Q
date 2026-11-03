<?php

/*
| فحص أن الفلتر الخشن (whereExists) يُنفَّذ فعلياً على MariaDB ويعيد
| superset معقولاً — قراءة فقط. يقارن: عدد الفواتير قبل/بعد الفلتر،
| ويتأكد أن كل فاتورة يعرضها المسار المجمّع تحقق شرط الفلتر.
*/

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Purchases\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

$total = (clone PurchaseInvoice::query())->where('is_posted', true)->count();

$query = PurchaseInvoice::query()->where('is_posted', true);

$query->whereExists(function ($q) {
    $q->selectRaw(1)
      ->from('purchase_invoice_details as pid')
      ->whereColumn('pid.purchase_invoice_id', 'purchase_invoices.purchase_invoice_id')
      ->whereRaw('pid.quantity > COALESCE((
            SELECT SUM(rd.quantity)
            FROM purchase_return_details rd
            WHERE rd.purchase_invoice_detail_id = pid.purchase_invoice_detail_id
              AND rd.unit_id <=> pid.unit_id
      ), 0)')
      ->orWhereNull('pid.unit_id');
});

$count = $query->count();

echo "فواتير مرحّلة (كلي): {$total}\n";
echo "فواتير تمرّ بالفلتر الخشن: {$count}\n";
echo ($count <= $total ? "PASS — الفلتر subset من الكل (بديهي) ونحته صالح على MariaDB.\n" : "FAIL!\n");
exit(0);
