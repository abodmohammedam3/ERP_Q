<?php

namespace App\Http\Controllers\Operation\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesReturnRequest;
use App\Http\Requests\Sales\UpdateSalesReturnRequest;
use App\Models\Sales\SalesReturn;
use App\Services\Sales\SalesReturnService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SalesReturnController extends Controller
{
    protected SalesReturnService $service;

    public function __construct(SalesReturnService $service)
    {
        $this->service = $service;
    }

    /**
     * عرض شاشة مرتجعات البيع
     */
    public function index()
    {
        return view('operation.sales.returns.index');
    }

    /**
     * قائمة مرتجعات البيع (JSON) - للبحث
     */
    public function list(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = SalesReturn::query()
            ->with(['customerAccount', 'coin', 'originalInvoice'])
            ->orderBy('sales_return_id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('originalInvoice', function ($q2) use ($search) {
                      $q2->where('invoice_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('customerAccount', function ($q3) use ($search) {
                      $q3->where('accName', 'like', "%{$search}%")
                         ->orWhere('accCode', 'like', "%{$search}%");
                  });
            });
        }

        $returns = $query->limit(50)->get()->map(function ($ret) {
            return [
                'sales_return_id'           => $ret->sales_return_id,
                'return_number'             => $ret->return_number,
                'return_date'               => optional($ret->return_date)->format('Y-m-d'),
                'original_sales_invoice_id' => $ret->original_sales_invoice_id,
                'original_invoice_number'   => $ret->originalInvoice->invoice_number ?? '',
                'customer_name'             => $ret->customerAccount->accName ?? '',
                'coin_name'                 => $ret->coin->coinsName ?? '',
                'payment_method'            => $ret->payment_method,
                'total'                     => $ret->total_in_return_currency,
            ];
        });

        return response()->json(['data' => $returns]);
    }

    /**
     * عرض مرتجع واحد مع التفاصيل
     */
    public function show($id)
    {
        $salesReturn = SalesReturn::with([
            'details.item',
            'details.type',
            'details.unit',
            'details.warehouse',
            'details.originalDetail',
            'customerAccount',
            'paymentAccount',
            'coin',
            'originalInvoice',
        ])->find($id);

        if (!$salesReturn) {
            return response()->json(['message' => 'مرتجع البيع غير موجود'], 404);
        }

        return response()->json([
            'header' => [
                'sales_return_id'           => $salesReturn->sales_return_id,
                'return_number'             => $salesReturn->return_number,
                'return_date'               => optional($salesReturn->return_date)->format('Y-m-d'),
                'original_sales_invoice_id' => $salesReturn->original_sales_invoice_id,
                'original_invoice_number'   => $salesReturn->originalInvoice->invoice_number ?? '',
                'account_id'                => $salesReturn->account_id,
                'customer_name'             => $salesReturn->customerAccount->accName ?? '',
                'payment_account_id'        => $salesReturn->payment_account_id,
                'payment_account_name'      => $salesReturn->paymentAccount->accName ?? '',
                'coin_id'                   => $salesReturn->coin_id,
                'coin_name'                 => $salesReturn->coin->coinsName ?? '',
                'exchange_rate'             => (float) $salesReturn->exchange_rate,
                'payment_method'            => (int) $salesReturn->payment_method,
                'items_total'               => (float) $salesReturn->items_total,
                'discount_total'            => (float) $salesReturn->discount_total,
                'statement'                 => $salesReturn->statement,
                'reference'                 => $salesReturn->reference,
                'total'                     => $salesReturn->total_in_return_currency,
            ],
            'details' => $salesReturn->details->map(function ($d) use ($salesReturn) {
                $available = $this->service->availableForReturn(
                    $d->sales_invoice_detail_id,
                    $salesReturn->sales_return_id
                );

                return [
                    'sales_return_detail_id'  => $d->sales_return_detail_id,
                    'sales_invoice_detail_id' => $d->sales_invoice_detail_id,
                    'item_id'                 => $d->item_id,
                    'item_name'               => $d->item->itemName2 ?? '',
                    'type_id'                 => $d->type_id,
                    'type_name'               => $d->type->name ?? '',
                    'unit_id'                 => $d->unit_id,
                    'unit_name'               => $d->unit->UnitName ?? '',
                    'warehouse_id'            => $d->warehouse_id,
                    'warehouse_name'          => $d->warehouse->StockName ?? '',
                    'code'                    => $d->code,
                    'quantity'                => (float) $d->quantity,
                    'available_quantity'      => (float) $available,
                    'price'                   => (float) $d->price,
                    'cost_price'              => (float) $d->cost_price,
                    'discount'                => (float) $d->discount,
                    'total'                   => (float) $d->total,
                ];
            }),
        ]);
    }

    /**
     * رقم المرتجع التالي (للعرض فقط)
     */
    public function nextNumber()
    {
        $last = SalesReturn::orderBy('sales_return_id', 'desc')->first();
        $next = $last ? ((int) $last->return_number + 1) : 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * الكمية القابلة للإرجاع لسطر فاتورة بيع معين
     */
    public function availableQuantity(Request $request)
    {
        $detailId       = (int) $request->input('sales_invoice_detail_id');
        $exceptReturnId = $request->input('except_return_id') ? (int) $request->input('except_return_id') : null;

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
        try {
            $salesReturn = $this->service->create($request->validated());

            return response()->json([
                'message'         => 'تم حفظ مرتجع البيع بنجاح',
                'sales_return_id' => $salesReturn->sales_return_id,
                'return_number'   => $salesReturn->return_number,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

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
        try {
            $this->service->update((int) $id, $request->validated());

            return response()->json([
                'message' => 'تم تحديث مرتجع البيع بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'مرتجع البيع غير موجود',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

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
        try {
            $this->service->delete((int) $id);

            return response()->json([
                'message' => 'تم حذف مرتجع البيع بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'مرتجع البيع غير موجود',
            ], 404);

        } catch (\Throwable $e) {
            Log::error('Sales return destroy failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'فشل حذف مرتجع البيع',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * طباعة مرتجع البيع
     */
    public function print($id)
    {
        $salesReturn = SalesReturn::with([
            'details.item',
            'details.type',
            'details.unit',
            'customerAccount',
            'coin',
            'originalInvoice',
        ])->find($id);

        if (!$salesReturn) {
            abort(404, 'مرتجع البيع غير موجود');
        }

        return view('print.sales-return', compact('salesReturn'));
    }

    // =====================================================
    // التحقق من البيانات
    // =====================================================

    /*
    protected function validateReturn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'return_number'             => ['required', 'string', 'max:50'],
            'return_date'               => ['required', 'date'],
            'original_sales_invoice_id' => ['required', 'exists:sales_invoices,sales_invoice_id'],
            'account_id'                => ['required', 'exists:characcount,accountID'],
            'payment_method'            => ['required', 'integer', 'in:1,2,3,4'],
            'coin_id'                   => ['required', 'exists:coins,coinsID'],
            'exchange_rate'             => ['nullable', 'numeric', 'gt:0'],
            'payment_account_id'        => ['nullable', 'exists:characcount,accountID'],
            'statement'                 => ['nullable', 'string'],
            'reference'                 => ['nullable', 'string'],

            'details'                            => ['required', 'array', 'min:1'],
            'details.*.sales_invoice_detail_id'  => ['required', 'exists:sales_invoice_details,sales_invoice_detail_id'],
            'details.*.quantity'                 => ['required', 'numeric', 'gt:0'],
            'details.*.price'                    => ['required', 'numeric', 'gt:0'],
            'details.*.discount'                 => ['nullable', 'numeric', 'min:0'],
        ], [
            'return_number.required'             => 'رقم المرتجع مطلوب',
            'return_date.required'               => 'تاريخ المرتجع مطلوب',
            'original_sales_invoice_id.required' => 'يجب اختيار الفاتورة الأصلية',
            'original_sales_invoice_id.exists'   => 'الفاتورة الأصلية غير موجودة',
            'account_id.required'                => 'يجب اختيار العميل',
            'payment_method.required'            => 'طريقة الدفع مطلوبة',
            'coin_id.required'                   => 'يجب اختيار العملة',
            'exchange_rate.gt'                   => 'سعر الصرف يجب أن يكون أكبر من صفر',
            'details.required'                   => 'يجب إضافة صنف واحد على الأقل للمرتجع',
            'details.min'                        => 'يجب إضافة صنف واحد على الأقل للمرتجع',
            'details.*.sales_invoice_detail_id.required' => 'سطر الفاتورة الأصلية مطلوب في كل الصفوف',
            'details.*.quantity.gt'              => 'الكمية المراد إرجاعها يجب أن تكون أكبر من صفر',
        ]);

        // ✅ التحقق من منطق طريقة الدفع (نفس منطق الفواتير)
        $validator->after(function ($v) use ($request) {
            $method    = (int) $request->input('payment_method');
            $accountId = $request->input('payment_account_id');

            if ($method === 1 && !empty($accountId)) {
                $v->errors()->add(
                    'payment_account_id',
                    'طريقة الدفع "أجل" لا تحتاج إلى حساب دفع'
                );
            }

            if (in_array($method, [2, 3, 4]) && empty($accountId)) {
                $v->errors()->add(
                    'payment_account_id',
                    'يجب اختيار حساب الدفع'
                );
            }
        });

        return $validator;
    }
    */
}