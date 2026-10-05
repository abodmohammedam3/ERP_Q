<?php

namespace App\Reports\Purchases;

use App\Models\Purchases\PurchaseInvoice;
use App\Reports\Contracts\Report;

/**
 * تقرير فواتير الشراء.
 *
 * جميع فواتير الشراء خلال فترة (مع فلاتر المورد وطريقة الدفع)
 * مع الإجماليات — الإجمالي الكلي أخضر والخصم أحمر.
 */
class PurchaseInvoicesReport implements Report
{
    private const PER_PAGE = 50;

    public function key(): string
    {
        return 'purchase-invoices';
    }

    public function title(): string
    {
        return 'فواتير الشراء';
    }

    public function description(): string
    {
        return 'جميع فواتير الشراء خلال فترة مع الإجماليات';
    }

    public function icon(): string
    {
        return 'receipt';
    }

    public function category(): string
    {
        return 'purchases';
    }

    public function filters(): array
    {
        return [
            [
                'key'    => 'supplier_id',
                'label'  => 'المورد',
                'type'   => 'item',
                'source' => 'suppliers',
                'col'    => 'col-md-3',
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
            [
                'key'    => 'payment_method',
                'label'  => 'طريقة الدفع',
                'type'   => 'select',
                'source' => 'paymentMethods',
                'col'    => 'col-md-2',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'invoice_number',  'label' => 'رقم الفاتورة',   'type' => 'text'],
            ['key' => 'invoice_date',    'label' => 'التاريخ',         'type' => 'date'],
            ['key' => 'supplier',        'label' => 'المورد',          'type' => 'text'],
            ['key' => 'payment_method',  'label' => 'طريقة الدفع',     'type' => 'text'],
            ['key' => 'currency',        'label' => 'العملة',          'type' => 'text'],
            ['key' => 'statement',       'label' => 'البيان',          'type' => 'text'],
            ['key' => 'items_total',     'label' => 'إجمالي الأصناف',  'type' => 'money', 'footer' => 'sum'],
            ['key' => 'discount_total',  'label' => 'الخصم',           'type' => 'money', 'footer' => 'sum', 'color' => 'red'],
            ['key' => 'total_local',     'label' => 'الإجمالي (محلي)', 'type' => 'money', 'footer' => 'sum', 'color' => 'green'],
        ];
    }

    public function run(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        $paymentMethod = (int) ($filters['payment_method'] ?? 0);

        $query = PurchaseInvoice::query()
            ->with([
                'supplierAccount:accountID,accCode,accName',
                'coin:coinsID,coinsName',
            ])
            ->when($supplierId > 0, fn ($q) => $q->where('account_id', $supplierId))
            ->when($paymentMethod > 0, fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('invoice_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn ($q) => $q->whereDate('invoice_date', '<=', $filters['date_to']));

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);

        $invoices = $query
            ->orderBy('invoice_date')
            ->orderBy('purchase_invoice_id')
            ->skip(($page - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE)
            ->get();

        $paymentLabels = [
            PurchaseInvoice::PAYMENT_CREDIT  => 'آجل',
            PurchaseInvoice::PAYMENT_CASH    => 'نقد',
            PurchaseInvoice::PAYMENT_BANK    => 'بنك',
            PurchaseInvoice::PAYMENT_NETWORK => 'شبكة',
        ];

        $rows = [];
        $sumItems = 0.0;
        $sumDiscount = 0.0;
        $sumTotalLocal = 0.0;

        foreach ($invoices as $invoice) {
            $itemsTotal = (float) $invoice->items_total;
            $discountTotal = (float) $invoice->discount_total;
            $totalLocal = (float) $invoice->total_in_base_currency;

            $sumItems += $itemsTotal;
            $sumDiscount += $discountTotal;
            $sumTotalLocal += $totalLocal;

            $rows[] = [
                'id'             => (int) $invoice->purchase_invoice_id,
                'invoice_number' => $invoice->invoice_number ?? '',
                'invoice_date'   => $invoice->invoice_date?->format('Y-m-d'),
                'supplier'       => $invoice->supplierAccount
                    ? trim(($invoice->supplierAccount->accCode ?? '') . ' - ' . ($invoice->supplierAccount->accName ?? ''))
                    : '',
                'payment_method' => $paymentLabels[$invoice->payment_method] ?? '',
                'currency'       => $invoice->coin->coinsName ?? '',
                'statement'      => $invoice->statement ?? '',
                'items_total'    => $itemsTotal,
                'discount_total' => $discountTotal,
                'total_local'    => $totalLocal,
            ];
        }

        return [
            'rows'   => $rows,
            'totals' => [
                'items_total'    => $sumItems,
                'discount_total' => $sumDiscount,
                'total_local'    => $sumTotalLocal,
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
