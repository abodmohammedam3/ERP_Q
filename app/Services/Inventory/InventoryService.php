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
     * يقفل صفوف inventory_movement_details المرتبطة بـ
     * (item + warehouse + unit + supplier?).
     *
     * عند تمرير supplierAccountId يقفل فقط دفعة ذلك المورد —
     * يمنع سباق خصم من نفس دفعة مورد بطلبيتين متزامنتين.
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
            $supplierId  = isset($c['supplier_account_id']) && $c['supplier_account_id'] !== null
                ? (int) $c['supplier_account_id']
                : null;

            if (!$itemId || !$warehouseId) {
                continue;
            }

            $key = $itemId . '|' . $warehouseId . '|' . ($unitId ?? 0) . '|' . ($supplierId ?? 0);
            $pairs[$key] = [
                'item_id'      => $itemId,
                'warehouse_id' => $warehouseId,
                'unit_id'      => $unitId,
                'supplier_account_id' => $supplierId,
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

            /* ✅ القفل على دفعة المورد تحديداً عند تمريره */
            if ($pair['supplier_account_id'] !== null) {
                $query->where('supplier_account_id', $pair['supplier_account_id']);
            }

            $query->select('movement_detail_id')
                  ->lockForUpdate()
                  ->get();
        }
    }

    /**
     * ✅ قفل مجمّع: استعلام واحد بشرط OR على كل التركيبات
     * بدل استعلام lockForUpdate لكل تركيبة على حدة.
     *
     * المدخل: مصفوفة pairs بنفس شكل lockStockRows
     * (item_id, warehouse_id, unit_id?, supplier_account_id?).
     *
     * الترتيب محفوظ تصاعدياً بنفس مفتاح lockStockRows (ksort) —
     * نفس ترتيب القفل القديم لمنع الـ deadlock، مع اختزال عدد
     * الاستعلامات من N إلى 1 داخل نفس الـ transaction.
     */
    public function lockRowsForPairs(array $pairs): void
    {
        $pairs = array_values($pairs);

        if (empty($pairs)) {
            return;
        }

        /* ترتيب ثابت (نفس مفتاح lockStockRows) لمنع سباق الترتيب بين العمليات */
        usort($pairs, function ($a, $b) {
            return [$a['item_id'], $a['warehouse_id'], $a['unit_id'] ?? 0, $a['supplier_account_id'] ?? 0]
                 <=> [$b['item_id'], $b['warehouse_id'], $b['unit_id'] ?? 0, $b['supplier_account_id'] ?? 0];
        });

        $query = DB::table('inventory_movement_details')->where(function ($outer) use ($pairs) {
            foreach ($pairs as $pair) {
                $outer->orWhere(function ($q) use ($pair) {
                    $q->where('item_id', $pair['item_id'])
                      ->where('warehouse_id', $pair['warehouse_id']);

                    if (!empty($pair['unit_id'])) {
                        $q->where('unit_id', $pair['unit_id']);
                    }

                    if (array_key_exists('supplier_account_id', $pair)
                        && $pair['supplier_account_id'] !== null) {
                        $q->where('supplier_account_id', $pair['supplier_account_id']);
                    }
                });
            }
        });

        $query->select('movement_detail_id')
              ->lockForUpdate()
              ->get();
    }

    // =====================================================
    // القراءة — قراءة الأرصدة
    // =====================================================

    public function availableQuantity(
        int $itemId,
        int $warehouseId,
        ?int $unitId = null
    ): float {
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

        return (float) $in - (float) $out;
    }

    public function isAvailable(
        int $itemId,
        int $warehouseId,
        float $qty,
        ?int $unitId = null
    ): bool {
        return $this->availableQuantity($itemId, $warehouseId, $unitId) >= $qty;
    }

    // =====================================================
    // ✅ رصيد دفعة موردية (Supplier-Tracked Lot)
    // =====================================================

    /**
     * رصيد صنف/مخزن/وحدة **لمورد محدد**.
     * supplierAccountId = null ⇒ رصيد "التوريد العام" (حركات بلا مورد: supply, بيع تاريخي...)
     */
    public function availableQuantityForSupplier(
        int $itemId,
        int $warehouseId,
        ?int $unitId,
        ?int $supplierAccountId
    ): float {
        $in = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->when($unitId !== null, fn ($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->when(
                $supplierAccountId === null,
                fn ($q) => $q->whereNull('inventory_movement_details.supplier_account_id'),
                fn ($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierAccountId)
            )
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
            ->when($unitId !== null, fn ($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->when(
                $supplierAccountId === null,
                fn ($q) => $q->whereNull('inventory_movement_details.supplier_account_id'),
                fn ($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierAccountId)
            )
            ->where('inventory_movements.direction', 'out')
            ->sum('inventory_movement_details.quantity');

        return (float) $in - (float) $out;
    }

    /**
     * ✅ نسخة مجمّعة من availableQuantityForSupplier + availableQuantity:
     * استعلام GROUP BY واحد يعيد Σ(in)−Σ(out) لكل (item, warehouse, unit, supplier).
     *
     * دلالة كل مفتاح مطابقة حرفياً للفردي:
     *   - unit_id = null      ⇒ يُحذف شرط الوحدة (مجموع كل الوحدات)
     *   - supplier = null     ⇒ whereNull(supplier_account_id) (التوريد العام)
     *   - supplier = int      ⇒ where(supplier_account_id = int)
     *   - بدون مفتاح supplier ⇒ يتجاهل المورد (مثل availableQuantity)
     *
     * @param array<int,array{item_id:int,warehouse_id:int,unit_id:?int,supplier_account_id?:?int}> $combinations
     * @return array<string,float> key: "item|warehouse|unit|null-sup" مثل مدخل lockStockRows
     */
    public function availableQuantityBatch(array $combinations): array
    {
        /* تفريغ المفاتيح المكررة وتوحيد الشكل */
        $unique = [];

        foreach ($combinations as $c) {
            $itemId      = (int) ($c['item_id'] ?? 0);
            $warehouseId = (int) ($c['warehouse_id'] ?? 0);

            if (!$itemId || !$warehouseId) {
                continue;
            }

            $unitId = isset($c['unit_id']) && $c['unit_id'] !== null ? (int) $c['unit_id'] : null;
            $hasSup = array_key_exists('supplier_account_id', $c);
            $supId  = $hasSup && $c['supplier_account_id'] !== null ? (int) $c['supplier_account_id'] : null;

            $key = $itemId . '|' . $warehouseId . '|' . ($unitId ?? 0) . '|' . ($hasSup ? ($supId ?? 0) : 'x');
            $unique[$key] = [
                'item_id'             => $itemId,
                'warehouse_id'        => $warehouseId,
                'unit_id'             => $unitId,
                'has_supplier'        => $hasSup,
                'supplier_account_id' => $supId,
            ];
        }

        if (empty($unique)) {
            return [];
        }

        /* استعلام واحد مجمّع على كل التركيبات المطلوبة (شرط OR مطابق لمنطق القفل) */
        $rows = InventoryMovementDetail::query()
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where(function ($outer) use ($unique) {
                foreach ($unique as $c) {
                    $outer->orWhere(function ($q) use ($c) {
                        $q->where('inventory_movement_details.item_id', $c['item_id'])
                          ->where('inventory_movement_details.warehouse_id', $c['warehouse_id']);

                        if ($c['unit_id'] !== null) {
                            $q->where('inventory_movement_details.unit_id', $c['unit_id']);
                        }

                        if ($c['has_supplier']) {
                            if ($c['supplier_account_id'] === null) {
                                $q->whereNull('inventory_movement_details.supplier_account_id');
                            } else {
                                $q->where('inventory_movement_details.supplier_account_id', $c['supplier_account_id']);
                            }
                        }
                    });
                }
            })
            ->groupBy(
                'inventory_movement_details.item_id',
                'inventory_movement_details.warehouse_id',
                'inventory_movement_details.unit_id',
                'inventory_movement_details.supplier_account_id',
                'inventory_movements.direction'
            )
            ->select([
                'inventory_movement_details.item_id as item_id',
                'inventory_movement_details.warehouse_id as warehouse_id',
                'inventory_movement_details.unit_id as unit_id',
                'inventory_movement_details.supplier_account_id as supplier_account_id',
                'inventory_movements.direction as direction',
                DB::raw('SUM(inventory_movement_details.quantity) as qty'),
            ])
            ->get();

        /* تجميع المجموعات في الذاكرة: لكل مفتاح مطلوب نجمع المجموعات المطابقة
           (وحدة null تعني كل الوحدات، والـ supplier مطابق حسب دلالة الطلب) */
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[] = [
                'item_id'             => (int) $row->item_id,
                'warehouse_id'        => (int) $row->warehouse_id,
                'unit_id'             => $row->unit_id !== null ? (int) $row->unit_id : null,
                'supplier_account_id' => $row->supplier_account_id !== null ? (int) $row->supplier_account_id : null,
                'direction'           => (string) $row->direction,
                'qty'                 => (float) $row->qty,
            ];
        }

        $result = [];

        foreach ($unique as $key => $c) {
            $in  = 0.0;
            $out = 0.0;

            foreach ($grouped as $g) {
                if ($g['item_id'] !== $c['item_id'] || $g['warehouse_id'] !== $c['warehouse_id']) {
                    continue;
                }

                /* وحدة null = كل الوحدات، غير ذلك تطابق تام */
                if ($c['unit_id'] !== null && $g['unit_id'] !== $c['unit_id']) {
                    continue;
                }

                if ($c['has_supplier']) {
                    /* دلالة الفردي: supplier null ⇒ whereNull فقط، وإلا تطابق تام */
                    if ($c['supplier_account_id'] === null && $g['supplier_account_id'] !== null) {
                        continue;
                    }
                    if ($c['supplier_account_id'] !== null && $g['supplier_account_id'] !== $c['supplier_account_id']) {
                        continue;
                    }
                }

                if ($g['direction'] === 'in') {
                    $in += $g['qty'];
                } else {
                    $out += $g['qty'];
                }
            }

            $result[$key] = $in - $out;
        }

        return $result;
    }

    /**
     * تكلفة آخر دفعة دخول **لمورد محدد** (نفس المنطق الحالي لكن مُقَيَّد بالمورد).
     */
    public function lastCostForSupplier(
        int $itemId,
        int $warehouseId,
        ?int $unitId,
        ?int $supplierAccountId
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
            ->when($unitId !== null, fn ($q) => $q->where('inventory_movement_details.unit_id', $unitId))
            ->when(
                $supplierAccountId === null,
                fn ($q) => $q->whereNull('inventory_movement_details.supplier_account_id'),
                fn ($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierAccountId)
            )
            ->where('inventory_movements.direction', 'in')
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select('inventory_movement_details.unit_cost')
            ->first();

        return $detail ? (float) $detail->unit_cost : 0;
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
    /* ✅ إصلاح: إزالة استثناء source_type='sorting'
       كان يمنع الفرز المتعدد المستويات (كجم → جرام → قطعة).
       التكلفة الصحيحة للوحدة الناتجة عن فرز سابق = تكلفتها المخزّنة نفسها. */
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

    public function lastPricing(
        int $itemId,
        int $warehouseId,
        ?int $unitId = null,
        ?int $supplierAccountId = null
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
            /* ✅ تسعير دفعة المورد المحدد (اختياري — null = التوريد العام) */
            ->when(
                $supplierAccountId !== null,
                fn ($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierAccountId)
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
            $supplierId   = !empty($data['supplier_account_id'])
                ? (int) $data['supplier_account_id']
                : null;
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
            //    ✅ القفل على دفعة المورد المحدد فقط
            $this->lockStockRows([
                ['item_id' => $itemId, 'warehouse_id' => $warehouseId, 'unit_id' => $inputUnitId, 'supplier_account_id' => $supplierId],
                ['item_id' => $itemId, 'warehouse_id' => $warehouseId, 'unit_id' => $outputUnitId, 'supplier_account_id' => $supplierId],
            ]);

            // ✅ 4) فحص الرصيد — على دفعة المورد المحدد حصراً
            //    حتى لو نفس الصنف موجود بمورد آخر لا يُمسّ رصيده
            $available = $this->availableQuantityForSupplier($itemId, $warehouseId, $inputUnitId, $supplierId);
            if ($available < $inputQty) {
                throw ValidationException::withMessages([
                    'input_quantity' => "الكمية المتوفرة من دفعة هذا المورد ({$available}) أقل من المطلوب ({$inputQty})",
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
                    'item_id' => 'لا يوجد سجل تكلفة صالح لهذا الصنف في الوحدة المحددة لهذا المورد',
                ]);
            }

            /* ✅ الدفعة المصروفة: آخر دفعة دخول لنفس التركيبة والمورد —
               الفرز يورّث مورد الدفعة ولا يخلط بين الموردين */
            $sourceDetail = InventoryMovementDetail::query()
                ->join(
                    'inventory_movements',
                    'inventory_movements.movement_id',
                    '=',
                    'inventory_movement_details.movement_id'
                )
                ->where('inventory_movement_details.item_id', $itemId)
                ->where('inventory_movement_details.warehouse_id', $warehouseId)
                ->where('inventory_movement_details.unit_id', $inputUnitId)
                ->where('inventory_movements.direction', 'in')
                /* ✅ الدفعة المصروفة من المورد المحدد فقط */
                ->when(
                    $supplierId !== null,
                    fn ($q) => $q->where('inventory_movement_details.supplier_account_id', $supplierId),
                    fn ($q) => $q->whereNull('inventory_movement_details.supplier_account_id')
                )
                ->orderByDesc('inventory_movement_details.movement_detail_id')
                ->select(
                    'inventory_movement_details.movement_detail_id',
                    'inventory_movement_details.supplier_account_id'
                )
                ->first();

            $sourceSupplierId = $sourceDetail?->supplier_account_id;
            $sourceDetailId   = $sourceDetail?->movement_detail_id;

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
                'supplier_account_id' => $sourceSupplierId,
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
                'supplier_account_id'  => $sourceSupplierId, /* ✅ الفرز يورّث المورد */
                'lot_source_detail_id' => $sourceDetailId,
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
    // ✅ applyMovement / reverseMovement
    // -----------------------------------------------------
    // الرصيد في هذا النظام يُحسب دائماً مباشرة من
    // inventory_movements + inventory_movement_details
    // (SUM(in) − SUM(out)) — لا يوجد جدول أرصدة منفصل
    // يُحدَّث عند كل حركة، لذا هذه العمليات اختيارية.
    // نُبقيها كنقاط ربط (Hooks) مستقبلاً لجدول
    // inventory_balances الموثَّق في تقارير الفحص،
    // مع ضمان عدم انكسار الاستدعاءات الموجودة في
    // خدمات البيع/الشراء/المرتجعات/الفرز.
    // =====================================================

    /**
     * نقطة ربط بعد إنشاء الحركة — لا تعديل على الأرصدة (تُحسب مباشرة من الحركات).
     */
    public function applyMovement(InventoryMovement $movement): void
    {
        /* No-op — الرصيد يُحسب مباشرة من inventory_movement_details */
    }

    /**
     * نقطة ربط بعد حذف الحركة — لا تعديل على الأرصدة (تُحسب مباشرة من الحركات).
     */
    public function reverseMovement(InventoryMovement $movement): void
    {
        /* No-op — الرصيد يُحسب مباشرة من inventory_movement_details */
    }

    /**
     * ✅ حذف مجمّع لكل حركات مصدر معيّن (source_type + source_id):
     * استعلامان بدل 2N — عكس الرصيد لكل حركة أولاً (hook)،
     * ثم حذف التفاصيل ثم الرؤوس، ثم إبطال كاش الداشبورد صراحةً
     * (استبدال لحدث Eloquent الذي لم يكن سيُطلق بالحذف الجماعي).
     */
    public function deleteMovementsBySource(string $sourceType, int $sourceId): int
    {
        $movements = InventoryMovement::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get(['movement_id']);

        if ($movements->isEmpty()) {
            return 0;
        }

        $movementIds = $movements->pluck('movement_id')->all();

        foreach ($movements as $movement) {
            $this->reverseMovement($movement);
        }

        InventoryMovementDetail::whereIn('movement_id', $movementIds)->delete();
        InventoryMovement::whereIn('movement_id', $movementIds)->delete();

        \App\Support\DashboardCache::bump();

        return count($movementIds);
    }


}