<?php

namespace App\Services\Export\Exporters;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

class SupplierExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'suppliers';
    }

    public function getTableName(): string
    {
        return 'Suppliers';
    }

    public function getModelClass(): string
    {
        return Supplier::class;
    }

    public function getColumns(): array
    {
        return [
            'suplierID',
            'accountID',
            'supName',
            'supPhone',
            'supArea',
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
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('supName', 'like', $search)
                  ->orWhere('supPhone', 'like', $search)
                  ->orWhere('supArea', 'like', $search);
            });
        }
    }
}
