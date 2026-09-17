<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة بيع رقم {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 11pt;
            direction: rtl;
            color: #000;
            background: #fff;
            padding: 15px;
            padding-bottom: 60px;
        }

        /* ============================================================
           Header
           ============================================================ */
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
        .doc-type {
            font-size: 16pt;
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 5px;
        }
        .company-name {
            font-size: 13pt;
            font-weight: 700;
            color: #333;
        }
        .meta-line {
            font-size: 10pt;
            color: #555;
            line-height: 1.6;
            font-weight: 600;
        }

        /* ============================================================
           Info Grid
           ============================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 15px;
        }
        .info-item {
            border: 1px solid #dee2e6;
            padding: 6px 10px;
            font-size: 10pt;
        }
        .info-label {
            color: #6c757d;
            font-size: 9pt;
            display: block;
            margin-bottom: 2px;
            font-weight: 600;
        }
        .info-value {
            font-weight: 700;
            color: #000;
        }

        /* ============================================================
           Items Table
           ============================================================ */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10pt;
        }
        .items-table thead th {
            background: #e7f1ff;
            color: #0d6efd;
            font-weight: 700;
            padding: 7px 6px;
            border: 1px solid #b6d4fe;
            text-align: center;
            font-size: 10pt;
        }
        .items-table tbody td {
            padding: 6px;
            border: 1px solid #dee2e6;
            text-align: center;
            color: #000;
            font-weight: 600;
        }
        .items-table tbody td.text-start { text-align: right; }

        /* عمود الإجمالي — أخضر الآن */
        .items-table tbody td.col-total {
            color: #198754;
            font-weight: 700;
        }

        .items-table tbody tr:nth-child(even) { background: #f8f9fa; }

        /* منع تقسيم الصف بين صفحتين */
        .items-table tbody tr {
            page-break-inside: avoid;
        }

        /* ============================================================
           Totals
           ============================================================ */
        .totals {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10pt;
        }
        .totals td {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            font-weight: 700;
            vertical-align: middle;
        }
        .totals .label {
            background: #f8f9fa;
            width: 25%;
            text-align: right;
        }
        .totals .value {
            text-align: left;
            width: 25%;
        }

        /* الصف الأول — إجمالي الأصناف + عدد الأصناف — أخضر الآن */
        .totals .row-items .label,
        .totals .row-items .value {
            color: #198754;
        }

        /* الصف الثاني — إجمالي الخصم + البيان — أحمر الآن */
        .totals .row-discount .label,
        .totals .row-discount .value {
            color: #dc3545;
        }

        /* خلية البيان المدمجة */
        .totals .statement-cell {
            background: #fff;
            color: #000;
            text-align: right;
            font-weight: 600;
        }
        .totals .statement-cell .statement-label {
            color: #6c757d;
            font-weight: 700;
            margin-left: 4px;
        }

        /* الصف الثالث — إجمالي الفاتورة — أخضر الآن */
        .totals .row-grand .label,
        .totals .row-grand .value {
            background: #e7f1ff;
            color: #198754;
            font-size: 12pt;
            font-weight: 700;
        }

        /* ============================================================
           Amount in words
           ============================================================ */
        .amount-words {
            border: 1px solid #dee2e6;
            padding: 10px 12px;
            background: #f8f9fa;
            font-size: 10pt;
            margin-bottom: 20px;
            line-height: 1.7;
            font-weight: 600;
        }
        .amount-words strong {
            color: #0d6efd;
            font-weight: 700;
        }
        .amount-words .only-text {
            color: #0d6efd;
            font-weight: 700;
        }

        /* ============================================================
           Signatures
           ============================================================ */
        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            margin-top: 30px;
            padding-top: 15px;
            page-break-inside: avoid;
        }
        .signature {
            text-align: center;
            padding-top: 45px;
            font-size: 10pt;
            font-weight: 600;
            position: relative;
        }
        .signature::before {
            content: '';
            position: absolute;
            top: 42px;
            left: 0;
            right: 0;
            border-top: 1px dotted #999;
        }

        /* ============================================================
           Footer — ثابت أسفل الصفحة
           ============================================================ */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 8px 15px;
            background: #fff;
            border-top: 1px solid #dee2e6;
            font-size: 9pt;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            z-index: 100;
        }
        .footer-left {
            text-align: right;
            color: #6c757d;
            flex: 1;
        }
        .footer-center {
            text-align: center;
            color: #6c757d;
            flex: 1;
        }
        /* المستخدم — أخضر الآن */
        .footer-right {
            text-align: left;
            color: #198754;
            font-weight: 700;
            flex: 1;
        }

        /* ============================================================
           Print
           ============================================================ */
        @media print {
            html, body {
                height: auto;
            }

            body {
                padding: 0;
                padding-bottom: 40px;
            }

            .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 6px 0;
                border-top: 1px solid #dee2e6;
                background: #fff;
            }

            .no-print { display: none !important; }

            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

