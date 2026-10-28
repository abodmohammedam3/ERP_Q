<?php

namespace App\Http\Controllers\Operation\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesInvoiceRequest;
use App\Http\Requests\Sales\UpdateSalesInvoiceRequest;
use App\Models\Sales\SalesInvoice;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesInvoiceService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\Sales\SalesInvoiceResource;

class SalesInvoiceApiController extends Controller
{
    protected SalesInvoiceService $service;
    protected InventoryService $inventoryService;

    public function __construct(
        SalesInvoiceService $service,
        InventoryService $inventoryService
    ) {
        $this->service = $service;
        $this->inventoryService = $inventoryService;
    }

    public function list(Request $request)
    {
        Permissions::authorize(config('permissions.sales_invoices.view'));

        $search = trim($request->input('search', ''));

        $query = SalesInvoice::query()
            ->with(['customerAccount', 'coin'])
            ->orderByDesc('sales_invoice_id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "{$search}%")
                  ->orWhere('reference', 'like', "{$search}%")
                  ->orWhereHas('customerAccount', function ($q2) use ($search) {
                      $q2->where('accName', 'like', "%{$search}%")
                         ->orWhere('accCode', 'like', "{$search}%");
                  });
            });
        }

        $invoices = $query->limit(100)->get()->map(function ($inv) {
            return [
                'sales_invoice_id' => $inv->sales_invoice_id,
                'invoice_number'   => $inv->invoice_number,
                'invoice_date'     => optional($inv->invoice_date)->format('Y-m-d'),
                'customer_name'    => $inv->customerAccount->accName ?? '',
                'coin_name'        => $inv->coin->coinsName ?? '',
                'payment_method'   => $inv->payment_method,
                'total'            => $inv->total_in_invoice_currency,
                'is_posted'        => (bool) $inv->is_posted,
            ];
        });

        return response()->json(['data' => $invoices]);
    }

   public function show($id)
{
    Permissions::authorize(config('permissions.sales_invoices.view'));

    $invoice = SalesInvoice::with([
        'details.item',
        'details.type',
        'details.unit',
        'details.warehouse',
        'customerAccount',
        'paymentAccount',
        'coin',
    ])->find($id);

    if (!$invoice) {
        return response()->json(['message' => 'الفاتورة غير موجودة'], 404);
    }

    return new SalesInvoiceResource($invoice);
}

    public function nextNumber()
    {
        Permissions::authorize(config('permissions.sales_invoices.create'));

        $maxNumber = SalesInvoice::max('invoice_number');
        $next      = ((int) $maxNumber) + 1;

        return response()->json(['next_number' => (string) $next]);
    }

    public function lastCost(Request $request)
    {
        Permissions::authorize(config('permissions.sales_invoices.view'));

        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');
        $supplierId  = $request->filled('supplier_account_id')
            ? (int) $request->input('supplier_account_id')
            : null;

        if (!$itemId || !$warehouseId) {
            return response()->json(['cost' => 0]);
        }

        /* ✅ تكلفة دفعة المورد المحدد إن أُرسل، وإلا آخر تكلفة إجمالية */
        $cost = $supplierId !== null
            ? $this->inventoryService->lastCostForSupplier($itemId, $warehouseId, null, $supplierId)
            : $this->inventoryService->lastCost($itemId, $warehouseId);

        return response()->json(['cost' => $cost]);
    }

    public function store(StoreSalesInvoiceRequest $request)
    {
        Permissions::authorize(config('permissions.sales_invoices.create'));

        try {
            $invoice = $this->service->create($request->validated());

            return response()->json([
                'message'          => 'تم حفظ وترحيل الفاتورة بنجاح',
                'sales_invoice_id' => $invoice->sales_invoice_id,
                'invoice_number'   => $invoice->invoice_number,
                'is_posted'        => (bool) $invoice->is_posted,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Sales invoice store failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'فشل حفظ الفاتورة',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(UpdateSalesInvoiceRequest $request, $id)
    {
        Permissions::authorize(config('permissions.sales_invoices.update'));

        try {
            $this->service->update((int) $id, $request->validated());

            return response()->json(['message' => 'تم تحديث الفاتورة بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales invoice update failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'فشل تحديث الفاتورة',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy($id)
    {
        Permissions::authorize(config('permissions.sales_invoices.delete'));

        try {
            $this->service->delete((int) $id);

            return response()->json(['message' => 'تم حذف الفاتورة بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales invoice destroy failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل حذف الفاتورة',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function post($id)
    {
        Permissions::authorize(config('permissions.sales_invoices.post'));

        try {
            $this->service->post((int) $id);

            return response()->json(['message' => 'تم ترحيل الفاتورة بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales invoice post failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل ترحيل الفاتورة',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function unpost($id)
    {
        Permissions::authorize(config('permissions.sales_invoices.unpost'));

        try {
            $this->service->unpost((int) $id);

            return response()->json(['message' => 'تم إلغاء ترحيل الفاتورة بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales invoice unpost failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل إلغاء ترحيل الفاتورة',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}