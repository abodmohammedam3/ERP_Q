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

            // 1. توليد رقم الفاتورة على الخادم بشكل آمن
            $nextNumber = $this->nextInvoiceNumber();

            $data = $this->headerData($request);
            $data['invoice_number'] = (string) $nextNumber;

            // 2. إنشاء الرأس
            $invoice = PurchaseInvoice::create($data);

            // 3. حفظ التفاصيل
            $this->saveDetails($invoice, $request->input('details', []));

            // 4. إعادة حساب الإجماليات
            $this->recalculateTotals($invoice);

            // 5. مزامنة حركة المخزون
            $invoice->load('details');
            $this->syncInventoryMovement($invoice);

            // 6. (لاحقًا) مزامنة القيود المحاسبية
            // $this->syncAccountingEntry($invoice);

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

            // 1. تحديث الرأس (بدون رقم الفاتورة)
            $data = $this->headerData($request);
            unset($data['invoice_number']);
            $invoice->update($data);

            // 2. حذف التفاصيل القديمة وإعادة إضافتها
            $invoice->details()->delete();
            $this->saveDetails($invoice, $request->input('details', []));

            // 3. إعادة حساب الإجماليات
            $this->recalculateTotals($invoice);

            // 4. مزامنة حركة المخزون (حذف القديمة + إنشاء جديدة)
            $invoice->load('details');
            $this->syncInventoryMovement($invoice);

            // 5. (لاحقًا) مزامنة القيود المحاسبية

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

            // 1. حذف حركة المخزون المرتبطة
            $this->deleteInventoryMovement($invoice);

            // 2. (لاحقًا) حذف القيود المحاسبية

            // 3. حذف التفاصيل + الرأس
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
        // 1. حذف أي حركة قديمة (حالة التعديل)
        $this->deleteInventoryMovement($invoice);

        // 2. توليد رقم الحركة بشكل آمن
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

        // 4. إنشاء التفاصيل
        $movementTotal = 0;

        foreach ($invoice->details as $detail) {
            $quantity  = (float) $detail->quantity;
            $unitCost  = (float) $detail->price;
            $lineTotal = $quantity * $unitCost;

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $invoice->warehouse_id,
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost,
                'min_price'    => null,
                'max_price'    => null,
                'sale_price'   => null,
                'total'        => $lineTotal,
            ]);

            $movementTotal += $lineTotal;
        }

        // 5. تحديث إجمالي الحركة
        $movement->total = $movementTotal;
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

    /**
     * توليد رقم الفاتورة التالي بشكل آمن (داخل Transaction)
     */
    private function nextInvoiceNumber(): int
    {
        $last = PurchaseInvoice::lockForUpdate()
            ->orderBy('purchase_invoice_id', 'desc')
            ->first();

        return $last ? ((int) $last->invoice_number + 1) : 1;
    }

    /**
     * تجهيز بيانات الرأس
     */
    private function headerData(Request $request): array
    {
        return [
            'invoice_number'      => $request->input('invoice_number'),
            'invoice_date'        => $request->input('invoice_date'),
            'account_id'          => $request->input('account_id'),
            'payment_account_id'  => $request->input('payment_account_id'),
            'coin_id'             => $request->input('coin_id'),
            'warehouse_id'        => $request->input('warehouse_id'),
            'exchange_rate'       => $request->input('exchange_rate', 1),
            'payment_method'      => $request->input('payment_method'),
            'expenses'            => $request->input('expenses', 0),
            'tax_cost'            => $request->input('tax_cost', 0),
            'transportation'      => $request->input('transportation', 0),
            'other_cost'          => $request->input('other_cost', 0),
            'other_cost_description' => $request->input('other_cost_description'),
            'statement'           => $request->input('statement'),
            'reference'           => $request->input('reference'),
        ];
    }

    /**
     * حفظ التفاصيل
     */
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

        $invoice->items_total    = $itemsTotal;
        $invoice->discount_total = $discountTotal;
        $invoice->save();
    }
}