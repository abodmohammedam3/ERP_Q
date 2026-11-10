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
                $this->validateSortedBatchRows($originalInvoiceId, $details);

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

                $this->validateSortedBatchRows(
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
    // SORTED BATCHES (المرحلة 1 — عرض فقط)
    // =========================================================
    /**
     * ✅ دفعات الفرز المشتقة من فاتورة شراء محددة.
     *
     * الفرز يورّث مورد الدفعة (supplier_account_id) ودفعة الأصل
     * (lot_source_detail_id — انظر InventoryService::sortInventory س474-489)،
     * ولذلك نستطيع تتبّع سلسلة الوراثة حتى نصل إلى حركة
     * source_type = purchase_invoice و source_id = رقم الفاتورة المطلوبة.
     *
     * المتاح للعرض = رصيد المورد الفعلي لنفس (الصنف، المخزن، الوحدة الناتجة)
     * عبر availableQuantityForSupplier — نفس تعريف الرصيد المستخدم في الحفظ.
     *
     * ⚠️ هذه قراءة عرض فقط — لا تؤثر على أي مسار كتابة.
     */
    public function sortedBatchesForInvoice(PurchaseInvoice $invoice): array
    {
        $supplierId = (int) $invoice->account_id;
        $warehouseId = (int) $invoice->warehouse_id;

        if ($supplierId <= 0 || $warehouseId <= 0) {
            return [];
        }

        /* وحدات أسطر الفاتورة الأصلية (للاستبعاد من دفعات الفرز المعروضة) */
        $invoiceUnits = $invoice->details()
            ->select('item_id', 'unit_id')
            ->get()
            ->mapWithKeys(fn ($d) => [$d->item_id.'|'.($d->unit_id ?? 0) => true])
            ->all();

        /* كل دفعات الفرز (in) لنفس المورد والمخزن */
        $sortedRows = InventoryMovementDetail::query()
            ->join('inventory_movements as im', 'im.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('im.direction', 'in')
            ->where('im.source_type', InventoryMovement::SOURCE_SORTING)
            ->where('inventory_movement_details.supplier_account_id', $supplierId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->where('inventory_movement_details.quantity', '>', 0)
            ->orderBy('inventory_movement_details.movement_detail_id')
            ->select([
                'inventory_movement_details.movement_detail_id',
                'inventory_movement_details.item_id',
                'inventory_movement_details.unit_id',
                'inventory_movement_details.quantity',
                'inventory_movement_details.unit_cost',
                'inventory_movement_details.lot_source_detail_id',
                'im.document_number',
            ])
            ->get();

        $batches = [];
        $candidates = [];
        $candidateItemIds = [];
        $candidateUnitIds = [];

        foreach ($sortedRows as $row) {
            /* استبعاد الصفوف بنفس وحدة سطر الفاتورة — تلك تُعرض ضمن الصف الأساسي */
            $key = $row->item_id.'|'.($row->unit_id ?? 0);
            if (isset($invoiceUnits[$key])) {
                continue;
            }

            /* تتبّع سلسلة الوراثة حتى حركة الشراء — بعمق محدود حمايةً من الحلقات */
            if (!$this->traceLotToInvoice($row->lot_source_detail_id, (int) $invoice->purchase_invoice_id)) {
                continue;
            }

            $candidateItemIds[$row->item_id] = true;
            $candidateUnitIds[(int) $row->unit_id] = true;

            $candidates[] = $row;
        }

        if (empty($candidates)) {
            return [];
        }

        /* أسماء الأصناف والوحدات باستعلامين مجمّعين (تفادي N+1) */
        $itemNames = \App\Models\Inventory\Item::query()
            ->whereIn('itemID', array_keys($candidateItemIds))
            ->pluck('itemName2', 'itemID');

        $unitNames = \App\Models\Inventory\Unit::query()
            ->whereIn('UnitID', array_keys(array_filter($candidateUnitIds, fn ($id) => $id > 0)))
            ->pluck('UnitName', 'UnitID');

        $batches = [];

        foreach ($candidates as $row) {
            $available = $this->inventoryService->availableQuantityForSupplier(
                (int) $row->item_id,
                $warehouseId,
                $row->unit_id !== null ? (int) $row->unit_id : null,
                $supplierId
            );

            if ($available <= 0) {
                continue;
            }

            $batches[$row->movement_detail_id] = [
                'item_id'          => (int) $row->item_id,
                'item_name'        => (string) ($itemNames[$row->item_id] ?? ''),
                'unit_id'          => $row->unit_id !== null ? (int) $row->unit_id : null,
                'unit_name'        => (string) ($unitNames[$row->unit_id] ?? ''),
                'sort_document'    => (string) $row->document_number,
                'sort_batch_qty'   => (float) $row->quantity,
                'unit_cost'        => (float) $row->unit_cost,
                'available'        => (float) $available,
            ];
        }

        return array_values($batches);
    }

    /**
     * مشي سلسلة lot_source_detail_id حتى نصل لدفع أصلها من فاتورة الشراء.
     * عمق 5 يكفي عمليًا (فرز متعدد المستويات نادر) ويحمي من الحلقات اللانهائية.
     */
    protected function traceLotToInvoice(?int $lotSourceDetailId, int $invoiceId, int $depth = 0): bool
    {
        if (!$lotSourceDetailId || $depth >= 5) {
            return false;
        }

        $ancestor = InventoryMovementDetail::query()
            ->join('inventory_movements as im', 'im.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('inventory_movement_details.movement_detail_id', $lotSourceDetailId)
            ->select([
                'inventory_movement_details.lot_source_detail_id',
                'im.source_type',
                'im.source_id',
            ])
            ->first();

        if (!$ancestor) {
            return false;
        }

        if ($ancestor->source_type === InventoryMovement::SOURCE_PURCHASE_INVOICE
            && (int) $ancestor->source_id === $invoiceId) {
            return true;
        }

        return $this->traceLotToInvoice($ancestor->lot_source_detail_id, $invoiceId, $depth + 1);
    }

    // =========================================================
    // AVAILABLE FOR RETURN
    // الحد الفعلي = الأصغر بين (كمية سطر الفاتورة − المرتجعات)
    // و(الرصيد الفعلي في مخزن الفاتورة) — حتى لا تعرض الشاشة
    // كمية فاتورة مُصرف منها بالبيع/الفرز/مرتجعات شراء سابقة.
    // المرتجعات بوحدة مفرزة (مثل حبة) تُحتسب حصتها محوّلةً
    // إلى وحدة الفاتورة: qty × (دخل الفرز ÷ خروج الفرز).
    // =========================================================
    public function availableForReturn(int $invoiceDetailId, ?int $exceptReturnId = null): float
    {
        $invoiceDetail = PurchaseInvoiceDetail::find($invoiceDetailId);

        if (!$invoiceDetail) {
            return 0.0;
        }

        /* استهلاك الحصة: صفوف بوحدة الفاتورة تُحتسب كما هي،
           وصفوف بوحدة مفرزة تُحوَّل إلى مكافئ وحدة الفاتورة */
        $rows = PurchaseReturnDetail::where('purchase_invoice_detail_id', $invoiceDetailId)
            ->when($exceptReturnId, function ($query, $exceptId) {
                $query->where('purchase_return_id', '!=', $exceptId);
            })
            ->get(['quantity', 'unit_id']);

        $consumed = 0.0;

        foreach ($rows as $row) {
            $consumed += $this->quotaEquivalent($invoiceDetail, (int) $row->unit_id, (float) $row->quantity);
        }

        $invoiceLimit = max(0.0, (float) $invoiceDetail->quantity - $consumed);

        if ($invoiceLimit <= 0) {
            return 0.0;
        }

        /* ✅ قصّ الحد بالرصيد الفعلي — المصدر: مخزن رأس فاتورة الشراء
           (سطر الفاتورة لا يحمل مخزنًا؛ purchase_invoice_details بلا warehouse_id).
           وتمييزاً لكمية المرتجع المستثنى (exceptReturnId) الذي لا يزال
           مستندها قابل للحفظ — نفس منطق validateStockAvailability أدناه. */
        $warehouseId = optional($invoiceDetail->purchaseInvoice)->warehouse_id;

        if (!$warehouseId) {
            return $invoiceLimit;
        }

        $stock = $this->inventoryService->availableQuantity(
            (int) $invoiceDetail->item_id,
            (int) $warehouseId,
            $invoiceDetail->unit_id !== null ? (int) $invoiceDetail->unit_id : null
        );

        if ($exceptReturnId) {
            $currentReturnedQty = PurchaseReturnDetail::where('purchase_return_id', $exceptReturnId)
                ->where('item_id', $invoiceDetail->item_id)
                ->where('warehouse_id', (int) $warehouseId)
                ->when($invoiceDetail->unit_id !== null, function ($q) use ($invoiceDetail) {
                    $q->where('unit_id', $invoiceDetail->unit_id);
                })
                ->sum('quantity');

            $stock += (float) $currentReturnedQty;
        }

        return min($invoiceLimit, max(0.0, $stock));
    }

    /**
     * ✅ مكافئ كمية سطر مرتجع بوحدة الفاتورة (لحساب الحصة):
     *  - وحدة الفاتورة نفسها: القيمة كما هي.
     *  - وحدة مفرزة (من دفعة فرز مشتقة من هذه الفاتورة):
     *      qty × (كمية دخول الفرز ÷ كمية خروج الفرز)
     *    مثال: 5 كجم ← 20 حبة ⇒ الحبة = 0.25 كجم ⇒ إرجاع 10 حبات يستهلك 2.5 كجم من الحصة.
     */
    public function quotaEquivalent(PurchaseInvoiceDetail $invoiceDetail, int $rowUnitId, float $qty): float
    {
        $invoiceUnitId = $invoiceDetail->unit_id !== null ? (int) $invoiceDetail->unit_id : 0;

        if ($rowUnitId === $invoiceUnitId || $rowUnitId <= 0) {
            return $qty;
        }

        $ratio = $this->sortedBatchRatio($invoiceDetail, $rowUnitId);

        return $ratio !== null ? $qty * $ratio : $qty;
    }

    /**
     * نسبة التحويل (وحدة الفاتورة لكل وحدة مفرزة) لأحدث دفعة فرز
     * مشتقة من هذه الفاتورة لنفس (الصنف، المخزن، وحدة الناتج، المورد).
     * ratio = كمية الدخول الناتجة ÷ كمية الخروج المصدرية.
     */
    public function sortedBatchRatio(PurchaseInvoiceDetail $invoiceDetail, int $sortedUnitId): ?float
    {
        $invoice = $invoiceDetail->purchaseInvoice;

        if (!$invoice) {
            return null;
        }

        $warehouseId = (int) $invoice->warehouse_id;
        $supplierId = (int) $invoice->account_id;

        $sortedIns = InventoryMovementDetail::query()
            ->join('inventory_movements as im', 'im.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('im.direction', 'in')
            ->where('im.source_type', InventoryMovement::SOURCE_SORTING)
            ->where('inventory_movement_details.item_id', $invoiceDetail->item_id)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->where('inventory_movement_details.unit_id', $sortedUnitId)
            ->where('inventory_movement_details.supplier_account_id', $supplierId)
            ->where('inventory_movement_details.quantity', '>', 0)
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select([
                'inventory_movement_details.movement_detail_id',
                'inventory_movement_details.quantity',
                'inventory_movement_details.lot_source_detail_id',
                'im.source_id',
            ])
            ->limit(50)
            ->get();

        foreach ($sortedIns as $in) {
            if (!$this->traceLotToInvoice($in->lot_source_detail_id, (int) $invoice->purchase_invoice_id)) {
                continue;
            }

            /* الخروج المصدر لحركة الدخول هذه: source_id يشير إلى movement_id الخاص بالخروج */
            $outQty = InventoryMovementDetail::query()
                ->join('inventory_movements as im', 'im.movement_id', '=', 'inventory_movement_details.movement_id')
                ->where('inventory_movement_details.movement_id', (int) $in->source_id)
                ->where('im.movement_type', InventoryMovement::TYPE_SORTING_OUT)
                ->where('inventory_movement_details.quantity', '>', 0)
                ->value('inventory_movement_details.quantity');

            if ($outQty && (float) $outQty > 0) {
                return (float) $outQty / (float) $in->quantity;    // كجم لكل حبة
            }
        }

        return null;
    }

    /**
     * ✅ رصيد دفعة فرز محددة (الصنف، المخزن، وحدة الناتج، المورد) —
     * يستخدم للتحقق عند الإرجاع من صف فرز (المرحلة 2).
     */
    public function sortedBatchStock(int $itemId, int $warehouseId, ?int $unitId, ?int $supplierId): float
    {
        return $this->inventoryService->availableQuantityForSupplier(
            $itemId,
            $warehouseId,
            $unitId,
            $supplierId
        );
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

            /* ✅ صفوف دفعات الفرز (بوحدة غير وحدة الفاتورة) تُستبعد من الحصة الأساسية —
               تتحقق منها validateSortedBatchRows بالتحويل */
            $rowUnitId = isset($row['unit_id']) && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : null;

            if ($rowUnitId !== null) {
                $invoiceDetailForUnit = PurchaseInvoiceDetail::find($detailId);

                if ($invoiceDetailForUnit
                    && $invoiceDetailForUnit->unit_id !== null
                    && $rowUnitId !== (int) $invoiceDetailForUnit->unit_id) {
                    continue;
                }
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

    /**
     * ✅ التحقق من صفوف دفعات الفرز (المرحلة 2):
     *  1) الوحدة المرسلة يجب أن تكون دفعة فرز مشتقة من نفس الفاتورة
     *     (تتبّع lot_source حتى حركة الشراء) — وإلا يُرفض السطر.
     *  2) الكمية ≤ رصيد المورد الفعلي لهذه الدفعة (availableQuantityForSupplier).
     *  3) المكافئ المحوّل (qty × دخل÷خروج) لا يتجاوز الحصة المتبقية للفاتورة.
     */
    protected function validateSortedBatchRows(
        int $originalInvoiceId,
        array $details,
        ?int $exceptReturnId = null
    ): void {
        $errors = [];

        foreach ($details as $i => $row) {
            $detailId = (int) ($row['purchase_invoice_detail_id'] ?? 0);
            $qty      = (float) ($row['quantity'] ?? 0);
            $rowUnitId = isset($row['unit_id']) && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : null;

            if ($detailId <= 0 || $qty <= 0 || $rowUnitId === null) {
                continue;
            }

            $invoiceDetail = PurchaseInvoiceDetail::find($detailId);

            if (!$invoiceDetail || (int) $invoiceDetail->purchase_invoice_id !== $originalInvoiceId) {
                continue;   /* رُفض سابقاً في validateReturnQuantities */
            }

            $invoiceUnitId = $invoiceDetail->unit_id !== null ? (int) $invoiceDetail->unit_id : 0;

            if ($rowUnitId === $invoiceUnitId) {
                continue;   /* صف أساسي عادي — تحقق موجود سابقاً */
            }

            $invoice = $invoiceDetail->purchaseInvoice;

            if (!$invoice) {
                continue;
            }

            /* 1) يجب أن تكون دفعة فرز مشتقة من هذه الفاتورة */
            $ratio = $this->sortedBatchRatio($invoiceDetail, $rowUnitId);

            if ($ratio === null) {
                $errors["details.{$i}.unit_id"] =
                    "الوحدة المرسلة للسطر #{$detailId} ليست دفعة فرز مشتقة من هذه الفاتورة";
                continue;
            }

            /* 2) رصيد المورد الفعلي للدفعة المفرزة */
            $batchStock = $this->sortedBatchStock(
                (int) $invoiceDetail->item_id,
                (int) $invoice->warehouse_id,
                $rowUnitId,
                (int) $invoice->account_id
            );

            if ($qty > $batchStock) {
                $errors["details.{$i}.quantity"] =
                    "الكمية المطلوبة من دفعة الفرز ({$qty}) أكبر من الرصيد الفعلي ({$batchStock})";
                continue;
            }

            /* 3) المكافئ المحوّل لا يتجاوز الحصة المتبقية للفاتورة */
            $quotaEquivalent = $qty * $ratio;
            $invoiceRemaining = $this->availableForReturn(
                $detailId,
                $exceptReturnId
            );

            if ($quotaEquivalent > $invoiceRemaining + 0.000001) {
                $errors["details.{$i}.quantity"] =
                    "مكافئ الكمية ({$quotaEquivalent} من وحدة الفاتورة) أكبر من المتبقي للفاتورة ({$invoiceRemaining})";
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

            /* ✅ الوحدة من الصف (قد تكون دفعة فرز) وإلا وحدة الفاتورة */
            $rowUnitId = isset($row['unit_id']) && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : ($detail->unit_id ? (int) $detail->unit_id : null);

            $key = $detail->item_id . '|' . ($rowUnitId ?? 0);
            $pairs[$key] = [
                'item_id' => (int) $detail->item_id,
                'unit_id' => $rowUnitId,
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

            /* ✅ الوحدة من الصف (قد تكون دفعة فرز) — الرصيد يُفحص لكل وحدة على حدة */
            $rowUnitId = isset($row['unit_id']) && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : ($detail->unit_id ? (int) $detail->unit_id : null);

            $key = $detail->item_id . '|' . ($rowUnitId ?? 0);
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

            /* ✅ المرحلة 2: الصف قد يكون بوحدة دفعة فرز (unit_id مرسل من الشاشة)
               بعد اجتياز validateSortedBatchRows — وإلا تكون بوحدة الفاتورة كما سابقاً. */
            $rowUnitId = isset($row['unit_id']) && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : null;

            $invoiceUnitId = $invoiceDetail->unit_id !== null ? (int) $invoiceDetail->unit_id : 0;

            $isSortedRow = $rowUnitId !== null
                && $invoiceUnitId > 0
                && $rowUnitId !== $invoiceUnitId;

            $unitId = $isSortedRow ? $rowUnitId : $invoiceDetail->unit_id;

            if ($isSortedRow) {
                /* تكلفة سطر الفرز = تكلفة الوحدة الناتجة المحسوبة لحظة الفرز
                   (إجمالي قيمة المصدر ÷ كمية الناتج) — وأسعره منسوخ من حركة الفرز */
                $sortedCost = $this->getSortedBatchUnitCost(
                    (int) $invoiceDetail->item_id,
                    (int) $purchaseReturn->warehouse_id,
                    $rowUnitId,
                    (int) $purchaseReturn->account_id,
                    (int) $invoiceDetail->purchase_invoice_id
                );

                if ($sortedCost !== null) {
                    $unitCost = $sortedCost;
                } else {
                    $unitCost = $this->getLandedCostFromMovement($invoiceDetail);
                }
            } else {
                $unitCost = $this->getLandedCostFromMovement($invoiceDetail);
            }

            PurchaseReturnDetail::create([
                'purchase_return_id'         => $purchaseReturn->purchase_return_id,
                'purchase_invoice_detail_id' => $invoiceDetailId,
                'item_id'                    => $invoiceDetail->item_id,
                'type_id'                    => $invoiceDetail->type_id,
                'unit_id'                    => $unitId,
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
     * ✅ تكلفة الوحدة الناتجة من دفعة فرز مشتقة من فاتورة الشراء (المرحلة 2).
     */
    protected function getSortedBatchUnitCost(
        int $itemId,
        int $warehouseId,
        int $sortedUnitId,
        int $supplierId,
        int $invoiceId
    ): ?float {
        $sortedIns = InventoryMovementDetail::query()
            ->join('inventory_movements as im', 'im.movement_id', '=', 'inventory_movement_details.movement_id')
            ->where('im.direction', 'in')
            ->where('im.source_type', InventoryMovement::SOURCE_SORTING)
            ->where('inventory_movement_details.item_id', $itemId)
            ->where('inventory_movement_details.warehouse_id', $warehouseId)
            ->where('inventory_movement_details.unit_id', $sortedUnitId)
            ->where('inventory_movement_details.supplier_account_id', $supplierId)
            ->orderByDesc('inventory_movement_details.movement_detail_id')
            ->select([
                'inventory_movement_details.unit_cost',
                'inventory_movement_details.lot_source_detail_id',
            ])
            ->limit(50)
            ->get();

        foreach ($sortedIns as $in) {
            if ($this->traceLotToInvoice($in->lot_source_detail_id, $invoiceId)) {
                return (float) $in->unit_cost;
            }
        }

        return null;
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