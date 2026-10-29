<?php

namespace App\Http\Controllers\Operation\PurchaseDistribution;

use App\Http\Controllers\Controller;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Distribution;
use App\Models\Purchases\DistributionDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
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
        return view('operation.purchase-distribution.customer-invoices');
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
    // أصناف فاتورة محددة (من حركة المخزون)
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
    // بحث العملاء (يدعم account-picker)
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
                'code'  => $c->account?->accCode ?? '',   // ← الحساب التحليلي
                'name'  => $c->CustomersName2,
                'phone' => $c->CusPhone ?? '',
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ============================================================
    // حفظ توزيع (جديد أو تعديل)
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
    // عرض توزيع
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

        return response()->json([
            'success' => true,
            'header'  => [
                'id'                => $dist->id,
                'code'              => $dist->code,
                'distribution_date' => optional($dist->distribution_date)->format('Y-m-d'),
                'status'            => $dist->status,
                'notes'             => $dist->notes,
            ],
            'details' => $dist->details->map(fn($d) => [
                'id'                  => $d->id,
                'movement_detail_id'  => $d->movement_detail_id,
                'movement_id'         => $d->movement_id,
                'purchase_invoice_id' => $d->purchase_invoice_id,
                'invoice_number'      => $d->purchaseInvoice?->invoice_number,
                'customer_id'         => $d->customer_id,
                'customer_name'       => $d->customer?->CustomersName2,
                'item_name'           => $d->movementDetail?->item?->itemName2 ?? '—',
                'type_name'           => $d->movementDetail?->type?->name ?? '—',
                'unit_name'           => $d->movementDetail?->unit?->UnitName ?? '—',
                'code'                => $d->movementDetail?->code ?? '—',
                'quantity'            => (float) $d->quantity,
                'unit_cost'           => (float) $d->unit_cost,
                'sale_price'          => (float) $d->sale_price,
                'sales_invoice_id'    => $d->sales_invoice_id,
            ]),
        ]);
    }

    // ============================================================
    // حذف توزيع (draft فقط)
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
    // سجل التوزيعات
    // ============================================================
    public function list(Request $request)
    {
        $q = Distribution::query()->withCount('details');
        if ($request->filled('status'))      $q->where('status', $request->input('status'));
        if ($request->filled('date_from'))   $q->whereDate('distribution_date', '>=', $request->input('date_from'));
        if ($request->filled('date_to'))     $q->whereDate('distribution_date', '<=', $request->input('date_to'));
        if ($request->filled('customer_id')) {
            $q->whereHas('details', fn($d) => $d->where('customer_id', $request->input('customer_id')));
        }

        $rows = $q->orderByDesc('id')->limit(200)->get()->map(fn($d) => [
            'id'                => $d->id,
            'code'              => $d->code,
            'distribution_date' => optional($d->distribution_date)->format('Y-m-d'),
            'status'            => $d->status,
            'details_count'     => $d->details_count,
        ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ============================================================
    // توزيعات عميل (غير مفوترة)
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
}