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
     * @param  bool $all طبقة الطباعة/التصدير: تلغي ترقيم الصفحة وتمشي التقرير على كل الصفوف
     *                  (حتى حدّ reports.export_max_rows) عبر فلتر مخفي _all
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   totals: array<string, float>,
     *   columns: array<int, array<string, mixed>>,
     *   meta: array<string, mixed>
     * }
     *
     * @throws InvalidArgumentException إذا كان المفتاح غير مسجل
     */
    public static function execute(string $key, array $rawFilters, bool $all = false): array
    {
        $report = ReportRegistry::get($key);
        $filters = self::sanitizeFilters($report, $rawFilters);

        // B1: الطباعة/التصدير يشملان كل الصفوف — نسقط page ونفعّل _all
        // داخل الفلاتر بعد التنظيف (لأنه مفتاح مخفي لا يحتاج إعلاناً في تعريف التقرير)
        if ($all) {
            unset($filters['page']);
            $filters['_all'] = true;
        }

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

            // القيم غير المفردة (مصفوفات/كائنات) لا تُحوَّل إطلاقاً — تُتجاهل
            if (!is_scalar($value)) {
                continue;
            }

            // القيمة الفارغة تُسقَط (لا يُطبَّق الفلتر) — إلا للحقول الخفية (توافق خلفي)
            if (($value === null || $value === '') && empty($filter['hidden'])) {
                continue;
            }

            $allowed[$key] = self::castValue($type, $value);
        }

        return $allowed;
    }

    /**
     * تحويل قيمة الفلتر حسب نوعه.
     *
     * القاعدة: select أو text يبقى نصاً دائماً ولا يُحوَّل إلى int،
     * و number أو money يُحوَّل إلى float.
     */
    private static function castValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'number' | 'money' => (float) $value,
            // date / select / text / account / item تبقى نصاً دائماً —
            // والأمان يتحقق في التقرير نفسه عبر Eloquent bindings
            default => trim((string) $value),
        };
    }
}
