<?php

namespace App\Services\Dashboard;

use App\Models\Customer;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\Type;
use App\Models\Inventory\Unit;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Sales\SalesInvoice;
use App\Models\Supplier;
use App\Support\DashboardCache;
use Illuminate\Support\Facades\DB;

/**
 * مصدر البيانات الوحيد للوحة المعلومات.
 *
 * القواعد المعتمدة في هذا الملف (كلها مبنية على الـ Schema الفعلي):
 *
 *  1) العملة: الإجماليات تُحسب بالعملة الأساسية.
 *     - sales_invoices.total_in_base_currency كان nullable عند إضافته
 *       (2026_10_12_155543 ... ->nullable())، والفواتير الأقدم منه قد تحمل NULL.
 *     - purchase_invoices.total_in_base_currency أُضيف لاحقًا (2026_09_17).
 *     - لذلك نستخدم SUM(COALESCE(عمود, (items_total - discount_total) * exchange_rate))
 *       وليس COALESCE(SUM(عمود), 0) فقط — لأن SUM يتجاهل NULL بصمت.
 *
 *  2) الإجماليات تُطابق معادلة recalculateTotals في الـ Services:
 *       net = max(0, items_total - discount_total)
 *       total_in_base_currency = net * exchange_rate
 *     (SalesInvoiceService:737-742 / PurchaseInvoiceService:649-656)
 *
 *  3) client_id إلغاء: لا نعتمد على cache أبدًا كمصدر تحديث — كل دالة
 *     تُغلَّف بـ DashboardCache::remember() بمفتاح يحمل الإصدار، والإصدار
 *     يُزاد من FlushDashboardCache عند أي كتابة.
 *
 *  4) الأداء: لا LIMIT في SQL للحركات (لا joins على جداول كبيرة) —
 *     نقرأ آخر N حركة من inventory_movements ثم نحضر تفاصيلها بـ whereIn.
 *
 * ملاحظة: أي تعديل على شكل الرد يجب أن يُوثَّق في docs/dashboard-contract.md
 * والتوافق مضمون باختبار tests/Feature/Dashboard/DashboardDataTest.php.
 */
class DashboardService
{
    /** عدد صفوف "آخر الحركات" في كل جدول */
    public const RECENT_LIMIT = 10;

    /** أقصى عدد صفوف للحركات في ملخص المخزون */
    public const STOCK_MOVE_LIMIT = 10;

    /** أنواع الحركة التي تُعدّ "توريد" (دخول) */
    private const SUPPLY_TYPES = [
        InventoryMovement::TYPE_SUPPLY,        // توريد مخزني يدوي
        InventoryMovement::TYPE_PURCHASE,      // توريد شراء
        InventoryMovement::TYPE_SALE_RETURN,   // مرتجع بيع يعود للمخزن
    ];

    /** أنواع الحركة التي تُعدّ "توزيع" (خروج) */
    private const DISTRIBUTION_TYPES = [
        InventoryMovement::TYPE_ISSUE,           // صرف مخزني يدوي
        InventoryMovement::TYPE_SALE,            // صرف بيع
        InventoryMovement::TYPE_PURCHASE_RETURN, // مرتجع شراء يخرج من المخزن
    ];

    // ════════
    //  الحِزمة الكاملة (طلب واحد من الواجهة)
    // ════════

    /**
     * كل ما تحتاجه الشاشة في استجابة واحدة.
     *
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        return [
            'indicators'   => $this->indicators(),
            'summary'      => $this->summary(),
            'supply'       => $this->supplyDistribution(),
            'distribution' => $this->distribution(),
            'inventory'    => $this->inventory(),
            'alerts'       => $this->alerts(),
            'version'      => DashboardCache::version(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ════════
    //  بطاقات المؤشرات (4 عدّادات)
    // ════════

    /**
     * @return array{sales_invoices:int,purchase_invoices:int,customers:int,suppliers:int}
     */
    public function indicators(): array
    {
        return DashboardCache::remember('indicators', function (): array {
            return [
                'sales_invoices'    => (int) SalesInvoice::query()->count(),
                'purchase_invoices' => (int) PurchaseInvoice::query()->count(),
                // is_active موجود في الجدولين بعد هجرتي 2026_09_27
                'customers'         => (int) Customer::query()->where('is_active', 1)->count(),
                'suppliers'         => (int) Supplier::query()->where('is_active', 1)->count(),
            ];
        });
    }

