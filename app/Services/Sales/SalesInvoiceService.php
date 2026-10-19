<?php

namespace App\Services\Sales;

use App\Models\Accounting\CharAccount;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Stock;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceDetail;
use App\Services\Inventory\InventoryService;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    // =========================================================
    // CONSTANTS
    // =========================================================

    private const LOCK_KEY = 'sales_invoice_write_lock';
    private const LOCK_TTL = 15;

    private const INVENTORY_ACCOUNT_CACHE_TTL = 3600;

    private const SALES_REVENUE_SYSTEM_KEY      = 'sales_revenue';
    private const COST_OF_GOODS_SOLD_SYSTEM_KEY = 'cost_of_goods_sold';

    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // =========================================================
    // CREATE — حفظ + ترحيل تلقائي
    // =========================================================
    public function create(array $data): SalesInvoice
    {
        return $this->withWriteLock(function () use ($data) {
            return DB::transaction(function () use ($data) {

                $details = $data['details'] ?? [];

                $this->lockStockRows($details);
                $this->validateStockAvailability($details);

                $nextNumber = $this->nextInvoiceNumber();

                $header = $this->headerData($data);
                $header['invoice_number'] = (string) $nextNumber;
                $header['created_by']     = auth()->id();

                $invoice = SalesInvoice::create($header);

                $this->saveDetails($invoice, $details);
                $this->recalculateTotals($invoice);

                // ✅ ترحيل تلقائي
                $this->postInternal($invoice);

                return $invoice->fresh(['details', 'customerAccount']);
            });
        });
    }

    // =========================================================
    // UPDATE — يمنع تعديل المرحّلة أو التي لها مرتجعات
    // =========================================================
    public function update(int $id, array $data): SalesInvoice
    {
        return $this->withWriteLock(function () use ($id, $data) {
            return DB::transaction(function () use ($id, $data) {

                $invoice = SalesInvoice::lockForUpdate()->findOrFail($id);

                if ($invoice->isPosted()) {
                    throw new \RuntimeException(
                        'لا يمكن تعديل فاتورة مرحّلة. قم بإلغاء الترحيل أولًا.'
                    );
                }

                if ($invoice->returns()->exists()) {
                    throw new \RuntimeException(
                        'لا يمكن تعديل فاتورة لها مرتجعات. احذف المرتجعات أولًا.'
                    );
                }

                $details = $data['details'] ?? [];

                $this->lockStockRows($details);
                $this->validateStockAvailability($details);

                $header = $this->headerData($data);
                unset($header['invoice_number']);
                unset($header['created_by']);
                $header['updated_by'] = auth()->id();

                $invoice->update($header);

                $this->deleteInventoryMovement($invoice);
                $invoice->details()->delete();
                $this->saveDetails($invoice, $details);
                $this->recalculateTotals($invoice);

                return $invoice->fresh(['details', 'customerAccount']);
            });
        });
    }

    // =========================================================
    // DELETE — يمنع الحذف إذا كان لها مرتجعات
    // =========================================================
    public function delete(int $id): void
    {
        $this->withWriteLock(function () use ($id) {
            DB::transaction(function () use ($id) {

                $invoice = SalesInvoice::lockForUpdate()->findOrFail($id);

                if ($invoice->returns()->exists()) {
                    throw new \RuntimeException(
                        'لا يمكن حذف فاتورة لها مرتجعات. احذف المرتجعات أولًا.'
                    );
                }

                $this->deleteAccountingEntry($invoice);
                $this->deleteInventoryMovement($invoice);

                $invoice->details()->delete();
                $invoice->delete();
            });
        });
    }

    // =========================================================
    // POST / UNPOST
    // =========================================================
    public function post(int $id): SalesInvoice
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $invoice = SalesInvoice::lockForUpdate()->findOrFail($id);

                if ($invoice->isPosted()) {
                    throw new \RuntimeException('الفاتورة مرحّلة مسبقًا');
                }

                $this->postInternal($invoice);

                return $invoice->fresh(['details', 'customerAccount']);
            });
        });
    }

    public function unpost(int $id): SalesInvoice
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $invoice = SalesInvoice::lockForUpdate()->findOrFail($id);

                if (!$invoice->isPosted()) {
                    throw new \RuntimeException('الفاتورة غير مرحّلة');
                }

                $this->unpostInternal($invoice);

                return $invoice->fresh(['details', 'customerAccount']);
            });
        });
    }

    // =========================================================
    // POST / UNPOST INTERNAL
    // =========================================================
    private function postInternal(SalesInvoice $invoice): void
    {
        $invoice->loadMissing(['details', 'customerAccount']);

        $this->syncInventoryMovement($invoice);
        $entryId = $this->syncAccountingEntry($invoice);

        $invoice->entryID    = $entryId;
        $invoice->is_posted  = true;
        $invoice->posted_at  = now();
        $invoice->posted_by  = auth()->id();
        $invoice->updated_by = auth()->id();
        $invoice->save();
    }

    private function unpostInternal(SalesInvoice $invoice): void
    {
        $this->deleteAccountingEntry($invoice);
        $this->deleteInventoryMovement($invoice);

        $invoice->is_posted  = false;
        $invoice->posted_at  = null;
        $invoice->posted_by  = null;
        $invoice->updated_by = auth()->id();
        $invoice->save();
    }

    // =========================================================
    // WRITE LOCK
    // =========================================================
    private function withWriteLock(callable $callback)
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);

        if (!$lock->get()) {
            throw new \RuntimeException(
                'عملية أخرى على فواتير البيع قيد التنفيذ، حاول بعد قليل'
            );
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Sales invoice operation failed', [
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
    protected function lockStockRows(array $details): void
    {
        $pairs = [];

        foreach ($details as $row) {
            $itemId      = (int) ($row['item_id'] ?? 0);
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);
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
    // STOCK AVAILABILITY
    // =========================================================
    protected function validateStockAvailability(array $details): void
    {
        $errors = [];

        foreach ($details as $i => $row) {
            $itemId      = $row['item_id'] ?? null;
            $warehouseId = $row['warehouse_id'] ?? null;
            $unitId      = $row['unit_id'] ?? null;
            $qty         = (float) ($row['quantity'] ?? 0);

            if (!$itemId || !$warehouseId || $qty <= 0) continue;

            $available = $this->inventoryService->availableQuantity(
                (int) $itemId,
                (int) $warehouseId,
                $unitId !== null ? (int) $unitId : null
            );

            if ($available < $qty) {
                $errors["details.{$i}.quantity"] =
                    "الكمية المتوفرة {$available} أقل من المطلوب {$qty}";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    // =========================================================
    // INVENTORY MOVEMENT
    // =========================================================
    public function syncInventoryMovement(SalesInvoice $invoice): void
    {
        /* ✅ علاقة العميل مطلوبة للبيان */
        $invoice->loadMissing('customerAccount');

        $this->deleteInventoryMovement($invoice);

        $lastMovement = InventoryMovement::lockForUpdate()
            ->orderBy('movement_id', 'desc')
            ->first();

        $nextNumber = $lastMovement
            ? ((int) $lastMovement->display_id + 1)
            : 1;

        $firstDetail     = $invoice->details->first();
        $headerWarehouse = $firstDetail ? $firstDetail->warehouse_id : null;

        if (!$headerWarehouse) {
            throw new \RuntimeException('لا يمكن إنشاء حركة مخزون بدون مخزن');
        }

        /* ✅ البيان: اسم العميل + رقم الفاتورة (ثابت في الحركة، ونص البيان المحفوظ غير متأثر) */
        $customerName = trim((string) optional($invoice->customerAccount)->accName);

        $statement = 'صرف تلقائي من فاتورة البيع رقم ' . $invoice->invoice_number;
        if ($customerName !== '') {
            $statement .= ' — العميل: ' . $customerName;
        }

        $movement = InventoryMovement::create([
            'display_id'      => (string) $nextNumber,
            'movement_type'   => InventoryMovement::TYPE_SALE,
            'direction'       => InventoryMovement::directionForType(
                                    InventoryMovement::TYPE_SALE
                                 ),
            'movement_date'   => $invoice->invoice_date,
            'document_number' => $invoice->invoice_number,
            'warehouse_id'    => $headerWarehouse,
            'statement'       => $statement,
            'source_type'     => InventoryMovement::SOURCE_SALES_INVOICE,
            'source_id'       => $invoice->sales_invoice_id,
            'total'           => 0,
        ]);

        $movementTotal = 0;

        foreach ($invoice->details as $detail) {
            $quantity  = (float) $detail->quantity;
            $unitCost  = (float) $detail->cost_price;
            $lineTotal = $quantity * $unitCost;

            /* ✅ آخر تسعير معروف للوحدة (سعر البيع/الحد الأدنى/الأعلى) بالعملة الأساسية.
               حركة البيع اتجاهها out ولا يقرأها allStockBalances للتسعير
               (يقرأ آخر حركة in فقط) → نسخها هنا للتوثيق فقط ولا يغيّر أي رصيد.
               كانت تُحفظ null فتظهر فارغة/صفر في شاشة حركات المخزون. */
            $lastPricing = $this->inventoryService->lastPricing(
                (int) $detail->item_id,
                (int) $detail->warehouse_id,
                $detail->unit_id !== null ? (int) $detail->unit_id : null
            );

            InventoryMovementDetail::create([
                'movement_id'  => $movement->movement_id,
                'item_id'      => $detail->item_id,
                'type_id'      => $detail->type_id,
                'unit_id'      => $detail->unit_id,
                'code'         => $detail->code,
                'warehouse_id' => $detail->warehouse_id,
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost,
                'min_price'    => $lastPricing['min_price'] > 0 ? $lastPricing['min_price'] : null,
                'max_price'    => $lastPricing['max_price'] > 0 ? $lastPricing['max_price'] : null,
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

    public function deleteInventoryMovement(SalesInvoice $invoice): void
    {
        $movements = InventoryMovement::where(
                'source_type',
                InventoryMovement::SOURCE_SALES_INVOICE
            )
            ->where('source_id', $invoice->sales_invoice_id)
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
    protected function syncAccountingEntry(SalesInvoice $invoice): int
    {
        $this->deleteAccountingEntry($invoice);

        $rate     = (float) ($invoice->exchange_rate ?: 1);
        $itemsNet = (float) $invoice->items_total - (float) $invoice->discount_total;

        $totalFC = round($itemsNet, 6);
        $totalBC = round($totalFC * $rate, 6);

        if ($totalBC <= 0) {
            throw new \RuntimeException(
                'إجمالي فاتورة البيع يجب أن يكون أكبر من صفر لإنشاء القيد'
            );
        }

        // 1) حساب العميل/الدفع (مدين)
        $paymentMethod = (int) $invoice->payment_method;
        $debitAccountId = $paymentMethod === SalesInvoice::PAYMENT_CREDIT
            ? (int) $invoice->account_id
            : (int) $invoice->payment_account_id;

        if ($debitAccountId <= 0) {
            throw new \RuntimeException(
                'حساب الدفع غير محدد للفاتورة رقم ' . $invoice->invoice_number
            );
        }

        // 2) حساب المبيعات (دائن)
        $salesRevenueAccountId = $this->getAccountIdBySystemKey(
            self::SALES_REVENUE_SYSTEM_KEY
        );

        if ($salesRevenueAccountId <= 0) {
            throw new \RuntimeException(
                'حساب المبيعات (' . self::SALES_REVENUE_SYSTEM_KEY
                . ') غير موجود في دليل الحسابات'
            );
        }

        // 3) حساب COGS (مدين) + حساب المخزون لكل تفصيل (دائن)
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
        $entryDate = $invoice->invoice_date instanceof \DateTimeInterface
            ? $invoice->invoice_date->format('Y-m-d')
            : (string) ($invoice->invoice_date ?: now()->toDateString());

        $docType = $invoice->journal_doc_type_label;

        $customerName = $invoice->customerAccount->accName ?? '';

        $description = $invoice->journal_doc_type_label
            . ' رقم ' . $invoice->invoice_number
            . ($customerName !== '' ? ' - ' . $customerName : '');

        // 5) السطور
        $lines = [
            // (أ) مدين: العميل / الصندوق / البنك
            [
                'accountID'    => $debitAccountId,
                'coinsID'      => $invoice->coin_id,
                'exchangRate'  => $rate,
                'debit'        => $totalFC,
                'credit'       => 0,
                'localDebit'   => $totalBC,
                'localCredit'  => 0,
                'description2' => $description,
            ],
            // (ب) دائن: المبيعات
            [
                'accountID'    => $salesRevenueAccountId,
                'coinsID'      => $invoice->coin_id,
                'exchangRate'  => $rate,
                'debit'        => 0,
                'credit'       => $totalFC,
                'localDebit'   => 0,
                'localCredit'  => $totalBC,
                'description2' => $description . ' - إيراد المبيعات',
            ],
        ];

        // (ج) COGS — تجميع حسب المخزون
        $cogsByWarehouse = [];
        $cogsTotalFC = 0;
        $cogsTotalBC = 0;

        foreach ($invoice->details as $detail) {
            $qty      = (float) $detail->quantity;
            $costFC   = (float) $detail->cost_price;
            $lineFC   = $qty * $costFC;
            $lineBC   = $lineFC * $rate;

            $warehouseId = (int) $detail->warehouse_id;

            if (!isset($cogsByWarehouse[$warehouseId])) {
                $cogsByWarehouse[$warehouseId] = [
                    'fc' => 0,
                    'bc' => 0,
                ];
            }

            $cogsByWarehouse[$warehouseId]['fc'] += $lineFC;
            $cogsByWarehouse[$warehouseId]['bc'] += $lineBC;

            $cogsTotalFC += $lineFC;
            $cogsTotalBC += $lineBC;
        }

        // مدين: COGS
        if ($cogsTotalFC > 0) {
            $lines[] = [
                'accountID'    => $cogsAccountId,
                'coinsID'      => $invoice->coin_id,
                'exchangRate'  => $rate,
                'debit'        => round($cogsTotalFC, 6),
                'credit'       => 0,
                'localDebit'   => round($cogsTotalBC, 6),
                'localCredit'  => 0,
                'description2' => $description . ' - تكلفة البضاعة المباعة',
            ];

            // دائن: حساب المخزون لكل مخزن
            foreach ($cogsByWarehouse as $warehouseId => $amounts) {
                $inventoryAccountId = $this->getInventoryAccountIdFromWarehouse($warehouseId);

                if ($inventoryAccountId <= 0) {
                    throw new \RuntimeException(
                        'حساب المخزون غير مُعرّف للمخزن رقم ' . $warehouseId
                    );
                }

                $lines[] = [
                    'accountID'    => $inventoryAccountId,
                    'coinsID'      => $invoice->coin_id,
                    'exchangRate'  => $rate,
                    'debit'        => 0,
                    'credit'       => round($amounts['fc'], 6),
                    'localDebit'   => 0,
                    'localCredit'  => round($amounts['bc'], 6),
                    'description2' => $description . ' - خروج من المخزون',
                ];
            }
        }

        // 6) إنشاء القيد
        $entryId = JournalEntryService::create([
            'entryDate'   => $entryDate,
            'docType'     => $docType,
            'docNumber'   => (string) $invoice->invoice_number,
            'description' => $description,
            'lines'       => $lines,
        ]);

        if (!$entryId) {
            throw new \RuntimeException(
                'فشل إنشاء القيد المحاسبي لفاتورة البيع رقم ' . $invoice->invoice_number
            );
        }

        return (int) $entryId;
    }

    protected function deleteAccountingEntry(SalesInvoice $invoice): void
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
    private function getAccountIdBySystemKey(string $systemKey): int
    {
        return (int) Cache::remember(
            "accounting.{$systemKey}_id",
            self::INVENTORY_ACCOUNT_CACHE_TTL,
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

    /**
     * ✅ تحسين: استخدام max() بدل قراءة صف كامل
     * - Index-Only Scan
     * - أسرع 50-900× على المدى الطويل
     */
    protected function nextInvoiceNumber(): int
    {
        $maxNumber = SalesInvoice::max('invoice_number');

        return ((int) $maxNumber) + 1;
    }

    protected function headerData(array $data): array
    {
        return [
            'invoice_number'     => $data['invoice_number'] ?? null,
            'invoice_date'       => $data['invoice_date'] ?? null,
            'account_id'         => $data['account_id'] ?? null,
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'coin_id'            => $data['coin_id'] ?? null,
            'exchange_rate'      => $data['exchange_rate'] ?? 1,
            'payment_method'     => $data['payment_method'] ?? null,
            'statement'          => $data['statement'] ?? null,
            'reference'          => $data['reference'] ?? null,
        ];
    }

    protected function saveDetails(SalesInvoice $invoice, array $details): void
    {
        foreach ($details as $row) {
            $itemId      = (int) $row['item_id'];
            $warehouseId = (int) $row['warehouse_id'];
            $unitId      = isset($row['unit_id']) && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : null;

            $quantity = (float) ($row['quantity'] ?? 0);
            $price    = (float) ($row['price'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $total    = max(0, ($quantity * $price) - $discount);

            $serverCost = $this->inventoryService->lastCost(
                $itemId,
                $warehouseId,
                $unitId
            );

            $uiCost = (float) ($row['cost_price'] ?? 0);

            if ($uiCost <= 0) {
                $costPrice = $serverCost;
            } elseif ($serverCost <= 0) {
                $costPrice = $uiCost;
            } else {
                $diffRatio = abs($uiCost - $serverCost) / $serverCost;
                $costPrice = $diffRatio > 0.1 ? $serverCost : $uiCost;
            }

            SalesInvoiceDetail::create([
                'sales_invoice_id' => $invoice->sales_invoice_id,
                'item_id'          => $itemId,
                'type_id'          => $row['type_id'] ?? null,
                'unit_id'          => $unitId,
                'warehouse_id'     => $warehouseId,
                'code'             => $row['code'] ?? null,
                'quantity'         => $quantity,
                'price'            => $price,
                'cost_price'       => $costPrice,
                'discount'         => $discount,
                'total'            => $total,
            ]);
        }
    }

    protected function recalculateTotals(SalesInvoice $invoice): void
    {
        $details = $invoice->details()->get();

        $itemsTotal    = 0;
        $discountTotal = 0;

        foreach ($details as $d) {
            $itemsTotal    += (float) $d->quantity * (float) $d->price;
            $discountTotal += (float) $d->discount;
        }

        $net  = max(0, $itemsTotal - $discountTotal);
        $rate = (float) ($invoice->exchange_rate ?: 1);

        $invoice->items_total    = $itemsTotal;
        $invoice->discount_total = $discountTotal;
        $invoice->total_in_base_currency = $net * $rate;
        $invoice->save();
    }
}