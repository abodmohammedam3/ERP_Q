<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>حركة مخزون رقم {{ $movement->display_id }}</title>
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

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 10px;
            border-bottom: 2px solid #0d6efd;
            margin-bottom: 15px;
        }
        .doc-type { font-size: 16pt; font-weight: 700; color: #0d6efd; margin-bottom: 5px; }
        .company-name { font-size: 13pt; font-weight: 700; color: #333; }
        .meta-line { font-size: 10pt; color: #555; line-height: 1.6; font-weight: 600; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 15px;
        }
        .info-item { border: 1px solid #dee2e6; padding: 6px 10px; font-size: 10pt; }
        .info-label { color: #6c757d; font-size: 9pt; display: block; margin-bottom: 2px; font-weight: 600; }
        .info-value { font-weight: 700; color: #000; }

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
        }
        .items-table tbody td {
            padding: 6px;
            border: 1px solid #dee2e6;
            text-align: center;
            font-weight: 600;
        }
        .items-table tbody td.text-start { text-align: right; }
        .items-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .items-table tbody td.col-total { color: #dc3545; font-weight: 700; }

        .totals {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 11pt;
        }
        .totals td {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            font-weight: 700;
        }
        .totals .label { background: #f8f9fa; width: 30%; text-align: right; }
        .totals .value { text-align: left; font-size: 12pt; color: #0d6efd; }

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
        }

        @media print {
            body { padding: 0; padding-bottom: 40px; }
            .no-print { display: none !important; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>

@php
    $directionLabel = $movement->direction === 'in' ? 'دخول' : 'خروج';
    $typeLabel = \App\Models\Inventory\InventoryMovement::labelForType($movement->movement_type);
@endphp

{{-- Header --}}
<div class="header">
    <div>
        <div class="doc-type">حركة مخزون — {{ $typeLabel }}</div>
        @if(config('company.name'))
            <div class="company-name">{{ config('company.name') }}</div>
        @endif
    </div>
    <div style="text-align: left;">
        <div class="meta-line">رقم الحركة: <strong>{{ $movement->display_id }}</strong></div>
        <div class="meta-line">التاريخ: <strong>{{ $movement->movement_date?->format('Y-m-d') }}</strong></div>
    </div>
</div>

{{-- Info Grid --}}
<div class="info-grid">
    <div class="info-item">
        <span class="info-label">نوع الحركة</span>
        <span class="info-value">{{ $typeLabel }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">الاتجاه</span>
        <span class="info-value">{{ $directionLabel }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">المخزن</span>
        <span class="info-value">{{ $movement->warehouse->StockName ?? '—' }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">رقم المستند</span>
        <span class="info-value">{{ $movement->document_number ?? '—' }}</span>
    </div>
</div>

{{-- Items Table --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 20%;">الصنف</th>
            <th style="width: 12%;">النوع</th>
            <th style="width: 10%;">الرمز</th>
            <th style="width: 10%;">الوحدة</th>
            <th style="width: 13%;">الكمية</th>
            <th style="width: 15%;">تكلفة الوحدة</th>
            <th style="width: 15%;">الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movement->details as $index => $detail)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="text-start">{{ $detail->item->itemName2 ?? '—' }}</td>
                <td>{{ $detail->type->name ?? '—' }}</td>
                <td>{{ $detail->code ?? '—' }}</td>
                <td>{{ $detail->unit->UnitName ?? '—' }}</td>
                <td>{{ number_format((float) $detail->quantity, 3) }}</td>
                <td>{{ number_format((float) $detail->unit_cost, 2) }}</td>
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
    <tr>
        <td class="label">عدد الأصناف</td>
        <td class="value">{{ $movement->details->count() }}</td>
    </tr>
    <tr>
        <td class="label">إجمالي الحركة</td>
        <td class="value">{{ number_format((float) $movement->total, 2) }}</td>
    </tr>
</table>

{{-- البيان --}}
@if($movement->statement)
    <div style="border: 1px solid #dee2e6; padding: 10px; background: #f8f9fa; margin-bottom: 15px;">
        <strong style="color: #0d6efd;">البيان:</strong>
        {{ $movement->statement }}
    </div>
@endif

{{-- Footer --}}
<div class="footer">
    <span>جميع الحقوق محفوظة ©</span>
    <span>رقم المستند: {{ $movement->document_number ?? '—' }}</span>
</div>

{{-- Print Buttons --}}
<div class="no-print" style="text-align: center; margin-top: 40px;">
    <button onclick="window.print()" style="padding: 10px 30px; font-size: 14px; background: #0d6efd; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700;">
        🖨️ طباعة
    </button>
    <button onclick="window.close()" style="padding: 10px 30px; font-size: 14px; background: #6c757d; color: #fff; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px; font-weight: 700;">
        إغلاق
    </button>
</div>

</body>
</html>