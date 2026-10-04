<?php

namespace App\Http\Controllers\Operation\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesInvoiceRequest;
use App\Http\Requests\Sales\UpdateSalesInvoiceRequest;
use App\Models\Sales\SalesInvoice;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SalesInvoiceController extends Controller
{
    /**
     * @var SalesInvoiceService
     */
    protected SalesInvoiceService $service;

    /**
     * @var InventoryService
     */
    protected InventoryService $inventoryService;

    public function __construct(
        SalesInvoiceService $service,
        InventoryService $inventoryService
    ) {
        $this->service = $service;
        $this->inventoryService = $inventoryService;
    }

    /**
     * عرض شاشة فواتير البيع
     */
    public function index()
    {
        return view('operation.sales.invoices.index');
    }

    /**
     * قائمة الفواتير (JSON) — للبحث
     */
    public function list(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = SalesInvoice::query()
            ->with(['customerAccount', 'coin'])
            ->orderBy('sales_invoice_id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('customerAccount', function ($q2) use ($search) {
                      $q2->where('accName', 'like', "%{$search}%")
                         ->orWhere('accCode', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->limit(50)->get()->map(function ($inv) {
            return [
                'sales_invoice_id' => $inv->sales_invoice_id,
                'invoice_number'   => $inv->invoice_number,
                'invoice_date'     => optional($inv->invoice_date)->format('Y-m-d'),
                'customer_name'    => $inv->customerAccount->accName ?? '',
                'coin_name'        => $inv->coin->coinsName ?? '',
                'payment_method'   => $inv->payment_method,
                'total'            => $inv->total_in_invoice_currency,
            ];
        });

        return response()->json(['data' => $invoices]);
    }

    /**
     * عرض فاتورة واحدة مع التفاصيل
     */
    public function show($id)
    {
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

        return response()->json([
            'header' => [
                'sales_invoice_id'    => $invoice->sales_invoice_id,
                'invoice_number'      => $invoice->invoice_number,
                'invoice_date'        => optional($invoice->invoice_date)->format('Y-m-d'),
                'account_id'          => $invoice->account_id,
                'customer_name'       => $invoice->customerAccount->accName ?? '',
                'payment_account_id'  => $invoice->payment_account_id,
                'payment_account_name'=> $invoice->paymentAccount->accName ?? '',
                'coin_id'             => $invoice->coin_id,
                'coin_name'           => $invoice->coin->coinsName ?? '',
                'exchange_rate'       => (float) $invoice->exchange_rate,
                'payment_method'      => (int) $invoice->payment_method,
                'items_total'         => (float) $invoice->items_total,
                'discount_total'      => (float) $invoice->discount_total,
                'statement'           => $invoice->statement,
                'reference'           => $invoice->reference,
                'total'               => $invoice->total_in_invoice_currency,
            ],
            'details' => $invoice->details->map(function ($d) {
                return [
                    'sales_invoice_detail_id' => $d->sales_invoice_detail_id,
                    'item_id'         => $d->item_id,
                    'item_name'       => $d->item->itemName2 ?? '',
                    'type_id'         => $d->type_id,
                    'type_name'       => $d->type->name ?? '',
                    'unit_id'         => $d->unit_id,
                    'unit_name'       => $d->unit->UnitName ?? '',
                    'warehouse_id'    => $d->warehouse_id,
                    'warehouse_name'  => $d->warehouse->StockName ?? '',
                    'code'            => $d->code,
                    'quantity'        => (float) $d->quantity,
                    'price'           => (float) $d->price,
                    'cost_price'      => $d->cost_price !== null
                                            ? (float) $d->cost_price : null,
                    'discount'        => (float) $d->discount,
                    'total'           => (float) $d->total,
                ];
            }),
        ]);
    }

    /**
     * رقم الفاتورة التالي (للعرض فقط)
     */
    public function nextNumber()
    {
        $last = SalesInvoice::orderBy('sales_invoice_id', 'desc')->first();
        $next = $last ? ((int) $last->invoice_number + 1) : 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * آخر تكلفة شراء لصنف في مخزن (مساعد لفاتورة البيع)
     */
    public function lastCost(Request $request)
    {
        $itemId      = (int) $request->input('item_id');
        $warehouseId = (int) $request->input('warehouse_id');

        if (!$itemId || !$warehouseId) {
            return response()->json(['cost' => 0]);
        }

        $cost = $this->inventoryService->lastCost($itemId, $warehouseId);

        return response()->json(['cost' => $cost]);
    }

    /**
     * حفظ فاتورة جديدة
     */
    public function store(StoreSalesInvoiceRequest $request)
    {
        try {
            $invoice = $this->service->create($request->validated());

            return response()->json([
                'message'          => 'تم حفظ الفاتورة بنجاح',
                'sales_invoice_id' => $invoice->sales_invoice_id,
                'invoice_number'   => $invoice->invoice_number,
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

    /**
     * تحديث فاتورة
     */
    public function update(UpdateSalesInvoiceRequest $request, $id)
    {
        try {
            $this->service->update((int) $id, $request->validated());

            return response()->json([
                'message' => 'تم تحديث الفاتورة بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'الفاتورة غير موجودة',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

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

    /**
     * حذف فاتورة
     */
    public function destroy($id)
    {
        try {
            $this->service->delete((int) $id);

            return response()->json([
                'message' => 'تم حذف الفاتورة بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'الفاتورة غير موجودة',
            ], 404);

        } catch (\Throwable $e) {
            Log::error('Sales invoice destroy failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'فشل حذف الفاتورة',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

        /**
     * طباعة فاتورة البيع
     */
    public function print($id)
    {
        $invoice = SalesInvoice::with([
            'details.item',
            'details.type',
            'details.unit',
            'customerAccount',
            'coin',
        ])->find($id);

        if (!$invoice) {
            abort(404, 'الفاتورة غير موجودة');
        }

        return view('print.sales-invoice', compact('invoice'));
    }





}