<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>عملية فرز {{ $documentNumber }}</title>
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
        .doc-type {
            font-size: 16pt;
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 5px;
        }
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

        .sorting-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .sorting-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .sorting-card.out { border-color: #dc3545; }
        .sorting-card.in  { border-color: #198754; }

        .sorting-card-header {
            padding: 10px 15px;
            font-weight: 700;
            font-size: 12pt;
            color: #fff;
        }
        .sorting-card.out .sorting-card-header { background: #dc3545; }
        .sorting-card.in  .sorting-card-header { background: #198754; }

        .sorting-card-body { padding: 12px; }

        .sorting-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px dashed #e9ecef;
            font-size: 10pt;
        }
        .sorting-row:last-child { border-bottom: none; }
        .sorting-row strong { color: #0d6efd; }

        .sorting-summary {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-around;
        }
        .summary-item {
            text-align: center;
        }
        .summary-item .label {
            display: block;
            font-size: 9pt;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .summary-item .value {
            font-size: 14pt;
            font-weight: 700;
            color: #0d6efd;
            direction: ltr;
        }
        .summary-item .value.unit-cost { color: #dc3545; }
        .summary-item .value.total     { color: #198754; }

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
    $outDetail = optional($outMovement->details)->first();
    $inDetail  = optional($inMovement->details)->first();
    $outTotal  = $outMovement->total;
    $inTotal   = $inMovement->total;
    $unitCost  = $inDetail ? (float) $inDetail->unit_cost : 0;
@endphp

{{-- Header --}}
<div class="header">
    <div>
        <div class="doc-type">عملية فرز مخزون</div>
        @if(config('company.name'))
            <div class="company-name">{{ config('company.name') }}</div>
        @endif
    </div>
    <div style="text-align: left;">
        <div class="meta-line">رقم العملية: <strong>{{ $documentNumber }}</strong></div>
        <div class="meta-line">التاريخ: <strong>{{ $outMovement->movement_date?->format('Y-m-d') }}</strong></div>
    </div>
</div>

{{-- Info Grid --}}
<div class="info-grid">
    <div class="info-item">
        <span class="info-label">الصنف</span>
        <span class="info-value">{{ $outDetail->item->itemName2 ?? '—' }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">النوع</span>
        <span class="info-value">{{ $outDetail->type->name ?? '—' }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">المخزن</span>
        <span class="info-value">{{ $outMovement->warehouse->StockName ?? '—' }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">الرمز</span>
        <span class="info-value">{{ $outDetail->code ?? '—' }}</span>
    </div>
</div>

{{-- Sorting Cards --}}
<div class="sorting-grid">
    <div class="sorting-card out">
        <div class="sorting-card-header">
            <i>📤</i> الكمية الأصلية (صرف)
        </div>
        <div class="sorting-card-body">
            <div class="sorting-row">
                <span>الوحدة:</span>
                <strong>{{ $outDetail->unit->UnitName ?? '—' }}</strong>
            </div>
            <div class="sorting-row">
                <span>الكمية:</span>
                <strong>{{ number_format((float) $outDetail->quantity, 3) }}</strong>
            </div>
            <div class="sorting-row">
                <span>تكلفة الوحدة:</span>
                <strong>{{ number_format((float) $outDetail->unit_cost, 2) }}</strong>
            </div>
            <div class="sorting-row">
                <span>الإجمالي:</span>
                <strong>{{ number_format($outTotal, 2) }}</strong>
            </div>
        </div>
    </div>

    <div class="sorting-card in">
        <div class="sorting-card-header">
            <i>📥</i> الوحدات الناتجة (توريد)
        </div>
        <div class="sorting-card-body">
            <div class="sorting-row">
                <span>الوحدة:</span>
                <strong>{{ $inDetail->unit->UnitName ?? '—' }}</strong>
            </div>
            <div class="sorting-row">
                <span>الكمية:</span>
                <strong>{{ number_format((float) $inDetail->quantity, 2) }}</strong>
            </div>
            <div class="sorting-row">
                <span>تكلفة الوحدة:</span>
                <strong>{{ number_format($unitCost, 2) }}</strong>
            </div>
            <div class="sorting-row">
                <span>الإجمالي:</span>
                <strong>{{ number_format($inTotal, 2) }}</strong>
            </div>
        </div>
    </div>
</div>

{{-- Summary --}}
<div class="sorting-summary">
    <div class="summary-item">
        <span class="label">الكمية الأصلية</span>
        <span class="value">{{ number_format((float) $outDetail->quantity, 3) }} {{ $outDetail->unit->UnitName ?? '' }}</span>
    </div>
    <div class="summary-item">
        <span class="label">الوحدات الناتجة</span>
        <span class="value">{{ number_format((float) $inDetail->quantity, 2) }} {{ $inDetail->unit->UnitName ?? '' }}</span>
    </div>
    <div class="summary-item">
        <span class="label">تكلفة الوحدة الناتجة</span>
        <span class="value unit-cost">{{ number_format($unitCost, 2) }}</span>
    </div>
    <div class="summary-item">
        <span class="label">القيمة الإجمالية</span>
        <span class="value total">{{ number_format($inTotal, 2) }}</span>
    </div>
</div>

{{-- Footer --}}
<div class="footer">
    <span>جميع الحقوق محفوظة ©</span>
    <span>المستخدم: مدير النظام</span>
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