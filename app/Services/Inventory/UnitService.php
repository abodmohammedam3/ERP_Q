<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Unit;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Purchases\PurchaseInvoiceDetail;
use App\Models\Purchases\PurchaseReturnDetail;
use App\Models\Sales\SalesInvoiceDetail;
use App\Models\Sales\SalesReturnDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * خدمة وحدات القياس — نموذج الوحدة 1 (المعيار للوحدات القادمة).
 *
 * القواعد المطبقة: Cache::lock + DB::transaction + RuntimeException + Log::error
 * منع الحذف الناعم: وحدة مستخدمة في أي مستند/حركة لا تُحذف، تُعطَّل بدل ذلك.
 */
class UnitService
{
    private const LOCK_KEY = 'unit_write_lock';
    private const LOCK_TTL = 10;
    private const PAGE_SIZE = 100;

    /** الجداول التي تستهلك الوحدة (فحص ما قبل الحذف) */
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
     * قائمة الوحدات: بحث Prefix + Pagination + limit 100.
     */
    public function list(?string $search, int $page = 1): LengthAwarePaginator
    {
        $query = Unit::query()
            ->orderBy('UnitID', 'asc');

        if ($search !== null && $search !== '') {
            $query->where('UnitName', 'like', "{$search}%");
        }

        return $query->paginate(self::PAGE_SIZE, ['*'], 'page', max($page, 1));
    }

    /**
     * القائمة الكاملة (بدون فلاتر) — تستخدمها الطباعة والتصدير.
     */
    public function allForView()
    {
        return Unit::orderBy('UnitID', 'asc')->get();
    }

    // =========================================================
    // WRITE
    // =========================================================

    public function store(array $data): Unit
    {
        return $this->withWriteLock(function () use ($data) {
            return DB::transaction(function () use ($data) {
                $unit = Unit::create([
                    'UnitName'   => $data['UnitName'],
                    'is_active'  => 1,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                Log::info('Unit created', ['id' => $unit->UnitID, 'name' => $unit->UnitName]);

                return $unit;
            });
        });
    }

    public function update(int $id, array $data): Unit
    {
        return $this->withWriteLock(function () use ($id, $data) {
            return DB::transaction(function () use ($id, $data) {
                $unit = Unit::lockForUpdate()->findOrFail($id);

                $unit->update([
                    'UnitName'   => $data['UnitName'],
                    'updated_by' => auth()->id(),
                ]);

                return $unit;
            });
        });
    }

    /**
     * حذف مع منع ناعم: وحدة مستخدمة في مستندات/حركات لا تُحذف.
     *
     * @throws \RuntimeException برسالة عربية مفهومة (422 في الـ Controller)
     */
    public function delete(int $id): void
    {
        $this->withWriteLock(function () use ($id) {
            DB::transaction(function () use ($id) {
                $unit = Unit::lockForUpdate()->findOrFail($id);

                foreach (self::USAGE_TABLES as $modelClass => $label) {
                    $inUse = $modelClass::query()->where('unit_id', $id)->exists();

                    if ($inUse) {
                        throw new \RuntimeException(
                            "لا يمكن حذف الوحدة \"{$unit->UnitName}\" لأنها مستخدمة في {$label}. "
                            . 'يمكنك تعطيلها بدلاً من ذلك.'
                        );
                    }
                }

                $unit->delete();

                Log::info('Unit deleted', ['id' => $id]);
            });
        });
    }

    public function toggleStatus(int $id): Unit
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $unit = Unit::lockForUpdate()->findOrFail($id);

                $unit->update([
                    'is_active'  => ! $unit->is_active,
                    'updated_by' => auth()->id(),
                ]);

                return $unit;
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
            throw new \RuntimeException('عملية أخرى على الوحدات قيد التنفيذ، حاول بعد قليل');
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Unit operation failed', [
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
