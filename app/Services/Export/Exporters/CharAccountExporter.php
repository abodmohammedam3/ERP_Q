<?php

namespace App\Services\Export\Exporters;

use App\Models\Accounting\CharAccount;
use Illuminate\Database\Eloquent\Builder;

class CharAccountExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'characcount';
    }

    public function getTableName(): string
    {
        return 'characcount';
    }

    public function getModelClass(): string
    {
        return CharAccount::class;
    }

    public function getColumns(): array
    {
        return [
            'accountID',
            'accCode',
            'accName',
            'accParent',
            'accLevel',
            'accTypeID',
            'nature',
            'isPostable',
            'IsActive',
            'is_system',
            'system_key',
        ];
    }

    /**
     * BR-B1-3: characcount ordered by accLevel ASC.
     */
    protected function applySorting(Builder $query): void
    {
        $query->orderBy('accLevel', 'asc')->orderBy('accCode', 'asc');
    }

    protected function applyFilters(Builder $query, array $filters = []): void
    {
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('IsActive', (int) $filters['is_active']);
        }

        if (isset($filters['IsActive']) && $filters['IsActive'] !== '') {
            $query->where('IsActive', (int) $filters['IsActive']);
        }

        if (isset($filters['isPostable']) && $filters['isPostable'] !== '') {
            $query->where('isPostable', (int) $filters['isPostable']);
        }

        if (isset($filters['accLevel']) && $filters['accLevel'] !== '') {
            $query->where('accLevel', (int) $filters['accLevel']);
        }

        if (isset($filters['accParent']) && $filters['accParent'] !== '') {
            $query->where('accParent', (int) $filters['accParent']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('accCode', 'like', $search)
                  ->orWhere('accName', 'like', $search);
            });
        }
    }
}
