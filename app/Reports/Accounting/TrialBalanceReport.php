<?php

namespace App\Reports\Accounting;

use App\Models\Accounting\CharAccount;
use App\Models\Accounting\JournalEntryLine;
use App\Reports\Contracts\Report;
use Illuminate\Support\Facades\DB;

/**
 * ميزان المراجعة (Trial Balance).
 *
 * أرصدة كل حساب من أرصدة القيود خلال فترة (أو الكل).
 * يعرض الحسابات ذات الحركة فقط — الجمع على الأعمدة المحلية.
 */
class TrialBalanceReport implements Report
{
    public function key(): string
    {
        return 'trial-balance';
    }

    public function title(): string
    {
        return 'ميزان المراجعة';
    }

    public function description(): string
    {
        return 'أرصدة الحسابات المدينة والدائنة خلال فترة';
    }

    public function icon(): string
    {
        return 'bounding-box-circles';
    }

    public function category(): string
    {
        return 'accounting';
    }

    public function filters(): array
    {
        return [
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
            ['key' => 'code',    'label' => 'رمز الحساب', 'type' => 'text'],
            ['key' => 'account', 'label' => 'اسم الحساب', 'type' => 'text'],
            ['key' => 'debit',   'label' => 'مدين',       'type' => 'money', 'footer' => 'sum'],
            ['key' => 'credit',  'label' => 'دائن',       'type' => 'money', 'footer' => 'sum'],
        ];
    }

    public function run(array $filters): array
    {
        $query = JournalEntryLine::query()
            ->join('Journal_Entries as je', 'je.entryID', '=', 'JournalEntrryLine.entryID')
            ->join('characcount as ca', 'ca.accountID', '=', 'JournalEntrryLine.accountID')
            ->select([
                'ca.accountID',
                'ca.accCode as code',
                'ca.accName as account',
                'ca.nature',
            ])
            ->selectRaw('COALESCE(SUM(JournalEntrryLine.localDebit), 0) as debit')
            ->selectRaw('COALESCE(SUM(JournalEntrryLine.localCredit), 0) as credit')
            ->groupBy('ca.accountID', 'ca.accCode', 'ca.accName', 'ca.nature');

        if (!empty($filters['date_from'])) {
            $query->where('je.entryDate', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('je.entryDate', '<=', $filters['date_to']);
        }

        $result = $query->orderBy('ca.accCode')->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($result as $item) {
            $debit = (float) $item->debit;
            $credit = (float) $item->credit;

            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'id'      => (int) $item->accountID,
                'code'    => $item->code ?? '',
                'account' => $item->account ?? '',
                'debit'   => $debit,
                'credit'  => $credit,
            ];
        }

        return [
            'rows'   => $rows,
            'totals' => [
                'debit'  => $totalDebit,
                'credit' => $totalCredit,
            ],
            'meta'   => [],
        ];
    }
}
