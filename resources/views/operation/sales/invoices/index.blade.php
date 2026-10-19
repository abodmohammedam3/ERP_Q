@extends('layouts.app')

@section('title', 'فواتير البيع | نظام ERP')

@section('content')

<div class="container-fluid py-3">

    <!-- عنوان الشاشة والأزرار -->
    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="mb-1">فاتورة بيع</h4>
            <small class="text-muted">إدارة فواتير المبيعات للعملاء</small>
        </div>

        <div class="btn-group" role="group">

            <button
                type="button"
                class="btn btn-primary"
                id="btnNewSalesInvoice"
                onclick="resetSalesInvoice()"
            >
                <i class="bi bi-plus-lg"></i>
                إضافة فاتورة
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnSearchSalesInvoice"
                onclick="searchSalesInvoice()"
            >
                <i class="bi bi-search"></i>
                بحث
            </button>

        </div>

    </div>


    <!-- رأس الفاتورة -->
    @include('operation.sales.invoices.header')

    <!-- تفاصيل الفاتورة -->
    @include('operation.sales.invoices.detalis')

</div>

@include('shared.lookup-modal')

{{-- ✅ نافذة البحث عن فاتورة بيع — ملف واحد فقط (search.blade.php)
     كان هناك نسخة مكررة هنا بنفس id بدون عمود «ت» (colspan=8)،
     وقد حُذفت لتجنّب ازدواج عناصر DOM بنفس المعرّف. --}}
@include('operation.sales.invoices.search')

@endsection


@push('scripts')
<script>
    window.SALES_SYSTEM_CURRENCY_ID   = @json($systemCurrencyId ?? null);
    window.SALES_SYSTEM_CURRENCY_CODE = @json($systemCurrencyCode ?? '');
</script>
@vite(['resources/js/pages/sales-invoice.js'])
@endpush