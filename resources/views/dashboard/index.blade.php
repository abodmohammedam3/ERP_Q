@extends('layouts.app')

@section('title', 'لوحة التحكم ')

@section('content')
<div class="py-3">

    <!-- ========================================= -->
    <!-- عنوان لوحة التحكم -->
    <!-- ========================================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                لوحة التحكم
            </h4>

            <p class="text-muted mb-0">
                ملخص العمليات والحركة الحالية في النظام
            </p>

        </div>

        <div>

            <span class="badge text-bg-secondary">

                <i class="bi bi-calendar3"></i>

                <span id="dashboardDate">
                    اليوم
                </span>

            </span>

        </div>

    </div>

    {{-- شريط خطأ خفيف: يظهر فقط إن فشل جلب البيانات، ولا يمنع عرض SSR --}}
    <div id="dashboardErrorBanner"
         class="alert alert-warning d-none d-flex align-items-center gap-2 py-2"
         role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span class="flex-grow-1">
            تعذّر تحديث بيانات لوحة المعلومات.
            <small class="text-muted">قد تكون الأرقام المعروضة غير محدّثة.</small>
        </span>
        <button type="button"
                class="btn btn-sm btn-outline-dark"
                onclick="window.Dashboard && window.Dashboard.refresh()">
            إعادة المحاولة
        </button>
    </div>

    @include('dashboard.SystemIndicators')
    @include('dashboard.SummaryOperations')
    @include('dashboard.SupplyDistribution')
    {{-- @include('dashboard.Inventory') --}}
    {{-- @include('dashboard.SystemAlerts') --}}

</div>
@endsection
@push('scripts')
{{--
    SSR محدود: المؤشرات + الملخص + رقم الإصدار فقط (7 أرقام).
    الغرض: منع ظهور "0" كاذب قبل انتهاء الجلب (SystemIndicators:27).

    الضمانة الأمنية: JSON_HEX_* نفس نمط reports/center.blade.php:136-140،
    لأن JSON_HEX_TAG يمنع الإغلاق المبكر لوسم <script> عبر أي نص
    قادم من قاعدة البيانات (اسم عميل/بيان).

    تصحيح مصاحب: @include('dashboard.summaryOperations') كان بحرف s صغير
    بينما الملف SummaryOperations.blade.php بحرف S — يعمل على Windows فقط
    ويفشل على Linux بـ View not found (موثّق في تقارير فحص/8.md:102-152).
--}}
<script>
    window.DASHBOARD_INITIAL = {!! json_encode([
        'indicators' => $initial['indicators'] ?? [],
        'summary'    => $initial['summary'] ?? [],
        'version'    => $initial['version'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!};
</script>

@vite(['resources/js/pages/dashboard.js'])
@endpush
