<?php

namespace App\Services\Export;

use App\Services\Export\Exporters\AbstractExporter;
use App\Services\Export\Exporters\UnitExporter;
use App\Services\Export\Exporters\TypeExporter;
use App\Services\Export\Exporters\CoinExporter;
use App\Services\Export\Exporters\CharAccountExporter;
use App\Services\Export\Exporters\BoxExporter;
use App\Services\Export\Exporters\BankExporter;
use App\Services\Export\Exporters\StockExporter;
use App\Services\Export\Exporters\ItemExporter;
use App\Services\Export\Exporters\CustomerExporter;
use App\Services\Export\Exporters\SupplierExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ExportService
{
    protected CsvWriter $csvWriter;

    /**
     * Registered exporters map.
     *
     * @var array<string, AbstractExporter>
     */
    protected array $exporters = [];

    /**
     * Singular/Plural alias map for entity resolution.
     *
     * @var array<string, string>
     */
    protected array $aliases = [
        'unit'         => 'units',
        'types'        => 'type',
        'coin'         => 'coins',
        'characcounts' => 'characcount',
        'box'          => 'boxes',
        'bank'         => 'banks',
        'stock'        => 'stocks',
        'item'         => 'items',
        'customer'     => 'customers',
        'supplier'     => 'suppliers',
    ];

    public function __construct(CsvWriter $csvWriter)
    {
        $this->csvWriter = $csvWriter;
        $this->registerExporters();
    }

    protected function registerExporters(): void
    {
        $exporterInstances = [
            new UnitExporter(),
            new TypeExporter(),
            new CoinExporter(),
            new CharAccountExporter(),
            new BoxExporter(),
            new BankExporter(),
            new StockExporter(),
            new ItemExporter(),
            new CustomerExporter(),
            new SupplierExporter(),
        ];

        foreach ($exporterInstances as $exporter) {
            $this->exporters[$exporter->getEntityKey()] = $exporter;
        }
    }

    public function getExporter(string $entity): AbstractExporter
    {
        $key = strtolower(trim($entity));
        if (isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        if (!isset($this->exporters[$key])) {
            throw new InvalidArgumentException("Unknown export entity: {$entity}");
        }

        return $this->exporters[$key];
    }

    /**
     * Export entity data to CSV StreamedResponse.
     *
     * @param string $entity
     * @param array $filters
     * @return StreamedResponse|JsonResponse
     */
    public function export(string $entity, array $filters = []): StreamedResponse|JsonResponse
    {
        $exporter = $this->getExporter($entity);

        // BR-B1-5: Max rows = 50,000 (config). Beyond -> refuse.
        $rowCount = $exporter->count($filters);
        if ($rowCount > AbstractExporter::MAX_ROWS) {
            return response()->json([
                'error'   => 'Row limit exceeded',
                'message' => "The requested export contains {$rowCount} rows, exceeding the maximum allowed limit of " . AbstractExporter::MAX_ROWS . ' rows.',
            ], 422);
        }

        // BR-B1-4: Filename: {table}_export_YYYYMMDD_HHmmss.csv
        $table = strtolower($exporter->getTableName());
        $filename = "{$table}_export_" . now()->format('Ymd_His') . '.csv';

        $headers = $exporter->getColumns();
        $rows = $exporter->getRows($filters);

        return $this->csvWriter->stream($filename, $headers, $rows);
    }
}
