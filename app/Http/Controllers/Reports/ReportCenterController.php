<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CharAccount;
use App\Models\Accounting\Coin;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Item;
use App\Reports\ReportEngine;
use App\Reports\ReportRegistry;
use App\Services\ChartAccountScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
                'data'     => url('/reports/data'),
                'print'    => url('/reports/print'),
                'export'   => url('/reports/export'),
                'sources'  => route('reports.sources'),
                'accounts' => route('reports.accounts'),
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
        // المصادر ثابتة نسبياً (حسابات/أصناف/عملات) — تُخزَّن مؤقتاً
        // لتفادي تنفيذ 4 استعلامات في كل فتح للشاشة.
        $sources = Cache::remember('reports.sources.v1', 3600, function () {

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

            $docTypes = JournalEntry::query()
                ->select('docType')
                ->whereNotNull('docType')
                ->distinct()
                ->orderBy('docType')
                ->pluck('docType');

            return [
                'accounts'     => $accounts->map(fn ($a) => [
                    'id'   => $a->accountID,
                    'text' => trim($a->accCode . ' - ' . $a->accName),
                ])->values()->all(),
                'items'        => $items->map(fn ($i) => [
                    'id'   => $i->itemID,
                    'text' => $i->itemName2,
                ])->values()->all(),
                'coins'        => $coins->map(fn ($c) => [
                    'id'   => $c->coinsID,
                    'text' => $c->coinsName,
                ])->values()->all(),
                'docTypes'     => $docTypes->values()->all(),
                'voucherTypes' => [
                    ['id' => 0, 'text' => 'الكل'],
                    ['id' => 1, 'text' => 'سندات القبض'],
                    ['id' => 2, 'text' => 'سندات الصرف'],
                ],
            ];
        });

        return $this->ok(['sources' => $sources]);
    }

    // ════════════════════════════════════════════════════
    //  مودال اختيار الحساب — دليل الحسابات (خاص بالشاشة)
    // ════════════════════════════════════════════════════

    /**
     * بيانات مودال "3 حقول" (الحساب / من / إلى):
     *  - parents: الأبواب القابلة للاختيار (+ صف "الكل" يُضاف في الواجهة).
     *  - accounts: الحسابات التفصيلية المؤهلة مع حقل parent
     *    (لعرض الأبناء المباشرين فقط لكل أب في الوضعين من/إلى).
     *
     * الفلترة كلها محلية في المتصفح — طلب واحد لكل جلسة.
     * المصدر المشترك: ChartAccountScope (نفسه يستخدمه كشف الحساب).
     */
    public function accounts(): JsonResponse
    {
        $eligible = ChartAccountScope::eligible()->map(fn ($a) => [
            'accountID' => $a['accountID'],
            'accCode'   => $a['accCode'],
            'accName'   => $a['accName'],
            'parent'    => $a['accParent'],
            'system_key'=> $a['system_key'],
        ])->values();

        $tree = CharAccount::query()
            ->orderBy('accCode')
            ->get(['accountID', 'accParent', 'accCode', 'accName', 'isPostable', 'system_key'])
            ->map(fn ($a) => [
                'accountID'  => (int) $a->accountID,
                'accParent'  => $a->accParent !== null ? (int) $a->accParent : null,
                'accCode'    => (string) $a->accCode,
                'accName'    => (string) $a->accName,
                'isPostable' => (int) $a->isPostable,
                'system_key' => $a->system_key,
            ])
            ->values();

        return $this->ok([
            'parents'   => ChartAccountScope::parents(),
            'eligible'  => $eligible,
            'accounts'  => $eligible,
            'tree'      => $tree,
            'cached_at' => now()->toIso8601String(),
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

            // مكافحة حقن CSV: خلية نصية تبدأ برمز تنفيذ محتمل (=+-@ tab CR)
            // تُسبَق بعلامة اقتباس حتى تتعامل معها Excel كنص
            $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/u', $v) ? "'" . $v : $v;

            // BOM لعرض العربية بشكل صحيح في Excel
            fwrite($out, "\xEF\xBB\xBF");

            // رأس الجدول
            fputcsv($out, array_map($safe, array_column($result['columns'], 'label')));

            // الصفوف
            foreach ($result['rows'] as $row) {
                $line = [];
                foreach ($result['columns'] as $col) {
                    $line[] = $safe($row[$col['key']] ?? '');
                }
                fputcsv($out, $line);
            }

            // صف الإجماليات (footer=sum) وصف الختام (footer=last = آخر صف مرئي)
            $hasFooter = !empty($result['totals'])
                || collect($result['columns'])->contains(fn ($c) => ($c['footer'] ?? '') === 'last');

            if ($hasFooter) {
                $lastRow = $result['rows'] !== []
                    ? $result['rows'][array_key_last($result['rows'])]
                    : null;

                $line = [];
                foreach ($result['columns'] as $col) {
                    $footer = $col['footer'] ?? '';

                    if ($footer === 'sum') {
                        $value = $result['totals'][$col['key']] ?? '';
                    } elseif ($footer === 'last') {
                        // قيمة آخر صف مرئي — وإن لم يوجد صف تبقى فارغة
                        $value = $lastRow[$col['key']] ?? '';
                    } else {
                        $value = '';
                    }

                    $line[] = $safe($value);
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
