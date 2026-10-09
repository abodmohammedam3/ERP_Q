<?php

namespace App\Reports\Sales;

use App\Models\Sales\SalesInvoice;
use App\Reports\Contracts\Report;

/**
 * تقرير فواتير البيع.
 *
 * جميع فواتير البيع خلال فترة (مع فلاتر العميل وطريقة الدفع)
 * مع الإجماليات — الإجمالي الكلي أخضر والخصم أحمر.
 */
class SalesInvoicesReport implements Report
{
    private const PER_PAGE = 50;

    public function key(): string
    {
        return 'sales-invoices';
    }

    public function title(): string
    {
        return 'فواتير البيع';
    }

    public function description(): string
    {
        return 'جميع فواتير البيع خلال فترة مع الإجماليات';
    }

    public function icon(): string
    {
        return 'receipt';
    }

    public function category(): string
    {
        return 'sales';
    }

    public function filters(): array
    {
        return [
            [
                'key'    => 'customer_id',
                'label'  => 'العميل',
                'type'   => 'item',
                'source' => 'customers',
                'placeholder' => 'اختر العميل...',
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
                'source' => 'paymentMethodsSales',
                'col'    => 'col-md-2',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'invoice_number',  'label' => 'رقم الفاتورة',   'type' => 'text'],
            ['key' => 'invoice_date',    'label' => 'التاريخ',         'type' => 'date'],
            ['key' => 'customer',        'label' => 'العميل',          'type' => 'text'],
            ['key' => 'payment_method',  'label' => 'طريقة الدفع',     'type' => 'text'],
            ['key' => 'currency',        'label' => 'العملة',          'type' => 'text'],
            ['key' => 'statement',       'label' => 'البيان',          'type' => 'text'],
            ['key' => 'items_total',     'label' => 'إجمالي الأصناف',  'type' => 'money', 'footer' => 'sum'],
            ['key' => 'discount_total',  'label' => 'الخصم',           'type' => 'money', 'footer' => 'sum', 'color' => 'red'],
            ['key' => 'total',           'label' => 'الإجمالي',        'type' => 'money', 'footer' => 'sum', 'color' => 'green'],
        ];
    }

    public function run(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $customerId = (int) ($filters['customer_id'] ?? 0);
        $paymentMethod = (int) ($filters['payment_method'] ?? 0);

        // فلاتر مشتركة فقط — بدون with/orderBy (لت Clone نظيف للإجماليات والعدّاد)
        $query = SalesInvoice::query()
            ->when($customerId > 0, fn ($q) => $q->where('account_id', $customerId))
            ->when($paymentMethod > 0, fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when(!empty($filters['date_from']), fn ($q) => $q->where('invoice_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn ($q) => $q->where('invoice_date', '<=', $filters['date_to']));

        // ── الإجماليات على كل النتائج المطابقة (ليس الصفحة فقط) ──
        // نظّف الأعمدة قبل إضافة عمودي SUM (وإلا خلط MySQL بينهما ورفض الاستعلام)
        $totalsRow = (clone $query)->toBase()
            ->cloneWithout(['columns'])
            ->selectRaw('COALESCE(SUM(items_total), 0) as items_sum')
            ->selectRaw('COALESCE(SUM(discount_total), 0) as discount_sum')
            ->first();

        // total = items − discount (نفس معادلة accessor total_in_invoice_currency)
        $itemsTotal    = round((float) ($totalsRow->items_sum ?? 0), 2);
        $discountTotal = round((float) ($totalsRow->discount_sum ?? 0), 2);
        $grandTotal    = round($itemsTotal - $discountTotal, 2);

        $total = (clone $query)->count();

        // B1: الطباعة/التصدير (all=true) تتجاوز الترقيم — كل الصفوف حتى حدّ export_max_rows
        $perPage = !empty($filters['_all'])
            ? max(1, min($total, (int) config('reports.export_max_rows', 50000)))
            : self::PER_PAGE;

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $invoices = $query
            ->with([
                'customerAccount:accountID,accCode,accName',
                'coin:coinsID,coinsName',
            ])
            ->orderBy('invoice_date')
            ->orderBy('sales_invoice_id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $paymentLabels = [
            SalesInvoice::PAYMENT_CREDIT  => 'آجل',
            SalesInvoice::PAYMENT_CASH    => 'نقد',
            SalesInvoice::PAYMENT_BANK    => 'بنك',
            SalesInvoice::PAYMENT_NETWORK => 'شبكة',
        ];

        $rows = [];

        foreach ($invoices as $invoice) {
            $rows[] = [
                'id'             => (int) $invoice->sales_invoice_id,
                'invoice_number' => $invoice->invoice_number ?? '',
                'invoice_date'   => $invoice->invoice_date?->format('Y-m-d'),
                'customer'       => $invoice->customerAccount
                    ? trim(($invoice->customerAccount->accCode ?? '') . ' - ' . ($invoice->customerAccount->accName ?? ''))
                    : '',
                'payment_method' => $paymentLabels[$invoice->payment_method] ?? '',
                'currency'       => $invoice->coin->coinsName ?? '',
                'statement'      => $invoice->statement ?? '',
                'items_total'    => (float) $invoice->items_total,
                'discount_total' => (float) $invoice->discount_total,
                'total'          => $invoice->total_in_invoice_currency,
            ];
        }

        return [
            'rows'   => $rows,
            'totals' => [
                'items_total'    => $itemsTotal,
                'discount_total' => $discountTotal,
                'total'          => $grandTotal,
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
