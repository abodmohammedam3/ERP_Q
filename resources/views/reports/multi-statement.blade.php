<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{{ $report->title() }} — {{ count($accounts) }} حساب</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body { height:100%; }
body {
    font-family:'Segoe UI',Tahoma,Arial,sans-serif;
    font-size:11pt; direction:rtl; color:#000;
    background:#e9ecef;
    padding:70px 15px 40px 15px;
}

/* ══════ Toolbar ══════ */
.ms-toolbar {
    position:fixed; top:0; left:0; right:0; height:56px;
    background:#fff; border-bottom:2px solid #0d6efd;
    display:flex; align-items:center; justify-content:space-between;
    padding:0 20px; z-index:1000;
    box-shadow:0 2px 8px rgba(0,0,0,0.08);
}
.ms-toolbar-left  { display:flex; align-items:center; gap:12px; }
.ms-toolbar-right { display:flex; align-items:center; gap:8px; }
.ms-nav-btn {
    width:38px; height:38px; border:1px solid #0d6efd; border-radius:6px;
    background:#fff; color:#0d6efd; font-weight:700; font-size:16pt;
    cursor:pointer; display:inline-flex; align-items:center; justify-content:center;
}
.ms-nav-btn:hover:not(:disabled) { background:#0d6efd; color:#fff; }
.ms-nav-btn:disabled { opacity:0.35; cursor:not-allowed; }
.ms-counter {
    min-width:100px; text-align:center; font-weight:700; font-size:11pt;
    padding:8px 14px; background:#f0f7ff; border:1px solid #b6d4fe;
    border-radius:6px; color:#084298; direction:ltr;
}
.ms-action-btn {
    padding:8px 16px; border:none; border-radius:6px; cursor:pointer;
    font-weight:700; font-size:10pt; color:#fff;
    display:inline-flex; align-items:center; gap:6px;
}
.ms-action-btn:hover { opacity:0.9; }
.btn-print     { background:#0d6efd; }
.btn-print-all { background:#198754; }
.btn-close     { background:#6c757d; }

/* ══════ Pages ══════ */
#ms-pages { max-width:210mm; margin:0 auto; }

.ms-page {
    position:relative;
    background:#fff;
    padding:15mm 15mm 25mm 15mm;
    margin-bottom:20px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
    display:none;
    min-height:297mm;
}
.ms-page.active { display:block; }

/* ══════ Header ══════ */
.ms-header {
    display:flex; justify-content:space-between; align-items:flex-start;
    padding-bottom:10px; border-bottom:2px solid #0d6efd; margin-bottom:15px;
}
.ms-header-right { text-align:right; }
.ms-header-left  { text-align:left; }
.ms-doc-type  { font-size:16pt; font-weight:700; color:#0d6efd; margin-bottom:5px; }
.ms-company   { font-size:13pt; font-weight:700; color:#333; }
.ms-meta-line { font-size:10pt; color:#555; line-height:1.6; font-weight:600; }

/* ══════ Filters ══════ */
.ms-filters { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:15px; }
.ms-filter-chip {
    border:1px solid #b6d4fe; background:#e7f1ff; color:#084298;
    border-radius:12px; padding:3px 12px; font-size:9.5pt; font-weight:600;
}

/* ══════ Account header ══════ */
.ms-acc-header {
    background:#cfe2ff; padding:10px 14px; border-radius:6px;
    margin-bottom:12px; font-weight:700; color:#084298; font-size:11pt;
}

/* ══════ Table ══════ */
.ms-table { width:100%; border-collapse:collapse; font-size:10pt; margin-bottom:15px; }
.ms-table thead th {
    background:#e7f1ff; color:#0d6efd; font-weight:700;
    padding:7px 6px; border:1px solid #b6d4fe; text-align:center;
}
.ms-table tbody td {
    padding:6px; border:1px solid #dee2e6; text-align:center; font-weight:600;
}
.ms-table tbody td.text-start { text-align:right; }
.ms-table tbody td.text-end   { text-align:left; direction:ltr; }
.ms-table tbody tr:nth-child(even) { background:#f8f9fa; }
.ms-table tr { page-break-inside:avoid; }

/* ══════ Totals ══════ */
.ms-totals { width:100%; border-collapse:collapse; margin-bottom:15px; font-size:10.5pt; }
.ms-totals td { border:1px solid #dee2e6; padding:7px 10px; }
.ms-totals td.label { background:#f8f9fa; color:#555; font-weight:600; width:25%; }
.ms-totals td.value { font-weight:700; text-align:left; direction:ltr; }

/* ══════ Signatures ══════ */
.ms-signatures {
    display:flex; justify-content:space-around; margin-top:30px;
    page-break-inside:avoid;
}
.ms-signature {
    text-align:center; font-weight:700; font-size:11pt;
    min-width:130px; border-top:1px dashed #000; padding-top:6px;
}

/* ══════ Footer — أسفل الصفحة ══════ */
.ms-footer {
    position:absolute;
    bottom:10mm;
    left:15mm;
    right:15mm;
    padding-top:8px;
    border-top:1px solid #dee2e6;
    font-size:9pt;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-weight:600;
}
.ms-footer-right  { text-align:right; color:#6c757d; flex:1; }
.ms-footer-center { text-align:center; color:#0d6efd; font-weight:700; flex:1; }
.ms-footer-left   { text-align:left; color:#198754; font-weight:700; flex:1; }

/* ════════════════════════ PRINT ════════════════════════ */
@media print {
    body {
        padding:0 !important; margin:0 !important;
        background:#fff !important;
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
    }

    .ms-toolbar, .no-print { display:none !important; }

    #ms-pages { max-width:none !important; margin:0 !important; padding:0 !important; }

    /* ═══ الإصلاح: min-height ثابت = أبعاد صفحة A4 القابلة للطباعة ═══ */
    .ms-page {
        display:block !important;
        padding:10mm 10mm 25mm 10mm !important;
        margin:0 !important;
        box-shadow:none !important;
        min-height:277mm !important;   /* ← A4 (297mm) − هامشين (10mm × 2) */
        page-break-after:always;
        page-break-inside:auto;
        position:relative;
    }

    #ms-pages > .ms-page:last-of-type { page-break-after:auto !important; }

    /* منع الصفحة الفارغة عند طباعة كشف واحد */
    body.printing-single .ms-page { display:none !important; }
    body.printing-single .ms-page.active {
        display:block !important;
        page-break-after:auto !important;
    }

    .ms-table thead { display:table-header-group; }
    .ms-table tr    { page-break-inside:avoid; }
    .ms-signatures  { page-break-inside:avoid; }

    /* ═══ الفوتر — أسفل كل ورقة فعلية ═══ */
    .ms-footer {
        position:absolute;
        bottom:0;
        left:0;
        right:0;
        padding-top:8px;
        border-top:1px solid #dee2e6;
        background:#fff;
    }

    @page { size:A4; margin:10mm; }
}
</style>
</head>
<body>

{{-- ══════ Toolbar ══════ --}}
<div class="ms-toolbar no-print">
    <div class="ms-toolbar-left">
        <button class="ms-nav-btn" id="msPrev" title="السابق">‹</button>
        <div class="ms-counter" id="msCounter">1 / {{ count($accounts) }}</div>
        <button class="ms-nav-btn" id="msNext" title="التالي">›</button>
    </div>
    <div class="ms-toolbar-right">
        <button class="ms-action-btn btn-print" id="msPrintThis">🖨️ طباعة هذا الكشف</button>
        <button class="ms-action-btn btn-print-all" id="msPrintAll">🖨️ طباعة الكل</button>
        <button class="ms-action-btn btn-close" id="msClose">✖ إغلاق</button>
    </div>
</div>

{{-- ══════ Pages ══════ --}}
<div id="ms-pages">

@php
    $filterLabels = collect($report->filters())->pluck('label', 'key');
    $quickQuery = trim((string) ($filters['_q'] ?? ''));

    $applied = collect($filters)
        ->except(['page', '_q'])
        ->map(function ($v, $k) {
            if ($k === 'account_parent' && $v === 'all') return 'كل الحسابات التفصيلية';
            return $v;
        })
        ->filter(fn ($v, $k) => $v !== '' && $v !== null && $filterLabels->has($k));

    $totalAccounts = count($accounts);
@endphp

@foreach ($accounts as $i => $acc)
    @php
        $rows = $acc['rows'];
        if ($quickQuery !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($quickQuery) {
                $haystack = mb_strtolower(implode(' ', array_map(fn ($v) => (string) $v, $r)));
                return mb_strpos($haystack, mb_strtolower($quickQuery)) !== false;
            }));
        }
    @endphp
    <div class="ms-page {{ $i === 0 ? 'active' : '' }}"
         data-acc-id="{{ $acc['id'] }}"
         data-index="{{ $i }}">

        {{-- ════ الرأس ════ --}}
        <div class="ms-header">
            <div class="ms-header-right">
                <div class="ms-doc-type">{{ $report->title() }}</div>
                <div class="ms-company">نظام إدارة الموارد ERP</div>
                <div class="ms-meta-line">{{ $report->description() }}</div>
            </div>
            <div class="ms-header-left">
                <div class="ms-meta-line">تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</div>
                <div class="ms-meta-line">الحساب: {{ $acc['code'] }} - {{ $acc['name'] }}</div>
                <div class="ms-meta-line">عدد الحركات: {{ count($rows) }}</div>
            </div>
        </div>

        {{-- ════ الفلاتر ════ --}}
        @if ($applied->isNotEmpty() || $quickQuery !== '')
            <div class="ms-filters">
                @foreach ($applied as $key => $value)
                    <span class="ms-filter-chip">{{ $filterLabels[$key] }}: {{ $value }}</span>
                @endforeach
                @if ($quickQuery !== '')
                    <span class="ms-filter-chip">بحث: {{ $quickQuery }}</span>
                @endif
            </div>
        @endif

        {{-- ════ رأس الحساب ════ --}}
        <div class="ms-acc-header">
            {{ $acc['code'] }} - {{ $acc['name'] }}
            <span style="float:left;font-weight:400;font-size:9.5pt;">
                {{ count($rows) }} حركة — ختامي: {{ number_format($acc['closing'], 2) }}
            </span>
        </div>

        {{-- ════ الجدول ════ --}}
        <table class="ms-table">
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th>{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($columns as $col)
                            @php
                                $val = $row[$col['key']] ?? null;
                                $type = $col['type'] ?? 'text';
                                $align = in_array($type, ['money','number'])
                                    ? 'text-end'
                                    : ($type === 'date' ? '' : 'text-start');
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
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="text-center text-muted py-3">
                            لا توجد نتائج مطابقة للبحث
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- ════ الإجماليات ════ --}}
        @php
            $accTotals = ['debit' => 0, 'credit' => 0];
            foreach ($rows as $r) {
                $docType = $r['doc_type'] ?? '';
                if ($docType === 'opening' || $docType === 'closing') continue;
                $accTotals['debit']  += (float) ($r['debit']  ?? 0);
                $accTotals['credit'] += (float) ($r['credit'] ?? 0);
            }
        @endphp
        <table class="ms-totals">
            <tr>
                <td class="label">إجمالي مدين</td>
                <td class="value">{{ number_format($accTotals['debit'], 2) }}</td>
                <td class="label">إجمالي دائن</td>
                <td class="value">{{ number_format($accTotals['credit'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">الرصيد الختامي</td>
                <td class="value">{{ number_format($acc['closing'], 2) }}</td>
                <td class="label">عدد الحركات</td>
                <td class="value">{{ count($rows) }}</td>
            </tr>
        </table>

        {{-- ════ التوقيعات ════ --}}
        <div class="ms-signatures">
            <div class="ms-signature">المحاسب</div>
            <div class="ms-signature">المراجع</div>
            <div class="ms-signature">مدير الحسابات</div>
        </div>

        {{-- ════ الفوتر ════ --}}
        <div class="ms-footer">
            <span class="ms-footer-right">جميع الحقوق محفوظة لشركة بيتاسيس ©</span>
            <span class="ms-footer-center">صفحة {{ $i + 1 }} / {{ $totalAccounts }}</span>
            <span class="ms-footer-left">المستخدم: {{ auth()->user()?->name ?? 'مدير النظام' }}</span>
        </div>

    </div>
@endforeach

</div>

<script>
(function () {
    var pages     = Array.from(document.querySelectorAll('.ms-page'));
    var total     = pages.length;
    var current   = 0;

    var counterEl = document.getElementById('msCounter');
    var prevBtn   = document.getElementById('msPrev');
    var nextBtn   = document.getElementById('msNext');
    var printBtn  = document.getElementById('msPrintThis');
    var allBtn    = document.getElementById('msPrintAll');
    var closeBtn  = document.getElementById('msClose');

    function update() {
        pages.forEach(function (p, i) {
            p.classList.toggle('active', i === current);
        });
        counterEl.textContent = (current + 1) + ' / ' + total;
        prevBtn.disabled = (current === 0);
        nextBtn.disabled = (current === total - 1);
        window.scrollTo(0, 0);
    }

    prevBtn.addEventListener('click', function () {
        if (current > 0) { current--; update(); }
    });
    nextBtn.addEventListener('click', function () {
        if (current < total - 1) { current++; update(); }
    });

    printBtn.addEventListener('click', function () {
        document.body.classList.add('printing-single');
        window.print();
        setTimeout(function () {
            document.body.classList.remove('printing-single');
        }, 800);
    });

    allBtn.addEventListener('click', function () {
        document.body.classList.remove('printing-single');
        window.print();
    });

    closeBtn.addEventListener('click', function () {
        window.close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' && current > 0) { current--; update(); }
        if (e.key === 'ArrowLeft'  && current < total - 1) { current++; update(); }
    });

    update();
})();
</script>

</body>
</html>