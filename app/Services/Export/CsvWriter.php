<?php

namespace App\Services\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvWriter
{
    public const BOM = "\xEF\xBB\xBF";

    /**
     * Stream CSV output with UTF-8 BOM.
     *
     * @param string $filename
     * @param array $headers
     * @param iterable $rows
     * @return StreamedResponse
     */
    public function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');

            // BR-B1-1: Write UTF-8 BOM for proper Arabic display in Excel
            fwrite($output, self::BOM);

            // Write Header row
            fputcsv($output, $headers);

            // Stream data rows
            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