@php
    use App\Helpers\Tafqeet;

    $currencyName = $invoice->coin->coinsName ?? '';
    $totalWords = Tafqeet::numberToWords(
        (float) $invoice->total_in_invoice_currency,
        $currencyName
    );

    // استبدال "فقط لا غير" بـ span أزرق
    $totalWordsHtml = str_replace(
        'فقط لا غير',
        '<span class="only-text">فقط لا غير</span>',
        e($totalWords)
    );

    $paymentLabels = [1 => 'أجل', 2 => 'نقد', 3 => 'بنك', 4 => 'شبكة'];

    // عدد الأصناف
    $itemsCount = $invoice->details->count();
@endphp

{{-- Header --}}
<div class="header">
    <div class="header-right">
        <div class="doc-type">فاتورة بيع</div>
        @if(config('company.name'))
            <div class="company-name">{{ config('company.name') }}</div>
        @endif
        @if(config('company.address'))
            <div class="meta-line">{{ config('company.address') }}</div>
        @endif
        @if(config('company.phone'))
            <div class="meta-line">هاتف: {{ config('company.phone') }}</div>
        @endif
    </div>

    <div class="header-left">
        <div class="meta-line">رقم الفاتورة: <strong>{{ $invoice->invoice_number }}</strong></div>
        <div class="meta-line">التاريخ: <strong>{{ $invoice->invoice_date?->format('Y-m-d') }}</strong></div>
    </div>
</div>

{{-- Info Grid --}}
<div class="info-grid">
    <div class="info-item">
        <span class="info-label">العميل</span>
        <span class="info-value">{{ $invoice->customerAccount->accName ?? '—' }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">العملة</span>
        <span class="info-value">{{ $currencyName ?: '—' }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">طريقة الدفع</span>
        <span class="info-value">{{ $paymentLabels[$invoice->payment_method] ?? '—' }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">المرجع</span>
        <span class="info-value">{{ $invoice->reference ?? '—' }}</span>
    </div>
</div>

{{-- Items Table --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 5%;">الرقم</th>
            <th style="width: 22%;">الصنف</th>
            <th style="width: 15%;">النوع</th>
            <th style="width: 12%;">الرمز</th>
            <th style="width: 10%;">الوحدة</th>
            <th style="width: 10%;">الكمية</th>
            <th style="width: 12%;">السعر</th>
            <th style="width: 14%;">الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invoice->details as $index => $detail)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="text-start">{{ $detail->item->itemName2 ?? '—' }}</td>
                <td>{{ $detail->type->name ?? '—' }}</td>
                <td>{{ $detail->code ?? '—' }}</td>
                <td>{{ $detail->unit->UnitName ?? '—' }}</td>
                <td>{{ number_format((float) $detail->quantity, 2) }}</td>
                <td>{{ number_format((float) $detail->price, 2) }}</td>
                <td class="col-total">{{ number_format((float) $detail->total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center; padding: 20px; color: #6c757d;">
                    لا توجد أصناف
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- Totals --}}
<table class="totals">
    <tr class="row-items">
        <td class="label">إجمالي الأصناف</td>
        <td class="value">{{ number_format((float) $invoice->items_total, 2) }}</td>
        <td class="label">عدد الأصناف</td>
        <td class="value">{{ $itemsCount }}</td>
    </tr>
    <tr class="row-discount">
        <td class="label">إجمالي الخصم</td>
        <td class="value">{{ number_format((float) $invoice->discount_total, 2) }}</td>
        <td colspan="2" class="statement-cell">
            <span class="statement-label">البيان:</span>
            {{ $invoice->statement ?: '—' }}
        </td>
    </tr>
    <tr class="row-grand">
        <td colspan="3" class="label">إجمالي الفاتورة</td>
        <td class="value">{{ number_format((float) $invoice->total_in_invoice_currency, 2) }}</td>
    </tr>
</table>

{{-- Amount in Words --}}
<div class="amount-words">
    <strong>المبلغ كتابة:</strong>
    {!! $totalWordsHtml !!}
</div>

{{-- Signatures --}}
<div class="signatures">
    <div class="signature">المستلم</div>
    <div class="signature">المخازن</div>
    <div class="signature">المالية</div>
</div>

{{-- Footer — ثابت أسفل الصفحة --}}
<div class="footer">
    <span class="footer-left">جميع الحقوق محفوظة لشركة بيتاسيس ©</span>
    <span class="footer-center">رقم الصفحة: 1</span>
    <span class="footer-right">المستخدم: مدير النظام</span>
</div>

{{-- Print Buttons (لا تُطبع) --}}
<div class="no-print" style="text-align: center; margin-top: 40px;">
    <button onclick="window.print()" style="
        padding: 10px 30px;
        font-size: 14px;
        background: #0d6efd;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 700;
    ">
        🖨️ طباعة
    </button>
    <button onclick="window.close()" style="
        padding: 10px 30px;
        font-size: 14px;
        background: #6c757d;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        margin-right: 10px;
        font-weight: 700;
    ">
        إغلاق
    </button>
</div>

</body>
</html>