<?php

namespace App\Services\Export\Exporters;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class CustomerExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'customers';
    }

    public function getTableName(): string
    {
        return 'customers';
    }

    public function getModelClass(): string
    {
        return Customer::class;
    }

    public function getColumns(): array
    {
        return [
            'CustomersID',
            'accountID',
            'CustomersName2',
            'CusPhone',
            'CusAddress',
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
                $q->where('CustomersName2', 'like', $search)
                  ->orWhere('CusPhone', 'like', $search)
                  ->orWhere('CusAddress', 'like', $search);
            });
        }
    }
}
