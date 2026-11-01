<?php

namespace App\Http\Controllers\Operation\PurchaseDistribution;

use App\Http\Controllers\Controller;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Distribution;
use App\Models\Purchases\DistributionDetail;
use App\Models\Purchases\DistributionLog;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Item;
use App\Models\Customer;
use App\Services\Purchases\DistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseDistributionController extends Controller
{
    public function __construct(protected DistributionService $distributionService)
    {
    }

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

        DB::beginTransaction();
        try {
            /* ✅ قفل صفوف المصدر (inventory_movement_details) داخل الترانزكشن —
               يمنع توزيعين متزامنين من قراءة/حساب الكمية المتاحة نفسها
               وتجاوزها (race condition) — القفل على المصدر لا على الموزّع. */
            InventoryMovementDetail::query()
                ->whereIn('movement_detail_id', array_keys($requested))
                ->lockForUpdate()
                ->get();

            $movementDetails = InventoryMovementDetail::query()
                ->whereIn('movement_detail_id', array_keys($requested))
                ->with('item')
                ->get()
                ->keyBy('movement_detail_id');

            /* ✅ التحقق من تطابق md ↔ movement ↔ فاتورة الشراء
               قبل كتابة أي صف — يمنع ربط توزيع بفاتورة شراء خاطئة. */
            $movements = InventoryMovement::query()
                ->whereIn('movement_id', $movementDetails->pluck('movement_id')->unique())
                ->get()
                ->keyBy('movement_id');

            foreach ($validated['details'] as $row) {
                $source = $movementDetails->get((int) $row['movement_detail_id']);

                if (! $source) {
                    throw ValidationException::withMessages([
                        'details' => "الصنف رقم {$row['movement_detail_id']} غير موجود في حركة المخزون.",
                    ]);
                }

                if ((int) $source->movement_id !== (int) $row['movement_id']) {
                    throw ValidationException::withMessages([
                        'details' => "الصنف رقم {$row['movement_detail_id']} لا يتبع الحركة رقم {$row['movement_id']} المرسلة (يتبع الحركة {$source->movement_id}).",
                    ]);
                }

                $movement = $movements->get((int) $row['movement_id']);

                if (! $movement
                    || $movement->source_type !== InventoryMovement::SOURCE_PURCHASE_INVOICE
                    || (int) $movement->source_id !== (int) $row['purchase_invoice_id']) {
                    throw ValidationException::withMessages([
                        'details' => "الحركة رقم {$row['movement_id']} لا تتبع فاتورة الشراء رقم {$row['purchase_invoice_id']} المرسلة.",
                    ]);
                }
            }

            $alreadyDistributed = $query->groupBy('movement_detail_id')->pluck('total', 'movement_detail_id');

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

            if ($existing) {
                $distribution = $existing;
                $distribution->update([
                    'distribution_date' => $validated['distribution_date'],
                    'notes'             => $validated['notes'] ?? null,
                ]);
                $distribution->details()->delete();

                /* ✅ wasChanged قبل التسجيل — لا يُكتب سجل تعديل إن لم تتغير
                   بيانات الرأس فعلاً (إعادة بناء الصفوف وحدها لا تعني تغيّر الرأس). */
                if ($distribution->wasChanged(['distribution_date', 'notes'])) {
                    $distribution->log(
                        DistributionLog::ACTION_UPDATED,
                        "تعديل بيانات التوزيع {$distribution->code} — إعادة بناء " . count($validated['details']) . " صف",
                        ['details_count' => count($validated['details'])],
                    );
                }
            } else {
                /* ✅ إعادة محاولة عند تعارض الرمز الفريد (nextCode) —
                   يحمي من التزامن: طلبان يحسبان نفس الرمز في نفس اللحظة.
                   عمود code فريد في الميجريشن، ففشل INSERT يعني احتكاك
                   اكتشفته قاعدة البيانات نفسها → نعيد المحاولة برمز جديد. */
                $distribution = null;
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    try {
                        $distribution = Distribution::create([
                            'code'              => Distribution::nextCode(),
                            'distribution_date' => $validated['distribution_date'],
                            'status'            => Distribution::STATUS_DRAFT,
                            'notes'             => $validated['notes'] ?? null,
                            'created_by'        => auth()->id(),
                        ]);
                        break; // نجاح
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($attempt === 4
                            || ! str_contains($e->getMessage(), 'Duplicate entry')) {
                            throw $e;
                        }
                    }
                }
                if (! $distribution) {
                    throw ValidationException::withMessages([
                        'code' => 'فشل توليد رمز فريد للتوزيع بعد 5 محاولات.',
                    ]);
                }

                $distribution->log(
                    DistributionLog::ACTION_CREATED,
                    "إنشاء توزيع {$distribution->code} بـ " . count($validated['details']) . " صف",
                    ['details_count' => count($validated['details'])],
                );
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
            'details.salesInvoice:sales_invoice_id,invoice_number',
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
            'sales_invoice_id'      => $d->sales_invoice_id,
            'sales_invoice_number'  => $d->salesInvoice?->invoice_number,
            'invoiced_at'           => optional($d->invoiced_at)->format('Y-m-d H:i'),
            'is_invoiced'           => $d->invoiced_at !== null,
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
    // سجل تتبع توزيع (Audit Log)
    // ============================================================
    public function logs(Request $request, int $id)
    {
        $dist = Distribution::findOrFail($id);

        /* ✅ Pagination بدل limit(50) الثابت — التوزيعات ذات السجل الطويل لم تعد مبتورة */
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        $page    = max(1, (int) $request->input('page', 1));
        $total   = $dist->logs()->count();

        $rows = $dist->logs()
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($l) => [
                'id'           => $l->id,
                'action'       => $l->action,
                'action_label' => DistributionLog::actionLabel($l->action),
                'description'  => $l->description,
                'meta'         => $l->meta,
                'user_id'      => $l->user_id,
                'created_at'   => optional($l->created_at)->format('Y-m-d H:i'),
            ]);

        return response()->json([
            'success' => true,
            'code'    => $dist->code,
            'data'    => $rows,
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
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
        // ✅ Audit Log: لقطة الصفوف قبل الحذف (التوزيع والتفاصيل سيُحذفان cascade)
        $snapshot = [
            'code'          => $dist->code,
            'details_count' => $dist->details()->count(),
            'total_cost'    => round((float) $dist->details()->sum(DB::raw('quantity * unit_cost')), 6),
            'total_sale'    => round((float) $dist->details()->sum(DB::raw('quantity * sale_price')), 6),
        ];

        $dist->log(
            DistributionLog::ACTION_DELETED,
            "حذف التوزيع {$dist->code} (" . $snapshot['details_count'] . " صف)",
            $snapshot,
        );

        $dist->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف التوزيع.']);
    }

    // ============================================================
    // قائمة التوزيعات (سجل)
    // ============================================================
    public function list(Request $request)
    {
        $q = Distribution::query()
            ->with([
                'details:id,distribution_id,customer_id,quantity,unit_cost,sale_price,sales_invoice_id',
                'details.salesInvoice:sales_invoice_id,invoice_number',
            ]);

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

        // ✅ Pagination احترافي بدل الـ limit(300) الثابت —
        //    يمنع اختفاء التوزيعات القديمة ويعطي meta للواجهة.
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        $page    = max(1, (int) $request->input('page', 1));

        $total = (clone $q)->count();

        $distributions = $q->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        /* ✅ أرقام فواتير البيع باستعلام SQL واحد — بدل الحساب في PHP لكل توزيع */
        $salesInvoiceIds = $distributions->flatMap->details
            ->pluck('sales_invoice_id')
            ->filter()
            ->unique()
            ->values();

        $numbersById = $salesInvoiceIds->isEmpty()
            ? collect()
            : \App\Models\Sales\SalesInvoice::query()
                ->whereIn('sales_invoice_id', $salesInvoiceIds)
                ->pluck('invoice_number', 'sales_invoice_id');

        $rows = $distributions->map(function ($d) use ($numbersById) {
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
                    'sales_invoice_numbers' => $d->details
                        ->map(fn($r) => $numbersById->get($r->sales_invoice_id))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ];
            });

        /* ✅ استعلام إحصائي واحد بدل ثلاثة counts منفصلة في كل طلب */
        $statsRow = Distribution::query()
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'invoiced' THEN 1 ELSE 0 END) as invoiced")
            ->first();

        $stats = [
            'total'    => (int) $statsRow->total,
            'draft'    => (int) $statsRow->draft,
            'invoiced' => (int) $statsRow->invoiced,
        ];

        return response()->json([
            'success' => true,
            'data'    => $rows,
            'stats'   => $stats,
            'meta'    => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
                'has_more'     => $page < max(1, ceil($total / $perPage)),
            ],
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
                      'salesInvoice:sales_invoice_id,invoice_number',
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
                    'sales_invoice_id'     => $row->sales_invoice_id,
                    'sales_invoice_number' => $row->salesInvoice?->invoice_number,
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

        /* ✅ Pagination بدل جلب كل الصفوف دفعة واحدة */
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        $page    = max(1, (int) $request->input('page', 1));

        $q = DistributionDetail::query()
            ->with([
                'distribution:id,code,distribution_date,status',
                'purchaseInvoice:purchase_invoice_id,invoice_number',
                'salesInvoice:sales_invoice_id,invoice_number',
                'movementDetail.item:itemID,itemName2',
                'movementDetail.type:id,name',
                'movementDetail.unit:UnitID,UnitName',
            ])
            ->where('customer_id', $customerId);

        if ($filter === 'invoiced')     $q->whereNotNull('invoiced_at');
        if ($filter === 'not_invoiced') $q->whereNull('invoiced_at');

        /* ✅ الملخص عبر استعلام SQL تجميعي واحد (بدل تجميع كل الصفوف في PHP)
           — يعمل على كامل المجموعة المفلترة وليس على صفحة واحدة فقط. */
        $agg = DB::table('distribution_details as dd')
            ->join('inventory_movement_details as imd', 'imd.movement_detail_id', '=', 'dd.movement_detail_id')
            ->leftJoin('units as u', 'u.UnitID', '=', 'imd.unit_id')
            ->where('dd.customer_id', $customerId)
            ->when($filter === 'invoiced', fn ($qq) => $qq->whereNotNull('dd.invoiced_at'))
            ->when($filter === 'not_invoiced', fn ($qq) => $qq->whereNull('dd.invoiced_at'))
            ->selectRaw("COALESCE(u.UnitName, '—') as unit_name,
                COUNT(*) as rows_count,
                SUM(dd.quantity) as total_qty,
                SUM(dd.quantity * dd.unit_cost) as total_cost,
                SUM(dd.quantity * dd.sale_price) as total_sale,
                SUM(CASE WHEN dd.invoiced_at IS NULL THEN dd.quantity ELSE 0 END) as not_invoiced_qty,
                SUM(CASE WHEN dd.invoiced_at IS NULL THEN dd.quantity * dd.unit_cost ELSE 0 END) as not_invoiced_cost,
                SUM(CASE WHEN dd.invoiced_at IS NULL THEN 1 ELSE 0 END) as not_invoiced_count")
            ->groupBy('u.UnitName')
            ->get();

        $qtyByUnit            = $agg->pluck('total_qty', 'unit_name')->map(fn ($v) => (float) $v)->all();
        $notInvoicedQtyByUnit = $agg->pluck('not_invoiced_qty', 'unit_name')->map(fn ($v) => (float) $v)->all();

        $total = (clone $q)->count();

        $rows = $q->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()->map(fn($d) => [
            'id'             => $d->id,
            'distribution'   => $d->distribution?->code,
            'dist_date'      => optional($d->distribution?->distribution_date)->format('Y-m-d'),
            'dist_status'    => $d->distribution?->status,
            'invoice_number'      => $d->purchaseInvoice?->invoice_number,
            'sales_invoice_id'     => $d->sales_invoice_id,
            'sales_invoice_number' => $d->salesInvoice?->invoice_number,
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

        /* ✅ حذف الحلقة اليدوية — الملخص جاء من استعلام SQL أعلاه */

        return response()->json([
            'success'  => true,
            'customer' => [
                'id'    => $customer->CustomersID,
                'name'  => $customer->CustomersName2,
                'phone' => $customer->CusPhone,
                'code'  => $customer->account?->accCode ?? '',
            ],
            'summary' => [
                'rows_count'           => (int) $agg->sum('rows_count'),
                'qty_by_unit'          => $qtyByUnit,
                'total_cost'           => (float) $agg->sum('total_cost'),
                'total_sale'           => (float) $agg->sum('total_sale'),
                'not_invoiced_count'   => (int) $agg->sum('not_invoiced_count'),
                'not_invoiced_qty'     => $notInvoicedQtyByUnit,
                'not_invoiced_cost'    => (float) $agg->sum('not_invoiced_cost'),
            ],
            'data' => $rows,
            'meta' => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
                'has_more'     => $page < max(1, ceil($total / $perPage)),
            ],
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
                'salesInvoice:sales_invoice_id,invoice_number',
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
                'invoice_number'      => $d->purchaseInvoice?->invoice_number,
                'sales_invoice_id'     => $d->sales_invoice_id,
                'sales_invoice_number' => $d->salesInvoice?->invoice_number,
                'type_name'      => $d->movementDetail?->type?->name ?? '—',
                'unit_name'      => $d->movementDetail?->unit?->UnitName ?? '—',
                'quantity'       => (float) $d->quantity,
                'is_invoiced'    => $d->invoiced_at !== null,
            ]);

        /* ✅ تجميع لكل وحدة على حدة — لا خلط بين الوحدات:
           الداخل من الشراء + الموزّع + المتبقي، مفصولة بالوحدة (SQL). */
        $origByUnit = DB::table('inventory_movement_details as imd')
            ->join('inventory_movements as im', 'im.movement_id', '=', 'imd.movement_id')
            ->where('imd.item_id', $itemId)
            ->where('im.movement_type', InventoryMovement::TYPE_PURCHASE)
            ->groupBy('imd.unit_id')
            ->selectRaw('COALESCE(imd.unit_id, 0) as unit_id, SUM(imd.quantity) as qty')
            ->pluck('qty', 'unit_id');

        $distByUnit = DB::table('distribution_details as dd')
            ->join('inventory_movement_details as imd', 'imd.movement_detail_id', '=', 'dd.movement_detail_id')
            ->where('imd.item_id', $itemId)
            ->groupBy('imd.unit_id')
            ->selectRaw("COALESCE(imd.unit_id, 0) as unit_id,
                SUM(dd.quantity) as qty,
                SUM(dd.quantity * dd.unit_cost) as cost,
                SUM(dd.quantity * dd.sale_price) as sale")
            ->get()
            ->keyBy('unit_id');

        $unitIds = $origByUnit->keys()->merge($distByUnit->keys())->unique()->values();
        $unitNames = $unitIds->filter()->isEmpty()
            ? collect()
            : DB::table('units')->whereIn('UnitID', $unitIds->filter())->pluck('UnitName', 'UnitID');

        $qtyByUnitSummary = $unitIds->mapWithKeys(function ($uid) use ($origByUnit, $distByUnit, $unitNames) {
            $name = ((int) $uid === 0)
                ? '—'
                : ($unitNames[$uid] ?? ("وحدة {$uid}"));
            $orig = (float) ($origByUnit[$uid] ?? 0);
            $dRow = $distByUnit->get($uid);
            $dist = (float) ($dRow->qty ?? 0);
            return [$name => [
                'original'    => $orig,
                'distributed' => $dist,
                'remaining'   => max(0, $orig - $dist),
                'total_cost'  => (float) ($dRow->cost ?? 0),
                'total_sale'  => (float) ($dRow->sale ?? 0),
            ]];
        })->all();

        $unitName = optional($rows->first())['unit_name'] ?? '—';

        $qtyByCustomer = $rows->groupBy('customer_id')->map(fn($g) => [
            'customer_name'  => $g->first()['customer_name'],
            'customer_phone' => $g->first()['customer_phone'],
            'total_qty'      => (float) $g->sum('quantity'),
            /* ✅ كميات العميل مفصولة بالوحدة — لا خلط */
            'qty_by_unit'    => $g->groupBy('unit_name')
                ->map(fn($gu) => (float) $gu->sum('quantity'))->all(),
        ])->values();

        return response()->json([
            'success' => true,
            'item' => [
                'id'   => $item->itemID,
                'name' => $item->itemName2,
            ],
            'summary' => [
                'qty_by_unit'       => $qtyByUnitSummary,
                'original_qty'      => array_sum(array_column($qtyByUnitSummary, 'original')),
                'total_distributed' => array_sum(array_column($qtyByUnitSummary, 'distributed')),
                'remaining'         => array_sum(array_column($qtyByUnitSummary, 'remaining')),
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

        try {
            $salesInvoice = $this->distributionService->issueInvoice($customerId, $ids);
        } catch (ValidationException $e) {
            $firstMessage = collect($e->errors())->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => $firstMessage ?? 'لا توجد تفاصيل قابلة للفوترة.',
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'تم إصدار فاتورة البيع رقم ' . $salesInvoice->invoice_number
                . ' للعميل (إجمالي: ' . number_format((float) $salesInvoice->total_in_invoice_currency, 2) . ').',
            'customer_id' => $customerId,
            'sales_invoice_id'     => $salesInvoice->sales_invoice_id,
            'sales_invoice_number' => $salesInvoice->invoice_number,
            'sales_invoice_total'  => (float) $salesInvoice->total_in_invoice_currency,
            'detail_ids'           => $validated['distribution_ids'],
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
            'salesInvoice:sales_invoice_id,invoice_number',
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
            'sales_invoice_id'     => $d->sales_invoice_id,
            'sales_invoice_number' => $d->salesInvoice?->invoice_number,
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