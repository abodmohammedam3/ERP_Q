<?php

namespace App\Services\Purchases;

use App\Models\Accounting\CharAccount;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Stock;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseInvoiceService
{
    // =========================================================
    // CONSTANTS
    // =========================================================

    private const LOCK_KEY = 'purchase_invoice_write_lock';
    private const LOCK_TTL = 15;

    private const ACCRUED_COSTS_CACHE_KEY = 'accounting.accrued_purchase_costs_id';
    private const ACCRUED_COSTS_CACHE_TTL = 3600;
    private const ACCRUED_COSTS_SYSTEM_KEY = 'AccruedPurchaseCosts';

    private const INVENTORY_ACCOUNT_CACHE_TTL = 3600;

    // =========================================================
    // CREATE
    // =========================================================
    public function create(array $data): PurchaseInvoice
    {
        return $this->withWriteLock(function () use ($data) {
            return DB::transaction(function () use ($data) {
                $details = $data['details'] ?? [];

                $this->lockStockRows($details, (int) ($data['warehouse_id'] ?? 0));

                $nextNumber = $this->nextInvoiceNumber();
                $header = $this->headerData($data);
                $header['invoice_number'] = (string) $nextNumber;

                $invoice = PurchaseInvoice::create($header);
                $this->saveDetails($invoice, $details);
                $this->recalculateTotals($invoice);

                $invoice->load(['details', 'supplierAccount']);
                $this->syncInventoryMovement($invoice);
                $this->syncAccountingEntry($invoice);

                return $invoice;
            });
        });
    }

    // =========================================================
    // UPDATE
    // =========================================================
    public function update(int $id, array $data): PurchaseInvoice
    {
        return $this->withWriteLock(function () use ($id, $data) {
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

                $invoice->load(['details', 'supplierAccount']);
                $this->syncInventoryMovement($invoice);
                $this->syncAccountingEntry($invoice);

                return $invoice;
            });
        });
    }

    // =========================================================
    // DELETE
    // =========================================================
    public function delete(int $id): void
    {
        $this->withWriteLock(function () use ($id) {
            DB::transaction(function () use ($id) {
                $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($id);

                $this->deleteAccountingEntry($invoice);
                $this->deleteInventoryMovement($invoice);
                $invoice->details()->delete();
                $invoice->delete();
            });
        });
    }

    // =========================================================
    // WRITE LOCK
    // =========================================================
    private function withWriteLock(callable $callback)
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);

        if (!$lock->get()) {
            throw new \RuntimeException(
                'عملية أخرى على فواتير الشراء قيد التنفيذ، حاول بعد قليل'
            );
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Purchase invoice operation failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    // =========================================================
    // LOCK STOCK ROWS
    // =========================================================
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

    // =========================================================
    // INVENTORY MOVEMENT
    // =========================================================
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

        $movementTotalBC  = 0;
        $allocatedCostsFC = 0;
        $index = 0;

        foreach ($detailsCollection as $detail) {
            $quantity   = (float) $detail->quantity;
            $priceFC    = (float) $detail->price;
            $discountFC = (float) $detail->discount;

            $lineNetFC = max(0, ($quantity * $priceFC) - $discountFC);

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

    // =========================================================
    // ACCOUNTING ENTRY
    // =========================================================
    protected function syncAccountingEntry(PurchaseInvoice $invoice): void
    {
        $this->deleteAccountingEntry($invoice);

        // 1) الحساب المالي
        $rate       = (float) ($invoice->exchange_rate ?: 1);
        $itemsNet   = (float) $invoice->items_total - (float) $invoice->discount_total;
        $extraCosts = (float) $invoice->expenses
                    + (float) $invoice->tax_cost
                    + (float) $invoice->transportation
                    + (float) $invoice->other_cost;

        $totalFC = round($itemsNet + $extraCosts, 6);
        $totalBC = round($totalFC * $rate, 6);

        if ($totalBC <= 0) {
            throw new \RuntimeException(
                'إجمالي فاتورة الشراء يجب أن يكون أكبر من صفر لإنشاء القيد'
            );
        }

        // 2) حساب المخزون (مدين) — من جدول المخازن
        $warehouseId        = (int) $invoice->warehouse_id;
        $inventoryAccountId = $this->getInventoryAccountIdFromWarehouse($warehouseId);

        if ($inventoryAccountId <= 0) {
            throw new \RuntimeException(
                'حساب المخزون غير مُعرّف للمخزن رقم ' . $warehouseId
            );
        }

        // 3) حساب الدائن (المورد أو الدفع)
        $paymentMethod   = (int) $invoice->payment_method;
        $creditAccountId = $paymentMethod === PurchaseInvoice::PAYMENT_CREDIT
            ? (int) $invoice->account_id
            : (int) $invoice->payment_account_id;

        if ($creditAccountId <= 0) {
            throw new \RuntimeException(
                'حساب الدفع غير محدد للفاتورة رقم ' . $invoice->invoice_number
            );
        }

        // 4) حساب المصاريف المستحقة (عبر System Key مع Cache)
        $accruedCostsAccountId = 0;
        if ($extraCosts > 0) {
            $accruedCostsAccountId = $this->getAccruedCostsAccountId();

            if ($accruedCostsAccountId <= 0) {
                throw new \RuntimeException(
                    'حساب المصاريف المستحقة (' . self::ACCRUED_COSTS_SYSTEM_KEY
                    . ') غير موجود في دليل الحسابات'
                );
            }
        }

        // 5) فصل المبالغ لضمان التوازن
        $extraFC = round($extraCosts, 6);
        $extraBC = round($extraCosts * $rate, 6);
        $itemsFC = round($totalFC - $extraFC, 6);
        $itemsBC = round($totalBC - $extraBC, 6);

        // 6) التاريخ والوصف
        $entryDate = $invoice->invoice_date instanceof \DateTimeInterface
            ? $invoice->invoice_date->format('Y-m-d')
            : (string) ($invoice->invoice_date ?: now()->toDateString());

        // نوع المستند بالعربي
        $docType = $invoice->journal_doc_type_label;

        // البيان مع اسم المورد
        $supplierName = $invoice->supplierAccount->accName ?? '';

        $description = $invoice->journal_doc_type_label
            . ' رقم ' . $invoice->invoice_number
            . ($supplierName !== '' ? ' - ' . $supplierName : '');

        // 7) بناء السطور
        $lines = [
            [
                'accountID'    => $inventoryAccountId,
                'coinsID'      => $invoice->coin_id,
                'exchangRate'  => $rate,
                'debit'        => $totalFC,
                'credit'       => 0,
                'localDebit'   => $totalBC,
                'localCredit'  => 0,
                'description2' => $description,
            ],
            [
                'accountID'    => $creditAccountId,
                'coinsID'      => $invoice->coin_id,
                'exchangRate'  => $rate,
                'debit'        => 0,
                'credit'       => $itemsFC,
                'localDebit'   => 0,
                'localCredit'  => $itemsBC,
                'description2' => $description . ' - قيمة البضاعة',
            ],
        ];

        if ($extraCosts > 0) {
            $lines[] = [
                'accountID'    => $accruedCostsAccountId,
                'coinsID'      => $invoice->coin_id,
                'exchangRate'  => $rate,
                'debit'        => 0,
                'credit'       => $extraFC,
                'localDebit'   => 0,
                'localCredit'  => $extraBC,
                'description2' => $description . ' - مصاريف مستحقة',
            ];
        }

        // 8) إنشاء القيد
        $entryId = JournalEntryService::create([
            'entryDate'   => $entryDate,
            'docType'     => $docType,
            'docNumber'   => (string) $invoice->invoice_number,
            'description' => $description,
            'lines'       => $lines,
        ]);

        if (!$entryId) {
            throw new \RuntimeException(
                'فشل إنشاء القيد المحاسبي لفاتورة الشراء رقم ' . $invoice->invoice_number
            );
        }

        // 9) ربط الفاتورة بالقيد
        $invoice->entryID = $entryId;
        $invoice->save();
    }

    protected function deleteAccountingEntry(PurchaseInvoice $invoice): void
    {
        if (!empty($invoice->entryID)) {
            JournalEntryService::delete((int) $invoice->entryID);

            $invoice->entryID = null;
            $invoice->saveQuietly();

            return;
        }

        if (!empty($invoice->invoice_number)) {
            JournalEntryService::deleteByDocNumber(
                (string) $invoice->invoice_number
            );
        }
    }

    // =========================================================
    // ACCOUNT RESOLVERS (Cache)
    // =========================================================
    private function getAccruedCostsAccountId(): int
    {
        return (int) Cache::remember(
            self::ACCRUED_COSTS_CACHE_KEY,
            self::ACCRUED_COSTS_CACHE_TTL,
            function () {
                return (int) CharAccount::query()
                    ->where('system_key', self::ACCRUED_COSTS_SYSTEM_KEY)
                    ->value('accountID');
            }
        );
    }

    private function getInventoryAccountIdFromWarehouse(int $warehouseId): int
    {
        if ($warehouseId <= 0) {
            return 0;
        }

        return (int) Cache::remember(
            "warehouse.inventory_account.{$warehouseId}",
            self::INVENTORY_ACCOUNT_CACHE_TTL,
            function () use ($warehouseId) {
                return (int) Stock::query()
                    ->where('StockID', $warehouseId)
                    ->value('accountID');
            }
        );
    }

    // =========================================================
    // HELPERS
    // =========================================================
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