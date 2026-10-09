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

        // فلاتر مشتركة فقط — بدون with/orderBy (لـ clone نظيف للإجماليات والعدّاد)
        $query = PurchaseInvoice::query()
            ->when($supplierId > 0, fn ($q) => $q->where('account_id', $supplierId))
            ->when($paymentMethod > 0, fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when(!empty($filters['date_from']), fn ($q) => $q->where('invoice_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn ($q) => $q->where('invoice_date', '<=', $filters['date_to']));

        // ── الإجماليات على كل النتائج المطابقة (ليس الصفحة فقط) ──
        // نظّف الأعمدة قبل إضافة عمودي SUM (وإلا خلط MySQL بينهما ورفض الاستعلام)
        // total_local = العمود الخام total_in_base_currency (ليس الـ accessor)
        $totalsRow = (clone $query)->toBase()
            ->cloneWithout(['columns'])
            ->selectRaw('COALESCE(SUM(items_total), 0) as items_sum')
            ->selectRaw('COALESCE(SUM(discount_total), 0) as discount_sum')
            ->selectRaw('COALESCE(SUM(total_in_base_currency), 0) as total_local_sum')
            ->first();

        $itemsTotal    = round((float) ($totalsRow->items_sum ?? 0), 2);
        $discountTotal = round((float) ($totalsRow->discount_sum ?? 0), 2);
        $totalLocal    = round((float) ($totalsRow->total_local_sum ?? 0), 2);

        $total = (clone $query)->count();

        // B1: الطباعة/التصدير (all=true) تتجاوز الترقيم — كل الصفوف حتى حدّ export_max_rows
        $perPage = !empty($filters['_all'])
            ? max(1, min($total, (int) config('reports.export_max_rows', 50000)))
            : self::PER_PAGE;

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $invoices = $query
            ->with([
                'supplierAccount:accountID,accCode,accName',
                'coin:coinsID,coinsName',
            ])
            ->orderBy('invoice_date')
            ->orderBy('purchase_invoice_id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $paymentLabels = [
            PurchaseInvoice::PAYMENT_CREDIT  => 'آجل',
            PurchaseInvoice::PAYMENT_CASH    => 'نقد',
            PurchaseInvoice::PAYMENT_BANK    => 'بنك',
            PurchaseInvoice::PAYMENT_NETWORK => 'شبكة',
        ];

        $rows = [];

        foreach ($invoices as $invoice) {
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
                'items_total'    => (float) $invoice->items_total,
                'discount_total' => (float) $invoice->discount_total,
                'total_local'    => (float) $invoice->total_in_base_currency,
            ];
        }

        return [
            'rows'   => $rows,
            'totals' => [
                'items_total'    => $itemsTotal,
                'discount_total' => $discountTotal,
                'total_local'    => $totalLocal,
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
