<?php

namespace App\Reports\Accounting;

use App\Models\Accounting\CharAccount;
use App\Models\Accounting\JournalEntryLine;
use App\Reports\Contracts\Report;
use Illuminate\Support\Facades\DB;

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

    public function filters(): array
    {
        return [
            [
                'key'    => 'account_id',
                'label'  => 'الحساب',
                'type'   => 'account',
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
        $accountId = (int) ($filters['account_id'] ?? 0);

        if ($accountId <= 0) {
            return ['rows' => [], 'totals' => [], 'meta' => ['message' => 'اختر الحساب أولاً']];
        }

        $account = CharAccount::query()
            ->select(['accountID', 'accCode', 'accName', 'nature'])
            ->find($accountId);

        if (!$account) {
            return ['rows' => [], 'totals' => [], 'meta' => ['message' => 'الحساب غير موجود']];
        }

        $isDebitNature = ((int) $account->nature) === 1;

        $query = JournalEntryLine::query()
            ->join('Journal_Entries as je', 'je.entryID', '=', 'JournalEntrryLine.entryID')
            ->where('JournalEntrryLine.accountID', $accountId)
            ->select([
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
            $opening = (clone $query)
                ->whereDate('je.entryDate', '<', $filters['date_from'])
                ->selectRaw('COALESCE(SUM(JournalEntrryLine.localDebit), 0) as d')
                ->selectRaw('COALESCE(SUM(JournalEntrryLine.localCredit), 0) as c')
                ->first();

            $openingDebit = (float) ($opening->d ?? 0);
            $openingCredit = (float) ($opening->c ?? 0);

            $query->whereDate('je.entryDate', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('je.entryDate', '<=', $filters['date_to']);
        }

        $lines = $query
            ->orderBy('je.entryDate')
            ->orderBy('je.entryID')
            ->orderBy('JournalEntrryLine.entryLineID')
            ->get();

        // الرصيد الافتتاحي حسب طبيعة الحساب
        $balance = $isDebitNature
            ? $openingDebit - $openingCredit
            : $openingCredit - $openingDebit;

        $rows = [];

        if ($openingDebit > 0 || $openingCredit > 0) {
            $rows[] = [
                'id'          => 0,
                'date'        => $filters['date_from'] ?? '',
                'doc_type'    => 'رصيد أول المدة',
                'doc_number'  => '',
                'description' => '',
                'debit'       => $openingDebit,
                'credit'      => $openingCredit,
                'balance'     => round($balance, 2),
            ];
        }

        $totalDebit = $openingDebit;
        $totalCredit = $openingCredit;

        foreach ($lines as $line) {
            $debit = (float) $line->localDebit;
            $credit = (float) $line->localCredit;

            $totalDebit += $debit;
            $totalCredit += $credit;

            // الرصيد التراكمي حسب طبيعة الحساب
            $balance = $isDebitNature
                ? $balance + $debit - $credit
                : $balance + $credit - $debit;

            $rows[] = [
                'id'          => (int) $line->entryID,
                'date'        => $line->entryDate
                    ? \Illuminate\Support\Carbon::parse($line->entryDate)->format('Y-m-d')
                    : '',
                'doc_type'    => $line->docType ?? '',
                'doc_number'  => $line->docNumber ?? '',
                'description' => $line->description2 ?? '',
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => round($balance, 2),
            ];
        }

        return [
            'rows'   => $rows,
            'totals' => [
                'debit'    => $totalDebit,
                'credit'   => $totalCredit,
                'balance'  => round($isDebitNature
                    ? $totalDebit - $totalCredit
                    : $totalCredit - $totalDebit, 2),
            ],
            'meta'   => [
                'account' => [
                    'code' => $account->accCode,
                    'name' => $account->accName,
                ],
            ],
        ];
    }
}
