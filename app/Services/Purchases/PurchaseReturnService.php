<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\PurchaseReturnDetail;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function availableForReturn(int $invoiceDetailId, ?int $exceptReturnId = null): float
    {
        $invoiceDetail = PurchaseInvoiceDetail::find($invoiceDetailId);

        if (!$invoiceDetail) {
            return 0.0;
        }

        $alreadyReturned = PurchaseReturnDetail::where('purchase_invoice_detail_id', $invoiceDetailId)
            ->when($exceptReturnId, function ($query, $exceptId) {
                $query->where('purchase_return_id', '!=', $exceptId);
            })
            ->sum('quantity');

        return max(0.0, (float) $invoiceDetail->quantity - (float) $alreadyReturned);
    }

    public function create(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {

            $originalInvoiceId = (int) ($data['original_purchase_invoice_id'] ?? 0);

            $this->lockAndValidateInvoice(
                $originalInvoiceId,
                $data['account_id'] ?? null,
                $data['coin_id'] ?? null,
                $data['warehouse_id'] ?? null
            );

            $details = $data['details'] ?? [];

            $this->validateReturnQuantities($originalInvoiceId, $details);

            $this->lockStockRows(
                $details,
                $data['warehouse_id'] ?? null
            );

            $this->validateStockAvailability(
                $details,
                $data['warehouse_id'] ?? null
            );

            $nextNumber = $this->nextReturnNumber();

            $header = $this->headerData($data);
            $header['return_number'] = (string) $nextNumber;

            $purchaseReturn = PurchaseReturn::create($header);

            $this->saveDetails($purchaseReturn, $details);
            $this->recalculateTotals($purchaseReturn);

            $purchaseReturn->load('details');
            $this->syncInventoryMovement($purchaseReturn);

            return $purchaseReturn;
        });
    }

    public function update(int $id, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($id, $data) {

            $originalInvoiceId = (int) ($data['original_purchase_invoice_id'] ?? 0);

            $this->lockAndValidateInvoice(
                $originalInvoiceId,
                $data['account_id'] ?? null,
                $data['coin_id'] ?? null,
                $data['warehouse_id'] ?? null
            );

            $purchaseReturn = PurchaseReturn::lockForUpdate()->findOrFail($id);

            $details = $data['details'] ?? [];

            $this->validateReturnQuantities($originalInvoiceId, $details, $purchaseReturn->purchase_return_id);

            $this->lockStockRows(
                $details,
                $data['warehouse_id'] ?? null
            );

            $this->validateStockAvailability(
                $details,
                $data['warehouse_id'] ?? null,
                $purchaseReturn->purchase_return_id
            );

            $header = $this->headerData($data);
            unset($header['return_number']);
            $purchaseReturn->update($header);

            $purchaseReturn->details()->delete();
            $this->saveDetails($purchaseReturn, $details);
            $this->recalculateTotals($purchaseReturn);

            $purchaseReturn->load('details');
            $this->syncInventoryMovement($purchaseReturn);

            return $purchaseReturn;
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $purchaseReturn = PurchaseReturn::lockForUpdate()->findOrFail($id);

            $this->deleteInventoryMovement($purchaseReturn);

            $purchaseReturn->details()->delete();
            $purchaseReturn->delete();
        });
    }

    // =====================================================
    // القفل + التحقق من الرأس
    // =====================================================

    protected function lockAndValidateInvoice(
        int $invoiceId,
        $accountId,
        $coinId,
        $warehouseId = null
    ): void {
        $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($invoiceId);

        if ((int) $accountId !== (int) $invoice->account_id) {
            throw ValidationException::withMessages([
                'account_id' => 'حساب المورد لا يطابق الفاتورة الأصلية',
            ]);
        }

        if ((int) $coinId !== (int) $invoice->coin_id) {
            throw ValidationException::withMessages([
                'coin_id' => 'العملة لا تطابق الفاتورة الأصلية',
            ]);
        }

        if ($warehouseId !== null && (int) $warehouseId !== (int) $invoice->warehouse_id) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'المستودع لا يطابق المستودع الأصلي للفاتورة',
            ]);
        }
    }

    // =====================================================
    // التحقق من التفاصيل
    // =====================================================

    protected function validateReturnQuantities(
        int $originalInvoiceId,
        array $details,
        ?int $exceptReturnId = null
    ): void {
        $errors = [];

        foreach ($details as $i => $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) {
                continue;
            }

            $detail = PurchaseInvoiceDetail::find($detailId);

            if (!$detail) {
                $errors["details.{$i}.purchase_invoice_detail_id"] = "سطر الفاتورة #{$detailId} غير موجود";
                continue;
            }

            if ((int) $detail->purchase_invoice_id !== $originalInvoiceId) {
                $errors["details.{$i}.purchase_invoice_detail_id"] =
                    "سطر الفاتورة #{$detailId} لا ينتمي للفاتورة الأصلية";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $grouped = [];
        foreach ($details as $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) {
                continue;
            }

            $grouped[$detailId] = ($grouped[$detailId] ?? 0) + $qty;
        }

        foreach ($grouped as $detailId => $totalQty) {
            $available = $this->availableForReturn($detailId, $exceptReturnId);

            if ($totalQty > $available) {
                $errors["details"] =
                    "الكمية الإجمالية للسطر #{$detailId} ({$totalQty}) أكبر من المتاح ({$available})";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    // =====================================================
    // قفل صفوف المخزون
    // =====================================================

    protected function lockStockRows(array $details, $warehouseId): void
    {
        if (!$warehouseId) {
            return;
        }

        $pairs = [];
        foreach ($details as $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            if ($detailId <= 0) continue;

            $detail = PurchaseInvoiceDetail::find($detailId);
            if (!$detail) continue;

            $key = $detail->item_id . '|' . ($detail->unit_id ?? 0);
            $pairs[$key] = [
                'item_id' => (int) $detail->item_id,
                'unit_id' => $detail->unit_id ? (int) $detail->unit_id : null,
            ];
        }

        ksort($pairs);

        foreach ($pairs as $pair) {
            $query = DB::table('inventory_movement_details')
                ->where('item_id', $pair['item_id'])
                ->where('warehouse_id', (int) $warehouseId);

            if ($pair['unit_id']) {
                $query->where('unit_id', $pair['unit_id']);
            }

            $query->select('movement_detail_id')
                  ->lockForUpdate()
                  ->get();
        }
    }

    // =====================================================
    // فحص الرصيد الفعلي
    // =====================================================

    protected function validateStockAvailability(
        array $details,
        $warehouseId,
        ?int $exceptReturnId = null
    ): void {
        if (!$warehouseId) {
            return;
        }

        $errors = [];
        $stockNeeds = [];

        foreach ($details as $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) {
                continue;
            }

            $detail = PurchaseInvoiceDetail::find($detailId);
            if (!$detail) {
                continue;
            }

            $key = $detail->item_id . '|' . ($detail->unit_id ?? 0);
            $stockNeeds[$key] = ($stockNeeds[$key] ?? 0) + $qty;
        }

        foreach ($stockNeeds as $key => $neededQty) {
            [$itemId, $unitId] = explode('|', $key);
            $itemId = (int) $itemId;
            $unitId = (int) $unitId;

            $stock = $this->inventoryService->availableQuantity(
                $itemId,
                (int) $warehouseId,
                $unitId > 0 ? $unitId : null
            );

            if ($exceptReturnId) {
                $currentReturnedQty = PurchaseReturnDetail::where('purchase_return_id', $exceptReturnId)
                    ->where('item_id', $itemId)
                    ->where('warehouse_id', (int) $warehouseId)
                    ->when($unitId > 0, function ($q) use ($unitId) {
                        $q->where('unit_id', $unitId);
                    })
                    ->sum('quantity');

                $stock += (float) $currentReturnedQty;
            }

            if ($neededQty > $stock) {
                $errors["details"] =
                    "الكمية المطلوبة للصنف #{$itemId} ({$neededQty}) أكبر من الرصيد الفعلي ({$stock})";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    // =====================================================
    // المخزون
    // =====================================================

    public function syncInventoryMovement(PurchaseReturn $purchaseReturn): void
    {
        $this->deleteInventoryMovement($purchaseReturn);

        $lastMovement = InventoryMovement::lockForUpdate()
            ->orderBy('movement_id', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? ((int) $lastMovement->display_id + 1)
            : 1;

        $firstDetail     = $purchaseReturn->details->first();
        $headerWarehouse = $purchaseReturn->warehouse_id
            ?: ($firstDetail ? $firstDetail->warehouse_id : null);

        if (!$headerWarehouse) {
            throw new \RuntimeException('لا يمكن إنشاء حركة مخزون بدون مخزن');
        }

        $movement = InventoryMovement::create([
            'display_id'      => (string) $nextNumber,
            'movement_type'   => InventoryMovement::TYPE_PURCHASE_RETURN,
            'direction'       => InventoryMovement::directionForType(
                                    InventoryMovement::TYPE_PURCHASE_RETURN
                                 ),
            'movement_date'   => $purchaseReturn->return_date,
            'document_number' => $purchaseReturn->return_number,
            'warehouse_id'    => $headerWarehouse,
            'statement'       => 'صرف تلقائي من مرتجع شراء رقم '
                                    . $purchaseReturn->return_number,
            'source_type'     => InventoryMovement::SOURCE_PURCHASE_RETURN,
            'source_id'       => $purchaseReturn->purchase_return_id,
            'total'           => 0,
        ]);

        $movementTotal = 0;

        foreach ($purchaseReturn->details as $detail) {
            $quantity  = (float) $detail->quantity;
            $unitCost  = (float) $detail->unit_cost;
            $lineTotal = $quantity * $unitCost;

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $detail->warehouse_id ?: $headerWarehouse,
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost,
                'min_price'    => null,
                'max_price'    => null,
                'sale_price'   => null,
                'total'        => $lineTotal,
            ]);

            $movementTotal += $lineTotal;
        }

        $movement->total = $movementTotal;
        $movement->save();

        $movement->load('details');
        $this->inventoryService->applyMovement($movement);
    }

    public function deleteInventoryMovement(PurchaseReturn $purchaseReturn): void
    {
        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_PURCHASE_RETURN
            )
            ->where('source_id', $purchaseReturn->purchase_return_id)
            ->with('details')
            ->get();

        foreach ($movements as $movement) {
            $this->inventoryService->reverseMovement($movement);

            $movement->details()->delete();
            $movement->delete();
        }
    }

    // =====================================================
    // دوال مساعدة
    // =====================================================

    protected function nextReturnNumber(): int
    {
        $last = PurchaseReturn::lockForUpdate()
            ->orderBy('purchase_return_id', 'desc')
            ->first();

        return $last ? ((int) $last->return_number + 1) : 1;
    }

    protected function headerData(array $data): array
    {
        return [
            'return_number'                => $data['return_number'] ?? null,
            'return_date'                  => $data['return_date'] ?? null,
            'original_purchase_invoice_id' => $data['original_purchase_invoice_id'] ?? null,
            'account_id'                   => $data['account_id'] ?? null,
            'payment_account_id'           => $data['payment_account_id'] ?? null,
            'coin_id'                      => $data['coin_id'] ?? null,
            'warehouse_id'                 => $data['warehouse_id'] ?? null,
            'exchange_rate'                => $data['exchange_rate'] ?? 1,
            'payment_method'               => $data['payment_method'] ?? null,
            'statement'                    => $data['statement'] ?? null,
            'reference'                    => $data['reference'] ?? null,
        ];
    }

    protected function saveDetails(PurchaseReturn $purchaseReturn, array $details): void
    {
        foreach ($details as $row) {
            $invoiceDetailId = (int) $row['purchase_invoice_detail_id'];
            $invoiceDetail   = PurchaseInvoiceDetail::findOrFail($invoiceDetailId);

            $quantity = (float) ($row['quantity'] ?? 0);
            $price    = (float) ($row['price'] ?? $invoiceDetail->price);
            $discount = (float) ($row['discount'] ?? 0);
            $total    = max(0, ($quantity * $price) - $discount);

            $unitCost = $this->getLandedCostFromMovement($invoiceDetail);

            PurchaseReturnDetail::create([
                'purchase_return_id'         => $purchaseReturn->purchase_return_id,
                'purchase_invoice_detail_id' => $invoiceDetailId,
                'item_id'                    => $invoiceDetail->item_id,
                'type_id'                    => $invoiceDetail->type_id,
                'unit_id'                    => $invoiceDetail->unit_id,
                'warehouse_id'               => $purchaseReturn->warehouse_id,
                'code'                       => $invoiceDetail->code,
                'quantity'                   => $quantity,
                'price'                      => $price,
                'unit_cost'                  => $unitCost,
                'discount'                   => $discount,
                'total'                      => $total,
            ]);
        }
    }

    protected function getLandedCostFromMovement(PurchaseInvoiceDetail $invoiceDetail): float
    {
        $unitCost = DB::table('inventory_movement_details')
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movements.source_type', InventoryMovement::SOURCE_PURCHASE_INVOICE)
            ->where('inventory_movements.source_id', $invoiceDetail->purchase_invoice_id)
            ->where('inventory_movement_details.item_id', $invoiceDetail->item_id)
            ->when($invoiceDetail->type_id, function ($q) use ($invoiceDetail) {
                $q->where('inventory_movement_details.type_id', $invoiceDetail->type_id);
            })
            ->when($invoiceDetail->unit_id, function ($q) use ($invoiceDetail) {
                $q->where('inventory_movement_details.unit_id', $invoiceDetail->unit_id);
            })
            ->orderBy('inventory_movement_details.movement_detail_id', 'desc')
            ->value('inventory_movement_details.unit_cost');

        return (float) ($unitCost !== null && $unitCost > 0
            ? $unitCost
            : $invoiceDetail->price);
    }

    protected function recalculateTotals(PurchaseReturn $purchaseReturn): void
    {
        $details = $purchaseReturn->details()->get();

        $itemsTotal    = 0;
        $discountTotal = 0;

        foreach ($details as $d) {
            $itemsTotal    += (float) $d->quantity * (float) $d->price;
            $discountTotal += (float) $d->discount;
        }

        $purchaseReturn->items_total    = $itemsTotal;
        $purchaseReturn->discount_total = $discountTotal;
        $purchaseReturn->save();
    }
}