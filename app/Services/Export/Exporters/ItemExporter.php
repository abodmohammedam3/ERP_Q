<?php

namespace App\Services\Export\Exporters;

use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Builder;

class ItemExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'items';
    }

    public function getTableName(): string
    {
        return 'Items';
    }

    public function getModelClass(): string
    {
        return Item::class;
    }

    public function getColumns(): array
    {
        return [
            'itemID',
            'itemName2',
            'is_active',
        ];
    }

    protected function applyFilters(Builder $query, array $filters = []): void
    {
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (int) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $query->where('itemName2', 'like', '%' . $filters['search'] . '%');
        }
    }
}
