<?php

namespace App\Http\Controllers\Operation\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseInvoiceController extends Controller
{
    /**
     * عرض شاشة فواتير الشراء
     */
    public function index()
    {
        return view('operation.purchases.invoicesPurch.index');
    }

    /**
     * قائمة الفواتير (JSON) — تُستخدم في نافذة البحث
     */
    public function list(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = PurchaseInvoice::query()
            ->with(['supplierAccount', 'coin'])
            ->orderBy('purchase_invoice_id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('supplierAccount', function ($q2) use ($search) {
                      $q2->where('accName', 'like', "%{$search}%")
                         ->orWhere('accCode', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->limit(50)->get()->map(function ($inv) {
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

    /**
     * عرض فاتورة واحدة مع تفاصيلها (JSON)
     */
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
                'total'               => $invoice->total_in_invoice_currency,
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

    /**
     * رقم الفاتورة التالي (مقترح للعرض فقط)
     */
    public function nextNumber()
    {
        $last = PurchaseInvoice::orderBy('purchase_invoice_id', 'desc')->first();
        $next = $last ? ((int) $last->invoice_number + 1) : 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * حفظ فاتورة جديدة
     */
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
            DB::beginTransaction();

            // توليد رقم الفاتورة على الخادم بشكل آمن (مع Lock)
            $last = PurchaseInvoice::lockForUpdate()
                ->orderBy('purchase_invoice_id', 'desc')
                ->first();

            $nextNumber = $last ? ((int) $last->invoice_number + 1) : 1;

            $data = $this->headerData($request);
            $data['invoice_number'] = (string) $nextNumber;

            $invoice = PurchaseInvoice::create($data);

            $this->saveDetails($invoice, $request->input('details', []));

            // إعادة حساب الإجماليات على الخادم
            $this->recalculateTotals($invoice);

            DB::commit();

            return response()->json([
                'message'             => 'تم حفظ الفاتورة بنجاح',
                'purchase_invoice_id' => $invoice->purchase_invoice_id,
                'invoice_number'      => $invoice->invoice_number,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل حفظ الفاتورة',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحديث فاتورة
     */
    public function update(Request $request, $id)
    {
        $invoice = PurchaseInvoice::find($id);
        if (!$invoice) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);
        }

        $validator = $this->validateInvoice($request);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $this->headerData($request);
            unset($data['invoice_number']); // لا نسمح بتغيير رقم الفاتورة

            $invoice->update($data);

            // حذف التفاصيل القديمة وإعادة إضافتها
            $invoice->details()->delete();
            $this->saveDetails($invoice, $request->input('details', []));

            $this->recalculateTotals($invoice);

            DB::commit();

            return response()->json([
                'message' => 'تم تحديث الفاتورة بنجاح',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل تحديث الفاتورة',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف فاتورة
     */
    public function destroy($id)
    {
        $invoice = PurchaseInvoice::find($id);
        if (!$invoice) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);
        }

        try {
            DB::beginTransaction();
            $invoice->details()->delete();
            $invoice->delete();
            DB::commit();

            return response()->json(['message' => 'تم حذف الفاتورة بنجاح']);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل حذف الفاتورة',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // =====================================================
    // دوال مساعدة داخلية
    // =====================================================

    /**
     * التحقق من البيانات
     */
    private function validateInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number'      => ['required', 'string', 'max:50'],
            'invoice_date'        => ['required', 'date'],
            'account_id'          => ['required', 'exists:characcount,accountID'],
            'payment_method'      => ['required', 'integer', 'in:1,2,3,4'],
            'coin_id'             => ['required', 'exists:coins,coinsID'],
            'warehouse_id'        => ['required', 'exists:stocks,StockID'],
            'exchange_rate'       => ['nullable', 'numeric', 'min:0'],
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

        // تحقق منطقي إضافي
        $validator->after(function ($v) use ($request) {
            $method    = (int) $request->input('payment_method');
            $accountId = $request->input('payment_account_id');

            // طريقة الدفع الفوري تتطلب حساب دفع
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

            // التحقق من الخصم + إجمالي كل صف
            foreach ($request->input('details', []) as $i => $row) {
                $qty      = (float) ($row['quantity'] ?? 0);
                $price    = (float) ($row['price'] ?? 0);
                $discount = (float) ($row['discount'] ?? 0);

                // منع خصم يتجاوز قيمة الصف
                if ($discount > $qty * $price) {
                    $v->errors()->add(
                        "details.{$i}.discount",
                        'الخصم لا يمكن أن يتجاوز قيمة الصف'
                    );
                }

                // منع صف بإجمالي صفر
                if (max(0, $qty * $price - $discount) <= 0) {
                    $v->errors()->add(
                        "details.{$i}.price",
                        'إجمالي الصف يجب أن يكون أكبر من صفر'
                    );
                }
            }
        });

        return $validator;
    }

    /**
     * تجهيز بيانات الرأس
     */
    private function headerData(Request $request): array
    {
        return [
            'invoice_number'      => $request->input('invoice_number'),
            'invoice_date'        => $request->input('invoice_date'),
            'account_id'          => $request->input('account_id'),
            'payment_account_id'  => $request->input('payment_account_id'),
            'coin_id'             => $request->input('coin_id'),
            'warehouse_id'        => $request->input('warehouse_id'),
            'exchange_rate'       => $request->input('exchange_rate', 1),
            'payment_method'      => $request->input('payment_method'),
            'expenses'            => $request->input('expenses', 0),
            'tax_cost'            => $request->input('tax_cost', 0),
            'transportation'      => $request->input('transportation', 0),
            'other_cost'          => $request->input('other_cost', 0),
            'other_cost_description' => $request->input('other_cost_description'),
            'statement'           => $request->input('statement'),
            'reference'           => $request->input('reference'),
        ];
    }

    /**
     * حفظ التفاصيل
     */
    private function saveDetails(PurchaseInvoice $invoice, array $details): void
    {
        foreach ($details as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $price    = (float) ($row['price'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $total    = max(0, ($quantity * $price) - $discount);

            PurchaseInvoiceDetail::create([
                'purchase_invoice_id' => $invoice->purchase_invoice_id,
                'item_id'             => $row['item_id'],
                'type_id'             => $row['type_id'] ?? null,
                'unit_id'             => $row['unit_id'] ?? null,
                'code'                => $row['code'] ?? null,
                'quantity'            => $quantity,
                'price'               => $price,
                'discount'            => $discount,
                'total'               => $total,
            ]);
        }
    }

    /**
     * إعادة حساب الإجماليات من التفاصيل
     */
    private function recalculateTotals(PurchaseInvoice $invoice): void
    {
        $details = $invoice->details()->get();

        $itemsTotal    = 0;
        $discountTotal = 0;

        foreach ($details as $d) {
            $itemsTotal    += (float) $d->quantity * (float) $d->price;
            $discountTotal += (float) $d->discount;
        }

        $invoice->items_total    = $itemsTotal;
        $invoice->discount_total = $discountTotal;
        $invoice->save();
    }
}