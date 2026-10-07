<?php

namespace Tests\Unit\Export;

use App\Services\Export\CsvWriter;
use Tests\TestCase;

class CsvWriterTest extends TestCase
{
    public function test_csv_writer_streams_content_with_bom_and_correct_headers(): void
    {
        $writer = new CsvWriter();

        $headers = ['UnitID', 'UnitName', 'is_active'];
        $rows = [
            [1, 'متر', 1],
            [2, 'كيلو', 0],
        ];

        $response = $writer->stream('test_export.csv', $headers, $rows);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Check BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Check Headers and data
        $this->assertStringContainsString('UnitID,UnitName,is_active', $content);
        $this->assertStringContainsString('1,متر,1', $content);
        $this->assertStringContainsString('2,كيلو,0', $content);
    }
}
