<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    /**
     * إنشاء فاتورة شراء جديدة
     */
    public function create(Request $request): PurchaseInvoice
    {
        return DB::transaction(function () use ($request) {

            $nextNumber = $this->nextInvoiceNumber();

            $data = $this->headerData($request);
            $data['invoice_number'] = (string) $nextNumber;

            $invoice = PurchaseInvoice::create($data);

            $this->saveDetails($invoice, $request->input('details', []));

            $this->recalculateTotals($invoice);

            $invoice->load('details');
            $this->syncInventoryMovement($invoice);

            return $invoice;
        });
    }

    /**
     * تحديث فاتورة شراء
     */
    public function update(int $id, Request $request): PurchaseInvoice
    {
        return DB::transaction(function () use ($id, $request) {

            $invoice = PurchaseInvoice::findOrFail($id);

            $data = $this->headerData($request);
            unset($data['invoice_number']);
            $invoice->update($data);

            $invoice->details()->delete();
            $this->saveDetails($invoice, $request->input('details', []));

            $this->recalculateTotals($invoice);

            $invoice->load('details');
            $this->syncInventoryMovement($invoice);

            return $invoice;
        });
    }

    /**
     * حذف فاتورة شراء
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {

            $invoice = PurchaseInvoice::findOrFail($id);

            $this->deleteInventoryMovement($invoice);

            $invoice->details()->delete();
            $invoice->delete();
        });
    }

    // =====================================================
    // المزامنة مع المخزون
    // =====================================================

    /**
     * إنشاء / إعادة إنشاء حركة المخزون المرتبطة بالفاتورة
     */
    public function syncInventoryMovement(PurchaseInvoice $invoice): void
    {
        // 1. حذف أي حركة قديمة
        $this->deleteInventoryMovement($invoice);

        // 2. توليد رقم الحركة
        $lastMovement = InventoryMovement::lockForUpdate()
            ->orderBy('movement_id', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? ((int) $lastMovement->display_id + 1)
            : 1;

        // 3. إنشاء رأس الحركة
        $movement = InventoryMovement::create([
            'display_id'      => (string) $nextNumber,
            'movement_type'   => InventoryMovement::TYPE_PURCHASE,
            'direction'       => InventoryMovement::directionForType(
                                    InventoryMovement::TYPE_PURCHASE
                                 ),
            'movement_date'   => $invoice->invoice_date,
            'document_number' => $invoice->invoice_number,
            'warehouse_id'    => $invoice->warehouse_id,
            'statement'       => 'توريد تلقائي من فاتورة الشراء رقم '
                                    . $invoice->invoice_number,
            'source_type'     => InventoryMovement::SOURCE_PURCHASE_INVOICE,
            'source_id'       => $invoice->purchase_invoice_id,
            'total'           => 0,
        ]);

        $rate = (float) ($invoice->exchange_rate ?: 1);

        // 4. التكاليف الإضافية بعملة الفاتورة
        $extraCostsFC = (float) $invoice->expenses
                      + (float) $invoice->tax_cost
                      + (float) $invoice->transportation
                      + (float) $invoice->other_cost;

        // 5. حساب مجموع الصافي (لقاعدة التوزيع)
        $detailsCollection = $invoice->details;
        $totalNetFC = 0;

        foreach ($detailsCollection as $d) {
            $totalNetFC += max(
                0,
                ((float) $d->quantity * (float) $d->price) - (float) $d->discount
            );
        }

        $movementTotalBC = 0;

        // 6. إنشاء التفاصيل
        foreach ($detailsCollection as $detail) {
            $quantity   = (float) $detail->quantity;
            $priceFC    = (float) $detail->price;
            $discountFC = (float) $detail->discount;

            // الصافي بعد الخصم (بعملة الفاتورة)
            $lineNetFC = max(0, ($quantity * $priceFC) - $discountFC);

            // توزيع التكاليف الإضافية نسبيًا
            $shareFC = 0;
            if ($totalNetFC > 0 && $extraCostsFC > 0) {
                $shareFC = ($lineNetFC / $totalNetFC) * $extraCostsFC;
            }

            // التكلفة الواصلة (Landed Cost) بعملة الفاتورة
            $landedFC   = $lineNetFC + $shareFC;
            $unitCostFC = $quantity > 0 ? ($landedFC / $quantity) : 0;

            // التحويل إلى العملة الأساسية
            $unitCostBC  = $unitCostFC * $rate;
            $lineTotalBC = $landedFC   * $rate;

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $invoice->warehouse_id,
                'quantity'     => $quantity,
                'unit_cost'    => $unitCostBC,
                'min_price'    => null,
                'max_price'    => null,
                'sale_price'   => null,
                'total'        => $lineTotalBC,
            ]);

            $movementTotalBC += $lineTotalBC;
        }

        $movement->total = $movementTotalBC;
        $movement->save();
    }

    /**
     * حذف حركة المخزون المرتبطة بفاتورة
     */
    public function deleteInventoryMovement(PurchaseInvoice $invoice): void
    {
        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_PURCHASE_INVOICE
            )
            ->where('source_id', $invoice->purchase_invoice_id)
            ->get();

        foreach ($movements as $movement) {
            $movement->details()->delete();
            $movement->delete();
        }
    }

    // =====================================================
    // دوال مساعدة داخلية
    // =====================================================

    private function nextInvoiceNumber(): int
    {
        $last = PurchaseInvoice::lockForUpdate()
            ->orderBy('purchase_invoice_id', 'desc')
            ->first();

        return $last ? ((int) $last->invoice_number + 1) : 1;
    }

    /**
     * تجهيز بيانات رأس الفاتورة
     *
     * ✅ إصلاح: كان الـ ternary موضوعًا في السطر الخطأ
     *    (على transportation بدلًا من other_cost_description)
     */
    private function headerData(Request $request): array
    {
        $otherCost = (float) $request->input('other_cost', 0);

        return [
            'invoice_number'         => $request->input('invoice_number'),
            'invoice_date'           => $request->input('invoice_date'),
            'account_id'             => $request->input('account_id'),
            'payment_account_id'     => $request->input('payment_account_id'),
            'coin_id'                => $request->input('coin_id'),
            'warehouse_id'           => $request->input('warehouse_id'),
            'exchange_rate'          => $request->input('exchange_rate', 1),
            'payment_method'         => $request->input('payment_method'),
            'expenses'               => $request->input('expenses', 0),
            'tax_cost'               => $request->input('tax_cost', 0),
            'transportation'         => $request->input('transportation', 0),

            // ✅ التصحيح: other_cost_description يُنظَّف فقط إذا كانت other_cost > 0
            'other_cost'             => $otherCost,
            'other_cost_description' => $otherCost > 0
                ? $request->input('other_cost_description')
                : null,

            'statement'              => $request->input('statement'),
            'reference'              => $request->input('reference'),
        ];
    }

    private function saveDetails(PurchaseInvoice $invoice, array $details): void
    {
        foreach ($details as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $price    = (float) ($row['price'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $total    = max(0, ($quantity * $price) - $discount);

            PurchaseInvoiceDetail::create([
                'purchase_invoice_id' => $invoice->purchase_invoice_id,
                'item_id'             => $row['item_id'],
                'type_id'             => $row['type_id'] ?? null,
                'unit_id'             => $row['unit_id'] ?? null,
                'code'                => $row['code'] ?? null,
                'quantity'            => $quantity,
                'price'               => $price,
                'discount'            => $discount,
                'total'               => $total,
            ]);
        }
    }

    /**
     * إعادة حساب الإجماليات من التفاصيل
     *
     * - items_total / discount_total  → أعمدة حقيقية
     * - total_in_invoice_currency     → Accessor (لا يُخزَّن)
     * - total_in_base_currency        → عمود حقيقي ✅
     */
    private function recalculateTotals(PurchaseInvoice $invoice): void
    {
        $details = $invoice->details()->get();

        $itemsTotal    = 0;
        $discountTotal = 0;

        foreach ($details as $d) {
            $itemsTotal    += (float) $d->quantity * (float) $d->price;
            $discountTotal += (float) $d->discount;
        }

        $net = max(0, $itemsTotal - $discountTotal);

        $invoice->items_total    = $itemsTotal;
        $invoice->discount_total = $discountTotal;

        // حساب الإجمالي بعملة الفاتورة (محليًا — لا يُحفظ)
        $totalInInvoiceCurrency = $net
            + (float) $invoice->expenses
            + (float) $invoice->tax_cost
            + (float) $invoice->transportation
            + (float) $invoice->other_cost;

        $rate = (float) ($invoice->exchange_rate ?: 1);

        // ✅ حفظ الإجمالي بالعملة الأساسية فقط (العمود المخزَّن)
        $invoice->total_in_base_currency = $totalInInvoiceCurrency * $rate;
        $invoice->save();
    }
}