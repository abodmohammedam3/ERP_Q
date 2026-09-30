<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    // =====================================================
    // ✅ قفل صفوف المخزون (يمنع Race Conditions)
    // =====================================================

    /**
     * يقفل صفوف inventory_movement_details المرتبطة بـ (item + warehouse + unit).
     *
     * يستخدم SELECT ... FOR UPDATE لضمان أن أي Transaction آخر
     * لا يمكنه تعديل الرصيد حتى ننتهي من العمليات الحساسة.
     *
     * ترتيب الفحص ثابت (ksort) لمنع Deadlocks بين طلبات متزامنة.
     *
     * @param array $combinations  مصفوفة من عناصر: { item_id, warehouse_id, unit_id? }
     */
    public function lockStockRows(array $combinations): void
    {
        $pairs = [];

        foreach ($combinations as $c) {
            $itemId      = (int) ($c['item_id'] ?? 0);
            $warehouseId = (int) ($c['warehouse_id'] ?? 0);
            $unitId      = isset($c['unit_id']) && $c['unit_id'] !== null
                ? (int) $c['unit_id']
                : null;

            if (!$itemId || !$warehouseId) {
                continue;
            }

            $key = $itemId . '|' . $warehouseId . '|' . ($unitId ?? 0);
            $pairs[$key] = [
                'item_id'      => $itemId,
                'warehouse_id' => $warehouseId,
                'unit_id'      => $unitId,
            ];
        }

        ksort($pairs);

        foreach ($pairs as $pair) {
            $query = DB::table('inventory_movement_details')
                ->where('item_id', $pair['item_id'])
                ->where('warehouse_id', $pair['warehouse_id']);

            if ($pair['unit_id']) {
                $query->where('unit_id', $pair['unit_id']);
            }

            $query->select('movement_detail_id')
                  ->lockForUpdate()
                  ->get();
        }
    }

    // =====================================================
    // القراءة — قراءة الأرصدة
    // =====================================================

    public function availableQuantity(
        int $itemId,
        int $warehouseId,
        ?int $unitId = null
    ): float {
        static $cache = [];
        $key = "{$itemId}:{$warehouseId}:" . ($unitId ?? 'null');

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $in = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, function ($q) use ($unitId) {
                $q->where('inventory_movement_details.unit_id', $unitId);
            })
            ->where('inventory_movements.direction', 'in')
            ->sum('inventory_movement_details.quantity');

        $out = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, function ($q) use ($unitId) {
                $q->where('inventory_movement_details.unit_id', $unitId);
            })
            ->where('inventory_movements.direction', 'out')
            ->sum('inventory_movement_details.quantity');

        return $cache[$key] = (float) $in - (float) $out;
    }

    public function isAvailable(
        int $itemId,
        int $warehouseId,
        float $qty,
        ?int $unitId = null
    ): bool {
        return $this->availableQuantity($itemId, $warehouseId, $unitId) >= $qty;
    }

    public function lastCost(
        int $itemId,
        int $warehouseId,
        ?int $unitId = null
    ): float {
        $detail = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, function ($q) use ($unitId) {
                $q->where('inventory_movement_details.unit_id', $unitId);
            })
            ->where('inventory_movements.direction', 'in')
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select('inventory_movement_details.unit_cost')
            ->first();

        return $detail ? (float) $detail->unit_cost : 0;
    }

    public function currentUnitCost(
        int $itemId,
        ?int $typeId,
        int $warehouseId,
        int $unitId
    ): float {
        $query = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->where('inventory_movement_details.unit_id', $unitId)
            ->where('inventory_movements.direction', 'in')
            ->where(function ($q) {
                $q->whereNull('inventory_movements.source_type')
                  ->orWhere(
                      'inventory_movements.source_type',
                      '!=',
                      InventoryMovement::SOURCE_SORTING
                  );
            });

        if ($typeId !== null) {
            $query->where('inventory_movement_details.type_id', $typeId);
        }

        $detail = $query
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select('inventory_movement_details.unit_cost')
            ->first();

        return $detail ? (float) $detail->unit_cost : 0;
    }

    public function lastPricing(
        int $itemId,
        int $warehouseId,
        ?int $unitId = null
    ): array {
        $query = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, function ($q) use ($unitId) {
                $q->where('inventory_movement_details.unit_id', $unitId);
            })
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
    // الفرز / التجهيز
    // =====================================================

    public function sortInventory(array $data): array
    {
        return DB::transaction(function () use ($data) {

            // 1) استخراج المدخلات
            $itemId       = (int) $data['item_id'];
            $typeId       = isset($data['type_id']) && $data['type_id'] !== null
                ? (int) $data['type_id']
                : null;
            $warehouseId  = (int) $data['warehouse_id'];
            $inputUnitId  = (int) $data['input_unit_id'];
            $outputUnitId = (int) $data['output_unit_id'];
            $inputQty     = (float) $data['input_quantity'];
            $outputQty    = (float) $data['output_quantity'];
            $movementDate = $data['movement_date'] ?? now()->format('Y-m-d');

            // 2) التحقق
            if ($inputQty <= 0) {
                throw ValidationException::withMessages([
                    'input_quantity' => 'الكمية المفرزة يجب أن تكون أكبر من صفر',
                ]);
            }

            if ($outputQty <= 0) {
                throw ValidationException::withMessages([
                    'output_quantity' => 'عدد الوحدات الناتجة يجب أن يكون أكبر من صفر',
                ]);
            }

            if ($inputUnitId === $outputUnitId) {
                throw ValidationException::withMessages([
                    'output_unit_id' => 'وحدة المصدر ووحدة الناتج يجب أن تكونا مختلفتين',
                ]);
            }

            // ✅ 3) قفل صفوف المخزون (يمنع Race مع بيع/شراء/مرتجع متزامن)
            $this->lockStockRows([
                ['item_id' => $itemId, 'warehouse_id' => $warehouseId, 'unit_id' => $inputUnitId],
                ['item_id' => $itemId, 'warehouse_id' => $warehouseId, 'unit_id' => $outputUnitId],
            ]);

            // ✅ 4) الآن فحص الرصيد موثوق (لا يمكن لـ Transaction آخر تعديله)
            $available = $this->availableQuantity($itemId, $warehouseId, $inputUnitId);
            if ($available < $inputQty) {
                throw ValidationException::withMessages([
                    'input_quantity' => "الكمية المتوفرة ({$available}) أقل من المطلوب ({$inputQty})",
                ]);
            }

            // ✅ تكلفة الوحدة الأصلية
            $unitCostInput = $this->currentUnitCost(
                $itemId,
                $typeId,
                $warehouseId,
                $inputUnitId
            );

            if ($unitCostInput <= 0) {
                throw ValidationException::withMessages([
                    'item_id' => 'لا يوجد سجل تكلفة صالح لهذا الصنف في الوحدة المحددة',
                ]);
            }

            // 5) الحساب
            $inputTotal = round($inputQty * $unitCostInput, 6);

            $unitCostOutput = $outputQty > 0
                ? round($inputTotal / $outputQty, 6)
                : 0;

            // 6) توليد رقم العملية
            $documentNumber = $this->generateSortingNumber();

            // 7) display_id
            $lastMovement = InventoryMovement::lockForUpdate()
                ->orderByDesc('movement_id')
                ->first();

            $nextDisplayId = $lastMovement
                ? ((int) $lastMovement->display_id + 1)
                : 1;

            // 8) OUT (الوحدة الأصلية)
            $outMovement = InventoryMovement::create([
                'display_id'      => (string) $nextDisplayId,
                'movement_type'   => InventoryMovement::TYPE_ISSUE,
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
                'quantity'     => $inputQty,
                'unit_cost'    => $unitCostInput,
                'min_price'    => null,
                'max_price'    => null,
                'sale_price'   => null,
                'total'        => $inputTotal,
            ]);

            // 9) IN (الوحدة الناتجة)
            $nextDisplayId++;

            $inMovement = InventoryMovement::create([
                'display_id'      => (string) $nextDisplayId,
                'movement_type'   => InventoryMovement::TYPE_SUPPLY,
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
                'quantity'     => $outputQty,
                'unit_cost'    => $unitCostOutput,
                'min_price'    => isset($data['min_price']) ? (float) $data['min_price'] : null,
                'max_price'    => isset($data['max_price']) ? (float) $data['max_price'] : null,
                'sale_price'   => isset($data['sale_price']) ? (float) $data['sale_price'] : null,
                'total'        => $inputTotal,
            ]);

            // 10) تطبيق
            $this->applyMovement($outMovement);
            $this->applyMovement($inMovement);

            // 11) النتيجة
            return [
                'document_number'   => $documentNumber,
                'out_movement_id'   => $outMovement->movement_id,
                'in_movement_id'    => $inMovement->movement_id,
                'input_quantity'    => $inputQty,
                'output_quantity'   => $outputQty,
                'input_value'       => $inputTotal,
                'output_unit_cost'  => $unitCostOutput,
            ];
        });
    }

    public function generateSortingNumber(): string
    {
        $lastDoc = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_SORTING
            )
            ->where('document_number', 'like', 'SORT-%')
            ->orderByDesc('movement_id')
            ->value('document_number');

        $lastNumber = 0;
        if ($lastDoc && preg_match('/SORT-(\d+)/', $lastDoc, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'SORT-' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }

    // =====================================================
    // الكتابة (no-op)
    // =====================================================

    public function applyMovement(InventoryMovement $movement): void
    {
        // لا شيء — الأرصدة تُحسب عند الطلب
    }

    public function reverseMovement(InventoryMovement $movement): void
    {
        // لا شيء — الأرصدة تُحسب عند الطلب
    }
}