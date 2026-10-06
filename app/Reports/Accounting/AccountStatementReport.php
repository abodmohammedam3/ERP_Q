<?php

namespace App\Reports\Accounting;

use App\Models\Accounting\CharAccount;
use App\Models\Accounting\JournalEntryLine;
use App\Reports\Contracts\Report;
use App\Services\ChartAccountScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * كشف حساب (عميل / مورد / أي حساب من الدليل).
 *
 * حركة حساب واحد زمنياً مع رصيد أول المدة والرصيد التراكمي.
 * الرصيد يُحسب حسب طبيعة الحساب (nature): مدينة أو دائنة.
 */
class AccountStatementReport implements Report
{
    public function key(): string
    {
        return 'account-statement';
    }

    public function title(): string
    {
        return 'كشف حساب';
    }

    public function description(): string
    {
        return 'حركة عميل أو مورد مع رصيد أول المدة والرصيد التراكمي';
    }

    public function icon(): string
    {
        return 'receipt';
    }

    public function category(): string
    {
        return 'accounting';
    }

    /**
     * ثلاثة حقول للحسابات:
     *  - account_parent: الحساب الرئيسي (الأب المباشر) — إلزامي.
     *  - account_from / account_to: نطاق الأبناء المباشرين تحت الأب.
     */
    public function filters(): array
    {
        return [
            // توافق خلفي: روابط قديمة تحمل account_id مفرد — خفي (لا يُرسم في الواجهة)
            [
                'key'    => 'account_id',
                'label'  => '',
                'type'   => 'number',
                'hidden' => true,
            ],
            [
                'key'    => 'account_parent',
                'label'  => 'الحساب',
                'type'   => 'account',
                'mode'   => 'parent',
                'source' => 'accounts',
                'col'    => 'col-md-4',
            ],
            [
                'key'   => 'account_from',
                'label'  => 'من حساب',
                'type'   => 'account',
                'mode'   => 'child',
                'source' => 'accounts',
                'col'    => 'col-md-4',
            ],
            [
                'key'    => 'account_to',
                'label'  => 'إلى حساب',
                'type'   => 'account',
                'mode'   => 'child',
                'source' => 'accounts',
                'col'    => 'col-md-4',
            ],
            [
                'key'   => 'date_from',
                'label' => 'من تاريخ',
                'type'  => 'date',
                'col'   => 'col-md-3',
            ],
            [
                'key'   => 'date_to',
                'label' => 'إلى تاريخ',
                'type'  => 'date',
                'col'   => 'col-md-3',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'date',         'label' => 'التاريخ',         'type' => 'date'],
            ['key' => 'doc_type',     'label' => 'نوع المستند',     'type' => 'text'],
            ['key' => 'doc_number',   'label' => 'رقم المستند',     'type' => 'text'],
            ['key' => 'description',  'label' => 'البيان',          'type' => 'text'],
            ['key' => 'debit',        'label' => 'مدين',            'type' => 'money', 'footer' => 'sum'],
            ['key' => 'credit',       'label' => 'دائن',            'type' => 'money', 'footer' => 'sum'],
            ['key' => 'balance',      'label' => 'الرصيد',          'type' => 'money', 'footer' => 'last'],
        ];
    }

