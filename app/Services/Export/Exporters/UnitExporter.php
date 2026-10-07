<?php

namespace App\Services\Export\Exporters;

use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Builder;

class UnitExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'units';
    }

    public function getTableName(): string
    {
        return 'units';
    }

    public function getModelClass(): string
    {
        return Unit::class;
    }

    public function getColumns(): array
    {
        return [
            'UnitID',
            'UnitName',
            'is_active',
        ];
    }

    protected function applyFilters(Builder $query, array $filters = []): void
    {
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (int) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $query->where('UnitName', 'like', '%' . $filters['search'] . '%');
        }
    }
}
