<?php

namespace App\Services\Sales;

use App\Models\Accounting\CharAccount;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Stock;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnDetail;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceDetail;
use App\Services\Inventory\InventoryService;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * خدمة مرتجعات البيع
 * =========================================================
 * - ترحيل تلقائي عند الإنشاء
 * - قيد محاسبي كامل (contra-revenue):
 *     مدين: مردود المبيعات (sales_returns)      [itemsNet]
 *     دائن: العميل / الصندوق / البنك             [itemsNet]
 *     مدين: المخزون (per warehouse)              [cost]
 *     دائن: COGS (cost_of_goods_sold)            [cost]
 * - حركة مخزون إدخال (TYPE_SALE_RETURN)
 * - Cache::lock + DB::transaction + Log::error
 * - منع تعديل/حذف المرحّل
 */
class SalesReturnService
{
    // =========================================================
    // CONSTANTS
    // =========================================================

    private const LOCK_KEY = 'sales_return_write_lock';
    private const LOCK_TTL = 15;
    private const ACCOUNT_CACHE_TTL = 3600;

    private const SALES_RETURNS_REVENUE_SYSTEM_KEY = 'sales_returns';
    private const COST_OF_GOODS_SOLD_SYSTEM_KEY    = 'cost_of_goods_sold';

    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // =========================================================
    // CREATE — حفظ + ترحيل تلقائي
    // =========================================================

    public function create(array $data): SalesReturn
    {
        return $this->withWriteLock(function () use ($data) {
            return DB::transaction(function () use ($data) {

                $originalInvoiceId = (int) ($data['original_sales_invoice_id'] ?? 0);

                $this->lockAndValidateInvoice(
                    $originalInvoiceId,
                    $data['account_id'] ?? null,
                    $data['coin_id'] ?? null
                );

                $details = $data['details'] ?? [];

                $this->validateReturnQuantities($originalInvoiceId, $details);
                $this->lockStockRows($details);

                $nextNumber = $this->nextReturnNumber();

                $header = $this->headerData($data);
                $header['return_number'] = (string) $nextNumber;
                $header['created_by']    = auth()->id();

                $salesReturn = SalesReturn::create($header);

                $this->saveDetails($salesReturn, $details);
                $this->recalculateTotals($salesReturn);

                $this->postInternal($salesReturn);

                return $salesReturn->fresh(['details', 'customerAccount']);
            });
        });
    }

    // =========================================================
    // UPDATE
    // =========================================================

    public function update(int $id, array $data): SalesReturn
    {
        return $this->withWriteLock(function () use ($id, $data) {
            return DB::transaction(function () use ($id, $data) {

                $salesReturn = SalesReturn::lockForUpdate()->findOrFail($id);

                if ($salesReturn->isPosted()) {
                    throw new \RuntimeException(
                        'لا يمكن تعديل مرتجع مرحّل. قم بإلغاء الترحيل أولاً.'
                    );
                }

                $originalInvoiceId = (int) ($data['original_sales_invoice_id'] ?? 0);

                $this->lockAndValidateInvoice(
                    $originalInvoiceId,
                    $data['account_id'] ?? null,
                    $data['coin_id'] ?? null
                );

                $details = $data['details'] ?? [];

                $this->validateReturnQuantities(
                    $originalInvoiceId,
                    $details,
                    $salesReturn->sales_return_id
                );

                $this->lockStockRows($details);

                $header = $this->headerData($data);
                unset($header['return_number'], $header['created_by']);
                $header['updated_by'] = auth()->id();

                $salesReturn->update($header);

                $salesReturn->details()->delete();
                $this->saveDetails($salesReturn, $details);
                $this->recalculateTotals($salesReturn);

                return $salesReturn->fresh(['details', 'customerAccount']);
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
                $salesReturn = SalesReturn::lockForUpdate()->findOrFail($id);

                if ($salesReturn->isPosted()) {
                    throw new \RuntimeException(
                        'لا يمكن حذف مرتجع مرحّل. قم بإلغاء الترحيل أولاً.'
                    );
                }

                $this->deleteAccountingEntry($salesReturn);
                $this->deleteInventoryMovement($salesReturn);

                $salesReturn->details()->delete();
                $salesReturn->delete();
            });
        });
    }

    // =========================================================
    // POST / UNPOST
    // =========================================================

    public function post(int $id): SalesReturn
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $salesReturn = SalesReturn::lockForUpdate()->findOrFail($id);

                if ($salesReturn->isPosted()) {
                    throw new \RuntimeException('مرتجع البيع مرحّل مسبقًا');
                }

