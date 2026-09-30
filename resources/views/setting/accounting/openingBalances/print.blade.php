<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأرصدة الافتتاحية - {{ $typeLabel }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            direction: rtl;
            margin: 0;
            padding: 0;
            color: #212529;
            font-size: 11px;
        }

        .page {
            padding: 10px;
            page-break-after: always;
            position: relative;
            min-height: 275mm;
        }

        .page:last-child { page-break-after: auto; }

        /* ─── الترويسة ─── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .header-title h1 {
            font-size: 14px;
            margin: 0 0 2px 0;
            color: #0d6efd;
        }

        .header-title h2 {
            font-size: 12px;
            margin: 0;
            color: #495057;
            font-weight: 500;
        }

        .header-meta {
            font-size: 10px;
            color: #6c757d;
            text-align: left;
            line-height: 1.5;
        }

        /* ─── شريط الملخص ─── */
        .summary-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            padding: 8px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }

        .summary-item {
            flex: 1;
            text-align: center;
            padding: 4px;
            border-left: 1px solid #dee2e6;
        }

        .summary-item:first-child { border-left: none; }

        .summary-item .label {
            display: block;
            font-size: 9px;
            color: #6c757d;
            margin-bottom: 3px;
        }

        .summary-item .value {
            font-size: 12px;
            font-weight: 700;
            direction: ltr;
            display: inline-block;
        }

        .summary-item .value.debit  { color: #198754; }
        .summary-item .value.credit { color: #dc3545; }
        .summary-item .value.net    { color: #0d6efd; }

        /* ─── الجدول ─── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            table-layout: fixed;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #dee2e6;
            padding: 4px 5px;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .data-table th {
            background: #e7f1ff;
            color: #0d6efd;
            font-weight: 700;
            font-size: 10px;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .data-table tfoot td {
            background: #e7f1ff;
            font-weight: 700;
            color: #0d6efd;
        }

        /* ─── أعمدة ─── */
        .col-num      { width: 4%; }
        .col-code     { width: 11%; }
        .col-name     { width: 23%; text-align: right; }
        .col-currency { width: 6%; }
        .col-rate     { width: 7%; }
        .col-amount   { width: 12%; text-align: right; direction: ltr; font-family: 'Consolas', monospace; font-weight: 600; }
        .col-notes    { width: 13%; text-align: right; font-size: 9px; }

        .col-amount.net { color: #0d6efd; }

        .total-label {
            text-align: right;
            background: #e7f1ff;
            color: #0d6efd;
            font-weight: 700;
        }

        /* ─── التذييل ─── */
        .page-footer {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px dashed #adb5bd;
        }

        .signatures {
            display: flex;
            justify-content: space-around;
            margin-bottom: 12px;
        }

        .sig-box {
            text-align: center;
            width: 28%;
        }

        .sig-line {
            border-bottom: 1px solid #495057;
            height: 35px;
            margin-bottom: 4px;
        }

        .sig-label {
            font-size: 10px;
            color: #495057;
            font-weight: 600;
        }

        .footer-info {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #6c757d;
            padding-top: 6px;
            border-top: 1px solid #e9ecef;
        }

        /* ─── لا توجد بيانات ─── */
        .no-data {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-style: italic;
        }

        @media print {
            body { padding: 0; }
            .page { padding: 0; min-height: auto; }
        }
    </style>
</head>
<body>
@php
    $rowsPerPage = 20;

    $pages = array_chunk($rows, $rowsPerPage);

    if (empty($pages)) {
        $pages = [[]];
    }

    $totalPages = count($pages);

    $fmt = function ($v) {
        return number_format((float) $v, 2, '.', ',');
    };

    $printDateStr = $printDate->format('Y/m/d H:i');
@endphp

@foreach($pages as $pageIndex => $pageRows)
    @php
        $isFirstPage = $pageIndex === 0;
        $isLastPage  = $pageIndex === $totalPages - 1;
        $startIndex  = $pageIndex * $rowsPerPage;
    @endphp

    <div class="page">

        {{-- الترويسة --}}
        <header class="page-header">
            <div class="header-title">
                <h1>{{ $companyName }}</h1>
                <h2>الأرصدة الافتتاحية - {{ $typeLabel }}</h2>
            </div>
            <div class="header-meta">
                <div><strong>التاريخ:</strong> {{ $printDateStr }}</div>
                <div><strong>الصفحة:</strong> {{ $pageIndex + 1 }} / {{ $totalPages }}</div>
                <div><strong>المستخدم:</strong> {{ $userName }}</div>
            </div>
        </header>

        {{-- شريط الملخص (الصفحة الأولى فقط) --}}
        @if($isFirstPage)
            <div class="summary-bar">
                <div class="summary-item">
                    <span class="label">عدد السجلات</span>
                    <span class="value">{{ count($rows) }}</span>
                </div>
                <div class="summary-item">
                    <span class="label">إجمالي المدين</span>
                    <span class="value debit">{{ $fmt($totals['local_debit'] ?? 0) }}</span>
                </div>
                <div class="summary-item">
                    <span class="label">إجمالي الدائن</span>
                    <span class="value credit">{{ $fmt($totals['local_credit'] ?? 0) }}</span>
                </div>
                <div class="summary-item">
                    <span class="label">الصافي</span>
                    <span class="value net">{{ $fmt($totals['local_net'] ?? 0) }}</span>
                </div>
            </div>
        @endif

        {{-- الجدول --}}
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-code">رقم الحساب</th>
                    <th class="col-name">اسم الحساب</th>
                    <th class="col-currency">العملة</th>
                    <th class="col-rate">السعر</th>
                    <th class="col-amount">مدين</th>
                    <th class="col-amount">دائن</th>
                    <th class="col-amount">الرصيد</th>
                    <th class="col-notes">ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                @if(empty($pageRows))
                    <tr>
                        <td colspan="9" class="no-data">
                            لا توجد بيانات للطباعة
                        </td>
                    </tr>
                @else
                    @foreach($pageRows as $i => $row)
                        @php
                            $isForeign = !empty($row['currency_code'])
                                && $row['currency_code'] !== $systemCurrencyCode;

                            $rate = $isForeign ? $fmt($row['exchange_rate']) : '—';
                        @endphp
                        <tr>
                            <td class="col-num">{{ $startIndex + $i + 1 }}</td>
                            <td class="col-code">{{ $row['entity_code'] ?? $row['account_code'] ?? '—' }}</td>
                            <td class="col-name">{{ $row['entity_name'] ?? $row['account_name'] ?? '—' }}</td>
                            <td class="col-currency">{{ $row['currency_code'] ?? '—' }}</td>
                            <td class="col-rate">{{ $rate }}</td>
                            <td class="col-amount">{{ $fmt($row['local_debit']  ?? 0) }}</td>
                            <td class="col-amount">{{ $fmt($row['local_credit'] ?? 0) }}</td>
                            <td class="col-amount net">{{ $fmt($row['local_net'] ?? 0) }}</td>
                            <td class="col-notes">{{ $row['notes'] ?? '' }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>

            @if($isLastPage && count($rows) > 0)
                <tfoot>
                    <tr>
                        <td colspan="5" class="total-label">الإجماليات:</td>
                        <td class="col-amount">{{ $fmt($totals['local_debit']  ?? 0) }}</td>
                        <td class="col-amount">{{ $fmt($totals['local_credit'] ?? 0) }}</td>
                        <td class="col-amount net">{{ $fmt($totals['local_net'] ?? 0) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>

        {{-- التذييل (الصفحة الأخيرة فقط) --}}
        @if($isLastPage && count($rows) > 0)
            <footer class="page-footer">
                <div class="signatures">
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">المحاسب</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">المراجع</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">المدير المالي</div>
                    </div>
                </div>
                <div class="footer-info">
                    <span>{{ $companyName }}</span>
                    <span>طُبع في: {{ $printDateStr }}</span>
                </div>
            </footer>
        @endif

    </div>
@endforeach

<script>
    window.onload = function () {
        setTimeout(function () { window.print(); }, 400);
    };
</script>
</body>
</html>