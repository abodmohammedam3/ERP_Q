<?php

namespace App\Reports\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryMovementDetail;
use App\Models\Inventory\Item;
use App\Reports\Contracts\Report;
use Illuminate\Support\Facades\DB;

/**
 * تقرير حركة صنف (Item Ledger).
 *
 * كل حركات صنف واحد (أو الكل) خلال فترة، مع الكميات الداخلة/الخارجة
 * والقيمة — مبني على حركات المخزون وتفاصيلها.
 */
class ItemLedgerReport implements Report
{
    private const PER_PAGE = 50;

    public function key(): string
    {
        return 'item-ledger';
    }

    public function title(): string
    {
        return 'حركة صنف';
    }

    public function description(): string
    {
        return 'حركات المخزون لصنف واحد أو الكل مع الكميات والقيم';
    }

    public function icon(): string
    {
        return 'box-seam';
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function filters(): array
    {
        return [
            [
                'key'    => 'item_id',
                'label'  => 'الصنف',
                'type'   => 'item',
                'source' => 'items',
                'col'    => 'col-md-4',
            ],
            [
                'key'   => 'date_from',
                'label' => 'من تاريخ',
                'type'  => 'date',
                'col'   => 'col-md-3',
            ],
            [
                'key'   => 'date_to',
                'label' => 'إلى تاريخ',
                'type'  => 'date',
                'col'   => 'col-md-3',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'date',         'label' => 'التاريخ',      'type' => 'date'],
            ['key' => 'movement_no',  'label' => 'رقم الحركة',   'type' => 'text'],
            ['key' => 'item',         'label' => 'الصنف',        'type' => 'text'],
            ['key' => 'movement_type','label' => 'نوع الحركة',   'type' => 'text'],
            ['key' => 'statement',    'label' => 'البيان',       'type' => 'text'],
            ['key' => 'in_qty',       'label' => 'داخل',         'type' => 'number', 'footer' => 'sum'],
            ['key' => 'out_qty',      'label' => 'خارج',         'type' => 'number', 'footer' => 'sum'],
            ['key' => 'unit_cost',    'label' => 'التكلفة',      'type' => 'money'],
            ['key' => 'total',        'label' => 'القيمة',       'type' => 'money', 'footer' => 'sum'],
        ];
    }

    public function run(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $itemId = (int) ($filters['item_id'] ?? 0);

        $query = InventoryMovementDetail::query()
            ->join('inventory_movements as im', 'im.movement_id', '=', 'inventory_movement_details.movement_id')
            ->join('Items as it', 'it.itemID', '=', 'inventory_movement_details.item_id')
            ->select([
                'im.movement_id',
                'im.display_id',
                'im.movement_date',
                'im.movement_type',
                'im.direction',
                'im.statement',
                'it.itemID',
                'it.itemName2 as item_name',
                'inventory_movement_details.quantity',
                'inventory_movement_details.unit_cost',
                'inventory_movement_details.total',
            ]);

        if ($itemId > 0) {
            $query->where('inventory_movement_details.item_id', $itemId);
        }

        if (!empty($filters['date_from'])) {
            $query->where('im.movement_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('im.movement_date', '<=', $filters['date_to']);
        }

        // ── الإجماليات على كل النتائج المطابقة ──
        // نظّف الأعمدة قبل إضافة أعمدة SUM (وإلا خلط MySQL بينهما ورفض الاستعلام)
        $totalsRow = (clone $query)->toBase()
            ->cloneWithout(['columns'])
            ->selectRaw("COALESCE(SUM(CASE WHEN im.direction = 'in'  THEN inventory_movement_details.quantity ELSE 0 END), 0) as in_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN im.direction = 'out' THEN inventory_movement_details.quantity ELSE 0 END), 0) as out_qty")
            ->selectRaw('COALESCE(SUM(inventory_movement_details.total), 0) as total')
            ->first();

        $count = (clone $query)->count();

        // B1: الطباعة/التصدير (all=true) تتجاوز الترقيم — كل الصفوف حتى حدّ export_max_rows
        $perPage = !empty($filters['_all'])
            ? max(1, min($count, (int) config('reports.export_max_rows', 50000)))
            : self::PER_PAGE;

        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = min($page, $lastPage);

        $details = $query
            ->orderBy('im.movement_date')
            ->orderBy('im.movement_id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $typeLabels = [
            InventoryMovement::TYPE_SUPPLY          => 'توريد',
            InventoryMovement::TYPE_ISSUE           => 'صرف',
            InventoryMovement::TYPE_PURCHASE        => 'شراء',
            InventoryMovement::TYPE_SALE            => 'بيع',
            InventoryMovement::TYPE_PURCHASE_RETURN => 'مرتجع شراء',
            InventoryMovement::TYPE_SALE_RETURN     => 'مرتجع بيع',
            InventoryMovement::TYPE_SORTING_OUT     => 'فرز (خروج)',
            InventoryMovement::TYPE_SORTING_IN      => 'فرز (دخول)',
        ];

        $rows = [];

        foreach ($details as $d) {
            $direction = $d->direction === 'in' ? 'in' : 'out';
            $qty = (float) $d->quantity;

            $rows[] = [
                'id'            => (int) $d->movement_id,
                'date'          => $d->movement_date
                    ? \Illuminate\Support\Carbon::parse($d->movement_date)->format('Y-m-d')
                    : '',
                'movement_no'   => $d->display_id ?? (string) $d->movement_id,
                'item'          => $d->item_name ?? '',
                'movement_type' => $typeLabels[$d->movement_type] ?? $d->movement_type,
                'statement'     => $d->statement ?? '',
                'in_qty'        => $direction === 'in' ? $qty : 0,
                'out_qty'       => $direction === 'out' ? $qty : 0,
                'unit_cost'     => (float) $d->unit_cost,
                'total'         => (float) $d->total,
            ];
        }

        return [
            'rows'   => $rows,
            'totals' => [
                'in_qty'  => (float) ($totalsRow->in_qty ?? 0),
                'out_qty' => (float) ($totalsRow->out_qty ?? 0),
                'total'   => (float) ($totalsRow->total ?? 0),
            ],
            'meta'   => [
                'pagination' => [
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'per_page'     => self::PER_PAGE,
                    'total'        => $count,
                ],
            ],
        ];
    }
}
