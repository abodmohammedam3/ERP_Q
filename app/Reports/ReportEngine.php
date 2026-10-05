<?php

namespace App\Reports;

use App\Reports\Contracts\Report;
use InvalidArgumentException;

/**
 * محرّك التقارير — الطبقة الوسطى بين الـ Controller والتقارير.
 *
 * المسؤوليات:
 *  - التحقق من الفلاتر الواردة (فقط المفاتيح المصرّح بها في تعريف التقرير).
 *  - تنفيذ التقرير عبر العقد الموحّد.
 *  - توحيد شكل الإرجاع: rows + totals + columns + pagination.
 */
class ReportEngine
{
    /**
     * تنفيذ تقرير بمفتاحه على فلاتر الطلب الخام.
     *
     * @param  array<string, mixed> $rawFilters فلاتر من الطلب (غير موثوقة)
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   totals: array<string, float>,
     *   columns: array<int, array<string, mixed>>,
     *   meta: array<string, mixed>
     * }
     *
     * @throws InvalidArgumentException إذا كان المفتاح غير مسجل
     */
    public static function execute(string $key, array $rawFilters): array
    {
        $report = ReportRegistry::get($key);
        $filters = self::sanitizeFilters($report, $rawFilters);

        $result = $report->run($filters);

        return [
            'rows'     => $result['rows'] ?? [],
            'totals'   => $result['totals'] ?? [],
            // التقرير قد يعدّل أعمدته ديناميكياً حسب الفلاتر
            'columns'  => $result['meta']['columns'] ?? $report->columns(),
            'meta'     => $result['meta'] ?? [],
        ];
    }

    /**
     * تنظيف الفلاتر: يقبل فقط المفاتيح المعرّفة في التقرير
     * ويحوّل القيم إلى أنواع آمنة حسب نوع الفلتر.
     *
     * @return array<string, mixed>
     */
    public static function sanitizeFilters(Report $report, array $raw): array
    {
        $allowed = [];

        // مفتاح التقسيم (page) مسموح دائماً — لا يحتاج إعلاناً في تعريف التقرير
        if (isset($raw['page']) && $raw['page'] !== '' && $raw['page'] !== null) {
            $allowed['page'] = max(1, (int) $raw['page']);
        }

        foreach ($report->filters() as $filter) {
            $key = $filter['key'] ?? null;

            if (!$key) {
                continue;
            }

            $type = $filter['type'] ?? 'text';
            $value = $raw[$key] ?? null;

            // القيمة الفارغة تُسقَط (لا يُطبَّق الفلتر)
            if ($value === null || $value === '') {
                continue;
            }

            $allowed[$key] = self::castValue($type, $value);
        }

        return $allowed;
    }

    /**
     * تحويل قيمة الفلتر حسب نوعه.
     */
    private static function castValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'number' => (float) $value,
            // select: رقم فقط إذا كانت القيمة رقمية (مثل voucher_type)
            // وإلا تبقى نصاً (مثل doc_type: "سند قبض")
            'select' => is_numeric($value) ? (int) $value : trim((string) $value),
            // date / text / account / item تبقى نصاً —
            // والأمان يتحقق في التقرير نفسه عبر Eloquent bindings
            default => trim((string) $value),
        };
    }
}
