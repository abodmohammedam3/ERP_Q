<?php

namespace App\Http\Controllers\Operation\Movements;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryMovementController extends Controller
{
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
     * ✅ إصلاح: نقل فحص (source_type + source_id) قبل try/catch
     *    السبب: سابقًا كان الفحص بعد catch — كود ميت لا يُنفَّذ أبدًا
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

        // ✅ الفحص الآن قبل try/catch — يُنفَّذ فعليًا
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

            // توليد رقم الحركة على الخادم بشكل آمن
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
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // =====================================================
    // دوال مساعدة داخلية
    // =====================================================

    /**
     * التحقق من البيانات
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

        // تحقق منطقي إضافي
        $validator->after(function ($v) use ($request) {
            foreach ($request->input('details', []) as $i => $row) {
                $min = isset($row['min_price']) && $row['min_price'] !== ''
                    ? (float) $row['min_price'] : null;
                $max = isset($row['max_price']) && $row['max_price'] !== ''
                    ? (float) $row['max_price'] : null;
                $sale = isset($row['sale_price']) && $row['sale_price'] !== ''
                    ? (float) $row['sale_price'] : null;

                // min ≤ max
                if ($min !== null && $max !== null && $min > $max) {
                    $v->errors()->add(
                        "details.{$i}.min_price",
                        'الحد الأدنى للسعر لا يمكن أن يكون أكبر من الحد الأعلى'
                    );
                }

                // sale_price ضمن الحدود (إن وُجدت)
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
     * حفظ التفاصيل
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
     * إعادة حساب إجمالي الحركة
     */
    private function recalculateTotal(InventoryMovement $movement): void
    {
        $total = $movement->details()->sum('total');

        $movement->total = $total;
        $movement->save();
    }
}