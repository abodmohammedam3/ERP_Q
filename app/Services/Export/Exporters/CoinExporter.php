<?php

namespace App\Services\Export\Exporters;

use App\Models\Accounting\Coin;
use Illuminate\Database\Eloquent\Builder;

class CoinExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'coins';
    }

    public function getTableName(): string
    {
        return 'coins';
    }

    public function getModelClass(): string
    {
        return Coin::class;
    }

    public function getColumns(): array
    {
        return [
            'coinsID',
            'coinsName',
            'coinsCode',
            'coinsExchangeRate',
            'coinsSystem',
            'is_active',
        ];
    }

    protected function applyFilters(Builder $query, array $filters = []): void
    {
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (int) $filters['is_active']);
        }

        if (isset($filters['coinsSystem']) && $filters['coinsSystem'] !== '') {
            $query->where('coinsSystem', (int) $filters['coinsSystem']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('coinsName', 'like', $search)
                  ->orWhere('coinsCode', 'like', $search);
            });
        }
    }
}
