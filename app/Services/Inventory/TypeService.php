<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Type;
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
 * خدمة أنواع الأصناف — مطابقة لنمط الوحدة المرجعية (UnitService).
 *
 * القواعد المطبقة: Cache::lock + DB::transaction + RuntimeException + Log::error
 * منع الحذف الناعم: نوع مستخدم في أي مستند/حركة لا يُحذف، يُعطَّل بدل ذلك.
 */
class TypeService
{
    private const LOCK_KEY = 'type_write_lock';
    private const LOCK_TTL = 10;
    private const PAGE_SIZE = 100;

    /** الجداول التي تستهلك النوع (فحص ما قبل الحذف) */
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
     * قائمة الأنواع: بحث Prefix + Pagination + limit 100.
     */
    public function list(?string $search, int $page = 1): LengthAwarePaginator
    {
        $query = Type::query()
            ->orderBy('id', 'asc');

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "{$search}%");
        }

        return $query->paginate(self::PAGE_SIZE, ['*'], 'page', max($page, 1));
    }

    /**
     * القائمة الكاملة (بدون فلاتر) — تستخدمها الطباعة والتصدير.
     */
    public function allForView()
    {
        return Type::orderBy('id', 'asc')->get();
    }

    // =========================================================
    // WRITE
    // =========================================================

    public function store(array $data): Type
    {
        return $this->withWriteLock(function () use ($data) {
            return DB::transaction(function () use ($data) {
                $type = Type::create([
                    'name'       => $data['name'],
                    'code'       => $data['code'] ?? null,
                    'is_active'  => 1,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                Log::info('Type created', ['id' => $type->id, 'name' => $type->name]);

                return $type;
            });
        });
    }

    public function update(int $id, array $data): Type
    {
        return $this->withWriteLock(function () use ($id, $data) {
            return DB::transaction(function () use ($id, $data) {
                $type = Type::lockForUpdate()->findOrFail($id);

                $type->update([
                    'name'       => $data['name'],
                    'code'       => $data['code'] ?? null,
                    'updated_by' => auth()->id(),
                ]);

                return $type;
            });
        });
    }

    /**
     * حذف مع منع ناعم: نوع مستخدم في مستندات/حركات لا يُحذف.
     *
     * @throws \RuntimeException برسالة عربية مفهومة (422 في الـ Controller)
     */
    public function delete(int $id): void
    {
        $this->withWriteLock(function () use ($id) {
            DB::transaction(function () use ($id) {
                $type = Type::lockForUpdate()->findOrFail($id);

                foreach (self::USAGE_TABLES as $modelClass => $label) {
                    $inUse = $modelClass::query()->where('type_id', $id)->exists();

                    if ($inUse) {
                        throw new \RuntimeException(
                            "لا يمكن حذف النوع \"{$type->name}\" لأنه مستخدم في {$label}. "
                            . 'يمكنك تعطيله بدلاً من ذلك.'
                        );
                    }
                }

                $type->delete();

                Log::info('Type deleted', ['id' => $id]);
            });
        });
    }

    public function toggleStatus(int $id): Type
    {
        return $this->withWriteLock(function () use ($id) {
            return DB::transaction(function () use ($id) {
                $type = Type::lockForUpdate()->findOrFail($id);

                $type->update([
                    'is_active'  => ! $type->is_active,
                    'updated_by' => auth()->id(),
                ]);

                return $type;
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
            throw new \RuntimeException('عملية أخرى على الأنواع قيد التنفيذ، حاول بعد قليل');
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Type operation failed', [
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
