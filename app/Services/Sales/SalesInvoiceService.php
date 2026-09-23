<?php

namespace App\Services\Sales;

use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    /**
     * @var InventoryService
     */
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // =====================================================
    // CRUD
    // =====================================================

    public function create(Request $request): SalesInvoice
    {
        return DB::transaction(function () use ($request) {

            $this->validateStockAvailability($request->input('details', []));

            $nextNumber = $this->nextInvoiceNumber();

            $data = $this->headerData($request);
            $data['invoice_number'] = (string) $nextNumber;

            $invoice = SalesInvoice::create($data);

            $this->saveDetails($invoice, $request->input('details', []));
            $this->recalculateTotals($invoice);

            $invoice->load('details');
            $this->syncInventoryMovement($invoice);

            return $invoice;
        });
    }

    public function update(int $id, Request $request): SalesInvoice
    {
        return DB::transaction(function () use ($id, $request) {

            $invoice = SalesInvoice::findOrFail($id);

            $this->deleteInventoryMovement($invoice);

            $this->validateStockAvailability($request->input('details', []));

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

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {

            $invoice = SalesInvoice::findOrFail($id);

            $this->deleteInventoryMovement($invoice);

            $invoice->details()->delete();
            $invoice->delete();
        });
    }

    // =====================================================
    // المخزون
    // =====================================================

    /**
     * التحقق من كفاية الرصيد
     *
     * ✅ unit-aware: يُمرِّر unit_id إلى availableQuantity
     *    لضمان فحص الرصيد في الوحدة الصحيحة فقط (حبة/كيلو).
     */
    protected function validateStockAvailability(array $details): void
    {
        $errors = [];

        foreach ($details as $i => $row) {
            $itemId      = $row['item_id'] ?? null;
            $warehouseId = $row['warehouse_id'] ?? null;
            $unitId      = $row['unit_id'] ?? null;
            $qty         = (float) ($row['quantity'] ?? 0);

            if (!$itemId || !$warehouseId || $qty <= 0) continue;

            $available = $this->inventoryService->availableQuantity(
                (int) $itemId,
                (int) $warehouseId,
                $unitId !== null ? (int) $unitId : null   // ✅ جديد
            );

            if ($available < $qty) {
                $errors["details.{$i}.quantity"] =
                    "الكمية المتوفرة {$available} أقل من المطلوب {$qty}";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function syncInventoryMovement(SalesInvoice $invoice): void
    {
        $this->deleteInventoryMovement($invoice);

        $lastMovement = InventoryMovement::lockForUpdate()
            ->orderBy('movement_id', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? ((int) $lastMovement->display_id + 1)
            : 1;

        $firstDetail     = $invoice->details->first();
        $headerWarehouse = $firstDetail ? $firstDetail->warehouse_id : null;

        if (!$headerWarehouse) {
            throw new \RuntimeException('لا يمكن إنشاء حركة مخزون بدون مخزن');
        }

        $movement = InventoryMovement::create([
            'display_id'      => (string) $nextNumber,
            'movement_type'   => InventoryMovement::TYPE_SALE,
            'direction'       => InventoryMovement::directionForType(
                                    InventoryMovement::TYPE_SALE
                                 ),
            'movement_date'   => $invoice->invoice_date,
            'document_number' => $invoice->invoice_number,
            'warehouse_id'    => $headerWarehouse,
            'statement'       => 'صرف تلقائي من فاتورة البيع رقم '
                                    . $invoice->invoice_number,
            'source_type'     => InventoryMovement::SOURCE_SALES_INVOICE,
            'source_id'       => $invoice->sales_invoice_id,
            'total'           => 0,
        ]);

        $movementTotal = 0;

        foreach ($invoice->details as $detail) {
            $quantity  = (float) $detail->quantity;
            $unitCost  = (float) $detail->cost_price;
            $lineTotal = $quantity * $unitCost;

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $detail->warehouse_id,
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost,
                'min_price'    => null,
                'max_price'    => null,
                'sale_price'   => $detail->price,
                'total'        => $lineTotal,
            ]);

            $movementTotal += $lineTotal;
        }

        $movement->total = $movementTotal;
        $movement->save();

        $movement->load('details');
        $this->inventoryService->applyMovement($movement);
    }

    public function deleteInventoryMovement(SalesInvoice $invoice): void
    {
        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_SALES_INVOICE
            )
            ->where('source_id', $invoice->sales_invoice_id)
            ->with('details')
            ->get();

        foreach ($movements as $movement) {
            $this->inventoryService->reverseMovement($movement);

            $movement->details()->delete();
            $movement->delete();
        }
    }

    // =====================================================
    // دوال مساعدة داخلية
    // =====================================================

    protected function nextInvoiceNumber(): int
    {
        $last = SalesInvoice::lockForUpdate()
            ->orderBy('sales_invoice_id', 'desc')
            ->first();

        return $last ? ((int) $last->invoice_number + 1) : 1;
    }

    protected function headerData(Request $request): array
    {
        return [
            'invoice_number'     => $request->input('invoice_number'),
            'invoice_date'       => $request->input('invoice_date'),
            'account_id'         => $request->input('account_id'),
            'payment_account_id' => $request->input('payment_account_id'),
            'coin_id'            => $request->input('coin_id'),
            'exchange_rate'      => $request->input('exchange_rate', 1),
            'payment_method'     => $request->input('payment_method'),
            'statement'          => $request->input('statement'),
            'reference'          => $request->input('reference'),
        ];
    }

    /**
     * حفظ تفاصيل الفاتورة
     *
     * ✅ Option C — مقارنة ذكية لـ cost_price:
     *   - إذا الواجهة أرسلت 0 → استخدم serverCost
     *   - إذا serverCost = 0 → استخدم uiCost
     *   - إذا الاثنان > 0 → قارن:
     *       - الفرق ≤ 10% → استخدم uiCost (تعديل مقصود)
     *       - الفرق > 10% → استخدم serverCost (حماية من التلاعب)
     */
    protected function saveDetails(SalesInvoice $invoice, array $details): void
    {
        foreach ($details as $row) {
            $itemId      = (int) $row['item_id'];
            $warehouseId = (int) $row['warehouse_id'];
            $unitId      = isset($row['unit_id']) && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : null;

            $quantity = (float) ($row['quantity'] ?? 0);
            $price    = (float) ($row['price'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $total    = max(0, ($quantity * $price) - $discount);

            // ✅ جلب تكلفة الوحدة من السيرفر (unit-aware)
            $serverCost = $this->inventoryService->lastCost(
                $itemId,
                $warehouseId,
                $unitId
            );

            $uiCost = (float) ($row['cost_price'] ?? 0);

            // ✅ Option C — مقارنة ذكية
            if ($uiCost <= 0) {
                $costPrice = $serverCost;
            } elseif ($serverCost <= 0) {
                $costPrice = $uiCost;
            } else {
                $diffRatio = abs($uiCost - $serverCost) / $serverCost;
                $costPrice = $diffRatio > 0.1 ? $serverCost : $uiCost;
            }

            SalesInvoiceDetail::create([
                'sales_invoice_id' => $invoice->sales_invoice_id,
                'item_id'          => $itemId,
                'type_id'          => $row['type_id'] ?? null,
                'unit_id'          => $unitId,
                'warehouse_id'     => $warehouseId,
                'code'             => $row['code'] ?? null,
                'quantity'         => $quantity,
                'price'            => $price,
                'cost_price'       => $costPrice,
                'discount'         => $discount,
                'total'            => $total,
            ]);
        }
    }

    protected function recalculateTotals(SalesInvoice $invoice): void
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