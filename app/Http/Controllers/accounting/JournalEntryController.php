<?php

namespace App\Http\Controllers\accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    private const PER_PAGE = 10;

    // ══════════════════════════════════════════════════════════
    //  Endpoints
    // ══════════════════════════════════════════════════════════

    public function index()
    {
        session()->save();

        return view('operation.accounting.journalEntries.index');
    }

    public function list(Request $request): JsonResponse
    {
        session()->save();

        $page     = max(1, (int) $request->input('page', 1));
        $search   = trim($request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $docType  = $request->input('doc_type');

        $query = JournalEntry::query()->with('lines');

        // ─── الفلاتر ───
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('entryNo', 'like', "%{$search}%")
                  ->orWhere('description2', 'like', "%{$search}%")
                  ->orWhere('docNumber', 'like', "%{$search}%")
                  ->orWhere('docType', 'like', "%{$search}%");
            });
        }

        if ($dateFrom) {
            $query->whereDate('entryDate', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('entryDate', '<=', $dateTo);
        }

        if ($docType) {
            $query->where('docType', $docType);
        }

        // ─── العدد والتقسيم ───
        $total    = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page     = min($page, $lastPage);

        $entries = $query->orderByDesc('entryDate')
            ->orderByDesc('entryID')
            ->skip(($page - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE)
            ->get();

        // ─── بناء الصفوف + الإجماليات ───
        $rows = [];
        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            $debit  = (float) $entry->lines->sum('localDebit');
            $credit = (float) $entry->lines->sum('localCredit');

            $totalDebit  += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'id'          => $entry->entryID,
                'entryNo'     => $entry->entryNo,
                'entryDate'   => $entry->entryDate?->format('Y-m-d'),
                'docType'     => $entry->docType ?? '',
                'docNumber'   => $entry->docNumber ?? '',
                'description' => $entry->description2 ?? '',
                'debit'       => $debit,
                'credit'      => $credit,
            ];
        }

        return $this->ok([
            'rows'       => $rows,
            'totals'     => [
                'debit'  => $totalDebit,
                'credit' => $totalCredit,
            ],
            'pagination' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => self::PER_PAGE,
                'total'        => $total,
            ],
        ]);
    }

   public function show(int $id): JsonResponse
{
    session()->save();

    $entry = JournalEntry::with([
        'lines.account',
    ])->findOrFail($id);

    // ⭐ جلب العملات يدويًا بدون أي فلترة
    $currencyIds = $entry->lines->pluck('coinsID')->filter()->unique()->all();
    $currencies = \App\Models\Accounting\Coin::whereIn('coinsID', $currencyIds)
        ->pluck('coinsCode', 'coinsID'); // المفتاح coinsID والقيمة coinsCode

    return $this->ok([
        'entry' => [
            'id'          => $entry->entryID,
            'entryNo'     => $entry->entryNo,
            'entryDate'   => $entry->entryDate?->format('Y-m-d'),
            'docType'     => $entry->docType ?? '',
            'docNumber'   => $entry->docNumber ?? '',
            'description' => $entry->description2 ?? '',
            'totalAmount' => (float) $entry->totalAmount,
            'lines'       => $entry->lines->map(fn($line) => [
                'id'            => $line->entryLineID,
                'accountID'     => $line->accountID,
                'accountCode'   => $line->account->accCode ?? '',
                'accountName'   => $line->account->accName ?? '',
                'currencyCode'  => $currencies[$line->coinsID] ?? '—', // ⭐ جلب مباشر
                'exchangRate'   => (float) $line->exchangRate,
                'debit'         => (float) $line->debit,
                'credit'        => (float) $line->credit,
                'localDebit'    => (float) $line->localDebit,
                'localCredit'   => (float) $line->localCredit,
                'description'   => $line->description2 ?? '',
            ])->all(),
        ],
    ]);
}

    // ══════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════

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