    // ════════
    //  ملخص الإجماليات (3 بطاقات)
    // ════════

    /**
     * @return array{sales_total:float,purchase_total:float,stock_movements:int}
     */
    public function summary(): array
    {
        return DashboardCache::remember('summary', function (): array {
            return [
                'sales_total'      => $this->salesGrandTotal(),
                'purchase_total'   => $this->purchaseGrandTotal(),
                'stock_movements'  => (int) InventoryMovement::query()->count(),
            ];
        });
    }

    /**
     * إجمالي المبيعات بالعملة الأساسية.
     *
     * COALESCE على مستوى الصف ثم SUM — لأن العمود nullable.
     */
    private function salesGrandTotal(): float
    {
        $row = DB::table('sales_invoices')
            ->selectRaw(
                'COALESCE(SUM(COALESCE(total_in_base_currency,
                    (items_total - discount_total) * COALESCE(exchange_rate, 1))), 0) AS value'
            )
            ->first();

        return round((float) ($row->value ?? 0), 2);
    }

    /**
     * إجمالي المشتريات بالعملة الأساسية.
     */
    private function purchaseGrandTotal(): float
    {
        $row = DB::table('purchase_invoices')
            ->selectRaw(
                'COALESCE(SUM(COALESCE(total_in_base_currency,
                    (items_total - discount_total) * COALESCE(exchange_rate, 1))), 0) AS value'
            )
            ->first();

        return round((float) ($row->value ?? 0), 2);
    }

    // ════════
    //  جدول التوريد (دخول للمخزن)
    // ════════

    /**
     * آخر حركات التوريد مع تفاصيلها.
     *
     * @return array<int,array<string,mixed>>
     */
    public function supplyDistribution(): array
    {
        return DashboardCache::remember(
            'supply',
            fn (): array => $this->movementRows(self::SUPPLY_TYPES)
        );
    }

    /**
     * آخر حركات التوزيع/الصرف.
     *
     * @return array<int,array<string,mixed>>
     */
    public function distribution(): array
    {
        return DashboardCache::remember(
            'distribution',
            fn (): array => $this->movementRows(self::DISTRIBUTION_TYPES)
        );
    }

    /**
     * صفوف الحركات + تفاصيلها (صف واحد لكل سطر حركة، مع بيانات الرأس).
     *
     * مقصود ألا نُعيد "رأس الحركة" فقط: جدول الشاشة يعرض (صنف/نوع/وحدة/كمية/سعر)
     * وهذه كلها في inventory_movement_details لا في الرأس.
     *
     * @param  array<int,string> $types
     * @return array<int,array<string,mixed>>
     */
    private function movementRows(array $types): array
    {
        $movements = InventoryMovement::query()
            ->whereIn('movement_type', $types)
            ->orderByDesc('movement_date')
            ->orderByDesc('movement_id')
            ->limit(self::RECENT_LIMIT)
            ->get(['movement_id', 'display_id', 'movement_type', 'direction',
                   'movement_date', 'statement', 'source_type', 'source_id', 'total']);

        if ($movements->isEmpty()) {
            return [];
        }

        $details = InventoryMovementDetail::query()
            ->whereIn('movement_id', $movements->pluck('movement_id')->all())
            ->with([
                'item:itemID,itemName2',
                'type:id,name',
                'unit:UnitID,UnitName',
            ])
            ->orderBy('movement_detail_id')
            ->get();

        // ربط أسماء الموردين بالحسابات (purchase_invoices.account_id → characcount)
        $supplierByAccount = $this->supplierNamesByInvoice(
            $movements->pluck('source_id', 'movement_id')
                ->filter()
                ->all(),
            $movements->pluck('source_type', 'movement_id')->all()
        );

        $rows = [];

        foreach ($movements as $movement) {
            $movementDetails = $details->where('movement_id', $movement->movement_id);

            // حركة بلا تفاصيل (نادرة): صف واحد يعرض الرأس فقط
            if ($movementDetails->isEmpty()) {
                $rows[] = $this->row(
                    $movement,
                    null,
                    $supplierByAccount[$movement->movement_id] ?? ''
                );

                continue;
            }

            foreach ($movementDetails as $detail) {
                $rows[] = $this->row(
                    $movement,
                    $detail,
                    $supplierByAccount[$movement->movement_id] ?? ''
                );
            }

            // كسر التكرار المفرط: لا نُعيد أكثر من RECENT_LIMIT صفًا
            if (count($rows) >= self::RECENT_LIMIT) {
                break;
            }
        }

        return array_slice($rows, 0, self::RECENT_LIMIT);
    }

    /**
     * صف واحد موحّد للجدولين (نفس أسماء الحقول في JSON).
     *
     * @param  InventoryMovementDetail|null $detail
     * @return array<string,mixed>
     */
    private function row(InventoryMovement $movement, ?InventoryMovementDetail $detail, string $supplierName): array
    {
        return [
            'movement_id'   => (int) $movement->movement_id,
            'display_id'    => (string) ($movement->display_id ?? ''),
            'movement_type' => (string) $movement->movement_type,
            'type_label'    => InventoryMovement::labelForType($movement->movement_type),
            'direction'     => (string) $movement->direction,
            'date'          => optional($movement->movement_date)->format('Y-m-d'),
            'day_name'      => $this->dayName($movement->movement_date),
            'statement'     => (string) ($movement->statement ?? ''),
            'supplier_name' => $supplierName,
            'item_name'     => (string) ($detail->item->itemName2 ?? ''),
            'type_name'     => (string) ($detail->type->name ?? ''),
            'unit_name'     => (string) ($detail->unit->UnitName ?? ''),
            'code'          => (string) ($detail->code ?? ''),
            'quantity'      => (float) ($detail->quantity ?? 0),
            'unit_cost'     => (float) ($detail->unit_cost ?? 0),
            'sale_price'    => (float) ($detail->sale_price ?? 0),
            'line_total'    => (float) ($detail->total ?? 0),
        ];
    }

    /**
     * أسماء الموردين المتعلقة بفواتير الشراء المرتبطة بالحركات.
     *
     * @param  array<int,int|string> $sourceIds
     * @param  array<int,string|null> $sourceTypes
     * @return array<int,string> movement_id => supplier name
     */
    private function supplierNamesByInvoice(array $sourceIds, array $sourceTypes): array
    {
        if (empty($sourceIds)) {
            return [];
        }

        $invoiceIds = [];

        foreach ($sourceIds as $movementId => $sourceId) {
            if (($sourceTypes[$movementId] ?? null) === InventoryMovement::SOURCE_PURCHASE_INVOICE
                && (int) $sourceId > 0) {
                $invoiceIds[$movementId] = (int) $sourceId;
            }
        }

        if (empty($invoiceIds)) {
            return [];
        }

        // account_id → اسم الحساب (اسم المورد يُحفظ في characcount.accName)
        $accounts = PurchaseInvoice::query()
            ->whereIn('purchase_invoice_id', array_values($invoiceIds))
            ->with('supplierAccount:accountID,accName,accCode')
            ->get(['purchase_invoice_id', 'account_id'])
            ->pluck('supplierAccount.accName', 'purchase_invoice_id');

        $map = [];

        foreach ($invoiceIds as $movementId => $invoiceId) {
            $map[$movementId] = (string) ($accounts[$invoiceId] ?? '');
        }

        return $map;
    }

    private function dayName($date): string
    {
        if (!$date) {
            return '';
        }

        $days = [
            0 => 'الأحد',
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];

        return $days[(int) $date->dayOfWeek] ?? '';
    }

    // ════════
    //  لوحة المخزون (Inventory.blade.php — كانت معلّقة)
    // ════════

    /**
     * @return array{stocks:int,items:int,types:int,units:int,recent:array}
     */
    public function inventory(): array
    {
        return DashboardCache::remember('inventory', function (): array {
            return [
                // stocks/units ليس بهما is_active — فالعدّ كامل عن قصد
                'stocks' => (int) Stock::query()->count(),
                'items'  => (int) Item::query()->where('is_active', 1)->count(),
                'types'  => (int) Type::query()->count(),
                'units'  => (int) Unit::query()->count(),
                'recent' => $this->recentMovements(),
            ];
        });
    }

    /**
     * آخر حركات المخزون (بدون تصفية النوع).
     *
     * @return array<int,array<string,mixed>>
     */
    private function recentMovements(): array
    {
        $movements = InventoryMovement::query()
            ->orderByDesc('movement_date')
            ->orderByDesc('movement_id')
            ->limit(self::STOCK_MOVE_LIMIT)
            ->get(['movement_id', 'display_id', 'movement_type', 'movement_date', 'statement', 'total']);

        if ($movements->isEmpty()) {
            return [];
        }

        $details = InventoryMovementDetail::query()
            ->whereIn('movement_id', $movements->pluck('movement_id')->all())
            ->with(['item:itemID,itemName2'])
            ->orderBy('movement_detail_id')
            ->get()
            ->groupBy('movement_id');

        $rows = [];

        foreach ($movements as $movement) {
            $first = $details->get($movement->movement_id)?->first();

            $rows[] = [
                'movement_id'   => (int) $movement->movement_id,
                'display_id'    => (string) ($movement->display_id ?? ''),
                'movement_type' => (string) $movement->movement_type,
                'type_label'    => InventoryMovement::labelForType($movement->movement_type),
                'date'          => optional($movement->movement_date)->format('Y-m-d'),
                'statement'     => (string) ($movement->statement ?? ''),
                'item_name'     => (string) ($first->item->itemName2 ?? ''),
                'quantity'      => (float) ($first->quantity ?? 0),
                'unit_cost'     => (float) ($first->unit_cost ?? 0),
            ];
        }

        return $rows;
    }

    // ════════
    //  التنبيهات (SystemAlerts.blade.php — كانت معلّقة)
    // ════════

    /**
     * تنبيهات مبنية على بيانات موجودة فقط (لا جداول جديدة).
     *
     * القواعد:
     *  - فواتير غير مرحّلة (is_posted = 0) → عدد
     *  - حركات مخزون بلا بيان (statement فارغ) → عدد (جودة بيانات)
     *
     * @return array<int,array{level:string,text:string,count:int}>
     */
    public function alerts(): array
    {
        return DashboardCache::remember('alerts', function (): array {
            $alerts = [];

            $draftSales = (int) SalesInvoice::query()->where('is_posted', 0)->count();
            if ($draftSales > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'key'   => 'draft_sales',
                    'text'  => 'فواتير بيع غير مرحّلة تحتاج مراجعة',
                    'count' => $draftSales,
                ];
            }

            $draftPurchases = (int) PurchaseInvoice::query()->where('is_posted', 0)->count();
            if ($draftPurchases > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'key'   => 'draft_purchases',
                    'text'  => 'فواتير شراء غير مرحّلة تحتاج مراجعة',
                    'count' => $draftPurchases,
                ];
            }

            $blankStatements = (int) InventoryMovement::query()
                ->where(function ($q) {
                    $q->whereNull('statement')->orWhere('statement', '');
                })
                ->count();

            if ($blankStatements > 0) {
                $alerts[] = [
                    'level' => 'info',
                    'key'   => 'blank_statement',
                    'text'  => 'حركات مخزون بدون بيان',
                    'count' => $blankStatements,
                ];
            }

            return $alerts;
        });
    }
}
