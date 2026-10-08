<?php

namespace App\Services\Export\Exporters;

use App\Models\Inventory\Stock;
use Illuminate\Database\Eloquent\Builder;

class StockExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'stocks';
    }

    public function getTableName(): string
    {
        return 'stocks';
    }

    public function getModelClass(): string
    {
        return Stock::class;
    }

    public function getColumns(): array
    {
        return [
            'StockID',
            'StockName',
            'accountID',
            'is_active',
        ];
    }

    protected function applyFilters(Builder $query, array $filters = []): void
    {
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (int) $filters['is_active']);
        }

        if (isset($filters['accountID']) && $filters['accountID'] !== '') {
            $query->where('accountID', (int) $filters['accountID']);
        }

        if (!empty($filters['search'])) {
            $query->where('StockName', 'like', '%' . $filters['search'] . '%');
        }
    }
}
