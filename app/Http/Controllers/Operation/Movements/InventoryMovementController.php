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

class InventoryMovementController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index()
    {
        return view('operation.movements.index');
    }

    public function list(Request $request)
    {
        $search         = trim($request->input('search', ''));
        $warehouseId    = $request->input('warehouse_id');
        $movementType   = $request->input('movement_type');
        $dateFrom       = $request->input('date_from');
        $dateTo         = $request->input('date_to');

        $query = InventoryMovement::query()
            ->with('warehouse')
            ->orderByDesc('movement_id');

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

        $movements = $query->limit(200)->get()->map(function ($m) {
            return [
                'movement_id'      => $m->movement_id,
                'display_id'       => $m->display_id,
                'movement_type'    => $m->movement_type,
                'movement_label'   => InventoryMovement::labelForType($m->movement_type),
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
            return response()->json(['message' => 'ط§ظ„ط­ط±ظƒط© ط؛ظٹط± ظ…ظˆط¬ظˆط¯ط©'], 404);
        }

        return response()->json([
            'header' => [
                'movement_id'      => $movement->movement_id,
                'display_id'       => $movement->display_id,
                'movement_type'    => $movement->movement_type,
                'movement_label'   => InventoryMovement::labelForType($movement->movement_type),
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

    public function nextNumber()
    {
        $last = InventoryMovement::orderBy('movement_id', 'desc')->first();
        $next = $last ? ((int) $last->display_id + 1) : 1;

        return response()->json(['next_number' => (string) $next]);
    }

    public function store(Request $request)
    {
        $validator = $this->validateMovement($request);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ط¨ظٹط§ظ†ط§طھ ط؛ظٹط± طµط­ظٹط­ط©',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->filled('source_type') && $request->filled('source_id')) {
            $exists = InventoryMovement::where('source_type', $request->input('source_type'))
                ->where('source_id', $request->input('source_id'))
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'طھظˆط¬ط¯ ط­ط±ظƒط© ظ…ط±طھط¨ط·ط© ط¨ظ†ظپط³ ط§ظ„ظ…طµط¯ط± ظ…ط³ط¨ظ‚ظ‹ط§',
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

            /* âœ… ط§ظ„ط¨ظٹط§ظ† ط§ظ„طھظ„ظ‚ط§ط¦ظٹ: ظٹظڈط¨ظ†ظ‰ ط¯ط§ط¦ظ…ظ‹ط§ ظ…ظ† ط§ظ„ط­ظ‚ظˆظ„ ط§ظ„ط£ط³ط§ط³ظٹط© (ظˆط«ظˆظ‚ظٹط©)
               ظˆظ„ط§ ظٹط·ظ…ط³ ط£ظٹ ط¨ظٹط§ظ† ظٹط¯ط®ظ„ظ‡ ط§ظ„ظ…ط³طھط®ط¯ظ… â€” ط§ظ„ط¨ظٹط§ظ†ط§طھ ظ…ظˆط¬ظˆط¯ط© ظپظٹ statement/reference/document_number
               ط¥ظ† ظƒط§ظ† ط§ظ„ظ…ط³طھط®ط¯ظ… ط£ط¯ط®ظ„ ط¨ظٹط§ظ†ظ‹ط§ ظ†طµظٹظ‹ط§ ط­ط±ظ‹ط§ ظٹظڈط­ظپط¸ ظƒظ…ط§ ظ‡ظˆ. */
            $statement = $this->buildMovementStatement($request, $nextNumber);

            $movement = InventoryMovement::create([
                'display_id'      => (string) $nextNumber,
                'movement_type'   => $type,
                'direction'       => $direction,
                'movement_date'   => $request->input('movement_date'),
                'document_number' => $request->input('document_number'),
                'warehouse_id'    => $request->input('warehouse_id'),
                'statement'       => $statement,
                'source_type'     => $request->input('source_type'),
                'source_id'       => $request->input('source_id'),
                'total'           => 0,
            ]);

            $this->saveDetails($movement, $request->input('details', []));
            $this->recalculateTotal($movement);

            DB::commit();

            return response()->json([
                'message'     => 'طھظ… ط­ظپط¸ ط§ظ„ط­ط±ظƒط© ط¨ظ†ط¬ط§ط­',
                'movement_id' => $movement->movement_id,
                'display_id'  => $movement->display_id,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'ظپط´ظ„ ط­ظپط¸ ط§ظ„ط­ط±ظƒط©',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =====================================================
    // ط§ظ„ظپط±ط² / ط§ظ„طھط¬ظ‡ظٹط²
    // =====================================================

    /**
     * âœ… ط§ظ„ط¨ظٹط§ظ† ط§ظ„طھظ„ظ‚ط§ط¦ظٹ ظ„ط­ط±ظƒط© ط§ظ„ظ…ط®ط²ظˆظ†
     * - ظ†ظˆط¹ ط§ظ„ط­ط±ظƒط© + ط±ظ‚ظ… ط§ظ„ظ…ط³طھظ†ط¯ + ط§ظ„ظ…ط®ط²ظ†
     * - ط¥ظ† ظƒط§ظ† ط§ظ„ظ…ط³طھط®ط¯ظ… ظƒطھط¨ ط¨ظٹط§ظ†ظ‹ط§ ط­ط±ظ‹ط§ (statement) ظٹظڈط¶ط§ظپ ط¨ط¹ط¯ظ‡ ط¨ط¯ظ„ ط·ظ…ط³ظ‡
     */
    private function buildMovementStatement(Request $request, $displayId): string
    {
        $type      = (string) $request->input('movement_type');
        $typeLabel = InventoryMovement::labelForType($type);

        $parts = ['ط­ط±ظƒط© ' . $typeLabel];

        $docNo = trim((string) $request->input('document_number'));
        if ($docNo !== '') {
            $parts[] = 'ظ…ط³طھظ†ط¯ ط±ظ‚ظ… ' . $docNo;
        } else {
            $parts[] = 'ط±ظ‚ظ… ' . $displayId;
        }

        $warehouseId = $request->input('warehouse_id');
        if ($warehouseId) {
            $warehouseName = \App\Models\Inventory\Stock::where('StockID', $warehouseId)
                ->value('StockName');
            if ($warehouseName) {
                $parts[] = 'ط§ظ„ظ…ط®ط²ظ†: ' . $warehouseName;
            }
        }

        $userStatement = trim((string) $request->input('statement'));
        if ($userStatement !== '') {
            $parts[] = 'ط§ظ„ط¨ظٹط§ظ†: ' . $userStatement;
        }

        return implode(' â€” ', $parts);
    }

    public function sort(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'         => ['required', 'exists:items,itemID'],
            'type_id'         => ['nullable', 'exists:type,id'],
            'warehouse_id'    => ['required', 'exists:stocks,StockID'],
            'input_unit_id'   => ['required', 'exists:units,UnitID'],
            'output_unit_id'  => ['required', 'exists:units,UnitID', 'different:input_unit_id'],
            'input_quantity'  => ['required', 'numeric', 'gt:0'],
            'output_quantity' => ['required', 'numeric', 'gt:0'],
            'code'            => ['nullable', 'string', 'max:50'],
            'supplier_account_id' => ['nullable', 'exists:characcount,accountID'],
            'sale_price'      => ['nullable', 'numeric', 'min:0'],
            'min_price'       => ['nullable', 'numeric', 'min:0'],
            'max_price'       => ['nullable', 'numeric', 'min:0'],
            'movement_date'   => ['nullable', 'date'],
        ], [
            'item_id.required'         => 'ظٹط¬ط¨ ط§ط®طھظٹط§ط± ط§ظ„طµظ†ظپ',
            'item_id.exists'           => 'ط§ظ„طµظ†ظپ ط§ظ„ظ…ط­ط¯ط¯ ط؛ظٹط± ظ…ظˆط¬ظˆط¯',
            'warehouse_id.required'    => 'ظٹط¬ط¨ ط§ط®طھظٹط§ط± ط§ظ„ظ…ط®ط²ظ†',
            'warehouse_id.exists'      => 'ط§ظ„ظ…ط®ط²ظ† ط§ظ„ظ…ط­ط¯ط¯ ط؛ظٹط± ظ…ظˆط¬ظˆط¯',
            'input_unit_id.required'   => 'ظٹط¬ط¨ طھط­ط¯ظٹط¯ ط§ظ„ظˆط­ط¯ط© ط§ظ„ط£طµظ„ظٹط©',
            'input_unit_id.exists'     => 'ط§ظ„ظˆط­ط¯ط© ط§ظ„ط£طµظ„ظٹط© ط؛ظٹط± ظ…ظˆط¬ظˆط¯ط©',
            'output_unit_id.required'  => 'ظٹط¬ط¨ طھط­ط¯ظٹط¯ ط§ظ„ظˆط­ط¯ط© ط§ظ„ظ†ط§طھط¬ط©',
            'output_unit_id.exists'    => 'ط§ظ„ظˆط­ط¯ط© ط§ظ„ظ†ط§طھط¬ط© ط؛ظٹط± ظ…ظˆط¬ظˆط¯ط©',
            'output_unit_id.different' => 'ط§ظ„ظˆط­ط¯ط© ط§ظ„ظ†ط§طھط¬ط© ظٹط¬ط¨ ط£ظ† طھط®طھظ„ظپ ط¹ظ† ط§ظ„ط£طµظ„ظٹط©',
            'input_quantity.required'  => 'ظٹط¬ط¨ ط¥ط¯ط®ط§ظ„ ط§ظ„ظƒظ…ظٹط© ط§ظ„ظ…ظپط±ط²ط©',
            'input_quantity.gt'        => 'ط§ظ„ظƒظ…ظٹط© ط§ظ„ظ…ظپط±ط²ط© ظٹط¬ط¨ ط£ظ† طھظƒظˆظ† ط£ظƒط¨ط± ظ…ظ† طµظپط±',
            'output_quantity.required' => 'ظٹط¬ط¨ ط¥ط¯ط®ط§ظ„ ط¹ط¯ط¯ ط§ظ„ظˆط­ط¯ط§طھ ط§ظ„ظ†ط§طھط¬ط©',
            'output_quantity.gt'       => 'ط¹ط¯ط¯ ط§ظ„ظˆط­ط¯ط§طھ ط§ظ„ظ†ط§طھط¬ط© ظٹط¬ط¨ ط£ظ† ظٹظƒظˆظ† ط£ظƒط¨ط± ظ…ظ† طµظپط±',
        ]);

        $validator->after(function ($v) use ($request) {
            $sale = $request->input('sale_price');
            $min  = $request->input('min_price');
            $max  = $request->input('max_price');

            $saleF = ($sale !== null && $sale !== '') ? (float) $sale : null;
            $minF  = ($min  !== null && $min  !== '') ? (float) $min  : null;
            $maxF  = ($max  !== null && $max  !== '') ? (float) $max  : null;

            if ($saleF !== null && $minF !== null && $saleF < $minF) {
                $v->errors()->add('sale_price', 'ط³ط¹ط± ط§ظ„ط¨ظٹط¹ ط£ظ‚ظ„ ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¯ظ†ظ‰');
            }
            if ($saleF !== null && $maxF !== null && $saleF > $maxF) {
                $v->errors()->add('sale_price', 'ط³ط¹ط± ط§ظ„ط¨ظٹط¹ ط£ظƒط¨ط± ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¹ظ„ظ‰');
            }
            if ($minF !== null && $maxF !== null && $minF > $maxF) {
                $v->errors()->add('min_price', 'ط§ظ„ط­ط¯ ط§ظ„ط£ط¯ظ†ظ‰ ط£ظƒط¨ط± ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¹ظ„ظ‰');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ط¨ظٹط§ظ†ط§طھ ط؛ظٹط± طµط­ظٹط­ط©',
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
                'supplier_account_id' => $request->filled('supplier_account_id')
                    ? (int) $request->input('supplier_account_id')
                    : null,
                'sale_price'      => $request->input('sale_price'),
                'min_price'       => $request->input('min_price'),
                'max_price'       => $request->input('max_price'),
                'movement_date'   => $request->input('movement_date') ?: now()->format('Y-m-d'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'طھظ… ط§ظ„ظپط±ط² ط¨ظ†ط¬ط§ط­',
                'data'    => $result,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'ط¨ظٹط§ظ†ط§طھ ط؛ظٹط± طµط­ظٹط­ط©',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Inventory sort failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'ظپط´ظ„ ط§ظ„ظپط±ط²',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ط¹ظƒط³ ط¹ظ…ظ„ظٹط© ظپط±ط²
     *
     * âœ… ط§ظ„ط¥طµظ„ط§ط­ط§طھ:
     *  1. ظپط­طµ ط§ظ„ط±طµظٹط¯ ط£طµط¨ط­ ط¯ط§ط®ظ„ ط§ظ„ظ€ Transaction
     *  2. ظ‚ظپظ„ طµظپظˆظپ ط§ظ„ظ…ط®ط²ظˆظ† ظ‚ط¨ظ„ ط§ظ„ظپط­طµ
     */
    public function reverseSort(Request $request, string $documentNumber)
    {
        try {
            return DB::transaction(function () use ($documentNumber) {

                $movements = InventoryMovement::with('details')
                    ->where('document_number', $documentNumber)
                    ->where('source_type', InventoryMovement::SOURCE_SORTING)
                    ->orderBy('movement_id')
                    ->get();

                if ($movements->count() < 2) {
                    return response()->json([
                        'message' => 'ظ„ظ… ظٹطھظ… ط§ظ„ط¹ط«ظˆط± ط¹ظ„ظ‰ ط¹ظ…ظ„ظٹط© ظپط±ط² طµط§ظ„ط­ط© ط¨ظ‡ط°ط§ ط§ظ„ط±ظ‚ظ…',
                    ], 404);
                }

                $reversalDoc = 'REV-' . $documentNumber;

                $alreadyReversed = InventoryMovement::where('document_number', $reversalDoc)->exists();

                if ($alreadyReversed) {
                    return response()->json([
                        'message' => 'طھظ… ط¹ظƒط³ ظ‡ط°ظ‡ ط§ظ„ط¹ظ…ظ„ظٹط© ظ…ط³ط¨ظ‚ظ‹ط§',
                    ], 409);
                }

                $outMovement = $movements->firstWhere('direction', 'out');
                $inMovement  = $movements->firstWhere('direction', 'in');

                if (!$outMovement || !$inMovement) {
                    return response()->json([
                        'message' => 'ط¨ظٹط§ظ†ط§طھ ط¹ظ…ظ„ظٹط© ط§ظ„ظپط±ط² ط؛ظٹط± ظ…ظƒطھظ…ظ„ط©',
                    ], 422);
                }

                $outDetail = $outMovement->details->first();
                $inDetail  = $inMovement->details->first();

                if (!$outDetail || !$inDetail) {
                    return response()->json([
                        'message' => 'طھظپط§طµظٹظ„ ط¹ظ…ظ„ظٹط© ط§ظ„ظپط±ط² ط؛ظٹط± ظ…ظƒطھظ…ظ„ط©',
                    ], 422);
                }

                // âœ… 1) ظ‚ظپظ„ طµظپظˆظپ ط§ظ„ظ…ط®ط²ظˆظ† ظ‚ط¨ظ„ ط§ظ„ظپط­طµ
                $this->inventoryService->lockStockRows([
                    [
                        'item_id'      => (int) $inDetail->item_id,
                        'warehouse_id' => (int) $inDetail->warehouse_id,
                        'unit_id'      => (int) $inDetail->unit_id,
                    ],
                ]);

                // âœ… 2) ط§ظ„ط¢ظ† ظپط­طµ ط§ظ„ط±طµظٹط¯ ظ…ظˆط«ظˆظ‚ (ط¯ط§ط®ظ„ Transaction + ظ…ط¹ ظ‚ظپظ„)
                $availableOutput = $this->inventoryService->availableQuantity(
                    (int) $inDetail->item_id,
                    (int) $inDetail->warehouse_id,
                    (int) $inDetail->unit_id
                );

                if ($availableOutput < (float) $inDetail->quantity) {
                    return response()->json([
                        'message' => "ظ„ط§ ظٹظ…ظƒظ† ط¹ظƒط³ ط§ظ„ط¹ظ…ظ„ظٹط© â€” ط§ظ„ط±طµظٹط¯ ط§ظ„ط­ط§ظ„ظٹ ({$availableOutput}) ط£ظ‚ظ„ ظ…ظ† ط§ظ„ظ…ط·ظ„ظˆط¨ ({$inDetail->quantity})",
                    ], 422);
                }

                $lastMovement = InventoryMovement::lockForUpdate()
                    ->orderByDesc('movement_id')
                    ->first();

                $nextDisplayId = $lastMovement ? ((int) $lastMovement->display_id + 1) : 1;

                $reverseOutMovement = InventoryMovement::create([
                    'display_id'      => (string) $nextDisplayId,
                    'movement_type'   => InventoryMovement::TYPE_SORTING_OUT,
                    'direction'       => InventoryMovement::DIRECTION_OUT,
                    'movement_date'   => now()->format('Y-m-d'),
                    'document_number' => $reversalDoc,
                    'warehouse_id'    => $inDetail->warehouse_id,
                    'statement'       => "ط¹ظƒط³ ظپط±ط² {$documentNumber}",
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

                $nextDisplayId++;

                $reverseInMovement = InventoryMovement::create([
                    'display_id'      => (string) $nextDisplayId,
                    'movement_type'   => InventoryMovement::TYPE_SORTING_IN,
                    'direction'       => InventoryMovement::DIRECTION_IN,
                    'movement_date'   => now()->format('Y-m-d'),
                    'document_number' => $reversalDoc,
                    'warehouse_id'    => $outDetail->warehouse_id,
                    'statement'       => "ط¹ظƒط³ ظپط±ط² {$documentNumber}",
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

                return response()->json([
                    'success' => true,
                    'message' => 'طھظ… ط¹ظƒط³ ط§ظ„ظپط±ط² ط¨ظ†ط¬ط§ط­',
                    'data'    => [
                        'reversal_document_number' => $reversalDoc,
                        'out_movement_id'          => $reverseOutMovement->movement_id,
                        'in_movement_id'           => $reverseInMovement->movement_id,
                    ],
                ], 201);
            });

        } catch (\Throwable $e) {
            Log::error('Reverse sort failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'ظپط´ظ„ ط¹ظƒط³ ط§ظ„ظپط±ط²',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function printSorting(string $documentNumber)
    {
        $movements = InventoryMovement::with([
            'details.item',
            'details.type',
            'details.unit',
            'warehouse',
        ])
            ->where('document_number', $documentNumber)
            ->where('source_type', InventoryMovement::SOURCE_SORTING)
            ->orderBy('movement_id')
            ->get();

        if ($movements->count() < 2) {
            abort(404, 'ظ„ظ… ظٹطھظ… ط§ظ„ط¹ط«ظˆط± ط¹ظ„ظ‰ ط¹ظ…ظ„ظٹط© ط§ظ„ظپط±ط²');
        }

        $outMovement = $movements->firstWhere('direction', 'out');
        $inMovement  = $movements->firstWhere('direction', 'in');

        if (!$outMovement || !$inMovement) {
            abort(404, 'ط¨ظٹط§ظ†ط§طھ ط¹ظ…ظ„ظٹط© ط§ظ„ظپط±ط² ط؛ظٹط± ظ…ظƒطھظ…ظ„ط©');
        }

        return view('print.sorting', compact(
            'documentNumber',
            'outMovement',
            'inMovement'
        ));
    }

    public function printMovementPage($id)
    {
        $movement = InventoryMovement::with([
            'details.item',
            'details.type',
            'details.unit',
            'details.warehouse',
            'warehouse',
        ])->find($id);

        if (!$movement) {
            abort(404, 'ط§ظ„ط­ط±ظƒط© ط؛ظٹط± ظ…ظˆط¬ظˆط¯ط©');
        }

        return view('print.movement', compact('movement'));
    }

    // =====================================================
    // Helpers
    // =====================================================

    public function available(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'exists:items,itemID'],
            'warehouse_id' => ['required', 'integer', 'exists:stocks,StockID'],
            'unit_id'      => ['nullable', 'integer', 'exists:units,UnitID'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ط¨ظٹط§ظ†ط§طھ ط؛ظٹط± طµط­ظٹط­ط©',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $unitId      = $request->input('unit_id') ? (int) $request->input('unit_id') : null;

        return response()->json([
            'success' => true,
            'data'    => [
                'quantity' => $this->inventoryService->availableQuantity($itemId, $warehouseId, $unitId),
                'cost'     => $unitId !== null
                    ? $this->inventoryService->lastCost($itemId, $warehouseId, $unitId)
                    : 0,
            ],
        ]);
    }

    public function pricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'exists:items,itemID'],
            'warehouse_id' => ['required', 'integer', 'exists:stocks,StockID'],
            'unit_id'      => ['nullable', 'integer', 'exists:units,UnitID'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ط¨ظٹط§ظ†ط§طھ ط؛ظٹط± طµط­ظٹط­ط©',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $unitId      = $request->input('unit_id') ? (int) $request->input('unit_id') : null;

        return response()->json([
            'success' => true,
            'data'    => $this->inventoryService->lastPricing($itemId, $warehouseId, $unitId),
        ]);
    }

    public function stockPricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'exists:items,itemID'],
            'warehouse_id' => ['required', 'integer', 'exists:stocks,StockID'],
            'unit_id'      => ['required', 'integer', 'exists:units,UnitID'],
            'type_id'      => ['nullable', 'integer', 'exists:type,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ط¨ظٹط§ظ†ط§طھ ط؛ظٹط± طµط­ظٹط­ط©',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $unitId      = (int) $request->input('unit_id');
        $typeId      = $request->input('type_id') ? (int) $request->input('type_id') : null;

        $quantity = $this->inventoryService->availableQuantity($itemId, $warehouseId, $unitId);
        $cost     = $this->inventoryService->currentUnitCost($itemId, $typeId, $warehouseId, $unitId);
        $pricing  = $this->inventoryService->lastPricing($itemId, $warehouseId, $unitId);

        $unitName = \App\Models\Inventory\Unit::where('UnitID', $unitId)->value('UnitName') ?? '';

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

    public function updatePricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id'      => ['required', 'integer', 'exists:items,itemID'],
            'warehouse_id' => ['required', 'integer', 'exists:stocks,StockID'],
            'unit_id'      => ['nullable', 'integer', 'exists:units,UnitID'],
            'sale_price'   => ['nullable', 'numeric', 'min:0'],
            'min_price'    => ['nullable', 'numeric', 'min:0'],
            'max_price'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $toNullable = function ($value) {
            if ($value === null || $value === '') return null;
            $n = (float) $value;
            return $n > 0 ? $n : null;
        };

        $saleF = $toNullable($request->input('sale_price'));
        $minF  = $toNullable($request->input('min_price'));
        $maxF  = $toNullable($request->input('max_price'));

        $validator->after(function ($v) use ($saleF, $minF, $maxF) {
            if ($minF !== null && $maxF !== null && $minF > $maxF) {
                $v->errors()->add('min_price', 'ط§ظ„ط­ط¯ ط§ظ„ط£ط¯ظ†ظ‰ ط£ظƒط¨ط± ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¹ظ„ظ‰');
            }
            if ($saleF !== null && $minF !== null && $saleF < $minF) {
                $v->errors()->add('sale_price', 'ط³ط¹ط± ط§ظ„ط¨ظٹط¹ ط£ظ‚ظ„ ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¯ظ†ظ‰');
            }
            if ($saleF !== null && $maxF !== null && $saleF > $maxF) {
                $v->errors()->add('sale_price', 'ط³ط¹ط± ط§ظ„ط¨ظٹط¹ ط£ظƒط¨ط± ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¹ظ„ظ‰');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ط¨ظٹط§ظ†ط§طھ ط؛ظٹط± طµط­ظٹط­ط©',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $unitId      = $request->input('unit_id') ? (int) $request->input('unit_id') : null;
        $supplierId  = $request->filled('supplier_account_id')
            ? (int) $request->input('supplier_account_id')
            : null;

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
            /* âœ… ط§ظ„طھط³ط¹ظٹط± ظٹظڈط­ط¯ظژظ‘ط« ظ„ط¢ط®ط± ط¯ظپط¹ط© ط¯ط®ظˆظ„ ظ„ظ†ظپط³ ط§ظ„ظ…ظˆط±ط¯ طھط­ط¯ظٹط¯ط§ظ‹ */
            ->when(
                $supplierId !== null,
                fn($q) => $q->where('supplier_account_id', $supplierId),
                fn($q) => $q->whereNull('supplier_account_id')
            )
            ->orderByDesc('movement_detail_id')
            ->first();

        if (!$detail) {
            return response()->json([
                'message' => 'ظ„ط§ ظٹظˆط¬ط¯ ط³ط¬ظ„ طھط³ط¹ظٹط± ظ„ظ‡ط°ط§ ط§ظ„طµظ†ظپ ظپظٹ ط§ظ„ظˆط­ط¯ط© ط§ظ„ظ…ط­ط¯ط¯ط©.',
            ], 404);
        }

        $detail->sale_price = $saleF;
        $detail->min_price  = $minF;
        $detail->max_price  = $maxF;
        $detail->save();

        return response()->json([
            'success' => true,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„طھط³ط¹ظٹط± ط¨ظ†ط¬ط§ط­',
            'data'    => [
                'movement_detail_id' => $detail->movement_detail_id,
                'supplier_account_id'=> $detail->supplier_account_id !== null ? (int) $detail->supplier_account_id : null,
                'sale_price'         => $detail->sale_price !== null ? (float) $detail->sale_price : 0,
                'min_price'          => $detail->min_price  !== null ? (float) $detail->min_price  : 0,
                'max_price'          => $detail->max_price  !== null ? (float) $detail->max_price  : 0,
            ],
        ]);
    }

    public function allStockBalances(Request $request)
    {
        /* âœ… ط§ظ„طھط¬ظ…ظٹط¹ ط®ظ…ط§ط³ظٹ: (طµظ†ظپ + ظ†ظˆط¹ + ظ…ط®ط²ظ† + ظˆط­ط¯ط© + ظ…ظˆط±ط¯)
           ظƒظ„ ط¯ظپط¹ط© ظ…ظˆط±ط¯ظٹط© ظ„ظ‡ط§ ط³ط·ط± ظ…ط³طھظ‚ظ„ ط¨طھظƒظ„ظپطھظ‡ط§ ظˆط±طµظٹط¯ظ‡ط§ â€”
           ط´ط±ط§ط، ظ†ظپط³ ط§ظ„طµظ†ظپ ظ…ظ† ظ…ظˆط±ط¯ظٹظ† ظ„ط§ ظٹظڈط¯ظ…ط¬ ط¨ط¹ط¯ ط§ظ„ط¢ظ†.
           supplier_account_id = NULL â‡’ "طھظˆط±ظٹط¯ ط¹ط§ظ…" (supply/ظٹط¯ظˆظٹ/طھط§ط±ظٹط®ظٹ).

           âœ… ط¥ط¶ط§ظپط©: ط§ط³طھط¨ط¹ط§ط¯ ط§ظ„ط£طµظ†ط§ظپ ط§ظ„ظ…ط¹ط·ظ‘ظ„ط© (is_active = 0) ظ…ظ† ط´ط§ط´ط© ط§ظ„ط£ط±طµط¯ط©.
           ط§ظ„طµظ†ظپ ط§ظ„ظ…ط¹ط·ظ‘ظ„ ظٹط®طھظپظٹ ظ…ظ† ظ‡ط°ظ‡ ط§ظ„ط´ط§ط´ط© ظپظ‚ط·طŒ ط¨ظٹظ†ظ…ط§ طھط¨ظ‚ظ‰ ظƒظ„ ط§ظ„ط³ط¬ظ„ط§طھ
           ط§ظ„طھط§ط±ظٹط®ظٹط© (ظپظˆط§طھظٹط±طŒ ظ‚ظٹظˆط¯طŒ ط­ط±ظƒط§طھ) ط³ظ„ظٹظ…ط© ظ„ظ„طھط¯ظ‚ظٹظ‚ ط§ظ„ظ…ط­ط§ط³ط¨ظٹ. */
        $combinations = DB::table('inventory_movement_details as imd')
            ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
            ->leftJoin('characcount as ca', 'ca.accountID', '=', 'imd.supplier_account_id')
            ->join('items as it', 'it.itemID', '=', 'imd.item_id')
            ->where('it.is_active', 1)
            ->select(
                'imd.item_id',
                'imd.type_id',
                'imd.warehouse_id',
                'imd.unit_id',
                'imd.supplier_account_id',
                'ca.accName as supplier_name',
                DB::raw("SUM(CASE WHEN im.direction = 'in' THEN imd.quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN im.direction = 'out' THEN imd.quantity ELSE 0 END) as total_out")
            )
            ->groupBy(
                'imd.item_id',
                'imd.type_id',
                'imd.warehouse_id',
                'imd.unit_id',
                'imd.supplier_account_id',
                'ca.accName'
            )
            ->get()
            ->filter(function ($c) {
                return ((float) $c->total_in - (float) $c->total_out) > 0.000001;
            })
            ->values();

        if ($combinations->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

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

        $latestIds = DB::table('inventory_movement_details as imd')
            ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
            ->where('im.direction', 'in')
            ->groupBy('imd.item_id', 'imd.type_id', 'imd.warehouse_id', 'imd.unit_id', 'imd.supplier_account_id')
            ->selectRaw('MAX(imd.movement_detail_id) as max_id')
            ->pluck('max_id')
            ->toArray();

        $latestDetails = InventoryMovementDetail::whereIn('movement_detail_id', $latestIds)
            ->get()
            ->keyBy(function ($d) {
                $typeKey = $d->type_id !== null ? $d->type_id : 'null';
                $unitKey = $d->unit_id !== null ? $d->unit_id : 'null';
                $supKey  = $d->supplier_account_id !== null ? $d->supplier_account_id : 'null';
                return "{$d->item_id}:{$typeKey}:{$d->warehouse_id}:{$unitKey}:{$supKey}";
            });

        $rows = [];

        foreach ($combinations as $c) {
            $typeKey = $c->type_id !== null ? $c->type_id : 'null';
            $unitKey = $c->unit_id !== null ? $c->unit_id : 'null';
            $supKey  = $c->supplier_account_id !== null ? $c->supplier_account_id : 'null';
            $lookupKey = "{$c->item_id}:{$typeKey}:{$c->warehouse_id}:{$unitKey}:{$supKey}";

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
                'supplier_account_id' => $c->supplier_account_id !== null ? (int) $c->supplier_account_id : null,
                'supplier_name'  => $c->supplier_name ?? '',
                'code'           => $last->code ?? '',
                'quantity'       => (float) $c->total_in - (float) $c->total_out,
                'unit_cost'      => $last ? (float) $last->unit_cost : 0,
                'sale_price'     => ($last && $last->sale_price !== null) ? (float) $last->sale_price : 0,
                'min_price'      => ($last && $last->min_price !== null)  ? (float) $last->min_price  : 0,
                'max_price'      => ($last && $last->max_price !== null)  ? (float) $last->max_price  : 0,
                /* ظ…ظپطھط§ط­ طھط±طھظٹط¨ ظ…ط¤ظ‚طھ: ط¢ط®ط± ط­ط±ظƒط© ط¯ط®ظˆظ„ ظ„ظƒظ„ طھط±ظƒظٹط¨ط© */
                '_order'         => $last ? (int) $last->movement_detail_id : 0,
            ];
        }

        /* âœ… ط§ظ„ط£ط­ط¯ط« ط¯ط®ظˆظ„ط§ظ‹ ظپظٹ ط§ظ„ط£ط¹ظ„ظ‰ (ط­ط³ط¨ ط£ظƒط¨ط± movement_detail_id ظ„ظƒظ„ طھط±ظƒظٹط¨ط©) */
        usort($rows, fn ($a, $b) => $b['_order'] <=> $a['_order']);

        foreach ($rows as &$row) {
            unset($row['_order']);
        }
        unset($row);

        return response()->json([
            'success' => true,
            'data'    => $rows,
        ]);
    }

    // =====================================================
    // ط¯ظˆط§ظ„ ظ…ط³ط§ط¹ط¯ط©
    // =====================================================

    private function validateMovement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'movement_type'   => [
                'required', 'string',
                'in:supply,issue,purchase,sale,purchase_return,sale_return,sorting_out,sorting_in',
            ],
            'movement_date'   => ['required', 'date'],
            'warehouse_id'    => ['required', 'exists:stocks,StockID'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'statement'       => ['nullable', 'string'],
            'source_type'     => ['nullable', 'string', 'max:50'],
            'source_id'       => ['nullable', 'integer'],

            'details'                   => ['required', 'array', 'min:1'],
            'details.*.item_id'         => ['required', 'exists:items,itemID'],
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
            'movement_type.required'    => 'ظ†ظˆط¹ ط§ظ„ط­ط±ظƒط© ظ…ط·ظ„ظˆط¨',
            'movement_type.in'          => 'ظ†ظˆط¹ ط§ظ„ط­ط±ظƒط© ط؛ظٹط± طµط§ظ„ط­',
            'movement_date.required'    => 'طھط§ط±ظٹط® ط§ظ„ط­ط±ظƒط© ظ…ط·ظ„ظˆط¨',
            'warehouse_id.required'     => 'ظٹط¬ط¨ ط§ط®طھظٹط§ط± ط§ظ„ظ…ط®ط²ظ†',
            'warehouse_id.exists'       => 'ط§ظ„ظ…ط®ط²ظ† ط§ظ„ظ…ط­ط¯ط¯ ط؛ظٹط± ظ…ظˆط¬ظˆط¯',
            'details.required'          => 'ظٹط¬ط¨ ط¥ط¶ط§ظپط© طµظ†ظپ ظˆط§ط­ط¯ ط¹ظ„ظ‰ ط§ظ„ط£ظ‚ظ„',
            'details.min'               => 'ظٹط¬ط¨ ط¥ط¶ط§ظپط© طµظ†ظپ ظˆط§ط­ط¯ ط¹ظ„ظ‰ ط§ظ„ط£ظ‚ظ„',
            'details.*.item_id.required'=> 'ظٹط¬ط¨ ط§ط®طھظٹط§ط± ط§ظ„طµظ†ظپ ظپظٹ ظƒظ„ ط§ظ„طµظپظˆظپ',
            'details.*.item_id.exists'  => 'ط£ط­ط¯ ط§ظ„ط£طµظ†ط§ظپ ط§ظ„ظ…ط­ط¯ط¯ط© ط؛ظٹط± ظ…ظˆط¬ظˆط¯',
            'details.*.warehouse_id.required' => 'ظٹط¬ط¨ ط§ط®طھظٹط§ط± ط§ظ„ظ…ط®ط²ظ† ظپظٹ ظƒظ„ ط§ظ„طµظپظˆظپ',
            'details.*.warehouse_id.exists'   => 'ط£ط­ط¯ ط§ظ„ظ…ط®ط§ط²ظ† ط§ظ„ظ…ط­ط¯ط¯ط© ط؛ظٹط± ظ…ظˆط¬ظˆط¯',
            'details.*.quantity.gt'     => 'ط§ظ„ظƒظ…ظٹط© ظٹط¬ط¨ ط£ظ† طھظƒظˆظ† ط£ظƒط¨ط± ظ…ظ† طµظپط±',
            'details.*.unit_cost.min'   => 'طھظƒظ„ظپط© ط§ظ„ظˆط­ط¯ط© ظ„ط§ ظٹظ…ظƒظ† ط£ظ† طھظƒظˆظ† ط³ط§ظ„ط¨ط©',
        ]);

        $validator->after(function ($v) use ($request) {
            foreach ($request->input('details', []) as $i => $row) {
                $min  = isset($row['min_price']) && $row['min_price'] !== '' ? (float) $row['min_price'] : null;
                $max  = isset($row['max_price']) && $row['max_price'] !== '' ? (float) $row['max_price'] : null;
                $sale = isset($row['sale_price']) && $row['sale_price'] !== '' ? (float) $row['sale_price'] : null;

                if ($min !== null && $max !== null && $min > $max) {
                    $v->errors()->add("details.{$i}.min_price", 'ط§ظ„ط­ط¯ ط§ظ„ط£ط¯ظ†ظ‰ ط£ظƒط¨ط± ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¹ظ„ظ‰');
                }
                if ($sale !== null) {
                    if ($min !== null && $sale < $min) {
                        $v->errors()->add("details.{$i}.sale_price", "ط³ط¹ط± ط§ظ„ط¨ظٹط¹ ط£ظ‚ظ„ ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¯ظ†ظ‰ ({$min})");
                    }
                    if ($max !== null && $sale > $max) {
                        $v->errors()->add("details.{$i}.sale_price", "ط³ط¹ط± ط§ظ„ط¨ظٹط¹ ط£ظƒط¨ط± ظ…ظ† ط§ظ„ط­ط¯ ط§ظ„ط£ط¹ظ„ظ‰ ({$max})");
                    }
                }
            }
        });

        return $validator;
    }

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
                'supplier_account_id'  => $row['supplier_account_id'] ?? null, /* طھظˆط±ظٹط¯ ظٹط¯ظˆظٹ: ظ…ظˆط±ط¯ ط§ط®طھظٹط§ط±ظٹ */
                'lot_source_detail_id' => $row['lot_source_detail_id'] ?? null,
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost,
                'min_price'    => $row['min_price'] ?? null,
                'max_price'    => $row['max_price'] ?? null,
                'sale_price'   => $row['sale_price'] ?? null,
                'total'        => $total,
            ]);
        }
    }

    private function recalculateTotal(InventoryMovement $movement): void
    {
        $total = $movement->details()->sum('total');
        $movement->total = $total;
        $movement->save();
    }
}