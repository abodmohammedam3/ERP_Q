<?php

namespace App\Http\Controllers\Operation\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\StorePurchaseReturnRequest;
use App\Http\Requests\Purchases\UpdatePurchaseReturnRequest;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseReturn;
use App\Services\Purchases\PurchaseReturnService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseReturnApiController extends Controller
{
    protected PurchaseReturnService $service;

    public function __construct(PurchaseReturnService $service)
    {
        $this->service = $service;
    }

    /**
     * قائمة مرتجعات الشراء (JSON)
     */
    public function list(Request $request)
    {
        Permissions::authorize(config('permissions.purchase_returns.view'));

        $search = trim($request->input('search', ''));

        $query = PurchaseReturn::query()
            ->with(['supplierAccount', 'coin', 'originalInvoice'])
            ->orderBy('purchase_return_id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "{$search}%")
                  ->orWhere('reference', 'like', "{$search}%")
                  ->orWhereHas('originalInvoice', function ($q2) use ($search) {
                      $q2->where('invoice_number', 'like', "{$search}%");
                  })
                  ->orWhereHas('supplierAccount', function ($q3) use ($search) {
                      $q3->where('accName', 'like', "%{$search}%")
                         ->orWhere('accCode', 'like', "{$search}%");
                  });
            });
        }

        $returns = $query->limit(100)->get()->map(function ($ret) {
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
                'is_posted'                    => (bool) $ret->is_posted,
            ];
        });

        return response()->json(['data' => $returns]);
    }

    /**
     * ✅ اختيار الفاتورة الأصلية لمرتجع الشراء.
     *
     * يُرجع فقط الفواتير المرحّلة التي ما زال بها رصيد قابل للإرجاع
     * (أي سطر لديه availableForReturn > 0) — لإخفاء المُرجَع بالكامل
     * من نتائج البحث.
     */
    public function invoicePicker(Request $request)
    {
        Permissions::authorize(config('permissions.purchase_invoices.view'));

        $search = trim($request->input('search', ''));

        $query = PurchaseInvoice::query()
            ->with(['supplierAccount', 'coin', 'details'])
            ->where('is_posted', true)
            ->orderByDesc('purchase_invoice_id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "{$search}%")
                  ->orWhere('reference', 'like', "{$search}%")
                  ->orWhereHas('supplierAccount', function ($q2) use ($search) {
                      $q2->where('accName', 'like', "%{$search}%")
                         ->orWhere('accCode', 'like', "{$search}%");
                  });
            });
        }

        $invoices = $query->limit(100)->get()
            ->map(function ($inv) {
                $remaining = 0.0;

                foreach ($inv->details as $detail) {
                    $remaining += max(
                        0,
                        $this->service->availableForReturn(
                            (int) $detail->purchase_invoice_detail_id
                        )
                    );
                }

                if ($remaining <= 0.000001) {
                    return null;
                }

                return [
                    'purchase_invoice_id' => $inv->purchase_invoice_id,
                    'invoice_number'      => $inv->invoice_number,
                    'invoice_date'        => optional($inv->invoice_date)->format('Y-m-d'),
                    'supplier_name'       => $inv->supplierAccount->accName ?? '',
                    'coin_name'           => $inv->coin->coinsName ?? '',
                    'payment_method'      => $inv->payment_method,
                    'total'               => $inv->total_in_invoice_currency,
                    'is_posted'           => (bool) $inv->is_posted,
                    'remaining_quantity'  => (float) $remaining,
                    'is_fully_returned'   => false,
                ];
            })
            ->filter()
            ->values();

        return response()->json(['data' => $invoices]);
    }

    /**
     * عرض مرتجع شراء واحد
     * ✅ مع حساب الكمية المتاحة بشكل صحيح
     */
    public function show($id)
    {
        Permissions::authorize(config('permissions.purchase_returns.view'));

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
                'coin_code'                    => $purchaseReturn->coin->coinsCode ?? '',
                'warehouse_id'                 => $purchaseReturn->warehouse_id,
                'warehouse_name'               => $purchaseReturn->warehouse->StockName ?? '',
                'exchange_rate'                => (float) $purchaseReturn->exchange_rate,
                'payment_method'               => (int) $purchaseReturn->payment_method,
                'items_total'                  => (float) $purchaseReturn->items_total,
                'discount_total'               => (float) $purchaseReturn->discount_total,
                'statement'                    => $purchaseReturn->statement,
                'reference'                    => $purchaseReturn->reference,
                'total'                        => (float) $purchaseReturn->total_in_return_currency,
                'total_in_base'                => (float) $purchaseReturn->total_in_local_currency,
                'is_posted'                    => (bool) $purchaseReturn->is_posted,
                'posted_at'                    => optional($purchaseReturn->posted_at)->format('Y-m-d H:i'),
                'posted_by'                    => $purchaseReturn->posted_by,
                'created_by'                   => $purchaseReturn->created_by,
                'updated_by'                   => $purchaseReturn->updated_by,
                'entryID'                      => $purchaseReturn->entryID,
            ],
            'details' => $purchaseReturn->details->map(function ($d) use ($purchaseReturn) {
            /* ✅ الكمية المتاحة للعرض = الأصلية − كل المرتجعات **عدا هذا المرتجع** + كمية هذا السطر
               (لأن الكمية الحالية في السطر ستُحفظ/تُحدَّث من هذه الشاشة)،
               ومقصوصة بالرصيد الفعلي للمخزن داخل availableForReturn.
               الطريقة القديمة كانت تُرجع الكمية الأصلية كاملة لأنها تستثني
               كل المرتجعات ثم تعيد إضافة كمية هذا المرتجع. */
            $availableForDisplay = $this->service->availableForReturn(
                $d->purchase_invoice_detail_id,
                (int) $purchaseReturn->purchase_return_id
            ) + (float) $d->quantity;   // ✅ نفس نمط مرتجع البيع (SalesReturnApiController::show)

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
                    'available_quantity'         => (float) $availableForDisplay,  // ✅
                    'price'                      => (float) $d->price,
                    'unit_cost'                  => (float) $d->unit_cost,
                    'discount'                   => (float) $d->discount,
                    'total'                      => (float) $d->total,
                ];
            }),
        ]);
    }

    /**
     * رقم المرتجع التالي
     */
    public function nextNumber()
    {
        Permissions::authorize(config('permissions.purchase_returns.create'));

        $maxNumber = PurchaseReturn::max('return_number');
        $next      = ((int) $maxNumber) + 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * ✅ دفعات الفرز المشتقة من فاتورة شراء (المرحلة 1 — عرض فقط)
     */
    public function sortedBatches(Request $request)
    {
        Permissions::authorize(config('permissions.purchase_returns.view'));

        $invoiceId = (int) $request->input('purchase_invoice_id');

        if (!$invoiceId) {
            return response()->json(['data' => []]);
        }

        $invoice = PurchaseInvoice::find($invoiceId);

        if (!$invoice) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);
        }

        return response()->json([
            'data' => $this->service->sortedBatchesForInvoice($invoice),
        ]);
    }

    /**
     * الكمية القابلة للإرجاع لسطر فاتورة شراء معين
     */
    public function availableQuantity(Request $request)
    {
        Permissions::authorize(config('permissions.purchase_returns.view'));

        $detailId       = (int) $request->input('purchase_invoice_detail_id');
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
     * حفظ مرتجع شراء جديد
     */
    public function store(StorePurchaseReturnRequest $request)
    {
        Permissions::authorize(config('permissions.purchase_returns.create'));

        try {
            $purchaseReturn = $this->service->create($request->validated());

            return response()->json([
                'message'            => 'تم حفظ وترحيل مرتجع الشراء بنجاح',
                'purchase_return_id' => $purchaseReturn->purchase_return_id,
                'return_number'      => $purchaseReturn->return_number,
                'is_posted'          => (bool) $purchaseReturn->is_posted,
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
    public function update(UpdatePurchaseReturnRequest $request, $id)
    {
        Permissions::authorize(config('permissions.purchase_returns.update'));

        try {
            $this->service->update((int) $id, $request->validated());

            return response()->json([
                'message' => 'تم تحديث مرتجع الشراء بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'مرتجع الشراء غير موجود'], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

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
        Permissions::authorize(config('permissions.purchase_returns.delete'));

        try {
            $this->service->delete((int) $id);

            return response()->json(['message' => 'تم حذف مرتجع الشراء بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'مرتجع الشراء غير موجود'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase return destroy failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل حذف مرتجع الشراء',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ترحيل مرتجع شراء
     */
    public function post($id)
    {
        Permissions::authorize(config('permissions.purchase_returns.post'));

        try {
            $this->service->post((int) $id);

            return response()->json(['message' => 'تم ترحيل مرتجع الشراء بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'مرتجع الشراء غير موجود'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase return post failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل ترحيل مرتجع الشراء',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * إلغاء ترحيل مرتجع شراء
     */
    public function unpost($id)
    {
        Permissions::authorize(config('permissions.purchase_returns.unpost'));

        try {
            $this->service->unpost((int) $id);

            return response()->json(['message' => 'تم إلغاء ترحيل مرتجع الشراء بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'مرتجع الشراء غير موجود'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase return unpost failed', [
                'message' => $e->getMessage(),
                'id'      => $id,
            ]);

            return response()->json([
                'message' => 'فشل إلغاء ترحيل مرتجع الشراء',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}