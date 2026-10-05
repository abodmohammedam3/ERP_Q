@extends('layouts.app')

@section('title', 'مركز التقارير')

@section('content')

@php
    // تصنيفات التقارير (للعرض في القائمة الجانبية للشاشة)
    $categories = [
        'accounting' => ['label' => 'محاسبة', 'icon' => 'calculator'],
        'sales'      => ['label' => 'مبيعات', 'icon' => 'cart-check'],
        'purchases'  => ['label' => 'مشتريات', 'icon' => 'cart-plus'],
        'inventory'  => ['label' => 'مخزون',  'icon' => 'boxes'],
        'vouchers'   => ['label' => 'سندات',  'icon' => 'cash-stack'],
    ];

    $grouped = collect($definitions)->groupBy('category');
@endphp

<div class="d-flex gap-3 align-items-start">

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- العمود الجانبي: قائمة التقارير بالتصنيفات       --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <aside class="card border-0 shadow-sm rc-sidebar flex-shrink-0">
        <div class="card-body p-2" id="rcReportList">

            @foreach ($grouped as $category => $items)
                @php $catMeta = $categories[$category] ?? ['label' => $category, 'icon' => 'folder']; @endphp

                <div class="rc-cat">
                    <i class="bi bi-{{ $catMeta['icon'] }}"></i>
                    {{ $catMeta['label'] }}
                </div>

                @foreach ($items as $def)
                    <button type="button"
                            class="rc-report-item {{ $def['key'] === $activeKey ? 'active' : '' }}"
                            data-report-key="{{ $def['key'] }}">
                        <i class="bi bi-{{ $def['icon'] }} mt-1"></i>
                        <span>
                            <span class="rc-item-title d-block">{{ $def['title'] }}</span>
                            <span class="rc-item-desc d-block">{{ $def['description'] }}</span>
                        </span>
                    </button>
                @endforeach
            @endforeach

        </div>
    </aside>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- المحتوى الرئيسي                                  --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="flex-grow-1 rc-main">

        {{-- ── الشريط العلوي: العنوان + أزرار العمليات ── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div class="d-flex align-items-center gap-3">
                    <span class="rc-title-icon" id="rcReportIconWrap">
                        <i id="rcReportIcon" class="bi bi-journal-text"></i>
                    </span>
                    <div>
                        <h5 class="mb-0 fw-bold" id="rcReportTitle">—</h5>
                        <small class="text-muted" id="rcReportDesc"></small>
                    </div>
                    <span class="badge text-bg-primary" id="rcReportCategory"></span>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="rcBtnRun">
                        <i class="bi bi-play-circle"></i> عرض
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="rcBtnPrint">
                        <i class="bi bi-printer"></i> طباعة
                    </button>
                    <button type="button" class="btn btn-outline-success" id="rcBtnExport">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                    </button>
                </div>

            </div>
        </div>

        {{-- ── بطاقة الفلاتر (تُبنى ديناميكياً من التعريف) ── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end" id="rcFilters"></div>
            </div>
        </div>

        {{-- ── شرائح الإجماليات (KPI) ── --}}
        <div class="d-flex flex-wrap gap-2 mb-3" id="rcTotals"></div>

        {{-- ── جدول التقرير ── --}}
        <div class="card border-0 shadow-sm">

            <div class="card-body py-2 border-bottom d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <small class="text-muted" id="rcRowCount">—</small>
                <input type="text"
                       id="rcQuickSearch"
                       class="form-control form-control-sm"
                       style="max-width: 260px;"
                       placeholder="بحث سريع في النتائج المعروضة..."
                       autocomplete="off">
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="rcTable">
                    <thead class="table-light" id="rcTableHead"></thead>
                    <tbody id="rcTableBody">
                        <tr>
                            <td class="rc-empty">اختر تقريراً واضغط "عرض" لتحميل البيانات</td>
                        </tr>
                    </tbody>
                    <tfoot id="rcTableFoot"></tfoot>
                </table>
            </div>

            <div class="p-3 border-top" id="rcPagination"></div>

        </div>

    </div>
</div>

@endsection

@push('scripts')
    <script>
        window.REPORT_CENTER = {!! json_encode([
            'definitions' => $definitions,
            'activeKey'   => $activeKey,
            'urls'        => $urls,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!};
    </script>
    @vite(['resources/js/pages/report-center.js'])
@endpush
