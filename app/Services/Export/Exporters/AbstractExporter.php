<?php

namespace App\Services\Export\Exporters;

use Illuminate\Database\Eloquent\Builder;
use Generator;

abstract class AbstractExporter
{
    public const MAX_ROWS = 50000;

    /**
     * Get entity key identifier.
     */
    abstract public function getEntityKey(): string;

    /**
     * Get database table name.
     */
    abstract public function getTableName(): string;

    /**
     * Get model class FQCN.
     */
    abstract public function getModelClass(): string;

    /**
     * Get columns to export.
     */
    abstract public function getColumns(): array;

    /**
     * Build base query with filters and default sorting.
     */
    public function buildQuery(array $filters = []): Builder
    {
        $modelClass = $this->getModelClass();
        /** @var Builder $query */
        $query = $modelClass::query();

        $this->applyFilters($query, $filters);
        $this->applySorting($query);

        return $query;
    }

    /**
     * Apply entity-specific filters to the query.
     */
    abstract protected function applyFilters(Builder $query, array $filters = []): void;

    /**
     * Apply sorting to query (default by primary key).
     */
    protected function applySorting(Builder $query): void
    {
        $model = new ($this->getModelClass())();
        $query->orderBy($model->getKeyName(), 'asc');
    }

    /**
     * Count total rows matching filters.
     */
    public function count(array $filters = []): int
    {
        return $this->buildQuery($filters)->count();
    }

    /**
     * Get streamed rows via generator (cursor to avoid full memory load).
     */
    public function getRows(array $filters = []): Generator
    {
        $columns = $this->getColumns();

        foreach ($this->buildQuery($filters)->cursor() as $record) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = $record->{$column} ?? '';
            }
            yield $row;
        }
    }
}
