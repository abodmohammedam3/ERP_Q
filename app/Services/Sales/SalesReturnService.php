<?php

namespace App\Services\Sales;

use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnDetail;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // =====================================================
    // الكمية المتاحة للإرجاع
    // =====================================================

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

    public function create(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data) {

            $originalInvoiceId = (int) ($data['original_sales_invoice_id'] ?? 0);

            // ✅ 1) قفل الفاتورة الأصلية + التحقق
            $this->lockAndValidateInvoice(
                $originalInvoiceId,
                $data['account_id'] ?? null,
                $data['coin_id'] ?? null
            );

            $details = $data['details'] ?? [];

            // ✅ 2) فحص التفاصيل
            $this->validateReturnQuantities($originalInvoiceId, $details);

            $nextNumber = $this->nextReturnNumber();

            $data = $this->headerData($data);
            $data['return_number'] = (string) $nextNumber;

            $salesReturn = SalesReturn::create($data);

            $this->saveDetails($salesReturn, $details);
            $this->recalculateTotals($salesReturn);

            $salesReturn->load('details');
            $this->syncInventoryMovement($salesReturn);

            return $salesReturn;
        });
    }

    public function update(int $id, array $data): SalesReturn
    {
        return DB::transaction(function () use ($id, $data) {

            $originalInvoiceId = (int) ($data['original_sales_invoice_id'] ?? 0);

            $this->lockAndValidateInvoice(
                $originalInvoiceId,
                $data['account_id'] ?? null,
                $data['coin_id'] ?? null
            );

            $salesReturn = SalesReturn::lockForUpdate()->findOrFail($id);

            $details = $data['details'] ?? [];

            $this->validateReturnQuantities($originalInvoiceId, $details, $salesReturn->sales_return_id);

            $data = $this->headerData($data);
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
            $salesReturn = SalesReturn::lockForUpdate()->findOrFail($id);

            $this->deleteInventoryMovement($salesReturn);

            $salesReturn->details()->delete();
            $salesReturn->delete();
        });
    }

    // =====================================================
    // القفل + التحقق
    // =====================================================

    protected function lockAndValidateInvoice(int $invoiceId, $accountId, $coinId): void
    {
        $invoice = SalesInvoice::lockForUpdate()->findOrFail($invoiceId);

        if ((int) $accountId !== (int) $invoice->account_id) {
            throw ValidationException::withMessages([
                'account_id' => 'حساب العميل لا يطابق الفاتورة الأصلية',
            ]);
        }

        if ((int) $coinId !== (int) $invoice->coin_id) {
            throw ValidationException::withMessages([
                'coin_id' => 'العملة لا تطابق الفاتورة الأصلية',
            ]);
        }
    }

    // =====================================================
    // التحقق من التفاصيل
    // =====================================================

    protected function validateReturnQuantities(
        int $originalInvoiceId,
        array $details,
        ?int $exceptReturnId = null
    ): void {
        $errors = [];

        // 1) Cross-invoice check
        foreach ($details as $i => $row) {
            $detailId = (int) ($row['sales_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) {
                continue;
            }

            $detail = SalesInvoiceDetail::find($detailId);

            if (!$detail) {
                $errors["details.{$i}.sales_invoice_detail_id"] = "سطر الفاتورة #{$detailId} غير موجود";
                continue;
            }

            if ((int) $detail->sales_invoice_id !== $originalInvoiceId) {
                $errors["details.{$i}.sales_invoice_detail_id"] =
                    "سطر الفاتورة #{$detailId} لا ينتمي للفاتورة الأصلية";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // 2) Duplicate check — تجميع الكميات
        $grouped = [];
        foreach ($details as $row) {
            $detailId = (int) ($row['sales_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) {
                continue;
            }

            $grouped[$detailId] = ($grouped[$detailId] ?? 0) + $qty;
        }

        // 3) Quantity check
        foreach ($grouped as $detailId => $totalQty) {
            $available = $this->availableForReturn($detailId, $exceptReturnId);

            if ($totalQty > $available) {
                $errors["details"] =
                    "الكمية الإجمالية للسطر #{$detailId} ({$totalQty}) أكبر من المتاح ({$available})";
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
                'sale_price'   => null,
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
    // دوال مساعدة
    // =====================================================

    protected function nextReturnNumber(): int
    {
        $last = SalesReturn::lockForUpdate()
            ->orderBy('sales_return_id', 'desc')
            ->first();

        return $last ? ((int) $last->return_number + 1) : 1;
    }

    protected function headerData(array $data): array
    {
        return [
            'return_number'             => $data['return_number'] ?? null,
            'return_date'               => $data['return_date'] ?? null,
            'original_sales_invoice_id' => $data['original_sales_invoice_id'] ?? null,
            'account_id'                => $data['account_id'] ?? null,
            'payment_account_id'        => $data['payment_account_id'] ?? null,
            'coin_id'                   => $data['coin_id'] ?? null,
            'exchange_rate'             => $data['exchange_rate'] ?? 1,
            'payment_method'            => $data['payment_method'] ?? null,
            'statement'                 => $data['statement'] ?? null,
            'reference'                 => $data['reference'] ?? null,
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