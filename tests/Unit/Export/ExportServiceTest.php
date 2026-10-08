<?php

namespace Tests\Unit\Export;

use App\Services\Export\CsvWriter;
use App\Services\Export\ExportService;
use App\Services\Export\Exporters\CharAccountExporter;
use App\Services\Export\Exporters\UnitExporter;
use InvalidArgumentException;
use Tests\TestCase;

class ExportServiceTest extends TestCase
{
    protected ExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportService = new ExportService(new CsvWriter());
    }

    public function test_resolves_all_ten_supported_exporters_and_aliases(): void
    {
        $entities = ['units', 'type', 'coins', 'characcount', 'boxes', 'banks', 'stocks', 'items', 'customers', 'suppliers'];

        foreach ($entities as $entity) {
            $exporter = $this->exportService->getExporter($entity);
            $this->assertNotNull($exporter);
        }

        // Test aliases
        $this->assertInstanceOf(UnitExporter::class, $this->exportService->getExporter('unit'));
        $this->assertInstanceOf(CharAccountExporter::class, $this->exportService->getExporter('characcounts'));
    }

    public function test_throws_exception_on_unknown_entity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown export entity: unknown_entity');

        $this->exportService->getExporter('unknown_entity');
    }
}
