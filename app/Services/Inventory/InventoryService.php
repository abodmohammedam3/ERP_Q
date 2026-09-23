<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    // =====================================================
    // القراءة — قراءة الأرصدة
    // =====================================================

    /**
     * الكمية المتوفرة من الصنف في المخزن
     *
     * ✅ unit-aware: إذا تم تمرير $unitId، يُحسب الرصيد
     *    لهذه الوحدة فقط. هذا ضروري لأن الصنف الواحد قد
     *    يكون له رصيد بوحدات مختلفة (كيلو / حبة).
     *
     * ⚠️ تحذير: الكاش لا يُبطَل تلقائيًا إذا أُنشئت حركة
     *    جديدة في نفس الطلب. لا تستدعِ هذه الدالة بعد
     *    إنشاء حركة جديدة في نفس الطلب.
     */
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

    /**
     * هل الكمية المطلوبة متوفرة؟
     */
    public function isAvailable(
        int $itemId,
        int $warehouseId,
        float $qty,
        ?int $unitId = null
    ): bool {
        return $this->availableQuantity($itemId, $warehouseId, $unitId) >= $qty;
    }

    /**
     * آخر تكلفة توريد للصنف في المخزن
     * (من آخر حركة 'in' — توريد أو شراء)
     *
     * ✅ unit-aware: إذا تم تمرير $unitId، يُرجع التكلفة
     *    لهذه الوحدة فقط.
     */
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

    /**
     * ✅ تكلفة الوحدة الحالية لسياق محدد
     *
     * تُستخدم قبل الفرز لمعرفة تكلفة الكيلو الحالية.
     *
     * الفرق عن lastCost:
     *   - تدعم فلترة النوع (type_id) اختياريًا
     *   - تُرجع فقط حركات "in" (توريد/شراء)
     *   - تتجاهل حركات الفرز لتفادي الحلقة الدائرية
     *     (لأن الفرز نفسه قد يُنشئ حركة "in" للحبات)
     */
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
            // ✅ استثناء حركات الفرز لتفادي الحلقة الدائرية
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

    /**
     * ✅ آخر تسعير مسجَّل لصنف في مخزن بوحدة معينة
     *
     * يُرجع: sale_price, min_price, max_price
     * (من آخر حركة "in" تحتوي على سعر بيع فعلي)
     */
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

    /**
     * ✅ تنفيذ عملية فرز / تجهيز
     *
     * يحوّل كمية من وحدة (مثلًا كيلو) إلى وحدة أخرى (مثلًا حبة)
     * مع الحفاظ على القيمة الإجمالية.
     *
     * @param array $data {
     *     @type int         item_id
     *     @type int|null    type_id
     *     @type int         warehouse_id
     *     @type int         input_unit_id     الوحدة الأصلية (كيلو)
     *     @type int         output_unit_id    الوحدة الناتجة (حبة)
     *     @type float       input_quantity    كمية الوحدة الأصلية
     *     @type float       output_quantity   عدد الوحدات الناتجة
     *     @type string|null code
     *     @type float|null  sale_price
     *     @type float|null  min_price
     *     @type float|null  max_price
     *     @type string|null movement_date
     * }
     *
     * @return array {
     *     @type string document_number
     *     @type int    out_movement_id
     *     @type int    in_movement_id
     *     @type float  input_quantity
     *     @type float  output_quantity
     *     @type float  input_value
     *     @type float  output_unit_cost
     * }
     *
     * @throws ValidationException
     */
    public function sortInventory(array $data): array
    {
        return DB::transaction(function () use ($data) {

            // ─────────────────────────────────────────────
            // 1. استخراج المدخلات
            // ─────────────────────────────────────────────
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

            // ─────────────────────────────────────────────
            // 2. التحقق
            // ─────────────────────────────────────────────
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

            // ✅ قراءة الرصيد داخل الـ Transaction (منع Race Condition)
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

            // ─────────────────────────────────────────────
            // 3. الحساب
            // ─────────────────────────────────────────────
            $inputTotal = round($inputQty * $unitCostInput, 6);

            $unitCostOutput = $outputQty > 0
                ? round($inputTotal / $outputQty, 6)
                : 0;

            // ─────────────────────────────────────────────
            // 4. توليد رقم العملية (SORT-XXXXXX)
            // ─────────────────────────────────────────────
            $documentNumber = $this->generateSortingNumber();

            // ─────────────────────────────────────────────
            // 5. توليد display_id تسلسلي
            // ─────────────────────────────────────────────
            $lastMovement = InventoryMovement::lockForUpdate()
                ->orderByDesc('movement_id')
                ->first();

            $nextDisplayId = $lastMovement
                ? ((int) $lastMovement->display_id + 1)
                : 1;

            // ─────────────────────────────────────────────
            // 6. إنشاء حركة OUT (الوحدة الأصلية — كيلو)
            // ─────────────────────────────────────────────
            $outMovement = InventoryMovement::create([
                'display_id'      => (string) $nextDisplayId,
                'movement_type'   => InventoryMovement::TYPE_ISSUE,
                'direction'       => InventoryMovement::DIRECTION_OUT,
                'movement_date'   => $movementDate,
                'document_number' => $documentNumber,
                'warehouse_id'    => $warehouseId,
                'statement'       => "فرز - {$documentNumber} - صرف الكمية الأصلية",
                'source_type'     => InventoryMovement::SOURCE_SORTING,
                'source_id'       => null, // OUT هو الأصل
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

            // ─────────────────────────────────────────────
            // 7. إنشاء حركة IN (الوحدة الناتجة — حبة)
            // ─────────────────────────────────────────────
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
                'source_id'       => $outMovement->movement_id, // ربط بالحركة الأصلية
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

            // ─────────────────────────────────────────────
            // 8. تطبيق الحركتين (no-op حاليًا — للتوافق المستقبلي)
            // ─────────────────────────────────────────────
            $this->applyMovement($outMovement);
            $this->applyMovement($inMovement);

            // ─────────────────────────────────────────────
            // 9. النتيجة
            // ─────────────────────────────────────────────
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

    /**
     * ✅ توليد رقم عملية فرز تسلسلي
     *
     * التنسيق: SORT-000001, SORT-000002, ...
     *
     * يعتمد على آخر رقم موجود في document_number
     * للحركات التي source_type = 'sorting'.
     */
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