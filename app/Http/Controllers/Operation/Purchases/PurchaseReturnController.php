<?php

namespace App\Http\Controllers\Operation\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchases\PurchaseReturn;
use App\Services\Purchases\PurchaseReturnService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PurchaseReturnController extends Controller
{
    protected PurchaseReturnService $service;

    public function __construct(PurchaseReturnService $service)
    {
        $this->service = $service;
    }

    /**
     * عرض شاشة مرتجعات الشراء
     */
    public function index()
    {
        return view('operation.purchases.returns.index');
    }

    /**
     * قائمة مرتجعات الشراء (JSON) - للبحث
     */
    public function list(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = PurchaseReturn::query()
            ->with(['supplierAccount', 'coin', 'originalInvoice'])
            ->orderBy('purchase_return_id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('originalInvoice', function ($q2) use ($search) {
                      $q2->where('invoice_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('supplierAccount', function ($q3) use ($search) {
                      $q3->where('accName', 'like', "%{$search}%")
                         ->orWhere('accCode', 'like', "%{$search}%");
                  });
            });
        }

        $returns = $query->limit(50)->get()->map(function ($ret) {
            return [
                'purchase_return_id'           => $ret->purchase_return_id,
                'return_number'                => $ret->return_number,
                'return_date'                  => optional($ret->return_date)->format('Y-m-d'),
                'original_purchase_invoice_id' => $ret->original_purchase_invoice_id,
                'original_invoice_number'      => $ret->originalInvoice->invoice_number ?? '',
                'supplier_name'                => $ret->supplierAccount->accName ?? '',
                'coin_name'                    => $ret->coin->coinsName ?? '',
                'payment_method'               => $ret->payment_method,
                'total'                        => $ret->total_in_return_currency,
            ];
        });

        return response()->json(['data' => $returns]);
    }

    /**
     * عرض مرتجع شراء واحد مع التفاصيل
     */
    public function show($id)
    {
        $purchaseReturn = PurchaseReturn::with([
            'details.item',
            'details.type',
            'details.unit',
            'details.warehouse',
            'details.originalDetail',
            'supplierAccount',
            'paymentAccount',
            'coin',
            'warehouse',
            'originalInvoice',
        ])->find($id);

        if (!$purchaseReturn) {
            return response()->json(['message' => 'مرتجع الشراء غير موجود'], 404);
        }

        return response()->json([
            'header' => [
                'purchase_return_id'           => $purchaseReturn->purchase_return_id,
                'return_number'                => $purchaseReturn->return_number,
                'return_date'                  => optional($purchaseReturn->return_date)->format('Y-m-d'),
                'original_purchase_invoice_id' => $purchaseReturn->original_purchase_invoice_id,
                'original_invoice_number'      => $purchaseReturn->originalInvoice->invoice_number ?? '',
                'account_id'                   => $purchaseReturn->account_id,
                'supplier_name'                => $purchaseReturn->supplierAccount->accName ?? '',
                'payment_account_id'           => $purchaseReturn->payment_account_id,
                'payment_account_name'         => $purchaseReturn->paymentAccount->accName ?? '',
                'coin_id'                      => $purchaseReturn->coin_id,
                'coin_name'                    => $purchaseReturn->coin->coinsName ?? '',
                'warehouse_id'                 => $purchaseReturn->warehouse_id,
                'warehouse_name'               => $purchaseReturn->warehouse->StockName ?? '',
                'exchange_rate'                => (float) $purchaseReturn->exchange_rate,
                'payment_method'               => (int) $purchaseReturn->payment_method,
                'items_total'                  => (float) $purchaseReturn->items_total,
                'discount_total'               => (float) $purchaseReturn->discount_total,
                'statement'                    => $purchaseReturn->statement,
                'reference'                    => $purchaseReturn->reference,
                'total'                        => $purchaseReturn->total_in_return_currency,
            ],
            'details' => $purchaseReturn->details->map(function ($d) use ($purchaseReturn) {
                $available = $this->service->availableForReturn(
                    $d->purchase_invoice_detail_id,
                    $purchaseReturn->purchase_return_id
                );

                return [
                    'purchase_return_detail_id'  => $d->purchase_return_detail_id,
                    'purchase_invoice_detail_id' => $d->purchase_invoice_detail_id,
                    'item_id'                    => $d->item_id,
                    'item_name'                  => $d->item->itemName2 ?? '',
                    'type_id'                    => $d->type_id,
                    'type_name'                  => $d->type->name ?? '',
                    'unit_id'                    => $d->unit_id,
                    'unit_name'                  => $d->unit->UnitName ?? '',
                    'warehouse_id'               => $d->warehouse_id,
                    'warehouse_name'             => $d->warehouse->StockName ?? '',
                    'code'                       => $d->code,
                    'quantity'                   => (float) $d->quantity,
                    'available_quantity'         => (float) $available,
                    'price'                      => (float) $d->price,
                    'unit_cost'                  => (float) $d->unit_cost,
                    'discount'                   => (float) $d->discount,
                    'total'                      => (float) $d->total,
                ];
            }),
        ]);
    }

    /**
     * رقم المرتجع التالي (للعرض فقط)
     */
    public function nextNumber()
    {
        $last = PurchaseReturn::orderBy('purchase_return_id', 'desc')->first();
        $next = $last ? ((int) $last->return_number + 1) : 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * الكمية القابلة للإرجاع لسطر فاتورة شراء معين
     */
    public function availableQuantity(Request $request)
    {
        $detailId       = (int) $request->input('purchase_invoice_detail_id');
        $exceptReturnId = $request->input('except_return_id') ? (int) $request->input('except_return_id') : null;

        if (!$detailId) {
            return response()->json(['available' => 0]);
        }

        $available = $this->service->availableForReturn($detailId, $exceptReturnId);

        return response()->json(['available' => $available]);
    }

    /**
     * حفظ مرتجع شراء جديد
     */
    public function store(Request $request)
    {
        $validator = $this->validateReturn($request);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $purchaseReturn = $this->service->create($request);

            return response()->json([
                'message'            => 'تم حفظ مرتجع الشراء بنجاح',
                'purchase_return_id' => $purchaseReturn->purchase_return_id,
                'return_number'      => $purchaseReturn->return_number,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase return store failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'فشل حفظ مرتجع الشراء',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تحديث مرتجع شراء
     */
    public function update(Request $request, $id)
    {
        $validator = $this->validateReturn($request);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $this->service->update((int) $id, $request);

            return response()->json([
                'message' => 'تم تحديث مرتجع الشراء بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'مرتجع الشراء غير موجود',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase return update failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'فشل تحديث مرتجع الشراء',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * حذف مرتجع شراء
     */
    public function destroy($id)
    {
        try {
            $this->service->delete((int) $id);

            return response()->json([
                'message' => 'تم حذف مرتجع الشراء بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'مرتجع الشراء غير موجود',
            ], 404);

        } catch (\Throwable $e) {
            Log::error('Purchase return destroy failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'فشل حذف مرتجع الشراء',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * طباعة مرتجع الشراء
     */
    public function print($id)
    {
        $purchaseReturn = PurchaseReturn::with([
            'details.item',
            'details.type',
            'details.unit',
            'supplierAccount',
            'coin',
            'warehouse',
            'originalInvoice',
        ])->find($id);

        if (!$purchaseReturn) {
            abort(404, 'مرتجع الشراء غير موجود');
        }

        return view('print.purchase-return', compact('purchaseReturn'));
    }

    // =====================================================
    // التحقق من البيانات
    // =====================================================

    protected function validateReturn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'return_number'                => ['required', 'string', 'max:50'],
            'return_date'                  => ['required', 'date'],
            'original_purchase_invoice_id' => ['required', 'exists:purchase_invoices,purchase_invoice_id'],
            'account_id'                   => ['required', 'exists:characcount,accountID'],
            'payment_method'               => ['required', 'integer', 'in:1,2,3,4'],
            'coin_id'                      => ['required', 'exists:coins,coinsID'],
            'warehouse_id'                 => ['nullable', 'exists:stocks,StockID'],
            'exchange_rate'                => ['nullable', 'numeric', 'gt:0'],
            'payment_account_id'           => ['nullable', 'exists:characcount,accountID'],
            'statement'                    => ['nullable', 'string'],
            'reference'                    => ['nullable', 'string'],

            'details'                               => ['required', 'array', 'min:1'],
            'details.*.purchase_invoice_detail_id' => ['required', 'exists:purchase_invoice_details,purchase_invoice_detail_id'],
            'details.*.quantity'                    => ['required', 'numeric', 'gt:0'],
            'details.*.price'                       => ['required', 'numeric', 'gt:0'],
            'details.*.discount'                    => ['nullable', 'numeric', 'min:0'],
        ], [
            'return_number.required'                => 'رقم المرتجع مطلوب',
            'return_date.required'                  => 'تاريخ المرتجع مطلوب',
            'original_purchase_invoice_id.required' => 'يجب اختيار الفاتورة الأصلية',
            'original_purchase_invoice_id.exists'   => 'الفاتورة الأصلية غير موجودة',
            'account_id.required'                   => 'يجب اختيار المورد',
            'payment_method.required'               => 'طريقة الدفع مطلوبة',
            'coin_id.required'                      => 'يجب اختيار العملة',
            'exchange_rate.gt'                      => 'سعر الصرف يجب أن يكون أكبر من صفر',
            'details.required'                      => 'يجب إضافة صنف واحد على الأقل للمرتجع',
            'details.min'                           => 'يجب إضافة صنف واحد على الأقل للمرتجع',
            'details.*.purchase_invoice_detail_id.required' => 'سطر الفاتورة الأصلية مطلوب في كل الصفوف',
            'details.*.quantity.gt'                 => 'الكمية المراد إرجاعها يجب أن تكون أكبر من صفر',
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
}