    public function run(array $filters): array
    {
        $startedAt = microtime(true);
        $maxRows = (int) config('reports.statement_max_rows', 20000);
        $maxSeconds = (float) config('reports.statement_max_seconds', 5);

        // ── 1) الأب إلزامي (رسالة واحدة فقط) ──
        $parentRaw = trim((string) ($filters['account_parent'] ?? ''));

        // توافق خلفي: روابط قديمة تحمل account_id المفرد
        // (ملاحظة: الحساب القديم قد يكون تجميعياً — نبحث في الدليل كاملاً لا في المؤهلين فقط)
        if ($parentRaw === '' && !empty($filters['account_id'])) {
            $legacyId = (int) $filters['account_id'];
            $byId = ChartAccountScope::eligible()->keyBy('accountID');
            if ($byId->has($legacyId)) {
                $parentRaw = (string) $byId->get($legacyId)['accParent'];
                $filters['account_from'] = $legacyId;
                $filters['account_to'] = $legacyId;
            }
        }

        if ($parentRaw === '') {
            return [
                'rows' => [], 'totals' => [],
                'meta' => ['message' => 'اختر الحساب الرئيسي أولاً (مثل: الصناديق، البنوك، العملاء...)'],
            ];
        }

        $eligible = ChartAccountScope::eligible();
        $eligibleById = $eligible->keyBy('accountID');

        // ── 2) النطاق: أبناء الأب المباشرين فقط ──
        $parentLabel = null;

        if ($parentRaw === 'all') {
            $scope = $eligible->sortBy('accCode')->values();
            $parentLabel = 'كل الحسابات التفصيلية';
        } else {
            $parentId = (int) $parentRaw;
            $children = ChartAccountScope::childrenOf($parentId)->sortBy('accCode')->values();

            if ($children->isNotEmpty()) {
                $scope = $children;
                $pRow = $eligibleById->get($parentId)
                    ?? ChartAccountScope::parents()->firstWhere('accountID', $parentId);
                $parentLabel = $pRow
                    ? (($pRow['accCode'] ?? '') . ' - ' . ($pRow['accName'] ?? ''))
                    : ('#' . $parentId);
            } elseif ($eligibleById->has($parentId)) {
                $scope = collect([$eligibleById->get($parentId)]);
                $one = $eligibleById->get($parentId);
                $parentLabel = $one['accCode'] . ' - ' . $one['accName'];
            } else {
                return [
                    'rows' => [], 'totals' => [],
                    'meta' => ['message' => 'لا توجد حسابات تفصيلية قابلة للحركة تحت هذا الحساب'],
                ];
            }
        }

        // ── 3) تطبيع من/إلى ──
        $fromRaw = trim((string) ($filters['account_from'] ?? ''));
        $toRaw = trim((string) ($filters['account_to'] ?? ''));
        $swapped = false;

        if ($fromRaw !== '' && $toRaw === '') {
            $toRaw = $fromRaw;                       // ❸: من وحدها = حساب واحد
        } elseif ($fromRaw === '' && $toRaw !== '') {
            $fromRaw = $toRaw;                       // ❹: تطبيع صامت + سجل
            Log::warning('[Reports] account_to بدون account_from — طُبّع', [
                'account_to' => $toRaw, 'parent' => $parentRaw,
            ]);
        }

        if ($fromRaw !== '' && $toRaw !== '') {
            $fromAcc = $eligibleById->get((int) $fromRaw);
            $toAcc = $eligibleById->get((int) $toRaw);

            if (!$fromAcc || !$toAcc) {
                return [
                    'rows' => [], 'totals' => [],
                    'meta' => ['message' => 'الحساب المحدد في النطاق غير صالح'],
                ];
            }

            // المقارنة برقم الحساب لا بـ IDs
            if ((int) $fromAcc['accCode'] > (int) $toAcc['accCode']) {
                [$fromAcc, $toAcc] = [$toAcc, $fromAcc];
                $swapped = true;
            }

            $scope = $scope->filter(function ($a) use ($fromAcc, $toAcc) {
                $code = (int) $a['accCode'];

                return $code >= (int) $fromAcc['accCode'] && $code <= (int) $toAcc['accCode'];
            })->sortBy('accCode')->values();

            if ($scope->isEmpty()) {
                return [
                    'rows' => [], 'totals' => [],
                    'meta' => ['message' => 'النطاق المحدد لا يقع تحت الحساب الرئيسي المختار'],
                ];
            }
        }

        $accountIds = $scope->pluck('accountID')->all();
        $isSingle = count($accountIds) === 1;

        $query = JournalEntryLine::query()
            ->join('Journal_Entries as je', 'je.entryID', '=', 'JournalEntrryLine.entryID')
            ->whereIn('JournalEntrryLine.accountID', $accountIds)
            ->select([
                'JournalEntrryLine.accountID',
                'je.entryID',
                'je.entryNo',
                'je.entryDate',
                'je.docType',
                'je.docNumber',
                'je.description2',
                'JournalEntrryLine.localDebit',
                'JournalEntrryLine.localCredit',
            ]);

        // ── رصيد أول المدة: مجموع الحركة قبل بداية الفترة ──
        $openingDebit = 0.0;
        $openingCredit = 0.0;

        if (!empty($filters['date_from'])) {
            $query->whereDate('je.entryDate', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('je.entryDate', '<=', $filters['date_to']);
        }

        // صمام الصفوف: عدّاد خفيف قبل الجلب (رفض كامل — لا قصّ للأرصدة)
        $lineCount = (clone $query)->toBase()->cloneWithout(['columns'])->count();

        if ($lineCount > $maxRows) {
            return [
                'rows' => [], 'totals' => [],
                'meta' => [
                    'message' => "النطاق واسع جداً ({$lineCount} صف) — حدّد نطاقاً أضيق أو فترة زمنية",
                    'accounts_count' => count($accountIds),
                ],
            ];
        }

        // افتتاحيات مجمّعة لكل حساب (استعلام واحد GROUP BY)
        $openings = [];

        if (!empty($filters['date_from'])) {
            $openRows = JournalEntryLine::query()
                ->join('Journal_Entries as je', 'je.entryID', '=', 'JournalEntrryLine.entryID')
                ->whereIn('JournalEntrryLine.accountID', $accountIds)
                ->whereDate('je.entryDate', '<', $filters['date_from'])
                ->toBase()
                ->select('JournalEntrryLine.accountID')
                ->selectRaw('COALESCE(SUM(JournalEntrryLine.localDebit), 0) as d')
                ->selectRaw('COALESCE(SUM(JournalEntrryLine.localCredit), 0) as c')
                ->groupBy('JournalEntrryLine.accountID')
                ->get();

            foreach ($openRows as $o) {
                $openings[(int) $o->accountID] = [
                    'd' => (float) $o->d,
                    'c' => (float) $o->c,
                ];
            }
        }

        $lines = $query
            ->orderBy('JournalEntrryLine.accountID')
            ->orderBy('je.entryDate')
            ->orderBy('je.entryID')
            ->orderBy('JournalEntrryLine.entryLineID')
            ->get()
            ->groupBy('accountID');

        // صمام الزمن ❻
        $elapsed = microtime(true) - $startedAt;

        if ($elapsed > $maxSeconds) {
            Log::warning('[Reports] تجاوز زمن كشف الحساب الحد', [
                'seconds' => round($elapsed, 3),
                'accounts' => count($accountIds),
                'lines' => $lineCount,
            ]);

            return [
                'rows' => [], 'totals' => [],
                'meta' => [
                    'message' => 'استغرق تنفيذ التقرير وقتاً طويلاً (' . round($elapsed, 1) . ' ث) — حدّد نطاقاً أضيق أو فترة زمنية',
                    'accounts_count' => count($accountIds),
                ],
            ];
        }

        // ── 5) بناء الصفوف: رصيد يُصفَّر لكل حساب ──
        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $dateFrom = $filters['date_from'] ?? null;

        foreach ($scope as $acc) {
            $accId = (int) $acc['accountID'];
            $isDebitNature = ((int) $acc['nature']) !== 1;
            $accLabel = $acc['accCode'] . ' - ' . $acc['accName'];

            $openD = (float) ($openings[$accId]['d'] ?? 0);
            $openC = (float) ($openings[$accId]['c'] ?? 0);
            $balance = $isDebitNature ? $openD - $openC : $openC - $openD;

            if ($openD > 0 || $openC > 0) {
                $rows[] = [
                    'id' => 0,
                    'account' => $accLabel,
                    'date' => $dateFrom ?? '',
                    'doc_type' => 'opening',
                    'doc_number' => '',
                    'description' => $isSingle ? '' : $accLabel,
                    'debit' => $openD,
                    'credit' => $openC,
                    'balance' => round($balance, 2),
                    '_account_id' => $accId,
                ];
                $totalDebit += $openD;
                $totalCredit += $openC;
            }

            $accDebit = $openD;
            $accCredit = $openC;

            foreach (($lines->get($accId) ?? collect()) as $line) {
                $debit = (float) $line->localDebit;
                $credit = (float) $line->localCredit;
                $accDebit += $debit;
                $accCredit += $credit;
                $totalDebit += $debit;
                $totalCredit += $credit;

                $balance = $isDebitNature
                    ? $balance + $debit - $credit
                    : $balance + $credit - $debit;

                $rows[] = [
                    'id' => (int) $line->entryID,
                    'account' => $accLabel,
                    'date' => $line->entryDate ? Carbon::parse($line->entryDate)->format('Y-m-d') : '',
                    'doc_type' => $line->docType ?? '',
                    'doc_number' => $line->docNumber ?? '',
                    'description' => $line->description2 ?? '',
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => round($balance, 2),
                    '_account_id' => $accId,
                ];
            }

            // صف إقفال لكل حساب في الوضع المتعدد فقط
            if (!$isSingle && ($accDebit > 0 || $accCredit > 0 || ($lines->get($accId) ?? collect())->isNotEmpty())) {
                $rows[] = [
                    'id' => -1,
                    'account' => $accLabel,
                    'date' => '',
                    'doc_type' => 'closing',
                    'doc_number' => '',
                    'description' => $accLabel,
                    'debit' => round($accDebit, 2),
                    'credit' => round($accCredit, 2),
                    'balance' => round($balance, 2),
                    '_account_id' => $accId,
                ];
            }
        }

        // ── 6) الأعمدة: عمود الحساب يظهر في المتعدد فقط ──
        $columns = $this->columns();
        $meta = [
            'parent' => $parentLabel,
            'accounts_count' => count($accountIds),
            'swapped' => $swapped,
        ];

        if (!$isSingle) {
            array_unshift($columns, ['key' => 'account', 'label' => 'الحساب', 'type' => 'text']);
            $meta['columns'] = $columns;
            $totals = ['debit' => round($totalDebit, 2), 'credit' => round($totalCredit, 2)];
        } else {
            $only = $scope->first();
            $isDebit = ((int) $only['nature']) !== 1;
            $totals = [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'balance' => round($isDebit ? $totalDebit - $totalCredit : $totalCredit - $totalDebit, 2),
            ];
            $meta['account'] = ['code' => $only['accCode'], 'name' => $only['accName']];
        }

        return ['rows' => $rows, 'totals' => $totals, 'meta' => $meta];
    }
}
