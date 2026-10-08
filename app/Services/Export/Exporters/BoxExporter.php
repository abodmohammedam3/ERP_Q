<?php

namespace App\Services\Export\Exporters;

use App\Models\Accounting\Box;
use Illuminate\Database\Eloquent\Builder;

class BoxExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'boxes';
    }

    public function getTableName(): string
    {
        return 'boxes';
    }

    public function getModelClass(): string
    {
        return Box::class;
    }

    public function getColumns(): array
    {
        return [
            'boxID',
            'coinsID',
            'accountID',
            'boxName',
            'is_active',
        ];
    }

    protected function applyFilters(Builder $query, array $filters = []): void
    {
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (int) $filters['is_active']);
        }

        if (isset($filters['coinsID']) && $filters['coinsID'] !== '') {
            $query->where('coinsID', (int) $filters['coinsID']);
        }

        if (isset($filters['accountID']) && $filters['accountID'] !== '') {
            $query->where('accountID', (int) $filters['accountID']);
        }

        if (!empty($filters['search'])) {
            $query->where('boxName', 'like', '%' . $filters['search'] . '%');
        }
    }
}
