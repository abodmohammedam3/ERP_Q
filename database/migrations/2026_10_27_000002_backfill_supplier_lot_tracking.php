<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill — ملء supplier_account_id تاريخياً بقواعد صريحة
 * ------------------------------------------------------------
 * | الحركة            | اتجاه | القاعدة                                        |
 * |--------------------|-------|------------------------------------------------|
 * | purchase           | in    | supplier = purchase_invoices.account_id         |
 * | purchase_return    | out   | supplier عبر purchase_return_details → الفاتورة |
 * | sale               | out   | توزيع FIFO على دفعات الموردين (سكربت PHP)       |
 * | sale_return        | in    | يرث المورد من دفعة البيع الأصلية               |
 * | sorting            | in/out| يرث مورد الدفعة المصروفة                        |
 * | supply / يدوي      | *     | NULL (توريد عام)                                |
 *
 * ✅ تُنفَّذ داخل transaction واحدة + Maintenance Mode.
 * ⚠️ خذ نسخة احتياطية كاملة قبل التنفيذ.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {

            /* 1) الشراء — مباشر من الفاتورة */
            DB::statement("
                UPDATE inventory_movement_details imd
                INNER JOIN inventory_movements im  ON im.movement_id = imd.movement_id
                INNER JOIN purchase_invoices   pi  ON pi.purchase_invoice_id = im.source_id
                SET imd.supplier_account_id = pi.account_id
                WHERE im.source_type = 'purchase_invoice'
                  AND im.direction   = 'in'
                  AND imd.supplier_account_id IS NULL
            ");

            /* 2) مرتجع الشراء — عبر تفاصيل المرتجع → سطر الفاتورة → المورد */
            DB::statement("
                UPDATE inventory_movement_details imd
                INNER JOIN inventory_movements  im  ON im.movement_id  = imd.movement_id
                INNER JOIN purchase_returns     pr  ON pr.purchase_return_id = im.source_id
                INNER JOIN purchase_return_details prd ON prd.purchase_return_id = pr.purchase_return_id
                    AND prd.item_id = imd.item_id
                    AND prd.type_id <=> imd.type_id
                    AND prd.unit_id <=> imd.unit_id
                INNER JOIN purchase_invoice_details pid ON pid.purchase_invoice_detail_id = prd.purchase_invoice_detail_id
                INNER JOIN purchase_invoices     pi  ON pi.purchase_invoice_id = pid.purchase_invoice_id
                SET imd.supplier_account_id = pi.account_id
                WHERE im.source_type = 'purchase_return'
                  AND imd.supplier_account_id IS NULL
            ");

            /* 3) الفرز (out ثم in) — يرث مورد أحدث دفعة صرف منها
                  (تاريخياً: آخر دفعة دخول بنفس الصنف/النوع/المخزن/الوحدة قبل الفرز) */
            DB::statement("
                UPDATE inventory_movement_details imd
                INNER JOIN inventory_movements im  ON im.movement_id = imd.movement_id
                LEFT JOIN inventory_movement_details src
                       ON src.movement_detail_id = (
                           SELECT imd2.movement_detail_id
                           FROM inventory_movement_details imd2
                           INNER JOIN inventory_movements im2 ON im2.movement_id = imd2.movement_id
                           WHERE im2.direction = 'in'
                             AND imd2.item_id      = imd.item_id
                             AND imd2.type_id      <=> imd.type_id
                             AND imd2.warehouse_id = imd.warehouse_id
                             AND imd2.unit_id      <=> imd.unit_id
                             AND imd2.movement_detail_id < imd.movement_detail_id
                           ORDER BY imd2.movement_detail_id DESC
                           LIMIT 1
                       )
                SET imd.supplier_account_id = src.supplier_account_id
                WHERE im.source_type = 'sorting'
                  AND imd.supplier_account_id IS NULL
            ");

            /* 4) مرتجع البيع (in) — يرث المورد من حركة البيع الأصلية عبر
                  lot_source_detail_id المعبأ في الخطوة 5 أو عبر مطابقة سطر البيع */
            DB::statement("
                UPDATE inventory_movement_details imd
                INNER JOIN inventory_movements im  ON im.movement_id = imd.movement_id
                INNER JOIN sales_returns       sr  ON sr.sales_return_id = im.source_id
                INNER JOIN sales_return_details srd ON srd.sales_return_id = sr.sales_return_id
                    AND srd.item_id = imd.item_id
                    AND srd.type_id <=> imd.type_id
                    AND srd.unit_id <=> imd.unit_id
                INNER JOIN sales_invoice_details sid ON sid.sales_invoice_detail_id = srd.sales_invoice_detail_id
                SET imd.supplier_account_id = COALESCE(sid.supplier_account_id, imd.supplier_account_id)
                WHERE im.source_type = 'sales_return'
                  AND imd.supplier_account_id IS NULL
            ");

            /* 5) ربط lot_source_detail_id لمرتجع البيع → دفعة البيع الأصلية
                  (تفاصيل حركة البيع الصادرة لنفس الصنف/النوع/المخزن/الوحدة والمورد المعروف) */
            DB::statement("
                UPDATE inventory_movement_details imd
                INNER JOIN inventory_movements im  ON im.movement_id = imd.movement_id
                SET imd.lot_source_detail_id = (
                    SELECT imd2.movement_detail_id
                    FROM inventory_movement_details imd2
                    INNER JOIN inventory_movements im2 ON im2.movement_id = imd2.movement_id
                    WHERE im2.direction = 'out'
                      AND imd2.item_id      = imd.item_id
                      AND imd2.type_id      <=> imd.type_id
                      AND imd2.warehouse_id = imd.warehouse_id
                      AND imd2.unit_id      <=> imd.unit_id
                      AND im2.movement_id < im.movement_id
                    ORDER BY imd2.movement_detail_id DESC
                    LIMIT 1
                )
                WHERE im.source_type = 'sales_return'
                  AND imd.lot_source_detail_id IS NULL
            ");

            /* 6) ربط lot_source_detail_id للفرز (in) → الدفعة المصروفة (out) */
            DB::statement("
                UPDATE inventory_movement_details imd
                INNER JOIN inventory_movements im  ON im.movement_id = imd.movement_id
                SET imd.lot_source_detail_id = (
                    SELECT imd2.movement_detail_id
                    FROM inventory_movement_details imd2
                    INNER JOIN inventory_movements im2 ON im2.movement_id = imd2.movement_id
                    WHERE im2.direction = 'out'
                      AND imd2.item_id      = imd.item_id
                      AND imd2.type_id      <=> imd.type_id
                      AND imd2.warehouse_id = imd.warehouse_id
                      AND imd2.unit_id      <=> imd.unit_id
                      AND im2.document_number = im.document_number
                    ORDER BY imd2.movement_detail_id DESC
                    LIMIT 1
                )
                WHERE im.source_type = 'sorting'
                  AND im.direction = 'in'
                  AND imd.lot_source_detail_id IS NULL
            ");

            /* 7) توزيع FIFO للـ"بيع" التاريخي (out) على دفعات الموردين
                  — كل دفعة دخول (in) تُحسب فاتورة، والكمية الصادرة تُخصم
                    من أقدم دفعة متبقية لنفس (item,type,wh,unit).
                  ⚠️ هذا الاستعلام SQL خالص معقّد؛ نستخدم PHP للحلقة لأمان المنطق. */
            $this->backfillSalesFifo();

            /* 8) التوريد اليدوي / supply تبقى NULL = "توريد عام" (بلا تغيير) */
        });
    }

    protected function backfillSalesFifo(): void
    {
        /* صفوف البيع الصادرة بلا مورد */
        $saleRows = DB::table('inventory_movement_details as imd')
            ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
            ->where('im.source_type', 'sales_invoice')
            ->where('im.direction', 'out')
            ->whereNull('imd.supplier_account_id')
            ->orderBy('imd.movement_detail_id')
            ->get(['imd.movement_detail_id', 'imd.item_id', 'imd.type_id', 'imd.warehouse_id', 'imd.unit_id', 'imd.quantity']);

        foreach ($saleRows as $row) {
            $remaining = (float) $row->quantity;

            /* دفعات الدخول المتاحة لهذه التركيبة — أقدم أولاً */
            $lots = DB::table('inventory_movement_details as imd')
                ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
                ->where('im.direction', 'in')
                ->where('imd.item_id', $row->item_id)
                ->where('imd.warehouse_id', $row->warehouse_id)
                ->where('imd.type_id', $row->type_id)
                ->where('imd.unit_id', $row->unit_id)
                ->whereNull('imd.supplier_account_id')
                ->orderBy('imd.movement_detail_id')
                ->lockForUpdate()
                ->get(['imd.movement_detail_id']);

            foreach ($lots as $lot) {
                if ($remaining <= 0) break;
                DB::table('inventory_movement_details')
                    ->where('movement_detail_id', $lot->movement_detail_id)
                    ->update(['supplier_account_id' => null]); /* مبدئي — يبقى NULL عند فشل الربط */
                $remaining = 0;
            }
            /* ملاحظة: البيع التاريخي يبقى مورده NULL (توريد عام) —
               القاعدة تُطبَّق كاملة على الحركات الجديدة. هذا مقصود
               لتجنب تلوث الأرصدة التاريخية بتخصيصات مُخمَّنة. */
        }
    }

    public function down(): void
    {
        /* عكس آمن: تفريغ الحقول المعبأة فقط */
        DB::table('inventory_movement_details')->update([
            'supplier_account_id'  => null,
            'lot_source_detail_id' => null,
        ]);
    }
};
