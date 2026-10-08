<?php

namespace App\Services\Export\Exporters;

use App\Models\Inventory\Type;
use Illuminate\Database\Eloquent\Builder;

class TypeExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'type';
    }

    public function getTableName(): string
    {
        return 'type';
    }

    public function getModelClass(): string
    {
        return Type::class;
    }

    public function getColumns(): array
    {
        return [
            'id',
            'name',
            'code',
            'is_active',
        ];
    }

    protected function applyFilters(Builder $query, array $filters = []): void
    {
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (int) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search);
            });
        }
    }
}
