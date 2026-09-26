<?php

namespace App\Http\Controllers\Operation\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Accounting\Coin;
use App\Models\Accounting\Bank;
use App\Services\Purchases\PurchaseInvoiceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PurchaseInvoiceController extends Controller
{
    protected PurchaseInvoiceService $service;

    public function __construct(PurchaseInvoiceService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        session()->save();

        $systemCurrency = Coin::where('coinsSystem', 1)
            ->first(['coinsID', 'coinsCode']);

        return view('operation.purchases.invoicesPurch.index', [
            'systemCurrencyId'   => $systemCurrency->coinsID ?? null,
            'systemCurrencyCode' => $systemCurrency->coinsCode ?? '',
        ]);
    }

    /**
     * ✅ قائمة الفواتير (JSON) — بحث موسّع + ترتيب تصاعدي
     */
    public function list(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = PurchaseInvoice::query()
            ->with(['supplierAccount', 'coin', 'details.item', 'details.type'])
            ->orderBy('purchase_invoice_id', 'asc');   // ✅ تصاعدي

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                // رقم الفاتورة
                $q->where('invoice_number', 'like', "%{$search}%")
                  // المرجع
                  ->orWhere('reference', 'like', "%{$search}%")
                  // البيان
                  ->orWhere('statement', 'like', "%{$search}%")
                  // المورد
                  ->orWhereHas('supplierAccount', function ($q2) use ($search) {
                      $q2->where('accName', 'like', "%{$search}%")
                         ->orWhere('accCode', 'like', "%{$search}%");
                  })
                  // الأصناف
                  ->orWhereHas('details.item', function ($q2) use ($search) {
                      $q2->where('itemName2', 'like', "%{$search}%");
                  })
                  // الأنواع
                  ->orWhereHas('details.type', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  })
                  // الرمز
                  ->orWhereHas('details', function ($q2) use ($search) {
                      $q2->where('code', 'like', "%{$search}%");
                  });

                // طريقة الدفع (بحث نصي)
                $paymentMap = ['أجل' => 1, 'نقد' => 2, 'بنك' => 3, 'شبكة' => 4];
                foreach ($paymentMap as $label => $val) {
                    if (mb_strpos($label, $search) !== false || mb_strpos($search, $label) !== false) {
                        $q->orWhere('payment_method', $val);
                    }
                }
            });
        }

        $invoices = $query->limit(200)->get()->map(function ($inv) {
            return [
                'purchase_invoice_id' => $inv->purchase_invoice_id,
                'invoice_number'      => $inv->invoice_number,
                'invoice_date'        => optional($inv->invoice_date)->format('Y-m-d'),
                'supplier_name'       => $inv->supplierAccount->accName ?? '',
                'coin_name'           => $inv->coin->coinsName ?? '',
                'payment_method'      => $inv->payment_method,
                'total'               => $inv->total_in_invoice_currency,
            ];
        });

        return response()->json(['data' => $invoices]);
    }

    public function show($id)
    {
        $invoice = PurchaseInvoice::with([
            'details.item',
            'details.type',
            'details.unit',
            'supplierAccount',
            'paymentAccount',
            'coin',
            'warehouse',
        ])->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);
        }

        return response()->json([
            'header' => [
                'purchase_invoice_id' => $invoice->purchase_invoice_id,
                'invoice_number'      => $invoice->invoice_number,
                'invoice_date'        => optional($invoice->invoice_date)->format('Y-m-d'),
                'account_id'          => $invoice->account_id,
                'supplier_name'       => $invoice->supplierAccount->accName ?? '',
                'payment_account_id'  => $invoice->payment_account_id,
                'payment_account_name'=> $invoice->paymentAccount->accName ?? '',
                'coin_id'             => $invoice->coin_id,
                'coin_name'           => $invoice->coin->coinsName ?? '',
                'coin_code'           => $invoice->coin->coinsCode ?? '',
                'warehouse_id'        => $invoice->warehouse_id,
                'warehouse_name'      => $invoice->warehouse->StockName ?? '',
                'exchange_rate'       => (float) $invoice->exchange_rate,
                'payment_method'      => (int) $invoice->payment_method,
                'items_total'         => (float) $invoice->items_total,
                'discount_total'      => (float) $invoice->discount_total,
                'expenses'            => (float) $invoice->expenses,
                'tax_cost'            => (float) $invoice->tax_cost,
                'transportation'      => (float) $invoice->transportation,
                'other_cost'          => (float) $invoice->other_cost,
                'other_cost_description' => $invoice->other_cost_description,
                'statement'           => $invoice->statement,
                'reference'           => $invoice->reference,
                'total'                => (float) $invoice->total_in_invoice_currency,
                'total_in_base'        => (float) ($invoice->total_in_base_currency ?? 0),
            ],
            'details' => $invoice->details->map(function ($d) {
                return [
                    'purchase_invoice_detail_id' => $d->purchase_invoice_detail_id,
                    'item_id'    => $d->item_id,
                    'item_name'  => $d->item->itemName2 ?? '',
                    'type_id'    => $d->type_id,
                    'type_name'  => $d->type->name ?? '',
                    'unit_id'    => $d->unit_id,
                    'unit_name'  => $d->unit->UnitName ?? '',
                    'code'       => $d->code,
                    'quantity'   => (float) $d->quantity,
                    'price'      => (float) $d->price,
                    'discount'   => (float) $d->discount,
                    'total'      => (float) $d->total,
                ];
            }),
        ]);
    }

    public function nextNumber()
    {
        $last = PurchaseInvoice::orderBy('purchase_invoice_id', 'desc')->first();
        $next = $last ? ((int) $last->invoice_number + 1) : 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * ✅ قائمة البنوك الكاملة (للشبكة)
     */
    public function listBanksFull()
    {
        $banks = Bank::with(['coin'])
            ->where('is_active', 1)
            ->get()
            ->map(function ($b) {
                return [
                    'bankID'             => $b->bankID,
                    'bankName'           => $b->bankName,
                    'accountID'          => $b->accountID,
                    'accountNumber'      => $b->accountNumber ?? '',
                    'coinsID'            => $b->coinsID,
                    'coinsName'          => $b->coin->coinsName ?? '',
                    'coinsExchangeRate'  => $b->coin ? (float) $b->coin->coinsExchangeRate : 0,
                ];
            });

        return response()->json(['data' => $banks]);
    }

    public function store(Request $request)
    {
        $validator = $this->validateInvoice($request);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $invoice = $this->service->create($request);

            return response()->json([
                'message'             => 'تم حفظ الفاتورة بنجاح',
                'purchase_invoice_id' => $invoice->purchase_invoice_id,
                'invoice_number'      => $invoice->invoice_number,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Purchase invoice store failed', [
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

    public function update(Request $request, $id)
    {
        $validator = $this->validateInvoice($request);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $this->service->update((int) $id, $request);

            return response()->json([
                'message' => 'تم تحديث الفاتورة بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'الفاتورة غير موجودة',
            ], 404);

        } catch (\Throwable $e) {
            Log::error('Purchase invoice update failed', [
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
            Log::error('Purchase invoice destroy failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'فشل حذف الفاتورة',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ✅ طباعة فاتورة الشراء — مع بيانات البنك
     */
    public function print($id)
    {
        $invoice = PurchaseInvoice::with([
            'details.item',
            'details.type',
            'details.unit',
            'supplierAccount',
            'warehouse',
            'coin',
            'paymentAccount',
        ])->find($id);

        if (!$invoice) {
            abort(404, 'الفاتورة غير موجودة');
        }

        // ✅ جلب بيانات البنك إن كانت طريقة الدفع بنك/شبكة
        $bank = null;
        if (in_array((int) $invoice->payment_method, [3, 4])) {
            $bank = Bank::where('accountID', $invoice->payment_account_id)
                ->with('coin')
                ->first();
        }

        return view('print.purchase-invoice', compact('invoice', 'bank'));
    }

    // =====================================================
    // التحقق
    // =====================================================

    private function validateInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number'      => ['required', 'string', 'max:50'],
            'invoice_date'        => ['required', 'date'],
            'account_id'          => ['required', 'exists:characcount,accountID'],
            'payment_method'      => ['required', 'integer', 'in:1,2,3,4'],
            'coin_id'             => ['required', 'exists:coins,coinsID'],
            'warehouse_id'        => ['required', 'exists:stocks,StockID'],
            'exchange_rate'       => ['required', 'numeric', 'gt:0'],
            'payment_account_id'  => ['nullable', 'exists:characcount,accountID'],
            'expenses'            => ['nullable', 'numeric', 'min:0'],
            'tax_cost'            => ['nullable', 'numeric', 'min:0'],
            'transportation'      => ['nullable', 'numeric', 'min:0'],
            'other_cost'          => ['nullable', 'numeric', 'min:0'],
            'other_cost_description' => ['nullable', 'string'],
            'statement'           => ['nullable', 'string'],
            'reference'           => ['nullable', 'string'],

            'details'                  => ['required', 'array', 'min:1'],
            'details.*.item_id'        => ['required', 'exists:Items,itemID'],
            'details.*.type_id'        => ['nullable', 'exists:type,id'],
            'details.*.unit_id'        => ['nullable', 'exists:units,UnitID'],
            'details.*.code'           => ['nullable', 'string', 'max:50'],
            'details.*.quantity'       => ['required', 'numeric', 'gt:0'],
            'details.*.price'          => ['required', 'numeric', 'gt:0'],
            'details.*.discount'       => ['nullable', 'numeric', 'min:0'],
        ], [
            'invoice_number.required'  => 'رقم الفاتورة مطلوب',
            'invoice_date.required'    => 'تاريخ الفاتورة مطلوب',
            'account_id.required'      => 'يجب اختيار المورد',
            'account_id.exists'        => 'المورد المحدد غير موجود',
            'payment_method.required'  => 'طريقة الدفع مطلوبة',
            'coin_id.required'         => 'يجب اختيار العملة',
            'coin_id.exists'           => 'العملة المحددة غير موجودة',
            'warehouse_id.required'    => 'يجب اختيار المخزن',
            'warehouse_id.exists'      => 'المخزن المحدد غير موجود',
            'details.required'         => 'يجب إضافة صنف واحد على الأقل',
            'details.min'              => 'يجب إضافة صنف واحد على الأقل',
            'details.*.item_id.required'=> 'يجب اختيار الصنف في كل الصفوف',
            'details.*.item_id.exists' => 'أحد الأصناف المحددة غير موجود',
            'details.*.quantity.gt'    => 'الكمية يجب أن تكون أكبر من صفر',
            'details.*.price.gt'       => 'سعر الوحدة يجب أن يكون أكبر من صفر',
        ]);

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

            foreach ($request->input('details', []) as $i => $row) {
                $qty      = (float) ($row['quantity'] ?? 0);
                $price    = (float) ($row['price'] ?? 0);
                $discount = (float) ($row['discount'] ?? 0);

                if ($discount > $qty * $price) {
                    $v->errors()->add(
                        "details.{$i}.discount",
                        'الخصم لا يمكن أن يتجاوز قيمة الصف'
                    );
                }

                if (max(0, $qty * $price - $discount) <= 0) {
                    $v->errors()->add(
                        "details.{$i}.price",
                        'إجمالي الصف يجب أن يكون أكبر من صفر'
                    );
                }
            }

            $otherCost = (float) $request->input('other_cost', 0);
            $otherDesc = trim($request->input('other_cost_description', ''));
            if ($otherCost > 0 && $otherDesc === '') {
                $v->errors()->add(
                    'other_cost_description',
                    'يجب إدخال وصف التكلفة الأخرى'
                );
            }
        });

        return $validator;
    }
}