<?php

namespace App\Services\Purchases;

use App\Models\Accounting\CharAccount;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Stock;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\PurchaseReturnDetail;
use App\Services\Inventory\InventoryService;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    // =========================================================
    // CONSTANTS
    // =========================================================

    private const LOCK_KEY = 'purchase_return_write_lock';
    private const LOCK_TTL = 15;

    private const INVENTORY_ACCOUNT_CACHE_TTL = 3600;

    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // =========================================================
    // CREATE — حفظ + ترحيل تلقائي
    // =========================================================
    public function create(array $data): PurchaseReturn
    {
        return $this->withWriteLock(function () use ($data) {
            return DB::transaction(function () use ($data) {

                $originalInvoiceId = (int) ($data['original_purchase_invoice_id'] ?? 0);

                $this->lockAndValidateInvoice(
                    $originalInvoiceId,
                    $data['account_id'] ?? null,
                    $data['coin_id'] ?? null,
                    $data['warehouse_id'] ?? null
                );

                $details = $data['details'] ?? [];

                $this->validateReturnQuantities($originalInvoiceId, $details);

                $this->lockStockRows($details, $data['warehouse_id'] ?? null);

                $this->validateStockAvailability($details, $data['warehouse_id'] ?? null);

                $nextNumber = $this->nextReturnNumber();

                $header = $this->headerData($data);
                $header['return_number'] = (string) $nextNumber;
                $header['created_by']    = auth()->id();

                $purchaseReturn = PurchaseReturn::create($header);

                $this->saveDetails($purchaseReturn, $details);
                $this->recalculateTotals($purchaseReturn);

                // ✅ ترحيل تلقائي
                $this->postInternal($purchaseReturn);

                return $purchaseReturn->fresh(['details', 'supplierAccount']);
            });
        });
    }

    // =========================================================
    // UPDATE — يمنع تعديل المرحّلة
    // =========================================================
    public function update(int $id, array $data): PurchaseReturn
    {
        return $this->withWriteLock(function () use ($id, $data) {
            return DB::transaction(function () use ($id, $data) {

                $purchaseReturn = PurchaseReturn::lockForUpdate()->findOrFail($id);

                if ($purchaseReturn->isPosted()) {
                    throw new \RuntimeException(
                        'لا يمكن تعديل مرتجع مرحّل. قم بإلغاء الترحيل أولًا.'
                    );
                }

                $originalInvoiceId = (int) ($data['original_purchase_invoice_id'] ?? 0);

                $this->lockAndValidateInvoice(
                    $originalInvoiceId,
                    $data['account_id'] ?? null,
                    $data['coin_id'] ?? null,
                    $data['warehouse_id'] ?? null
                );

                $details = $data['details'] ?? [];

                $this->validateReturnQuantities(
                    $originalInvoiceId,
                    $details,
                    $purchaseReturn->purchase_return_id
                );

                $this->lockStockRows($details, $data['warehouse_id'] ?? null);

                $this->validateStockAvailability(
                    $details,
                    $data['warehouse_id'] ?? null,
                    $purchaseReturn->purchase_return_id
                );

                $header = $this->headerData($data);
                unset($header['return_number']);
                unset($header['created_by']);
                $header['updated_by'] = auth()->id();

                $purchaseReturn->update($header);

                $purchaseReturn->details()->delete();
                $this->saveDetails($purchaseReturn, $details);
                $this->recalculateTotals($purchaseReturn);

                return $purchaseReturn->fresh(['details', 'supplierAccount']);
            });
        });
    }

    // =========================================================
    // DELETE — يمنع حذف المرحّل
    // =========================================================
    public function delete(int $id): void
    {
        $this->withWriteLock(function () use ($id) {
            DB::transaction(function () use ($id) {
                $purchaseReturn = PurchaseReturn::lockForUpdate()->findOrFail($id);

                // ✅ منع حذف المرتجع المرحّل قبل إلغاء الترحيل
                if ($purchaseReturn->isPosted()) {
                    throw new \RuntimeException(
                        'لا يمكن حذف مرتجع مرحّل. قم بإلغاء الترحيل أولاً.'
                    );
                }

                $this->deleteAccountingEntry($purchaseReturn);
                $this->deleteInventoryMovement($purchaseReturn);

                $purchaseReturn->details()->delete();
                $purchaseReturn->delete();
            });
        });
    }

    // =========================================================
    // POST / UNPOST
    // =========================================================
    public function post(int $id): PurchaseReturn
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $purchaseReturn = PurchaseReturn::lockForUpdate()->findOrFail($id);

                if ($purchaseReturn->isPosted()) {
                    throw new \RuntimeException('مرتجع الشراء مرحّل مسبقًا');
                }

                $this->postInternal($purchaseReturn);

                return $purchaseReturn->fresh(['details', 'supplierAccount']);
            });
        });
    }

    public function unpost(int $id): PurchaseReturn
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $purchaseReturn = PurchaseReturn::lockForUpdate()->findOrFail($id);

                if (!$purchaseReturn->isPosted()) {
                    throw new \RuntimeException('مرتجع الشراء غير مرحّل');
                }

                $this->unpostInternal($purchaseReturn);

                return $purchaseReturn->fresh(['details', 'supplierAccount']);
            });
        });
    }

    // =========================================================
    // POST / UNPOST INTERNAL
    // =========================================================
    private function postInternal(PurchaseReturn $purchaseReturn): void
    {
        $purchaseReturn->loadMissing(['details', 'supplierAccount']);

        $this->syncInventoryMovement($purchaseReturn);

        // ✅ إنشاء القيد واستلام entryID
        $entryId = $this->syncAccountingEntry($purchaseReturn);

        $purchaseReturn->entryID    = $entryId;
        $purchaseReturn->is_posted  = true;
        $purchaseReturn->posted_at  = now();
        $purchaseReturn->posted_by  = auth()->id();
        $purchaseReturn->updated_by = auth()->id();
        $purchaseReturn->save();
    }

    private function unpostInternal(PurchaseReturn $purchaseReturn): void
    {
        $this->deleteAccountingEntry($purchaseReturn);
        $this->deleteInventoryMovement($purchaseReturn);

        $purchaseReturn->is_posted  = false;
        $purchaseReturn->posted_at  = null;
        $purchaseReturn->posted_by  = null;
        $purchaseReturn->updated_by = auth()->id();
        $purchaseReturn->save();
    }

    // =========================================================
    // WRITE LOCK
    // =========================================================
    private function withWriteLock(callable $callback)
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);

        if (!$lock->get()) {
            throw new \RuntimeException(
                'عملية أخرى على مرتجعات الشراء قيد التنفيذ، حاول بعد قليل'
            );
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Purchase return operation failed', [
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
    // AVAILABLE FOR RETURN
    // =========================================================
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

    // =========================================================
    // VALIDATION
    // =========================================================
    protected function lockAndValidateInvoice(
        int $invoiceId,
        $accountId,
        $coinId,
        $warehouseId = null
    ): void {
        $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($invoiceId);

        if (!$invoice->isPosted()) {
            throw ValidationException::withMessages([
                'original_purchase_invoice_id' => 'لا يمكن الإرجاع من فاتورة غير مرحّلة',
            ]);
        }

        if ((int) $accountId !== (int) $invoice->account_id) {
            throw ValidationException::withMessages([
                'account_id' => 'حساب المورد لا يطابق الفاتورة الأصلية',
            ]);
        }

        if ((int) $coinId !== (int) $invoice->coin_id) {
            throw ValidationException::withMessages([
                'coin_id' => 'العملة لا تطابق الفاتورة الأصلية',
            ]);
        }

        if ($warehouseId !== null && (int) $warehouseId !== (int) $invoice->warehouse_id) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'المستودع لا يطابق المستودع الأصلي للفاتورة',
            ]);
        }
    }

    protected function validateReturnQuantities(
        int $originalInvoiceId,
        array $details,
        ?int $exceptReturnId = null
    ): void {
        $errors = [];

        foreach ($details as $i => $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) {
                continue;
            }

            $detail = PurchaseInvoiceDetail::find($detailId);

            if (!$detail) {
                $errors["details.{$i}.purchase_invoice_detail_id"] = "سطر الفاتورة #{$detailId} غير موجود";
                continue;
            }

            if ((int) $detail->purchase_invoice_id !== $originalInvoiceId) {
                $errors["details.{$i}.purchase_invoice_detail_id"] =
                    "سطر الفاتورة #{$detailId} لا ينتمي للفاتورة الأصلية";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $grouped = [];
        foreach ($details as $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) {
                continue;
            }

            $grouped[$detailId] = ($grouped[$detailId] ?? 0) + $qty;
        }

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

    // =========================================================
    // LOCK STOCK ROWS
    // =========================================================
    protected function lockStockRows(array $details, $warehouseId): void
    {
        if (!$warehouseId) {
            return;
        }

        $pairs = [];
        foreach ($details as $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            if ($detailId <= 0) continue;

            $detail = PurchaseInvoiceDetail::find($detailId);
            if (!$detail) continue;

            $key = $detail->item_id . '|' . ($detail->unit_id ?? 0);
            $pairs[$key] = [
                'item_id' => (int) $detail->item_id,
                'unit_id' => $detail->unit_id ? (int) $detail->unit_id : null,
            ];
        }

        ksort($pairs);

        foreach ($pairs as $pair) {
            $query = DB::table('inventory_movement_details')
                ->where('item_id', $pair['item_id'])
                ->where('warehouse_id', (int) $warehouseId);

            if ($pair['unit_id']) {
                $query->where('unit_id', $pair['unit_id']);
            }

            $query->select('movement_detail_id')
                  ->lockForUpdate()
                  ->get();
        }
    }

    // =========================================================
    // STOCK AVAILABILITY
    // =========================================================
    protected function validateStockAvailability(
        array $details,
        $warehouseId,
        ?int $exceptReturnId = null
    ): void {
        if (!$warehouseId) {
            return;
        }

        $errors = [];
        $stockNeeds = [];

        foreach ($details as $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) {
                continue;
            }

            $detail = PurchaseInvoiceDetail::find($detailId);
            if (!$detail) {
                continue;
            }

            $key = $detail->item_id . '|' . ($detail->unit_id ?? 0);
            $stockNeeds[$key] = ($stockNeeds[$key] ?? 0) + $qty;
        }

        foreach ($stockNeeds as $key => $neededQty) {
            [$itemId, $unitId] = explode('|', $key);
            $itemId = (int) $itemId;
            $unitId = (int) $unitId;

            $stock = $this->inventoryService->availableQuantity(
                $itemId,
                (int) $warehouseId,
                $unitId > 0 ? $unitId : null
            );

            if ($exceptReturnId) {
                $currentReturnedQty = PurchaseReturnDetail::where('purchase_return_id', $exceptReturnId)
                    ->where('item_id', $itemId)
                    ->where('warehouse_id', (int) $warehouseId)
                    ->when($unitId > 0, function ($q) use ($unitId) {
                        $q->where('unit_id', $unitId);
                    })
                    ->sum('quantity');

                $stock += (float) $currentReturnedQty;
            }

            if ($neededQty > $stock) {
                $errors["details"] =
                    "الكمية المطلوبة للصنف #{$itemId} ({$neededQty}) أكبر من الرصيد الفعلي ({$stock})";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    // =========================================================
    // INVENTORY MOVEMENT
    // =========================================================
    public function syncInventoryMovement(PurchaseReturn $purchaseReturn): void
    {
        /* ✅ علاقة المورد مطلوبة للبيان */
        $purchaseReturn->loadMissing('supplierAccount');

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

        /* ✅ البيان: أولوية لبيان المستخدم، وإلا البيان التلقائي،
           ثم يُلحق اسم المورد دائمًا (سواء كان البيان يدويًا أو تلقائيًا). */
        $supplierName = trim((string) optional($purchaseReturn->supplierAccount)->accName);

        $returnStatement = trim((string) ($purchaseReturn->statement ?? ''));
        if ($returnStatement === '') {
            $returnStatement = 'صرف تلقائي من مرتجع شراء رقم ' . $purchaseReturn->return_number;
        }
        if ($supplierName !== '') {
            $returnStatement .= ' — المورد: ' . $supplierName;
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
            'statement'       => $returnStatement,
            'source_type'     => InventoryMovement::SOURCE_PURCHASE_RETURN,
            'source_id'       => $purchaseReturn->purchase_return_id,
            'total'           => 0,
        ]);

        $movementTotal = 0;

        foreach ($purchaseReturn->details as $detail) {
            $quantity  = (float) $detail->quantity;
            $unitCost  = (float) $detail->unit_cost;
            $lineTotal = $quantity * $unitCost;

            /* ✅ المورد: من فاتورة الأصل عبر سطر الفاتورة المرجعي */
            $supplierAccountId = $detail->originalDetail?->purchaseInvoice?->account_id;

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $detail->warehouse_id ?: $headerWarehouse,
                'supplier_account_id' => $supplierAccountId !== null ? (int) $supplierAccountId : null,
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

    // =========================================================
    // ACCOUNTING ENTRY
    // =========================================================
    protected function syncAccountingEntry(PurchaseReturn $purchaseReturn): int
    {
        $this->deleteAccountingEntry($purchaseReturn);

        // 1) الحساب المالي
        $rate     = (float) ($purchaseReturn->exchange_rate ?: 1);
        $itemsNet = (float) $purchaseReturn->items_total
                  - (float) $purchaseReturn->discount_total;

        $totalFC = round($itemsNet, 6);
        $totalBC = round($totalFC * $rate, 6);

        if ($totalBC <= 0) {
            throw new \RuntimeException(
                'إجمالي مرتجع الشراء يجب أن يكون أكبر من صفر لإنشاء القيد'
            );
        }

        // 2) حساب المخزون (دائن)
        $warehouseId        = (int) $purchaseReturn->warehouse_id;
        $inventoryAccountId = $this->getInventoryAccountIdFromWarehouse($warehouseId);

        if ($inventoryAccountId <= 0) {
            throw new \RuntimeException(
                'حساب المخزون غير مُعرّف للمخزن رقم ' . $warehouseId
            );
        }

        // 3) حساب المدين (المورد أو الدفع)
        $paymentMethod    = (int) $purchaseReturn->payment_method;
        $debitAccountId   = $paymentMethod === PurchaseReturn::PAYMENT_CREDIT
            ? (int) $purchaseReturn->account_id
            : (int) $purchaseReturn->payment_account_id;

        if ($debitAccountId <= 0) {
            throw new \RuntimeException(
                'حساب الدفع غير محدد للمرتجع رقم ' . $purchaseReturn->return_number
            );
        }

        // 4) التاريخ والوصف
        $entryDate = $purchaseReturn->return_date instanceof \DateTimeInterface
            ? $purchaseReturn->return_date->format('Y-m-d')
            : (string) ($purchaseReturn->return_date ?: now()->toDateString());

        $docType = $purchaseReturn->journal_doc_type_label;

        $supplierName = $purchaseReturn->supplierAccount->accName ?? '';

        $description = $purchaseReturn->journal_doc_type_label
            . ' رقم ' . $purchaseReturn->return_number
            . ($supplierName !== '' ? ' - ' . $supplierName : '');

        // 5) السطور
        // ✅ القيد: مدين المورد/الدفع + دائن المخزون
        $lines = [
            [
                'accountID'    => $debitAccountId,
                'coinsID'      => $purchaseReturn->coin_id,
                'exchangRate'  => $rate,
                'debit'        => $totalFC,
                'credit'       => 0,
                'localDebit'   => $totalBC,
                'localCredit'  => 0,
                'description2' => $description,
            ],
            [
                'accountID'    => $inventoryAccountId,
                'coinsID'      => $purchaseReturn->coin_id,
                'exchangRate'  => $rate,
                'debit'        => 0,
                'credit'       => $totalFC,
                'localDebit'   => 0,
                'localCredit'  => $totalBC,
                'description2' => $description . ' - قيمة البضاعة',
            ],
        ];

        // 6) إنشاء القيد
        $entryId = JournalEntryService::create([
            'entryDate'   => $entryDate,
            'docType'     => $docType,
            'docNumber'   => (string) $purchaseReturn->return_number,
            'description' => $description,
            'lines'       => $lines,
        ]);

        if (!$entryId) {
            throw new \RuntimeException(
                'فشل إنشاء القيد المحاسبي لمرتجع الشراء رقم ' . $purchaseReturn->return_number
            );
        }

        return (int) $entryId;
    }

    protected function deleteAccountingEntry(PurchaseReturn $purchaseReturn): void
    {
        if (!empty($purchaseReturn->entryID)) {
            JournalEntryService::delete((int) $purchaseReturn->entryID);

            $purchaseReturn->entryID = null;
            $purchaseReturn->saveQuietly();

            return;
        }

        if (!empty($purchaseReturn->return_number)) {
            JournalEntryService::deleteByDocNumber(
                (string) $purchaseReturn->return_number
            );
        }
    }

    // =========================================================
    // INVENTORY ACCOUNT RESOLVER
    // =========================================================
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
    protected function nextReturnNumber(): int
    {
        $last = PurchaseReturn::lockForUpdate()
            ->orderBy('purchase_return_id', 'desc')
            ->first();

        return $last ? ((int) $last->return_number + 1) : 1;
    }

    protected function headerData(array $data): array
    {
        return [
            'return_number'                => $data['return_number'] ?? null,
            'return_date'                  => $data['return_date'] ?? null,
            'original_purchase_invoice_id' => $data['original_purchase_invoice_id'] ?? null,
            'account_id'                   => $data['account_id'] ?? null,
            'payment_account_id'           => $data['payment_account_id'] ?? null,
            'coin_id'                      => $data['coin_id'] ?? null,
            'warehouse_id'                 => $data['warehouse_id'] ?? null,
            'exchange_rate'                => $data['exchange_rate'] ?? 1,
            'payment_method'               => $data['payment_method'] ?? null,
            'statement'                    => $data['statement'] ?? null,
            'reference'                    => $data['reference'] ?? null,
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