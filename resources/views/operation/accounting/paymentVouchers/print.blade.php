<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سند صرف - {{ $voucher->voucherNumber }}</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            background: #fff;
            color: #212529;
            font-size: 13px;
            padding: 10px;
        }

        .voucher-container {
            max-width: 210mm;
            margin: 0 auto;
            border: 3px double #dc3545;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
        }

        /* ═══ الترويسة ═══ */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-logo {
            width: 60px;
            height: 60px;
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: bold;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #dc3545;
        }

        .company-subtitle {
            font-size: 12px;
            color: #6c757d;
        }

        .voucher-title {
            text-align: center;
        }

        .voucher-title h1 {
            font-size: 22px;
            color: #dc3545;
            border: 2px solid #dc3545;
            padding: 6px 24px;
            border-radius: 8px;
            background: #f8d7da;
            display: inline-block;
        }

        .voucher-title .subtitle {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        /* ═══ بيانات السند ═══ */
        .voucher-info {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 20px;
        }

        .voucher-info .info-item {
            text-align: center;
        }

        .voucher-info .info-item .label {
            font-size: 11px;
            color: #6c757d;
            display: block;
            margin-bottom: 3px;
        }

        .voucher-info .info-item .value {
            font-size: 14px;
            font-weight: bold;
            color: #dc3545;
        }

        /* ═══ الأطراف ═══ */
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .party-box {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px 15px;
            background: #f8f9fa;
        }

        .party-box .party-label {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 5px;
            display: block;
        }

        .party-box .party-value {
            font-size: 14px;
            font-weight: bold;
            color: #212529;
        }

        /* ═══ الجدول ═══ */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: center;
            vertical-align: middle;
        }

        .details-table th {
            background: #f8d7da;
            color: #dc3545;
            font-size: 13px;
            font-weight: bold;
        }

        .details-table td {
            font-size: 13px;
        }

        .details-table .amount-cell {
            font-weight: bold;
            color: #dc3545;
            font-size: 15px;
            direction: ltr;
        }

        .details-table .currency-cell {
            font-weight: bold;
            color: #dc3545;
        }

        /* ═══ المبلغ كتابة ═══ */
        .amount-words-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }

        .amount-words-box .label {
            font-size: 11px;
            color: #856404;
            margin-bottom: 5px;
            display: block;
        }

        .amount-words-box .words {
            font-size: 14px;
            font-weight: bold;
            color: #212529;
        }

        /* ═══ الملاحظات ═══ */
        .notes-box {
            background: #f8f9fa;
            border-right: 4px solid #dc3545;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 20px;
        }

        .notes-box .label {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 3px;
            display: block;
        }

        .notes-box .value {
            font-size: 13px;
            color: #212529;
        }

        /* ═══ التواقيع ═══ */
        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #dee2e6;
        }

        .signature-box {
            text-align: center;
        }

        .signature-box .sig-line {
            border-bottom: 1px solid #212529;
            height: 40px;
            margin-bottom: 8px;
        }

        .signature-box .sig-label {
            font-size: 12px;
            font-weight: bold;
            color: #495057;
        }

        /* ═══ التذييل ═══ */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #6c757d;
        }

        /* ═══ الطباعة ═══ */
        @media print {
            body {
                padding: 0;
                font-size: 12px;
            }

            .voucher-container {
                border: 2px solid #dc3545;
                padding: 15px;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

    <div class="voucher-container">

        <!-- ═══ الترويسة ═══ -->
        <div class="header">
            <div class="company-info">
                <div class="company-logo">ERP</div>
                <div>
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="company-subtitle">نظام إدارة الموارد</div>
                </div>
            </div>

            <div class="voucher-title">
                <h1>سند صرف</h1>
                <div class="subtitle">Payment Voucher</div>
            </div>
        </div>

        <!-- ═══ بيانات السند ═══ -->
        <div class="voucher-info">
            <div class="info-item">
                <span class="label">رقم السند</span>
                <span class="value">{{ $voucher->voucherNumber }}</span>
            </div>
            <div class="info-item">
                <span class="label">التاريخ</span>
                <span class="value">{{ $formattedDate }}</span>
            </div>
            <div class="info-item">
                <span class="label">طريقة الدفع</span>
                <span class="value">{{ $paymentMethodText }}</span>
            </div>
        </div>

        <!-- ═══ الأطراف ═══ -->
        <div class="parties">
            <!-- ⭐ الحساب الدائن: المورد -->
            <div class="party-box">
                <span class="party-label">الحساب الدائن (المورد)</span>
                <span class="party-value">
                    {{ $voucher->creditAccount->accCode ?? '' }} - {{ $voucher->creditAccount->accName ?? '' }}
                </span>
            </div>
            <!-- ⭐ الحساب المدين: الصندوق/البنك -->
            <div class="party-box">
                <span class="party-label">الحساب المدين (الصندوق/البنك)</span>
                <span class="party-value">
                    {{ $voucher->debitAccount->accCode ?? '' }} - {{ $voucher->debitAccount->accName ?? '' }}
                </span>
            </div>
        </div>

        <!-- ═══ التفاصيل ═══ -->
        <table class="details-table">
            <thead>
                <tr>
                    <th style="width: 50%;">البيان</th>
                    <th style="width: 25%;">المبلغ</th>
                    <th style="width: 25%;">العملة</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>مبلغ مصروف إلى {{ $voucher->creditAccount->accName ?? '' }}</td>
                    <td class="amount-cell">{{ number_format($voucher->amount, 2) }}</td>
                    <td class="currency-cell">
                        {{ $voucher->currency->coinsCode ?? '' }} - {{ $voucher->currency->coinsName ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ═══ المبلغ كتابة ═══ -->
        <div class="amount-words-box">
            <span class="label">المبلغ كتابة</span>
            <span class="words">{{ $amountWords ?? '—' }}</span>
        </div>

        <!-- ═══ الملاحظات ═══ -->
        @if($voucher->notes)
        <div class="notes-box">
            <span class="label">البيان / الملاحظات</span>
            <span class="value">{{ $voucher->notes }}</span>
        </div>
        @endif

        <!-- ═══ التواقيع ═══ -->
        <div class="signatures">
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label">المحاسب</div>
            </div>
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label">المراجع</div>
            </div>
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label">المدير المالي</div>
            </div>
        </div>

        <!-- ═══ التذييل ═══ -->
        <div class="footer">
            <span>{{ $companyName }}</span>
            <span>طُبع في: {{ $printTime }}</span>
        </div>

    </div>

    <script>
        window.onload = function () {
            setTimeout(function () {
                window.print();
            }, 500);
        };
    </script>

</body>
</html>