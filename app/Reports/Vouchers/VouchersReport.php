<?php

namespace App\Reports\Vouchers;

use App\Models\Accounting\PaymentVoucher;
use App\Models\Accounting\ReceiptVoucher;
use App\Reports\Contracts\Report;

/**
 * تقرير سندات القبض والصرف.
 *
 * يدمج النوعين في قائمة واحدة زمنياً مع مؤشر النوع،
 * ويعرض الحساب المدين/الدائن ورقم القيد المرتبط (جسر الربط المحاسبي).
 */
class VouchersReport implements Report
{
    private const PER_PAGE = 50;

    public function key(): string
    {
        return 'vouchers';
    }

    public function title(): string
    {
        return 'سندات القبض والصرف';
    }

    public function description(): string
    {
        return 'جميع سندات القبض والصرف مع رقم القيد المرتبط';
    }

    public function icon(): string
    {
        return 'cash-coin';
    }

    public function category(): string
    {
        return 'vouchers';
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
                'key'    => 'voucher_type',
                'label'  => 'نوع السند',
                'type'   => 'select',
                'source' => 'voucherTypes',
                'col'    => 'col-md-3',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'date',          'label' => 'التاريخ',        'type' => 'date'],
            ['key' => 'voucher_type',  'label' => 'النوع',          'type' => 'text'],
            ['key' => 'voucher_no',    'label' => 'رقم السند',      'type' => 'text'],
            ['key' => 'debit_account', 'label' => 'الحساب المدين',  'type' => 'text'],
            ['key' => 'credit_account','label' => 'الحساب الدائن',  'type' => 'text'],
            ['key' => 'entry_no',      'label' => 'رقم القيد',      'type' => 'text'],
            ['key' => 'local_amount',  'label' => 'المبلغ المحلي',  'type' => 'money', 'footer' => 'sum'],
        ];
    }

    public function run(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $type = (int) ($filters['voucher_type'] ?? 0); // 0 = الكل, 1 = قبض, 2 = صرف

        $rows = [];
        $total = 0.0;
        $count = 0;
        $lastPage = 1;

        $wants = fn (int $which): bool => $type === 0 || $type === $which;

        // ── سندات القبض ──
        if ($wants(1)) {
            $receipts = ReceiptVoucher::query()
                ->with([
                    'debitAccount:accountID,accCode,accName',
                    'creditAccount:accountID,accCode,accName',
                    'currency:coinsID,coinsName',
                ])
                ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('voucherDate', '>=', $filters['date_from']))
                ->when(!empty($filters['date_to']), fn ($q) => $q->whereDate('voucherDate', '<=', $filters['date_to']))
                ->orderBy('voucherDate')
                ->orderBy('receiptID')
                ->get();

            foreach ($receipts as $v) {
                $rows[] = [
                    'id'             => 'R' . $v->receiptID,
                    'date'           => $v->voucherDate?->format('Y-m-d'),
                    'voucher_type'   => 'قبض',
                    'voucher_no'     => $v->voucherNumber ?? ('RC-' . $v->receiptID),
                    'debit_account'  => $this->accountLabel($v->debitAccount),
                    'credit_account' => $this->accountLabel($v->creditAccount),
                    'entry_no'       => $v->entry ? (string) $v->entry->entryNo : '',
                    'local_amount'   => (float) $v->localAmount,
                ];
            }
        }

        // ── سندات الصرف ──
        if ($wants(2)) {
            $payments = PaymentVoucher::query()
                ->with([
                    'beneficiaryAccount:accountID,accCode,accName',
                    'paymentAccount:accountID,accCode,accName',
                ])
                ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('voucherDate', '>=', $filters['date_from']))
                ->when(!empty($filters['date_to']), fn ($q) => $q->whereDate('voucherDate', '<=', $filters['date_to']))
                ->orderBy('voucherDate')
                ->orderBy('paymentID')
                ->get();

            foreach ($payments as $v) {
                $rows[] = [
                    'id'             => 'P' . $v->paymentID,
                    'date'           => $v->voucherDate?->format('Y-m-d'),
                    'voucher_type'   => 'صرف',
                    'voucher_no'     => $v->voucherNumber ?? ('PV-' . $v->paymentID),
                    'debit_account'  => $this->accountLabel($v->beneficiaryAccount),
                    'credit_account' => $this->accountLabel($v->paymentAccount),
                    'entry_no'       => $v->entry ? (string) $v->entry->entryNo : '',
                    'local_amount'   => (float) $v->localAmount,
                ];
            }
        }

        // ── ترتيب زمني موحّد + pagination في الذاكرة (النوعان من جداول مختلفة) ──
        usort($rows, fn ($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);

        $count = count($rows);
        $lastPage = max(1, (int) ceil($count / self::PER_PAGE));
        $page = min($page, $lastPage);
        $rows = array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        foreach ($rows as $row) {
            $total += (float) $row['local_amount'];
        }

        return [
            'rows'   => $rows,
            'totals' => ['local_amount' => $total],
            'meta'   => [
                'pagination' => [
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'per_page'     => self::PER_PAGE,
                    'total'        => $count,
                ],
            ],
        ];
    }

    /**
     * تنسيق اسم الحساب (رمز - اسم).
     */
    private function accountLabel($account): string
    {
        if (!$account) {
            return '';
        }

        return trim(($account->accCode ?? '') . ' - ' . ($account->accName ?? ''));
    }
}
