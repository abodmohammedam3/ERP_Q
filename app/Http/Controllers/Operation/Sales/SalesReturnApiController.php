<?php

namespace App\Http\Controllers\Operation\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesReturnRequest;
use App\Http\Requests\Sales\UpdateSalesReturnRequest;
use App\Models\Sales\SalesReturn;
use App\Services\Sales\SalesReturnService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\Sales\SalesReturnResource;

class SalesReturnApiController extends Controller
{
    protected SalesReturnService $service;

    public function __construct(SalesReturnService $service)
    {
        $this->service = $service;
    }

    /**
 * قائمة مرتجعات البيع (JSON)
 */
public function list(Request $request)
{
    Permissions::authorize(config('permissions.sales_returns.view'));

    $search = trim($request->input('search', ''));

    $query = SalesReturn::query()
        ->with(['customerAccount', 'coin', 'originalInvoice'])
        ->orderBy('sales_return_id', 'desc');

    if ($search !== '') {
        $query->where(function ($q) use ($search) {
            $q->where('return_number', 'like', "{$search}%")
              ->orWhere('reference', 'like', "{$search}%")
              ->orWhereHas('originalInvoice', function ($q2) use ($search) {
                  $q2->where('invoice_number', 'like', "{$search}%");
              })
              ->orWhereHas('customerAccount', function ($q3) use ($search) {
                  $q3->where('accName', 'like', "%{$search}%")
                     ->orWhere('accCode', 'like', "{$search}%");
              });
        });
    }

    $returns = $query->limit(100)->get()->map(function ($ret) {
        return [
            'sales_return_id'           => $ret->sales_return_id,
            'return_number'             => $ret->return_number,
            'return_date'               => optional($ret->return_date)->format('Y-m-d'),
            'original_sales_invoice_id' => $ret->original_sales_invoice_id,
            'original_invoice_number'   => $ret->originalInvoice->invoice_number ?? '',
            'customer_name'             => $ret->customerAccount->accName ?? '',
            'coin_name'                 => $ret->coin->coinsName ?? '',
            'payment_method'            => (int) $ret->payment_method,
            'payment_method_label'      => $ret->journal_doc_type_label,
            'total'                     => (float) $ret->total_in_return_currency,
            'is_posted'                 => (bool) $ret->is_posted,
        ];
    });

    return response()->json(['data' => $returns]);
}

    /**
 * عرض مرتجع واحد — باستخدام Resource
 */
public function show($id)
{
    Permissions::authorize(config('permissions.sales_returns.view'));

    $salesReturn = SalesReturn::with([
        'details.item',
        'details.type',
        'details.unit',
        'details.warehouse',
        'details.originalDetail',
        'customerAccount',
        'paymentAccount',
        'coin',
        'warehouse',
        'originalInvoice',
    ])->find($id);

    if (!$salesReturn) {
        return response()->json(['message' => 'مرتجع البيع غير موجود'], 404);
    }

    // ✅ إضافة available_quantity ديناميكياً لكل تفصيل
    $salesReturn->details->each(function ($detail) use ($salesReturn) {
        $available = $this->service->availableForReturn(
            $detail->sales_invoice_detail_id,
            $salesReturn->sales_return_id
        );
        $detail->available_quantity = $available + (float) $detail->quantity;
    });

    return new \App\Http\Resources\Sales\SalesReturnResource($salesReturn);
}

    /**
     * رقم المرتجع التالي
     */
    public function nextNumber()
    {
        Permissions::authorize(config('permissions.sales_returns.create'));

        $maxNumber = SalesReturn::max('return_number');
        $next      = ((int) $maxNumber) + 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * الكمية القابلة للإرجاع لسطر فاتورة بيع
     */
    public function availableQuantity(Request $request)
    {
        Permissions::authorize(config('permissions.sales_returns.view'));

        $detailId       = (int) $request->input('sales_invoice_detail_id');
        $exceptReturnId = $request->input('except_return_id')
            ? (int) $request->input('except_return_id')
            : null;

        if (!$detailId) {
            return response()->json(['available' => 0]);
        }

        $available = $this->service->availableForReturn($detailId, $exceptReturnId);

        return response()->json(['available' => $available]);
    }

    /**
     * حفظ مرتجع جديد
     */
    public function store(StoreSalesReturnRequest $request)
    {
        Permissions::authorize(config('permissions.sales_returns.create'));

        try {
            $salesReturn = $this->service->create($request->validated());

            return response()->json([
                'message'         => 'تم حفظ وترحيل مرتجع البيع بنجاح',
                'sales_return_id' => $salesReturn->sales_return_id,
                'return_number'   => $salesReturn->return_number,
                'is_posted'       => (bool) $salesReturn->is_posted,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales return store failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'فشل حفظ مرتجع البيع',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تحديث مرتجع
     */
    public function update(UpdateSalesReturnRequest $request, $id)
    {
        Permissions::authorize(config('permissions.sales_returns.update'));

        try {
            $this->service->update((int) $id, $request->validated());

            return response()->json([
                'message' => 'تم تحديث مرتجع البيع بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'مرتجع البيع غير موجود'], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales return update failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'فشل تحديث مرتجع البيع',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * حذف مرتجع
     */
    public function destroy($id)
    {
        Permissions::authorize(config('permissions.sales_returns.delete'));

        try {
            $this->service->delete((int) $id);

            return response()->json(['message' => 'تم حذف مرتجع البيع بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'مرتجع البيع غير موجود'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales return destroy failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل حذف مرتجع البيع',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ترحيل مرتجع
     */
    public function post($id)
    {
        Permissions::authorize(config('permissions.sales_returns.post'));

        try {
            $this->service->post((int) $id);

            return response()->json(['message' => 'تم ترحيل مرتجع البيع بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'مرتجع البيع غير موجود'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales return post failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل ترحيل مرتجع البيع',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * إلغاء ترحيل مرتجع
     */
    public function unpost($id)
    {
        Permissions::authorize(config('permissions.sales_returns.unpost'));

        try {
            $this->service->unpost((int) $id);

            return response()->json(['message' => 'تم إلغاء ترحيل مرتجع البيع بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'مرتجع البيع غير موجود'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Sales return unpost failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل إلغاء ترحيل مرتجع البيع',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}