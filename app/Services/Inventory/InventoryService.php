<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    // =====================================================
    // قفل صفوف المخزون
    // =====================================================
    public function lockStockRows(array $details): void
    {
        $pairs = [];

        foreach ($details as $row) {
            $itemId      = (int) ($row['item_id'] ?? 0);
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);
            $unitId      = isset($row['unit_id']) && $row['unit_id'] !== null ? (int) $row['unit_id'] : null;
            $supplierId  = !empty($row['supplier_account_id']) ? (int) $row['supplier_account_id'] : null;

            if (!$itemId || !$warehouseId) continue;

            $key = $itemId . '|' . $warehouseId . '|' . ($unitId ?? 0) . '|' . ($supplierId ?? 0);
            $pairs[$key] = [
                'item_id'      => $itemId,
                'warehouse_id' => $warehouseId,
                'unit_id'      => $unitId,
                'supplier_account_id' => $supplierId,
            ];
        }

        if (empty($pairs)) return;

        ksort($pairs);

        DB::table('inventory_movement_details')
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $pair) {
                    $q->orWhere(function ($sub) use ($pair) {
                        $sub->where('item_id', $pair['item_id'])
                            ->where('warehouse_id', $pair['warehouse_id']);
                        if ($pair['unit_id']) $sub->where('unit_id', $pair['unit_id']);
                        if ($pair['supplier_account_id'] !== null) {
                            $sub->where('supplier_account_id', $pair['supplier_account_id']);
                        }
                    });
                }
            })
            ->lockForUpdate()
            ->pluck('movement_detail_id');
    }

    // =====================================================
    // القراءة
    // =====================================================
    public function availableQuantity(int $itemId, int $warehouseId, ?int $unitId = null): float
    {
        $in = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, fn($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->where('inventory_movements.direction', 'in')
            ->sum('inventory_movement_details.quantity');

        $out = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, fn($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->where('inventory_movements.direction', 'out')
            ->sum('inventory_movement_details.quantity');

        return (float) $in - (float) $out;
    }

    public function isAvailable(int $itemId, int $warehouseId, float $qty, ?int $unitId = null): bool
    {
        return $this->availableQuantity($itemId, $warehouseId, $unitId) >= $qty;
    }

    public function availableQuantityForSupplier(int $itemId, int $warehouseId, ?int $unitId, ?int $supplierAccountId): float
    {
        $in = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, fn($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->when(
                $supplierAccountId === null,
                fn($q) => $q->whereNull('inventory_movement_details.supplier_account_id'),
                fn($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierAccountId)
            )
            ->where('inventory_movements.direction', 'in')
            ->sum('inventory_movement_details.quantity');

        $out = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, fn($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->when(
                $supplierAccountId === null,
                fn($q) => $q->whereNull('inventory_movement_details.supplier_account_id'),
                fn($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierAccountId)
            )
            ->where('inventory_movements.direction', 'out')
            ->sum('inventory_movement_details.quantity');

        return (float) $in - (float) $out;
    }

    public function lastCostForSupplier(int $itemId, int $warehouseId, ?int $unitId, ?int $supplierAccountId): float
    {
        $detail = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, fn($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->when(
                $supplierAccountId === null,
                fn($q) => $q->whereNull('inventory_movement_details.supplier_account_id'),
                fn($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierAccountId)
            )
            ->where('inventory_movements.direction', 'in')
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select('inventory_movement_details.unit_cost')
            ->first();

        return $detail ? (float) $detail->unit_cost : 0;
    }

    public function lastCost(int $itemId, int $warehouseId, ?int $unitId = null): float
    {
        $detail = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, fn($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->where('inventory_movements.direction', 'in')
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select('inventory_movement_details.unit_cost')
            ->first();

        return $detail ? (float) $detail->unit_cost : 0;
    }

    public function currentUnitCost(int $itemId, ?int $typeId, int $warehouseId, int $unitId): float
    {
        $query = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->where('inventory_movement_details.unit_id', $unitId)
            ->where('inventory_movements.direction', 'in');

        if ($typeId !== null) {
            $query->where('inventory_movement_details.type_id', $typeId);
        }

        $detail = $query
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select('inventory_movement_details.unit_cost')
            ->first();

        return $detail ? (float) $detail->unit_cost : 0;
    }

    public function lastPricing(int $itemId, int $warehouseId, ?int $unitId = null, ?int $supplierAccountId = null): array
    {
        $query = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, fn($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->when(
                $supplierAccountId !== null,
                fn($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierAccountId)
            )
            ->where('inventory_movements.direction', 'in')
            ->whereNotNull('inventory_movement_details.sale_price');

        $detail = $query
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select([
                'inventory_movement_details.sale_price',
                'inventory_movement_details.min_price',
                'inventory_movement_details.max_price',
            ])
            ->first();

        return [
            'sale_price' => $detail ? (float) $detail->sale_price : 0,
            'min_price'  => $detail ? (float) $detail->min_price  : 0,
            'max_price'  => $detail ? (float) $detail->max_price  : 0,
        ];
    }

    // =====================================================
    // ✅ الفرز — مع دعم batch_id (فاتورة محددة)
    // =====================================================
    public function sortInventory(array $data): array
    {
        return DB::transaction(function () use ($data) {

            $itemId       = (int) $data['item_id'];
            $typeId       = isset($data['type_id']) && $data['type_id'] !== null ? (int) $data['type_id'] : null;
            $warehouseId  = (int) $data['warehouse_id'];
            $inputUnitId  = (int) $data['input_unit_id'];
            $outputUnitId = (int) $data['output_unit_id'];
            $inputQty     = (float) $data['input_quantity'];
            $outputQty    = (float) $data['output_quantity'];
            $supplierId   = !empty($data['supplier_account_id']) ? (int) $data['supplier_account_id'] : null;
            $batchId      = !empty($data['batch_id']) ? (int) $data['batch_id'] : null;
            $movementDate = $data['movement_date'] ?? now()->format('Y-m-d');

            if ($inputQty <= 0) {
                throw ValidationException::withMessages(['input_quantity' => 'الكمية المفرزة يجب أن تكون أكبر من صفر']);
            }
            if ($outputQty <= 0) {
                throw ValidationException::withMessages(['output_quantity' => 'عدد الوحدات الناتجة يجب أن يكون أكبر من صفر']);
            }
            if ($inputUnitId === $outputUnitId) {
                throw ValidationException::withMessages(['output_unit_id' => 'وحدة المصدر ووحدة الناتج يجب أن تكونا مختلفتين']);
            }

            $this->lockStockRows([
                ['item_id' => $itemId, 'warehouse_id' => $warehouseId, 'unit_id' => $inputUnitId, 'supplier_account_id' => $supplierId],
                ['item_id' => $itemId, 'warehouse_id' => $warehouseId, 'unit_id' => $outputUnitId, 'supplier_account_id' => $supplierId],
            ]);

            if ($batchId !== null) {
                $sourceDetail = InventoryMovementDetail::query()
                    ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
                    ->where('inventory_movement_details.movement_detail_id', $batchId)
                    ->where('inventory_movement_details.item_id', $itemId)
                    ->where('inventory_movement_details.warehouse_id', $warehouseId)
                    ->where('inventory_movement_details.unit_id', $inputUnitId)
                    ->where('inventory_movements.direction', 'in')
                    ->select(
                        'inventory_movement_details.movement_detail_id',
                        'inventory_movement_details.supplier_account_id',
                        'inventory_movement_details.quantity as batch_quantity'
                    )
                    ->first();

                if (!$sourceDetail) {
                    throw ValidationException::withMessages([
                        'batch_id' => 'الدفعة المحددة غير موجودة أو لا تنتمي لهذا الصنف/المخزن/الوحدة',
                    ]);
                }

                $distributed = (float) DB::table('distribution_details')
                    ->where('movement_detail_id', $batchId)
                    ->sum('quantity');

                $sortedOut = (float) DB::table('inventory_movement_details as imd')
                    ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
                    ->where('imd.lot_source_detail_id', $batchId)
                    ->where('im.movement_type', InventoryMovement::TYPE_SORTING_OUT)
                    ->sum('imd.quantity');

                $batchAvailable = max(0, (float) $sourceDetail->batch_quantity - $distributed - $sortedOut);

                if ($batchAvailable < $inputQty) {
                    throw ValidationException::withMessages([
                        'input_quantity' => "الكمية المتاحة من هذه الدفعة ({$batchAvailable}) أقل من المطلوب ({$inputQty})",
                    ]);
                }
            } else {
                $available = $this->availableQuantityForSupplier($itemId, $warehouseId, $inputUnitId, $supplierId);
                if ($available < $inputQty) {
                    throw ValidationException::withMessages([
                        'input_quantity' => "الكمية المتوفرة من دفعة هذا المورد ({$available}) أقل من المطلوب ({$inputQty})",
                    ]);
                }

                $sourceDetail = InventoryMovementDetail::query()
                    ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
                    ->where('inventory_movement_details.item_id', $itemId)
                    ->where('inventory_movement_details.warehouse_id', $warehouseId)
                    ->where('inventory_movement_details.unit_id', $inputUnitId)
                    ->where('inventory_movements.direction', 'in')
                    ->when(
                        $supplierId !== null,
                        fn($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierId),
                        fn($q) => $q->whereNull('inventory_movement_details.supplier_account_id')
                    )
                    ->orderByDesc('inventory_movement_details.movement_detail_id')
                    ->select(
                        'inventory_movement_details.movement_detail_id',
                        'inventory_movement_details.supplier_account_id'
                    )
                    ->first();
            }

            $unitCostInput = $this->currentUnitCost($itemId, $typeId, $warehouseId, $inputUnitId);
            if ($unitCostInput <= 0) {
                throw ValidationException::withMessages([
                    'item_id' => 'لا يوجد سجل تكلفة صالح لهذا الصنف في الوحدة المحددة لهذا المورد',
                ]);
            }

            $sourceSupplierId = $sourceDetail?->supplier_account_id;
            $sourceDetailId   = $sourceDetail?->movement_detail_id;

            $inputTotal     = round($inputQty * $unitCostInput, 6);
            $unitCostOutput = $outputQty > 0 ? round($inputTotal / $outputQty, 6) : 0;
            $documentNumber = $this->generateSortingNumber();

            $lastMovement = InventoryMovement::lockForUpdate()->orderByDesc('movement_id')->first();
            $nextDisplayId = $lastMovement ? ((int) $lastMovement->display_id + 1) : 1;

            // OUT
            $outMovement = InventoryMovement::create([
                'display_id'      => (string) $nextDisplayId,
                'movement_type'   => InventoryMovement::TYPE_SORTING_OUT,
                'direction'       => InventoryMovement::DIRECTION_OUT,
                'movement_date'   => $movementDate,
                'document_number' => $documentNumber,
                'warehouse_id'    => $warehouseId,
                'statement'       => "فرز - {$documentNumber} - صرف الكمية الأصلية",
                'source_type'     => InventoryMovement::SOURCE_SORTING,
                'source_id'       => null,
                'total'           => $inputTotal,
            ]);

            InventoryMovementDetail::create([
                'movement_id'  => $outMovement->movement_id,
                'item_id'      => $itemId,
                'type_id'      => $typeId,
                'unit_id'      => $inputUnitId,
                'code'         => $data['code'] ?? null,
                'warehouse_id' => $warehouseId,
                'supplier_account_id'  => $sourceSupplierId,
                'lot_source_detail_id' => $sourceDetailId,
                'quantity'     => $inputQty,
                'unit_cost'    => $unitCostInput,
                'min_price'    => null,
                'max_price'    => null,
                'sale_price'   => null,
                'total'        => $inputTotal,
            ]);

            // IN
            $nextDisplayId++;

            $inMovement = InventoryMovement::create([
                'display_id'      => (string) $nextDisplayId,
                'movement_type'   => InventoryMovement::TYPE_SORTING_IN,
                'direction'       => InventoryMovement::DIRECTION_IN,
                'movement_date'   => $movementDate,
                'document_number' => $documentNumber,
                'warehouse_id'    => $warehouseId,
                'statement'       => "فرز - {$documentNumber} - إضافة الوحدات الناتجة",
                'source_type'     => InventoryMovement::SOURCE_SORTING,
                'source_id'       => $outMovement->movement_id,
                'total'           => $inputTotal,
            ]);

            InventoryMovementDetail::create([
                'movement_id'  => $inMovement->movement_id,
                'item_id'      => $itemId,
                'type_id'      => $typeId,
                'unit_id'      => $outputUnitId,
                'code'         => $data['code'] ?? null,
                'warehouse_id' => $warehouseId,
                'supplier_account_id'  => $sourceSupplierId,
                'lot_source_detail_id' => $sourceDetailId,
                'quantity'     => $outputQty,
                'unit_cost'    => $unitCostOutput,
                'min_price'    => isset($data['min_price']) ? (float) $data['min_price'] : null,
                'max_price'    => isset($data['max_price']) ? (float) $data['max_price'] : null,
                'sale_price'   => isset($data['sale_price']) ? (float) $data['sale_price'] : null,
                'total'        => $inputTotal,
            ]);

            $this->applyMovement($outMovement);
            $this->applyMovement($inMovement);

            return [
                'document_number'   => $documentNumber,
                'out_movement_id'   => $outMovement->movement_id,
                'in_movement_id'    => $inMovement->movement_id,
                'input_quantity'    => $inputQty,
                'output_quantity'   => $outputQty,
                'input_value'       => $inputTotal,
                'output_unit_cost'  => $unitCostOutput,
                'batch_id'          => $sourceDetailId,
            ];
        });
    }

    public function generateSortingNumber(): string
    {
        $lastDoc = InventoryMovement::where('source_type', InventoryMovement::SOURCE_SORTING)
            ->where('document_number', 'like', 'SORT-%')
            ->orderByDesc('movement_id')
            ->value('document_number');

        $lastNumber = 0;
        if ($lastDoc && preg_match('/SORT-(\d+)/', $lastDoc, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'SORT-' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }

    public function applyMovement(InventoryMovement $movement): void { /* No-op */ }
    public function reverseMovement(InventoryMovement $movement): void { /* No-op */ }
}