                $this->postInternal($salesReturn);

                return $salesReturn->fresh(['details', 'customerAccount']);
            });
        });
    }

    public function unpost(int $id): SalesReturn
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $salesReturn = SalesReturn::lockForUpdate()->findOrFail($id);

                if (!$salesReturn->isPosted()) {
                    throw new \RuntimeException('مرتجع البيع غير مرحّل');
                }

                $this->unpostInternal($salesReturn);

                return $salesReturn->fresh(['details', 'customerAccount']);
            });
        });
    }

    // =========================================================
    // POST / UNPOST INTERNAL
    // =========================================================

    private function postInternal(SalesReturn $salesReturn): void
    {
        $salesReturn->loadMissing(['details', 'customerAccount']);

        $this->syncInventoryMovement($salesReturn);
        $entryId = $this->syncAccountingEntry($salesReturn);

        $salesReturn->entryID    = $entryId;
        $salesReturn->is_posted  = true;
        $salesReturn->posted_at  = now();
        $salesReturn->posted_by  = auth()->id();
        $salesReturn->updated_by = auth()->id();
        $salesReturn->save();
    }

    private function unpostInternal(SalesReturn $salesReturn): void
    {
        $this->deleteAccountingEntry($salesReturn);
        $this->deleteInventoryMovement($salesReturn);

        $salesReturn->is_posted  = false;
        $salesReturn->posted_at  = null;
        $salesReturn->posted_by  = null;
        $salesReturn->updated_by = auth()->id();
        $salesReturn->save();
    }

    // =========================================================
    // WRITE LOCK
    // =========================================================

    private function withWriteLock(callable $callback)
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);

        if (!$lock->get()) {
            throw new \RuntimeException(
                'عملية أخرى على مرتجعات البيع قيد التنفيذ، حاول بعد قليل'
            );
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Sales return operation failed', [
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

    // =========================================================
    // VALIDATION
    // =========================================================

    protected function lockAndValidateInvoice(int $invoiceId, $accountId, $coinId): void
    {
        $invoice = SalesInvoice::lockForUpdate()->findOrFail($invoiceId);

        if (!$invoice->isPosted()) {
            throw ValidationException::withMessages([
                'original_sales_invoice_id' => 'لا يمكن الإرجاع من فاتورة غير مرحّلة',
            ]);
        }

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

    protected function validateReturnQuantities(
        int $originalInvoiceId,
        array $details,
        ?int $exceptReturnId = null
    ): void {
        $errors = [];

        foreach ($details as $i => $row) {
            $detailId = (int) ($row['sales_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) continue;

            $detail = SalesInvoiceDetail::find($detailId);

            if (!$detail) {
                $errors["details.{$i}.sales_invoice_detail_id"] =
                    "سطر الفاتورة #{$detailId} غير موجود";
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

        $grouped = [];
        foreach ($details as $row) {
            $detailId = (int) ($row['sales_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);

            if ($detailId <= 0 || $qty <= 0) continue;

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

    protected function lockStockRows(array $details): void
    {
        $pairs = [];

        foreach ($details as $row) {
            $detailId = (int) ($row['sales_invoice_detail_id'] ?? 0);
            if ($detailId <= 0) continue;

            $detail = SalesInvoiceDetail::find($detailId);
            if (!$detail) continue;

            $warehouseId = (int) ($detail->warehouse_id ?? 0);
            if (!$warehouseId) continue;

            $itemId = (int) $detail->item_id;
            $unitId = $detail->unit_id ? (int) $detail->unit_id : null;

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
    // INVENTORY MOVEMENT — إدخال (استلام من العميل)
    // =========================================================

    public function syncInventoryMovement(SalesReturn $salesReturn): void
    {
        /* ✅ علاقة العميل مطلوبة للبيان */
        $salesReturn->loadMissing('customerAccount');

        $this->deleteInventoryMovement($salesReturn);

        $lastMovement = InventoryMovement::lockForUpdate()
            ->orderBy('movement_id', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? ((int) $lastMovement->display_id + 1)
            : 1;

        $firstDetail     = $salesReturn->details->first();
        $headerWarehouse = $salesReturn->warehouse_id
            ?: ($firstDetail ? $firstDetail->warehouse_id : null);

        if (!$headerWarehouse) {
            throw new \RuntimeException('لا يمكن إنشاء حركة مخزون بدون مخزن');
        }

        /* ✅ البيان: اسم العميل + رقم المرتجع */
        $customerName = trim((string) optional($salesReturn->customerAccount)->accName);

        $returnStatement = 'إدخال تلقائي من مرتجع بيع رقم ' . $salesReturn->return_number;
        if ($customerName !== '') {
            $returnStatement .= ' — العميل: ' . $customerName;
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
            'statement'       => $returnStatement,
            'source_type'     => InventoryMovement::SOURCE_SALES_RETURN,
            'source_id'       => $salesReturn->sales_return_id,
            'total'           => 0,
        ]);

        $movementTotal = 0;

        foreach ($salesReturn->details as $detail) {
            $quantity  = (float) $detail->quantity;
            $unitCost  = (float) ($detail->cost_price ?? 0);
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

    // =========================================================
    // ACCOUNTING ENTRY
    // =========================================================
    //
    // قيد مرتجع البيع:
    //   مدين: مردود المبيعات (sales_returns)      [itemsNet]
    //   دائن: العميل / الصندوق / البنك             [itemsNet]
    //   مدين: المخزون (per warehouse)              [cost]
    //   دائن: COGS (cost_of_goods_sold)            [cost]
    //
    // =========================================================

    protected function syncAccountingEntry(SalesReturn $salesReturn): int
    {
        $this->deleteAccountingEntry($salesReturn);

        $rate     = (float) ($salesReturn->exchange_rate ?: 1);
        $itemsNet = (float) $salesReturn->items_total
                  - (float) $salesReturn->discount_total;

        $totalFC = round($itemsNet, 6);
        $totalBC = round($totalFC * $rate, 6);

        if ($totalBC <= 0) {
            throw new \RuntimeException(
                'إجمالي مرتجع البيع يجب أن يكون أكبر من صفر لإنشاء القيد'
            );
        }

        // 1) حساب مردود المبيعات (مدين)
        $salesReturnsRevenueAccountId = $this->getAccountIdBySystemKey(
            self::SALES_RETURNS_REVENUE_SYSTEM_KEY
        );

        if ($salesReturnsRevenueAccountId <= 0) {
            throw new \RuntimeException(
                'حساب مردود المبيعات (' . self::SALES_RETURNS_REVENUE_SYSTEM_KEY
                . ') غير موجود في دليل الحسابات'
            );
        }

        // 2) حساب العميل/الدفع (دائن)
        $paymentMethod   = (int) $salesReturn->payment_method;
        $creditAccountId = $paymentMethod === SalesReturn::PAYMENT_CREDIT
            ? (int) $salesReturn->account_id
            : (int) $salesReturn->payment_account_id;

        if ($creditAccountId <= 0) {
            throw new \RuntimeException(
                'حساب العميل/الدفع غير محدد للمرتجع رقم ' . $salesReturn->return_number
            );
        }

        // 3) حساب COGS (دائن — عكس التكلفة)
        $cogsAccountId = $this->getAccountIdBySystemKey(
            self::COST_OF_GOODS_SOLD_SYSTEM_KEY
        );

        if ($cogsAccountId <= 0) {
            throw new \RuntimeException(
                'حساب تكلفة البضاعة المباعة (' . self::COST_OF_GOODS_SOLD_SYSTEM_KEY
                . ') غير موجود في دليل الحسابات'
            );
        }

        // 4) التاريخ والوصف
        $entryDate = $salesReturn->return_date instanceof \DateTimeInterface
            ? $salesReturn->return_date->format('Y-m-d')
            : (string) ($salesReturn->return_date ?: now()->toDateString());

        $docType = $salesReturn->journal_doc_type_label;

        $customerName = $salesReturn->customerAccount->accName ?? '';

        $description = $salesReturn->journal_doc_type_label
            . ' رقم ' . $salesReturn->return_number
            . ($customerName !== '' ? ' - ' . $customerName : '');

        // 5) السطور
        $lines = [
            // (أ) مدين: مردود المبيعات (contra-revenue)
            [
                'accountID'    => $salesReturnsRevenueAccountId,
                'coinsID'      => $salesReturn->coin_id,
                'exchangRate'  => $rate,
                'debit'        => $totalFC,
                'credit'       => 0,
                'localDebit'   => $totalBC,
                'localCredit'  => 0,
                'description2' => $description . ' - مردود المبيعات',
            ],
            // (ب) دائن: العميل/الصندوق
            [
                'accountID'    => $creditAccountId,
                'coinsID'      => $salesReturn->coin_id,
                'exchangRate'  => $rate,
                'debit'        => 0,
                'credit'       => $totalFC,
                'localDebit'   => 0,
                'localCredit'  => $totalBC,
                'description2' => $description,
            ],
        ];

        // (ج) COGS + المخزون — تجميع حسب المخزن
        $costByWarehouse = [];
        $costTotalFC = 0.0;
        $costTotalBC = 0.0;

        foreach ($salesReturn->details as $detail) {
            $qty    = (float) $detail->quantity;
            $costFC = (float) ($detail->cost_price ?? 0);
            $lineFC = $qty * $costFC;
            $lineBC = $lineFC * $rate;

            $warehouseId = (int) $detail->warehouse_id;
            if ($warehouseId <= 0) {
                throw new \RuntimeException(
                    'سطر المرتجع لا يحتوي مخزنًا صالحًا'
                );
            }

            if (!isset($costByWarehouse[$warehouseId])) {
                $costByWarehouse[$warehouseId] = ['fc' => 0.0, 'bc' => 0.0];
            }

            $costByWarehouse[$warehouseId]['fc'] += $lineFC;
            $costByWarehouse[$warehouseId]['bc'] += $lineBC;

            $costTotalFC += $lineFC;
            $costTotalBC += $lineBC;
        }

        if ($costTotalFC > 0) {
            // (ج-1) مدين: المخزون لكل مخزن — دخول البضاعة
            foreach ($costByWarehouse as $warehouseId => $amounts) {
                $inventoryAccountId = $this->getInventoryAccountIdFromWarehouse($warehouseId);

                if ($inventoryAccountId <= 0) {
                    throw new \RuntimeException(
                        'حساب المخزون غير مُعرّف للمخزن رقم ' . $warehouseId
                    );
                }

                $lines[] = [
                    'accountID'    => $inventoryAccountId,
                    'coinsID'      => $salesReturn->coin_id,
                    'exchangRate'  => $rate,
                    'debit'        => round($amounts['fc'], 6),
                    'credit'       => 0,
                    'localDebit'   => round($amounts['bc'], 6),
                    'localCredit'  => 0,
                    'description2' => $description . ' - دخول للمخزون',
                ];
            }

            // (ج-2) دائن: COGS — عكس التكلفة
            $lines[] = [
                'accountID'    => $cogsAccountId,
                'coinsID'      => $salesReturn->coin_id,
                'exchangRate'  => $rate,
                'debit'        => 0,
                'credit'       => round($costTotalFC, 6),
                'localDebit'   => 0,
                'localCredit'  => round($costTotalBC, 6),
                'description2' => $description . ' - عكس تكلفة البضاعة المباعة',
            ];
        }

        // 6) إنشاء القيد
        try {
            $entryId = JournalEntryService::create([
                'entryDate'   => $entryDate,
                'docType'     => $docType,
                'docNumber'   => (string) $salesReturn->return_number,
                'description' => $description,
                'lines'       => $lines,
            ]);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(
                'فشل إنشاء القيد المحاسبي لمرتجع البيع رقم '
                . $salesReturn->return_number . ': ' . $e->getMessage()
            );
        }

        if (!$entryId) {
            throw new \RuntimeException(
                'فشل إنشاء القيد المحاسبي لمرتجع البيع رقم ' . $salesReturn->return_number
            );
        }

        return (int) $entryId;
    }

    protected function deleteAccountingEntry(SalesReturn $salesReturn): void
    {
        if (!empty($salesReturn->entryID)) {
            JournalEntryService::delete((int) $salesReturn->entryID);

            $salesReturn->entryID = null;
            $salesReturn->saveQuietly();

            return;
        }

        if (!empty($salesReturn->return_number)) {
            JournalEntryService::deleteByDocNumber(
                (string) $salesReturn->return_number
            );
        }
    }

    // =========================================================
    // ACCOUNT RESOLVERS (Cache)
    // =========================================================

    private function getAccountIdBySystemKey(string $systemKey): int
    {
        return (int) Cache::remember(
            "accounting.{$systemKey}_id",
            self::ACCOUNT_CACHE_TTL,
            function () use ($systemKey) {
                return (int) CharAccount::query()
                    ->where('system_key', $systemKey)
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
            self::ACCOUNT_CACHE_TTL,
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
            'warehouse_id'              => $data['warehouse_id'] ?? null,
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

            $quantity  = (float) ($row['quantity'] ?? 0);
            $price     = (float) ($row['price'] ?? $invoiceDetail->price);
            $discount  = (float) ($row['discount'] ?? 0);
            $total     = max(0, ($quantity * $price) - $discount);
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

        if (empty($salesReturn->warehouse_id)) {
            $firstWarehouse = $details->first()->warehouse_id ?? null;
            if ($firstWarehouse) {
                $salesReturn->warehouse_id = $firstWarehouse;
            }
        }

        $salesReturn->save();
    }
}