<?php

namespace App\Reports\Accounting;

use App\Models\Accounting\JournalEntryLine;
use App\Reports\Contracts\Report;

/**
 * دفتر الأستاذ العام (General Ledger).
 *
 * كل بنود القيود مرتبة زمنياً — المستوى الأدق في دفتر الحسابات.
 */
class GeneralLedgerReport implements Report
{
    /** عدد صفوف الصفحة الواحدة */
    private const PER_PAGE = 50;

    public function key(): string
    {
        return 'general-ledger';
    }

    public function title(): string
    {
        return 'دفتر الأستاذ العام';
    }

    public function description(): string
    {
        return 'جميع بنود القيود مرتبة زمنياً مع الإجماليات';
    }

    public function icon(): string
    {
        return 'journal-text';
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
            [
                'key'    => 'doc_type',
                'label'  => 'نوع المستند',
                'type'   => 'select',
                'source' => 'docTypes',
                'col'    => 'col-md-3',
            ],
            [
                'key'   => 'search',
                'label' => 'بحث',
                'type'  => 'text',
                'col'   => 'col-md-3',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'entry_date',   'label' => 'التاريخ',      'type' => 'date'],
            ['key' => 'entry_no',     'label' => 'رقم القيد',    'type' => 'text'],
            ['key' => 'doc_type',     'label' => 'نوع المستند',  'type' => 'text'],
            ['key' => 'doc_number',   'label' => 'رقم المستند',  'type' => 'text'],
            ['key' => 'account',      'label' => 'الحساب',       'type' => 'text'],
            ['key' => 'description',  'label' => 'البيان',       'type' => 'text'],
            ['key' => 'debit',        'label' => 'مدين',         'type' => 'money', 'footer' => 'sum'],
            ['key' => 'credit',       'label' => 'دائن',         'type' => 'money', 'footer' => 'sum'],
        ];
    }

    public function run(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = JournalEntryLine::query()
            ->join('Journal_Entries as je', 'je.entryID', '=', 'JournalEntrryLine.entryID')
            ->join('characcount as ca', 'ca.accountID', '=', 'JournalEntrryLine.accountID')
            ->select([
                'JournalEntrryLine.entryID',
                'je.entryNo',
                'je.entryDate',
                'je.docType',
                'je.docNumber',
                'je.description2',
                'ca.accCode as account_code',
                'ca.accName as account_name',
                'JournalEntrryLine.localDebit',
                'JournalEntrryLine.localCredit',
            ]);

        if (!empty($filters['date_from'])) {
            $query->where('je.entryDate', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('je.entryDate', '<=', $filters['date_to']);
        }

        if (!empty($filters['doc_type'])) {
            $query->where('je.docType', $filters['doc_type']);
        }

        $search = $filters['search'] ?? '';
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('je.entryNo', 'like', "%{$search}%")
                  ->orWhere('je.docNumber', 'like', "%{$search}%")
                  ->orWhere('je.description2', 'like', "%{$search}%")
                  ->orWhere('ca.accName', 'like', "%{$search}%")
                  ->orWhere('ca.accCode', 'like', "%{$search}%");
            });
        }

        // ── الإجماليات على كل النتائج المطابقة (ليس الصفحة فقط) ──
        // نظّف الأعمدة قبل إضافة عمودي SUM (وإلا خلط MySQL بينهما ورفض الاستعلام)
        $totalsRow = (clone $query)->toBase()
            ->cloneWithout(['columns'])
            ->selectRaw('COALESCE(SUM(JournalEntrryLine.localDebit), 0) as debit')
            ->selectRaw('COALESCE(SUM(JournalEntrryLine.localCredit), 0) as credit')
            ->first();

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);

        $lines = $query
            ->orderBy('je.entryDate')
            ->orderBy('je.entryID')
            ->skip(($page - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE)
            ->get();

        $rows = [];

        foreach ($lines as $line) {
            $rows[] = [
                'id'          => (int) $line->entryID,
                'entry_date'  => $line->entryDate
                    ? \Illuminate\Support\Carbon::parse($line->entryDate)->format('Y-m-d')
                    : '',
                'entry_no'    => $line->entryNo ?? '',
                'doc_type'    => $line->docType ?? '',
                'doc_number'  => $line->docNumber ?? '',
                'account'     => trim(($line->account_code ?? '') . ' - ' . ($line->account_name ?? '')),
                'description' => $line->description2 ?? '',
                'debit'       => (float) $line->localDebit,
                'credit'      => (float) $line->localCredit,
            ];
        }

        return [
            'rows'   => $rows,
            'totals' => [
                'debit'  => (float) ($totalsRow->debit ?? 0),
                'credit' => (float) ($totalsRow->credit ?? 0),
            ],
            'meta'   => [
                'pagination' => [
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'per_page'     => self::PER_PAGE,
                    'total'        => $total,
                ],
            ],
        ];
    }
}
