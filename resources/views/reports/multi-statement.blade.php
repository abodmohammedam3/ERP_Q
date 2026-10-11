<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{{ $report->title() }} — {{ count($accounts) }} حساب</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Tahoma,sans-serif; font-size:11pt; direction:rtl; background:#fff; padding:15px; padding-bottom:60px; }
.ms-header { display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:10px; border-bottom:2px solid #0d6efd; margin-bottom:15px; }
.ms-title { font-size:16pt; font-weight:700; color:#0d6efd; }
.ms-meta  { font-size:10pt; color:#555; }
.ms-filters { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
.ms-filter-chip { border:1px solid #b6d4fe; background:#e7f1ff; color:#084298; border-radius:12px; padding:3px 12px; font-size:9.5pt; font-weight:600; }
.ms-totals { width:100%; border-collapse:collapse; margin-bottom:15px; font-size:10.5pt; }
.ms-totals td { border:1px solid #dee2e6; padding:7px 10px; }
.ms-totals td.label { background:#f8f9fa; color:#555; font-weight:600; width:25%; }
.ms-totals td.value { font-weight:700; text-align:left; direction:ltr; }
.ms-tabs { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:15px; padding:8px; background:#f8f9fa; border-radius:6px; }
.ms-tab { padding:8px 16px; border:1px solid #0d6efd; border-radius:6px; cursor:pointer; background:#fff; color:#0d6efd; font-weight:600; font-size:10pt; }
.ms-tab.active { background:#0d6efd; color:#fff; }
.ms-panel { display:none; }
.ms-panel.active { display:block; }
.ms-acc-header { background:#cfe2ff; padding:10px 14px; border-radius:6px; margin-bottom:12px; font-weight:700; color:#084298; }
.ms-table { width:100%; border-collapse:collapse; font-size:10pt; margin-bottom:15px; }
.ms-table th { background:#e7f1ff; color:#0d6efd; padding:7px 6px; border:1px solid #b6d4fe; text-align:center; }
.ms-table td { padding:6px; border:1px solid #dee2e6; text-align:center; }
.ms-table td.text-end { text-align:left; direction:ltr; }
.ms-table td.text-start { text-align:right; }
.ms-footer { position:fixed; bottom:0; left:0; right:0; padding:8px 15px; background:#fff; border-top:1px solid #dee2e6; font-size:9pt; display:flex; justify-content:space-between; z-index:100; }
.no-print { margin-top:20px; text-align:center; }
.no-print button { padding:10px 30px; font-size:14px; border:none; border-radius:6px; cursor:pointer; font-weight:700; margin:0 5px; color:#fff; }
.btn-print { background:#0d6efd; }
.btn-close { background:#6c757d; }
@media print {
    .ms-tabs, .no-print { display:none !important; }
    #ms-panels > .ms-panel { display:block !important; page-break-after:always; }
    #ms-panels > .ms-panel:last-child { page-break-after:auto; }
    .ms-acc-header { page-break-after:avoid; }
    @page { size:A4; margin:10mm; }
}
</style>
</head>
<body>
<div class="ms-header">
    <div>
        <div class="ms-title">{{ $report->title() }}</div>
        <div class="ms-meta">{{ $report->description() }}</div>
    </div>
    <div class="ms-meta" style="text-align:left;">
        <div>عدد الحسابات: {{ count($accounts) }}</div>
        <div>تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</div>
    </div>
</div>

@php
    $filterLabels = collect($report->filters())->pluck('label', 'key');
@endphp
@php
    $applied = collect($filters)->except(['page'])->filter(fn ($v, $k) => $v !== '' && $v !== null && $filterLabels->has($k));
@endphp
@if ($applied->isNotEmpty())
    <div class="ms-filters">
        @foreach ($applied as $key => $value)
            <span class="ms-filter-chip">{{ $filterLabels[$key] }}: {{ $value }}</span>
        @endforeach
    </div>
@endif

@if (count($totals) > 0)
    <table class="ms-totals">
        @foreach ($totals as $key => $value)
            <tr>
                <td class="label">{{ collect($columns)->firstWhere('key', $key)['label'] ?? $key }}</td>
                <td class="value">{{ number_format((float) $value, 2) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<div class="ms-tabs">
    @foreach ($accounts as $i => $acc)
        <button class="ms-tab {{ $i === 0 ? 'active' : '' }}" data-target="ms-panel-{{ $acc['id'] }}">
            {{ $acc['code'] }} - {{ $acc['name'] }}
        </button>
    @endforeach
</div>
<div id="ms-panels">
    @foreach ($accounts as $i => $acc)
        <div class="ms-panel {{ $i === 0 ? 'active' : '' }}" id="ms-panel-{{ $acc['id'] }}">
            <div class="ms-acc-header">
                {{ $acc['code'] }} - {{ $acc['name'] }}
                <span style="float:left;font-weight:400;font-size:9.5pt;">
                    {{ $acc['rows_count'] }} حركة — ختامي: {{ number_format($acc['closing'], 2) }}
                </span>
            </div>
            <table class="ms-table">
                <thead>
                    <tr>
                        @foreach ($columns as $col)
                            <th>{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($acc['rows'] as $row)
                        <tr>
                            @foreach ($columns as $col)
                                @php
                                    $val = $row[$col['key']] ?? null;
                                    $type = $col['type'] ?? 'text';
                                    $align = in_array($type, ['money','number']) ? 'text-end' : ($type === 'date' ? '' : 'text-start');
                                @endphp
                                <td class="{{ $align }}">
                                    @if ($val === null || $val === '') —
                                    @elseif ($type === 'money') {{ number_format((float) $val, 2) }}
                                    @elseif ($type === 'number') {{ number_format((float) $val, 3) }}
                                    @else {{ $val }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>

<div class="ms-footer">
    <span>جميع الحقوق محفوظة</span>
    <span>{{ now()->format('Y-m-d') }}</span>
</div>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">طباعة الكل</button>
    <button class="btn-close" onclick="window.close()">اغلاق</button>
</div>

<script>
document.querySelectorAll('.ms-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.ms-tab').forEach(function(t){ t.classList.remove('active'); });
        document.querySelectorAll('.ms-panel').forEach(function(p){ p.classList.remove('active'); });
        tab.classList.add('active');
        var panel = document.getElementById(tab.dataset.target);
        if (panel) panel.classList.add('active');
    });
});
</script>
</body>
</html>
