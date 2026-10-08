<?php

namespace App\Services\Export\Exporters;

use App\Models\Accounting\Bank;
use Illuminate\Database\Eloquent\Builder;

class BankExporter extends AbstractExporter
{
    public function getEntityKey(): string
    {
        return 'banks';
    }

    public function getTableName(): string
    {
        return 'banks';
    }

    public function getModelClass(): string
    {
        return Bank::class;
    }

    public function getColumns(): array
    {
        return [
            'bankID',
            'bankName',
            'accountID',
            'coinsID',
            'accountNumber',
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
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('bankName', 'like', $search)
                  ->orWhere('accountNumber', 'like', $search);
            });
        }
    }
}
