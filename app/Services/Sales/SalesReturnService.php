<?php

namespace App\Services\Sales;

use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnDetail;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    /**
     * @var InventoryService
     */
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // =====================================================
    // الكمية المتاحة للإرجاع
    // =====================================================

    /**
     * حساب الكمية المتبقية القابلة للإرجاع لسطر فاتورة محدد
     */
    public function availableForReturn(int $invoiceDetailId, ?int $exceptReturnId = null): float
    {
        $invoiceDetail = SalesInvoiceDetail::find($invoiceDetailId);

        if (!$invoiceDetail) {
            return 0.0;
        }

        $alreadyReturned = SalesReturnDetail::where('sales_invoice_detail_id', $invoiceDetailId)
            ->when($exceptReturnId, function ($query, $exceptId) {
                $query->where('sales_return_id', '!=', $exceptId);
            })
            ->sum('quantity');

        return max(0.0, (float) $invoiceDetail->quantity - (float) $alreadyReturned);
    }

    // =====================================================
    // CRUD
    // =====================================================

    public function create(Request $request): SalesReturn
    {
        return DB::transaction(function () use ($request) {

            // ✅ التحقق الأمني: تطابق حساب العميل مع الفاتورة الأصلية
            $this->validateAccountMatchesInvoice(
                (int) $request->input('original_sales_invoice_id'),
                (int) $request->input('account_id')
            );

            $details = $request->input('details', []);
            $this->validateReturnQuantities($details);

            $nextNumber = $this->nextReturnNumber();

            $data = $this->headerData($request);
            $data['return_number'] = (string) $nextNumber;

            $salesReturn = SalesReturn::create($data);

            $this->saveDetails($salesReturn, $details);
            $this->recalculateTotals($salesReturn);

            $salesReturn->load('details');
            $this->syncInventoryMovement($salesReturn);

            return $salesReturn;
        });
    }

    public function update(int $id, Request $request): SalesReturn
    {
        return DB::transaction(function () use ($id, $request) {

            $salesReturn = SalesReturn::findOrFail($id);

            // ⚠️ ملاحظة: لم نعد نستدعي deleteInventoryMovement هنا
            // لأن syncInventoryMovement يستدعيها داخلياً.

            // ✅ التحقق الأمني
            $this->validateAccountMatchesInvoice(
                (int) $request->input('original_sales_invoice_id'),
                (int) $request->input('account_id')
            );

            $details = $request->input('details', []);
            $this->validateReturnQuantities($details, $salesReturn->sales_return_id);

            $data = $this->headerData($request);
            unset($data['return_number']);
            $salesReturn->update($data);

            $salesReturn->details()->delete();
            $this->saveDetails($salesReturn, $details);
            $this->recalculateTotals($salesReturn);

            $salesReturn->load('details');
            $this->syncInventoryMovement($salesReturn);

            return $salesReturn;
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {

            $salesReturn = SalesReturn::findOrFail($id);

            $this->deleteInventoryMovement($salesReturn);

            $salesReturn->details()->delete();
            $salesReturn->delete();
        });
    }

    // =====================================================
    // التحقق الأمني
    // =====================================================

    /**
     * التحقق من تطابق حساب العميل مع الفاتورة الأصلية
     * يمنع تحميل مرتجع على حساب عميل مختلف عن عميل الفاتورة
     */
    protected function validateAccountMatchesInvoice(int $invoiceId, int $accountId): void
    {
        $invoice = SalesInvoice::findOrFail($invoiceId);

        if ($accountId !== (int) $invoice->account_id) {
            throw ValidationException::withMessages([
                'account_id' => 'حساب العميل لا يطابق الفاتورة الأصلية',
            ]);
        }
    }

    // =====================================================
    // التحقق من الكميات
    // =====================================================

    protected function validateReturnQuantities(array $details, ?int $exceptReturnId = null): void
    {
        $errors = [];

        foreach ($details as $i => $row) {
            $invoiceDetailId = $row['sales_invoice_detail_id'] ?? null;
            $qty             = (float) ($row['quantity'] ?? 0);

            if (!$invoiceDetailId || $qty <= 0) {
                continue;
            }

            $available = $this->availableForReturn((int) $invoiceDetailId, $exceptReturnId);

            if ($qty > $available) {
                $errors["details.{$i}.quantity"] =
                    "الكمية المراد إرجاعها ({$qty}) أكبر من الكمية المتبقية القابلة للإرجاع ({$available})";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    // =====================================================
    // المخزون
    // =====================================================

    public function syncInventoryMovement(SalesReturn $salesReturn): void
    {
        $this->deleteInventoryMovement($salesReturn);

        $lastMovement = InventoryMovement::lockForUpdate()
            ->orderBy('movement_id', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? ((int) $lastMovement->display_id + 1)
            : 1;

        $firstDetail     = $salesReturn->details->first();
        $headerWarehouse = $firstDetail ? $firstDetail->warehouse_id : null;

        if (!$headerWarehouse) {
            throw new \RuntimeException('لا يمكن إنشاء حركة مخزون بدون مخزن');
        }

        $movement = InventoryMovement::create([
            'display_id'      => (string) $nextNumber,
            'movement_type'   => InventoryMovement::TYPE_SALE_RETURN,
            'direction'       => InventoryMovement::directionForType(
                                    InventoryMovement::TYPE_SALE_RETURN
                                 ),
            'movement_date'   => $salesReturn->return_date,
            'document_number' => $salesReturn->return_number,
            'warehouse_id'    => $headerWarehouse,
            'statement'       => 'إدخال تلقائي من مرتجع بيع رقم '
                                    . $salesReturn->return_number,
            'source_type'     => InventoryMovement::SOURCE_SALES_RETURN,
            'source_id'       => $salesReturn->sales_return_id,
            'total'           => 0,
        ]);

        $movementTotal = 0;

        foreach ($salesReturn->details as $detail) {
            $quantity  = (float) $detail->quantity;
            $unitCost  = (float) $detail->cost_price;
            $lineTotal = $quantity * $unitCost;

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $detail->warehouse_id,
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost,
                'min_price'    => null,
                'max_price'    => null,
                'sale_price'   => $detail->price,
                'total'        => $lineTotal,
            ]);

            $movementTotal += $lineTotal;
        }

        $movement->total = $movementTotal;
        $movement->save();

        $movement->load('details');
        $this->inventoryService->applyMovement($movement);
    }

    public function deleteInventoryMovement(SalesReturn $salesReturn): void
    {
        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_SALES_RETURN
            )
            ->where('source_id', $salesReturn->sales_return_id)
            ->with('details')
            ->get();

        foreach ($movements as $movement) {
            $this->inventoryService->reverseMovement($movement);

            $movement->details()->delete();
            $movement->delete();
        }
    }

    // =====================================================
    // دوال مساعدة داخلية
    // =====================================================

    protected function nextReturnNumber(): int
    {
        $last = SalesReturn::lockForUpdate()
            ->orderBy('sales_return_id', 'desc')
            ->first();

        return $last ? ((int) $last->return_number + 1) : 1;
    }

    protected function headerData(Request $request): array
    {
        return [
            'return_number'             => $request->input('return_number'),
            'return_date'               => $request->input('return_date'),
            'original_sales_invoice_id' => $request->input('original_sales_invoice_id'),
            'account_id'                => $request->input('account_id'),
            'payment_account_id'        => $request->input('payment_account_id'),
            'coin_id'                   => $request->input('coin_id'),
            'exchange_rate'             => $request->input('exchange_rate', 1),
            'payment_method'            => $request->input('payment_method'),
            'statement'                 => $request->input('statement'),
            'reference'                 => $request->input('reference'),
        ];
    }

    protected function saveDetails(SalesReturn $salesReturn, array $details): void
    {
        foreach ($details as $row) {
            $invoiceDetailId = (int) $row['sales_invoice_detail_id'];
            $invoiceDetail   = SalesInvoiceDetail::findOrFail($invoiceDetailId);

            $quantity = (float) ($row['quantity'] ?? 0);
            $price    = (float) ($row['price'] ?? $invoiceDetail->price);
            $discount = (float) ($row['discount'] ?? 0);
            $total    = max(0, ($quantity * $price) - $discount);

            // نسخ التكلفة الأصلية للسطر
            $costPrice = (float) ($invoiceDetail->cost_price ?? 0);

            SalesReturnDetail::create([
                'sales_return_id'         => $salesReturn->sales_return_id,
                'sales_invoice_detail_id' => $invoiceDetailId,
                'item_id'                 => $invoiceDetail->item_id,
                'type_id'                 => $invoiceDetail->type_id,
                'unit_id'                 => $invoiceDetail->unit_id,
                'warehouse_id'            => $invoiceDetail->warehouse_id,
                'code'                    => $invoiceDetail->code,
                'quantity'                => $quantity,
                'price'                   => $price,
                'cost_price'              => $costPrice,
                'discount'                => $discount,
                'total'                   => $total,
            ]);
        }
    }

    protected function recalculateTotals(SalesReturn $salesReturn): void
    {
        $details = $salesReturn->details()->get();

        $itemsTotal    = 0;
        $discountTotal = 0;

        foreach ($details as $d) {
            $itemsTotal    += (float) $d->quantity * (float) $d->price;
            $discountTotal += (float) $d->discount;
        }

        $salesReturn->items_total    = $itemsTotal;
        $salesReturn->discount_total = $discountTotal;
        $salesReturn->save();
    }
}