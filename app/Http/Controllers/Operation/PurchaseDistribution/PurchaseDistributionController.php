<?php

namespace App\Http\Controllers\Operation\PurchaseDistribution;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CharAccount;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Distribution;
use App\Models\Purchases\DistributionDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use Illuminate\Support\Facades\Log;
use App\Models\Inventory\Item;
use App\Models\Customer;
use App\Models\Sales\SalesInvoice;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseDistributionController extends Controller
{
    // ============================================================
    // الشاشات
    // ============================================================
    public function index()
    {
        return view('operation.purchase-distribution.index');
    }

    public function customerInvoicesPage()
    {
        return redirect('/operation/purchases/distribution?tab=issue');
    }

    public function historyPage()
    {
        return redirect('/operation/purchases/distribution?tab=history');
    }

    // ============================================================
    // فواتير الشراء المرحّلة
    // ============================================================
    public function invoices(Request $request)
    {
        $q = PurchaseInvoice::query()
            ->with('supplierAccount')
            ->where('is_posted', 1);

        if ($request->filled('date')) {
            $q->whereDate('invoice_date', $request->input('date'));
        }
        if ($request->filled('supplier')) {
            $term = $request->input('supplier');
            $q->whereHas('supplierAccount', fn($s) => $s->where('accName', 'like', "%{$term}%"));
        }

        $rows = $q->orderByDesc('purchase_invoice_id')
            ->limit(500)
            ->get()
            ->map(fn($inv) => [
                'id'             => $inv->purchase_invoice_id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date'   => optional($inv->invoice_date)->format('Y-m-d'),
                'supplier'       => $inv->supplierAccount?->accName ?? '—',
                'total'          => (float) $inv->total_in_invoice_currency,
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ============================================================
    // أصناف فاتورة محددة + المفرزات
    // ============================================================
    public function items(Request $request, int $id)
    {
        $excludeId = $request->input('exclude_distribution_id');

        $invoice = PurchaseInvoice::query()
            ->with('supplierAccount')
            ->where('is_posted', 1)
            ->findOrFail($id);

        $movement = InventoryMovement::query()
            ->where('source_type', InventoryMovement::SOURCE_PURCHASE_INVOICE)
            ->where('source_id', $invoice->purchase_invoice_id)
            ->where('movement_type', InventoryMovement::TYPE_PURCHASE)
            ->first();

        if (! $movement) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد حركة مخزون مرتبطة بهذه الفاتورة.',
                'data'    => [],
            ]);
        }

        // 1) دفعات الفاتورة الأصلية
        $originalDetails = InventoryMovementDetail::query()
            ->with(['item', 'type', 'unit'])
            ->where('movement_id', $movement->movement_id)
            ->get();

        // 2) كل المفرزات المرتبطة (متعدد المستويات)
        $allIds    = $originalDetails->pluck('movement_detail_id')->toArray();
        $visited   = $allIds;
        $sortedIds = [];
        $maxDepth  = 10;
        $depth     = 0;

        do {
            if ($depth++ > $maxDepth) {
                throw new \RuntimeException(
                    'تجاوز عمق تتبع الفرزات الحد الأقصى (' . $maxDepth . '). '
                    . 'قد تكون هناك حلقة دائرية في البيانات.'
                );
            }

            $newIds = DB::table('inventory_movement_details as imd')
                ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
                ->whereIn('imd.lot_source_detail_id', $allIds)
                ->where('im.movement_type', InventoryMovement::TYPE_SORTING_IN)
                ->whereNotIn('imd.movement_detail_id', $visited)
                ->pluck('imd.movement_detail_id')
                ->toArray();

            if (!empty($newIds)) {
                $sortedIds = array_merge($sortedIds, $newIds);
                $allIds    = array_merge($allIds, $newIds);
                $visited   = array_merge($visited, $newIds);
            }
        } while (!empty($newIds));

        // 3) جلب المفرزات
        $sortedDetails = !empty($sortedIds)
            ? InventoryMovementDetail::query()
                ->with(['item', 'type', 'unit'])
                ->whereIn('movement_detail_id', $sortedIds)
                ->get()
            : collect();

        // 4) دمج الدفعات الأصلية + المفرزات
        $allDetails = $originalDetails->merge($sortedDetails);

        // 5) الموزّع من كل دفعة (مع استثناء التوزيع الحالي إن وجد)
        $distributedQuery = DistributionDetail::query()
            ->select('movement_detail_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('movement_detail_id', $allDetails->pluck('movement_detail_id'))
            ->whereHas('distribution');

        // ✅ استثناء التوزيع الحالي عند التعديل
        if ($excludeId) {
            $distributedQuery->where('distribution_id', '!=', $excludeId);
        }

        $distributed = $distributedQuery->groupBy('movement_detail_id')->pluck('total', 'movement_detail_id');

        // 6) المصروف من كل دفعة (sorting_out)
        $sortedOut = DB::table('inventory_movement_details as imd')
            ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
            ->whereIn('imd.lot_source_detail_id', $allDetails->pluck('movement_detail_id'))
            ->where('im.movement_type', InventoryMovement::TYPE_SORTING_OUT)
            ->select('imd.lot_source_detail_id', DB::raw('SUM(imd.quantity) as total'))
            ->groupBy('imd.lot_source_detail_id')
            ->pluck('total', 'lot_source_detail_id');

        // 7) بناء الصفوف
        $rows = $allDetails->map(function ($d) use ($distributed, $sortedOut, $invoice, $originalDetails, $sortedIds) {
            $dist = (float) ($distributed[$d->movement_detail_id] ?? 0);
            $sort = (float) ($sortedOut[$d->movement_detail_id] ?? 0);
            $avail = max(0, (float) $d->quantity - $dist - $sort);

            return [
                'id'                  => $d->movement_detail_id,
                'movement_id'         => $d->movement_id,
                'purchase_invoice_id' => $invoice->purchase_invoice_id,
                'item_name'           => $d->item?->itemName2 ?? '—',
                'type_name'           => $d->type?->name ?? '—',
                'unit_name'           => $d->unit?->UnitName ?? '—',
                'code'                => $d->code ?? '—',
                'quantity'            => (float) $d->quantity,
                'distributed'         => $dist,
                'sorted_out'          => $sort,
                'available'           => $avail,
                'unit_cost'           => (float) $d->unit_cost,
                'sale_price'          => (float) $d->sale_price,
                'is_sorted'           => in_array($d->movement_detail_id, $sortedIds, true),
            ];
        })->filter(fn($r) => $r['available'] > 0.000001)->values();

        return response()->json([
            'success' => true,
            'invoice' => [
                'id'             => $invoice->purchase_invoice_id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date'   => optional($invoice->invoice_date)->format('Y-m-d'),
                'supplier'       => $invoice->supplierAccount?->accName ?? '—',
            ],
            'data' => $rows,
        ]);
    }

    // ============================================================
    // بحث العملاء
    // ============================================================
    public function customers(Request $request)
    {
        $term = trim((string) $request->input('q', ''));

        $query = Customer::query()
            ->with('account')
            ->where('is_active', 1);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('CustomersName2', 'like', "%{$term}%")
                  ->orWhere('CusPhone', 'like', "%{$term}%");
            });
        }

        $rows = $query->orderBy('CustomersName2')
            ->limit(50)
            ->get()
            ->map(fn($c) => [
                'id'    => $c->CustomersID,
                'code'  => $c->account?->accCode ?? '',
                'name'  => $c->CustomersName2,
                'phone' => $c->CusPhone ?? '',
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ============================================================
    // بحث حسابات الدفع
    // ============================================================
    public function paymentAccounts(Request $request)
    {
        $term   = trim((string) $request->input('q', ''));
        $method = (int) $request->input('method', 0);

        $query = CharAccount::query();

        if ($method === 1) {
            $query->where('accCode', 'like', '1101%');
        } elseif ($method === 2) {
            $query->where('accCode', 'like', '1102%');
        }

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('accName', 'like', "%{$term}%")
                  ->orWhere('accCode', 'like', "%{$term}%");
            });
        }

        $rows = $query->orderBy('accCode')
            ->orderBy('accName')
            ->limit(50)
            ->get()
            ->map(fn($a) => [
                'id'   => $a->accountID,
                'code' => $a->accCode ?? '',
                'name' => $a->accName ?? '',
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'id'                => ['nullable', 'integer', 'exists:distributions,id'],
            'distribution_date' => ['required', 'date_format:Y-m-d'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            'details'           => ['required', 'array', 'min:1'],
            'details.*.movement_detail_id' => ['required', 'integer', 'exists:inventory_movement_details,movement_detail_id'],
            'details.*.customer_id'        => ['required', 'integer', 'exists:customers,CustomersID'],
            'details.*.quantity'           => ['required', 'numeric', 'min:0.000001'],
        ]);

        $isUpdate = ! empty($validated['id']);
        $existing = $isUpdate ? Distribution::findOrFail($validated['id']) : null;

        // جمع الكميات المطلوبة لكل دفعة
        $requested = [];
        foreach ($validated['details'] as $row) {
            $mdId = (int) $row['movement_detail_id'];
            $requested[$mdId] = ($requested[$mdId] ?? 0) + (float) $row['quantity'];
        }

        $movementDetails = InventoryMovementDetail::with(['movement', 'item'])
            ->whereIn('movement_detail_id', array_keys($requested))
            ->get()
            ->keyBy('movement_detail_id');

        // ✅ 1) حدد الفاتورة الأصلية لكل دفعة (يتتبع سلسلة الفرز)
        $rootInvoices = [];
        foreach ($movementDetails as $mdId => $md) {
            $rootId = $this->resolveRootPurchaseInvoiceId($md);
            if ($rootId === null) {
                $itemName = $md->item?->itemName2 ?? "#{$mdId}";
                throw ValidationException::withMessages([
                    'details' => "الصنف «{$itemName}» ليس من فاتورة شراء (ولا من فرز مرتبط بفاتورة).",
                ]);
            }
            $rootInvoices[$mdId] = $rootId;
        }

        // ✅ 2) الكمية المصروفة بالفرز من كل دفعة (sorting_out)
        $sortedOutQty = DB::table('inventory_movement_details as imd')
            ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
            ->whereIn('imd.lot_source_detail_id', array_keys($requested))
            ->where('im.movement_type', InventoryMovement::TYPE_SORTING_OUT)
            ->select('imd.lot_source_detail_id', DB::raw('SUM(imd.quantity) as total'))
            ->groupBy('imd.lot_source_detail_id')
            ->pluck('total', 'lot_source_detail_id')
            ->toArray();

        // 3) الكمية الموزعة مسبقًا (باستثناء التوزيع الحالي عند التعديل)
        $query = DistributionDetail::query()
            ->select('movement_detail_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('movement_detail_id', array_keys($requested))
            ->whereHas('distribution');

        if ($existing) {
            $query->where('distribution_id', '!=', $existing->id);
        }
        $alreadyDistributed = $query->groupBy('movement_detail_id')->pluck('total', 'movement_detail_id');

        // 4) تحقق من الكميات المتاحة = quantity - distributed - sorted_out
        foreach ($requested as $mdId => $qty) {
            $source = $movementDetails->get($mdId);
            if (! $source) {
                throw ValidationException::withMessages([
                    'details' => "الصنف رقم {$mdId} غير موجود.",
                ]);
            }

            $available = (float) $source->quantity
                       - (float) ($alreadyDistributed[$mdId] ?? 0)
                       - (float) ($sortedOutQty[$mdId] ?? 0);

            if ($qty > $available + 0.000001) {
                $itemName = $source->item?->itemName2 ?? 'الصنف';
                throw ValidationException::withMessages([
                    'details' => "الكمية المتاحة لـ «{$itemName}» هي {$available} فقط (المطلوب {$qty}).",
                ]);
            }
        }

        DB::beginTransaction();
        try {
            if ($existing) {
                $distribution = $existing;

                $updateData = [
                    'distribution_date' => $validated['distribution_date'],
                    'notes'             => $validated['notes'] ?? null,
                ];

                // ✅ إرجاع التعديل على المفوتر (تحويله لمسودة)
                if ($distribution->status === Distribution::STATUS_INVOICED) {
                    $updateData['status']      = Distribution::STATUS_DRAFT;
                    $updateData['invoiced_at'] = null;
                    $updateData['invoiced_by'] = null;
                }

                $distribution->update($updateData);
                $distribution->details()->delete();
            } else {
                $distribution = Distribution::create([
                    'code'              => Distribution::nextCode(),
                    'distribution_date' => $validated['distribution_date'],
                    'status'            => Distribution::STATUS_DRAFT,
                    'notes'             => $validated['notes'] ?? null,
                    'created_by'        => auth()->id(),
                ]);
            }

            foreach ($validated['details'] as $row) {
                $mdId   = (int) $row['movement_detail_id'];
                $source = $movementDetails->get($mdId);

                DistributionDetail::create([
                    'distribution_id'     => $distribution->id,
                    'purchase_invoice_id' => $rootInvoices[$mdId],   // ✅ الفاتورة الأصلية
                    'movement_id'         => $source->movement_id,
                    'movement_detail_id'  => $mdId,
                    'customer_id'         => (int) $row['customer_id'],
                    'quantity'            => (float) $row['quantity'],
                    'unit_cost'           => (float) ($source->unit_cost ?? 0),
                    'sale_price'          => (float) ($source->sale_price ?? 0),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => $isUpdate ? 'تم تحديث التوزيع.' : 'تم حفظ التوزيع.',
            'id'      => $distribution->id,
            'code'    => $distribution->code,
        ]);
    }

    /**
     * يتتبع سلسلة lot_source_detail_id للوصول إلى رقم فاتورة الشراء الأصلية.
     * يعيد null إذا لم يكن للدفعة أصل فاتورة شراء.
     */
    private function resolveRootPurchaseInvoiceId(InventoryMovementDetail $md): ?int
    {
        $current = $md;
        $visited = [];
        $maxDepth = 20;

        while ($current && $maxDepth-- > 0) {
            if (in_array($current->movement_detail_id, $visited, true)) {
                return null; // حلقة دائرية
            }
            $visited[] = $current->movement_detail_id;

            $movement = $current->movement
                ?: $current->movement()->first();

            if ($movement && $movement->source_type === InventoryMovement::SOURCE_PURCHASE_INVOICE) {
                return (int) $movement->source_id;
            }

            if (!$current->lot_source_detail_id) {
                return null; // وصلنا لدفعة لا فاتورة لها
            }

            $current = InventoryMovementDetail::with('movement')
                ->find($current->lot_source_detail_id);

            if (!$current) return null;
        }

        return null;
    }

    // ============================================================
    // عرض توزيع (كامل)
    // ============================================================
    public function show(int $id)
    {
        $dist = Distribution::with([
            'details.customer',
            'details.purchaseInvoice',
            'details.movementDetail.item',
            'details.movementDetail.type',
            'details.movementDetail.unit',
        ])->findOrFail($id);

        $rows = $dist->details->map(fn($d) => [
            'id'                  => $d->id,
            'movement_detail_id'  => $d->movement_detail_id,
            'movement_id'         => $d->movement_id,
            'purchase_invoice_id' => $d->purchase_invoice_id,
            'invoice_number'      => $d->purchaseInvoice?->invoice_number,
            'customer_id'         => $d->customer_id,
            'customer_name'       => $d->customer?->CustomersName2,
            'customer_phone'      => $d->customer?->CusPhone,
            'item_name'           => $d->movementDetail?->item?->itemName2 ?? '—',
            'type_name'           => $d->movementDetail?->type?->name ?? '—',
            'unit_name'           => $d->movementDetail?->unit?->UnitName ?? '—',
            'code'                => $d->movementDetail?->code ?? '—',
            'quantity'            => (float) $d->quantity,
            'unit_cost'           => (float) $d->unit_cost,
            'sale_price'          => (float) $d->sale_price,
            'total_cost'          => (float) $d->quantity * (float) $d->unit_cost,
            'total_sale'          => (float) $d->quantity * (float) $d->sale_price,
            'sales_invoice_id'    => $d->sales_invoice_id,
            'invoiced_at'         => optional($d->invoiced_at)->format('Y-m-d H:i'),
            'is_invoiced'         => $d->invoiced_at !== null || $d->sales_invoice_id !== null,
        ]);

        $customersSet = $rows->pluck('customer_id')->unique()->count();
        $qtyByUnit    = [];
        $totalCost    = 0.0;
        $totalSale    = 0.0;
        foreach ($rows as $r) {
            $u = $r['unit_name'] ?: '—';
            $qtyByUnit[$u] = ($qtyByUnit[$u] ?? 0) + $r['quantity'];
            $totalCost += $r['total_cost'];
            $totalSale += $r['total_sale'];
        }

        return response()->json([
            'success' => true,
            'header'  => [
                'id'                => $dist->id,
                'code'              => $dist->code,
                'distribution_date' => optional($dist->distribution_date)->format('Y-m-d'),
                'status'            => $dist->status,
                'status_label'      => $dist->isDraft() ? 'مسودة' : 'مفوتر',
                'notes'             => $dist->notes,
                'is_draft'          => $dist->isDraft(),
            ],
            'summary' => [
                'details_count'   => $rows->count(),
                'customers_count' => $customersSet,
                'qty_by_unit'     => $qtyByUnit,
                'total_cost'      => $totalCost,
                'total_sale'      => $totalSale,
            ],
            'details' => $rows,
        ]);
    }

    // ============================================================
    // حذف توزيع
    // ============================================================
    public function destroy(int $id)
    {
        $dist = Distribution::findOrFail($id);

        $hasInvoiced = DistributionDetail::where('distribution_id', $id)
            ->where(function ($q) {
                $q->whereNotNull('sales_invoice_id')
                  ->orWhereNotNull('invoiced_at');
            })
            ->exists();

        if ($hasInvoiced) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف توزيع يحتوي صفوفًا مفوترة.',
            ], 422);
        }

        if (! $dist->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف توزيع بحالة غير مسودة.',
            ], 422);
        }

        $dist->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف التوزيع.']);
    }

    // ============================================================
    // قائمة التوزيعات (سجل)
    // ============================================================
    public function list(Request $request)
    {
        $q = Distribution::query()
            ->with(['details:id,distribution_id,customer_id,quantity,unit_cost,sale_price']);

        // ✅ إرجاع فلتر الحالة (لعرض المفوتر والمسودات)
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        
        if ($request->filled('date_from')) {
            $q->whereDate('distribution_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('distribution_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('search')) {
            $q->where('code', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->filled('customer_id')) {
            $q->whereHas('details', fn($d) => $d->where('customer_id', (int)$request->input('customer_id')));
        }

        $rows = $q->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(function ($d) {
                $customersCount = $d->details->pluck('customer_id')->unique()->count();
                $totalCost = 0.0;
                $totalSale = 0.0;
                foreach ($d->details as $r) {
                    $totalCost += (float)$r->quantity * (float)$r->unit_cost;
                    $totalSale += (float)$r->quantity * (float)$r->sale_price;
                }

                return [
                    'id'                => $d->id,
                    'code'              => $d->code,
                    'distribution_date' => optional($d->distribution_date)->format('Y-m-d'),
                    'status'            => $d->status,
                    'status_label'      => $d->isDraft() ? 'مسودة' : 'مفوتر',
                    'details_count'     => $d->details->count(),
                    'customers_count'   => $customersCount,
                    'total_cost'        => $totalCost,
                    'total_sale'        => $totalSale,
                ];
            });

        $stats = [
            'total'    => Distribution::count(),
            'draft'    => Distribution::where('status', 'draft')->count(),
            'invoiced' => Distribution::where('status', 'invoiced')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $rows,
            'stats'   => $stats,
        ]);
    }

    // ============================================================
    // إحصائيات
    // ============================================================
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total'    => Distribution::count(),
                'draft'    => Distribution::where('status', 'draft')->count(),
                'invoiced' => Distribution::where('status', 'invoiced')->count(),
            ],
        ]);
    }

    // ============================================================
    // توزيعات عميل (غير مفوترة)
    // ============================================================
    public function customerDistributions(int $customerId)
    {
        $customer = Customer::with('account')->findOrFail($customerId);

        $distributions = Distribution::query()
            ->with(['details' => function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                  ->whereNull('sales_invoice_id')
                  ->with([
                      'purchaseInvoice.coin',
                      'movementDetail.item',
                      'movementDetail.type',
                      'movementDetail.unit',
                  ]);
            }])
            ->whereHas('details', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)->whereNull('sales_invoice_id');
            })
            ->orderByDesc('id')
            ->get();

        $firstInvoice = $distributions->first()?->details->first()?->purchaseInvoice;
        $coinCode     = $firstInvoice?->coin?->coinsCode ?? 'ر.ي';
        $exchangeRate = (float) ($firstInvoice?->exchange_rate ?: 1);

        return response()->json([
            'success'  => true,
            'customer' => [
                'id'    => $customer->CustomersID,
                'name'  => $customer->CustomersName2,
                'phone' => $customer->CusPhone,
                'code'  => $customer->account?->accCode ?? '',
            ],
            'currency' => [
                'code'          => $coinCode,
                'exchange_rate' => $exchangeRate,
            ],
            'data' => $distributions->map(fn($d) => [
                'id'                => $d->id,
                'code'              => $d->code,
                'distribution_date' => optional($d->distribution_date)->format('Y-m-d'),
                'details' => $d->details->map(fn($row) => [
                    'id'             => $row->id,
                    'invoice_number' => $row->purchaseInvoice?->invoice_number,
                    'item_name'      => $row->movementDetail?->item?->itemName2 ?? '—',
                    'type_name'      => $row->movementDetail?->type?->name ?? '—',
                    'unit_name'      => $row->movementDetail?->unit?->UnitName ?? '—',
                    'code'           => $row->movementDetail?->code ?? '—',
                    'quantity'       => (float) $row->quantity,
                    'unit_cost'      => (float) $row->unit_cost,
                    'sale_price'     => (float) $row->sale_price,
                ]),
            ]),
        ]);
    }

    // ============================================================
    // ملخص العميل
    // ============================================================
    public function customerSummary(Request $request)
    {
        $validated = $request->validate([
            'customer_id'     => ['required', 'integer', 'exists:customers,CustomersID'],
            'invoiced_filter' => ['nullable', 'in:all,invoiced,not_invoiced'],
        ]);

        $customerId = (int) $validated['customer_id'];
        $filter     = $validated['invoiced_filter'] ?? 'all';

        $customer = Customer::with('account')->findOrFail($customerId);

        $q = DistributionDetail::query()
            ->with([
                'distribution:id,code,distribution_date,status',
                'purchaseInvoice:purchase_invoice_id,invoice_number',
                'movementDetail.item:itemID,itemName2',
                'movementDetail.type:id,name',
                'movementDetail.unit:UnitID,UnitName',
            ])
            ->where('customer_id', $customerId);

        if ($filter === 'invoiced')     $q->whereNotNull('sales_invoice_id');
        if ($filter === 'not_invoiced') $q->whereNull('sales_invoice_id');

        $rows = $q->orderByDesc('id')->get()->map(fn($d) => [
            'id'             => $d->id,
            'distribution'   => $d->distribution?->code,
            'dist_date'      => optional($d->distribution?->distribution_date)->format('Y-m-d'),
            'dist_status'    => $d->distribution?->status,
            'invoice_number' => $d->purchaseInvoice?->invoice_number,
            'item_name'      => $d->movementDetail?->item?->itemName2 ?? '—',
            'type_name'      => $d->movementDetail?->type?->name ?? '—',
            'unit_name'      => $d->movementDetail?->unit?->UnitName ?? '—',
            'code'           => $d->movementDetail?->code ?? '—',
            'quantity'       => (float) $d->quantity,
            'unit_cost'      => (float) $d->unit_cost,
            'sale_price'     => (float) $d->sale_price,
            'total_cost'     => (float) $d->quantity * (float) $d->unit_cost,
            'total_sale'     => (float) $d->quantity * (float) $d->sale_price,
            'is_invoiced'    => $d->sales_invoice_id !== null,
        ]);

        $qtyByUnit = [];
        $totalCost = 0.0;
        $totalSale = 0.0;
        $notInvoicedCost = 0.0;
        $notInvoicedQtyByUnit = [];

        foreach ($rows as $r) {
            $u = $r['unit_name'] ?: '—';
            $qtyByUnit[$u] = ($qtyByUnit[$u] ?? 0) + $r['quantity'];
            $totalCost += $r['total_cost'];
            $totalSale += $r['total_sale'];

            if (! $r['is_invoiced']) {
                $notInvoicedQtyByUnit[$u] = ($notInvoicedQtyByUnit[$u] ?? 0) + $r['quantity'];
                $notInvoicedCost += $r['total_cost'];
            }
        }

        return response()->json([
            'success'  => true,
            'customer' => [
                'id'    => $customer->CustomersID,
                'name'  => $customer->CustomersName2,
                'phone' => $customer->CusPhone,
                'code'  => $customer->account?->accCode ?? '',
            ],
            'summary' => [
                'rows_count'           => $rows->count(),
                'qty_by_unit'          => $qtyByUnit,
                'total_cost'           => $totalCost,
                'total_sale'           => $totalSale,
                'not_invoiced_count'   => $rows->where('is_invoiced', false)->count(),
                'not_invoiced_qty'     => $notInvoicedQtyByUnit,
                'not_invoiced_cost'    => $notInvoicedCost,
            ],
            'data' => $rows,
        ]);
    }

    // ============================================================
    // بحث فواتير الشراء (سجل التوزيعات)
    // ============================================================
    public function searchInvoices(Request $request)
    {
        $date     = trim((string) $request->input('date', ''));
        $supplier = trim((string) $request->input('supplier', ''));
        $q        = trim((string) $request->input('q', ''));

        $query = PurchaseInvoice::query()
            ->with('supplierAccount')
            ->where('is_posted', 1);

        if ($date !== '') {
            $query->whereDate('invoice_date', $date);
        }

        if ($supplier !== '') {
            $query->whereHas('supplierAccount', fn($s) =>
                $s->where('accName', 'like', "%{$supplier}%"));
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('invoice_number', 'like', "%{$q}%")
                    ->orWhereHas('supplierAccount', fn($s) =>
                        $s->where('accName', 'like', "%{$q}%"));
            });
        }

        $rows = $query->orderByDesc('purchase_invoice_id')
            ->limit(100)
            ->get()
            ->map(fn($i) => [
                'id'       => $i->purchase_invoice_id,
                'number'   => $i->invoice_number,
                'date'     => optional($i->invoice_date)->format('Y-m-d'),
                'supplier' => $i->supplierAccount?->accName ?? '—',
                'total'    => (float) $i->total_in_invoice_currency,
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ============================================================
    // ملخص الفاتورة
    // ============================================================
    public function invoiceSummary(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:purchase_invoices,purchase_invoice_id'],
        ]);

        $invoiceId = (int) $validated['invoice_id'];
        $invoice   = PurchaseInvoice::with('supplierAccount')->findOrFail($invoiceId);

        $rows = DistributionDetail::query()
            ->with([
                'distribution:id,code,distribution_date,status',
                'customer:CustomersID,CustomersName2,CusPhone',
                'movementDetail.item:itemID,itemName2',
                'movementDetail.type:id,name',
                'movementDetail.unit:UnitID,UnitName',
            ])
            ->where('purchase_invoice_id', $invoiceId)
            ->orderByDesc('id')
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'distribution'   => $d->distribution?->code,
                'dist_date'      => optional($d->distribution?->distribution_date)->format('Y-m-d'),
                'dist_status'    => $d->distribution?->status,
                'customer_id'    => $d->customer_id,
                'customer_name'  => $d->customer?->CustomersName2 ?? '—',
                'customer_phone' => $d->customer?->CusPhone,
                'item_name'      => $d->movementDetail?->item?->itemName2 ?? '—',
                'type_name'      => $d->movementDetail?->type?->name ?? '—',
                'unit_name'      => $d->movementDetail?->unit?->UnitName ?? '—',
                'code'           => $d->movementDetail?->code ?? '—',
                'quantity'       => (float) $d->quantity,
                'unit_cost'      => (float) $d->unit_cost,
                'sale_price'     => (float) $d->sale_price,
                'total_cost'     => (float) $d->quantity * (float) $d->unit_cost,
                'total_sale'     => (float) $d->quantity * (float) $d->sale_price,
                'is_invoiced'    => $d->sales_invoice_id !== null,
            ]);

        $qtyByUnit        = [];
        $totalCost        = 0.0;
        $totalSale        = 0.0;
        $itemsSet         = collect();
        $customersSet     = collect();
        $notInvoicedCount = 0;

        foreach ($rows as $r) {
            $u = $r['unit_name'] ?: '—';
            $qtyByUnit[$u]   = ($qtyByUnit[$u] ?? 0) + $r['quantity'];
            $totalCost      += $r['total_cost'];
            $totalSale      += $r['total_sale'];
            $itemsSet->push($r['item_name']);
            $customersSet->push($r['customer_id']);
            if (! $r['is_invoiced']) $notInvoicedCount++;
        }

        return response()->json([
            'success' => true,
            'invoice' => [
                'id'       => $invoice->purchase_invoice_id,
                'number'   => $invoice->invoice_number,
                'date'     => optional($invoice->invoice_date)->format('Y-m-d'),
                'supplier' => $invoice->supplierAccount?->accName ?? '—',
                'total'    => (float) $invoice->total_in_invoice_currency,
            ],
            'summary' => [
                'rows_count'         => $rows->count(),
                'items_count'        => $itemsSet->unique()->count(),
                'customers_count'    => $customersSet->unique()->count(),
                'qty_by_unit'        => $qtyByUnit,
                'total_cost'         => $totalCost,
                'total_sale'         => $totalSale,
                'not_invoiced_count' => $notInvoicedCount,
            ],
            'data' => $rows,
        ]);
    }

    // ============================================================
    // إصدار فاتورة مبيعات
    // ============================================================
    public function issueInvoice(Request $request)
    {
        $validated = $request->validate([
            'customer_id'        => ['required', 'integer', 'exists:customers,CustomersID'],
            'distribution_ids'   => ['required', 'array', 'min:1'],
            'distribution_ids.*' => ['integer', 'exists:distributions,id'],
            'payment_method'     => ['required', 'integer', 'in:1,2,3'],
            'payment_account_id' => ['nullable', 'integer'],
            'statement'          => ['nullable', 'string', 'max:500'],
            'reference'          => ['nullable', 'string', 'max:100'],
        ]);

        $customerId      = (int) $validated['customer_id'];
        $distributionIds = $validated['distribution_ids'];
        $paymentMethod   = (int) $validated['payment_method'];

        if ($paymentMethod !== SalesInvoice::PAYMENT_CREDIT) {
            if (empty($validated['payment_account_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'حساب الدفع مطلوب لهذه الطريقة (نقدي / بنك).',
                ], 422);
            }
            $paymentAccount = CharAccount::find((int) $validated['payment_account_id']);
            if (!$paymentAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'حساب الدفع المحدد غير موجود.',
                ], 422);
            }
        }

        $customer = Customer::findOrFail($customerId);
        if (empty($customer->accountID)) {
            return response()->json([
                'success' => false,
                'message' => 'العميل «' . $customer->CustomersName2 . '» غير مرتبط بحساب محاسبي.',
            ], 422);
        }

        $lockKey = "distribution_issue_customer_{$customerId}";
        $lock    = Cache::lock($lockKey, 20);

        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'عملية إصدار فاتورة أخرى لنفس العميل قيد التنفيذ. حاول بعد قليل.',
            ], 409);
        }

        try {
            return DB::transaction(function () use ($customerId, $distributionIds, $paymentMethod, $customer, $validated) {
                $details = DistributionDetail::query()
                    ->whereIn('distribution_id', $distributionIds)
                    ->where('customer_id', $customerId)
                    ->whereNull('sales_invoice_id')
                    ->with(['movementDetail', 'purchaseInvoice.coin'])
                    ->lockForUpdate()
                    ->get();

                if ($details->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا توجد تفاصيل قابلة للفوترة (قد تكون فُوترت بالفعل من عملية أخرى).',
                    ], 422);
                }

                foreach ($details as $d) {
                    if (!$d->movementDetail) {
                        return response()->json([
                            'success' => false,
                            'message' => "التفصيل #{$d->id} مفقود في حركة المخزون.",
                        ], 422);
                    }
                    if (!$d->purchaseInvoice) {
                        return response()->json([
                            'success' => false,
                            'message' => "التفصيل #{$d->id} مفقود في فاتورة الشراء.",
                        ], 422);
                    }
                }

                $coins = $details->pluck('purchaseInvoice.coin_id')->unique();
                if ($coins->count() > 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا يمكن إصدار فاتورة واحدة بتوزيعات من فواتير بعملات مختلفة.',
                    ], 422);
                }

                $firstInvoice = $details->first()->purchaseInvoice;
                $coinId       = (int) $firstInvoice->coin_id;
                $exchangeRate = (float) ($firstInvoice->exchange_rate ?: 1);

                $items = $details->map(function ($d) {
                    $md = $d->movementDetail;
                    return [
                        'item_id'             => (int) $md->item_id,
                        'type_id'             => $md->type_id ? (int) $md->type_id : null,
                        'unit_id'             => $md->unit_id ? (int) $md->unit_id : null,
                        'warehouse_id'        => (int) $md->warehouse_id,
                        'supplier_account_id' => $md->supplier_account_id ? (int) $md->supplier_account_id : null,
                        'code'                => $md->code ?? null,
                        'quantity'            => (float) $d->quantity,
                        'price'               => (float) $d->sale_price,
                        'cost_price'          => (float) $d->unit_cost,
                        'discount'            => 0,
                    ];
                })->all();

                $data = [
                    'invoice_date'       => now()->toDateString(),
                    'account_id'         => (int) $customer->accountID,
                    'payment_account_id' => $paymentMethod === SalesInvoice::PAYMENT_CREDIT
                                            ? null
                                            : (int) $validated['payment_account_id'],
                    'coin_id'            => $coinId,
                    'exchange_rate'      => $exchangeRate,
                    'payment_method'     => $paymentMethod,
                    'statement'          => $validated['statement'] ?? null,
                    'reference'          => $validated['reference'] ?? null,
                    'details'            => $items,
                    'skip_stock_validation' => true, // ✅ إضافة هذا السطر
                ];

                /** @var SalesInvoice $invoice */
                $invoice = app(SalesInvoiceService::class)->create($data);

                DistributionDetail::whereIn('id', $details->pluck('id'))
                    ->update([
                        'sales_invoice_id' => $invoice->sales_invoice_id,
                        'invoiced_at'      => now(),
                    ]);

                foreach ($distributionIds as $distId) {
                    $remaining = DistributionDetail::where('distribution_id', $distId)
                        ->whereNull('sales_invoice_id')
                        ->count();

                    if ($remaining === 0) {
                        Distribution::find($distId)?->update([
                            'status'      => Distribution::STATUS_INVOICED,
                            'invoiced_at' => now(),
                            'invoiced_by' => auth()->id(),
                        ]);
                    }
                }

                return response()->json([
                    'success'          => true,
                    'message'          => 'تم إصدار فاتورة المبيعات وترحيلها بنجاح.',
                    'sales_invoice_id' => $invoice->sales_invoice_id,
                    'invoice_number'   => $invoice->invoice_number,
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('issueInvoice failed', [
                'message'          => $e->getMessage(),
                'file'             => $e->getFile(),
                'line'             => $e->getLine(),
                'customer_id'      => $customerId,
                'distribution_ids' => $distributionIds,
                'payment_method'   => $paymentMethod,
                'user_id'          => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل إصدار الفاتورة: ' . $e->getMessage(),
            ], 500);
        } finally {
            $lock->release();
        }
    }

    // ============================================================
    // قائمة التوزيعات التي لها تفاصيل غير مفوترة
    // ============================================================
    public function distributionsForInvoice()
    {
        $distributions = Distribution::query()
            ->with(['details' => fn($q) => $q->whereNull('sales_invoice_id')])
            ->whereHas('details', fn($q) => $q->whereNull('sales_invoice_id'))
            ->orderByDesc('id')
            ->get()
            ->map(function ($d) {
                $totalCost = 0.0;
                $totalSale = 0.0;
                foreach ($d->details as $r) {
                    $totalCost += (float) $r->quantity * (float) $r->unit_cost;
                    $totalSale += (float) $r->quantity * (float) $r->sale_price;
                }
                return [
                    'id'              => $d->id,
                    'code'            => $d->code,
                    'date'            => optional($d->distribution_date)->format('Y-m-d'),
                    'status'          => $d->status,
                    'details_count'   => $d->details->count(),
                    'customers_count' => $d->details->pluck('customer_id')->unique()->count(),
                    'total_cost'      => $totalCost,
                    'total_sale'      => $totalSale,
                ];
            });

        return response()->json(['success' => true, 'data' => $distributions]);
    }

    // ============================================================
    // عملاء توزيع معين (الذين لهم تفاصيل غير مفوترة)
    // ============================================================
    public function distributionCustomers(int $id)
    {
        $dist = Distribution::with([
            'details' => function ($q) {
                $q->whereNull('sales_invoice_id')
                  ->with([
                      'customer.account',
                      'movementDetail.unit',
                  ]);
            },
        ])->findOrFail($id);

        $byCustomer = $dist->details->groupBy('customer_id');

        $customers = $byCustomer->map(function ($details, $cid) {
            $first    = $details->first();
            $customer = $first->customer;

            $qtyByUnit = [];
            $totalCost = 0.0;
            $totalSale = 0.0;

            foreach ($details as $r) {
                $u = $r->movementDetail?->unit?->UnitName ?? '—';
                $qtyByUnit[$u] = ($qtyByUnit[$u] ?? 0) + (float) $r->quantity;
                $totalCost    += (float) $r->quantity * (float) $r->unit_cost;
                $totalSale    += (float) $r->quantity * (float) $r->sale_price;
            }

            return [
                'customer_id'    => (int) $cid,
                'customer_name'  => $customer?->CustomersName2 ?? '—',
                'customer_phone' => $customer?->CusPhone,
                'customer_code'  => $customer?->account?->accCode ?? '',
                'details_count'  => $details->count(),
                'qty_by_unit'    => $qtyByUnit,
                'total_cost'     => $totalCost,
                'total_sale'     => $totalSale,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'distribution' => [
                'id'   => $dist->id,
                'code' => $dist->code,
                'date' => optional($dist->distribution_date)->format('Y-m-d'),
            ],
            'data' => $customers,
        ]);
    }

    // ============================================================
    // تفاصيل عميل معين داخل توزيع معين (غير مفوترة) — مع الكمية المفرزة
    // ============================================================
    public function distributionCustomerDetails(int $distributionId, int $customerId)
    {
        $details = DistributionDetail::query()
            ->where('distribution_id', $distributionId)
            ->where('customer_id', $customerId)
            ->whereNull('sales_invoice_id')
            ->with([
                'movementDetail.item',
                'movementDetail.type',
                'movementDetail.unit',
                'purchaseInvoice',
            ])
            ->get();

        if ($details->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد تفاصيل غير مفوترة لهذا العميل في هذا التوزيع.',
                'data'    => [],
            ]);
        }

        $movementDetailIds = $details->pluck('movement_detail_id')->unique();

        $sortedByMD = DistributionDetail::query()
            ->select('movement_detail_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('movement_detail_id', $movementDetailIds)
            ->groupBy('movement_detail_id')
            ->pluck('total', 'movement_detail_id');

        $convertedByMD = InventoryMovementDetail::query()
            ->join('inventory_movements', 'inventory_movements.movement_id', '=', 'inventory_movement_details.movement_id')
            ->select(
                'inventory_movement_details.lot_source_detail_id',
                DB::raw('SUM(inventory_movement_details.quantity) as total')
            )
            ->whereIn('inventory_movement_details.lot_source_detail_id', $movementDetailIds)
            ->where('inventory_movements.movement_type', 'sorting_in')
            ->groupBy('inventory_movement_details.lot_source_detail_id')
            ->pluck('total', 'lot_source_detail_id');

        $rows = $details->map(function ($d) use ($sortedByMD, $convertedByMD) {
            $md = $d->movementDetail;

            $batchQty     = (float) ($md?->quantity ?? 0);
            $sortedQty    = (float) ($sortedByMD[$d->movement_detail_id] ?? 0);
            $convertedQty = (float) ($convertedByMD[$d->movement_detail_id] ?? 0);
            $remainingQty = max(0, $batchQty - $sortedQty);

            return [
                'id'                 => $d->id,
                'movement_detail_id' => $d->movement_detail_id,
                'invoice_number'     => $d->purchaseInvoice?->invoice_number,
                'item_name'          => $md?->item?->itemName2 ?? '—',
                'type_name'          => $md?->type?->name ?? '—',
                'unit_name'          => $md?->unit?->UnitName ?? '—',
                'code'               => $md?->code ?? '—',
                'batch_quantity'     => $batchQty,
                'sorted_quantity'    => $sortedQty,
                'converted_quantity' => $convertedQty,
                'remaining_quantity' => $remainingQty,
                'quantity'           => (float) $d->quantity,
                'unit_cost'          => (float) $d->unit_cost,
                'sale_price'         => (float) $d->sale_price,
                'total_cost'         => (float) $d->quantity * (float) $d->unit_cost,
                'total_sale'         => (float) $d->quantity * (float) $d->sale_price,
            ];
        });

        $qtyByUnit = [];
        $totalCost = 0.0;
        $totalSale = 0.0;
        foreach ($rows as $r) {
            $u = $r['unit_name'] ?: '—';
            $qtyByUnit[$u] = ($qtyByUnit[$u] ?? 0) + $r['quantity'];
            $totalCost += $r['total_cost'];
            $totalSale += $r['total_sale'];
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'rows_count'  => $rows->count(),
                'qty_by_unit' => $qtyByUnit,
                'total_cost'  => $totalCost,
                'total_sale'  => $totalSale,
            ],
            'data' => $rows,
        ]);
    }
}