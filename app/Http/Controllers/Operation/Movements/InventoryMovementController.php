<?php

namespace App\Http\Controllers\Operation\Movements;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Inventory\Item;
use App\Models\Inventory\Type;
use App\Models\Inventory\Stock;
use App\Models\Inventory\Unit;

class InventoryMovementController extends Controller
{
    /**
     * @var InventoryService
     */
    protected InventoryService $inventoryService;

    /**
     * Constructor Injection
     */
    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * عرض شاشة حركات المخزون
     */
    public function index()
    {
        return view('operation.movements.index');
    }

    /**
     * قائمة الحركات (JSON) — تُستخدم في البحث
     */
    public function list(Request $request)
    {
        $search         = trim($request->input('search', ''));
        $warehouseId    = $request->input('warehouse_id');
        $movementType   = $request->input('movement_type');
        $dateFrom       = $request->input('date_from');
        $dateTo         = $request->input('date_to');

        $query = InventoryMovement::query()
            ->with('warehouse')
            ->orderBy('movement_id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('display_id', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%")
                  ->orWhere('statement', 'like', "%{$search}%");
            });
        }

        if (!empty($warehouseId)) {
            $query->where('warehouse_id', $warehouseId);
        }

        if (!empty($movementType)) {
            $query->where('movement_type', $movementType);
        }

        if (!empty($dateFrom)) {
            $query->where('movement_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->where('movement_date', '<=', $dateTo);
        }

        $movements = $query->limit(50)->get()->map(function ($m) {
            return [
                'movement_id'      => $m->movement_id,
                'display_id'       => $m->display_id,
                'movement_type'    => $m->movement_type,
                'direction'        => $m->direction,
                'movement_date'    => optional($m->movement_date)->format('Y-m-d'),
                'document_number'  => $m->document_number,
                'warehouse_id'     => $m->warehouse_id,
                'warehouse_name'   => $m->warehouse->StockName ?? '',
                'statement'        => $m->statement,
                'total'            => (float) $m->total,
            ];
        });

        return response()->json(['data' => $movements]);
    }

    /**
     * عرض حركة واحدة مع تفاصيلها (JSON)
     */
    public function show($id)
    {
        $movement = InventoryMovement::with([
            'warehouse',
            'details.item',
            'details.type',
            'details.unit',
            'details.warehouse',
        ])->find($id);

        if (!$movement) {
            return response()->json(['message' => 'الحركة غير موجودة'], 404);
        }

        return response()->json([
            'header' => [
                'movement_id'      => $movement->movement_id,
                'display_id'       => $movement->display_id,
                'movement_type'    => $movement->movement_type,
                'direction'        => $movement->direction,
                'movement_date'    => optional($movement->movement_date)->format('Y-m-d'),
                'document_number'  => $movement->document_number,
                'warehouse_id'     => $movement->warehouse_id,
                'warehouse_name'   => $movement->warehouse->StockName ?? '',
                'statement'        => $movement->statement,
                'source_type'      => $movement->source_type,
                'source_id'        => $movement->source_id,
                'total'            => (float) $movement->total,
            ],
            'details' => $movement->details->map(function ($d) {
                return [
                    'movement_detail_id' => $d->movement_detail_id,
                    'item_id'            => $d->item_id,
                    'item_name'          => $d->item->itemName2 ?? '',
                    'type_id'            => $d->type_id,
                    'type_name'          => $d->type->name ?? '',
                    'unit_id'            => $d->unit_id,
                    'unit_name'          => $d->unit->UnitName ?? '',
                    'code'               => $d->code,
                    'warehouse_id'       => $d->warehouse_id,
                    'warehouse_name'     => $d->warehouse->StockName ?? '',
                    'quantity'           => (float) $d->quantity,
                    'unit_cost'          => (float) $d->unit_cost,
                    'min_price'          => $d->min_price !== null ? (float) $d->min_price : null,
                    'max_price'          => $d->max_price !== null ? (float) $d->max_price : null,
                    'sale_price'         => $d->sale_price !== null ? (float) $d->sale_price : null,
                    'total'              => (float) $d->total,
                ];
            }),
        ]);
    }

    /**
     * رقم الحركة التالي (مقترح للعرض فقط)
     */
    public function nextNumber()
    {
        $last = InventoryMovement::orderBy('movement_id', 'desc')->first();
        $next = $last ? ((int) $last->display_id + 1) : 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * حفظ حركة جديدة
     *
     * ✅ فحص (source_type + source_id) قبل try/catch
     */
    public function store(Request $request)
    {
        $validator = $this->validateMovement($request);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->filled('source_type') && $request->filled('source_id')) {
            $exists = InventoryMovement::where('source_type', $request->input('source_type'))
                ->where('source_id', $request->input('source_id'))
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'توجد حركة مرتبطة بنفس المصدر مسبقًا',
                ], 409);
            }
        }

        try {
            DB::beginTransaction();

            $last = InventoryMovement::lockForUpdate()
                ->orderBy('movement_id', 'desc')
                ->first();

            $nextNumber = $last ? ((int) $last->display_id + 1) : 1;

            $type      = $request->input('movement_type');
            $direction = InventoryMovement::directionForType($type);

            $movement = InventoryMovement::create([
                'display_id'      => (string) $nextNumber,
                'movement_type'   => $type,
                'direction'       => $direction,
                'movement_date'   => $request->input('movement_date'),
                'document_number' => $request->input('document_number'),
                'warehouse_id'    => $request->input('warehouse_id'),
                'statement'       => $request->input('statement'),
                'source_type'     => $request->input('source_type'),
                'source_id'       => $request->input('source_id'),
                'total'           => 0,
            ]);

            $this->saveDetails($movement, $request->input('details', []));
            $this->recalculateTotal($movement);

            DB::commit();

            return response()->json([
                'message'     => 'تم حفظ الحركة بنجاح',
                'movement_id' => $movement->movement_id,
                'display_id'  => $movement->display_id,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل حفظ الحركة',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =====================================================
    // ✅ الفرز / التجهيز
    // =====================================================

    /**
     * تنفيذ عملية فرز / تجهيز
     *
     * يحوّل كمية من وحدة (كيلو) إلى وحدة أخرى (حبة)
     * عبر حركتين مترابطتين:
     *   - issue/out: الوحدة الأصلية
     *   - supply/in: الوحدة الناتجة
     *
     * POST /operation/movements/sort
     */
    public function sort(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'         => ['required', 'exists:Items,itemID'],
            'type_id'         => ['nullable', 'exists:type,id'],
            'warehouse_id'    => ['required', 'exists:stocks,StockID'],
            'input_unit_id'   => ['required', 'exists:units,UnitID'],
            'output_unit_id'  => ['required', 'exists:units,UnitID', 'different:input_unit_id'],
            'input_quantity'  => ['required', 'numeric', 'gt:0'],
            'output_quantity' => ['required', 'numeric', 'gt:0'],
            'code'            => ['nullable', 'string', 'max:50'],
            'sale_price'      => ['nullable', 'numeric', 'min:0'],
            'min_price'       => ['nullable', 'numeric', 'min:0'],
            'max_price'       => ['nullable', 'numeric', 'min:0'],
            'movement_date'   => ['nullable', 'date'],
        ], [
            'item_id.required'         => 'يجب اختيار الصنف',
            'item_id.exists'           => 'الصنف المحدد غير موجود',
            'warehouse_id.required'    => 'يجب اختيار المخزن',
            'warehouse_id.exists'      => 'المخزن المحدد غير موجود',
            'input_unit_id.required'   => 'يجب تحديد الوحدة الأصلية',
            'input_unit_id.exists'     => 'الوحدة الأصلية غير موجودة',
            'output_unit_id.required'  => 'يجب تحديد الوحدة الناتجة',
            'output_unit_id.exists'    => 'الوحدة الناتجة غير موجودة',
            'output_unit_id.different' => 'الوحدة الناتجة يجب أن تختلف عن الأصلية',
            'input_quantity.required'  => 'يجب إدخال الكمية المفرزة',
            'input_quantity.gt'        => 'الكمية المفرزة يجب أن تكون أكبر من صفر',
            'output_quantity.required' => 'يجب إدخال عدد الوحدات الناتجة',
            'output_quantity.gt'       => 'عدد الوحدات الناتجة يجب أن يكون أكبر من صفر',
        ]);

        // ✅ تحقق منطقي: min ≤ sale ≤ max
        $validator->after(function ($v) use ($request) {
            $sale = $request->input('sale_price');
            $min  = $request->input('min_price');
            $max  = $request->input('max_price');

            if ($sale !== null && $min !== null && (float) $sale < (float) $min) {
                $v->errors()->add('sale_price', 'سعر البيع أقل من الحد الأدنى');
            }

            if ($sale !== null && $max !== null && (float) $sale > (float) $max) {
                $v->errors()->add('sale_price', 'سعر البيع أكبر من الحد الأعلى');
            }

            if ($min !== null && $max !== null && (float) $min > (float) $max) {
                $v->errors()->add('min_price', 'الحد الأدنى أكبر من الحد الأعلى');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->inventoryService->sortInventory([
                'item_id'         => (int) $request->input('item_id'),
                'type_id'         => $request->input('type_id') ? (int) $request->input('type_id') : null,
                'warehouse_id'    => (int) $request->input('warehouse_id'),
                'input_unit_id'   => (int) $request->input('input_unit_id'),
                'output_unit_id'  => (int) $request->input('output_unit_id'),
                'input_quantity'  => (float) $request->input('input_quantity'),
                'output_quantity' => (float) $request->input('output_quantity'),
                'code'            => $request->input('code'),
                'sale_price'      => $request->input('sale_price'),
                'min_price'       => $request->input('min_price'),
                'max_price'       => $request->input('max_price'),
                'movement_date'   => $request->input('movement_date') ?: now()->format('Y-m-d'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم الفرز بنجاح',
                'data'    => $result,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Inventory sort failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'input'   => $request->all(),
            ]);

            return response()->json([
                'message' => 'فشل الفرز',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * عكس عملية فرز (Reverse Sorting)
     *
     * ينشئ حركتين جديدتين لعكس الفرز:
     *   - issue/out: الوحدة الناتجة (حبة)
     *   - supply/in: الوحدة الأصلية (كيلو)
     *
     * ⚠️ لا يحذف الحركات الأصلية — يحافظ على التاريخ.
     *
     * POST /operation/movements/sort/{documentNumber}/reverse
     *
     * ملاحظة: هذا التنفيذ الأولي (P2). قد يحتاج تطويرًا لاحقًا
     * لمراعاة حالات مثل بيع جزء من الناتج قبل العكس.
     */
    public function reverseSort(Request $request, string $documentNumber)
    {
        // ─────────────────────────────────────────────
        // 1. البحث عن الحركتين الأصليتين
        // ─────────────────────────────────────────────
        $movements = InventoryMovement::with('details')
            ->where('document_number', $documentNumber)
            ->where('source_type', InventoryMovement::SOURCE_SORTING)
            ->orderBy('movement_id')
            ->get();

        if ($movements->count() < 2) {
            return response()->json([
                'message' => 'لم يتم العثور على عملية فرز صالحة بهذا الرقم',
            ], 404);
        }

        // ─────────────────────────────────────────────
        // 2. التحقق من عدم وجود عملية عكس سابقة
        // ─────────────────────────────────────────────
        $reversalDoc = 'REV-' . $documentNumber;

        $alreadyReversed = InventoryMovement::where('document_number', $reversalDoc)
            ->exists();

        if ($alreadyReversed) {
            return response()->json([
                'message' => 'تم عكس هذه العملية مسبقًا',
            ], 409);
        }

        // ─────────────────────────────────────────────
        // 3. استخراج بيانات الحركتين
        // ─────────────────────────────────────────────
        $outMovement = $movements->firstWhere('direction', 'out');
        $inMovement  = $movements->firstWhere('direction', 'in');

        if (!$outMovement || !$inMovement) {
            return response()->json([
                'message' => 'بيانات عملية الفرز غير مكتملة',
            ], 422);
        }

        $outDetail = $outMovement->details->first();
        $inDetail  = $inMovement->details->first();

        if (!$outDetail || !$inDetail) {
            return response()->json([
                'message' => 'تفاصيل عملية الفرز غير مكتملة',
            ], 422);
        }

        // ─────────────────────────────────────────────
        // 4. التحقق من توفر الناتج للعكس
        // ─────────────────────────────────────────────
        $availableOutput = $this->inventoryService->availableQuantity(
            (int) $inDetail->item_id,
            (int) $inDetail->warehouse_id,
            (int) $inDetail->unit_id
        );

        if ($availableOutput < (float) $inDetail->quantity) {
            return response()->json([
                'message' => "لا يمكن عكس العملية — الرصيد الحالي من الوحدة الناتجة ({$availableOutput}) أقل من المطلوب ({$inDetail->quantity}). قد يكون جزء من الكمية قد تم بيعه.",
            ], 422);
        }

        try {
            DB::beginTransaction();

            $lastMovement = InventoryMovement::lockForUpdate()
                ->orderByDesc('movement_id')
                ->first();

            $nextDisplayId = $lastMovement
                ? ((int) $lastMovement->display_id + 1)
                : 1;

            // ─────────────────────────────────────────
            // 5. حركة OUT للوحدة الناتجة (حبة)
            // ─────────────────────────────────────────
            $reverseOutMovement = InventoryMovement::create([
                'display_id'      => (string) $nextDisplayId,
                'movement_type'   => InventoryMovement::TYPE_ISSUE,
                'direction'       => InventoryMovement::DIRECTION_OUT,
                'movement_date'   => now()->format('Y-m-d'),
                'document_number' => $reversalDoc,
                'warehouse_id'    => $inDetail->warehouse_id,
                'statement'       => "عكس فرز {$documentNumber} - صرف الوحدات الناتجة",
                'source_type'     => InventoryMovement::SOURCE_SORTING,
                'source_id'       => $outMovement->movement_id,
                'total'           => $inDetail->total,
            ]);

            InventoryMovementDetail::create([
                'movement_id'  => $reverseOutMovement->movement_id,
                'item_id'      => $inDetail->item_id,
                'type_id'      => $inDetail->type_id,
                'unit_id'      => $inDetail->unit_id,
                'code'         => $inDetail->code,
                'warehouse_id' => $inDetail->warehouse_id,
                'quantity'     => $inDetail->quantity,
                'unit_cost'    => $inDetail->unit_cost,
                'total'        => $inDetail->total,
            ]);

            // ─────────────────────────────────────────
            // 6. حركة IN للوحدة الأصلية (كيلو)
            // ─────────────────────────────────────────
            $nextDisplayId++;

            $reverseInMovement = InventoryMovement::create([
                'display_id'      => (string) $nextDisplayId,
                'movement_type'   => InventoryMovement::TYPE_SUPPLY,
                'direction'       => InventoryMovement::DIRECTION_IN,
                'movement_date'   => now()->format('Y-m-d'),
                'document_number' => $reversalDoc,
                'warehouse_id'    => $outDetail->warehouse_id,
                'statement'       => "عكس فرز {$documentNumber} - إرجاع الكمية الأصلية",
                'source_type'     => InventoryMovement::SOURCE_SORTING,
                'source_id'       => $outMovement->movement_id,
                'total'           => $outDetail->total,
            ]);

            InventoryMovementDetail::create([
                'movement_id'  => $reverseInMovement->movement_id,
                'item_id'      => $outDetail->item_id,
                'type_id'      => $outDetail->type_id,
                'unit_id'      => $outDetail->unit_id,
                'code'         => $outDetail->code,
                'warehouse_id' => $outDetail->warehouse_id,
                'quantity'     => $outDetail->quantity,
                'unit_cost'    => $outDetail->unit_cost,
                'total'        => $outDetail->total,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم عكس الفرز بنجاح',
                'data'    => [
                    'reversal_document_number' => $reversalDoc,
                    'out_movement_id'          => $reverseOutMovement->movement_id,
                    'in_movement_id'           => $reverseInMovement->movement_id,
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Inventory reverse sort failed', [
                'message'         => $e->getMessage(),
                'document_number' => $documentNumber,
            ]);

            return response()->json([
                'message' => 'فشل عكس الفرز',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =====================================================
    // ✅ Helpers — للواجهة الأمامية
    // =====================================================

    /**
     * جلب الرصيد المتاح لصنف في مخزن بوحدة محددة
     *
     * GET /operation/movements/helpers/available
     *     ?item_id=X&warehouse_id=Y&unit_id=Z
     */
    public function available(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'exists:Items,itemID'],
            'warehouse_id' => ['required', 'integer', 'exists:stocks,StockID'],
            'unit_id'      => ['nullable', 'integer', 'exists:units,UnitID'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $unitId      = $request->input('unit_id')
            ? (int) $request->input('unit_id')
            : null;

        $quantity = $this->inventoryService->availableQuantity(
            $itemId,
            $warehouseId,
            $unitId
        );

        $cost = $unitId !== null
            ? $this->inventoryService->lastCost($itemId, $warehouseId, $unitId)
            : 0;

        return response()->json([
            'success'   => true,
            'data'      => [
                'quantity' => $quantity,
                'cost'     => $cost,
            ],
        ]);
    }

    /**
     * جلب التسعير الحالي لصنف في مخزن بوحدة محددة
     *
     * GET /operation/movements/helpers/pricing
     *     ?item_id=X&warehouse_id=Y&unit_id=Z
     */
    public function pricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'exists:Items,itemID'],
            'warehouse_id' => ['required', 'integer', 'exists:stocks,StockID'],
            'unit_id'      => ['nullable', 'integer', 'exists:units,UnitID'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $unitId      = $request->input('unit_id')
            ? (int) $request->input('unit_id')
            : null;

        $pricing = $this->inventoryService->lastPricing(
            $itemId,
            $warehouseId,
            $unitId
        );

        return response()->json([
            'success' => true,
            'data'    => $pricing,
        ]);
    }
        /**
     * ✅ جلب الرصيد + التكلفة + التسعير (مدمج)
     *
     * GET /operation/movements/helpers/stock-pricing
     *     ?item_id=X&warehouse_id=Y&unit_id=Z&type_id=W
     */
    public function stockPricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'exists:Items,itemID'],
            'warehouse_id' => ['required', 'integer', 'exists:stocks,StockID'],
            'unit_id'      => ['required', 'integer', 'exists:units,UnitID'],
            'type_id'      => ['nullable', 'integer', 'exists:type,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $unitId      = (int) $request->input('unit_id');
        $typeId      = $request->input('type_id') ? (int) $request->input('type_id') : null;

        $quantity = $this->inventoryService->availableQuantity(
            $itemId,
            $warehouseId,
            $unitId
        );

        $cost = $this->inventoryService->currentUnitCost(
            $itemId,
            $typeId,
            $warehouseId,
            $unitId
        );

        $pricing = $this->inventoryService->lastPricing(
            $itemId,
            $warehouseId,
            $unitId
        );

        // جلب اسم الوحدة
        $unitName = \App\Models\Inventory\Unit::where('UnitID', $unitId)
            ->value('UnitName') ?? '';

        return response()->json([
            'success' => true,
            'data'    => [
                'quantity'   => $quantity,
                'cost'       => $cost,
                'unit_name'  => $unitName,
                'sale_price' => $pricing['sale_price'],
                'min_price'  => $pricing['min_price'],
                'max_price'  => $pricing['max_price'],
            ],
        ]);
    }

        /**
     * ✅ تحديث التسعير لآخر حركة "in"
     *
     * PUT /operation/movements/helpers/pricing
     *
     * ⚠️ معالجة خاصة:
     *   - unit_id قد يكون null
     *   - sale_price / min_price / max_price:
     *       0 أو فارغ = "لم يُحدَّد" → null في DB
     *       يُتجاهل في التحقق المنطقي
     */
    public function updatePricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'exists:Items,itemID'],
            'warehouse_id' => ['required', 'integer', 'exists:stocks,StockID'],
            'unit_id'      => ['nullable', 'integer', 'exists:units,UnitID'],
            'sale_price'   => ['nullable', 'numeric', 'min:0'],
            'min_price'    => ['nullable', 'numeric', 'min:0'],
            'max_price'    => ['nullable', 'numeric', 'min:0'],
        ]);

        // ✅ تحويل 0/فارغ إلى null قبل التحقق المنطقي
        $toNullable = function ($value) {
            if ($value === null || $value === '') return null;
            $n = (float) $value;
            return $n > 0 ? $n : null;
        };

        $saleF = $toNullable($request->input('sale_price'));
        $minF  = $toNullable($request->input('min_price'));
        $maxF  = $toNullable($request->input('max_price'));

        // ✅ تحقق منطقي (مع تجاهل القيم الفارغة)
        $validator->after(function ($v) use ($saleF, $minF, $maxF) {
            if ($minF !== null && $maxF !== null && $minF > $maxF) {
                $v->errors()->add('min_price', 'الحد الأدنى أكبر من الحد الأعلى');
            }
            if ($saleF !== null && $minF !== null && $saleF < $minF) {
                $v->errors()->add('sale_price', 'سعر البيع أقل من الحد الأدنى');
            }
            if ($saleF !== null && $maxF !== null && $saleF > $maxF) {
                $v->errors()->add('sale_price', 'سعر البيع أكبر من الحد الأعلى');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $unitId      = $request->input('unit_id')
            ? (int) $request->input('unit_id')
            : null;

        // ✅ آخر حركة "in" — دعم unit_id = null
        $detail = InventoryMovementDetail::query()
            ->whereHas('movement', function ($q) {
                $q->where('direction', 'in');
            })
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->when(
                $unitId !== null,
                fn($q) => $q->where('unit_id', $unitId),
                fn($q) => $q->whereNull('unit_id')
            )
            ->orderByDesc('movement_detail_id')
            ->first();

        if (!$detail) {
            return response()->json([
                'message' => 'لا يوجد سجل تسعير لهذا الصنف في الوحدة المحددة.',
            ], 404);
        }

        // ✅ تخزين null بدل 0 (القيم الفارغة تُنظَّف)
        $detail->sale_price = $saleF;
        $detail->min_price  = $minF;
        $detail->max_price  = $maxF;
        $detail->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث التسعير بنجاح',
            'data'    => [
                'movement_detail_id' => $detail->movement_detail_id,
                'sale_price'         => $detail->sale_price !== null ? (float) $detail->sale_price : 0,
                'min_price'          => $detail->min_price  !== null ? (float) $detail->min_price  : 0,
                'max_price'          => $detail->max_price  !== null ? (float) $detail->max_price  : 0,
            ],
        ]);
    }

        /**
     * ✅ جلب جميع الأرصدة المتاحة مع التسعير
     *    (للنافذة المنبثقة الموحّدة)
     *
     * GET /operation/movements/helpers/stock-balances
     *
     * يُرجع مصفوفة من الصفوف، كل صف يمثل تركيبة:
     *   (صنف + نوع + مخزن + وحدة)
     * مع الرصيد والتكلفة والتسعير الحالي.
     */
    public function allStockBalances(Request $request)
    {
        // ─────────────────────────────────────────────
        // 1. جلب جميع التركيبات مع الأرصدة
        // ─────────────────────────────────────────────
        $combinations = DB::table('inventory_movement_details as imd')
            ->join(
                'inventory_movements as im',
                'im.movement_id',
                '=',
                'imd.movement_id'
            )
            ->select(
                'imd.item_id',
                'imd.type_id',
                'imd.warehouse_id',
                'imd.unit_id',
                DB::raw("SUM(CASE WHEN im.direction = 'in' THEN imd.quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN im.direction = 'out' THEN imd.quantity ELSE 0 END) as total_out")
            )
            ->groupBy('imd.item_id', 'imd.type_id', 'imd.warehouse_id', 'imd.unit_id')
            ->get()
            ->filter(function ($c) {
                return ((float) $c->total_in - (float) $c->total_out) > 0.000001;
            })
            ->values();

        if ($combinations->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // ─────────────────────────────────────────────
        // 2. تحميل الأسماء في bulk (تجنب N+1)
        // ─────────────────────────────────────────────
        $itemIds      = $combinations->pluck('item_id')->unique()->filter()->values()->toArray();
        $typeIds      = $combinations->pluck('type_id')->unique()->filter()->values()->toArray();
        $warehouseIds = $combinations->pluck('warehouse_id')->unique()->filter()->values()->toArray();
        $unitIds      = $combinations->pluck('unit_id')->unique()->filter()->values()->toArray();

        $items = \App\Models\Inventory\Item::whereIn('itemID', $itemIds)
            ->pluck('itemName2', 'itemID')->toArray();

        $types = \App\Models\Inventory\Type::whereIn('id', $typeIds)
            ->pluck('name', 'id')->toArray();

        $warehouses = \App\Models\Inventory\Stock::whereIn('StockID', $warehouseIds)
            ->pluck('StockName', 'StockID')->toArray();

        $units = \App\Models\Inventory\Unit::whereIn('UnitID', $unitIds)
            ->pluck('UnitName', 'UnitID')->toArray();

        // ─────────────────────────────────────────────
        // 3. جلب آخر تفصيل "in" لكل تركيبة (للتكلفة والتسعير)
        //    استعلامان فقط:
        //    a) MAX(movement_detail_id) لكل تركيبة
        //    b) WHERE IN للحصول على التفاصيل الكاملة
        // ─────────────────────────────────────────────
        $latestIds = DB::table('inventory_movement_details as imd')
            ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
            ->where('im.direction', 'in')
            ->groupBy('imd.item_id', 'imd.type_id', 'imd.warehouse_id', 'imd.unit_id')
            ->selectRaw('MAX(imd.movement_detail_id) as max_id')
            ->pluck('max_id')
            ->toArray();

        $latestDetails = InventoryMovementDetail::whereIn('movement_detail_id', $latestIds)
            ->get()
            ->keyBy(function ($d) {
                $typeKey = $d->type_id !== null ? $d->type_id : 'null';
                $unitKey = $d->unit_id !== null ? $d->unit_id : 'null';
                return "{$d->item_id}:{$typeKey}:{$d->warehouse_id}:{$unitKey}";
            });

        // ─────────────────────────────────────────────
        // 4. بناء الصفوف النهائية
        // ─────────────────────────────────────────────
        $rows = [];

        foreach ($combinations as $c) {
            $typeKey = $c->type_id !== null ? $c->type_id : 'null';
            $unitKey = $c->unit_id !== null ? $c->unit_id : 'null';
            $lookupKey = "{$c->item_id}:{$typeKey}:{$c->warehouse_id}:{$unitKey}";

            /** @var InventoryMovementDetail|null $last */
            $last = $latestDetails->get($lookupKey);

            $rows[] = [
                'item_id'        => (int) $c->item_id,
                'item_name'      => $items[$c->item_id] ?? '',
                'type_id'        => $c->type_id !== null ? (int) $c->type_id : null,
                'type_name'      => $c->type_id !== null ? ($types[$c->type_id] ?? '') : '',
                'warehouse_id'   => (int) $c->warehouse_id,
                'warehouse_name' => $warehouses[$c->warehouse_id] ?? '',
                'unit_id'        => $c->unit_id !== null ? (int) $c->unit_id : null,
                'unit_name'      => $c->unit_id !== null ? ($units[$c->unit_id] ?? '') : '',
                'code'           => $last->code ?? '',
                'quantity'       => (float) $c->total_in - (float) $c->total_out,
                'unit_cost'      => $last ? (float) $last->unit_cost : 0,
                'sale_price'     => ($last && $last->sale_price !== null)
                    ? (float) $last->sale_price : 0,
                'min_price'      => ($last && $last->min_price !== null)
                    ? (float) $last->min_price : 0,
                'max_price'      => ($last && $last->max_price !== null)
                    ? (float) $last->max_price : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $rows,
        ]);
    }

    // =====================================================
    // دوال مساعدة داخلية
    // =====================================================

    /**
     * التحقق من بيانات الحركة اليدوية
     */
    private function validateMovement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'movement_type'   => [
                'required', 'string',
                'in:supply,issue,purchase,sale,purchase_return,sale_return',
            ],
            'movement_date'   => ['required', 'date'],
            'warehouse_id'    => ['required', 'exists:stocks,StockID'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'statement'       => ['nullable', 'string'],
            'source_type'     => ['nullable', 'string', 'max:50'],
            'source_id'       => ['nullable', 'integer'],

            'details'                   => ['required', 'array', 'min:1'],
            'details.*.item_id'         => ['required', 'exists:Items,itemID'],
            'details.*.type_id'         => ['nullable', 'exists:type,id'],
            'details.*.unit_id'         => ['nullable', 'exists:units,UnitID'],
            'details.*.code'            => ['nullable', 'string', 'max:50'],
            'details.*.warehouse_id'    => ['required', 'exists:stocks,StockID'],
            'details.*.quantity'        => ['required', 'numeric', 'gt:0'],
            'details.*.unit_cost'       => ['required', 'numeric', 'min:0'],
            'details.*.min_price'       => ['nullable', 'numeric', 'min:0'],
            'details.*.max_price'       => ['nullable', 'numeric', 'min:0'],
            'details.*.sale_price'      => ['nullable', 'numeric', 'min:0'],
        ], [
            'movement_type.required'    => 'نوع الحركة مطلوب',
            'movement_type.in'          => 'نوع الحركة غير صالح',
            'movement_date.required'    => 'تاريخ الحركة مطلوب',
            'warehouse_id.required'     => 'يجب اختيار المخزن',
            'warehouse_id.exists'       => 'المخزن المحدد غير موجود',
            'details.required'          => 'يجب إضافة صنف واحد على الأقل',
            'details.min'               => 'يجب إضافة صنف واحد على الأقل',
            'details.*.item_id.required'=> 'يجب اختيار الصنف في كل الصفوف',
            'details.*.item_id.exists'  => 'أحد الأصناف المحددة غير موجود',
            'details.*.warehouse_id.required' => 'يجب اختيار المخزن في كل الصفوف',
            'details.*.warehouse_id.exists'   => 'أحد المخازن المحددة غير موجود',
            'details.*.quantity.gt'     => 'الكمية يجب أن تكون أكبر من صفر',
            'details.*.unit_cost.min'   => 'تكلفة الوحدة لا يمكن أن تكون سالبة',
        ]);

        $validator->after(function ($v) use ($request) {
            foreach ($request->input('details', []) as $i => $row) {
                $min  = isset($row['min_price']) && $row['min_price'] !== ''
                    ? (float) $row['min_price'] : null;
                $max  = isset($row['max_price']) && $row['max_price'] !== ''
                    ? (float) $row['max_price'] : null;
                $sale = isset($row['sale_price']) && $row['sale_price'] !== ''
                    ? (float) $row['sale_price'] : null;

                if ($min !== null && $max !== null && $min > $max) {
                    $v->errors()->add(
                        "details.{$i}.min_price",
                        'الحد الأدنى للسعر لا يمكن أن يكون أكبر من الحد الأعلى'
                    );
                }

                if ($sale !== null) {
                    if ($min !== null && $sale < $min) {
                        $v->errors()->add(
                            "details.{$i}.sale_price",
                            "سعر البيع أقل من الحد الأدنى ({$min})"
                        );
                    }
                    if ($max !== null && $sale > $max) {
                        $v->errors()->add(
                            "details.{$i}.sale_price",
                            "سعر البيع أكبر من الحد الأعلى ({$max})"
                        );
                    }
                }
            }
        });

        return $validator;
    }

    /**
     * حفظ تفاصيل الحركة اليدوية
     */
    private function saveDetails(InventoryMovement $movement, array $details): void
    {
        foreach ($details as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $unitCost = (float) ($row['unit_cost'] ?? 0);
            $total    = $quantity * $unitCost;

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $row['item_id'],
                'type_id'      => $row['type_id'] ?? null,
                'unit_id'      => $row['unit_id'] ?? null,
                'code'         => $row['code'] ?? null,
                'warehouse_id' => $row['warehouse_id'],
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost,
                'min_price'    => $row['min_price'] ?? null,
                'max_price'    => $row['max_price'] ?? null,
                'sale_price'   => $row['sale_price'] ?? null,
                'total'        => $total,
            ]);
        }
    }

    /**
     * إعادة حساب إجمالي الحركة اليدوية
     */
    private function recalculateTotal(InventoryMovement $movement): void
    {
        $total = $movement->details()->sum('total');

        $movement->total = $total;
        $movement->save();
    }
}