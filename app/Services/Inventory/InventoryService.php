<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;

class InventoryService
{
    // =====================================================
    // القراءة
    // =====================================================

    /**
     * الكمية المتوفرة من الصنف في المخزن
     * محسوبة مباشرة من كل الحركات
     */
    public function availableQuantity(int $itemId, int $warehouseId): float
    {
        $in = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
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
            ->where('inventory_movements.direction', 'out')
            ->sum('inventory_movement_details.quantity');

        return (float) $in - (float) $out;
    }

    /**
     * هل الكمية المطلوبة متوفرة؟
     */
    public function isAvailable(int $itemId, int $warehouseId, float $qty): bool
    {
        return $this->availableQuantity($itemId, $warehouseId) >= $qty;
    }

    /**
     * آخر تكلفة شراء للصنف في المخزن
     * (من آخر حركة 'in' — توريد أو شراء)
     */
    public function lastCost(int $itemId, int $warehouseId): float
    {
        $detail = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->where('inventory_movements.direction', 'in')
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select('inventory_movement_details.unit_cost')
            ->first();

        return $detail ? (float) $detail->unit_cost : 0;
    }

    // =====================================================
    // الكتابة (no-op حالياً)
    // =====================================================
    //
    // الأرصدة تُحسب مباشرة من الحركات (availableQuantity).
    // لا يوجد جدول أرصدة منفصل.
    //
    // عند إضافة جدول inventory_balances مستقبلًا،
    // ستُصبح هاتان الدالتان مسؤولتين عن تحديث الرصيد.
    //

    public function applyMovement(InventoryMovement $movement): void
    {
        // لا شيء — الأرصدة تُحسب عند الطلب
    }

    public function reverseMovement(InventoryMovement $movement): void
    {
        // لا شيء — الأرصدة تُحسب عند الطلب
    }
}