<?php

namespace App\Services;

use App\Models\Accounting\CharAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * نطاق حسابات مركز التقارير — المصدر الوحيد لحقيقة:
 *  - الحسابات التفصيلية المؤهلة للكشف (isPostable=1 وغير مخزنية).
 *  - الأبواب المباشرين لتبويب/نطاق المودال.
 *
 * يشترك بين ReportCenterController::accounts() (مودال الشاشة)
 * و AccountStatementReport (نطاق كشف الحساب) — منعاً لتكرار الكود.
 *
 * الأهلية:
 *  - isPostable = 1  (تفصيلي قابل للحركة — يستبعد التجميعية).
 *  - خارج فرع المخزون كاملاً (حركته تُتابَع في كشف الأصناف).
 *
 * الأبواب: الآباء المباشرين الفريدون للحسابات المؤهلة فقط
 *  (أي حساب له ابن مؤهل واحد على الأقل — ديناميكي بلا قواعد مستوية).
 */
class ChartAccountScope
{
    private const CACHE_KEY = 'reports.chart-scope.v1';
    private const CACHE_TTL = 3600;

    /**
     * كل صفوف الدليل (صغيرة — عشرات الصفوف) — مخزّنة ساعة.
     *
     * @return array<int, array{accountID:int, accParent:?int, accCode:string, accName:string, isPostable:int, nature:int, system_key:?string}>
     */
    private static function flat(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return CharAccount::query()
                ->orderBy('accCode')
                ->get(['accountID', 'accParent', 'accCode', 'accName', 'isPostable', 'nature', 'system_key'])
                ->map(fn ($a) => [
                    'accountID'  => (int) $a->accountID,
                    'accParent'  => $a->accParent !== null ? (int) $a->accParent : null,
                    'accCode'    => (string) $a->accCode,
                    'accName'    => (string) $a->accName,
                    'isPostable' => (int) $a->isPostable,
                    'nature'     => (int) $a->nature,
                    'system_key' => $a->system_key,
                ])
                ->all();
        });
    }

    /**
     * الحسابات التفصيلية المؤهلة (القابلة للحركة).
     *
     * @return Collection<int, array>
     */
    public static function eligible(): Collection
    {
        $all = self::flat();

        // شجرة المخزون (استبعاد كامل)
        $childrenByParent = [];
        $inventoryRootId = null;

        foreach ($all as $row) {
            if ($row['system_key'] === 'inventory') {
                $inventoryRootId = $row['accountID'];
            }

            if ($row['accParent'] !== null) {
                $childrenByParent[$row['accParent']][] = $row['accountID'];
            }
        }

        $excluded = [];

        if ($inventoryRootId !== null) {
            $stack = [$inventoryRootId];

            while ($stack) {
                $current = array_pop($stack);
                $excluded[$current] = true;

                foreach ($childrenByParent[$current] ?? [] as $childId) {
                    $stack[] = $childId;
                }
            }
        }

        $eligible = [];

        foreach ($all as $row) {
            if ($row['isPostable'] !== 1) {
                continue;   // حساب تجميعي
            }

            if (isset($excluded[$row['accountID']])) {
                continue;   // حساب مخزني
            }

            $eligible[] = $row;
        }

        return collect($eligible);
    }

    /**
     * الأبواب القابلة للاختيار: الآباء المباشرين الفريدون
     * للحسابات المؤهلة، مع عدد أبناء كل أب.
     *
     * @return Collection<int, array{accountID:int, accCode:string, accName:string, children:int}>
     */
    public static function parents(): Collection
    {
        $eligible = self::eligible();
        $parentIds = $eligible->pluck('accParent')->filter()->unique()->values();

        $byId = collect(self::flat())->keyBy('accountID');

        return $parentIds->map(function ($parentId) use ($byId, $eligible) {
            $parent = $byId->get($parentId);

            if (!$parent) {
                return null;
            }

            return [
                'accountID' => $parent['accountID'],
                'accCode'   => $parent['accCode'],
                'accName'   => $parent['accName'],
                'children'  => $eligible->where('accParent', $parentId)->count(),
            ];
        })->filter()->values();
    }

    /**
     * الأبناء المباشرون المؤهلون لحساب أب معيّن.
     *
     * @return Collection<int, array>
     */
    public static function childrenOf(int $parentId): Collection
    {
        return self::eligible()
            ->where('accParent', $parentId)
            ->values();
    }

    /**
     * إبطاء الكاش (يُستدعى عند تغيّر الدليل في نفس الجلسة الاختبارية/التشغيلية).
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
