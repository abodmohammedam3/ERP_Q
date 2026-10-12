<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Item;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Purchases\PurchaseReturnDetail;
use App\Models\Sales\SalesInvoiceDetail;
use App\Models\Sales\SalesReturnDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * خدمة الأصناف — مطابقة لنمط الوحدتين المرجعيتين (UnitService / TypeService).
 *
 * القواعد المطبقة: Cache::lock + DB::transaction + RuntimeException + Log::error
 * منع الحذف الناعم: صنف مستخدم في أي مستند/حركة لا يُحذف، يُعطَّل بدل ذلك.
 *
 * قرار المستخدم: الإبقاء على unique على itemName2 (لا يُسمح بتكرار الأسماء).
 */
class ItemService
{
    private const LOCK_KEY = 'item_write_lock';
    private const LOCK_TTL = 10;
    private const PAGE_SIZE = 100;

    /** الجداول التي تستهلك الصنف (فحص ما قبل الحذف) */
    private const USAGE_TABLES = [
        PurchaseInvoiceDetail::class    => 'فواتير الشراء',
        PurchaseReturnDetail::class     => 'مردودات المشتريات',
        SalesInvoiceDetail::class       => 'فواتير البيع',
        SalesReturnDetail::class        => 'مردودات المبيعات',
        InventoryMovementDetail::class  => 'حركات المخزون',
    ];

    // =========================================================
    // READ (قوائم — بلا كتابة، لا حاجة لقفل)
    // =========================================================

    /**
     * قائمة الأصناف: بحث Prefix + Pagination + limit 100.
     */
    public function list(?string $search, int $page = 1): LengthAwarePaginator
    {
        $query = Item::query()
            ->orderBy('itemID', 'asc');

        if ($search !== null && $search !== '') {
            $query->where('itemName2', 'like', "{$search}%");
        }

        return $query->paginate(self::PAGE_SIZE, ['*'], 'page', max($page, 1));
    }

    /**
     * القائمة الكاملة (بدون فلاتر) — تستخدمها الطباعة والتصدير.
     */
    public function allForView()
    {
        return Item::orderBy('itemID', 'asc')->get();
    }

    // =========================================================
    // WRITE
    // =========================================================

    public function store(array $data): Item
    {
        return $this->withWriteLock(function () use ($data) {
            return DB::transaction(function () use ($data) {
                $item = Item::create([
                    'itemName2'  => $data['itemName2'],
                    'is_active'  => 1,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                Log::info('Item created', ['id' => $item->itemID, 'name' => $item->itemName2]);

                return $item;
            });
        });
    }

    public function update(int $id, array $data): Item
    {
        return $this->withWriteLock(function () use ($id, $data) {
            return DB::transaction(function () use ($id, $data) {
                $item = Item::lockForUpdate()->findOrFail($id);

                $item->update([
                    'itemName2'  => $data['itemName2'],
                    'updated_by' => auth()->id(),
                ]);

                return $item;
            });
        });
    }

    /**
     * حذف مع منع ناعم: صنف مستخدم في مستندات/حركات لا يُحذف.
     *
     * @throws \RuntimeException برسالة عربية مفهومة (422 في الـ Controller)
     */
    public function delete(int $id): void
    {
        $this->withWriteLock(function () use ($id) {
            DB::transaction(function () use ($id) {
                $item = Item::lockForUpdate()->findOrFail($id);

                foreach (self::USAGE_TABLES as $modelClass => $label) {
                    $inUse = $modelClass::query()->where('item_id', $id)->exists();

                    if ($inUse) {
                        throw new \RuntimeException(
                            "لا يمكن حذف الصنف \"{$item->itemName2}\" لأنه مستخدم في {$label}. "
                            . 'يمكنك تعطيله بدلاً من ذلك.'
                        );
                    }
                }

                $item->delete();

                Log::info('Item deleted', ['id' => $id]);
            });
        });
    }

    public function toggleStatus(int $id): Item
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $item = Item::lockForUpdate()->findOrFail($id);

                $item->update([
                    'is_active'  => ! $item->is_active,
                    'updated_by' => auth()->id(),
                ]);

                return $item;
            });
        });
    }

    // =========================================================
    // WRITE LOCK (نمط الوحدة المرجعية)
    // =========================================================

    private function withWriteLock(callable $callback)
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);

        if (! $lock->get()) {
            throw new \RuntimeException('عملية أخرى على الأصناف قيد التنفيذ، حاول بعد قليل');
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Item operation failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
