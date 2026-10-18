<?php

namespace App\Http\Controllers\Operation\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\StorePurchaseInvoiceRequest;
use App\Http\Requests\Purchases\UpdatePurchaseInvoiceRequest;
use App\Http\Resources\Purchases\PurchaseInvoiceResource;
use App\Models\Accounting\Bank;
use App\Models\Purchases\PurchaseInvoice;
use App\Services\Purchases\PurchaseInvoiceService;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseInvoiceApiController extends Controller
{
    protected PurchaseInvoiceService $service;

    public function __construct(PurchaseInvoiceService $service)
    {
        $this->service = $service;
    }

    /**
     * قائمة الفواتير (JSON)
     */
    public function list(Request $request)
    {
        Permissions::authorize(config('permissions.purchase_invoices.view'));

        $search = trim($request->input('search', ''));

        $query = PurchaseInvoice::query()
            ->with(['supplierAccount', 'coin'])
            ->orderByDesc('purchase_invoice_id');

        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        $invoices = $query->limit(100)->get()->map(function ($inv) {
            return [
                'purchase_invoice_id' => $inv->purchase_invoice_id,
                'invoice_number'      => $inv->invoice_number,
                'invoice_date'        => optional($inv->invoice_date)->format('Y-m-d'),
                'supplier_name'       => $inv->supplierAccount->accName ?? '',
                'coin_name'           => $inv->coin->coinsName ?? '',
                'payment_method'      => $inv->payment_method,
                'total'               => $inv->total_in_invoice_currency,
                'is_posted'           => (bool) $inv->is_posted,
            ];
        });

        return response()->json(['data' => $invoices]);
    }

    private function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('invoice_number', 'like', "{$search}%")
              ->orWhere('reference', 'like', "{$search}%")
              ->orWhereHas('supplierAccount', function ($q2) use ($search) {
                  $q2->where('accName', 'like', "%{$search}%")
                     ->orWhere('accCode', 'like', "{$search}%");
              })
              ->orWhereHas('details', function ($q2) use ($search) {
                  $q2->where('code', 'like', "{$search}%");
              });

            $q->orWhere('statement', 'like', "%{$search}%")
              ->orWhereHas('details.item', function ($q2) use ($search) {
                  $q2->where('itemName2', 'like', "%{$search}%");
              })
              ->orWhereHas('details.type', function ($q2) use ($search) {
                  $q2->where('name', 'like', "%{$search}%");
              });

            foreach (PurchaseInvoice::paymentMethodLabels() as $val => $label) {
                if (mb_strpos($label, $search) !== false
                    || mb_strpos($search, $label) !== false) {
                    $q->orWhere('payment_method', $val);
                }
            }
        });
    }

    /**
     * عرض فاتورة واحدة
     */
    public function show($id)
    {
        Permissions::authorize(config('permissions.purchase_invoices.view'));

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

        return new PurchaseInvoiceResource($invoice);
    }

    /**
     * رقم الفاتورة التالي
     */
    public function nextNumber()
    {
        Permissions::authorize(config('permissions.purchase_invoices.create'));

        $maxNumber = PurchaseInvoice::max('invoice_number');
        $next      = ((int) $maxNumber) + 1;

        return response()->json(['next_number' => (string) $next]);
    }

    /**
     * قائمة البنوك
     */
    public function listBanksFull()
    {
        Permissions::authorize(config('permissions.purchase_invoices.view'));

        $banks = Bank::with(['coin'])
            ->where('is_active', 1)
            ->get()
            ->map(function ($b) {
                return [
                    'bankID'            => $b->bankID,
                    'bankName'          => $b->bankName,
                    'accountID'         => $b->accountID,
                    'accountNumber'     => $b->accountNumber ?? '',
                    'coinsID'           => $b->coinsID,
                    'coinsName'         => $b->coin->coinsName ?? '',
                    'coinsExchangeRate' => $b->coin ? (float) $b->coin->coinsExchangeRate : 0,
                ];
            });

        return response()->json(['data' => $banks]);
    }

    public function store(StorePurchaseInvoiceRequest $request)
    {
        Permissions::authorize(config('permissions.purchase_invoices.create'));

        try {
            $invoice = $this->service->create($request->validated());

            return response()->json([
                'message'             => 'تم حفظ وترحيل الفاتورة بنجاح',
                'purchase_invoice_id' => $invoice->purchase_invoice_id,
                'invoice_number'      => $invoice->invoice_number,
                'is_posted'           => (bool) $invoice->is_posted,
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

    public function update(UpdatePurchaseInvoiceRequest $request, $id)
    {
        Permissions::authorize(config('permissions.purchase_invoices.update'));

        try {
            $this->service->update((int) $id, $request->validated());

            return response()->json([
                'message' => 'تم تحديث الفاتورة بنجاح',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

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
        Permissions::authorize(config('permissions.purchase_invoices.delete'));

        try {
            $this->service->delete((int) $id);

            return response()->json(['message' => 'تم حذف الفاتورة بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase invoice destroy failed', [
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
        Permissions::authorize(config('permissions.purchase_invoices.post'));

        try {
            $this->service->post((int) $id);

            return response()->json(['message' => 'تم ترحيل الفاتورة بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase invoice post failed', [
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
        Permissions::authorize(config('permissions.purchase_invoices.unpost'));

        try {
            $this->service->unpost((int) $id);

            return response()->json(['message' => 'تم إلغاء ترحيل الفاتورة بنجاح']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الفاتورة غير موجودة'], 404);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase invoice unpost failed', [
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