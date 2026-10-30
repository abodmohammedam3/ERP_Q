<?php

namespace App\Http\Controllers\Operation\PurchaseDistribution;

use App\Http\Controllers\Controller;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Distribution;
use App\Models\Purchases\DistributionDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Item;
use App\Models\Customer;
use Illuminate\Http\Request;
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
        return view('operation.purchase-distribution.customer-invoices.index');
    }

    public function historyPage()
    {
        return view('operation.purchase-distribution.history.index');
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
    // أصناف فاتورة محددة
    // ============================================================
    public function items(int $id)
    {
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

        $details = InventoryMovementDetail::query()
            ->with(['item', 'type', 'unit'])
            ->where('movement_id', $movement->movement_id)
            ->get();

        $distributed = DistributionDetail::query()
            ->select('movement_detail_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('movement_detail_id', $details->pluck('movement_detail_id'))
            ->whereHas('distribution')
            ->groupBy('movement_detail_id')
            ->pluck('total', 'movement_detail_id');

        $rows = $details->map(function ($d) use ($distributed, $invoice) {
            $dist = (float) ($distributed[$d->movement_detail_id] ?? 0);
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
                'available'           => max(0, (float) $d->quantity - $dist),
                'unit_cost'           => (float) $d->unit_cost,
                'sale_price'          => (float) $d->sale_price,
            ];
        });

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
    // حفظ توزيع
    // ============================================================
    public function save(Request $request)
    {
        $validated = $request->validate([
            'id'                => ['nullable', 'integer', 'exists:distributions,id'],
            'distribution_date' => ['required', 'date_format:Y-m-d'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            'details'           => ['required', 'array', 'min:1'],
            'details.*.movement_detail_id'  => ['required', 'integer'],
            'details.*.purchase_invoice_id' => ['required', 'integer'],
            'details.*.movement_id'         => ['required', 'integer'],
            'details.*.customer_id'         => ['required', 'integer'],
            'details.*.quantity'            => ['required', 'numeric', 'min:0.000001'],
        ]);

        $isUpdate = ! empty($validated['id']);
        $existing = $isUpdate ? Distribution::findOrFail($validated['id']) : null;

        if ($existing && ! $existing->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل توزيع مفوتر.',
            ], 422);
        }

        $requested = [];
        foreach ($validated['details'] as $row) {
            $mdId = (int) $row['movement_detail_id'];
            $requested[$mdId] = ($requested[$mdId] ?? 0) + (float) $row['quantity'];
        }

        $query = DistributionDetail::query()
            ->select('movement_detail_id', DB::raw('SUM(quantity) as total'))
            ->whereIn('movement_detail_id', array_keys($requested))
            ->whereHas('distribution');

        if ($existing) {
            $query->where('distribution_id', '!=', $existing->id);
        }
        $alreadyDistributed = $query->groupBy('movement_detail_id')->pluck('total', 'movement_detail_id');

        $movementDetails = InventoryMovementDetail::whereIn(
            'movement_detail_id', array_keys($requested)
        )->with('item')->get()->keyBy('movement_detail_id');

        foreach ($requested as $mdId => $qty) {
            $source = $movementDetails->get($mdId);
            if (! $source) {
                throw ValidationException::withMessages([
                    'details' => "الصنف رقم {$mdId} غير موجود في حركة المخزون.",
                ]);
            }
            $available = (float) $source->quantity - (float) ($alreadyDistributed[$mdId] ?? 0);
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
                $distribution->update([
                    'distribution_date' => $validated['distribution_date'],
                    'notes'             => $validated['notes'] ?? null,
                ]);
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
                $source = $movementDetails->get((int) $row['movement_detail_id']);
                DistributionDetail::create([
                    'distribution_id'     => $distribution->id,
                    'purchase_invoice_id' => (int) $row['purchase_invoice_id'],
                    'movement_id'         => (int) $row['movement_id'],
                    'movement_detail_id'  => (int) $row['movement_detail_id'],
                    'customer_id'         => (int) $row['customer_id'],
                    'quantity'            => (float) $row['quantity'],
                    'unit_cost'           => (float) ($source?->unit_cost ?? 0),
                    'sale_price'          => (float) ($source?->sale_price ?? 0),
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
            'is_invoiced'         => $d->invoiced_at !== null,
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
        if (! $dist->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف توزيع مفوتر.',
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
    // توزيعات عميل
    // ============================================================
    public function customerDistributions(int $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        $distributions = Distribution::query()
            ->with(['details' => function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                  ->whereNull('invoiced_at')
                  ->with([
                      'purchaseInvoice',
                      'movementDetail.item',
                      'movementDetail.type',
                      'movementDetail.unit',
                  ]);
            }])
            ->whereHas('details', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)->whereNull('invoiced_at');
            })
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success'  => true,
            'customer' => [
                'id'    => $customer->CustomersID,
                'name'  => $customer->CustomersName2,
                'phone' => $customer->CusPhone,
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
    // ملخص العميل (سجل التوزيعات — التبويب 2)
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

        if ($filter === 'invoiced')     $q->whereNotNull('invoiced_at');
        if ($filter === 'not_invoiced') $q->whereNull('invoiced_at');

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
            'is_invoiced'    => $d->invoiced_at !== null,
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
    // البحث عن أصناف (لاختيار صنف في تبويب الملخص)
    // ============================================================
    public function searchItems(Request $request)
    {
        $term = trim((string) $request->input('q', ''));

        $query = Item::query();

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('itemName2', 'like', "%{$term}%");
                if (is_numeric($term)) {
                    $q->orWhere('itemID', (int)$term);
                }
            });
        }

        $rows = $query->orderBy('itemName2')
            ->limit(50)
            ->get()
            ->map(fn($i) => [
                'id'   => $i->itemID,
                'name' => $i->itemName2,
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ============================================================
    // ملخص الصنف (سجل التوزيعات — التبويب 3)
    // ============================================================
    public function itemSummary(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer'],
        ]);

        $itemId = (int) $validated['item_id'];

        $item = Item::findOrFail($itemId);

        $rows = DistributionDetail::query()
            ->with([
                'distribution:id,code,distribution_date,status',
                'customer:CustomersID,CustomersName2,CusPhone',
                'purchaseInvoice:purchase_invoice_id,invoice_number',
                'movementDetail.type:id,name',
                'movementDetail.unit:UnitID,UnitName',
            ])
            ->whereHas('movementDetail', fn($q) => $q->where('item_id', $itemId))
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
                'invoice_number' => $d->purchaseInvoice?->invoice_number,
                'type_name'      => $d->movementDetail?->type?->name ?? '—',
                'unit_name'      => $d->movementDetail?->unit?->UnitName ?? '—',
                'quantity'       => (float) $d->quantity,
                'is_invoiced'    => $d->invoiced_at !== null,
            ]);

        // الكمية الأصلية: مجموع الكميات الداخلة من فواتير الشراء لهذا الصنف
        $originalQty = (float) InventoryMovementDetail::query()
            ->where('item_id', $itemId)
            ->whereHas('movement', fn($q) => $q->where('movement_type', InventoryMovement::TYPE_PURCHASE))
            ->sum('quantity');

        $totalDistributed = (float) $rows->sum('quantity');
        $remaining = max(0, $originalQty - $totalDistributed);

        $unitName = optional($rows->first())['unit_name'] ?? '—';

        $qtyByCustomer = $rows->groupBy('customer_id')->map(fn($g) => [
            'customer_name'  => $g->first()['customer_name'],
            'customer_phone' => $g->first()['customer_phone'],
            'total_qty'      => (float) $g->sum('quantity'),
        ])->values();

        return response()->json([
            'success' => true,
            'item' => [
                'id'   => $item->itemID,
                'name' => $item->itemName2,
            ],
            'summary' => [
                'original_qty'      => $originalQty,
                'total_distributed' => $totalDistributed,
                'remaining'         => $remaining,
                'unit_name'         => $unitName,
                'customers_count'   => $rows->pluck('customer_id')->unique()->count(),
            ],
            'by_customer' => $qtyByCustomer,
            'data'        => $rows,
        ]);
    }

    // ============================================================
    // إصدار فاتورة
    // ============================================================
    public function issueInvoice(Request $request)
    {
        $validated = $request->validate([
            'customer_id'        => ['required', 'integer', 'exists:customers,CustomersID'],
            'distribution_ids'   => ['required', 'array', 'min:1'],
            'distribution_ids.*' => ['integer'],
        ]);

        $customerId = (int) $validated['customer_id'];
        $ids        = $validated['distribution_ids'];

        $details = DistributionDetail::query()
            ->whereIn('distribution_id', $ids)
            ->where('customer_id', $customerId)
            ->whereNull('invoiced_at')
            ->get();

        if ($details->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد تفاصيل قابلة للفوترة.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($details as $row) {
                $row->update(['invoiced_at' => now()]);
            }

            foreach ($ids as $distId) {
                $dist = Distribution::find($distId);
                if (! $dist) continue;

                $remaining = DistributionDetail::where('distribution_id', $distId)
                    ->whereNull('invoiced_at')
                    ->count();

                if ($remaining === 0) {
                    $dist->update([
                        'status'      => Distribution::STATUS_INVOICED,
                        'invoiced_at' => now(),
                        'invoiced_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'success'     => true,
            'message'     => 'تم تجهيز البيانات للفاتورة.',
            'customer_id' => $customerId,
            'detail_ids'  => $details->pluck('id'),
        ]);
    }

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

    /**
 * بحث الفواتير لتبويب "حسب الفاتورة"
 */
public function searchInvoices(Request $request)
{
    $term = trim((string) $request->input('q', ''));

    $query = PurchaseInvoice::query()
        ->with('supplierAccount')
        ->where('is_posted', 1);

    if ($term !== '') {
        $query->where(function ($q) use ($term) {
            $q->where('invoice_number', 'like', "%{$term}%")
              ->orWhereHas('supplierAccount', fn($s) =>
                    $s->where('accName', 'like', "%{$term}%"));
        });
    }

    $rows = $query->orderByDesc('purchase_invoice_id')
        ->limit(50)
        ->get()
        ->map(fn($i) => [
            'id'       => $i->purchase_invoice_id,
            'number'   => $i->invoice_number,
            'date'     => optional($i->invoice_date)->format('Y-m-d'),
            'supplier' => $i->supplierAccount?->accName ?? '—',
        ]);

    return response()->json(['success' => true, 'data' => $rows]);
}

/**
 * ملخص الفاتورة — من وزّع منها ماذا وكم
 */
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
            'is_invoiced'    => $d->invoiced_at !== null,
        ]);

    $qtyByUnit       = [];
    $totalCost       = 0.0;
    $totalSale       = 0.0;
    $itemsSet        = collect();
    $customersSet    = collect();
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
            'rows_count'          => $rows->count(),
            'items_count'         => $itemsSet->unique()->count(),
            'customers_count'     => $customersSet->unique()->count(),
            'qty_by_unit'         => $qtyByUnit,
            'total_cost'          => $totalCost,
            'total_sale'          => $totalSale,
            'not_invoiced_count'  => $notInvoicedCount,
        ],
        'data' => $rows,
    ]);
}
}