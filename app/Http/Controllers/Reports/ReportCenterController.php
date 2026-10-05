<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Inventory\Item;
use App\Reports\ReportEngine;
use App\Reports\ReportRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مركز التقارير — نقطة التحكم الواحدة لكل التقارير.
 *
 * التدفق:
 *  index()       → يقرأ الـ Registry ويرسل تعريفات التقارير للشاشة
 *  sources()     → بيانات القوائم المنسدلة (حسابات، أصناف، عملات...)
 *  data($key)    → JSON للتقرير عبر المحرك (rows + totals + columns)
 *  print($key)   → صفحة طباعة مستقلة
 *  export($key)  → تصدير CSV
 */
class ReportCenterController extends Controller
{
    // ════════════════════════════
    //  الشاشة الرئيسية
    // ════════════════════════════

    public function index(Request $request)
    {
        // تعريف التقرير المطلوب (أو التقرير الأول افتراضياً)
        $key = (string) $request->query('report', '');
        $report = $key !== '' && ReportRegistry::has($key)
            ? ReportRegistry::get($key)
            : ReportRegistry::all()[array_key_first(ReportRegistry::all())];

        // كل التعريفات (بما فيها الفلاتر والأعمدة) — تُرسم الشاشة ديناميكياً منها
        $definitions = [];

        foreach (ReportRegistry::all() as $item) {
            $definitions[] = [
                'key'         => $item->key(),
                'title'       => $item->title(),
                'description' => $item->description(),
                'icon'        => $item->icon(),
                'category'    => $item->category(),
                'filters'     => $item->filters(),
                'columns'     => $item->columns(),
            ];
        }

        return view('reports.center', [
            'definitions' => $definitions,
            'activeKey'   => $report->key(),
            'urls'        => [
                'data'    => url('/reports/data'),
                'print'   => url('/reports/print'),
                'export'  => url('/reports/export'),
                'sources' => route('reports.sources'),
            ],
        ]);
    }

    // ════════════════════════════
    //  تعريفات التقارير للشاشة
    // ════════════════════════════

    public function definitions(): JsonResponse
    {
        $definitions = [];

        foreach (ReportRegistry::all() as $report) {
            $definitions[] = [
                'key'         => $report->key(),
                'title'       => $report->title(),
                'description' => $report->description(),
                'icon'        => $report->icon(),
                'category'    => $report->category(),
            ];
        }

        return $this->ok(['definitions' => $definitions]);
    }

    // ════════════════════════════════════════════
    //  مصادر القوائم المنسدلة للفلاتر
    // ════════════════════════════

    public function sources(): JsonResponse
    {
        $accounts = CharAccount::query()
            ->where('isPostable', 1)
            ->orderBy('accCode')
            ->get(['accountID', 'accCode', 'accName']);

        $items = Item::query()
            ->where('is_active', 1)
            ->orderBy('itemID')
            ->get(['itemID', 'itemName2']);

        $coins = Coin::query()
            ->orderBy('coinsID')
            ->get(['coinsID', 'coinsName', 'coinsCode']);

        $docTypes = \App\Models\Accounting\JournalEntry::query()
            ->select('docType')
            ->whereNotNull('docType')
            ->distinct()
            ->orderBy('docType')
            ->pluck('docType');

        return $this->ok([
            'sources' => [
                'accounts'     => $accounts->map(fn ($a) => [
                    'id'   => $a->accountID,
                    'text' => trim($a->accCode . ' - ' . $a->accName),
                ]),
                'items'        => $items->map(fn ($i) => [
                    'id'   => $i->itemID,
                    'text' => $i->itemName2,
                ]),
                'coins'        => $coins->map(fn ($c) => [
                    'id'   => $c->coinsID,
                    'text' => $c->coinsName,
                ]),
                'docTypes'     => $docTypes,
                'voucherTypes' => [
                    ['id' => 0, 'text' => 'الكل'],
                    ['id' => 1, 'text' => 'سندات القبض'],
                    ['id' => 2, 'text' => 'سندات الصرف'],
                ],
            ],
        ]);
    }

    // ════════════════════════════
    //  بيانات تقرير (JSON)
    // ════════════════════════════

    public function data(Request $request, string $key): JsonResponse
    {
        if (!ReportRegistry::has($key)) {
            return $this->fail('التقرير غير موجود');
        }

        $result = ReportEngine::execute($key, $request->query->all());

        return $this->ok($result);
    }

    // ════════════════════════════
    //  الطباعة
    // ════════════════════════════

    public function print(Request $request, string $key)
    {
        if (!ReportRegistry::has($key)) {
            abort(404);
        }

        $result = ReportEngine::execute($key, $request->query->all());
        $report = ReportRegistry::get($key);

        return view('reports.print', [
            'report'  => $report,
            'rows'    => $result['rows'],
            'totals'  => $result['totals'],
            'columns' => $result['columns'],
            'meta'    => $result['meta'],
            'filters' => $request->query->all(),
        ]);
    }

    // ════════════════════════════
    //  تصدير CSV
    // ════════════════════════════

    public function export(Request $request, string $key)
    {
        if (!ReportRegistry::has($key)) {
            abort(404);
        }

        $result = ReportEngine::execute($key, $request->query->all());
        $report = ReportRegistry::get($key);

        $filename = $report->key() . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');

            // BOM لعرض العربية بشكل صحيح في Excel
            fwrite($out, "\xEF\xBB\xBF");

            // رأس الجدول
            fputcsv($out, array_column($result['columns'], 'label'));

            // الصفوف
            foreach ($result['rows'] as $row) {
                $line = [];
                foreach ($result['columns'] as $col) {
                    $line[] = $row[$col['key']] ?? '';
                }
                fputcsv($out, $line);
            }

            // صف الإجماليات (تحت الأعمدة ذات footer=sum)
            if (!empty($result['totals'])) {
                $line = [];
                foreach ($result['columns'] as $col) {
                    $line[] = ($col['footer'] ?? '') === 'sum'
                        ? ($result['totals'][$col['key']] ?? '')
                        : '';
                }
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ════════════════════════════════════════════
    //  أدوات الاستجابة الموحدة
    // ════════════════════════════

    private function ok(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['success' => true], $data), $status);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
