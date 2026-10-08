<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 11pt;
            direction: rtl;
            color: #000;
            background: #fff;
            padding: 15px;
            padding-bottom: 60px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 10px;
            border-bottom: 2px solid #0d6efd;
            margin-bottom: 15px;
        }
        .header-right { text-align: right; }
        .header-left  { text-align: left; }
        .doc-type { font-size: 16pt; font-weight: 700; color: #0d6efd; margin-bottom: 5px; }
        .company-name { font-size: 13pt; font-weight: 700; color: #333; }
        .meta-line { font-size: 10pt; color: #555; line-height: 1.6; font-weight: 600; }

        /* Filters Summary */
        .filters-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
        .filter-chip {
            border: 1px solid #b6d4fe;
            background: #e7f1ff;
            color: #084298;
            border-radius: 12px;
            padding: 3px 12px;
            font-size: 9.5pt;
            font-weight: 600;
        }

        /* Report Table */
        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10pt; }
        .report-table thead th {
            background: #e7f1ff;
            color: #0d6efd;
            font-weight: 700;
            padding: 7px 6px;
            border: 1px solid #b6d4fe;
            text-align: center;
        }
        .report-table tbody td { padding: 6px; border: 1px solid #dee2e6; text-align: center; font-weight: 600; }
        .report-table tbody td.text-start { text-align: right; }
        .report-table tbody td.text-end   { text-align: left; }
        .report-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .report-table tfoot td {
            background: #fff3cd;
            border: 1px solid #ffda6a;
            font-weight: 700;
            text-align: center;
            padding: 7px 6px;
        }
        .report-table tfoot td.text-end { text-align: left; }
        .no-data { text-align: center; padding: 25px !important; color: #6c757d; }
        .report-table tr { page-break-inside: avoid; }

        /* Totals */
        .totals { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10.5pt; }
        .totals td { border: 1px solid #dee2e6; padding: 7px 10px; }
        .totals td.label { background: #f8f9fa; color: #555; font-weight: 600; width: 25%; }
        .totals td.value { font-weight: 700; text-align: left; direction: ltr; }

        /* Signatures + Footer */
        .signatures { display: flex; justify-content: space-around; margin-top: 40px; page-break-inside: avoid; }
        .signature {
            text-align: center;
            font-weight: 700;
            font-size: 11pt;
            min-width: 130px;
            border-top: 1px dashed #000;
            padding-top: 6px;
        }
        .footer {
            position: fixed;
            bottom: 0; right: 15px; left: 15px;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #dee2e6;
            padding-top: 5px;
            font-size: 8.5pt;
            color: #6c757d;
            background: #fff;
        }

        .no-print { margin-top: 30px; text-align: center; }
        .no-print button {
            padding: 10px 30px;
            font-size: 14px;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            margin: 0 5px;
        }
        .btn-print { background: #0d6efd; }
        .btn-closew { background: #6c757d; }

        /* ═══════════════ Print-specific rules ═══════════════ */
        @media print {
            .no-print { display: none !important; }
            body { padding-bottom: 40px; }
        }

        /* ═══════════════ Amount colors (Fix #7) ═══════════════ */
        .amount-debit  { color: #dc3545; font-weight: 600; } /* مدين */
        .amount-credit { color: #198754; font-weight: 600; } /* دائن */
        .amount-pos    { color: #198754; font-weight: 600; } /* رصيد موجب */
        .amount-neg    { color: #dc3545; font-weight: 600; } /* رصيد سالب */

        /* R2/M5: ألوان من حقل column.color الصريح */
        .amount-red    { color: #dc3545; font-weight: 600; }
        .amount-green  { color: #198754; font-weight: 600; }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="header-right">
        <div class="doc-type">{{ $report->title() }}</div>
        <div class="company-name">نظام إدارة الموارد ERP</div>
        <div class="meta-line">{{ $report->description() }}</div>
    </div>
    <div class="header-left">
        <div class="meta-line">تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</div>
        <div class="meta-line">عدد السجلات: {{ count($rows) }}</div>
    </div>
</div>

{{-- الفلاتر المُطبَّقة --}}
@php
    $filterLabels = collect($report->filters())->pluck('label', 'key');
    $applied = collect($filters)
        ->except(['page'])
        ->filter(fn ($value, $key) => $value !== '' && $value !== null && $filterLabels->has($key));
@endphp

@if ($applied->isNotEmpty())
    <div class="filters-bar">
        @foreach ($applied as $key => $value)
            <span class="filter-chip">
                {{ $filterLabels[$key] }}: {{ $value }}
            </span>
        @endforeach
    </div>
@endif

{{-- جدول التقرير --}}
<table class="report-table">
    <thead>
        <tr>
            @foreach ($columns as $column)
                <th>{{ $column['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($columns as $column)
                    @php
                        $value = $row[$column['key']] ?? null;
                        $type = $column['type'] ?? 'text';
                        $alignClass = in_array($type, ['money', 'number'])
                            ? 'text-end'
                            : ($type === 'date' ? '' : 'text-start');

                        // Fix #7 + R2/M5 — لون خلية المبلغ: color الصريح أولاً ثم مفتاح العمود
                        $amountClass = '';
                        if ($value !== null && $value !== '' && (float) $value != 0) {
                            if (($column['color'] ?? '') === 'red') {
                                $amountClass = 'amount-red';
                            } elseif (($column['color'] ?? '') === 'green') {
                                $amountClass = 'amount-green';
                            } elseif ($column['key'] === 'debit' && (float) $value > 0) {
                                $amountClass = 'amount-debit';
                            } elseif ($column['key'] === 'credit' && (float) $value > 0) {
                                $amountClass = 'amount-credit';
                            } elseif ($column['key'] === 'balance') {
                                $amountClass = (float) $value < 0 ? 'amount-neg' : 'amount-pos';
                            }
                        }
                    @endphp
                    <td class="{{ trim($alignClass . ' ' . $amountClass) }}">
                        @if ($value === null || $value === '')
                            —
                        @elseif ($type === 'money')
                            {{ number_format((float) $value, 2) }}
                        @elseif ($type === 'number')
                            {{ number_format((float) $value, 3) }}
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td class="no-data" colspan="{{ count($columns) }}">
                    لا توجد بيانات مطابقة للفلاتر المحددة
                </td>
            </tr>
        @endforelse
    </tbody>
    @if (count($rows) > 0 && collect($columns)->contains(fn ($c) => !empty($c['footer'])))
        <tfoot>
            <tr>
                @foreach ($columns as $column)
                    @php
                        $footer = $column['footer'] ?? '';
                        $totalValue = $totals[$column['key']] ?? null;
                        $alignClass = in_array($column['type'] ?? '', ['money', 'number']) ? 'text-end' : '';

                        // Fix #7 + R2/M5 — لون خلية المبلغ في الإجماليات: color الصريح أولاً ثم مفتاح العمود
                        $amountClass = '';
                        if ($totalValue !== null && (float) $totalValue != 0) {
                            if (($column['color'] ?? '') === 'red') {
                                $amountClass = 'amount-red';
                            } elseif (($column['color'] ?? '') === 'green') {
                                $amountClass = 'amount-green';
                            } elseif ($column['key'] === 'debit' && (float) $totalValue > 0) {
                                $amountClass = 'amount-debit';
                            } elseif ($column['key'] === 'credit' && (float) $totalValue > 0) {
                                $amountClass = 'amount-credit';
                            } elseif ($column['key'] === 'balance') {
                                $amountClass = (float) $totalValue < 0 ? 'amount-neg' : 'amount-pos';
                            }
                        }
                    @endphp
                    <td class="{{ trim($alignClass . ' ' . $amountClass) }}">
                        @if ($footer === 'sum' && $totalValue !== null)
                            {{ number_format((float) $totalValue, ($column['type'] ?? '') === 'number' ? 3 : 2) }}
                        @elseif ($footer === 'last')
                            {{ $totalValue !== null ? number_format((float) $totalValue, 2) : '—' }}
                        @else
                            &nbsp;
                        @endif
                    </td>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>

{{-- الإجماليات --}}
@if (count($totals) > 0)
    <table class="totals">
        @foreach ($totals as $key => $value)
            @php $column = collect($columns)->firstWhere('key', $key); @endphp
            <tr>
                <td class="label">{{ $column['label'] ?? $key }}</td>
                <td class="value">{{ number_format((float) $value, 2) }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- التوقيعات --}}
<div class="signatures">
    <div class="signature">المحاسب</div>
    <div class="signature">المراجع</div>
    <div class="signature">مدير الحسابات</div>
</div>

{{-- التذييل --}}
<div class="footer">
    <span>نظام ERP — مركز التقارير</span>
    <span>{{ $report->title() }}</span>
    <span>{{ now()->format('Y-m-d') }}</span>
</div>

{{-- أزرار الطباعة (لا تُطبع) --}}
<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ طباعة</button>
    <button class="btn-closew" onclick="window.close()">إغلاق</button>
</div>

</body>
</html>