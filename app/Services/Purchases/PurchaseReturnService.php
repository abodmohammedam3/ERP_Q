<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\PurchaseReturnDetail;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
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
     * حساب الكمية المتبقية القابلة للإرجاع لسطر فاتورة شراء محدد
     */
    public function availableForReturn(int $invoiceDetailId, ?int $exceptReturnId = null): float
    {
        $invoiceDetail = PurchaseInvoiceDetail::find($invoiceDetailId);

        if (!$invoiceDetail) {
            return 0.0;
        }

        $alreadyReturned = PurchaseReturnDetail::where('purchase_invoice_detail_id', $invoiceDetailId)
            ->when($exceptReturnId, function ($query, $exceptId) {
                $query->where('purchase_return_id', '!=', $exceptId);
            })
            ->sum('quantity');

        return max(0.0, (float) $invoiceDetail->quantity - (float) $alreadyReturned);
    }

    // =====================================================
    // CRUD
    // =====================================================

    public function create(Request $request): PurchaseReturn
    {
        return DB::transaction(function () use ($request) {

            // ✅ التحقق الأمني: تطابق حساب المورد مع الفاتورة الأصلية
            $this->validateAccountMatchesInvoice(
                (int) $request->input('original_purchase_invoice_id'),
                (int) $request->input('account_id')
            );

            $details = $request->input('details', []);
            $this->validateReturnQuantities($details);

            $nextNumber = $this->nextReturnNumber();

            $data = $this->headerData($request);
            $data['return_number'] = (string) $nextNumber;

            $purchaseReturn = PurchaseReturn::create($data);

            $this->saveDetails($purchaseReturn, $details);
            $this->recalculateTotals($purchaseReturn);

            $purchaseReturn->load('details');
            $this->syncInventoryMovement($purchaseReturn);

            return $purchaseReturn;
        });
    }

    public function update(int $id, Request $request): PurchaseReturn
    {
        return DB::transaction(function () use ($id, $request) {

            $purchaseReturn = PurchaseReturn::findOrFail($id);

            // ⚠️ ملاحظة: لم نعد نستدعي deleteInventoryMovement هنا
            // لأن syncInventoryMovement يستدعيها داخلياً.

            // ✅ التحقق الأمني
            $this->validateAccountMatchesInvoice(
                (int) $request->input('original_purchase_invoice_id'),
                (int) $request->input('account_id')
            );

            $details = $request->input('details', []);
            $this->validateReturnQuantities($details, $purchaseReturn->purchase_return_id);

            $data = $this->headerData($request);
            unset($data['return_number']);
            $purchaseReturn->update($data);

            $purchaseReturn->details()->delete();
            $this->saveDetails($purchaseReturn, $details);
            $this->recalculateTotals($purchaseReturn);

            $purchaseReturn->load('details');
            $this->syncInventoryMovement($purchaseReturn);

            return $purchaseReturn;
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {

            $purchaseReturn = PurchaseReturn::findOrFail($id);

            $this->deleteInventoryMovement($purchaseReturn);

            $purchaseReturn->details()->delete();
            $purchaseReturn->delete();
        });
    }

    // =====================================================
    // التحقق الأمني
    // =====================================================

    /**
     * التحقق من تطابق حساب المورد مع الفاتورة الأصلية
     * يمنع تحميل مرتجع على حساب مورد مختلف عن مورد الفاتورة
     */
    protected function validateAccountMatchesInvoice(int $invoiceId, int $accountId): void
    {
        $invoice = PurchaseInvoice::findOrFail($invoiceId);

        if ($accountId !== (int) $invoice->account_id) {
            throw ValidationException::withMessages([
                'account_id' => 'حساب المورد لا يطابق الفاتورة الأصلية',
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
            $invoiceDetailId = $row['purchase_invoice_detail_id'] ?? null;
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

    public function syncInventoryMovement(PurchaseReturn $purchaseReturn): void
    {
        $this->deleteInventoryMovement($purchaseReturn);

        $lastMovement = InventoryMovement::lockForUpdate()
            ->orderBy('movement_id', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? ((int) $lastMovement->display_id + 1)
            : 1;

        $firstDetail     = $purchaseReturn->details->first();
        $headerWarehouse = $purchaseReturn->warehouse_id
            ?: ($firstDetail ? $firstDetail->warehouse_id : null);

        if (!$headerWarehouse) {
            throw new \RuntimeException('لا يمكن إنشاء حركة مخزون بدون مخزن');
        }

        $movement = InventoryMovement::create([
            'display_id'      => (string) $nextNumber,
            'movement_type'   => InventoryMovement::TYPE_PURCHASE_RETURN,
            'direction'       => InventoryMovement::directionForType(
                                    InventoryMovement::TYPE_PURCHASE_RETURN
                                 ),
            'movement_date'   => $purchaseReturn->return_date,
            'document_number' => $purchaseReturn->return_number,
            'warehouse_id'    => $headerWarehouse,
            'statement'       => 'صرف تلقائي من مرتجع شراء رقم '
                                    . $purchaseReturn->return_number,
            'source_type'     => InventoryMovement::SOURCE_PURCHASE_RETURN,
            'source_id'       => $purchaseReturn->purchase_return_id,
            'total'           => 0,
        ]);

        $movementTotal = 0;

        foreach ($purchaseReturn->details as $detail) {
            $quantity  = (float) $detail->quantity;
            $unitCost  = (float) $detail->unit_cost;
            $lineTotal = $quantity * $unitCost;

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $detail->warehouse_id ?: $headerWarehouse,
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

    public function deleteInventoryMovement(PurchaseReturn $purchaseReturn): void
    {
        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_PURCHASE_RETURN
            )
            ->where('source_id', $purchaseReturn->purchase_return_id)
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
        $last = PurchaseReturn::lockForUpdate()
            ->orderBy('purchase_return_id', 'desc')
            ->first();

        return $last ? ((int) $last->return_number + 1) : 1;
    }

    protected function headerData(Request $request): array
    {
        return [
            'return_number'                => $request->input('return_number'),
            'return_date'                  => $request->input('return_date'),
            'original_purchase_invoice_id' => $request->input('original_purchase_invoice_id'),
            'account_id'                   => $request->input('account_id'),
            'payment_account_id'           => $request->input('payment_account_id'),
            'coin_id'                      => $request->input('coin_id'),
            'warehouse_id'                 => $request->input('warehouse_id'),
            'exchange_rate'                => $request->input('exchange_rate', 1),
            'payment_method'               => $request->input('payment_method'),
            'statement'                    => $request->input('statement'),
            'reference'                    => $request->input('reference'),
        ];
    }

    protected function saveDetails(PurchaseReturn $purchaseReturn, array $details): void
    {
        foreach ($details as $row) {
            $invoiceDetailId = (int) $row['purchase_invoice_detail_id'];
            $invoiceDetail   = PurchaseInvoiceDetail::findOrFail($invoiceDetailId);

            $quantity = (float) ($row['quantity'] ?? 0);
            $price    = (float) ($row['price'] ?? $invoiceDetail->price);
            $discount = (float) ($row['discount'] ?? 0);
            $total    = max(0, ($quantity * $price) - $discount);

            // ✅ التكلفة الفعلية (Landed Cost) من حركة الشراء الأصلية
            $unitCost = $this->getLandedCostFromMovement($invoiceDetail);

            PurchaseReturnDetail::create([
                'purchase_return_id'         => $purchaseReturn->purchase_return_id,
                'purchase_invoice_detail_id' => $invoiceDetailId,
                'item_id'                    => $invoiceDetail->item_id,
                'type_id'                    => $invoiceDetail->type_id,
                'unit_id'                    => $invoiceDetail->unit_id,
                'warehouse_id'               => $purchaseReturn->warehouse_id,
                'code'                       => $invoiceDetail->code,
                'quantity'                   => $quantity,
                'price'                      => $price,
                'unit_cost'                  => $unitCost,
                'discount'                   => $discount,
                'total'                      => $total,
            ]);
        }
    }

    /**
     * ✅ جلب التكلفة الفعلية (Landed Cost) لسطر فاتورة شراء
     * من inventory_movement_details المرتبطة بحركة الشراء الأصلية.
     */
    protected function getLandedCostFromMovement(PurchaseInvoiceDetail $invoiceDetail): float
    {
        $unitCost = DB::table('inventory_movement_details')
            ->join(
                'inventory_movements',
                'inventory_movements.movement_id',
                '=',
                'inventory_movement_details.movement_id'
            )
            ->where('inventory_movements.source_type', InventoryMovement::SOURCE_PURCHASE_INVOICE)
            ->where('inventory_movements.source_id', $invoiceDetail->purchase_invoice_id)
            ->where('inventory_movement_details.item_id', $invoiceDetail->item_id)
            ->when($invoiceDetail->type_id, function ($q) use ($invoiceDetail) {
                $q->where('inventory_movement_details.type_id', $invoiceDetail->type_id);
            })
            ->when($invoiceDetail->unit_id, function ($q) use ($invoiceDetail) {
                $q->where('inventory_movement_details.unit_id', $invoiceDetail->unit_id);
            })
            ->orderBy('inventory_movement_details.movement_detail_id', 'desc')
            ->value('inventory_movement_details.unit_cost');

        // fallback: إذا لم نجد الحركة (فاتورة قديمة)، نستخدم سعر الفاتورة
        return (float) ($unitCost !== null && $unitCost > 0
            ? $unitCost
            : $invoiceDetail->price);
    }

    protected function recalculateTotals(PurchaseReturn $purchaseReturn): void
    {
        $details = $purchaseReturn->details()->get();

        $itemsTotal    = 0;
        $discountTotal = 0;

        foreach ($details as $d) {
            $itemsTotal    += (float) $d->quantity * (float) $d->price;
            $discountTotal += (float) $d->discount;
        }

        $purchaseReturn->items_total    = $itemsTotal;
        $purchaseReturn->discount_total = $discountTotal;
        $purchaseReturn->save();
    }
}