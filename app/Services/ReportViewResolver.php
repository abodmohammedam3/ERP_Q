<?php

namespace App\Services;

use App\Reports\Contracts\Report;

class ReportViewResolver
{
    public function for(Report $report, array $meta): array
    {
        if ($report->key() === 'account-statement'
            && (($meta['accounts_count'] ?? 0) > 1)) {
            return ['view' => 'reports.multi-statement', 'mode' => 'multi'];
        }

        return ['view' => 'reports.print', 'mode' => 'single'];
    }
}
