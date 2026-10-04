<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    public function create(array $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($data) {
            $details = $data['details'] ?? [];

            $this->lockStockRows($details, (int) ($data['warehouse_id'] ?? 0));

            $nextNumber = $this->nextInvoiceNumber();
            $header = $this->headerData($data);
            $header['invoice_number'] = (string) $nextNumber;

            $invoice = PurchaseInvoice::create($header);
            $this->saveDetails($invoice, $details);
            $this->recalculateTotals($invoice);

            $invoice->load('details');
            $this->syncInventoryMovement($invoice);

            return $invoice;
        });
    }

    public function update(int $id, array $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($id, $data) {
            $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($id);

            $details = $data['details'] ?? [];

            $this->lockStockRows($details, (int) ($data['warehouse_id'] ?? 0));

            $header = $this->headerData($data);
            unset($header['invoice_number']);
            $invoice->update($header);

            $invoice->details()->delete();
            $this->saveDetails($invoice, $details);
            $this->recalculateTotals($invoice);

            $invoice->load('details');
            $this->syncInventoryMovement($invoice);

            return $invoice;
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($id);
            $this->deleteInventoryMovement($invoice);
            $invoice->details()->delete();
            $invoice->delete();
        });
    }

    protected function lockStockRows(array $details, int $defaultWarehouseId): void
    {
        $pairs = [];

        foreach ($details as $row) {
            $itemId      = (int) ($row['item_id'] ?? 0);
            $warehouseId = (int) ($row['warehouse_id'] ?? $defaultWarehouseId);
            $unitId      = isset($row['unit_id']) && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : null;

            if (!$itemId || !$warehouseId) {
                continue;
            }

            $key = $itemId . '|' . $warehouseId . '|' . ($unitId ?? 0);
            $pairs[$key] = [
                'item_id'      => $itemId,
                'warehouse_id' => $warehouseId,
                'unit_id'      => $unitId,
            ];
        }

        ksort($pairs);

        foreach ($pairs as $pair) {
            $query = DB::table('inventory_movement_details')
                ->where('item_id', $pair['item_id'])
                ->where('warehouse_id', $pair['warehouse_id']);

            if ($pair['unit_id']) {
                $query->where('unit_id', $pair['unit_id']);
            }

            $query->select('movement_detail_id')
                  ->lockForUpdate()
                  ->get();
        }
    }

    public function syncInventoryMovement(PurchaseInvoice $invoice): void
    {
        $this->deleteInventoryMovement($invoice);

        $lastMovement = InventoryMovement::lockForUpdate()
            ->orderBy('movement_id', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? ((int) $lastMovement->display_id + 1)
            : 1;

        $movement = InventoryMovement::create([
            'display_id'      => (string) $nextNumber,
            'movement_type'   => InventoryMovement::TYPE_PURCHASE,
            'direction'       => InventoryMovement::directionForType(
                                    InventoryMovement::TYPE_PURCHASE
                                 ),
            'movement_date'   => $invoice->invoice_date,
            'document_number' => $invoice->invoice_number,
            'warehouse_id'    => $invoice->warehouse_id,
            'statement'       => 'توريد تلقائي من فاتورة الشراء رقم '
                                    . $invoice->invoice_number,
            'source_type'     => InventoryMovement::SOURCE_PURCHASE_INVOICE,
            'source_id'       => $invoice->purchase_invoice_id,
            'total'           => 0,
        ]);

        $rate = (float) ($invoice->exchange_rate ?: 1);

        $extraCostsFC = (float) $invoice->expenses
                      + (float) $invoice->tax_cost
                      + (float) $invoice->transportation
                      + (float) $invoice->other_cost;

        $detailsCollection = $invoice->details;
        $lineCount = $detailsCollection->count();

        $movementTotalBC = 0;
        $allocatedCostsFC = 0;
        $index = 0;

        foreach ($detailsCollection as $detail) {
            $quantity   = (float) $detail->quantity;
            $priceFC    = (float) $detail->price;
            $discountFC = (float) $detail->discount;

            $lineNetFC = max(0, ($quantity * $priceFC) - $discountFC);

            // توزيع التكاليف الإضافية بالتساوي على عدد الأسطر
            // مع دمج فرق التقريب المتبقي في السطر الأخير
            $shareFC = 0;
            if ($lineCount > 0 && $extraCostsFC > 0) {
                if ($index === $lineCount - 1) {
                    $shareFC = $extraCostsFC - $allocatedCostsFC;
                } else {
                    $shareFC = $extraCostsFC / $lineCount;
                    $allocatedCostsFC += $shareFC;
                }
            }

            $landedFC   = $lineNetFC + $shareFC;
            $unitCostFC = $quantity > 0 ? ($landedFC / $quantity) : 0;

            $unitCostBC  = $unitCostFC * $rate;
            $lineTotalBC = $landedFC   * $rate;

            $minPrice  = round($unitCostBC, 6);
            $maxPrice  = round($unitCostBC * 2, 6);
            $salePrice = round($unitCostBC * 1.5, 6);

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $invoice->warehouse_id,
                'quantity'     => $quantity,
                'unit_cost'    => $unitCostBC,
                'min_price'    => $minPrice,
                'max_price'    => $maxPrice,
                'sale_price'   => $salePrice,
                'total'        => $lineTotalBC,
            ]);

            $movementTotalBC += $lineTotalBC;
            $index++;
        }

        $movement->total = $movementTotalBC;
        $movement->save();
    }

    public function deleteInventoryMovement(PurchaseInvoice $invoice): void
    {
        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_PURCHASE_INVOICE
            )
            ->where('source_id', $invoice->purchase_invoice_id)
            ->get();

        foreach ($movements as $movement) {
            $movement->details()->delete();
            $movement->delete();
        }
    }

    private function nextInvoiceNumber(): int
    {
        $last = PurchaseInvoice::lockForUpdate()
            ->orderBy('purchase_invoice_id', 'desc')
            ->first();

        return $last ? ((int) $last->invoice_number + 1) : 1;
    }

    private function headerData(array $data): array
    {
        $otherCost = (float) ($data['other_cost'] ?? 0);

        return [
            'invoice_number'         => $data['invoice_number'] ?? null,
            'invoice_date'           => $data['invoice_date'] ?? null,
            'account_id'             => $data['account_id'] ?? null,
            'payment_account_id'     => $data['payment_account_id'] ?? null,
            'coin_id'                => $data['coin_id'] ?? null,
            'warehouse_id'           => $data['warehouse_id'] ?? null,
            'exchange_rate'          => $data['exchange_rate'] ?? 1,
            'payment_method'         => $data['payment_method'] ?? null,
            'expenses'               => $data['expenses'] ?? 0,
            'tax_cost'               => $data['tax_cost'] ?? 0,
            'transportation'         => $data['transportation'] ?? 0,
            'other_cost'             => $otherCost,
            'other_cost_description' => $otherCost > 0
                ? ($data['other_cost_description'] ?? null)
                : null,
            'statement'              => $data['statement'] ?? null,
            'reference'              => $data['reference'] ?? null,
        ];
    }

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

    private function recalculateTotals(PurchaseInvoice $invoice): void
    {
        $details = $invoice->details()->get();

        $itemsTotal    = 0;
        $discountTotal = 0;

        foreach ($details as $d) {
            $itemsTotal    += (float) $d->quantity * (float) $d->price;
            $discountTotal += (float) $d->discount;
        }

        $net = max(0, $itemsTotal - $discountTotal);

        $invoice->items_total    = $itemsTotal;
        $invoice->discount_total = $discountTotal;

        $rate = (float) ($invoice->exchange_rate ?: 1);

        $invoice->total_in_base_currency = $net * $rate;
        $invoice->save();
    }
}